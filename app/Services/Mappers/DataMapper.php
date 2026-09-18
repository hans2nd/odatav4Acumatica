<?php

namespace App\Services\Mappers;

use App\Services\Mappers\Contracts\DataMapperInterface;
use App\Services\Mappers\Transformers\TransformerRegistry;

class DataMapper implements DataMapperInterface
{
    public function __construct(
        private readonly TransformerRegistry $registry
    ) {}

    public function map(iterable $sourceRecords, MappingContext $context): MappingResult
    {
        $normalizedRecords = [];

        foreach ($sourceRecords as $sourceRecord) {
            $sourceArray = (array) $sourceRecord; // Memudahkan akses bersarang/sederhana
            $mappedData = [];

            foreach ($context->definitions as $definition) {
                // 1. Ekstraksi Nilai
                $rawValue = $this->extractValue($sourceArray, $definition->sourceField);
                
                // 2. Transformasi
                $finalValue = $rawValue;
                if ($definition->transformerName && $this->registry->has($definition->transformerName)) {
                    $transformer = $this->registry->get($definition->transformerName);
                    $finalValue = $transformer->transform($rawValue, $definition->transformerOptions);
                }

                // 3. Penanganan Default Value
                if ($finalValue === null && $definition->defaultValue !== null) {
                    $finalValue = $definition->defaultValue;
                }

                // 4. Injeksi ke Target
                $this->injectValue($mappedData, $definition->targetField, $finalValue);
            }

            $normalizedRecords[] = new NormalizedRecord($mappedData);
        }

        return new MappingResult(new NormalizedRecordCollection($normalizedRecords));
    }

    private function extractValue(array $data, string $field): mixed
    {
        // Mendukung dot-notation untuk objek bersarang (nested) di masa depan
        if (str_contains($field, '.')) {
            $keys = explode('.', $field);
            $current = $data;
            foreach ($keys as $key) {
                if (!is_array($current) || !array_key_exists($key, $current)) {
                    return null;
                }
                $current = $current[$key];
            }
            return $current;
        }

        return $data[$field] ?? null;
    }

    private function injectValue(array &$data, string $field, mixed $value): void
    {
        // Mendukung injeksi dot-notation
        if (str_contains($field, '.')) {
            $keys = explode('.', $field);
            $current = &$data;
            foreach ($keys as $key) {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
            $current = $value;
            return;
        }

        $data[$field] = $value;
    }
}
