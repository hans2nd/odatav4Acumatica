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
            'source_connection'      => 'nullable|string|max:100',
            'destination_connection' => 'nullable|string|max:100',
        ]);

        $key = $validated['key'];

        if ($this->entityConfig->exists($key)) {
            return response()->json([
                'success' => false,
                'message' => "Entity key '{$key}' already exists.",
            ], 422);
        }

        $this->entityConfig->save($key, $validated);

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
            'source_connection'      => 'nullable|string|max:100',
            'destination_connection' => 'nullable|string|max:100',
            'schedule_minutes'       => 'nullable|integer|min:1',
        ]);

        $newKey = $validated['key'];

        // Check if new key conflicts with another entity
        if ($newKey !== $key && $this->entityConfig->exists($newKey)) {
            return response()->json([
                'success' => false,
                'message' => "Entity key '{$newKey}' already exists.",
            ], 422);
        }

        $this->entityConfig->update($key, $newKey, $validated);

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
     * Delete an entity.
     */
    public function destroy(string $key): JsonResponse
    {
        if (!$this->entityConfig->delete($key)) {
            return response()->json([
                'success' => false,
                'message' => "Entity '{$key}' not found.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => "Entity '{$key}' deleted successfully.",
        ]);
    }
}
