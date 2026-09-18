<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\UpsertContext;
use App\Services\Persistence\UpsertRequest;
use App\Services\Persistence\UpsertResult;

interface UpsertEngineInterface
{
    /**
     * Memulai proses Upsert agnostik yang nanti akan diteruskan ke Adapter.
     */
    public function upsert(UpsertRequest $request, UpsertContext $context): UpsertResult;
}
