<?php

namespace App\Services\ChangeDetection;

class RecordFingerprint
{
    public function __construct(
        public readonly string $hash,
        public readonly string $algorithm
    ) {}
}
