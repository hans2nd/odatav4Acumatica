<?php

namespace App\Services\ChangeDetection\Contracts;

use App\Services\Mappers\NormalizedRecord;
use App\Services\ChangeDetection\RecordFingerprint;

interface FingerprintGeneratorInterface
{
    /**
     * Menghasilkan nilai *hash* kanonikal absolut untuk mendeteksi kesamaan data persis (exact match).
     * 
     * @param NormalizedRecord $record Rekaman data ternormalisasi
     * @param array $excludedFields Daftar kolom (contoh: updated_at) yang tidak boleh memengaruhi perhitungan hash.
     * @return RecordFingerprint Objek identitas hash.
     */
    public function generate(NormalizedRecord $record, array $excludedFields = []): RecordFingerprint;
}
