<?php

namespace App\Services\Mappers;

class MappingResult
{
    public function __construct(
        public readonly NormalizedRecordCollection $records
    ) {}
}
