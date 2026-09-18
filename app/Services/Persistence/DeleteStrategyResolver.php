<?php

namespace App\Services\Persistence;

use App\Services\Persistence\Contracts\DeleteStrategyInterface;
use InvalidArgumentException;

class DeleteStrategyResolver
{
    /**
     * @var array<string, DeleteStrategyInterface>
     */
    private array $strategies = [];

    public function register(string $policy, DeleteStrategyInterface $strategy): void
    {
        $this->strategies[$policy] = $strategy;
    }

    public function resolve(string $policy): DeleteStrategyInterface
    {
        if (!isset($this->strategies[$policy])) {
            throw new InvalidArgumentException("Delete Strategy tidak dikenali untuk kebijakan: {$policy}");
        }

        return $this->strategies[$policy];
    }

    public function has(string $policy): bool
    {
        return isset($this->strategies[$policy]);
    }
}
