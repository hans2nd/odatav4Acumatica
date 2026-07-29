<?php

namespace App\Services\Reliability\Recovery;

class CheckpointValidator
{
    public function validate(CheckpointSnapshot $snapshot): bool
    {
        // Contoh validasi kemurnian data (Checksum comparison)
        // Di sistem ril, kita meregenerasi hash dari jobId dan offset lalu membandingkannya
        // dengan $snapshot->checksum untuk mencegah intervensi manual (tampering).

        if (empty($snapshot->jobId)) {
            return false;
        }

        if ($snapshot->offset < 0) {
            return false;
        }

        return true;
    }
}
