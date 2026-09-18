<?php

namespace App\Services\Sync\Watermarks;

use App\Contracts\Sync\WatermarkInterface;

class SequenceWatermark implements WatermarkInterface
{
    public function __construct(
        private string $column,
        private int $value
    ) {}

    public function getColumn(): string
    {
        return $this->column;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function toFilterString(string $operator = 'gt'): string
    {
        return "{$this->column} {$operator} {$this->value}";
    }
}
