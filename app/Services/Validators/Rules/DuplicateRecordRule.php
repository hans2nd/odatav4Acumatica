<?php

namespace App\Services\Validators\Rules;

use App\Services\Validators\Contracts\ValidationRuleInterface;
use App\Services\Validators\ValidationContext;
use App\Services\Validators\ValidationIssue;
use App\Services\Validators\RecoveryRecommendation;

class DuplicateRecordRule implements ValidationRuleInterface
{
    public function validate(ValidationContext $context): ?ValidationIssue
    {
        $records = $context->executionResult->records;
        if ($records->isEmpty()) {
            return null;
        }

        $pkField = $context->executionContext->primaryKeyField;
        if (empty($pkField)) {
            return null; // Skip jika entitas tidak mendukung PK
        }

        $seenPks = [];
        foreach ($records as $record) {
            $recordArray = (array) $record;
            $pk = $recordArray[$pkField] ?? null;
            
            if ($pk !== null) {
                if (isset($seenPks[$pk])) {
                    return new ValidationIssue(
                        'DuplicateRecordRule',
                        "Terdeteksi rekaman duplikat pada halaman yang sama dengan Primary Key: {$pk}",
                        'Data Integrity Error',
                        new RecoveryRecommendation(
                            'FORCE_PAGE_RETRY_OR_FULL_REFRESH',
                            'Coba tarik ulang halaman ini. Jika masih ada duplikasi, sumber data (Provider) melanggar determinisme; pertimbangkan untuk melakukan sinkronisasi penuh (Full Refresh).'
                        )
                    );
                }
                $seenPks[$pk] = true;
            }
        }

        return null; // Lolos
    }
}
