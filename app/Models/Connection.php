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
    ];

    protected $casts = [
        'credentials'  => 'array',
        'is_active'    => 'boolean',
        'is_connected' => 'boolean',
    ];
}
