<?php

namespace App\Services\Mappers;

class MappingDefinition
{
    public function __construct(
        public readonly string $sourceField,
        public readonly string $targetField,
        public readonly ?string $transformerName = null,
        public readonly array $transformerOptions = [],
        public readonly mixed $defaultValue = null
    ) {}
}
