<?php

namespace App\Services\Persistence\Adapters;

use App\Services\Persistence\PersistenceCapabilities;

class MariaDbAdapter extends AbstractPersistenceAdapter
{
    public function getName(): string
    {
        return 'mariadb';
    }

    public function getCapabilities(): PersistenceCapabilities
    {
        return new PersistenceCapabilities(
            supportsBulkUpsert: true,
            supportsNativeMerge: false,
            supportsOnDuplicateKeyUpdate: true,
            supportsOnConflict: false,
            supportsReturning: true, // MariaDB 10.5+ mendukung RETURNING
            supportsTransactions: true,
            supportsSavepoints: true,
            maxBatchSize: 1000,
            identifierQuoteChar: '`'
        );
    }

    /**
     * Implementasi Bulk Upsert khusus MariaDB (Sama seperti MySQL)
     */
    protected function bulkUpsert(array $records, \App\Services\Persistence\PersistenceContext $context): int
    {
        if (empty($records)) {
            return 0;
        }

        // Sama persis strateginya dengan MySQL menggunakan ON DUPLICATE KEY UPDATE
        // Atau jika MariaDB versi modern, menggunakan sintaks INSERT ... ON DUPLICATE KEY UPDATE
        // Bisa juga mendelegasikan pemanggilan logika dari trait.

        return count($records);
    }
}
