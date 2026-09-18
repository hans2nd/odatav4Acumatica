<?php

namespace App\Services\Alerting;

use App\Services\Alerting\Contracts\AlertRuleInterface;
use App\Services\Alerting\Contracts\NotificationProviderInterface;

class AlertRegistry
{
    /** @var array<AlertRuleInterface> */
    private array $rules = [];

    /** @var array<NotificationProviderInterface> */
    private array $providers = [];

    public function registerRule(AlertRuleInterface $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * @return array<AlertRuleInterface>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function registerProvider(NotificationProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return array<NotificationProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
