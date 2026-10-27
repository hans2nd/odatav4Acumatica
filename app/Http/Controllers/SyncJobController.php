<?php

namespace App\Http\Controllers;

use App\Models\SyncJob;
use App\Http\Resources\SyncJobResource;
use Illuminate\Http\Request;

class SyncJobController extends Controller
{
    public function page(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $paginator = SyncJob::query()->orderByDesc('created_at')->paginate($perPage);
        $jobs = SyncJobResource::collection($paginator)->toArray($request);
        $queueStatus = $this->getQueueStatusData();

        return view('sync-history', compact('jobs', 'paginator', 'queueStatus'));
    }

    public function queueStatus()
    {
        return response()->json([
            'success' => true,
            'data' => $this->getQueueStatusData(),
        ]);
    }

    public function getQueueStatusData(): array
    {
        try {
            $runningJobs = SyncJob::where('status', SyncJob::STATUS_RUNNING)
                ->orderBy('started_at')
                ->get();

            $pendingJobs = SyncJob::where('status', SyncJob::STATUS_PENDING)
                ->orderBy('created_at')
                ->get();

            $rawQueueCount = 0;
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('jobs')) {
                    $rawQueueCount = (int) \Illuminate\Support\Facades\DB::table('jobs')->count();
                }
            } catch (\Throwable $e) {}

            $activeLocks = collect();
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('sync_states')) {
                    $activeLocks = \App\Models\SyncState::with('syncSchedule')
                        ->whereNotNull('lock_owner')
                        ->where(function ($q) {
                            $q->whereNull('lock_expires_at')
                              ->orWhere('lock_expires_at', '>', now());
                        })
                        ->get()
                        ->map(fn($s) => [
                            'entity' => $s->entity_name ?: ($s->syncSchedule?->entity_key ?? 'Unknown'),
                            'owner' => $s->lock_owner,
                            'acquired_at' => $s->lock_acquired_at ? \Carbon\Carbon::parse($s->lock_acquired_at)->diffForHumans() : null,
                            'heartbeat_at' => $s->heartbeat_at ? \Carbon\Carbon::parse($s->heartbeat_at)->diffForHumans() : null,
                        ]);
                }
            } catch (\Throwable $e) {}

            return [
                'running_count' => $runningJobs->count(),
                'pending_count' => max($pendingJobs->count(), $rawQueueCount),
                'raw_queue_count' => $rawQueueCount,
                'running_jobs' => $runningJobs->map(fn($j) => [
                    'id' => $j->id,
                    'entity' => $j->entity,
                    'progress' => $j->progress,
                    'processed_rows' => $j->processed_rows,
                    'total_rows' => $j->total_rows,
                    'started_at' => $j->started_at ? \Carbon\Carbon::parse($j->started_at)->diffForHumans() : null,
                ])->toArray(),
                'pending_jobs' => $pendingJobs->map(fn($j) => [
                    'id' => $j->id,
                    'entity' => $j->entity,
                    'queued_at' => $j->created_at ? \Carbon\Carbon::parse($j->created_at)->diffForHumans() : null,
                ])->toArray(),
                'active_locks' => $activeLocks->toArray(),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('getQueueStatusData error: ' . $e->getMessage(), ['exception' => $e]);
            return [
                'running_count' => 0,
                'pending_count' => 0,
                'raw_queue_count' => 0,
                'running_jobs' => [],
                'pending_jobs' => [],
                'active_locks' => [],
            ];
        }
    }

    public function index(Request $request)
    {
        $query = SyncJob::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('entity')) {
            $query->where('entity', $request->input('entity'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('entity', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 20);
        $jobs = $query->paginate($perPage);

        return SyncJobResource::collection($jobs);
    }

    public function active()
    {
        $jobs = SyncJob::whereIn('status', [SyncJob::STATUS_PENDING, SyncJob::STATUS_RUNNING])
            ->orderByDesc('created_at')
            ->get();

        return SyncJobResource::collection($jobs);
    }

    public function show($id)
    {
        $job = SyncJob::findOrFail($id);
        return new SyncJobResource($job);
    }

    public function abort($id)
    {
        $job = SyncJob::findOrFail($id);

        if (in_array($job->status, [SyncJob::STATUS_PENDING, SyncJob::STATUS_RUNNING])) {
            $job->update([
                'status' => SyncJob::STATUS_FAILED,
                'error_message' => 'Aborted by user',
                'finished_at' => now(),
            ]);

            // Attempt to stop the underlying SyncService execution by placing a cache control signal
            // for any currently running SyncLog corresponding to this entity.
            $activeLogs = \App\Models\SyncLog::where('entity_key', $job->entity)
                ->where('status', 'running')
                ->get();
                
            foreach ($activeLogs as $log) {
                \Illuminate\Support\Facades\Cache::put('sync_control_log_' . $log->id, 'cancel', 3600);
            }

            return response()->json(['success' => true, 'message' => 'Job aborted successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Job cannot be aborted because it is already ' . $job->status], 400);
    }
}
