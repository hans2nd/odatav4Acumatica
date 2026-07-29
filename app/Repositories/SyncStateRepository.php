<?php

namespace App\Repositories;

use App\Models\SyncState;
use App\Repositories\Contracts\SyncStateRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class SyncStateRepository implements SyncStateRepositoryInterface
{
    /**
     * Find a SyncState by its parent schedule ID.
     */
    public function findBySchedule(int $scheduleId): ?SyncState
    {
        return SyncState::where('sync_schedule_id', $scheduleId)->first();
    }

    /**
     * Find a SyncState by its entity name.
     */
    public function findByEntity(string $entityName): ?SyncState
    {
        return SyncState::where('entity_name', $entityName)->first();
    }

    /**
     * Create a new SyncState record.
     */
    public function create(array $data): SyncState
    {
        if (!isset($data['sync_schedule_id']) || !isset($data['entity_name'])) {
            throw new InvalidArgumentException("sync_schedule_id and entity_name are required attributes.");
        }
        return SyncState::create($data);
    }

    /**
     * Initialize a new SyncState idempotently if it does not exist.
     */
    public function initialize(int $scheduleId, string $entityName): SyncState
    {
        return SyncState::firstOrCreate(
            ['sync_schedule_id' => $scheduleId],
            [
                'entity_name' => $entityName,
                'status' => 'Idle',
                'total_runs' => 0,
            ]
        );
    }

    /**
     * Check if a SyncState exists for a given schedule ID.
     */
    public function exists(int $scheduleId): bool
    {
        return SyncState::where('sync_schedule_id', $scheduleId)->exists();
    }

    /**
     * Get the current composite watermark (timestamp and primary key).
     */
    public function getCurrentWatermark(int $scheduleId): ?array
    {
        $state = $this->findBySchedule($scheduleId);
        if (!$state) {
            return null;
        }

        return [
            'timestamp' => $state->watermark_timestamp,
            'primary_key' => $state->watermark_primary_key,
        ];
    }

    /**
     * Update the composite watermark for a given schedule ID.
     */
    public function updateWatermark(int $scheduleId, ?Carbon $timestamp, ?string $primaryKey): bool
    {
        return (bool) SyncState::where('sync_schedule_id', $scheduleId)
            ->update([
                'watermark_timestamp' => $timestamp,
                'watermark_primary_key' => $primaryKey,
            ]);
    }

    /**
     * Atomically update the operational statistics counters.
     */
    public function updateStatistics(int $scheduleId, int $rowsRead, int $rowsInserted, int $rowsUpdated, int $rowsFailed): bool
    {
        $state = $this->findBySchedule($scheduleId);
        if (!$state) {
            throw new ModelNotFoundException("SyncState not found for schedule ID: {$scheduleId}");
        }

        $state->total_rows_read += $rowsRead;
        $state->total_rows_inserted += $rowsInserted;
        $state->total_rows_updated += $rowsUpdated;
        $state->total_rows_failed += $rowsFailed;
        
        return $state->save();
    }

    /**
     * Update the execution status explicitly.
     */
    public function updateStatus(int $scheduleId, string $status): bool
    {
        $validStatuses = ['Idle', 'Running', 'Completed', 'Failed', 'Paused'];
        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException("Invalid status provided: {$status}");
        }

        return (bool) SyncState::where('sync_schedule_id', $scheduleId)->update(['status' => $status]);
    }

    /**
     * Mark the synchronization job as started.
     */
    public function markStarted(int $scheduleId): bool
    {
        return (bool) SyncState::where('sync_schedule_id', $scheduleId)
            ->update([
                'status' => 'Running',
                'last_started_at' => Carbon::now(),
                'total_runs' => \Illuminate\Support\Facades\DB::raw('total_runs + 1'),
            ]);
    }

    /**
     * Mark the synchronization job as completed successfully.
     */
    public function markCompleted(int $scheduleId): bool
    {
        return (bool) SyncState::where('sync_schedule_id', $scheduleId)
            ->update([
                'status' => 'Completed',
                'last_finished_at' => Carbon::now(),
                'retry_count' => 0,
            ]);
    }

    /**
     * Mark the synchronization job as failed.
     */
    public function markFailed(int $scheduleId): bool
    {
        return (bool) SyncState::where('sync_schedule_id', $scheduleId)
            ->update([
                'status' => 'Failed',
                'last_finished_at' => Carbon::now(),
            ]);
    }

    /**
     * Increment the retry counter for the current sync state.
     */
    public function incrementRetry(int $scheduleId): int
    {
        $state = $this->findBySchedule($scheduleId);
        if (!$state) {
            throw new ModelNotFoundException("SyncState not found for schedule ID: {$scheduleId}");
        }

        $state->retry_count += 1;
        $state->save();

        return $state->retry_count;
    }

    /**
     * Reset the retry counter to zero.
     */
    public function resetRetry(int $scheduleId): bool
    {
        return (bool) SyncState::where('sync_schedule_id', $scheduleId)->update(['retry_count' => 0]);
    }

    /**
     * Atomically acquire a distributed lock in the database.
     */
    public function acquireLock(int $scheduleId, string $owner): bool
    {
        $updated = SyncState::where('sync_schedule_id', $scheduleId)
            ->whereNull('lock_owner')
            ->update([
                'lock_owner' => $owner,
                'lock_acquired_at' => Carbon::now(),
            ]);

        return $updated > 0;
    }

    /**
     * Release a previously acquired distributed lock.
     */
    public function releaseLock(int $scheduleId, string $owner): bool
    {
        $updated = SyncState::where('sync_schedule_id', $scheduleId)
            ->where('lock_owner', $owner)
            ->update([
                'lock_owner' => null,
                'lock_acquired_at' => null,
            ]);

        return $updated > 0;
    }

    /**
     * Check if the synchronization state is currently locked by any worker.
     */
    public function isLocked(int $scheduleId): bool
    {
        return SyncState::where('sync_schedule_id', $scheduleId)
            ->whereNotNull('lock_owner')
            ->exists();
    }

    /**
     * Arbitrarily update execution metadata.
     */
    public function updateExecutionInformation(int $scheduleId, array $data): bool
    {
        return (bool) SyncState::where('sync_schedule_id', $scheduleId)->update($data);
    }

    /**
     * Delete the SyncState record.
     */
    public function delete(int $scheduleId): bool
    {
        return (bool) SyncState::where('sync_schedule_id', $scheduleId)->delete();
    }
}
