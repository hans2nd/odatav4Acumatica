<?php

namespace App\Services\Observability\Models;

class ObservabilityResult
{
    /**
     * @param bool $isPublished Apakah data berhasil dipublikasikan ke minimal satu provider
     * @param int $publishedToCount Jumlah provider yang berhasil menerima data
     */
    public function __construct(
        public readonly bool $isPublished,
        public readonly int $publishedToCount
    ) {}
}
