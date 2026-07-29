<?php

namespace App\Services\Reliability\Classification;

class FailureDiagnostic
{
    public function __construct(
        public readonly string $errorMessage,
        public readonly string $exceptionClass,
        public readonly int|string $errorCode,
        public readonly string $file,
        public readonly int $line,
        public readonly ?string $additionalMetadata = null
    ) {}
}
