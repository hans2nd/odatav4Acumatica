<?php

namespace App\Services\Reliability\Contracts;

use App\Services\Reliability\Recovery\ResumeRequest;
use App\Services\Reliability\Recovery\ResumeContext;
use App\Services\Reliability\Recovery\ResumeResult;

interface CheckpointRecoveryInterface
{
    /**
     * Menganalisa titik periksa dan menghasilkan keputusan posisi pemulihan (offset)
     */
    public function recoverPosition(ResumeRequest $request, ResumeContext $context): ResumeResult;
}
