<?php

namespace App\Services\Persistence\Resolvers;

use App\Services\Persistence\Contracts\UpdateFieldResolverInterface;
use App\Services\Persistence\UpsertContext;

class UpdateFieldResolver implements UpdateFieldResolverInterface
{
    public function resolve(array $records, array $conflictKeys, UpsertContext $context): array
    {
        if (empty($records)) {
            return [];
        }

        // Ambil struktur (keys) dari baris pertama. 
        // Idealnya jika pakai NormalizedRecord, di-extract dari atributnya.
        $firstRecord = is_object($records[0]) && method_exists($records[0], 'toArray') 
            ? $records[0]->toArray() 
            : (array) $records[0];

        $allKeys = array_keys($firstRecord);

        $updateFields = [];
        
        foreach ($allKeys as $key) {
            // 1. Abaikan Primary Keys (Tidak boleh ditimpa)
            if (in_array($key, $conflictKeys, true)) {
                continue;
            }

            // 2. Abaikan Ignored Fields (Misal: kolom password, log_creator)
            if (in_array($key, $context->ignoredFields, true)) {
                continue;
            }

            $updateFields[] = $key;
        }

        // 3. Suntikkan (Inject) Auto Timestamp jika ada (Misal: updated_at)
        foreach ($context->autoTimestampFields as $tsField) {
            if (!in_array($tsField, $updateFields, true)) {
                $updateFields[] = $tsField;
            }
        }

        return $updateFields;
    }
}
