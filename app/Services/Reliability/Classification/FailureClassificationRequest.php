<?php

namespace App\Services\Reliability\Classification;

use Throwable;

class FailureClassificationRequest
{
    public function __construct(
        public readonly Throwable $exception
    ) {}
}
