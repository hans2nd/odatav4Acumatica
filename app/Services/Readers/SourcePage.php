<?php

namespace App\Services\Readers;

class SourcePage
{
    public function __construct(
        public readonly ?string $cursorToken = null,
        public readonly int $offset = 0
    ) {}

    public function hasNext(): bool
    {
        return $this->cursorToken !== null || $this->offset > 0;
    }
}
