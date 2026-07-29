<?php

namespace App\Services\Mappers\Transformers;

use App\Services\Mappers\Contracts\FieldTransformerInterface;

class BooleanTransformer implements FieldTransformerInterface
{
    public function transform(mixed $value, array $options = []): ?bool
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
