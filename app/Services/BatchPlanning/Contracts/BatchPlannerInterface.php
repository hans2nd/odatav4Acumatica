<?php

namespace App\Services\BatchPlanning\Contracts;

use App\Services\BatchPlanning\ExecutionPlan;

interface BatchPlannerInterface
{
    /**
     * Menghasilkan rencana eksekusi teroptimasi berdasarkan konteks hasil deteksi perubahan.
     * 
     * @param ProcessingContextInterface $context
     * @return ExecutionPlan
     */
    public function plan(ProcessingContextInterface $context): ExecutionPlan;
}
