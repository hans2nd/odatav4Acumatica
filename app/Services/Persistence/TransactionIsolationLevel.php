<?php

namespace App\Services\Persistence;

class TransactionIsolationLevel
{
    public const READ_UNCOMMITTED = 'READ_UNCOMMITTED';
    public const READ_COMMITTED = 'READ_COMMITTED';
    public const REPEATABLE_READ = 'REPEATABLE_READ';
    public const SERIALIZABLE = 'SERIALIZABLE';
}
