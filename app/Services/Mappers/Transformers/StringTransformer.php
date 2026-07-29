<?php

namespace App\Services\Mappers\Transformers;

use App\Services\Mappers\Contracts\FieldTransformerInterface;

class StringTransformer implements FieldTransformerInterface
{
    public function transform(mixed $value, array $options = []): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = (string) $value;

        if ($options['trim'] ?? true) {
            $str = trim($str);
        }

        $case = $options['case'] ?? null;
        if ($case === 'lower') {
            $str = strtolower($str);
        } elseif ($case === 'upper') {
            $str = strtoupper($str);
        }

        return $str;
    }
}
