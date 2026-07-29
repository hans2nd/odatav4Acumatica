<?php

namespace App\DTO\Metadata;

class MetadataResult
{
    public function __construct(
        public readonly string $entityKey,
        public readonly array $fields,
        public readonly string $version,
        public readonly string $hash,
        public readonly string $source,
        public readonly bool $isSuccess = true,
        public readonly ?string $errorMessage = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'entity' => $this->entityKey,
            'fields' => $this->fields,
            'version' => $this->version,
            'hash' => $this->hash,
            'source' => $this->source,
            'success' => $this->isSuccess,
            'error' => $this->errorMessage,
        ];
    }
}
