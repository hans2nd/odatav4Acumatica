<?php

namespace App\Services\Coordinators\Contracts;

use App\Services\Coordinators\ExecutionContext;
use App\Services\Coordinators\ExecutionResult;
use App\Services\Readers\SourcePage;

interface IncrementalExecutionCoordinatorInterface
{
    /**
     * Mengorkestrasikan dan mengeksekusi satu siklus/halaman dari sinkronisasi inkremental.
     * 
     * @param ExecutionContext $context Detail dan konfigurasi eksekusi.
     * @param SourcePage|null $page Penanda paginasi (jika melanjutkan iterasi di halaman berikutnya).
     * @return ExecutionResult Hasil standar sinkronisasi yang ditarik.
     */
    public function execute(ExecutionContext $context, ?SourcePage $page = null): ExecutionResult;
}
