<?php

namespace App\Services\Coordinators;

class ExecutionStatistics
{
    public function __construct(
        public readonly int $recordsFetched,
        public readonly float $durationSeconds
    ) {}
}
