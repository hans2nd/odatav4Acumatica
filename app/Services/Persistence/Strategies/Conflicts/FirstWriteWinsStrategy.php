<?php

namespace App\Services\Persistence\Strategies\Conflicts;

use App\Services\Persistence\Contracts\ConflictResolutionStrategyInterface;
use App\Services\Persistence\ConflictResolutionContext;
use App\Services\Persistence\ConflictResolutionRequest;
use App\Services\Persistence\ConflictResolutionResult;
use App\Services\Persistence\ConflictResolutionPolicy;

class FirstWriteWinsStrategy implements ConflictResolutionStrategyInterface
{
    public function getName(): string
    {
        return ConflictResolutionPolicy::FIRST_WRITE_WINS;
    }

    public function resolve(ConflictResolutionRequest $request, ConflictResolutionContext $context): ConflictResolutionResult
    {
        // DestinationRecord dianggap sebagai tulisan pertama.
        // Kita memenangkan destinationRecord. Jika tujuan kosong, menangkan source.
        $winner = $request->destinationRecord ?? $request->sourceRecord;
        $decision = $request->destinationRecord ? 'DESTINATION_SELECTED' : 'SOURCE_SELECTED';
        
        return new ConflictResolutionResult(
            isResolved: true,
            resolvedRecord: $winner,
            decision: $decision
        );
    }
}
