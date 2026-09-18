<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Cron\CronExpression;
use App\Models\SyncJob;

class SyncScheduleResource extends JsonResource
{
    public function toArray($request)
    {
        $nextRun = null;
        if ($this->is_active && $this->cron_expression) {
            try {
                $cron = new CronExpression($this->cron_expression);
                $nextRun = $cron->getNextRunDate()->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // Fallback if expression is invalid somehow
            }
        }

        $latestJob = SyncJob::where('entity', $this->entity_key)
            ->orderBy('id', 'desc')
            ->first();

        $isRunning = $latestJob && in_array($latestJob->status, [SyncJob::STATUS_PENDING, SyncJob::STATUS_RUNNING]);

        return [
            'entity_key' => $this->entity_key,
            'is_active' => (bool)$this->is_active,
            'frequency' => $this->frequency, // Uses the model's accessor
            'cron_expression' => $this->cron_expression,
            'next_run' => $nextRun,
            'last_run_at' => $this->last_run_at ? $this->last_run_at->format('Y-m-d H:i:s') : null,
            'is_running' => $isRunning,
            'latest_job_status' => $latestJob ? $latestJob->status : null,
            'latest_job_id' => $latestJob ? $latestJob->id : null,
        ];
    }
}
