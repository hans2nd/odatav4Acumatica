<?php

namespace App\Services\Monitoring;

use App\Services\Monitoring\Contracts\HealthRuleInterface;

class MonitoringRegistry
{
    /** @var array<HealthRuleInterface> */
    private array $rules = [];

    public function registerRule(HealthRuleInterface $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * @return array<HealthRuleInterface>
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
