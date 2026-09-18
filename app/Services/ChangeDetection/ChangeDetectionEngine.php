<?php

namespace App\Services\ChangeDetection;

use App\Services\ChangeDetection\Contracts\ChangeDetectionEngineInterface;
use App\Services\ChangeDetection\Strategies\ComparisonStrategyRegistry;
use App\Services\Mappers\NormalizedRecord;

class ChangeDetectionEngine implements ChangeDetectionEngineInterface
{
    public function __construct(
        private readonly ComparisonStrategyRegistry $registry
    ) {}

    public function detect(NormalizedRecord $record, ChangeDetectionContext $context): ChangeDetectionResult
    {
        $strategyName = $context->strategyName;
        
        if (!$this->registry->has($strategyName)) {
            // Fallback strategy jika nama strategy keliru
            $strategyName = 'primary_key';
        }

        $strategy = $this->registry->get($strategyName);
        $operation = $strategy->compare($record, $context);

        return new ChangeDetectionResult(
            $operation,
            $record,
            $context->snapshot
        );
    }
}
