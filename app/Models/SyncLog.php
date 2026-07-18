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
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
        'duration_seconds' => 'decimal:2',
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
}
