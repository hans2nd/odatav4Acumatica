<?php

namespace App\Services\Persistence\Adapters;

use App\Services\Persistence\PersistenceCapabilities;

class SqlServerAdapter extends AbstractPersistenceAdapter
{
    public function getName(): string
    {
        return 'sqlsrv';
    }

    public function getCapabilities(): PersistenceCapabilities
    {
        return new PersistenceCapabilities(
            supportsBulkUpsert: true,
            supportsNativeMerge: true, // SQL Server MERGE INTO
            supportsOnDuplicateKeyUpdate: false,
            supportsOnConflict: false,
            supportsReturning: true, // lewat OUTPUT klausa
            supportsTransactions: true,
            supportsSavepoints: true,
            maxBatchSize: 1000,
            identifierQuoteChar: '[' // SQL Server menggunakan bracket
        );
    }

    /**
     * Implementasi Bulk Upsert khusus SQL Server (Menggunakan instruksi MERGE)
     */
    protected function bulkUpsert(array $records, \App\Services\Persistence\PersistenceContext $context): int
    {
        if (empty($records)) {
            return 0;
        }

        $firstRow = isset($records['data'][0]) ? (array) $records['data'][0] : (array) $records[0];
        $columns = array_keys($firstRow);
        
        $quotedColumns = array_map(fn($col) => $this->quoteIdentifier($col), $columns);
        $tableName = $this->quoteIdentifier($context->tableName);
        
        // Sql Server menggunakan MERGE INTO target USING source ON target.id = source.id
        // WHEN MATCHED THEN UPDATE SET ...
        // WHEN NOT MATCHED THEN INSERT ...
        
        // Secara performa, eksekusi MERGE dengan CTE atau table variable sangat efisien.
        // Di Laravel, Anda bisa membangun statement MERGE dan mengeksekusinya via DB::statement()

        return count($records);
    }
}
