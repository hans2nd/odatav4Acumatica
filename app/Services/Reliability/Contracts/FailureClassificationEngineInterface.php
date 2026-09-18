<?php

namespace App\Services\Reliability\Contracts;

use App\Services\Reliability\Classification\FailureClassificationRequest;
use App\Services\Reliability\Classification\FailureContext;
use App\Services\Reliability\Classification\FailureClassificationResult;

interface FailureClassificationEngineInterface
{
    /**
     * Menganalisa permintaan kegagalan dan mengklasifikasikannya lewat Registry.
     */
    public function classify(FailureClassificationRequest $request, FailureContext $context): FailureClassificationResult;
}
