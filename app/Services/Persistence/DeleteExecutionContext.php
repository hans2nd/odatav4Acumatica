<?php

namespace App\Services\Persistence;

class DeleteExecutionContext
{
    public function __construct(
        public readonly string $connectionName,
        public readonly string $tableName,
        public readonly array $primaryKeys,
        public readonly string $policy, // Referensi dari DeletePolicy::class
        public readonly bool $isProtected = false, // Mencegah hard/soft delete jika true
        public readonly array $softDeleteFields = ['deleted_at' => null], // Format: ['nama_kolom' => fungsi/nilai]
        public readonly string $archiveTableName = '' // Diperlukan hanya jika policy == ARCHIVE
    ) {}
}
