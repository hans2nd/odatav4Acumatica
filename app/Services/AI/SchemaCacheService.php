<?php

namespace App\Services\AI;

use App\Models\Connection;
use App\DTO\Metadata\MetadataEntity;
use App\DTO\Metadata\MetadataField;
use App\Services\Metadata\DatabaseMetadataStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

class SchemaCacheService
{
    public function __construct(
        private DatabaseMetadataStorage $metadataStorage
    ) {}

    public function cacheSchema(Connection $connection): void
    {
        if (!$connection->is_analytics_source) {
            throw new \Exception("Cannot cache schema: Connection is not the designated analytics source.");
        }

        $dbName = 'ai_schema_cache_' . $connection->id;
        
        config(["database.connections.{$dbName}" => array_merge(
            ['driver' => $connection->type],
            $connection->credentials ?? []
        )]);

        try {
            $dbConnection = DB::connection($dbName);
            
            // Laravel 11 native fallback if doctrine is absent: 
            $tables = array_column(Schema::connection($dbName)->getTables(), 'name');

            foreach ($tables as $table) {
                $columns = Schema::connection($dbName)->getColumns($table);
                $fields = [];
                
                // Fetch existing semantic_type from analytics DB to preserve manual configurations
                $existingFields = DB::table('sync_metadata_fields')
                    ->join('sync_metadata_definitions', 'sync_metadata_fields.definition_id', '=', 'sync_metadata_definitions.id')
                    ->where('sync_metadata_definitions.connection_id', $connection->id)
                    ->where('sync_metadata_definitions.entity_key', $table)
                    ->select('sync_metadata_fields.field_name', 'sync_metadata_fields.semantic_type')
                    ->get()
                    ->keyBy('field_name');

                // Try to find source connection metadata
                $sourceFields = null;
                $entityConfig = app(\App\Services\EntityConfigService::class)->getAll();
                $config = collect($entityConfig)->firstWhere('table', $table);
                
                if ($config && isset($config['odata_entity'])) {
                    $sourceConn = app(\App\Services\ConnectionService::class)->get($config['source_connection'] ?? 'acumatica_default');
                    if ($sourceConn) {
                        $sourceDef = DB::table('sync_metadata_definitions')
                            ->where('connection_id', $sourceConn['id'] ?? $sourceConn->id ?? 0)
                            ->where('entity_key', $config['odata_entity'])
                            ->first();
                        
                        if ($sourceDef) {
                            $sourceFields = DB::table('sync_metadata_fields')
                                ->where('definition_id', $sourceDef->id)
                                ->select('field_name', 'semantic_type')
                                ->get()
                                ->keyBy('field_name');
                        }
                    }
                }
                
                foreach ($columns as $column) {
                    $colName = $column['name'];
                    
                    // Cascade semantic type
                    $semanticType = $existingFields->get($colName)->semantic_type ?? null;
                    if (empty($semanticType) && $sourceFields) {
                        $semanticType = $sourceFields->get($colName)->semantic_type ?? null;
                    }

                    $fields[] = new MetadataField(
                        name: $colName,
                        dataType: $column['type_name'],
                        isPrimaryKey: false, // Would require deeper extraction logic for P1
                        isNullable: $column['nullable'],
                        maxLength: null,
                        precision: null,
                        scale: null,
                        relationshipType: null,
                        relatedEntity: null,
                        semanticType: $semanticType
                    );
                }

                $entity = new MetadataEntity(
                    entityKey: $table,
                    fields: $fields,
                    providerVersion: '1.0',
                    connectionId: $connection->id,
                    provider: 'analytics'
                );

                $this->metadataStorage->save($entity, '1.0', $entity->getHash());

                // Build Semantic Profile
                $definition = DB::table('sync_metadata_definitions')
                    ->where('connection_id', $connection->id)
                    ->where('entity_key', $table)
                    ->first();

                if ($definition) {
                    $dateFields = [];
                    $filterableFields = [];
                    foreach ($columns as $col) {
                        $type = strtolower($col['type_name']);
                        $name = strtolower($col['name']);
                        
                        if (str_contains($type, 'date') || str_contains($type, 'time') || str_contains($name, 'date')) {
                            $dateFields[] = $col['name'];
                        } elseif (str_contains($type, 'char') || str_contains($type, 'text') || str_contains($type, 'enum') || str_contains($type, 'int') || str_contains($name, 'cd') || str_contains($name, 'id')) {
                            $filterableFields[] = $col['name'];
                        }
                    }

                    $metricDefs = \App\Models\AiSemanticDefinition::where('connection_id', $connection->id)
                        ->where('source_table', $table)
                        ->where('type', 'metric')
                        ->get();

                    $semanticMetrics = [];
                    foreach ($metricDefs as $m) {
                        $meta = is_string($m->metadata) ? json_decode($m->metadata, true) : ($m->metadata ?? []);
                        $semanticMetrics[] = [
                            'name' => $m->name,
                            'source_column' => $m->source_column,
                            'aggregation' => $meta['aggregation'] ?? 'SUM',
                            'aliases' => $meta['aliases'] ?? [],
                            'is_default' => $meta['is_default'] ?? false
                        ];
                    }

                    $semanticDimensions = [];
                    $syncEntity = \App\Models\SyncEntity::where('destination_table', $table)->first();
                    if ($syncEntity) {
                        $dimensionDefs = \App\Models\SyncEntityField::where('sync_entity_id', $syncEntity->id)
                            ->where('is_enabled', true)
                            ->whereNotNull('destination_field')
                            ->whereIn('semantic_type', ['dimension', 'categorical', 'status', 'identifier', 'boolean', 'date', 'datetime'])
                            ->get();
                            
                        foreach ($dimensionDefs as $d) {
                            $semanticDimensions[] = [
                                'name' => $d->label ?? $d->destination_field,
                                'source_column' => $d->destination_field,
                                'aliases' => is_array($d->aliases) ? $d->aliases : []
                            ];
                        }
                    }

                    $dateDefs = \App\Models\AiSemanticDefinition::where('connection_id', $connection->id)
                        ->where('source_table', $table)
                        ->where('type', 'date')
                        ->get();

                    $semanticDates = [];
                    foreach ($dateDefs as $d) {
                        $meta = is_string($d->metadata) ? json_decode($d->metadata, true) : ($d->metadata ?? []);
                        $semanticDates[] = [
                            'name' => $d->name,
                            'source_column' => $d->source_column,
                            'role' => $meta['role'] ?? 'transaction_date'
                        ];
                    }

                    $existingProfile = $definition->semantic_profile ? json_decode($definition->semantic_profile, true) : [];

                    $semanticProfile = [
                        'logical_name' => $definition->logical_name ?? $table,
                        'data_role' => $definition->data_role ?? 'other',
                        'description' => $definition->description ?? '',
                        'aliases' => $definition->aliases ? json_decode($definition->aliases, true) : [],
                        'metrics' => $semanticMetrics,
                        'dimensions' => $semanticDimensions,
                        'semantic_dates' => $semanticDates,
                        'date_fields' => $dateFields,
                        'filterable_fields' => $filterableFields,
                        'transaction_grain' => $existingProfile['transaction_grain'] ?? null,
                        'capabilities' => $existingProfile['capabilities'] ?? [],
                        'hierarchies' => $existingProfile['hierarchies'] ?? [],
                        'question_patterns' => $existingProfile['question_patterns'] ?? []
                    ];

                    DB::table('sync_metadata_definitions')
                        ->where('id', $definition->id)
                        ->update(['semantic_profile' => json_encode($semanticProfile)]);
                }
            }
        } finally {
            DB::purge($dbName);
            $this->clearCache($connection->id);
        }
    }

