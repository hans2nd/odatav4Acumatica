<?php

namespace App\Services\Watermarks;

use InvalidArgumentException;

class WatermarkValue
{
    public function __construct(
        public readonly ?string $timestamp = null,
        public readonly ?string $primaryKey = null,
        public readonly array $additionalKeys = []
    ) {}

    public function isEmpty(): bool
    {
        return $this->timestamp === null && $this->primaryKey === null;
    }
}
