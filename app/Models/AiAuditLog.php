<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'connection_id',
        'conversation_id',
        'query_plan_json',
        'sql_hash',
        'status',
        'duration_ms',
        'row_count',
        'error_message',
    ];

    protected $casts = [
        'query_plan_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
