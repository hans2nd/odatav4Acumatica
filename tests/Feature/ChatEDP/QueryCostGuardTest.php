<?php

namespace Tests\Feature\ChatEDP;

use Tests\TestCase;
use App\DTO\AI\QueryPlan;
use App\Services\AI\QueryCostGuardService;
use App\Exceptions\AI\SemanticAmbiguityException;

class QueryCostGuardTest extends TestCase
{
    protected QueryCostGuardService $costGuard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costGuard = new QueryCostGuardService();
        
        config(['chatedp.cost_guard' => [
            'max_cost' => 1000,
            'high_cardinality_penalty' => 800,
            'medium_cardinality_penalty' => 300,
            'low_cardinality_penalty' => 100,
            'dimension_multiplier' => 1.5,
            'safe_top_n_reduction' => 0.8,
            'date_filter_reduction' => 0.2,
        ]]);
        
        config(['chatedp.cardinality_hints' => [
            'high' => ['customer', 'product'],
            'medium' => ['category'],
            'low' => ['branch']
        ]]);
    }

    public function test_aggregate_only_query_is_allowed()
    {
        $plan = new QueryPlan(
            dimensions: [],
            internal_metadata: ['logical_dimensions' => []]
        );

        $this->costGuard->guard($plan);
        $this->assertTrue(true);
    }

    public function test_low_cardinality_grouping_allowed()
    {
        $plan = new QueryPlan(
            dimensions: ['branchCD'],
            internal_metadata: ['logical_dimensions' => ['branch']]
        );

        $this->costGuard->guard($plan);
        $this->assertTrue(true);
    }

    public function test_high_cardinality_grouping_blocked()
    {
        $this->expectException(SemanticAmbiguityException::class);
        $this->expectExceptionMessage("Query ini menghasilkan terlalu banyak kelompok data");

        config(['chatedp.cost_guard.max_cost' => 800]);

        $plan = new QueryPlan(
            dimensions: ['customer_id'],
            internal_metadata: ['logical_dimensions' => ['customer']]
        );

        $this->costGuard->guard($plan);
    }

    public function test_high_cardinality_top_n_allowed()
    {
        config(['chatedp.cost_guard.max_cost' => 800]);
        
        $plan = new QueryPlan(
            dimensions: ['customer_id'],
            limit: 10,
            internal_metadata: ['logical_dimensions' => ['customer']]
        );

        $this->costGuard->guard($plan);
        $this->assertTrue(true);
    }

    public function test_large_limit_blocked()
    {
        config(['chatedp.cost_guard.max_cost' => 800]);
        $this->expectException(SemanticAmbiguityException::class);

        $plan = new QueryPlan(
            dimensions: ['customer_id'],
            limit: 100000,
            internal_metadata: ['logical_dimensions' => ['customer']]
        );

        $this->costGuard->guard($plan);
    }

    public function test_multiple_high_cardinality_blocked()
    {
        config(['chatedp.cost_guard.max_cost' => 1000]);
        $this->expectException(SemanticAmbiguityException::class);

        $plan = new QueryPlan(
            dimensions: ['customer_id', 'product_id'],
            internal_metadata: ['logical_dimensions' => ['customer', 'product']]
        );

        $this->costGuard->guard($plan);
    }

    public function test_restrictive_date_filter_lowers_cost_but_may_still_block_if_too_high()
    {
        config(['chatedp.cost_guard.max_cost' => 600]);
        $this->expectException(SemanticAmbiguityException::class);
        
        $plan = new QueryPlan(
            dimensions: ['customer_id'],
            date_context: 'this_month',
            internal_metadata: ['logical_dimensions' => ['customer']]
        );

        $this->costGuard->guard($plan);
    }
}
