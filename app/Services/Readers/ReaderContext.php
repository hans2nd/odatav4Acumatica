<?php

namespace App\Services\Readers;

class ReaderContext
{
    public function __construct(
        public readonly string $entityName,
        public readonly int $pageSize = 100,
        public readonly int $timeoutSeconds = 60,
        public readonly array $requestOptions = []
    ) {}
}
