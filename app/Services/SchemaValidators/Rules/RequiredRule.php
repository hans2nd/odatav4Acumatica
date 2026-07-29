<?php

namespace App\Services\SchemaValidators\Rules;

use App\Services\SchemaValidators\Contracts\ValidationRuleInterface;

class RequiredRule implements ValidationRuleInterface
{
    public function validate(string $field, mixed $value, array $options = []): ?string
    {
        if ($value === null || $value === '') {
            return "Nilai untuk bidang '{$field}' diwajibkan, namun kosong atau null ditemukan.";
        }

        return null; // Valid
    }
}
