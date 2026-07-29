<?php

namespace App\Services\Persistence;

class UpsertRequest
{
    /**
     * @param array $records Daftar record termutakhir (assosiative arrays atau NormalizedRecord).
     */
    public function __construct(
        public readonly array $records
    ) {}
}
