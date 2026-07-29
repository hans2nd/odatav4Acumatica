<?php

namespace App\Services\Watermarks\Strategies;

use App\Services\Watermarks\Contracts\WatermarkStrategyInterface;
use App\Services\Watermarks\WatermarkValue;
use App\Services\Watermarks\Exceptions\InvalidWatermarkException;
use Carbon\Carbon;

class TimestampPrimaryKeyStrategy implements WatermarkStrategyInterface
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

        // Timestamps are exactly equal. Fallback to Primary Key resolution.
        return strcmp($a->primaryKey ?? '', $b->primaryKey ?? '');
    }

    public function validate(WatermarkValue $value): void
    {
        if ($value->isEmpty()) {
            return; // Accept empty watermark for initial sync
        }

        if ($value->timestamp === null) {
            throw new InvalidWatermarkException("Timestamp cannot be null for TimestampPrimaryKeyStrategy.");
        }

        if ($value->primaryKey === null) {
            throw new InvalidWatermarkException("Primary Key cannot be null for TimestampPrimaryKeyStrategy.");
        }

        try {
            $parsedDate = Carbon::parse($value->timestamp);
            // Allow a 1-day tolerance for future timestamps in case of slight server clock drift
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
