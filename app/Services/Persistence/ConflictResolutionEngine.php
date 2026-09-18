<?php

namespace App\Services\Persistence;

use App\Services\Persistence\Contracts\ConflictResolutionEngineInterface;
use App\Services\Persistence\Strategies\Conflicts\ConflictResolutionStrategyRegistry;

class ConflictResolutionEngine implements ConflictResolutionEngineInterface
{
    public function __construct(
        private readonly ConflictResolutionStrategyRegistry $registry
    ) {}

    public function resolve(ConflictResolutionRequest $request, ConflictResolutionContext $context): ConflictResolutionResult
    {
        // Mencari dan mendelegasikan penyelesaian ke hakim/strategi yang tepat
        $strategy = $this->registry->get($context->policy);
        
        return $strategy->resolve($request, $context);
    }
}
