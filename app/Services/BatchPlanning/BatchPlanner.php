<?php

namespace App\Services\BatchPlanning;

use App\Services\BatchPlanning\Contracts\BatchPlannerInterface;
use App\Services\BatchPlanning\Contracts\ProcessingContextInterface;
use App\Services\ChangeDetection\ChangeOperation;

class BatchPlanner implements BatchPlannerInterface
{
    public function plan(ProcessingContextInterface $context): ExecutionPlan
    {
        $profile = $context->getExecutionProfile();
        $results = $context->getChangeResults();

        // 1. Singkirkan yang UNCHANGED dan kelompokkan berdasarkan Operasi
        $groupedRecords = [
            ChangeOperation::INSERT => [],
            ChangeOperation::UPDATE => [],
            ChangeOperation::DELETE => [],
        ];

        $totalEstimatedMemory = 0;
        $totalRecordCount = 0;

        foreach ($results as $result) {
            $operation = $result->operation->operation;
            
            if ($operation === ChangeOperation::UNCHANGED || $operation === ChangeOperation::UNKNOWN) {
                continue;
            }

            if (!isset($groupedRecords[$operation])) {
                $groupedRecords[$operation] = [];
            }

            $groupedRecords[$operation][] = $result->record;
            
            // Estimasi kasar: serialize untuk mendapatkan taksiran byte memori
            $totalEstimatedMemory += strlen(serialize($result->record->toArray()));
            $totalRecordCount++;
        }

        // 2. Pemotongan (Chunking) berdasarkan Max Batch Size
        $batchPlans = [];
        $transactionCount = 0;

        // Kami memprioritaskan INSERT lalu UPDATE agar Foreign Key aman
        $priorities = [ChangeOperation::INSERT, ChangeOperation::UPDATE, ChangeOperation::DELETE];

        foreach ($priorities as $operation) {
            if (empty($groupedRecords[$operation])) {
                continue;
            }

            $chunks = array_chunk($groupedRecords[$operation], $profile->maxBatchSize);

            foreach ($chunks as $chunk) {
                $batchGroup = new BatchGroup($operation, $chunk, count($chunk));
                $batchPlans[] = new BatchPlan([$batchGroup]); // 1 BatchGroup per BatchPlan untuk transaksi independen
                $transactionCount++;
            }
        }

        return new ExecutionPlan(
            $batchPlans,
            $totalRecordCount,
            $totalEstimatedMemory,
            $transactionCount,
            $totalRecordCount * 10, // Sekadar heuristik abstrak
            [
                'entity' => $context->getEntityName(),
                'planned_at' => now()->toIso8601String()
            ]
        );
    }
}
