<?php

namespace App\Services\Contracts;

use App\Models\SyncState;
use Carbon\Carbon;

interface SyncStateServiceInterface
{
    public function initializeSyncState(int $scheduleId, string $entityName): SyncState;
    public function loadSyncState(int $scheduleId): SyncState;
    public function validateSyncState(int $scheduleId, array $expectedStatuses): bool;
    
    public function startSynchronization(int $scheduleId): bool;
    public function completeSynchronization(int $scheduleId): bool;
    public function failSynchronization(int $scheduleId): bool;
    public function pauseSynchronization(int $scheduleId): bool;
    public function resumeSynchronization(int $scheduleId): bool;
    
    public function updateWatermark(int $scheduleId, ?Carbon $timestamp, ?string $primaryKey): bool;
    public function updateStatistics(int $scheduleId, int $rowsRead, int $rowsInserted, int $rowsUpdated, int $rowsFailed): bool;
    
    public function incrementRetry(int $scheduleId): int;
    public function resetRetry(int $scheduleId): bool;
    
    public function acquireSynchronizationLock(int $scheduleId, string $owner): bool;
    public function releaseSynchronizationLock(int $scheduleId, string $owner): bool;
    public function checkSynchronizationStatus(int $scheduleId): string;
    
    public function resetSynchronizationState(int $scheduleId): bool;
}
