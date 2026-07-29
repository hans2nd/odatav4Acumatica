<?php

namespace App\Services\Persistence;

class ConflictResolutionRequest
{
    /**
     * @param array $sourceRecord Rekaman dari sumber eksternal
     * @param array|null $destinationRecord Rekaman yang sudah eksis di tujuan (jika ada)
     * @param string $conflictType Jenis konflik (contoh: 'PRIMARY_KEY_CONFLICT')
     */
    public function __construct(
        public readonly array $sourceRecord,
        public readonly ?array $destinationRecord,
        public readonly string $conflictType
    ) {}
}
