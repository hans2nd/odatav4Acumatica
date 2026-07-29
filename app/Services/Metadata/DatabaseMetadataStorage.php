<?php

namespace App\Services\Metadata;

use App\Contracts\Metadata\DatabaseMetadataStorageInterface;
use App\DTO\Metadata\MetadataEntity;
use App\DTO\Metadata\MetadataField;
use Illuminate\Support\Facades\DB;

class DatabaseMetadataStorage implements DatabaseMetadataStorageInterface
{
    public function save(MetadataEntity $metadataEntity, string $version, string $hash): void
    {
        DB::transaction(function () use ($metadataEntity, $version, $hash) {
            $defId = DB::table('sync_metadata_definitions')->updateOrInsert(
                ['entity_key' => $metadataEntity->entityKey],
                [
                    'provider' => 'acumatica',
                    'provider_version' => $metadataEntity->providerVersion,
                    'metadata_version' => $version,
                    'metadata_hash' => $hash,
                    'last_synced_at' => now(),
                    'updated_at' => now(),
                    'is_valid' => true
                ]
            );

            if ($defId === true || $defId === false) {
                $defRecord = DB::table('sync_metadata_definitions')->where('entity_key', $metadataEntity->entityKey)->first();
                $defId = $defRecord->id;
            }

            DB::table('sync_metadata_fields')->where('definition_id', $defId)->delete();

            $fieldsToInsert = [];
            foreach ($metadataEntity->fields as $field) {
                $fieldsToInsert[] = [
                    'definition_id' => $defId,
                    'field_name' => $field->name,
                    'data_type' => $field->dataType,
                    'is_primary_key' => $field->isPrimaryKey,
                    'is_nullable' => $field->isNullable,
                    'max_length' => $field->maxLength,
                    'precision' => $field->precision,
                    'scale' => $field->scale,
                    'relationship_type' => $field->relationshipType,
                    'related_entity' => $field->relatedEntity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($fieldsToInsert)) {
                $chunks = array_chunk($fieldsToInsert, 100);
                foreach ($chunks as $chunk) {
                    DB::table('sync_metadata_fields')->insert($chunk);
                }
            }
        });
    }

    public function get(string $entityKey): ?MetadataEntity
    {
        $def = DB::table('sync_metadata_definitions')->where('entity_key', $entityKey)->first();
        if (!$def) return null;

        $fieldsRows = DB::table('sync_metadata_fields')->where('definition_id', $def->id)->get();
        
        $fields = [];
        foreach ($fieldsRows as $row) {
            $fields[] = new MetadataField(
                name: $row->field_name,
                dataType: $row->data_type,
                isPrimaryKey: (bool) $row->is_primary_key,
                isNullable: (bool) $row->is_nullable,
                maxLength: $row->max_length,
                precision: $row->precision,
                scale: $row->scale,
                relationshipType: $row->relationship_type,
                relatedEntity: $row->related_entity
            );
        }

        return new MetadataEntity(
            entityKey: $entityKey,
            fields: $fields,
            providerVersion: $def->provider_version
        );
    }

    public function getVersion(string $entityKey): ?string
    {
        return DB::table('sync_metadata_definitions')
            ->where('entity_key', $entityKey)
            ->value('metadata_version');
    }

    public function getHash(string $entityKey): ?string
    {
        return DB::table('sync_metadata_definitions')
            ->where('entity_key', $entityKey)
            ->value('metadata_hash');
    }

    public function logRefreshOperation(array $data): void
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();
        DB::table('sync_metadata_refresh_history')->insert($data);
    }

    public function logHistory(string $entityKey, ?string $oldVersion, string $newVersion, array $changes): void
    {
        DB::table('sync_metadata_history')->insert([
            'entity_key' => $entityKey,
            'previous_version' => $oldVersion,
            'new_version' => $newVersion,
            'changes_detected' => json_encode($changes),
            'source' => 'provider',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function recordStatistics(string $entityKey, array $stats): void
    {
        $existing = DB::table('sync_metadata_statistics')->where('entity_key', $entityKey)->first();
        
        if ($existing) {
            DB::table('sync_metadata_statistics')->where('entity_key', $entityKey)->update([
                'cache_hits' => $existing->cache_hits + ($stats['cache_hits'] ?? 0),
                'cache_misses' => $existing->cache_misses + ($stats['cache_misses'] ?? 0),
                'provider_calls' => $existing->provider_calls + ($stats['provider_calls'] ?? 0),
                'refresh_count' => $existing->refresh_count + ($stats['refresh_count'] ?? 0),
                'validation_errors' => $existing->validation_errors + ($stats['validation_errors'] ?? 0),
                'last_discovery_time_ms' => $stats['discovery_time_ms'] ?? $existing->last_discovery_time_ms,
                'updated_at' => now()
            ]);
        } else {
            DB::table('sync_metadata_statistics')->insert([
                'entity_key' => $entityKey,
                'cache_hits' => $stats['cache_hits'] ?? 0,
                'cache_misses' => $stats['cache_misses'] ?? 0,
                'provider_calls' => $stats['provider_calls'] ?? 0,
                'refresh_count' => $stats['refresh_count'] ?? 0,
                'validation_errors' => $stats['validation_errors'] ?? 0,
                'last_discovery_time_ms' => $stats['discovery_time_ms'] ?? 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
