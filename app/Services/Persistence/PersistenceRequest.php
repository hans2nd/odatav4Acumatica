<?php

namespace App\Services\Persistence;

class PersistenceRequest
{
    /**
     * @param string $operation Tipe operasi (contoh: INSERT, UPDATE)
     * @param array $records Daftar objek NormalizedRecord atau array murni
     */
    public function __construct(
        public readonly string $operation,
        public readonly array $records
    ) {}
}
