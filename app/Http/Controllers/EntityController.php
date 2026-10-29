<?php

namespace App\Http\Controllers;

use App\Services\EntityConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntityController extends Controller
{
    protected EntityConfigService $entityConfig;

    public function __construct(EntityConfigService $entityConfig)
    {
        $this->entityConfig = $entityConfig;
    }

    /**
     * Get all entities as JSON.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'entities' => $this->entityConfig->getAll(),
        ]);
    }

    /**
     * Get a single entity by key.
     */
    public function show(string $key): JsonResponse
    {
        $entity = $this->entityConfig->get($key);
        if (!$entity) {
            return response()->json(['success' => false, 'message' => "Entity '{$key}' not found."], 404);
        }

        return response()->json([
            'success' => true,
            'key'     => $key,
            'entity'  => $entity,
        ]);
    }

    /**
     * Create a new entity.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key'                    => 'required|string|max:100|regex:/^[A-Za-z][A-Za-z0-9]*$/',
            'odata_entity'           => 'required|string|max:255',
            'table'                  => 'required|string|max:255|regex:/^[a-z][a-z0-9_]*$/',
            'type'                   => 'required|in:master,transaction',
            'icon'                   => 'nullable|string|max:100',
            'description'            => 'required|string|max:255',
            'source_connection'      => 'nullable|string|max:100|exists:connections,key',
            'destination_connection' => 'nullable|string|max:100|exists:connections,key',
            'sync_mode'              => 'required|in:full,incremental,backdate',
            'watermark_type'         => 'nullable|required_if:sync_mode,incremental|required_if:sync_mode,backdate|string|max:50',
            'watermark_column'       => 'nullable|required_if:sync_mode,incremental|required_if:sync_mode,backdate|string|max:100',
            'batch_size'             => 'required|integer|min:1|max:100000',
            'is_active'              => 'boolean',
        ]);

        $key = $validated['key'];

        if ($this->entityConfig->exists($key)) {
            return response()->json([
                'success' => false,
                'message' => "Entity key '{$key}' already exists.",
            ], 422);
        }

        $this->entityConfig->save($key, $validated);
        $this->clearEntityConnectionMetadataCache(
            $validated['source_connection'] ?? null,
            $validated['destination_connection'] ?? null,
            $validated['table'] ?? null
        );

        \App\Jobs\DiscoverEntityMetadataJob::dispatch($key);

        return response()->json([
            'success' => true,
            'message' => "Entity '{$key}' created successfully.",
            'entity'  => $this->entityConfig->get($key),
        ]);
    }

    /**
     * Update an existing entity.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        if (!$this->entityConfig->exists($key)) {
            return response()->json([
                'success' => false,
                'message' => "Entity '{$key}' not found.",
            ], 404);
        }

        $validated = $request->validate([
            'key'                    => 'required|string|max:100|regex:/^[A-Za-z][A-Za-z0-9]*$/',
            'odata_entity'           => 'required|string|max:255',
            'table'                  => 'required|string|max:255|regex:/^[a-z][a-z0-9_]*$/',
            'type'                   => 'required|in:master,transaction',
            'icon'                   => 'nullable|string|max:100',
            'description'            => 'required|string|max:255',
            'source_connection'      => 'nullable|string|max:100|exists:connections,key',
            'destination_connection' => 'nullable|string|max:100|exists:connections,key',
            'schedule_minutes'       => 'nullable|integer|min:1',
            'sync_mode'              => 'required|in:full,incremental,backdate',
            'watermark_type'         => 'nullable|required_if:sync_mode,incremental|required_if:sync_mode,backdate|string|max:50',
            'watermark_column'       => 'nullable|required_if:sync_mode,incremental|required_if:sync_mode,backdate|string|max:100',
            'batch_size'             => 'required|integer|min:1|max:100000',
            'is_active'              => 'boolean',
        ]);

        $newKey = $validated['key'];

        // Check if new key conflicts with another entity
        if ($newKey !== $key && $this->entityConfig->exists($newKey)) {
            return response()->json([
                'success' => false,
                'message' => "Entity key '{$newKey}' already exists.",
            ], 422);
        }

        $oldEntity = $this->entityConfig->get($key);
        $this->entityConfig->update($key, $newKey, $validated);
        if ($oldEntity) {
            $this->clearEntityConnectionMetadataCache(
                $oldEntity['source_connection'] ?? null,
                $oldEntity['destination_connection'] ?? null,
                $oldEntity['table'] ?? null
            );
        }
        $this->clearEntityConnectionMetadataCache(
            $validated['source_connection'] ?? null,
            $validated['destination_connection'] ?? null,
            $validated['table'] ?? null
        );

        \App\Jobs\DiscoverEntityMetadataJob::dispatch($newKey);

        return response()->json([
            'success' => true,
            'message' => "Entity '{$newKey}' updated successfully.",
            'entity'  => $this->entityConfig->get($newKey),
        ]);
    }

    /**
     * Update an entity's schedule.
     */


    /**
     * Get an entity's field mappings.
     */
    public function getMappings(string $key): JsonResponse
    {
        $entity = $this->entityConfig->getModel($key);
        
        if (!$entity) {
            return response()->json([
                'success' => false,
                'message' => "Entity '{$key}' not found.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'fields' => $entity->fields,
        ]);
    }

    /**
     * Update an entity's field mappings.
     */
    public function updateMappings(Request $request, string $key): JsonResponse
    {
        $entity = $this->entityConfig->getModel($key);
        
        if (!$entity) {
            return response()->json([
                'success' => false,
                'message' => "Entity '{$key}' not found.",
            ], 404);
        }

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.source_field' => 'required|string|max:255',
            'fields.*.destination_field' => 'nullable|string|max:255',
            'fields.*.data_type' => 'nullable|string|max:100',
            'fields.*.semantic_type' => 'nullable|string|max:100',
            'fields.*.is_key' => 'boolean',
            'fields.*.is_nullable' => 'boolean',
            'fields.*.transform_rule' => 'nullable|string',
            'fields.*.is_enabled' => 'boolean',
            'fields.*.label' => 'nullable|string|max:150',
            'fields.*.description' => 'nullable|string',
            'fields.*.aliases' => 'nullable|array',
            'fields.*.aliases.*' => 'string|max:100',
        ]);

        // Validation: require at least one key field if incremental
        if ($entity->sync_mode === 'incremental') {
            $hasKey = collect($validated['fields'])
                ->where('is_key', true)
                ->where('is_enabled', true)
                ->isNotEmpty();
                
            if (!$hasKey) {
                return response()->json([
                    'success' => false,
                    'message' => "Incremental sync mode requires at least one enabled key field.",
                ], 422);
            }
        }

        $this->entityConfig->updateFields($entity->id, $validated['fields']);

        // Explicit Schema Generation on Save
        try {
            $entity->load('fields'); // Refresh the relationship so the new fields are loaded
            $config = $entity->toConfigArray();
            $destConnection = $config['destination_connection'] ?? 'database_default';
            $tableName = $config['table'];
            
            // toConfigArray() will load fields, so we can use it to get mappings
            $fieldMappings = $config['field_mappings'] ?? [];
            if (!empty($fieldMappings) && $tableName) {
                $schemaManager = app(\App\Services\SchemaManager::class);
                $schemaManager->ensureTableFromMappings($destConnection, $tableName, $fieldMappings);
            }
            $this->clearEntityConnectionMetadataCache(
                $config['source_connection'] ?? null,
                $destConnection,
                $tableName
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to generate strict schema for {$key}: " . $e->getMessage());
            // We don't fail the request here, but log it so the user can be warned or it can be retried during sync.
        }

        // Auto-refresh AI schema cache to reflect Semantic Type changes for Suggest Semantics
        try {
            $connectionId = \App\Models\AiSetting::getConfig('analytics_connection_id');
            if ($connectionId) {
                $connection = \App\Models\Connection::find($connectionId);
                if ($connection && $connection->is_analytics_source) {
                    $schemaCacheService = app(\App\Services\AI\SchemaCacheService::class);
                    $schemaCacheService->cacheSchema($connection);
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Auto-refresh schema failed after updating mappings: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "Entity '{$key}' fields updated successfully.",
        ]);
    }

    /**
     * Trigger metadata discovery manually.
     */
    public function discover(string $key): JsonResponse
    {
        if (!$this->entityConfig->exists($key)) {
            return response()->json([
                'success' => false,
                'message' => "Entity '{$key}' not found.",
            ], 404);
        }

        \App\Jobs\DiscoverEntityMetadataJob::dispatchSync($key);

        return response()->json([
            'success' => true,
            'message' => "Metadata discovery completed for '{$key}'.",
        ]);
    }

    /**
     * AI Suggest Fields for Manage Fields mapping
     */
    public function suggestFields(string $key): JsonResponse
    {
        $entity = $this->entityConfig->getModel($key);
        if (!$entity) {
            return response()->json(['success' => false, 'message' => "Entity '{$key}' not found."], 404);
        }

        $fields = \App\Models\SyncEntityField::where('sync_entity_id', $entity->id)->get();
        if ($fields->isEmpty()) {
            return response()->json(['success' => false, 'message' => "No fields found to suggest."], 400);
        }

        $fieldData = $fields->map(fn($f) => [
            'name' => $f->source_field,
            'current_data_type' => $f->data_type,
            'current_semantic_type' => $f->semantic_type,
        ])->toArray();

        try {
            $openRouter = app(\App\Services\AI\OpenRouterClient::class);
            $prompt = [
                ['role' => 'system', 'content' => 'You are an expert Data Architect. Given a database table schema, you will suggest useful data types, semantic types, aliases, and labels for the fields. Generate a strictly valid JSON response.'],
                ['role' => 'user', 'content' => "Table Name: {$entity->destination_table}\n\nFields:\n" . json_encode($fieldData) . "\n\nProvide your suggestions strictly as a JSON object where the keys are the field names and the values are objects containing data_type (string, number, datetime, boolean), semantic_type (dimension, metric, datetime, identifier, amount, status), label, description, and aliases (comma separated string).\nExample output:\n{\n  \"Amount\": {\n    \"data_type\": \"number\",\n    \"semantic_type\": \"amount\",\n    \"label\": \"Total Amount\",\n    \"description\": \"The total amount of the transaction\",\n    \"aliases\": \"total, sum, revenue\"\n  }\n}"]
            ];

            $response = $openRouter->getCompletion($prompt, true);
            $parsed = json_decode($response, true);

            if (is_array($parsed)) {
                return response()->json([
                    'success' => true,
                    'suggestions' => $parsed
                ]);
            }
            
            throw new \Exception("Invalid JSON format returned from LLM.");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("LLM Suggest Fields failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'AI Suggestion failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an entity.
     */
    public function destroy(string $key): JsonResponse
    {
        $oldEntity = $this->entityConfig->get($key);
        if (!$this->entityConfig->delete($key)) {
            return response()->json([
                'success' => false,
                'message' => "Entity '{$key}' not found.",
            ], 404);
        }

        if ($oldEntity) {
            $this->clearEntityConnectionMetadataCache(
                $oldEntity['source_connection'] ?? null,
                $oldEntity['destination_connection'] ?? null,
                $oldEntity['table'] ?? null
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Entity '{$key}' deleted successfully.",
        ]);
    }

    /**
     * Batch delete entities.
     */
    public function batchDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keys'   => 'required|array|min:1',
            'keys.*' => 'string|max:100',
        ]);

        $keys = array_values(array_unique($validated['keys']));

        try {
            $entitiesToClear = [];
            foreach ($keys as $k) {
                if ($ent = $this->entityConfig->get($k)) {
                    $entitiesToClear[] = $ent;
                }
            }

            if (!$this->entityConfig->batchDelete($keys)) {
                return response()->json([
                    'success' => false,
                    'message' => "Failed to delete the selected entity/entities.",
                ], 422);
            }

            foreach ($entitiesToClear as $ent) {
                $this->clearEntityConnectionMetadataCache(
                    $ent['source_connection'] ?? null,
                    $ent['destination_connection'] ?? null,
                    $ent['table'] ?? null
                );
            }

            return response()->json([
                'success' => true,
                'message' => count($keys) . " entity/entities deleted successfully.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Error deleting entities: " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Invalidate cached connection and table metadata when entity configuration or schema changes.
     */
    protected function clearEntityConnectionMetadataCache(?string $sourceConn, ?string $destConn, ?string $tableName = null): void
    {
        if ($sourceConn) {
            \Illuminate\Support\Facades\Cache::forget("connection_{$sourceConn}_metadata");
        }
        if ($destConn) {
            \Illuminate\Support\Facades\Cache::forget("connection_{$destConn}_metadata");
            if ($tableName) {
                \Illuminate\Support\Facades\Cache::forget("connection_{$destConn}_structure_" . md5($tableName));
                \Illuminate\Support\Facades\Cache::forget("sync_metadata_table_{$destConn}_{$tableName}");
            }
        }
    }
}
