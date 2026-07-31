<?php

namespace App\Http\Controllers;

use App\Services\ScheduleService;
use App\Services\CronBuilder;
use App\Http\Resources\SyncScheduleResource;
use App\DTOs\ScheduleData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ScheduleController extends Controller
{
    public function __construct(
        private ScheduleService $scheduleService,
        private CronBuilder $cronBuilder
    ) {}

    public function index()
    {
        $schedules = $this->scheduleService->getAllSchedules();
        
        $transformedSchedules = $schedules->map(function($schedule) {
            return (new SyncScheduleResource($schedule))->toArray(request());
        });

        // Calculate dashboard stats
        $stats = [
            'total' => $transformedSchedules->count(),
            'active' => $transformedSchedules->where('is_active', true)->count(),
            'disabled' => $transformedSchedules->where('is_active', false)->count(),
            'running' => $transformedSchedules->where('is_running', true)->count(),
            'failed_today' => \App\Models\SyncJob::whereDate('created_at', today())
                                ->where('status', \App\Models\SyncJob::STATUS_FAILED)->count(),
            'success_today' => \App\Models\SyncJob::whereDate('created_at', today())
                                ->where('status', \App\Models\SyncJob::STATUS_COMPLETED)->count(),
        ];

        return view('scheduler.index', [
            'schedules' => $transformedSchedules,
            'stats' => $stats,
        ]);
    }

    public function show(string $entityKey): JsonResponse
    {
        $schedule = $this->scheduleService->getSchedule($entityKey);
        return response()->json([
            'success' => true,
            'data' => new SyncScheduleResource($schedule)
        ]);
    }

    public function update(Request $request, string $entityKey): JsonResponse
    {
        try {
            $data = ScheduleData::fromRequest($request);
            $schedule = $this->scheduleService->updateSchedule($entityKey, $data);
            
            return response()->json([
                'success' => true,
                'message' => "Schedule for {$entityKey} updated successfully.",
                'data' => new SyncScheduleResource($schedule)
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    public function toggle(Request $request, string $entityKey): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean'
        ]);

        try {
            $schedule = $this->scheduleService->toggleStatus($entityKey, $validated['is_active']);
            $statusStr = $schedule->is_active ? 'enabled' : 'disabled';
            
            return response()->json([
                'success' => true,
                'message' => "Schedule for {$entityKey} is now {$statusStr}.",
                'data' => new SyncScheduleResource($schedule)
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    public function preview(Request $request): JsonResponse
    {
        try {
            $data = ScheduleData::fromRequest($request);
            
            if (!$data->preset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Preset is required for preview.'
                ], 422);
            }

            $cronStr = $this->cronBuilder->fromPreset(
                $data->preset,
                $data->time,
                $data->dayOfWeek,
                $data->dayOfMonth,
                $data->customCron
            );

            $nextRuns = $this->cronBuilder->preview($cronStr, 3);
            $description = $this->cronBuilder->describe($cronStr);

            return response()->json([
                'success' => true,
                'cron' => $cronStr,
                'description' => $description,
                'timezone' => $data->timezone,
                'next_runs' => $nextRuns
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid cron configuration.'
            ], 422);
        }
    }

    public function runNow(string $entityKey): JsonResponse
    {
        try {
            $jobRecord = $this->scheduleService->runNow($entityKey);

            return response()->json([
                'success' => true,
                'message' => "Manual sync dispatched for {$entityKey}.",
                'job_id' => $jobRecord->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
