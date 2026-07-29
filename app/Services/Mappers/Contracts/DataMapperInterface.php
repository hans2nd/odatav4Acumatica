<?php

namespace App\Services\Mappers\Contracts;

use App\Services\Mappers\MappingContext;
use App\Services\Mappers\MappingResult;

interface DataMapperInterface
{
    /**
     * Mentransformasi sekumpulan rekaman mentah menjadi struktur NormalizedRecordCollection.
     * 
     * @param array|\Traversable $sourceRecords Rekaman asli yang ditarik dari *Provider*.
     * @param MappingContext $context Definisi pemetaan untuk memandu proses normalisasi.
     * @return MappingResult
     */
    public function map(iterable $sourceRecords, MappingContext $context): MappingResult;
}
