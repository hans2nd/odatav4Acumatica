<?php

namespace App\Services\ChangeDetection;

class ChangeOperation
{
    public const INSERT = 'INSERT';
    public const UPDATE = 'UPDATE';
    public const UNCHANGED = 'UNCHANGED';
    public const DELETE = 'DELETE';
    public const UNKNOWN = 'UNKNOWN';

    public function __construct(
        public readonly string $operation,
        public readonly string $reason
    ) {}
}
