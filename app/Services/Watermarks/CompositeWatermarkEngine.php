<?php

namespace App\Services\Watermarks;

use App\Services\Watermarks\Contracts\CompositeWatermarkInterface;
use App\Services\Watermarks\Contracts\WatermarkStrategyInterface;
use App\Services\Watermarks\Exceptions\InvalidWatermarkException;

class CompositeWatermarkEngine implements CompositeWatermarkInterface
{
    private ?WatermarkStrategyInterface $strategy = null;

    public function setStrategy(WatermarkStrategyInterface $strategy): void
    {
        $this->strategy = $strategy;
    }

    private function getStrategy(): WatermarkStrategyInterface
    {
        if ($this->strategy === null) {
            // Default to composite strategy if none explicitly provided
            $this->strategy = new \App\Services\Watermarks\Strategies\TimestampPrimaryKeyStrategy();
        }
        return $this->strategy;
    }

    public function determineNextCheckpoint(WatermarkValue $current, WatermarkValue $candidate): WatermarkValue
    {
        if ($current->isEmpty()) {
            $this->getStrategy()->validate($candidate);
            return $candidate;
        }

        if ($candidate->isEmpty()) {
            return $current;
        }

        $comparison = $this->getStrategy()->compare($candidate, $current);

        return $comparison > 0 ? $candidate : $current;
    }

    public function validateContinuity(WatermarkValue $previous, WatermarkValue $new): void
    {
        if ($previous->isEmpty() || $new->isEmpty()) {
            return;
        }

        $comparison = $this->getStrategy()->compare($new, $previous);

        if ($comparison < 0) {
            throw new InvalidWatermarkException("Continuity violation: new watermark is older than previous watermark (Time travel detected).");
        }
    }
}
