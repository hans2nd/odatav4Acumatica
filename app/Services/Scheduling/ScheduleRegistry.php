<?php

namespace App\Services\Scheduling;

use App\Services\Scheduling\Contracts\SchedulePolicyInterface;

class ScheduleRegistry
{
    /** @var array<string, ScheduleDefinition> */
    private array $definitions = [];

    /** @var array<SchedulePolicyInterface> */
    private array $policies = [];

    public function registerDefinition(ScheduleDefinition $definition): void
    {
        $this->definitions[$definition->jobId] = $definition;
    }

    public function getDefinition(string $jobId): ?ScheduleDefinition
    {
        return $this->definitions[$jobId] ?? null;
    }

    public function registerPolicy(SchedulePolicyInterface $policy): void
    {
        $this->policies[] = $policy;
    }

    /**
     * @return array<SchedulePolicyInterface>
     */
    public function getPolicies(): array
    {
        return $this->policies;
    }
}
