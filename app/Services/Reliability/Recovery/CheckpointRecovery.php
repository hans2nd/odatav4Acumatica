<?php

namespace App\Services\Reliability\Recovery;

use App\Services\Reliability\Contracts\CheckpointRecoveryInterface;
use App\Services\Reliability\Contracts\CheckpointProviderInterface;
use App\Services\Reliability\Recovery\Enums\ResumePolicy;

class CheckpointRecovery implements CheckpointRecoveryInterface
{
    public function __construct(
        private readonly CheckpointProviderInterface $provider,
        private readonly CheckpointValidator $validator
    ) {}

    public function recoverPosition(ResumeRequest $request, ResumeContext $context): ResumeResult
    {
        $startTime = microtime(true);
        $statistics = new RecoveryStatistics();
        
        // Jika minta mulai dari 0 secara eksplisit
        if ($context->policy === ResumePolicy::RESTART_ENTIRE_SYNCHRONIZATION) {
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);
            return new ResumeResult(true, 0, $statistics, "Policy diatur ke RESTART_ENTIRE");
        }

        $snapshot = $this->provider->getLatestCheckpoint($request->jobId);
        $statistics->checkpointsEvaluated++;

        if (!$snapshot) {
            // Belum ada checkpoint, mulai dari awal
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);
            return new ResumeResult(true, 0, $statistics, "Tidak ditemukan checkpoint, mulai dari awal");
        }

        if (!$this->validator->validate($snapshot)) {
            $statistics->checkpointsRejected++;
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);
            // Checkpoint rusak, terpaksa mengembalikan offset 0 atau digagalkan
            return new ResumeResult(false, 0, $statistics, "Checkpoint ditemukan namun tidak lolos validasi integritas");
        }

        $statistics->durationSeconds = round(microtime(true) - $startTime, 4);
        return new ResumeResult(true, $snapshot->offset, $statistics, "Pemulihan berhasil dari offset " . $snapshot->offset);
    }
}
