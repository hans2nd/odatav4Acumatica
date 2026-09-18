<?php

namespace App\Services\Observability\Models;

class ExecutionEvent
{
    /**
     * @param string $name Nama kejadian (Misal: 'Memory_Threshold_Reached')
     * @param float $timestamp Waktu kejadian (microtime)
     * @param array $attributes Atribut detail
     */
    public function __construct(
        public readonly string $name,
        public readonly float $timestamp,
        public readonly array $attributes = []
    ) {}
}
