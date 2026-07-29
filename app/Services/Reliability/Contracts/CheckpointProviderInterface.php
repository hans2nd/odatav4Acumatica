<?php

namespace App\Services\Reliability\Contracts;

use App\Services\Reliability\Recovery\CheckpointSnapshot;

interface CheckpointProviderInterface
{
    /**
     * Mengambil checkpoint terakhir yang berhasil untuk suatu job
     */
    public function getLatestCheckpoint(string $jobId): ?CheckpointSnapshot;

    /**
     * Menyimpan checkpoint sukses yang baru
     */
    public function saveCheckpoint(CheckpointSnapshot $snapshot): void;

    /**
     * Menghapus checkpoint terkait job jika sudah tidak dibutuhkan
     */
    public function clearCheckpoint(string $jobId): void;
}
