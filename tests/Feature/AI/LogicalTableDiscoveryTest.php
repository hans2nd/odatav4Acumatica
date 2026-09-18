<?php

namespace Tests\Feature\AI;

use App\DTO\AI\QueryPlan;
use App\Models\Connection;
use App\Models\User;
use App\Services\AI\QueryValidatorService;
use App\Exceptions\AI\TableResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LogicalTableDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected $validator;
    protected $connection;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->validator = new QueryValidatorService();
        
        $this->connection = Connection::create([
            'name' => 'Analytics DB',
            'key' => 'analytics_db',
            'type' => 'mysql',
        ]);
        $this->connection->is_analytics_source = true;
        $this->connection->save();

        DB::table('sync_metadata_definitions')->insert([
            'id' => 1,
            'entity_key' => 'acm_datamentahpenjualan',
            'connection_id' => $this->connection->id,
            'logical_name' => 'DataMentahPenjualan',
            'aliases' => json_encode(['data mentah penjualan', 'penjualan mentah', 'raw sales']),
            'description' => 'Raw synchronized sales transaction data'
        ]);

        DB::table('sync_metadata_fields')->insert([
            ['definition_id' => 1, 'field_name' => 'OrderDate'],
            ['definition_id' => 1, 'field_name' => 'OrderTotal'],
        ]);

        // Ambiguous setup
        DB::table('sync_metadata_definitions')->insert([
            'id' => 2,
            'entity_key' => 'acm_sales_other',
            'connection_id' => $this->connection->id,
            'logical_name' => 'OtherSales',
            'aliases' => json_encode(['raw sales backup']),
            'description' => 'Backup sales data'
        ]);
        
        DB::table('sync_metadata_fields')->insert([
            ['definition_id' => 2, 'field_name' => 'OrderDate'],
        ]);
    }

    public function test_resolves_exact_physical_table()
    {
        $plan = QueryPlan::fromArray([
            'tables' => ['acm_datamentahpenjualan'],
            'columns' => ['OrderDate']
        ]);

        $validated = $this->validator->validate($plan, $this->connection);
        
        $this->assertEquals(['acm_datamentahpenjualan'], $validated->tables);
    }

    public function test_resolves_exact_logical_name()
    {
        $plan = QueryPlan::fromArray([
            'tables' => ['DataMentahPenjualan'],
            'columns' => ['OrderDate']
        ]);

        $validated = $this->validator->validate($plan, $this->connection);
        
        $this->assertEquals(['acm_datamentahpenjualan'], $validated->tables);
    }

    public function test_resolves_exact_alias()
    {
        $plan = QueryPlan::fromArray([
            'tables' => ['penjualan mentah'],
            'columns' => ['OrderDate']
        ]);

        $validated = $this->validator->validate($plan, $this->connection);
        
        $this->assertEquals(['acm_datamentahpenjualan'], $validated->tables);
    }

    public function test_resolves_partial_discovery()
    {
        // LLM generates something close
        $plan = QueryPlan::fromArray([
            'tables' => ['%datamentah%'],
            'columns' => ['OrderDate']
        ]);

        $validated = $this->validator->validate($plan, $this->connection);
        
        $this->assertEquals(['acm_datamentahpenjualan'], $validated->tables);
    }

    public function test_ambiguous_resolution_throws_exception()
    {
        $this->expectException(TableResolutionException::class);
        $this->expectExceptionCode(0);
        
        $plan = QueryPlan::fromArray([
            'tables' => ['%sales%'], // matches 'raw sales' alias and 'OtherSales' or 'acm_sales_other'
            'columns' => ['OrderDate']
        ]);

        try {
            $this->validator->validate($plan, $this->connection);
        } catch (TableResolutionException $e) {
            $this->assertEquals(TableResolutionException::TYPE_AMBIGUOUS, $e->getResolutionType());
            throw $e;
        }
    }

    public function test_not_found_resolution_throws_exception()
    {
        $this->expectException(TableResolutionException::class);
        
        $plan = QueryPlan::fromArray([
            'tables' => ['unknown_table'],
            'columns' => ['OrderDate']
        ]);

        try {
            $this->validator->validate($plan, $this->connection);
        } catch (TableResolutionException $e) {
            $this->assertEquals(TableResolutionException::TYPE_NOT_FOUND, $e->getResolutionType());
            throw $e;
        }
    }
}