    /**
     * Invalidate the schema cache and suggested questions for a specific connection.
     */
    public function clearCache(int $connectionId): void
    {
        try {
            $currentVer = (int) Cache::get("ai_schema_ver_{$connectionId}", 1);
            Cache::put("ai_schema_ver_{$connectionId}", $currentVer + 1, 86400 * 30);
        } catch (\Throwable $e) {
            Cache::forget("ai_schema_ver_{$connectionId}");
        }
        Cache::forget("ai_suggested_questions_{$connectionId}");
    }

    public function getCache(int $connectionId, ?string $domainContext = null)
    {
        $version = Cache::get("ai_schema_ver_{$connectionId}", 1);
        $domainKey = $domainContext ? md5($domainContext) : 'all';
        $cacheKey = "ai_schema_{$connectionId}_v{$version}_{$domainKey}";

        return Cache::remember($cacheKey, 3600, function () use ($connectionId, $domainContext) {
            // Retrieve only ChatEDP-enabled metadata definitions for this connection
            $query = DB::table('sync_metadata_definitions')
                ->where('connection_id', $connectionId)
                ->where('chatedp_enabled', true);
                
            if ($domainContext) {
                $query->where(function ($q) use ($domainContext) {
                    $q->whereNull('business_domains')
                      ->orWhere('business_domains', '[]')
                      ->orWhere('business_domains', 'null')
                      ->orWhereJsonContains('business_domains', $domainContext);
                });
            }
            
            $definitions = $query->get();
                
            $tables = [];
            foreach ($definitions as $def) {
                $profile = null;
                if ($def->semantic_profile) {
                    $profile = json_decode($def->semantic_profile, true);
                }
                
                if (!$profile) {
                    $profile = [
                        'logical_name' => $def->logical_name ?? $def->entity_key,
                        'description' => $def->description,
                        'aliases' => $def->aliases ? json_decode($def->aliases, true) : [],
                        'data_role' => $def->data_role ?? 'other',
                        'metrics' => [],
                        'dimensions' => [],
                        'semantic_dates' => [],
                        'date_fields' => [],
                        'filterable_fields' => [],
                        'transaction_grain' => null,
                        'capabilities' => [],
                        'hierarchies' => [],
                        'question_patterns' => []
                    ];
                }
                
                $profile['physical_name'] = $def->entity_key;
                
                // Fallback: Dynamically load if metrics/dimensions are empty
                if (empty($profile['metrics'])) {
                    $metricDefs = \App\Models\AiSemanticDefinition::where('connection_id', $connectionId)
                        ->where('source_table', $def->entity_key)
                        ->where('type', 'metric')
                        ->get();
                    foreach ($metricDefs as $m) {
                        $meta = is_string($m->metadata) ? json_decode($m->metadata, true) : ($m->metadata ?? []);
                        $profile['metrics'][] = [
                            'name' => $m->name,
                            'source_column' => $m->source_column,
                            'aggregation' => $meta['aggregation'] ?? 'SUM',
                            'aliases' => $meta['aliases'] ?? [],
                            'is_default' => $meta['is_default'] ?? false
                        ];
                    }
                }

                $needsDimensions = empty($profile['dimensions']);
                $needsDateFields = empty($profile['date_fields']);
                $needsFilterableFields = empty($profile['filterable_fields']);

                if ($needsDimensions || $needsDateFields || $needsFilterableFields) {
                    $fields = DB::table('sync_metadata_fields')
                        ->where('definition_id', $def->id)
                        ->get();
                        
                    foreach ($fields as $f) {
                        // Populate dimensions
                        if ($needsDimensions && in_array($f->semantic_type ?? null, ['dimension', 'categorical', 'status', 'identifier', 'boolean', 'date', 'datetime'])) {
                            $aliasesArray = !empty($f->aliases) ? array_map('trim', explode(',', $f->aliases)) : [];
                            $profile['dimensions'][] = [
                                'name' => count($aliasesArray) > 0 ? $aliasesArray[0] : $f->field_name,
                                'source_column' => $f->field_name,
                                'aliases' => $aliasesArray
                            ];
                        }
                        // Populate date fields
                        if ($needsDateFields && (in_array($f->semantic_type ?? null, ['date', 'datetime']) || str_contains(strtolower($f->data_type ?? ''), 'date'))) {
                            $profile['date_fields'][] = $f->field_name;
                        }
                        // Populate filterable fields
                        if ($needsFilterableFields && in_array($f->semantic_type ?? null, ['dimension', 'categorical', 'status', 'identifier', 'boolean'])) {
                            $profile['filterable_fields'][] = $f->field_name;
                        }
                    }
                }
                
                // If still empty from metadata_fields, try SyncEntityField (legacy fallback)
                if (empty($profile['dimensions'])) {
                    $syncEntity = \App\Models\SyncEntity::where('destination_table', $def->entity_key)->first();
                    if ($syncEntity) {
                        $dimensionDefs = \App\Models\SyncEntityField::where('sync_entity_id', $syncEntity->id)
                            ->where('is_enabled', true)
                            ->whereNotNull('destination_field')
                            ->whereIn('semantic_type', ['dimension', 'categorical', 'status', 'identifier', 'boolean', 'date', 'datetime'])
                            ->get();
                        foreach ($dimensionDefs as $d) {
                            $profile['dimensions'][] = [
                                'name' => $d->label ?? $d->destination_field,
                                'source_column' => $d->destination_field,
                                'aliases' => is_array($d->aliases) ? $d->aliases : []
                            ];
                        }
                    }
                }

                $tables[] = $profile;
            }

            return (object)['tables' => $tables];
        });
    }
}
