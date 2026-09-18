<?php

namespace App\Services\Persistence;

class UpsertResult
{
    public function __construct(
        public readonly bool $isSuccess,
        public readonly UpsertStatistics $statistics,
        public readonly ?\Throwable $error = null
    ) {}
}
