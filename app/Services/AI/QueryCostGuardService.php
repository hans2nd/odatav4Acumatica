<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;

class QueryCostGuardService
{
    public function __construct(
        private int $maxTables = 5,
        private int $maxColumns = 20,
        private int $maxDimensions = 5,
        private int $maxLimit = 1000
    ) {}

    public function guard(QueryPlan $plan): void
    {
        if (count($plan->tables) > $this->maxTables) {
            throw new \Exception("Cost Guard: Query exceeds maximum allowed tables/joins ({$this->maxTables}).");
        }

        if (count($plan->columns) > $this->maxColumns) {
            throw new \Exception("Cost Guard: Query exceeds maximum allowed selected columns ({$this->maxColumns}).");
        }

        if (count($plan->dimensions) > $this->maxDimensions) {
            throw new \Exception("Cost Guard: Query exceeds maximum allowed group-by dimensions ({$this->maxDimensions}).");
        }

        if ($plan->limit !== null && $plan->limit > $this->maxLimit) {
            throw new \Exception("Cost Guard: Requested row limit ({$plan->limit}) exceeds the maximum allowed server limit ({$this->maxLimit}).");
        }
    }
}
