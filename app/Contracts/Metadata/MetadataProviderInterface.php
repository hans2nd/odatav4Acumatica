<?php

namespace App\Contracts\Metadata;

use App\DTO\Metadata\MetadataEntity;

interface MetadataProviderInterface
{
    /**
     * Connects to the external system and extracts raw metadata.
     */
    public function fetchMetadata(string $entityKey): MetadataEntity;
}
