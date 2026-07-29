<?php

namespace App\Services\Validators;

class ValidationIssue
{
    public function __construct(
        public readonly string $ruleName,
        public readonly string $description,
        public readonly string $classification,
        public readonly RecoveryRecommendation $recommendation
    ) {}
}
