<?php

namespace App\Services\Orchestration\Contracts;

use App\Services\Orchestration\SynchronizationExecutionRequest;
use App\Services\Orchestration\SynchronizationExecutionResult;

interface SynchronizationOrchestratorInterface
{
    /**
     * Mendaftarkan pendengar peristiwa/siklus (lifecycle listener)
     */
    public function addLifecycle(SynchronizationLifecycleEventsInterface $lifecycle): void;

    /**
     * Pintu gerbang utama eksekusi sinkronisasi.
     */
    public function execute(SynchronizationExecutionRequest $request): SynchronizationExecutionResult;
}
