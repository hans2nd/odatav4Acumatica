<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiEndpoint extends Model
{
    protected $fillable = [
        'name',
        'connection_key',
        'table_name',
        'columns',
        'filters',
        'endpoint_slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'columns'   => 'array',
        'filters'   => 'array',
    ];
}