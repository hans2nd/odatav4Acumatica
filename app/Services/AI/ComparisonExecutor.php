<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;
use App\Models\Connection;
use App\Services\QueryRepositoryService;

class ComparisonExecutor
{
    public function __construct(private QueryCompilerService $compiler, private QueryRepositoryService $executor) {}

    /** Execute two independently compiled, parameterized aggregates. No JOIN is generated. */
    public function execute(QueryPlan $plan, Connection $connection): array
    {
        $sides = $plan->internal_metadata['table_comparison'] ?? null;
        if (!$sides) throw new \LogicException('Validated table comparison metadata is missing.');

        $executed = [];
        foreach (['left', 'right'] as $side) {
            $rawPlan = $sides[$side . '_plan'];
            $sidePlan = new QueryPlan(
                intent: $rawPlan['intent'], metrics: $rawPlan['metrics'], dimensions: $rawPlan['dimensions'], filters: $rawPlan['filters'],
                sorts: $rawPlan['sorts'], limit: 1, date_context: $rawPlan['date_context'], domain: $rawPlan['domain'],
                analysis: $rawPlan['analysis'], columns: $rawPlan['columns'], tables: $rawPlan['tables'], internal_metadata: $rawPlan['internal_metadata']
            );
            [$sql, $bindings] = $this->compiler->compile($sidePlan, $connection);
            $execution = $this->executor->executeForAnalytics($sql, $bindings, 1);
            $metric = $sidePlan->metrics[0];
            $alias = ($metric['alias'] ?? preg_replace('/[^a-zA-Z0-9_]/', '_', $metric['name'])) . '_' . strtoupper($metric['aggregation'] ?? 'SUM');
            $row = (array) ($execution['data'][0] ?? []);
            $value = $row[$alias] ?? $row[$metric['alias'] ?? ''] ?? null;
            $rowCount = (int) ($row['_total_rows'] ?? 0);
            $executed[$side] = ['table' => $sidePlan->tables[0], 'metric' => $metric['alias'] ?? $metric['name'], 'value' => is_numeric($value) ? (float) $value : 0.0, 'has_data' => $rowCount > 0, 'sql_hash' => hash('sha256', $sql), 'duration' => $execution['duration']];
        }

        $left = $executed['left']['value'];
        $right = $executed['right']['value'];
        $gap = $left - $right;
        $ratio = $left == 0.0 ? null : $right / $left;
        return [
            'left' => $executed['left'], 'right' => $executed['right'],
            'has_data' => $executed['left']['has_data'] && $executed['right']['has_data'],
            'comparison' => ['gap' => $gap, 'absolute_difference' => abs($gap), 'percentage_difference' => $left == 0.0 ? null : ($gap / abs($left)) * 100, 'ratio' => $ratio, 'conversion_rate' => $ratio === null ? null : $ratio * 100],
            'duration' => $executed['left']['duration'] + $executed['right']['duration'],
        ];
    }
}
