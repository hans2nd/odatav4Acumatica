<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncBottleneck extends Model
{
    protected $table = 'sync_bottlenecks';

    protected $fillable = [
        'sync_log_id',
        'entity_key',
        'pipeline',
        'stage',
        'duration_ms',
        'threshold_ms',
        'severity',
        'recommendation',
    ];

    public function log()
    {
        return $this->belongsTo(SyncLog::class, 'sync_log_id');
    }
}
