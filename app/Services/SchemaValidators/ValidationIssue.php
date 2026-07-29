<?php

namespace App\Services\SchemaValidators;

class ValidationIssue
{
    public function __construct(
        public readonly string $level, // 'error' or 'warning'
        public readonly string $field,
        public readonly string $ruleName,
        public readonly string $message
    ) {}
}
