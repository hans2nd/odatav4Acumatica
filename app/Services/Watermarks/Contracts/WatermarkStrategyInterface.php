<?php

namespace App\Services\Watermarks\Contracts;

use App\Services\Watermarks\WatermarkValue;

interface WatermarkStrategyInterface
{
    /**
     * Compare two watermark values.
     * @return int 1 if $a > $b, -1 if $a < $b, 0 if equal.
     */
    public function compare(WatermarkValue $a, WatermarkValue $b): int;

    /**
     * Validate the semantic correctness of a watermark value.
     */
    public function validate(WatermarkValue $value): void;
}
