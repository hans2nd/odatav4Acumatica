<?php

namespace App\Services\ChangeDetection;

use App\Services\Mappers\NormalizedRecord;

class ChangeDetectionContext
{
    public function __construct(
        public readonly string $strategyName,
        public readonly ?NormalizedRecord $snapshot = null,
        public readonly array $options = []
    ) {}
}
