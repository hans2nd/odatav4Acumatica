<?php

namespace App\Services\Observability\Models;

class CorrelationId
{
    public function __construct(
        public readonly string $id,
        public readonly string $jobId
    ) {}

    public function __toString(): string
    {
        return $this->id;
    }
}
