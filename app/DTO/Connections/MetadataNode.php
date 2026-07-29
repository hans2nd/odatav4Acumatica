<?php

declare(strict_types=1);

namespace App\DTO\Connections;

final readonly class MetadataNode
{
    /**
     * @param string $name Name of the object (table name, entity name, endpoint path)
     * @param string $type Type of object (table, view, entity, endpoint, procedure)
     * @param bool $isQueryable Whether this object can be queried
     * @param array $properties List of standardized column/property schemas
     */
    public function __construct(
        public string $name,
        public string $type,
        public bool $isQueryable = true,
        public array $properties = []
    ) {}
}
