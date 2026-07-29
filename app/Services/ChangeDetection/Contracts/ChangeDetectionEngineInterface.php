<?php

namespace App\Services\ChangeDetection\Contracts;

use App\Services\Mappers\NormalizedRecord;
use App\Services\ChangeDetection\ChangeDetectionContext;
use App\Services\ChangeDetection\ChangeDetectionResult;

interface ChangeDetectionEngineInterface
{
    /**
     * Memproses satu rekaman utuh melintasi strategi perbandingan untuk menghasilkan keputusan tindakan data.
     * 
     * @param NormalizedRecord $record
     * @param ChangeDetectionContext $context
     * @return ChangeDetectionResult
     */
    public function detect(NormalizedRecord $record, ChangeDetectionContext $context): ChangeDetectionResult;
}
