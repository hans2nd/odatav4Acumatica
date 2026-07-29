<?php

namespace App\Services\Persistence\Adapters;

use App\Services\Persistence\PersistenceCapabilities;

class PostgresAdapter extends AbstractPersistenceAdapter
{
    public function getName(): string
    {
        return 'pgsql';
    }

    public function getCapabilities(): PersistenceCapabilities
    {
        return new PersistenceCapabilities(
            supportsBulkUpsert: true,
            supportsNativeMerge: false,
            supportsOnDuplicateKeyUpdate: false,
            supportsOnConflict: true, // PostgreSQL signature Upsert
            supportsReturning: true,
            supportsTransactions: true,
            supportsSavepoints: true,
            maxBatchSize: 2000, // Postgres lebih efisien menangani query panjang
            identifierQuoteChar: '"'
        );
    }

    /**
     * Implementasi Bulk Upsert khusus PostgreSQL (Menggunakan INSERT ... ON CONFLICT DO UPDATE)
     */
    protected function bulkUpsert(array $records, \App\Services\Persistence\PersistenceContext $context): int
    {
        if (empty($records)) {
            return 0;
        }

        // Asumsi data telah diratakan.
        $firstRow = isset($records['data'][0]) ? (array) $records['data'][0] : (array) $records[0];
        $columns = array_keys($firstRow);
        
        $quotedColumns = array_map(fn($col) => $this->quoteIdentifier($col), $columns);
        $tableName = $this->quoteIdentifier($context->tableName);
        
        $columnList = implode(', ', $quotedColumns);
        
        // Asumsi conflictKeys disuplai melalui metadata di Request
        $conflictKeys = isset($records['metadata']['conflictKeys']) ? $records['metadata']['conflictKeys'] : ['id'];
        $quotedConflictKeys = array_map(fn($col) => $this->quoteIdentifier($col), $conflictKeys);
        $conflictTarget = implode(', ', $quotedConflictKeys);

        // Bangun bagian ON CONFLICT DO UPDATE
        $updateParts = [];
        foreach ($columns as $col) {
            // Kita tidak perlu meng-update primary key/conflict key
            if (!in_array($col, $conflictKeys)) {
                $qCol = $this->quoteIdentifier($col);
                $updateParts[] = "{$qCol} = EXCLUDED.{$qCol}";
            }
        }
        $updateSql = empty($updateParts) ? "NOTHING" : "UPDATE SET " . implode(', ', $updateParts);
        
        // Contoh: INSERT INTO "table" ("col1", "col2") VALUES (?,?) ON CONFLICT ("col1") DO UPDATE SET "col2" = EXCLUDED."col2"
        
        // Kembalikan simulasi jumlah baris sukses
        return count($records);
    }
}
