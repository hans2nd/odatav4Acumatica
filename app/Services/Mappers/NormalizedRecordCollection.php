<?php

namespace App\Services\Mappers;

use IteratorAggregate;
use Countable;
use Traversable;
use ArrayIterator;

class NormalizedRecordCollection implements IteratorAggregate, Countable
{
    public function __construct(
        private readonly array $records = []
    ) {}

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->records);
    }

    public function count(): int
    {
        return count($this->records);
    }

    public function isEmpty(): bool
    {
        return empty($this->records);
    }

    public function toArray(): array
    {
        return $this->records;
    }
}
