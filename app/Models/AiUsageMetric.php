<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageMetric extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'provider',
        'model',
        'request_tokens',
        'response_tokens',
        'total_tokens',
        'estimated_cost',
        'duration_ms',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
