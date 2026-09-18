<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;

class QueryCostGuardService
{
    public function __construct(
        private int $maxTables = 5,
        private int $maxColumns = 20
    ) {}

    public function guard(QueryPlan $plan): void
    {
        if (count($plan->tables) > $this->maxTables) {
            throw new \App\Exceptions\AI\SemanticAmbiguityException("Permintaan Anda terlalu kompleks karena melibatkan terlalu banyak tabel ({$this->maxTables}). Mohon persingkat pertanyaan Anda.");
        }

        if (count($plan->columns) > $this->maxColumns) {
            throw new \App\Exceptions\AI\SemanticAmbiguityException("Permintaan Anda mencoba menampilkan terlalu banyak kolom. Mohon spesifikasikan kolom yang ingin Anda lihat.");
        }

        $maxCost = config('chatedp.cost_guard.max_cost', 1000);
        $cost = 0;

        // Base penalties
        $highPenalty = config('chatedp.cost_guard.high_cardinality_penalty', 800);
        $medPenalty = config('chatedp.cost_guard.medium_cardinality_penalty', 300);
        $lowPenalty = config('chatedp.cost_guard.low_cardinality_penalty', 100);
        
        $dimensionMultiplier = config('chatedp.cost_guard.dimension_multiplier', 1.5);

        $dimensionCount = count($plan->dimensions);

        // Rule D: Aggregate-only query
        if ($dimensionCount === 0) {
            // Apply a base scan penalty instead of bypassing
            $cost = config('chatedp.cost_guard.aggregate_base_penalty', 100);
            $highestDimensionCost = 0; // No dimensions
        } else {
            $highestDimensionCost = 0;
            foreach ($plan->dimensions as $idx => $dimension) {
                $logicalName = $plan->internal_metadata['logical_dimensions'][$idx] ?? $dimension;
                $cardinality = $this->estimateCardinality($logicalName);
                
                $dimCost = match($cardinality) {
                    'high' => $highPenalty,
                    'medium' => $medPenalty,
                    'low' => $lowPenalty,
                    default => $medPenalty // fallback
                };
                
                if ($dimCost > $highestDimensionCost) {
                    $highestDimensionCost = $dimCost;
                }
            }
            $cost = $highestDimensionCost;
        }



        // Rule C: Multiple high-cardinality dimensions
        if ($dimensionCount > 1) {
            $cost = $cost * pow($dimensionMultiplier, $dimensionCount - 1);
        }

        // Rule E: Explicit User Limit / Top-N discount
        if ($plan->limit !== null && $plan->limit > 0) {
            if ($plan->limit <= 100) {
                // Rule B: Safe Top-N
                $reduction = config('chatedp.cost_guard.safe_top_n_reduction', 0.8);
                $cost = $cost * (1 - $reduction);
            } elseif ($plan->limit <= 1000) {
                $cost = $cost * 0.5; // Moderate reduction
            }
            // Rule F: Large Limit -> No reduction for LIMIT 100000
        }

        // Rule E: Date filter discount
        if (!empty($plan->date_context)) {
            $reduction = config('chatedp.cost_guard.date_filter_reduction', 0.2);
            $cost = $cost * (1 - $reduction);
        }

        if ($cost >= $maxCost) {
            throw new \App\Exceptions\AI\SemanticAmbiguityException("Query ini menghasilkan terlalu banyak kelompok data. Coba batasi ke Top 10/20 atau gunakan filter periode/cabang.");
        }
    }

    private function estimateCardinality(string $logicalName): string
    {
        $logicalName = strtolower(trim($logicalName));
        $hints = config('chatedp.cardinality_hints', []);
        
        foreach ($hints as $level => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($logicalName, strtolower($keyword))) {
                    return $level;
                }
            }
        }
        
        return 'medium'; // Default fallback
    }
}
