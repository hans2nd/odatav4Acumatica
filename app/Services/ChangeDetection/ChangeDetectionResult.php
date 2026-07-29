<?php

namespace App\Services\ChangeDetection;

use App\Services\Mappers\NormalizedRecord;

class ChangeDetectionResult
{
    public function __construct(
        public readonly ChangeOperation $operation,
        public readonly NormalizedRecord $record,
        public readonly ?NormalizedRecord $snapshot = null
    ) {}
}
