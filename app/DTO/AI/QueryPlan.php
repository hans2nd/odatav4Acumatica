<?php

namespace App\DTO\AI;

class QueryPlan
{
    public function __construct(
        public readonly string $intent = 'sales_analysis',
        public readonly array $tables = [],
        public readonly array $columns = [],
        public readonly array $metrics = [],
        public readonly array $dimensions = [],
        public readonly array $filters = [],
        public readonly array $sorts = [],
        public readonly ?int $limit = null,
        public readonly ?array $comparison = null,
        public readonly ?string $metric_term = null,
        public readonly string $aggregation = 'sum'
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
        
        return new self(
            $data['intent'] ?? 'sales_analysis',
            $data['tables'] ?? [],
            $data['columns'] ?? [],
            $data['metrics'] ?? [],
            $data['dimensions'] ?? [],
            $filters,
            $data['sorts'] ?? [],
            $data['limit'] ?? null,
            $data['comparison'] ?? null,
            $data['metric_term'] ?? null,
            $data['aggregation'] ?? 'sum'
        );
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'tables' => $this->tables,
            'columns' => $this->columns,
            'metrics' => $this->metrics,
            'dimensions' => $this->dimensions,
            'filters' => $this->filters,
            'sorts' => $this->sorts,
            'limit' => $this->limit,
            'comparison' => $this->comparison,
            'metric_term' => $this->metric_term,
            'aggregation' => $this->aggregation,
        ];
    }
}
