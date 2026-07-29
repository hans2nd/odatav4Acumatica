<?php

namespace App\Services\Reliability\Policies;

use App\Services\Reliability\Contracts\RetryPolicyInterface;
use InvalidArgumentException;

class RetryPolicyRegistry
{
    /** @var array<string, RetryPolicyInterface> */
    private array $policies = [];

    public function register(string $policyName, RetryPolicyInterface $policy): void
    {
        $this->policies[$policyName] = $policy;
    }

    public function get(string $policyName): RetryPolicyInterface
    {
        if (!isset($this->policies[$policyName])) {
            throw new InvalidArgumentException("Retry Policy tidak dikenali: {$policyName}");
        }

        return $this->policies[$policyName];
    }
}
