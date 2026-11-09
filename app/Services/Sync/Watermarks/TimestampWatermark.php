<?php

namespace App\Services\Sync\Watermarks;

use App\Contracts\Sync\WatermarkInterface;
use Carbon\Carbon;

class TimestampWatermark implements WatermarkInterface
{
    public function __construct(
        private string $column,
        private string $value,
        private bool $isV4 = true
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
            $date = Carbon::parse($this->value, 'UTC');
            if ($this->isV4) {
                // Treat the database value as UTC if Acumatica 25R1 always returns UTC
                $utcString = $date->format('Y-m-d\TH:i:s.v\Z');
                return "{$this->column} {$operator} {$utcString}";
            }

            // OData v3 DateTime literal format: datetime'yyyy-MM-ddTHH:mm:ss.fff'
            $dateStr = $date->format('Y-m-d\TH:i:s.v');
            return "{$this->column} {$operator} datetime'{$dateStr}'";
        } catch (\Exception $e) {
            return "{$this->column} {$operator} {$this->value}";
        }
    }
}
