<?php

namespace App\Services\Persistence\Strategies\Conflicts;

use App\Services\Persistence\Contracts\ConflictResolutionStrategyInterface;
use App\Services\Persistence\ConflictResolutionContext;
use App\Services\Persistence\ConflictResolutionRequest;
use App\Services\Persistence\ConflictResolutionResult;
use App\Services\Persistence\ConflictResolutionPolicy;
use App\Services\Persistence\Exceptions\ConflictResolutionException;

class FailFastStrategy implements ConflictResolutionStrategyInterface
{
    public function getName(): string
    {
        return ConflictResolutionPolicy::FAIL_FAST;
    }

    public function resolve(ConflictResolutionRequest $request, ConflictResolutionContext $context): ConflictResolutionResult
    {
        throw new ConflictResolutionException("Konflik terdeteksi dan kebijakan FAIL_FAST aktif. Eksekusi dihentikan.");
    }
}
