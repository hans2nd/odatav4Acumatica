<?php

namespace App\Services\BatchPlanning;

class BatchPlan
{
    /**
     * @param array<BatchGroup> $batchGroups Kumpulan operasi dalam 1 blok eksekusi.
     */
    public function __construct(
        public readonly array $batchGroups
    ) {}
}
