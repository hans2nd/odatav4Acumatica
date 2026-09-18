<?php

namespace App\Services\Readers;

class SourceResult
{
    public function __construct(
        private readonly SourceRecordCollection $records,
        private readonly SourcePage $nextPage,
        private readonly bool $hasMoreData,
        private readonly ?int $totalRecords = null
    ) {}

    public function getRecords(): SourceRecordCollection
    {
        return $this->records;
    }

    public function getNextPage(): SourcePage
    {
        return $this->nextPage;
    }

    public function hasMoreData(): bool
    {
        return $this->hasMoreData;
    }

    public function getTotalRecords(): ?int
    {
        return $this->totalRecords;
    }
}
