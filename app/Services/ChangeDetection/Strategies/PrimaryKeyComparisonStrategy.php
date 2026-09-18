<?php

namespace App\Services\ChangeDetection\Strategies;

use App\Services\ChangeDetection\Contracts\ComparisonStrategyInterface;
use App\Services\ChangeDetection\ChangeOperation;
use App\Services\ChangeDetection\ChangeDetectionContext;
use App\Services\Mappers\NormalizedRecord;

class PrimaryKeyComparisonStrategy implements ComparisonStrategyInterface
{
    public function compare(NormalizedRecord $record, ChangeDetectionContext $context): ChangeOperation
    {
        // Snapshot null berarti rekaman sama sekali belum ada di database lokal
        if ($context->snapshot === null) {
            return new ChangeOperation(
                ChangeOperation::INSERT, 
                "Snapshot tujuan tidak ditemukan (Rekaman baru)."
            );
        }

        return new ChangeOperation(
            ChangeOperation::UPDATE, 
            "Snapshot tujuan ditemukan. Pembaruan diperlukan (Perbandingan naif)."
        );
    }
}
