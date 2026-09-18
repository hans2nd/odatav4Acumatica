<?php

namespace App\Services\ChangeDetection\Strategies;

use App\Services\ChangeDetection\Contracts\ComparisonStrategyInterface;
use InvalidArgumentException;

class ComparisonStrategyRegistry
{
    /**
     * @var array<string, ComparisonStrategyInterface>
     */
    private array $strategies = [];

    public function register(string $name, ComparisonStrategyInterface $strategy): void
    {
        $this->strategies[$name] = $strategy;
    }

    public function get(string $name): ComparisonStrategyInterface
    {
        if (!isset($this->strategies[$name])) {
            throw new InvalidArgumentException("Comparison strategy belum diregistrasikan: {$name}");
        }

        return $this->strategies[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->strategies[$name]);
    }
}
