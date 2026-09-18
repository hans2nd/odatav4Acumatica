<?php

namespace App\Services\BatchPlanning\Contracts;

interface ProcessingContextInterface
{
    /**
     * Mendapatkan entitas target operasi.
     */
    public function getEntityName(): string;

    /**
     * Mendapatkan daftar ChangeDetectionResult untuk diproses.
     * @return array<\App\Services\ChangeDetection\ChangeDetectionResult>
     */
    public function getChangeResults(): array;

    /**
     * Mendapatkan parameter profil batas eksekusi.
     */
    public function getExecutionProfile(): \App\Services\BatchPlanning\ExecutionProfile;
}
