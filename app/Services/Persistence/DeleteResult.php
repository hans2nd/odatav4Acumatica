<?php

namespace App\Services\Persistence;

class DeleteResult
{
    public function __construct(
        public readonly bool $isSuccess,
        public readonly DeleteStatistics $statistics,
        public readonly ?\Throwable $error = null
    ) {}
}
