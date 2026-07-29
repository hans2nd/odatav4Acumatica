<?php

namespace App\Services\Mappers\Transformers;

use App\Services\Mappers\Contracts\FieldTransformerInterface;
use Carbon\Carbon;

class DateTransformer implements FieldTransformerInterface
{
    public function transform(mixed $value, array $options = []): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            $format = $options['format'] ?? null;
            if ($format) {
                return Carbon::createFromFormat($format, $value)->toDateTimeString();
            }

            return Carbon::parse($value)->toDateTimeString();
        } catch (\Exception $e) {
            // Bisa dilempar sebagai error, atau dikembalikan null (tergantung strict_mode)
            $strict = $options['strict'] ?? false;
            if ($strict) {
                throw new \InvalidArgumentException("Format tanggal tidak valid: {$value}");
            }
            return null;
        }
    }
}
