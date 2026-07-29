<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\ConflictResolutionContext;
use App\Services\Persistence\ConflictResolutionRequest;
use App\Services\Persistence\ConflictResolutionResult;

interface ConflictResolutionStrategyInterface
{
    /**
     * Nama identifikasi strategi (Sesuai dengan ConflictResolutionPolicy)
     */
    public function getName(): string;

    /**
     * Menjalankan evaluasi untuk mencari penyelesaian konflik.
     */
    public function resolve(ConflictResolutionRequest $request, ConflictResolutionContext $context): ConflictResolutionResult;
}
