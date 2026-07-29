<?php

namespace App\Services\Readers;

use IteratorAggregate;
use Countable;
use Traversable;
use ArrayIterator;

class SourceRecordCollection implements IteratorAggregate, Countable
{
    public function __construct(
        private readonly array $items = []
    ) {}

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function toArray(): array
    {
        return $this->items;
    }
}
