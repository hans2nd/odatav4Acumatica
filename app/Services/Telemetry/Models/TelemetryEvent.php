<?php

namespace App\Services\Telemetry\Models;

class TelemetryEvent
{
    /**
     * @param string $name Nama kejadian penting (Misal: 'pipeline_started')
     * @param float $timestamp Waktu terjadinya event di timeline
     * @param array $attributes Detail kustom lainnya (Misal: ['correlation_id' => 'abc'])
     */
    public function __construct(
        public readonly string $name,
        public readonly float $timestamp,
        public readonly array $attributes = []
    ) {}
}
