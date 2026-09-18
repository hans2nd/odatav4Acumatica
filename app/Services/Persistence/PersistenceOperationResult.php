<?php

namespace App\Services\Persistence;

use Throwable;

class PersistenceOperationResult
{
    public function __construct(
        public readonly bool $isSuccess,
        public readonly int $affectedRows,
        public readonly ?Throwable $error = null
    ) {}
}
