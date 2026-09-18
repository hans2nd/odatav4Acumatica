<?php

namespace App\Services\Validators\Rules;

use App\Services\Validators\Contracts\ValidationRuleInterface;
use App\Services\Validators\ValidationContext;
use App\Services\Validators\ValidationIssue;
use App\Services\Validators\RecoveryRecommendation;
use App\Services\Watermarks\Strategies\TimestampPrimaryKeyStrategy;

class WatermarkProgressionRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ?ValidationIssue
    {
        $previous = $context->previousWatermark;
        $next = $context->executionResult->nextWatermark;

        if ($previous->isEmpty() || $next->isEmpty()) {
            return null; // Sinkronisasi inisial atau tidak ada data yang ditarik, valid.
        }

        // Kami meminjam strategi inti untuk komparasi konsisten
        $strategy = new TimestampPrimaryKeyStrategy();
        
        try {
            $strategy->validate($next);
        } catch (\Exception $e) {
            return new ValidationIssue(
                'WatermarkProgressionRule',
                "Watermark baru tidak valid: " . $e->getMessage(),
                'Data Integrity Error',
                new RecoveryRecommendation(
                    'ABORT_SYNC',
                    'Batalkan sinkronisasi. Jangan lanjutkan jika watermark yang akan diperbarui tidak memenuhi standar mesin.'
                )
            );
        }

        if ($strategy->compare($next, $previous) < 0) {
            return new ValidationIssue(
                'WatermarkProgressionRule',
                "Mendeteksi Time-Travel Mundur: Watermark baru (" . json_encode($next) . ") lebih kecil dari watermark sebelumnya (" . json_encode($previous) . ").",
                'Non-Recoverable',
                new RecoveryRecommendation(
                    'ABORT_SYNC',
                    'Anomali serius pada pengurutan sumber data. Hentikan seluruh proses untuk mencegah kerusakan data berkelanjutan.'
                )
            );
        }

        return null;
    }
}
