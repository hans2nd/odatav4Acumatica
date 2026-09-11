<?php

namespace App\Services;

use App\Models\SyncEntity;
use App\Models\SyncEntityField;
use App\Services\PermissionGeneratorService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EntityConfigService
{
    /**
     * Get all entities as a keyed array (legacy format).
     *
     * @return array<string, array>  Keyed by entity code
     */
    public function getAll(): array
    {
        try {
            return SyncEntity::with('fields')
                ->get()
                ->keyBy('code')
                ->map(fn(SyncEntity $entity) => $entity->toConfigArray())
                ->toArray();
        } catch (\Throwable $e) {
            // Graceful fallback during pre-migration state (tables don't exist yet)
            Log::warning('EntityConfigService::getAll() failed — tables may not exist yet: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single entity by key (legacy format).
     */
    public function get(string $key): ?array
    {
        try {
            $entity = SyncEntity::with('fields')->where('code', $key)->first();
            return $entity ? $entity->toConfigArray() : null;
        } catch (\Throwable $e) {
            Log::warning("EntityConfigService::get({$key}) failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the SyncEntity model instance by code.
     */
    public function getModel(string $key): ?SyncEntity
    {
        return SyncEntity::where('code', $key)->first();
    }

    /**
     * Get entities filtered by type (legacy format).
     */
    public function getByType(string $type): array
    {
        try {
            return SyncEntity::with('fields')
                ->where('type', $type)
                ->get()
                ->keyBy('code')
                ->map(fn(SyncEntity $entity) => $entity->toConfigArray())
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning("EntityConfigService::getByType({$type}) failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Helper to extract extra attributes not mapped to columns.
     */
    private function extractExtraAttributes(array $data): ?array
    {
        $knownKeys = [
            'code', 'key', 'name', 'odata_entity', 'table', 'type', 'icon',
            'description', 'source_connection', 'destination_connection',
            'schedule_minutes', 'available_fields', 'created_at', 'updated_at'
        ];

        $extra = [];
        foreach ($data as $k => $v) {
            if (!in_array($k, $knownKeys)) {
                $extra[$k] = $v;
            }
        }

        return !empty($extra) ? $extra : null;
    }

    /**
     * Create or update an entity.
     */
    public function save(string $key, array $data): void
    {
        DB::transaction(function () use ($key, $data) {
            $entity = SyncEntity::updateOrCreate(
                ['code' => $key],
                [
                    'name'                      => $data['description'] ?? $key,
                    'source_object'             => $data['odata_entity'],
                    'destination_table'         => $data['table'],
                    'type'                      => $data['type'],
                    'icon'                      => $data['icon'] ?? 'database',
                    'description'               => $data['description'] ?? null,
                    'source_connection_id'      => $data['source_connection'] ?? 'acumatica_default',
                    'destination_connection_id' => $data['destination_connection'] ?? 'database_default',
                    'schedule_minutes'          => $data['schedule_minutes'] ?? null,
                    'sync_mode'                 => $data['sync_mode'] ?? 'full',
                    'watermark_type'            => $data['watermark_type'] ?? null,
                    'watermark_column'          => $data['watermark_column'] ?? null,
                    'batch_size'                => $data['batch_size'] ?? 2000,
                    'is_active'                 => $data['is_active'] ?? true,
                    'extra_attributes'          => $this->extractExtraAttributes($data),
                ]
            );
        });

        $this->syncPermissions();
    }

    /**
     * Update an existing entity (supports key rename).
     */
    public function update(string $oldKey, string $newKey, array $data): void
    {
        DB::transaction(function () use ($oldKey, $newKey, $data) {
            $entity = SyncEntity::where('code', $oldKey)->first();

            if (!$entity) {
                // Fallback to create if not found
                $this->save($newKey, $data);
                return;
            }

            $entity->update([
                'code'                      => $newKey,
                'name'                      => $data['description'] ?? $newKey,
                'source_object'             => $data['odata_entity'],
                'destination_table'         => $data['table'],
                'type'                      => $data['type'],
                'icon'                      => $data['icon'] ?? 'database',
                'description'               => $data['description'] ?? null,
                'source_connection_id'      => $data['source_connection'] ?? 'acumatica_default',
                'destination_connection_id' => $data['destination_connection'] ?? 'database_default',
                'schedule_minutes'          => $data['schedule_minutes'] ?? null,
                'sync_mode'                 => $data['sync_mode'] ?? 'full',
                'watermark_type'            => $data['watermark_type'] ?? null,
                'watermark_column'          => $data['watermark_column'] ?? null,
                'batch_size'                => $data['batch_size'] ?? 2000,
                'is_active'                 => $data['is_active'] ?? true,
                'extra_attributes'          => $this->extractExtraAttributes($data),
            ]);
        });

        $this->syncPermissions();
    }

    /**
     * Merge/upsert discovered fields for an entity without overwriting
     * manual mappings (destination_field, semantic_type, is_key, transform_rule).
     */
    public function updateAvailableFields(string $key, array $fields): void
    {
        $entity = SyncEntity::where('code', $key)->first();
        if (!$entity) {
            return;
        }

        DB::transaction(function () use ($entity, $fields) {
            foreach ($fields as $fieldData) {
                $fieldName = is_array($fieldData) ? $fieldData['name'] : $fieldData;
                $record = SyncEntityField::firstOrNew([
                    'sync_entity_id' => $entity->id,
                    'source_field'   => $fieldName,
                ]);

                if (!$record->exists) {
                    $record->data_type = is_array($fieldData) ? ($fieldData['data_type'] ?? null) : null;
                    $record->semantic_type = is_array($fieldData) ? ($fieldData['semantic_type'] ?? null) : null;
                    $record->save();
                } else {
                    $dirty = false;
                    if (is_array($fieldData)) {
                        if (empty($record->data_type) && !empty($fieldData['data_type'])) {
                            $record->data_type = $fieldData['data_type'];
                            $dirty = true;
                        }
                        if (empty($record->semantic_type) && !empty($fieldData['semantic_type'])) {
                            $record->semantic_type = $fieldData['semantic_type'];
                            $dirty = true;
                        }
                    }
                    if ($dirty) $record->save();
                }
            }
        });
    }

    /**
     * Explicitly update field mappings (driven by UI).
     * Overwrites mappings but maintains existing records safely.
     */
    public function updateFields(int $entityId, array $fields): void
    {
        DB::transaction(function () use ($entityId, $fields) {
            foreach ($fields as $field) {
                SyncEntityField::updateOrCreate(
                    [
                        'sync_entity_id' => $entityId,
                        'source_field'   => $field['source_field'],
                    ],
                    [
                        'destination_field' => $field['destination_field'] ?? null,
                        'data_type'         => $field['data_type'] ?? null,
                        'semantic_type'     => $field['semantic_type'] ?? null,
                        'is_key'            => $field['is_key'] ?? false,
                        'is_nullable'       => $field['is_nullable'] ?? true,
                        'transform_rule'    => $field['transform_rule'] ?? null,
                        'is_enabled'        => $field['is_enabled'] ?? true,
                        'label'             => $field['label'] ?? null,
                        'description'       => $field['description'] ?? null,
                        'aliases'           => $field['aliases'] ?? null,
                    ]
                );
            }
        });
    }

    /**
     * Delete an entity by key.
     */
    public function delete(string $key): bool
    {
        $deleted = SyncEntity::where('code', $key)->delete();

        if ($deleted) {
            $this->syncPermissions();
        }

        return $deleted > 0;
    }

    /**
     * Delete multiple entities by keys in a single transaction.
     */
    public function batchDelete(array $keys): bool
    {
        try {
            DB::transaction(function () use ($keys) {
                $entityIds = SyncEntity::whereIn('code', $keys)->pluck('id');
                if ($entityIds->isNotEmpty()) {
                    SyncEntityField::whereIn('sync_entity_id', $entityIds)->delete();
                    SyncEntity::whereIn('id', $entityIds)->delete();
                }
            });

            $this->syncPermissions();
            return true;
        } catch (\Throwable $e) {
            Log::error("EntityConfigService::batchDelete failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if an entity key already exists.
     */
    public function exists(string $key): bool
    {
        try {
            return SyncEntity::where('code', $key)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Trigger permission sync after entity changes.
     */
    protected function syncPermissions(): void
    {
        try {
            app(PermissionGeneratorService::class)->generate();
        } catch (\Throwable $e) {
            // Silently fail — permissions table may not exist yet (pre-migration)
            Log::warning('Permission sync skipped: ' . $e->getMessage());
        }
    }
}
