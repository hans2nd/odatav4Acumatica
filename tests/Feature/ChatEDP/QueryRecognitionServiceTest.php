<?php

namespace Tests\Feature\ChatEDP;

use Tests\TestCase;
use App\Services\AI\QueryRecognitionService;
use App\Services\AI\SemanticCandidateDiscoveryService;
use App\Models\AiQueryLibrary;
use App\Models\Connection;

class QueryRecognitionServiceTest extends TestCase
{
    protected QueryRecognitionService $recognizer;
    protected $discoveryServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the Discovery Service
        $this->discoveryServiceMock = \Mockery::mock(SemanticCandidateDiscoveryService::class);
        
        $this->recognizer = new QueryRecognitionService($this->discoveryServiceMock);
        
        config(['chatedp.query_recognition' => [
            'enabled' => true,
            'min_fast_path_confidence' => 0.95
        ]]);
    }

    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_recognizes_simple_sales_query_with_filter()
    {
        $connection = Connection::factory()->create();
        $requestText = "Berapa total penjualan Bandung bulan ini?";

        $this->discoveryServiceMock->shouldReceive('discoverCandidates')
            ->once()
            ->andReturn([
                'metrics' => [['name' => 'penjualan', 'aggregation' => 'SUM']],
                'dimensions' => ['branch']
            ]);

        AiQueryLibrary::factory()->create([
            'connection_id' => $connection->id,
            'is_active' => true,
            'canonical_plan_json' => [
                'metrics' => [['name' => 'penjualan', 'aggregation' => 'sum']],
                'dimensions' => [],
                'filters' => [['field' => 'branch', 'operator' => '=']]
            ]
        ]);

        $result = $this->recognizer->recognize($requestText, $connection->id);

        $this->assertNotNull($result);
        $this->assertEquals('recognized', $result['status']);
        $this->assertEquals(1.0, $result['confidence']); 
        $this->assertEquals('this_month', $result['plan']->date_context);
        $this->assertEquals('bandung', strtolower($result['plan']->filters[0]['value']));
        $this->assertEquals('branch', strtolower($result['plan']->filters[0]['field']));
        $this->assertEquals('fast_path_recognition', $result['source']);
    }

    public function test_recognizes_average_sales_despite_library_having_sum()
    {
        $connection = Connection::factory()->create();
        $requestText = "Rata-rata penjualan Bandung bulan ini"; 

        $this->discoveryServiceMock->shouldReceive('discoverCandidates')
            ->once()
            ->andReturn([
                'metrics' => [['name' => 'penjualan', 'aggregation' => 'AVG']],
                'dimensions' => ['branch']
            ]);

        // Library has SUM, but recognizer uses it for structural hint only!
        AiQueryLibrary::factory()->create([
            'connection_id' => $connection->id,
            'is_active' => true,
            'canonical_plan_json' => [
                'metrics' => [['name' => 'penjualan', 'aggregation' => 'sum']], 
                'dimensions' => [],
                'filters' => [['field' => 'branch', 'operator' => '=']]
            ]
        ]);

        $result = $this->recognizer->recognize($requestText, $connection->id);

        $this->assertEquals('recognized', $result['status']);
        $this->assertEquals(1.0, $result['confidence']);
        $this->assertEquals('avg', strtolower($result['plan']->metrics[0]['aggregation']));
    }

    public function test_fails_on_ambiguous_semantic_term()
    {
        $connection = Connection::factory()->create();
        $requestText = "Total penjualan tertinggi Bandung bulan ini"; 

        config(['chatedp.query_recognition.ambiguous_terms' => ['tertinggi']]);

        $result = $this->recognizer->recognize($requestText, $connection->id);

        $this->assertEquals('unrecognized', $result['status']);
        $this->assertEquals(0.0, $result['confidence']);
        $this->assertContains('ambiguous_term_detected:tertinggi', $result['reasons']);
    }
    
    public function test_recognizes_standalone_without_library_hit()
    {
        $connection = Connection::factory()->create();
        $requestText = "Total penjualan berdasarkan cabang"; // Perfect match, no residual text
        
        $this->discoveryServiceMock->shouldReceive('discoverCandidates')
            ->once()
            ->andReturn([
                'metrics' => [['name' => 'penjualan', 'aggregation' => 'SUM']],
                'dimensions' => ['cabang']
            ]);
            
        // No library exists
        $result = $this->recognizer->recognize($requestText, $connection->id);

        $this->assertEquals('recognized', $result['status']);
        $this->assertGreaterThanOrEqual(0.95, $result['confidence']); 
        $this->assertContains('standalone_semantic_match_no_library', $result['reasons']);
    }
    
    public function test_fails_if_unresolved_residual_text_and_no_library()
    {
        $connection = Connection::factory()->create();
        $requestText = "Total penjualan cabang Bandung"; // "Bandung" is residual text!
        
        $this->discoveryServiceMock->shouldReceive('discoverCandidates')
            ->once()
            ->andReturn([
                'metrics' => [['name' => 'penjualan', 'aggregation' => 'SUM']],
                'dimensions' => ['cabang']
            ]);
            
        // No library exists to tell us how to map "Bandung"
        $result = $this->recognizer->recognize($requestText, $connection->id);

        $this->assertEquals('unrecognized', $result['status']);
        $this->assertEquals(0.0, $result['confidence']);
        $this->assertContains('unresolved_residual_text', $result['reasons']);
    }
    
    public function test_context_dependent_follow_up_misses_fast_path()
    {
        $connection = Connection::factory()->create();
        $requestText = "Bagaimana dengan Jakarta?"; // No metric!
        
        $this->discoveryServiceMock->shouldReceive('discoverCandidates')
            ->once()
            ->andReturn([
                'metrics' => [], // No metric matched
                'dimensions' => []
            ]);
            
        $result = $this->recognizer->recognize($requestText, $connection->id);

        $this->assertEquals('unrecognized', $result['status']);
        $this->assertContains('no_metrics_found', $result['reasons']);
    }
}
