<?php

namespace App\DTO\Metadata;

class MetadataEntity
{
    /**
     * @param string $entityKey
     * @param MetadataField[] $fields
     * @param string|null $providerVersion
     */
    public function __construct(
        public readonly string $entityKey,
        public readonly array $fields,
        public readonly ?string $providerVersion = null
    ) {
    }

    public function getHash(): string
    {
        $fieldData = array_map(fn(MetadataField $field) => $field->toArray(), $this->fields);
        // Sort to ensure consistent hashing
        usort($fieldData, fn($a, $b) => strcmp($a['name'], $b['name']));
        
        return hash('sha256', json_encode([
            'entity' => $this->entityKey,
            'fields' => $fieldData
        ]));
    }
}
