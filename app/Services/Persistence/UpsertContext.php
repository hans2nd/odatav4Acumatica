<?php

namespace App\Services\Persistence;

class UpsertContext
{
    public function __construct(
        public readonly string $connectionName,
        public readonly string $tableName,
        public readonly array $primaryKeys,
        public readonly array $ignoredFields = [],
        public readonly array $autoTimestampFields = []
    ) {}
}
