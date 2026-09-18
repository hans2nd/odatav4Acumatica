<?php

namespace App\Services\Mappers;

class NormalizedRecord
{
    public function __construct(
        private readonly array $data
    ) {}

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->data[$field] ?? $default;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
