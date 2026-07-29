<?php

namespace App\Services\Persistence\Strategies\Conflicts;

use App\Services\Persistence\Contracts\ConflictResolutionStrategyInterface;
use App\Services\Persistence\ConflictResolutionContext;
use App\Services\Persistence\ConflictResolutionRequest;
use App\Services\Persistence\ConflictResolutionResult;
use App\Services\Persistence\ConflictResolutionPolicy;

class TimestampComparisonStrategy implements ConflictResolutionStrategyInterface
{
    public function getName(): string
    {
        return ConflictResolutionPolicy::TIMESTAMP_COMPARISON;
    }

    public function resolve(ConflictResolutionRequest $request, ConflictResolutionContext $context): ConflictResolutionResult
    {
        if (empty($context->timestampField)) {
            // Jika field tidak tersedia di konteks, jatuh balik ke sumber
            return new ConflictResolutionResult(true, $request->sourceRecord, 'SOURCE_SELECTED_FALLBACK');
        }

        $field = $context->timestampField;
        
        $sourceTime = $request->sourceRecord[$field] ?? null;
        $destTime = $request->destinationRecord[$field] ?? null;

        // Jika tujuan tidak ada atau timestamp-nya usang, menangkan sumber
        if (!$destTime || ($sourceTime && strtotime($sourceTime) > strtotime($destTime))) {
            return new ConflictResolutionResult(true, $request->sourceRecord, 'SOURCE_SELECTED_NEWER');
        }

        return new ConflictResolutionResult(true, $request->destinationRecord, 'DESTINATION_SELECTED_NEWER');
    }
}
