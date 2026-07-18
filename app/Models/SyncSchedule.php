<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncSchedule extends Model
{
    protected $fillable = [
        'entity_key',
        'interval_minutes',
        'is_active',
        'last_run_at',
        'next_run_at',
        'custom_filters',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'custom_filters' => 'array',
    ];
}
