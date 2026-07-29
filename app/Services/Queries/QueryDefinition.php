<?php

namespace App\Services\Queries;

class QueryDefinition
{
    public function __construct(
        public readonly array $filters = [],
        public readonly array $orderBy = [],
        public readonly ?int $limit = null
    ) {}
}
