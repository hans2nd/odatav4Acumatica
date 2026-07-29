<?php

namespace App\Services\Validators;

class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $issues = []
    ) {}
}
