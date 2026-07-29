<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\UpsertContext;

interface ConflictKeyResolverInterface
{
    /**
     * Mengembalikan daftar nama kolom yang bertindak sebagai Kunci Konflik (Primary/Composite Key).
     * @return array<string>
     */
    public function resolve(UpsertContext $context): array;
}
