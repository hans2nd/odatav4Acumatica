<?php

namespace App\Services\SchemaValidators\Rules;

use App\Services\SchemaValidators\Contracts\ValidationRuleInterface;

class NumericTypeRule implements ValidationRuleInterface
{
    public function validate(string $field, mixed $value, array $options = []): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return "Bidang '{$field}' wajib berformat angka numerik, namun nilai '{$value}' ditemukan.";
        }

        return null;
    }
}
