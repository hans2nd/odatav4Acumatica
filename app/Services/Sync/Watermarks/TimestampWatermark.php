<?php

namespace App\Services\Sync\Watermarks;

use App\Contracts\Sync\WatermarkInterface;
use Carbon\Carbon;

class TimestampWatermark implements WatermarkInterface
{
    public function __construct(
        private string $column,
        private string $value
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
        try {
            $utcString = Carbon::parse($this->value)->timezone('UTC')->format('Y-m-d\TH:i:s.v\Z');
            return "{$this->column} {$operator} {$utcString}";
        } catch (\Exception $e) {
            return "{$this->column} {$operator} {$this->value}";
        }
    }
}
