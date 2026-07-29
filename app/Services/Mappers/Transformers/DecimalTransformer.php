<?php

namespace App\Services\Mappers\Transformers;

use App\Services\Mappers\Contracts\FieldTransformerInterface;

class DecimalTransformer implements FieldTransformerInterface
{
    public function transform(mixed $value, array $options = []): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $floatVal = (float) $value;
        
        $precision = $options['precision'] ?? null;
        if ($precision !== null) {
            return round($floatVal, $precision);
        }

        return $floatVal;
    }
}
