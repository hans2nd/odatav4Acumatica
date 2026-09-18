<?php

namespace App\Services\ChangeDetection;

use App\Services\ChangeDetection\Contracts\FingerprintGeneratorInterface;
use App\Services\Mappers\NormalizedRecord;

class FingerprintGenerator implements FingerprintGeneratorInterface
{
    public function generate(NormalizedRecord $record, array $excludedFields = []): RecordFingerprint
    {
        $data = $record->toArray();

        // 1. Singkirkan kolom pengecualian
        foreach ($excludedFields as $field) {
            unset($data[$field]);
        }

        // 2. Kanonisasi: Urutkan array berdasarkan kunci agar urutan acak tidak merubah hash
        ksort($data);

        // 3. Serialisasi (JSON menjamin tipe data terkonversi ke string secara persis dan deterministik)
        $serialized = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        // 4. Hitung Sidik Jari (Hash SHA-256)
        $hash = hash('sha256', $serialized);

        return new RecordFingerprint($hash, 'sha256');
    }
}
