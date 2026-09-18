<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueryRepositoryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'query_repository_id',
        'user_id',
        'executed_at',
        'duration',
        'success',
        'error_message',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
        'success' => 'boolean',
        'duration' => 'decimal:4',
    ];

    public function repository()
    {
        return $this->belongsTo(QueryRepository::class, 'query_repository_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
