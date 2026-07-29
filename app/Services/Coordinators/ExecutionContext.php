<?php

namespace App\Services\Coordinators;

class ExecutionContext
{
    public function __construct(
        public readonly int $scheduleId,
        public readonly string $entityKey,
        public readonly string $timestampField,
        public readonly string $primaryKeyField,
        public readonly int $pageSize = 100,
        public readonly array $customFilters = []
    ) {}
}
