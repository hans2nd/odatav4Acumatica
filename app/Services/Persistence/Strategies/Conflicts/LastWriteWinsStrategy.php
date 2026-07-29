<?php

namespace App\Services\Persistence\Strategies\Conflicts;

use App\Services\Persistence\Contracts\ConflictResolutionStrategyInterface;
use App\Services\Persistence\ConflictResolutionContext;
use App\Services\Persistence\ConflictResolutionRequest;
use App\Services\Persistence\ConflictResolutionResult;
use App\Services\Persistence\ConflictResolutionPolicy;

class LastWriteWinsStrategy implements ConflictResolutionStrategyInterface
{
    public function getName(): string
    {
        return ConflictResolutionPolicy::LAST_WRITE_WINS;
    }

    public function resolve(ConflictResolutionRequest $request, ConflictResolutionContext $context): ConflictResolutionResult
    {
        // Dalam konteks sinkronisasi, sumber datang dihitung sebagai "penulisan terakhir".
        // Oleh karenanya kita memenangkan sourceRecord secara penuh.
        return new ConflictResolutionResult(
            isResolved: true,
            resolvedRecord: $request->sourceRecord,
            decision: 'SOURCE_SELECTED'
        );
    }
}
