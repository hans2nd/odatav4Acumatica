<?php

namespace App\Services\BatchProcessing;

use App\Services\BatchProcessing\Contracts\BatchProcessingEngineInterface;
use App\Services\BatchPlanning\ExecutionPlan;
use App\Services\BatchProcessing\Exceptions\BatchExecutionException;
use Throwable;

class BatchProcessingEngine implements BatchProcessingEngineInterface
{
    public function execute(ExecutionPlan $plan, BatchExecutionContext $context): BatchExecutionResult
    {
        $startTime = microtime(true);
        $statistics = new BatchExecutionStatistics();
        $batchResults = [];
        $status = BatchExecutionResult::STATUS_COMPLETED;

        foreach ($plan->batchPlans as $batchPlan) {
            foreach ($batchPlan->batchGroups as $group) {
                // 1. Cek pembatalan
                if ($context->isCancelled()) {
                    $status = BatchExecutionResult::STATUS_CANCELLED;
                    if (is_callable($context->onBatchSkipped)) {
                        call_user_func($context->onBatchSkipped, $group, "Eksekusi dibatalkan (Cancelled).");
                    }
                    continue;
                }

                $statistics->totalBatches++;

                // 2. Lifecycle: Sebelum Eksekusi
                if (is_callable($context->onBeforeBatch)) {
                    call_user_func($context->onBeforeBatch, $group);
                }

                try {
                    // 3. Eksekusi Inti (Persistensi di Fase selanjutnya)
                    $processedCount = 0;
                    if (is_callable($context->executorCallback)) {
                        $processedCount = call_user_func($context->executorCallback, $group);
                    }

                    // 4. Catat kesuksesan
                    $result = new BatchResult($group, true, null, $processedCount);
                    $batchResults[] = $result;

                    $statistics->successfulBatches++;
                    $statistics->totalRecordsProcessed += $processedCount;

                    // 5. Lifecycle: Sesudah Eksekusi Sukses
                    if (is_callable($context->onAfterBatch)) {
                        call_user_func($context->onAfterBatch, $group, $result);
                    }
                } catch (Throwable $e) {
                    // Penanganan kegagalan
                    $result = new BatchResult($group, false, $e, 0);
                    $batchResults[] = $result;
                    
                    $statistics->failedBatches++;

                    if (is_callable($context->onBatchFailure)) {
                        call_user_func($context->onBatchFailure, $group, $e);
                    }
                }
            }
        }

        if ($status !== BatchExecutionResult::STATUS_CANCELLED && $statistics->failedBatches > 0) {
            // Jika ada yang gagal walau hanya 1, kita bisa menandainya sebagai FAILED secara global,
            // atau PARTIAL_FAILED. Untuk sementara kita tandai FAILED jika batal/gagal semua.
            // Biarkan status tetap COMPLETED jika ada sukses, dan caller akan memeriksa statistik.
        }

        $statistics->durationSeconds = round(microtime(true) - $startTime, 4);
        
        $executionResult = new BatchExecutionResult($status, $batchResults, $statistics);

        if (is_callable($context->onExecutionCompleted)) {
            call_user_func($context->onExecutionCompleted, $executionResult);
        }

        return $executionResult;
    }
}
