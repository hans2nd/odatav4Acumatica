<?php

namespace App\Http\Controllers;

use App\Services\EntityConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntityController extends Controller
{
    protected EntityConfigService $entityConfig;

    public function __construct(EntityConfigService $entityConfig)
    {
        $this->entityConfig = $entityConfig;
    }

    /**
     * Get all entities as JSON.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'entities' => $this->entityConfig->getAll(),
        ]);
    }

    /**
     * Get a single entity by key.
     */
    public function show(string $key): JsonResponse
    {
        $entity = $this->entityConfig->get($key);
        if (!$entity) {
            return response()->json(['success' => false, 'message' => "Entity '{$key}' not found."], 404);
        }

        return response()->json([
            'success' => true,
            'key'     => $key,
            'entity'  => $entity,
        ]);
    }

    /**
     * Create a new entity.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key'                    => 'required|string|max:100|regex:/^[A-Za-z][A-Za-z0-9]*$/',
            'odata_entity'           => 'required|string|max:255',
            'table'                  => 'required|string|max:255|regex:/^[a-z][a-z0-9_]*$/',
            'type'                   => 'required|in:master,transaction',
            'icon'                   => 'nullable|string|max:100',
            'description'            => 'required|string|max:255',
            'source_connection'      => 'nullable|string|max:100',
            'destination_connection' => 'nullable|string|max:100',
        ]);

        $key = $validated['key'];

        if ($this->entityConfig->exists($key)) {
            return response()->json([
                'success' => false,
                'message' => "Entity key '{$key}' already exists.",
            ], 422);
        }

        $this->entityConfig->save($key, $validated);

        \App\Jobs\DiscoverEntityMetadataJob::dispatch($key);

        return response()->json([
            'success' => true,
            'message' => "Entity '{$key}' created successfully.",
            'entity'  => $this->entityConfig->get($key),
        ]);
    }

    /**
     * Update an existing entity.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        if (!$this->entityConfig->exists($key)) {
            return response()->json([
                'success' => false,
                'message' => "Entity '{$key}' not found.",
            ], 404);
        }

        $validated = $request->validate([
            'key'                    => 'required|string|max:100|regex:/^[A-Za-z][A-Za-z0-9]*$/',
            'odata_entity'           => 'required|string|max:255',
            'table'                  => 'required|string|max:255|regex:/^[a-z][a-z0-9_]*$/',
            'type'                   => 'required|in:master,transaction',
            'icon'                   => 'nullable|string|max:100',
            'description'            => 'required|string|max:255',
            'source_connection'      => 'nullable|string|max:100',
            'destination_connection' => 'nullable|string|max:100',
            'schedule_minutes'       => 'nullable|integer|min:1',
        ]);

        $newKey = $validated['key'];

        // Check if new key conflicts with another entity
        if ($newKey !== $key && $this->entityConfig->exists($newKey)) {
            return response()->json([
                'success' => false,
                'message' => "Entity key '{$newKey}' already exists.",
            ], 422);
        }

        $this->entityConfig->update($key, $newKey, $validated);

        \App\Jobs\DiscoverEntityMetadataJob::dispatch($newKey);

        return response()->json([
            'success' => true,
            'message' => "Entity '{$newKey}' updated successfully.",
            'entity'  => $this->entityConfig->get($newKey),
        ]);
    }

    /**
     * Update an entity's schedule.
     */
    public function updateSchedule(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'frequency'    => 'nullable|in:minute,hour,daily,weekly,monthly',
            'interval'     => 'nullable|integer|min:1',
            'time'         => 'nullable|date_format:H:i',
            'day_of_week'  => 'nullable|integer|between:1,7',
            'day_of_month' => 'nullable|integer|between:1,31',
        ]);

        if (!$this->entityConfig->exists($key)) {
            return response()->json([
                'success' => false,
                'message' => "Entity '{$key}' not found.",
            ], 404);
        }

        $entity = $this->entityConfig->get($key);

        $isActive = !empty($validated['frequency']);
        
        $cronExpression = null;
        if ($isActive) {
            $freq = $validated['frequency'];
            $interval = (int) ($validated['interval'] ?? 1);
            
            if ($freq === 'minute') {
                $cronExpression = $interval > 1 ? "*/{$interval} * * * *" : "* * * * *";
            } elseif ($freq === 'hour') {
                $cronExpression = $interval > 1 ? "0 */{$interval} * * *" : "0 * * * *";
            } elseif (!empty($validated['time'])) {
                $timeParts = explode(':', $validated['time']);
                $hour = (int)$timeParts[0];
                $minute = (int)$timeParts[1];
                
                if ($freq === 'daily') {
                    $cronExpression = "{$minute} {$hour} * * *";
                } elseif ($freq === 'weekly') {
                    $dayOfWeek = (int) ($validated['day_of_week'] ?? 1);
                    $cronExpression = "{$minute} {$hour} * * {$dayOfWeek}";
                } elseif ($freq === 'monthly') {
                    $dayOfMonth = (int) ($validated['day_of_month'] ?? 1);
                    $cronExpression = "{$minute} {$hour} {$dayOfMonth} * *";
                }
            } else {
                $cronExpression = "0 0 * * *";
            }
        }

        // Update or Create the DB record
        $schedule = \App\Models\SyncSchedule::updateOrCreate(
            ['entity_key' => $key],
            [
                'cron_expression' => $cronExpression ?? '* * * * *',
                'is_active' => $isActive,
            ]
        );

        $message = "Schedule for '{$key}' updated successfully.";

        // Log the schedule change
        \App\Models\SyncLog::create([
            'entity_key' => $key,
            'entity_description' => $entity['description'] ?? $key,
            'odata_entity' => $entity['odata_entity'],
            'table_name' => $entity['table'],
            'type' => 'system',
            'source' => 'manual',
            'status' => 'success',
            'records_fetched' => 0,
            'records_inserted' => 0,
            'duration_seconds' => 0,
            'error_message' => $isActive ? "Schedule Activated (" . ($validated['frequency'] ?? 'unknown') . " at " . ($validated['time'] ?? 'N/A') . ")" : "Schedule Deactivated",
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        // Trigger daemon if activated
        if ($isActive) {
            // Wake up scheduler daemon
            app(\App\Services\ScheduleWorkerService::class)->startDaemon();
            
            // Also wake up queue worker just in case
            app(\App\Services\QueueWorkerService::class)->startBackgroundWorker();

            $message .= " Background scheduler is active.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'schedule' => $schedule,
        ]);
    }

    /**
     * Delete an entity.
     */
    public function destroy(string $key): JsonResponse
    {
        if (!$this->entityConfig->delete($key)) {
            return response()->json([
                'success' => false,
                'message' => "Entity '{$key}' not found.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => "Entity '{$key}' deleted successfully.",
        ]);
    }
}
