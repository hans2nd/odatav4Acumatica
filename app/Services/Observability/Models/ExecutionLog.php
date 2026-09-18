<?php

namespace App\Services\Observability\Models;

class ExecutionLog
{
    /**
     * @param string $level Tingkat keparahan (Misal: 'INFO', 'ERROR')
     * @param string $message Pesan teks log terstruktur
     * @param float $timestamp Waktu pencatatan
     * @param array $context Konteks objek atau array pendukung
     */
    public function __construct(
        public readonly string $level,
        public readonly string $message,
        public readonly float $timestamp,
        public readonly array $context = []
    ) {}
}
