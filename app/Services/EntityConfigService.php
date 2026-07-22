<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use App\Services\PermissionGeneratorService;

class EntityConfigService
{
    /**
     * Path to the JSON file that stores entity definitions.
     */
    protected string $filePath;

    public function __construct()
    {
        $this->filePath = storage_path('app/acumatica_entities.json');
        $this->ensureFileExists();
    }

    /**
     * If the JSON file doesn't exist yet, seed it from the current config defaults.
     */
    protected function ensureFileExists(): void
    {
        if (!File::exists($this->filePath)) {
            $defaults = config('acumatica.default_entities', []);
            File::ensureDirectoryExists(dirname($this->filePath));
            File::put($this->filePath, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Get all entities.
     */
    public function getAll(): array
    {
        $content = File::get($this->filePath);
        return json_decode($content, true) ?? [];
    }

    /**
     * Get a single entity by key.
     */
    public function get(string $key): ?array
    {
        $entities = $this->getAll();
        return $entities[$key] ?? null;
    }

    /**
     * Get entities filtered by type.
     */
    public function getByType(string $type): array
    {
        return collect($this->getAll())
            ->filter(fn($e) => $e['type'] === $type)
            ->toArray();
    }

    /**
     * Create or update an entity.
     */
    public function save(string $key, array $data): void
    {
        $entities = $this->getAll();
        $existingFields = $entities[$key]['available_fields'] ?? [];
        
        $entities[$key] = [
            'odata_entity'           => $data['odata_entity'],
            'table'                  => $data['table'],
            'type'                   => $data['type'],
            'icon'                   => $data['icon'] ?? 'database',
            'description'            => $data['description'],
            'source_connection'      => $data['source_connection'] ?? 'acumatica_default',
            'destination_connection' => $data['destination_connection'] ?? 'database_default',
            'schedule_minutes'       => $data['schedule_minutes'] ?? null,
        ];
        
        if (!empty($existingFields)) {
            $entities[$key]['available_fields'] = $existingFields;
        }
        
        $this->writeFile($entities);
        $this->syncPermissions();
    }

    /**
     * Update an existing entity (supports key rename).
     */
    public function update(string $oldKey, string $newKey, array $data): void
    {
        $entities = $this->getAll();
        $existingFields = $entities[$oldKey]['available_fields'] ?? [];

        // Remove old key if renamed
        if ($oldKey !== $newKey && isset($entities[$oldKey])) {
            unset($entities[$oldKey]);
        }

        $entities[$newKey] = [
            'odata_entity'           => $data['odata_entity'],
            'table'                  => $data['table'],
            'type'                   => $data['type'],
            'icon'                   => $data['icon'] ?? 'database',
            'description'            => $data['description'],
            'source_connection'      => $data['source_connection'] ?? 'acumatica_default',
            'destination_connection' => $data['destination_connection'] ?? 'database_default',
            'schedule_minutes'       => $data['schedule_minutes'] ?? null,
        ];
        
        if (!empty($existingFields)) {
            $entities[$newKey]['available_fields'] = $existingFields;
        }
        
        $this->writeFile($entities);
        $this->syncPermissions();
    }
    
    /**
     * Cache available fields for an entity to speed up sync filter modal loading.
     */
    public function updateAvailableFields(string $key, array $fields): void
    {
        $entities = $this->getAll();
        if (isset($entities[$key])) {
            $entities[$key]['available_fields'] = $fields;
            $this->writeFile($entities);
        }
    }

    /**
     * Delete an entity by key.
     */
    public function delete(string $key): bool
    {
        $entities = $this->getAll();
        if (!isset($entities[$key])) {
            return false;
        }
        unset($entities[$key]);
        $this->writeFile($entities);
        $this->syncPermissions();
        return true;
    }

    /**
     * Check if an entity key already exists.
     */
    public function exists(string $key): bool
    {
        return isset($this->getAll()[$key]);
    }

    /**
     * Write entities back to the JSON file.
     */
    protected function writeFile(array $entities): void
    {
        File::put($this->filePath, json_encode($entities, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
            \Illuminate\Support\Facades\Log::warning('Permission sync skipped: ' . $e->getMessage());
        }
    }
}
