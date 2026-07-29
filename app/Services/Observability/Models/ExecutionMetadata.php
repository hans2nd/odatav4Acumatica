<?php

namespace App\Services\Observability\Models;

class ExecutionMetadata
{
    public function __construct(
        public readonly string $environment,
        public readonly string $host,
        public readonly array $tags = []
    ) {}
}
