<?php

namespace App\Services\Persistence;

class PersistenceContext
{
    public function __construct(
        public readonly string $connectionName,
        public readonly string $tableName,
        public readonly array $primaryKeys
    ) {}
}
