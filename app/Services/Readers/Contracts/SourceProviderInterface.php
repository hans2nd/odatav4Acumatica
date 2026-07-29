<?php

namespace App\Services\Readers\Contracts;

use App\Services\Queries\QueryDefinition;
use App\Services\Readers\ReaderContext;
use App\Services\Readers\SourcePage;
use App\Services\Readers\SourceResult;

interface SourceProviderInterface
{
    /**
     * Mengeksekusi QueryDefinition ke sumber data eksternal.
     * 
     * @param QueryDefinition $query Definisi *query* independen dari IncrementalQueryBuilder.
     * @param ReaderContext $context Konteks eksekusi (timeout, ukuran halaman).
     * @param SourcePage|null $page Penanda navigasi halaman (*cursor/offset*).
     * @return SourceResult Hasil yang telah dinormalisasi.
     * 
     * @throws \App\Services\Readers\Exceptions\SourceReaderException
     */
    public function read(QueryDefinition $query, ReaderContext $context, ?SourcePage $page = null): SourceResult;
}
