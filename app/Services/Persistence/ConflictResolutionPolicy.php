<?php

namespace App\Services\Persistence;

class ConflictResolutionPolicy
{
    public const LAST_WRITE_WINS = 'LAST_WRITE_WINS';
    public const FIRST_WRITE_WINS = 'FIRST_WRITE_WINS';
    public const SOURCE_PRIORITY = 'SOURCE_PRIORITY';
    public const DESTINATION_PRIORITY = 'DESTINATION_PRIORITY';
    public const TIMESTAMP_COMPARISON = 'TIMESTAMP_COMPARISON';
    public const VERSION_COMPARISON = 'VERSION_COMPARISON';
    public const SKIP_CONFLICT = 'SKIP_CONFLICT';
    public const FAIL_FAST = 'FAIL_FAST';
}
