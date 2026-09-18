<?php

namespace App\Services\Reliability\Recovery;

class ResumeResult
{
    /**
     * @param bool $isSuccess Apakah engine berhasil merumuskan titik pelanjutan
     * @param int $resumeOffset Posisi data untuk memulai ulang (0 jika dari awal)
     * @param RecoveryStatistics $statistics Metrik proses pemulihan
     * @param string|null $reason Alasan jika gagal memulihkan
     */
    public function __construct(
        public readonly bool $isSuccess,
        public readonly int $resumeOffset,
        public readonly RecoveryStatistics $statistics,
        public readonly ?string $reason = null
    ) {}
}
