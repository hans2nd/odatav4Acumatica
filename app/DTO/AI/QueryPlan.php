<?php

namespace App\DTO\AI;

class QueryPlan
{
    public function __construct(
        public readonly string $intent = 'sales_analysis',
        public readonly array $metrics = [],
        public readonly array $dimensions = [],
        public readonly array $filters = [],
        public readonly array $sorts = [],
        public readonly ?int $limit = null,
        public readonly ?array $comparison = null,
        public readonly ?string $date_context = null,
        
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
                            'aggregation' => $m['aggregation'] ?? 'sum'
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
        
        return new self(
            $data['intent'] ?? 'sales_analysis',
            $canonicalMetrics,
            $data['dimensions'] ?? [],
            $filters,
            $data['sorts'] ?? [],
            $data['limit'] ?? null,
            $data['comparison'] ?? null,
            $data['date_context'] ?? null,
            
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
            'filters' => $this->filters,
            'sorts' => $this->sorts,
            'limit' => $this->limit,
            'comparison' => $this->comparison,
            'date_context' => $this->date_context,
            'tables' => $this->tables,
            'internal_metadata' => $this->internal_metadata
        ];
    }
}
