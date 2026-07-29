<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\UpsertContext;

interface UpdateFieldResolverInterface
{
    /**
     * Mengembalikan daftar nama kolom yang diizinkan untuk di-Update pada saat terjadi duplikasi data.
     * @param array $records Array dari data sumber
     * @param array $conflictKeys Daftar primary key
     * @return array<string>
     */
    public function resolve(array $records, array $conflictKeys, UpsertContext $context): array;
}
