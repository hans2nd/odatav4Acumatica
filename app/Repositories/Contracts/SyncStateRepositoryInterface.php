<?php

namespace App\Repositories\Contracts;

use App\Models\SyncState;
use Carbon\Carbon;

interface SyncStateRepositoryInterface
{
    /**
     * Find a SyncState by its parent schedule ID.
     */
    public function findBySchedule(int $scheduleId): ?SyncState;

    /**
     * Find a SyncState by its entity name.
     */
    public function findByEntity(string $entityName): ?SyncState;

    /**
     * Create a new SyncState record.
     */
    public function create(array $data): SyncState;

    /**
     * Initialize a new SyncState idempotently if it does not exist.
     */
    public function initialize(int $scheduleId, string $entityName): SyncState;

    /**
     * Check if a SyncState exists for a given schedule ID.
     */
    public function exists(int $scheduleId): bool;

    /**
     * Get the current composite watermark (timestamp and primary key).
     */
    public function getCurrentWatermark(int $scheduleId): ?array;

    /**
     * Update the composite watermark for a given schedule ID.
     */
    public function updateWatermark(int $scheduleId, ?Carbon $timestamp, ?string $primaryKey): bool;

    /**
     * Atomically update the operational statistics counters.
     */
    public function updateStatistics(int $scheduleId, int $rowsRead, int $rowsInserted, int $rowsUpdated, int $rowsFailed): bool;

    /**
     * Update the execution status explicitly.
     */
    public function updateStatus(int $scheduleId, string $status): bool;

    /**
     * Mark the synchronization job as started.
     */
    public function markStarted(int $scheduleId): bool;

    /**
     * Mark the synchronization job as completed successfully.
     */
    public function markCompleted(int $scheduleId): bool;

    /**
     * Mark the synchronization job as failed.
     */
    public function markFailed(int $scheduleId): bool;

    /**
     * Increment the retry counter for the current sync state.
     */
    public function incrementRetry(int $scheduleId): int;

    /**
     * Reset the retry counter to zero.
     */
    public function resetRetry(int $scheduleId): bool;

    /**
     * Atomically acquire a distributed lock in the database.
     */
    public function acquireLock(int $scheduleId, string $owner): bool;

    /**
     * Release a previously acquired distributed lock.
     */
    public function releaseLock(int $scheduleId, string $owner): bool;

    /**
     * Check if the synchronization state is currently locked by any worker.
     */
    public function isLocked(int $scheduleId): bool;

    /**
     * Arbitrarily update execution metadata (e.g., last_started_at).
     */
    public function updateExecutionInformation(int $scheduleId, array $data): bool;

    /**
     * Delete the SyncState record.
     */
    public function delete(int $scheduleId): bool;
}
