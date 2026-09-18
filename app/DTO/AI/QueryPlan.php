<?php

namespace App\DTO\AI;

class QueryPlan
{
    public function __construct(
        public readonly string $intent = 'sales_analysis',
        public readonly array $metrics = [],
        public readonly array $dimensions = [],
        public readonly array $detail_dimensions = [],
        public readonly array $filters = [],
        public readonly array $sorts = [],
        public readonly ?int $limit = null,
        public readonly ?array $comparison = null,
        public readonly ?string $date_context = null,
        public readonly array $domain = [],
        public readonly array $relationships = [],
        public readonly array $analysis = [],
        public readonly array $columns = [],
        
        // Internal properties for pipeline resolution
        public readonly array $tables = [],
        public readonly array $internal_metadata = []
    ) {}

    public static function fromArray(array $data): self
    {
        $filters = $data['filters'] ?? [];
        foreach ($filters as &$f) {
            if (!isset($f['field'])) {
                if (isset($f['dimension'])) {
                    $f['field'] = $f['dimension'];
                    unset($f['dimension']);
                } elseif (isset($f['column'])) {
                    $f['field'] = $f['column'];
                    unset($f['column']);
                }
            }
            if (isset($f['operator'])) {
                if (strtolower($f['operator']) === 'equals' || strtolower($f['operator']) === 'eq') {
                    $f['operator'] = '=';
                } elseif (strtolower($f['operator']) === 'not equals' || strtolower($f['operator']) === 'neq') {
                    $f['operator'] = '!=';
                }
            }
        }
        
        // Canonical Metric Normalization
        $canonicalMetrics = [];
        
        // Handle LLM arrays of metrics
        if (!empty($data['metrics']) && is_array($data['metrics'])) {
            foreach ($data['metrics'] as $m) {
                if (is_string($m)) {
                    $canonicalMetrics[] = ['name' => $m, 'aggregation' => 'sum'];
                } elseif (is_array($m)) {
                    $name = $m['name'] ?? $m['term'] ?? null;
                    if ($name) {
                        $canonicalMetrics[] = [
                            'name' => $name,
                            'aggregation' => $m['aggregation'] ?? 'sum',
                            'expression' => $m['expression'] ?? null,
                            'depends_on' => $m['depends_on'] ?? null,
                            'type' => $m['type'] ?? null,
                            'calculation_scope' => $m['calculation_scope'] ?? null,
                            'aggregation_strategy' => $m['aggregation_strategy'] ?? null,
                            'is_expression' => $m['is_expression'] ?? false,
                            'alias' => $m['alias'] ?? null,
                        ];
                    }
                }
            }
        }
        
        // Handle legacy single object "metric"
        if (empty($canonicalMetrics) && !empty($data['metric']) && is_array($data['metric'])) {
            $name = $data['metric']['name'] ?? $data['metric']['term'] ?? null;
            if ($name) {
                $canonicalMetrics[] = [
                    'name' => $name,
                    'aggregation' => $data['metric']['aggregation'] ?? 'sum'
                ];
            }
        }

        // Handle legacy "metric_term" string
        if (empty($canonicalMetrics) && !empty($data['metric_term'])) {
            $canonicalMetrics[] = [
                'name' => $data['metric_term'],
                'aggregation' => $data['aggregation'] ?? 'sum'
            ];
        }
        
        $intent = $data['intent'] ?? 'sales_analysis';
        $dimensions = array_values($data['dimensions'] ?? []);
        $detailDimensions = array_values($data['detail_dimensions'] ?? []);
        // Backward-compatible protection for older planners that represented a
        // ranked entity and its requested attributes as one dimensions array.
        if (strtolower($intent) === 'ranking' && empty($detailDimensions) && count($dimensions) > 1) {
            $detailDimensions = array_slice($dimensions, 1);
            $dimensions = [reset($dimensions)];
        }

        return new self(
            $intent,
            $canonicalMetrics,
            $dimensions,
            $detailDimensions,
            $filters,
            $data['sorts'] ?? [],
            $data['limit'] ?? null,
            $data['comparison'] ?? null,
            $data['date_context'] ?? null,
            (array) ($data['domain'] ?? []),
            (array) ($data['relationships'] ?? []),
            array_merge(
                (array) ($data['analysis'] ?? []),
                // Absorb root-level capability/message for unsupported_capability intent
                array_filter([
                    'capability' => $data['capability'] ?? null,
                    'message' => $data['message'] ?? null,
                ], fn($v) => $v !== null)
            ),
            (array) ($data['columns'] ?? []),
            
            // Legacy internal
            $data['tables'] ?? [],
            $data['internal_metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'metrics' => $this->metrics,
            'dimensions' => $this->dimensions,
            'detail_dimensions' => $this->detail_dimensions,
            'filters' => $this->filters,
            'sorts' => $this->sorts,
            'limit' => $this->limit,
            'comparison' => $this->comparison,
            'date_context' => $this->date_context,
            'domain' => $this->domain,
            'relationships' => $this->relationships,
            'analysis' => $this->analysis,
            'columns' => $this->columns,
            'tables' => $this->tables,
            'internal_metadata' => $this->internal_metadata
        ];
    }
}
