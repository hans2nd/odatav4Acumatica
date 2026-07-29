<?php

namespace App\Services\Persistence\Strategies\Conflicts;

use App\Services\Persistence\Contracts\ConflictResolutionStrategyInterface;
use InvalidArgumentException;

class ConflictResolutionStrategyRegistry
{
    /**
     * @var array<string, ConflictResolutionStrategyInterface>
     */
    private array $strategies = [];

    public function register(string $policyName, ConflictResolutionStrategyInterface $strategy): void
    {
        $this->strategies[$policyName] = $strategy;
    }

    public function get(string $policyName): ConflictResolutionStrategyInterface
    {
        if (!isset($this->strategies[$policyName])) {
            throw new InvalidArgumentException("Conflict Resolution Strategy tidak dikenali untuk kebijakan: {$policyName}");
        }

        return $this->strategies[$policyName];
    }
}
