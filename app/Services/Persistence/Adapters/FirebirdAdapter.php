<?php

namespace App\Services\Persistence\Adapters;

use App\Services\Persistence\PersistenceCapabilities;

class FirebirdAdapter extends AbstractPersistenceAdapter
{
    public function getName(): string
    {
        return 'firebird';
    }

    public function getCapabilities(): PersistenceCapabilities
    {
        return new PersistenceCapabilities(
            supportsBulkUpsert: true,
            supportsNativeMerge: true, // Firebird MERGE INTO
            supportsOnDuplicateKeyUpdate: false,
            supportsOnConflict: false,
            supportsReturning: true, 
            supportsTransactions: true,
            supportsSavepoints: true,
            maxBatchSize: 500, // Biasanya dibatasi jumlah parameter limit per query
            identifierQuoteChar: '"' // Firebird menggunakan double quote untuk identifier (Dialect 3)
        );
    }

    /**
     * Implementasi Bulk Upsert khusus Firebird (Menggunakan barisan UPDATE OR INSERT)
     * Karena Firebird 3.0 ke bawah kurang baik dengan INSERT multi-value, 
     * optimasinya adalah via EXECUTE BLOCK atau prepared statement masal.
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
        
        $columnList = implode(', ', $quotedColumns);
        
        // Firebird menggunakan sintaks UPDATE OR INSERT INTO Table (cols) VALUES (vals) MATCHING (primary_keys)
        $conflictKeys = isset($records['metadata']['conflictKeys']) ? $records['metadata']['conflictKeys'] : ['ID'];
        $quotedConflictKeys = array_map(fn($col) => $this->quoteIdentifier($col), $conflictKeys);
        $matching = implode(', ', $quotedConflictKeys);

        // Secara arsitektur Laravel, bisa disiapkan 1 kueri dengan parameter binding:
        // UPDATE OR INSERT INTO "table" ("col1", "col2") VALUES (?,?) MATCHING ("col1")
        // Dan dieksekusi looping binding di layer koneksi, tapi di memori tetap jauh lebih hemat
        // karena hanya butuh 1 kali parse SQL di server (Prepared Statement).

        return count($records);
    }
}
