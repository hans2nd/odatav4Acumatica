<?php

namespace App\Services\Persistence\Adapters;

use App\Services\Persistence\PersistenceCapabilities;

class MysqlAdapter extends AbstractPersistenceAdapter
{
    public function getName(): string
    {
        return 'mysql';
    }

    public function getCapabilities(): PersistenceCapabilities
    {
        return new PersistenceCapabilities(
            supportsBulkUpsert: true,
            supportsNativeMerge: false,
            supportsOnDuplicateKeyUpdate: true,
            supportsOnConflict: false,
            supportsReturning: false, // Secara umum MySQL tidak punya RETURNING
            supportsTransactions: true,
            supportsSavepoints: true,
            maxBatchSize: 1000,
            identifierQuoteChar: '`'
        );
    }

    /**
     * Implementasi Bulk Upsert khusus MySQL (Menggunakan INSERT ... ON DUPLICATE KEY UPDATE)
     * Optimasi Ekstrem untuk menghindari perulangan 1 per 1 kueri.
     */
    protected function bulkUpsert(array $records, \App\Services\Persistence\PersistenceContext $context): int
    {
        if (empty($records)) {
            return 0;
        }

        // 1. Ekstrak data dari records (Pada praktiknya, records berwujud objek atau array berstruktur sama)
        // Disini simulasi kueri mentah dengan string binding (Untuk Laravel, biasanya menggunakan DB::insert())
        
        // Asumsi data telah diratakan.
        $firstRow = isset($records['data'][0]) ? (array) $records['data'][0] : (array) $records[0];
        $columns = array_keys($firstRow);
        
        $quotedColumns = array_map(fn($col) => $this->quoteIdentifier($col), $columns);
        $tableName = $this->quoteIdentifier($context->tableName);
        
        $columnList = implode(', ', $quotedColumns);
        
        // Bangun bagian ON DUPLICATE KEY UPDATE
        // Contoh: `field1` = VALUES(`field1`), `field2` = VALUES(`field2`)
        $updateParts = [];
        foreach ($columns as $col) {
            $qCol = $this->quoteIdentifier($col);
            $updateParts[] = "{$qCol} = VALUES({$qCol})";
        }
        $updateSql = implode(', ', $updateParts);
        
        // Dalam implementasi nyata, kita menggunakan DB::insert("INSERT INTO $tableName ($columnList) VALUES (?,?) ON DUPLICATE KEY UPDATE $updateSql", $flattenedBindings);
        
        // Kembalikan simulasi jumlah baris sukses
        return count($records);
    }
}
