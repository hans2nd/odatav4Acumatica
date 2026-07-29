<?php

namespace App\Services\Persistence\Pipeline;

class PersistencePipelineContext
{
    /**
     * @param string $connectionName
     * @param string $tableName
     * @param array $primaryKeys
     * @param array $upsertRecords Data sumber untuk sinkronisasi (Upsert/Insert)
     * @param array $deleteRecords Data sumber/ID untuk sinkronisasi (Delete)
     * @param string $conflictPolicy Kebijakan resolusi konflik
     * @param string $deletePolicy Kebijakan penghapusan
     */
    public function __construct(
        public readonly string $connectionName,
        public readonly string $tableName,
        public readonly array $primaryKeys,
        public readonly array $upsertRecords = [],
        public readonly array $deleteRecords = [],
        public readonly string $conflictPolicy = 'LAST_WRITE_WINS',
        public readonly string $deletePolicy = 'IGNORE'
    ) {}
}
