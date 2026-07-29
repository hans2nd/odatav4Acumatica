<?php

namespace App\Services\Persistence;

class TransactionScope
{
    public function __construct(
        public readonly string $connectionName,
        public readonly int $nestingLevel = 1,
        public readonly ?string $savepointName = null
    ) {}
}
