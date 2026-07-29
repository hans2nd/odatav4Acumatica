<?php

namespace App\Services\Coordinators;

use App\Services\Readers\SourcePage;
use App\Services\Readers\SourceRecordCollection;
use App\Services\Watermarks\WatermarkValue;

class ExecutionResult
{
    public function __construct(
        public readonly SourceRecordCollection $records,
        public readonly WatermarkValue $nextWatermark,
        public readonly SourcePage $nextPage,
        public readonly ExecutionStatistics $statistics,
        public readonly bool $hasMoreData
    ) {}
}
