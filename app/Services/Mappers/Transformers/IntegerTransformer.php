<?php

namespace App\Services\Mappers\Transformers;

use App\Services\Mappers\Contracts\FieldTransformerInterface;

class IntegerTransformer implements FieldTransformerInterface
{
    public function transform(mixed $value, array $options = []): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
