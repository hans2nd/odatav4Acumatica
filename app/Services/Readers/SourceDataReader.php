<?php

namespace App\Services\Readers;

use App\Services\Queries\QueryDefinition;
use App\Services\Readers\Contracts\SourceDataReaderInterface;
use App\Services\Readers\Contracts\SourceProviderInterface;
use App\Services\Readers\Exceptions\SourceReaderException;

class SourceDataReader implements SourceDataReaderInterface
{
    private ?SourceProviderInterface $provider = null;

    public function setProvider(SourceProviderInterface $provider): void
    {
        $this->provider = $provider;
    }

    public function execute(QueryDefinition $query, ReaderContext $context, ?SourcePage $page = null): SourceResult
    {
        if ($this->provider === null) {
            throw new SourceReaderException("Source provider has not been set.");
        }

        try {
            return $this->provider->read($query, $context, $page);
        } catch (SourceReaderException $e) {
            // Rethrow explicitly if it's already our domain exception
            throw $e;
        } catch (\Throwable $e) {
            // Wrap any unexpected infrastructure exceptions into our domain exception
            throw new SourceReaderException(
                "An unexpected error occurred while reading from the source: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
