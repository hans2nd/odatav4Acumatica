<?php

namespace App\Services\Observability;

use App\Services\Observability\Contracts\ObservabilityProviderInterface;

class ObservabilityRegistry
{
    /** @var array<ObservabilityProviderInterface> */
    private array $providers = [];

    public function registerProvider(ObservabilityProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return array<ObservabilityProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
