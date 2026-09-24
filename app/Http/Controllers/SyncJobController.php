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

        return view('sync-history', compact('jobs', 'paginator'));
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
