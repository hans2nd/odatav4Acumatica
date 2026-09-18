<?php

namespace App\Services\Readers\Contracts;

use App\Services\Queries\QueryDefinition;
use App\Services\Readers\ReaderContext;
use App\Services\Readers\SourcePage;
use App\Services\Readers\SourceResult;

interface SourceDataReaderInterface
{
    /**
     * Menyuntikkan penyedia sumber data (*provider*) yang spesifik.
     */
    public function setProvider(SourceProviderInterface $provider): void;

    /**
     * Membaca kumpulan data dari penyedia menggunakan *query* dan konteks yang diberikan.
     * 
     * @throws \App\Services\Readers\Exceptions\SourceReaderException
     */
    public function execute(QueryDefinition $query, ReaderContext $context, ?SourcePage $page = null): SourceResult;
}
