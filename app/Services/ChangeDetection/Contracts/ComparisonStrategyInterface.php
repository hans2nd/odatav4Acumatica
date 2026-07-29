<?php

namespace App\Services\ChangeDetection\Contracts;

use App\Services\Mappers\NormalizedRecord;
use App\Services\ChangeDetection\ChangeDetectionContext;
use App\Services\ChangeDetection\ChangeOperation;

interface ComparisonStrategyInterface
{
    /**
     * Membandingkan rekaman baru dengan cuplikan lama (*snapshot*) untuk menentukan jenis operasi mutasi.
     * 
     * @param NormalizedRecord $record
     * @param ChangeDetectionContext $context
     * @return ChangeOperation
     */
    public function compare(NormalizedRecord $record, ChangeDetectionContext $context): ChangeOperation;
}
