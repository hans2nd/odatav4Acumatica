<?php

namespace App\Services;

use App\Models\SyncState;
use App\Repositories\Contracts\SyncStateRepositoryInterface;
use App\Services\Contracts\SyncStateServiceInterface;
use App\Exceptions\SyncState\SyncStateNotFoundException;
use App\Exceptions\SyncState\InvalidSyncStateException;
use App\Exceptions\SyncState\SyncStateLockException;
use Carbon\Carbon;

class SyncStateService implements SyncStateServiceInterface
{
    private SyncStateRepositoryInterface $repository;

    public function __construct(SyncStateRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function initializeSyncState(int $scheduleId, string $entityName): SyncState
    {
        return $this->repository->initialize($scheduleId, $entityName);
    }

    public function loadSyncState(int $scheduleId): SyncState
    {
        $state = $this->repository->findBySchedule($scheduleId);
        if (!$state) {
            throw new SyncStateNotFoundException("Sync state for schedule ID {$scheduleId} not found.");
        }
        return $state;
    }

    public function validateSyncState(int $scheduleId, array $expectedStatuses): bool
    {
        $state = $this->loadSyncState($scheduleId);
        if (!in_array($state->status, $expectedStatuses)) {
            throw new InvalidSyncStateException(
                "Invalid state transition. Current status is '{$state->status}', expected one of: " . implode(', ', $expectedStatuses)
            );
        }
        return true;
    }

    public function startSynchronization(int $scheduleId): bool
    {
        $this->validateSyncState($scheduleId, ['Idle', 'Completed', 'Failed', 'Paused', 'Running']);
        return $this->repository->markStarted($scheduleId);
    }

    public function completeSynchronization(int $scheduleId): bool
    {
        $this->validateSyncState($scheduleId, ['Running']);
        return $this->repository->markCompleted($scheduleId);
    }

    public function failSynchronization(int $scheduleId): bool
    {
        $this->validateSyncState($scheduleId, ['Running', 'Paused']);
        return $this->repository->markFailed($scheduleId);
    }

    public function pauseSynchronization(int $scheduleId): bool
    {
        $this->validateSyncState($scheduleId, ['Running']);
        return $this->repository->updateStatus($scheduleId, 'Paused');
    }

    public function resumeSynchronization(int $scheduleId): bool
    {
        $this->validateSyncState($scheduleId, ['Paused']);
        return $this->repository->markStarted($scheduleId);
    }

    public function updateWatermark(int $scheduleId, ?Carbon $timestamp, ?string $primaryKey): bool
    {
        $this->validateSyncState($scheduleId, ['Running']);
        return $this->repository->updateWatermark($scheduleId, $timestamp, $primaryKey);
    }

    public function updateStatistics(int $scheduleId, int $rowsRead, int $rowsInserted, int $rowsUpdated, int $rowsFailed): bool
    {
        $this->validateSyncState($scheduleId, ['Running']);
        return $this->repository->updateStatistics($scheduleId, $rowsRead, $rowsInserted, $rowsUpdated, $rowsFailed);
    }

    public function incrementRetry(int $scheduleId): int
    {
        return $this->repository->incrementRetry($scheduleId);
    }

    public function resetRetry(int $scheduleId): bool
    {
        return $this->repository->resetRetry($scheduleId);
    }

    public function acquireSynchronizationLock(int $scheduleId, string $owner, int $ttlMinutes = 5): bool
    {
        $acquired = $this->repository->acquireLock($scheduleId, $owner, $ttlMinutes);
        if (!$acquired) {
            $state = $this->loadSyncState($scheduleId);
            throw new SyncStateLockException("Lock for schedule ID {$scheduleId} is already held by {$state->lock_owner} (active lease).");
        }
        return true;
    }

    public function heartbeatSynchronizationLock(int $scheduleId, string $owner, int $ttlMinutes = 5): bool
    {
        return $this->repository->heartbeatLock($scheduleId, $owner, $ttlMinutes);
    }

    public function releaseSynchronizationLock(int $scheduleId, string $owner): bool
    {
        $state = $this->loadSyncState($scheduleId);
        
        if ($state->lock_owner && $state->lock_owner !== $owner) {
            throw new SyncStateLockException("Cannot release lock for schedule ID {$scheduleId}. Owned by {$state->lock_owner}, but {$owner} attempted release.");
        }

        return $this->repository->releaseLock($scheduleId, $owner);
    }

    public function checkSynchronizationStatus(int $scheduleId): string
    {
        return $this->loadSyncState($scheduleId)->status;
    }

    public function resetSynchronizationState(int $scheduleId): bool
    {
        $state = $this->repository->findBySchedule($scheduleId);
        if ($state && $state->lock_owner) {
            $this->repository->releaseLock($scheduleId, $state->lock_owner);
        }

        return $this->repository->updateStatus($scheduleId, 'Idle') &&
               $this->repository->updateWatermark($scheduleId, null, null) &&
               $this->repository->resetRetry($scheduleId);
    }
}
