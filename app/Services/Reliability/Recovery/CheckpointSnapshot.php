<?php

namespace App\Services\Reliability\Recovery;

class CheckpointSnapshot
{
    /**
     * @param string $jobId ID Sinkronisasi unik
     * @param int $offset Posisi atau indeks data terakhir yang berhasil diproses
     * @param array $metadata Informasi tambahan konteks (misal tahap apa yang berhasil terakhir)
     * @param string $checksum Validasi integritas agar tidak dimodifikasi manual
     * @param string $timestamp Waktu pembuatan titik periksa (ISO 8601)
     */
    public function __construct(
        public readonly string $jobId,
        public readonly int $offset,
        public readonly array $metadata = [],
        public readonly string $checksum = '',
        public readonly string $timestamp = ''
    ) {}
}
