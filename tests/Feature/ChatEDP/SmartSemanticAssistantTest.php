<?php

namespace Tests\Feature\ChatEDP;

use Tests\TestCase;
use App\Models\Connection;
use App\Models\AiSemanticDefinition;
use App\Services\AI\OpenRouterClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class SmartSemanticAssistantTest extends TestCase
{
    // Use an in-memory or transactions approach depending on the test setup
    // use RefreshDatabase; // Omitted if testing against existing test DB without migrations
    
    protected $connection;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup mock connection
        $this->connection = Connection::create([
            'name' => 'Test AI Source',
            'type' => 'mysql',
            'is_analytics_source' => true,
            'read_only' => true,
            'credentials' => json_encode([
                'host' => '127.0.0.1',
                'database' => 'odatav4_testing',
                'username' => 'root',
                'password' => ''
            ])
        ]);

        // Setup semantic mapping for sales
        AiSemanticDefinition::create([
            'connection_id' => $this->connection->id,
            'name' => 'sales',
            'type' => 'metric',
            'source_table' => 'acm_datamentahpenjualan',
            'source_column' => 'NetAmount',
            'expression' => 'SUM(NetAmount)',
            'metadata' => json_encode(['is_default' => true, 'aliases' => ['penjualan', 'omzet']])
        ]);
        
        // Setup semantic mapping for branch
        AiSemanticDefinition::create([
            'connection_id' => $this->connection->id,
            'name' => 'branch',
            'type' => 'dimension',
            'source_table' => 'acm_datamentahpenjualan',
            'source_column' => 'BranchCD',
            'metadata' => json_encode(['aliases' => ['cabang']])
        ]);

        // Setup semantic mapping for date
        AiSemanticDefinition::create([
            'connection_id' => $this->connection->id,
            'name' => 'transaction_date',
            'type' => 'dimension',
            'source_table' => 'acm_datamentahpenjualan',
            'source_column' => 'DocDate',
            'metadata' => json_encode(['is_default_date' => true, 'aliases' => ['tanggal']])
        ]);
    }

    public function test_end_to_end_sales_analysis_with_date_and_filter()
    {
        // Mock OpenRouterClient for Planner (Response 1) and Interpretation (Response 2)
        $mockOpenRouter = $this->mock(OpenRouterClient::class);
        
        // Mock the planner response
        $mockOpenRouter->shouldReceive('getCompletion')
            ->once()
            ->withArgs(function($messages, $jsonMode) {
                return $jsonMode === true && str_contains(end($messages)['content'], 'Berapa rata-rata penjualan untuk cabang Bandung bulan ini?');
            })
            ->andReturn(json_encode([
                'intent' => 'sales_analysis',
                'metrics' => [
                    ['name' => 'sales', 'aggregation' => 'avg']
                ],
                'dimensions' => [],
                'filters' => [
                    ['field' => 'branch', 'operator' => '=', 'value' => 'Bandung']
                ],
                'date_context' => 'current_month'
            ]));

        // We aren't testing the actual HTTP endpoint since auth/conversations might be complex to seed.
        // We will test the service chain directly.
        
        $planner = app(\App\Services\AI\QueryPlannerService::class);
        $plan = $planner->plan("Berapa rata-rata penjualan untuk cabang Bandung bulan ini?", $this->connection->id);

        $this->assertEquals('sales_analysis', $plan->intent);
        $this->assertCount(1, $plan->metrics);
        $this->assertEquals('sales', $plan->metrics[0]['name']);
        
        // Next step in chain
        $dateResolver = app(\App\Services\AI\DateContextResolverService::class);
        $plan = $dateResolver->resolve($plan);
        
        // Ensure date filter was injected
        $hasDateFilter = false;
        foreach ($plan->filters as $f) {
            if ($f['field'] === 'date' && $f['operator'] === 'BETWEEN') {
                $hasDateFilter = true;
                break;
            }
        }
        $this->assertTrue($hasDateFilter, 'Date filter should be injected by DateContextResolverService');

        // Next step in chain: Validator
        $validator = app(\App\Services\AI\QueryValidatorService::class);
        $plan = $validator->validateAndResolve($plan, $this->connection, null);
        
        // Verify logical -> physical resolution
        $this->assertCount(1, $plan->tables);
        $this->assertEquals('acm_datamentahpenjualan', strtolower($plan->tables[0]));
        $this->assertEquals('NetAmount', $plan->metrics[0]['name']);
        
        $hasDocDateFilter = false;
        $hasBranchFilter = false;
        foreach ($plan->filters as $f) {
            if ($f['field'] === 'DocDate') $hasDocDateFilter = true;
            if ($f['field'] === 'BranchCD') $hasBranchFilter = true;
        }
        $this->assertTrue($hasDocDateFilter, 'Logical date field resolved to DocDate');
        $this->assertTrue($hasBranchFilter, 'Logical branch field resolved to BranchCD');

        // Next step in chain: Compiler
        $compiler = app(\App\Services\AI\QueryCompilerService::class);
        [$sql, $bindings] = $compiler->compile($plan, $this->connection);
        
        // Verify SQL output structure
        $this->assertStringContainsString('AVG(NetAmount) AS sales_AVG', $sql);
        $this->assertStringContainsString('FROM `acm_datamentahpenjualan`', $sql);
        $this->assertStringContainsString('`DocDate` >=', $sql);
        $this->assertStringContainsString('`DocDate` <=', $sql);
        $this->assertStringContainsString('`BranchCD` = ?', $sql);
        
        // Verify Parameter bindings
        $this->assertContains('Bandung', $bindings);
    }
}
