<?php

namespace App\Services\Reliability\Contracts;

use App\Services\Reliability\RetryContext;

interface RetryPolicyInterface
{
    /**
     * Menghitung durasi jeda dalam milidetik (ms) untuk percobaan berikutnya
     * 
     * @param int $attempt Percobaan saat ini
     * @param RetryContext $context Konfigurasi percobaan ulang
     * @return int Penundaan (delay) dalam milidetik
     */
    public function calculateDelay(int $attempt, RetryContext $context): int;
}
