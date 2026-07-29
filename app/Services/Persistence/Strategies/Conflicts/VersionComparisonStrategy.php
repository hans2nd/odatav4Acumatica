<?php

namespace App\Services\Persistence\Strategies\Conflicts;

use App\Services\Persistence\Contracts\ConflictResolutionStrategyInterface;
use App\Services\Persistence\ConflictResolutionContext;
use App\Services\Persistence\ConflictResolutionRequest;
use App\Services\Persistence\ConflictResolutionResult;
use App\Services\Persistence\ConflictResolutionPolicy;

class VersionComparisonStrategy implements ConflictResolutionStrategyInterface
{
    public function getName(): string
    {
        return ConflictResolutionPolicy::VERSION_COMPARISON;
    }

    public function resolve(ConflictResolutionRequest $request, ConflictResolutionContext $context): ConflictResolutionResult
    {
        if (empty($context->versionField)) {
            return new ConflictResolutionResult(true, $request->sourceRecord, 'SOURCE_SELECTED_FALLBACK');
        }

        $field = $context->versionField;
        
        $sourceVer = (int) ($request->sourceRecord[$field] ?? 0);
        $destVer = (int) ($request->destinationRecord[$field] ?? 0);

        if ($sourceVer > $destVer) {
            return new ConflictResolutionResult(true, $request->sourceRecord, 'SOURCE_SELECTED_HIGHER_VERSION');
        }

        return new ConflictResolutionResult(true, $request->destinationRecord, 'DESTINATION_SELECTED_HIGHER_VERSION');
    }
}
