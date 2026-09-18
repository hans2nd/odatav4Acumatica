<?php

namespace App\Contracts\Sync;

interface WatermarkInterface
{
    /**
     * Get the name of the column/field representing the watermark.
     */
    public function getColumn(): string;

    /**
     * Get the raw value of the watermark.
     */
    public function getValue(): mixed;

    /**
     * Format the watermark for OData or generic API queries.
     */
    public function toFilterString(string $operator = 'gt'): string;
}
