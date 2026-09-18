<?php

namespace App\Services\Persistence;

class ConflictResolutionContext
{
    public function __construct(
        public readonly string $entityName,
        public readonly string $policy, // Referensi dari ConflictResolutionPolicy
        public readonly ?string $timestampField = null,
        public readonly ?string $versionField = null
    ) {}
}
