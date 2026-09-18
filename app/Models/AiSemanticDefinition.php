<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSemanticDefinition extends Model
{
    protected $fillable = [
        'connection_id',
        'type',
        'name',
        'description',
        'expression',
        'source_table',
        'source_column',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
