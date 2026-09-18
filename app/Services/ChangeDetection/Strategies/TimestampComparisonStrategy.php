<?php

namespace App\Services\ChangeDetection\Strategies;

use App\Services\ChangeDetection\Contracts\ComparisonStrategyInterface;
use App\Services\ChangeDetection\ChangeOperation;
use App\Services\ChangeDetection\ChangeDetectionContext;
use App\Services\Mappers\NormalizedRecord;

class TimestampComparisonStrategy implements ComparisonStrategyInterface
{
    public function compare(NormalizedRecord $record, ChangeDetectionContext $context): ChangeOperation
    {
        if ($context->snapshot === null) {
            return new ChangeOperation(
                ChangeOperation::INSERT, 
                "Snapshot tujuan tidak ditemukan (Rekaman baru)."
            );
        }

        // Ambil nama kolom timestamp dari options, atau fallback ke default
        $timestampField = $context->options['timestampField'] ?? 'updated_at';

        $sourceTs = $record->get($timestampField);
        $snapshotTs = $context->snapshot->get($timestampField);

        if ($sourceTs === null || $snapshotTs === null) {
             return new ChangeOperation(
                ChangeOperation::UPDATE, 
                "Informasi timestamp tidak lengkap, memaksa pembaharuan (fallback)."
            );
        }

        if ($sourceTs > $snapshotTs) {
             return new ChangeOperation(
                ChangeOperation::UPDATE, 
                "Timestamp sumber lebih baru dibanding tujuan."
            );
        }

        return new ChangeOperation(
            ChangeOperation::UNCHANGED, 
            "Timestamp tidak menunjukkan ada pergerakan perubahan (Sama atau Lebih Tua)."
        );
    }
}
