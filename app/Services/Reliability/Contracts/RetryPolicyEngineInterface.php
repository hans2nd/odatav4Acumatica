<?php

namespace App\Services\Reliability\Contracts;

use App\Services\Reliability\RetryRequest;
use App\Services\Reliability\RetryContext;
use App\Services\Reliability\RetryDecisionResult;

interface RetryPolicyEngineInterface
{
    /**
     * Mengevaluasi kegagalan operasi dan memutuskan tindakan selanjutnya.
     */
    public function evaluate(RetryRequest $request, RetryContext $context): RetryDecisionResult;
}
