<?php

namespace App\Services\SchemaValidators\Rules;

use App\Services\SchemaValidators\Contracts\ValidationRuleInterface;

class StringLengthRule implements ValidationRuleInterface
{
    public function validate(string $field, mixed $value, array $options = []): ?string
    {
        if ($value === null || $value === '') {
            return null; // Ignore if empty. Use RequiredRule for mandatory check.
        }

        $max = $options['max'] ?? null;
        if ($max === null) {
            return null;
        }

        if (!is_string($value)) {
            $value = (string) $value;
        }

        if (mb_strlen($value) > $max) {
            return "Panjang teks untuk bidang '{$field}' melampaui batas {$max} karakter.";
        }

        return null;
    }
}
