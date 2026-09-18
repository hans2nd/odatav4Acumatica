<?php

namespace App\Services\Mappers\Transformers;

use App\Services\Mappers\Contracts\FieldTransformerInterface;
use InvalidArgumentException;

class TransformerRegistry
{
    /**
     * @var array<string, FieldTransformerInterface>
     */
    private array $transformers = [];

    public function register(string $name, FieldTransformerInterface $transformer): void
    {
        $this->transformers[$name] = $transformer;
    }

    public function get(string $name): FieldTransformerInterface
    {
        if (!isset($this->transformers[$name])) {
            throw new InvalidArgumentException("Transformer belum diregistrasikan: {$name}");
        }

        return $this->transformers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->transformers[$name]);
    }
}
