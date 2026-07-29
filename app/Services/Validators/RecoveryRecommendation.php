<?php

namespace App\Services\Validators;

class RecoveryRecommendation
{
    public function __construct(
        public readonly string $action,
        public readonly string $reasoning
    ) {}
}
