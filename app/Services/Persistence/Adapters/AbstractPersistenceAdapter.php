<?php

namespace App\Services\Persistence\Adapters;

use App\Services\Persistence\Contracts\PersistenceAdapterInterface;
use App\Services\Persistence\PersistenceContext;
use App\Services\Persistence\PersistenceRequest;
use App\Services\Persistence\PersistenceResponse;

abstract class AbstractPersistenceAdapter implements PersistenceAdapterInterface
{
    /**
     * Metode standar untuk melakukan kuotasi kolom berdasarkan karakter dari Capability.
     */
    public function quoteIdentifier(string $identifier): string
    {
        $char = $this->getCapabilities()->identifierQuoteChar;
        
        // Pencegahan sederhana untuk SQL Injection pada penamaan identifier (walau tidak umum)
        $cleanIdentifier = str_replace($char, '', $identifier);

        // Jika karakter kuotasi untuk SqlServer `[`, tutupnya harus `]`
        if ($char === '[') {
            return '[' . $cleanIdentifier . ']';
        }

        return $char . $cleanIdentifier . $char;
    }

    /**
     * Memproses permintaan (Insert, Update, Delete, Upsert) dengan membaginya ke dalam batch
     * untuk mencegah kelumpuhan memori pada dataset jutaan baris (Chunking).
     */
    public function execute(PersistenceRequest $request, PersistenceContext $context): PersistenceResponse
    {
        $records = $request->records;
        
        if (empty($records)) {
            return new PersistenceResponse(0, []);
        }

        // Terapkan batas maksimal batch sesuai kemampuan DBMS
        $maxBatchSize = $this->getCapabilities()->maxBatchSize;
        $chunks = array_chunk($records, $maxBatchSize);

        $totalAffectedRows = 0;
        $errors = [];

        foreach ($chunks as $chunk) {
            try {
                // Di dunia nyata, operasi ini dibungkus savepoint/transaction lokal.
                $affected = match (strtoupper($request->operation)) {
                    'INSERT' => $this->bulkInsert($chunk, $context),
                    'UPDATE' => $this->bulkUpdate($chunk, $context),
                    'UPSERT' => $this->bulkUpsert($chunk, $context),
                    'DELETE' => $this->bulkDelete($chunk, $context),
                    default  => 0,
                };
                
                $totalAffectedRows += $affected;

                // Bebaskan memori setiap putaran loop untuk performa enterprise (GC)
                unset($chunk);
                
            } catch (\Throwable $e) {
                // Bergantung pada strategi fail-fast atau best-effort
                $errors[] = $e->getMessage();
                // break; // Bisa di-break atau dilanjut
            }
        }

        return new PersistenceResponse($totalAffectedRows, $errors);
    }

    /**
     * @param array $records Daftar objek
     * @return int Jumlah baris terpengaruh
     */
    protected function bulkInsert(array $records, PersistenceContext $context): int
    {
        return count($records);
    }

    protected function bulkUpdate(array $records, PersistenceContext $context): int
    {
        return count($records);
    }

    protected function bulkUpsert(array $records, PersistenceContext $context): int
    {
        return count($records);
    }

    protected function bulkDelete(array $records, PersistenceContext $context): int
    {
        return count($records);
    }
}
