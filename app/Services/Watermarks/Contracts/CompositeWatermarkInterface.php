<?php

namespace App\Services\Watermarks\Contracts;

use App\Services\Watermarks\WatermarkValue;

interface CompositeWatermarkInterface
{
    /**
     * Set the strategy to be used for watermark comparison and validation.
     */
    public function setStrategy(WatermarkStrategyInterface $strategy): void;

    /**
     * Determine the highest watermark safely based on the current and candidate values.
     */
    public function determineNextCheckpoint(WatermarkValue $current, WatermarkValue $candidate): WatermarkValue;

    /**
     * Ensure the new watermark does not logically precede the previous watermark (prevent time-travel).
     */
    public function validateContinuity(WatermarkValue $previous, WatermarkValue $new): void;
}
