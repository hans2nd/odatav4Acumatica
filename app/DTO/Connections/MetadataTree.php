<?php

declare(strict_types=1);

namespace App\DTO\Connections;

final readonly class MetadataTree
{
    /**
     * @param MetadataNode[] $nodes
     */
    public function __construct(
        public array $nodes = []
    ) {}
}
