<?php

namespace App\Services\Reliability;

use Throwable;

class RetryRequest
{
    /**
     * @param Throwable $exception Kesalahan/Exception penyebab kegagalan
     * @param int $currentAttempt Berapa kali operasi ini sudah dicoba
     */
    public function __construct(
        public readonly Throwable $exception,
        public readonly int $currentAttempt
    ) {}
}
