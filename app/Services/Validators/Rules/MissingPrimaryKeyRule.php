<?php

namespace App\Services\Validators\Rules;

use App\Services\Validators\Contracts\ValidationRuleInterface;
use App\Services\Validators\ValidationContext;
use App\Services\Validators\ValidationIssue;
use App\Services\Validators\RecoveryRecommendation;

class MissingPrimaryKeyRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ?ValidationIssue
    {
        $records = $context->executionResult->records;
        if ($records->isEmpty()) {
            return null;
        }

        $pkField = $context->executionContext->primaryKeyField;
        if (empty($pkField)) {
            return null; // Skip jika tidak ada konfigurasi PK
        }

        foreach ($records as $record) {
            $recordArray = (array) $record;
            if (!isset($recordArray[$pkField]) || $recordArray[$pkField] === null || $recordArray[$pkField] === '') {
                return new ValidationIssue(
                    'MissingPrimaryKeyRule',
                    "Ditemukan setidaknya satu baris data (record) tanpa nilai pada kolom Primary Key '{$pkField}'.",
                    'Data Integrity Error',
                    new RecoveryRecommendation(
                        'ABORT_SYNC',
                        'Terdapat rekaman yatim tanpa penanda identitas unik. Sinkronisasi harus dihentikan untuk mencegah kegagalan Upsert (operasi simpan).'
                    )
                );
            }
        }

        return null; // Lolos
    }
}
