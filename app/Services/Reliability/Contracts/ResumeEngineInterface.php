<?php

namespace App\Services\Reliability\Contracts;

use App\Services\Reliability\Recovery\ResumeRequest;
use App\Services\Reliability\Recovery\ResumeContext;
use App\Services\Reliability\Recovery\ResumeResult;

interface ResumeEngineInterface
{
    /**
     * Merumuskan dan memulihkan eksekusi (menghasilkan resume offset yang aman)
     */
    public function resume(ResumeRequest $request, ResumeContext $context): ResumeResult;
}
