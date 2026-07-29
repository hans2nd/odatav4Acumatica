<?php

namespace App\Services\Mappers;

class MappingContext
{
    /**
     * @param array<MappingDefinition> $definitions
     */
    public function __construct(
        public readonly array $definitions
    ) {}
}
