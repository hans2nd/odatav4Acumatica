<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncEntity extends Model
{
    protected $table = 'sync_entities';

    protected $fillable = [
        'code',
        'name',
        'source_connection_id',
        'source_object',
        'destination_connection_id',
        'destination_table',
        'sync_mode',
        'watermark_type',
        'watermark_column',
        'batch_size',
        'is_active',
        'description',
        'type',
        'icon',
        'schedule_minutes',
        'extra_attributes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'batch_size' => 'integer',
        'schedule_minutes' => 'integer',
        'extra_attributes' => 'array',
    ];

    /**
     * Relationship: fields belonging to this entity.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(SyncEntityField::class, 'sync_entity_id');
    }

    /**
     * Temporary compatibility layer: convert to the legacy config array format
     * expected by existing consumers (controllers, services, jobs).
     *
     * Returns the same shape as the old JSON entries:
     *   [
     *       'odata_entity'           => '...',
     *       'table'                  => '...',
     *       'type'                   => '...',
     *       'icon'                   => '...',
     *       'description'            => '...',
     *       'source_connection'      => '...',
     *       'destination_connection' => '...',
     *       'schedule_minutes'       => ...,
     *       'available_fields'       => [...],  // if fields are loaded
     *   ]
     */
    public function toConfigArray(): array
    {
        $config = [
            'odata_entity'           => $this->source_object,
            'table'                  => $this->destination_table,
            'type'                   => $this->type,
            'icon'                   => $this->icon ?? 'database',
            'description'            => $this->description ?? $this->name,
            'source_connection'      => $this->source_connection_id ?? 'acumatica_default',
            'destination_connection' => $this->destination_connection_id ?? 'database_default',
            'schedule_minutes'       => $this->schedule_minutes,
            'sync_mode'              => $this->sync_mode,
            'watermark_type'         => $this->watermark_type,
            'watermark_column'       => $this->watermark_column,
            'batch_size'             => $this->batch_size,
            'is_active'              => $this->is_active,
        ];

        // Include available_fields if fields relationship is loaded
        if ($this->relationLoaded('fields') && $this->fields->isNotEmpty()) {
            $config['available_fields'] = $this->fields
                ->where('is_enabled', true)
                ->pluck('source_field')
                ->values()
                ->toArray();
        }

        // Merge any extra attributes to preserve unexpected JSON properties (e.g. primary_keys)
        if (is_array($this->extra_attributes)) {
            $config = array_merge($this->extra_attributes, $config);
        }

        return $config;
    }
}
