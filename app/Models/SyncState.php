<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncState extends Model
{
    protected $table = 'sync_states';

    protected $fillable = [
        'sync_schedule_id',
        'entity_name',
        'watermark_timestamp',
        'watermark_primary_key',
        'last_started_at',
        'last_finished_at',
        'total_runs',
        'total_rows_read',
        'total_rows_inserted',
        'total_rows_updated',
        'total_rows_failed',
        'status',
        'lock_owner',
        'lock_acquired_at',
        'lock_expires_at',
        'heartbeat_at',
        'retry_count',
    ];

    protected $casts = [
        'watermark_timestamp' => 'datetime',
        'last_started_at' => 'datetime',
        'last_finished_at' => 'datetime',
        'lock_acquired_at' => 'datetime',
        'lock_expires_at' => 'datetime',
        'heartbeat_at' => 'datetime',
    ];

    // Relationships
    public function syncSchedule()
    {
        return $this->belongsTo(SyncSchedule::class, 'sync_schedule_id');
    }

    public function schedule()
    {
        return $this->syncSchedule();
    }

    public function syncLogs()
    {
        return $this->hasMany(SyncLog::class, 'entity_key', 'entity_name');
    }
}
