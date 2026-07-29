<?php

namespace App\Services\Queries;

use App\Services\Queries\Contracts\IncrementalQueryBuilderInterface;
use InvalidArgumentException;

class IncrementalQueryBuilder implements IncrementalQueryBuilderInterface
{
    private array $watermarkConditions = [];
    private array $customConditions = [];
    private array $orderBy = [];
    private ?int $limit = null;

    public function withCompositeWatermark(string $timestampField, string $pkField, ?string $lastTimestamp, ?string $lastPk): self
    {
        if (empty($timestampField) || empty($pkField)) {
            throw new InvalidArgumentException("Timestamp and Primary Key fields are required for composite watermarks.");
        }

        // If this is the very first run, no watermark filter is needed.
        if ($lastTimestamp === null) {
            return $this;
        }

        if ($lastPk === null) {
            // Fallback to simple watermark if PK was lost or missing
            $this->watermarkConditions = [
                'type' => 'condition',
                'field' => $timestampField,
                'operator' => 'gt',
                'value' => $lastTimestamp
            ];
            return $this;
        }

        // Composite Logic: (TS > lastTS) OR (TS == lastTS AND PK > lastPK)
        $this->watermarkConditions = [
            'type' => 'OR',
            'conditions' => [
                [
                    'type' => 'condition',
                    'field' => $timestampField,
                    'operator' => 'gt',
                    'value' => $lastTimestamp
                ],
                [
                    'type' => 'AND',
                    'conditions' => [
                        [
                            'type' => 'condition',
                            'field' => $timestampField,
                            'operator' => 'eq',
                            'value' => $lastTimestamp
                        ],
                        [
                            'type' => 'condition',
                            'field' => $pkField,
                            'operator' => 'gt',
                            'value' => $lastPk
                        ]
                    ]
                ]
            ]
        ];

        return $this;
    }

    public function withOrdering(string $timestampField, string $pkField, string $direction = 'asc'): self
    {
        $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $this->orderBy = [
            ['field' => $timestampField, 'direction' => $dir],
            ['field' => $pkField, 'direction' => $dir]
        ];
        
        return $this;
    }

    public function withLimit(int $limit): self
    {
        if ($limit <= 0) {
            throw new InvalidArgumentException("Limit must be greater than zero.");
        }
        $this->limit = $limit;
        return $this;
    }

    public function withCustomFilters(array $filters): self
    {
        foreach ($filters as $filter) {
            if (isset($filter['field'], $filter['operator'], $filter['value'])) {
                $this->customConditions[] = [
                    'type' => 'condition',
                    'field' => $filter['field'],
                    'operator' => $filter['operator'],
                    'value' => $filter['value']
                ];
            }
        }
        return $this;
    }

    public function build(): QueryDefinition
    {
        $finalFilters = [];

        // Merge custom filters and watermark filters using AND at the root
        if (!empty($this->customConditions) && !empty($this->watermarkConditions)) {
            $finalFilters = [
                'type' => 'AND',
                'conditions' => [
                    ['type' => 'AND', 'conditions' => $this->customConditions],
                    $this->watermarkConditions
                ]
            ];
        } elseif (!empty($this->customConditions)) {
            $finalFilters = ['type' => 'AND', 'conditions' => $this->customConditions];
        } elseif (!empty($this->watermarkConditions)) {
            $finalFilters = $this->watermarkConditions;
        }

        return new QueryDefinition(
            $finalFilters,
            $this->orderBy,
            $this->limit
        );
    }
}
