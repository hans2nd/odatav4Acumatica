<?php

namespace App\Services\SchemaValidators;

class ValidationProfile
{
    /**
     * @param array<string, array<string, array|mixed>> $fieldRules
     * Format:
     * [
     *    'customer_id' => [
     *        'required' => [],
     *        'string_length' => ['max' => 50]
     *    ]
     * ]
     */
    public function __construct(
        public readonly array $fieldRules = []
    ) {}
}
