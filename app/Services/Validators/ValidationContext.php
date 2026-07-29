<?php

namespace App\Services\Validators;

use App\Services\Coordinators\ExecutionContext;
use App\Services\Coordinators\ExecutionResult;
use App\Services\Watermarks\WatermarkValue;

class ValidationContext
{
    public function __construct(
        public readonly ExecutionContext $executionContext,
        public readonly ExecutionResult $executionResult,
        public readonly WatermarkValue $previousWatermark
    ) {}
}
