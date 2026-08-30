<?php

namespace App\DTO\Metadata;

class MetadataField
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $dataType = null,
        public readonly bool $isPrimaryKey = false,
        public readonly bool $isNullable = true,
        public readonly ?int $maxLength = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly ?string $relationshipType = null,
        public readonly ?string $relatedEntity = null,
        public readonly ?string $semanticType = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'dataType' => $this->dataType,
            'isPrimaryKey' => $this->isPrimaryKey,
            'isNullable' => $this->isNullable,
            'maxLength' => $this->maxLength,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'relationshipType' => $this->relationshipType,
            'relatedEntity' => $this->relatedEntity,
            'semanticType' => $this->semanticType,
        ];
    }
}
