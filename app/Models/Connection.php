<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Connection extends Model
{
    protected $fillable = [
        'key',
        'name',
        'type',
        'credentials',
        'is_active',
        'is_connected',
        'is_default',
        'is_system',
        'read_only',
        'deletable',
        'editable',
        'status',
        'is_analytics_source',
    ];

    protected $casts = [
        'credentials'  => \App\Casts\EncryptedCredentials::class,
        'is_active'    => 'boolean',
        'is_connected' => 'boolean',
        'is_default'   => 'boolean',
        'is_system'    => 'boolean',
        'read_only'    => 'boolean',
        'deletable'    => 'boolean',
        'editable'     => 'boolean',
        'is_analytics_source' => 'boolean',
    ];
}
