<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $table = 'sync_logs';

    protected $fillable = [
        'entity_key',
        'entity_description',
        'odata_entity',
        'table_name',
        'type',
        'source',
        'status',
        'records_fetched',
        'records_inserted',
        'duration_seconds',
        'error_message',
        'stack_trace',
        'started_at',
        'completed_at',
        'current_stage',
        'current_batch',
        'total_batch',
        'records_updated',
        'records_deleted',
        'records_skipped',
        'records_failed',
        'retry_count',
        'resume_count',
        'rows_per_second',
        'peak_rows_per_second',
        'average_rows_per_second',
        'peak_memory_mb',
        'current_operation',
        'current_checkpoint',
        'triggered_by',
        'execution_source'
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
        'duration_seconds' => 'decimal:2',
        'current_checkpoint' => 'array',
        'rows_per_second' => 'decimal:2',
        'peak_rows_per_second' => 'decimal:2',
        'average_rows_per_second' => 'decimal:2',
        'peak_memory_mb' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeLatestPerEntity($query)
    {
        return $query->whereIn('id', function ($sub) {
            $sub->selectRaw('MAX(id)')
                ->from('sync_logs')
                ->groupBy('entity_key');
        });
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeMaster($query)
    {
        return $query->where('type', 'master');
    }

    public function scopeTransaction($query)
    {
        return $query->where('type', 'transaction');
    }

    public function syncState()
    {
        return $this->belongsTo(SyncState::class, 'entity_key', 'entity_name');
    }

    public function bottlenecks()
    {
        return $this->hasMany(SyncBottleneck::class, 'sync_log_id');
    }
}
