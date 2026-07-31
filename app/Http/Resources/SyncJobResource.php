<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $duration = null;
        if ($this->started_at) {
            $end = $this->finished_at ?? now();
            $diff = $this->started_at->diffInSeconds($end);
            
            if ($diff < 60) {
                $duration = "{$diff} sec";
            } elseif ($diff < 3600) {
                $minutes = floor($diff / 60);
                $duration = "{$minutes} min";
            } else {
                $hours = floor($diff / 3600);
                $minutes = floor(($diff % 3600) / 60);
                $duration = "{$hours}h {$minutes}m";
            }
        }

        return [
            'job_id' => $this->id,
            'entity' => $this->entity,
            'status' => $this->status,
            'progress' => (int) $this->progress,
            'processed_rows' => (int) $this->processed_rows,
            'total_rows' => (int) $this->total_rows,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration' => $duration,
            'error_message' => $this->error_message,
        ];
    }
}
