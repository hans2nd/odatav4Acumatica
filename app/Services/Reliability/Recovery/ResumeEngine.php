<?php

namespace App\Services\Reliability\Recovery;

use App\Services\Reliability\Contracts\ResumeEngineInterface;
use App\Services\Reliability\Contracts\CheckpointRecoveryInterface;
use App\Services\Reliability\Exceptions\ResumeException;
use Throwable;

class ResumeEngine implements ResumeEngineInterface
{
    public function __construct(
        private readonly CheckpointRecoveryInterface $checkpointRecovery
    ) {}

    public function resume(ResumeRequest $request, ResumeContext $context): ResumeResult
    {
        try {
            // Mesin Mendelegasikan langsung ke CheckpointRecovery untuk menghitung offset.
            // Tidak ada eksekusi logic bisnis sinkronisasi di sini. Sesuai desain, 
            // ia hanya mengkoordinasi pemulihan state (offset).

            return $this->checkpointRecovery->recoverPosition($request, $context);
            
        } catch (Throwable $e) {
            throw new ResumeException("Gagal melakukan proses Resume: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
