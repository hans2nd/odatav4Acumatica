<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncEntityField extends Model
{
    // Semantic Type Constants
    public const TYPE_IDENTIFIER  = 'identifier';
    public const TYPE_METRIC      = 'metric';
    public const TYPE_AMOUNT      = 'amount';
    public const TYPE_CURRENCY    = 'currency';
    public const TYPE_DIMENSION   = 'dimension';
    public const TYPE_CATEGORICAL = 'categorical';
    public const TYPE_DATE        = 'date';
    public const TYPE_DATETIME    = 'datetime';
    public const TYPE_BOOLEAN     = 'boolean';
    public const TYPE_STATUS      = 'status';

    protected $table = 'sync_entity_fields';

    protected $fillable = [
        'sync_entity_id',
        'source_field',
        'destination_field',
        'data_type',
        'semantic_type',
        'is_key',
        'is_nullable',
        'transform_rule',
        'is_enabled',
        'label',
        'description',
        'aliases',
    ];

    protected $casts = [
        'is_key' => 'boolean',
        'is_nullable' => 'boolean',
        'is_enabled' => 'boolean',
        'aliases' => 'array',
    ];

    /**
     * Relationship: the parent sync entity.
     */
    public function syncEntity(): BelongsTo
    {
        return $this->belongsTo(SyncEntity::class, 'sync_entity_id');
    }
}
