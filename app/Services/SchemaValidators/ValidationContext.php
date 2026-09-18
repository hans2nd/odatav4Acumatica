<?php

namespace App\Services\SchemaValidators;

use App\Services\Mappers\NormalizedRecordCollection;

class ValidationContext
{
    public function __construct(
        public readonly NormalizedRecordCollection $records,
        public readonly ValidationProfile $profile
    ) {}
}
