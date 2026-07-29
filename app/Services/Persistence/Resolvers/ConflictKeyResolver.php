<?php

namespace App\Services\Persistence\Resolvers;

use App\Services\Persistence\Contracts\ConflictKeyResolverInterface;
use App\Services\Persistence\UpsertContext;

class ConflictKeyResolver implements ConflictKeyResolverInterface
{
    public function resolve(UpsertContext $context): array
    {
        // Dalam skenario standar, Conflict Keys adalah Primary Keys itu sendiri
        return $context->primaryKeys;
    }
}
