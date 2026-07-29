<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\ConflictResolutionContext;
use App\Services\Persistence\ConflictResolutionRequest;
use App\Services\Persistence\ConflictResolutionResult;

interface ConflictResolutionEngineInterface
{
    /**
     * Menangani penyelesaian konflik dengan menemukan strategi yang tepat.
     */
    public function resolve(ConflictResolutionRequest $request, ConflictResolutionContext $context): ConflictResolutionResult;
}
