<?php

namespace App\Services\Watermarks\Strategies;

use App\Services\Watermarks\Contracts\WatermarkStrategyInterface;
use App\Services\Watermarks\WatermarkValue;
use App\Services\Watermarks\Exceptions\InvalidWatermarkException;
use Carbon\Carbon;

class TimestampOnlyStrategy implements WatermarkStrategyInterface
{
    public function compare(WatermarkValue $a, WatermarkValue $b): int
    {
        $this->validate($a);
        $this->validate($b);

        if ($a->timestamp > $b->timestamp) {
            return 1;
        }

        if ($a->timestamp < $b->timestamp) {
            return -1;
        }

        return 0;
    }

    public function validate(WatermarkValue $value): void
    {
        if ($value->isEmpty()) {
            return;
        }

        if ($value->timestamp === null) {
            throw new InvalidWatermarkException("Timestamp cannot be null for TimestampOnlyStrategy.");
        }

        try {
            $parsedDate = Carbon::parse($value->timestamp);
            if ($parsedDate->gt(now()->addDay())) {
                throw new InvalidWatermarkException("Watermark timestamp cannot be in the future.");
            }
        } catch (\Exception $e) {
            if ($e instanceof InvalidWatermarkException) {
                throw $e;
            }
            throw new InvalidWatermarkException("Invalid timestamp format.");
        }
    }
}
