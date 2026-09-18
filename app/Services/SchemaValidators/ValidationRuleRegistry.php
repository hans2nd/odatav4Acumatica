<?php

namespace App\Services\SchemaValidators;

use App\Services\SchemaValidators\Contracts\ValidationRuleInterface;
use InvalidArgumentException;

class ValidationRuleRegistry
{
    /**
     * @var array<string, ValidationRuleInterface>
     */
    private array $rules = [];

    public function register(string $name, ValidationRuleInterface $rule): void
    {
        $this->rules[$name] = $rule;
    }

    public function get(string $name): ValidationRuleInterface
    {
        if (!isset($this->rules[$name])) {
            throw new InvalidArgumentException("Aturan validasi belum didaftarkan: {$name}");
        }

        return $this->rules[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->rules[$name]);
    }
}
