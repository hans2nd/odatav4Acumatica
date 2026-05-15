<?php

namespace App\Services;

use App\Services\PermissionSyncService;
use Illuminate\Support\Facades\File;

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
        $entities[$key] = [
            'odata_entity' => $data['odata_entity'],
            'table'        => $data['table'],
            'type'         => $data['type'],
            'icon'         => $data['icon'] ?? 'database',
            'description'  => $data['description'],
        ];
        $this->writeFile($entities);
        $this->syncPermissions();
    }

    /**
     * Update an existing entity (supports key rename).
     */
    public function update(string $oldKey, string $newKey, array $data): void
    {
        $entities = $this->getAll();

        // Remove old key if renamed
        if ($oldKey !== $newKey && isset($entities[$oldKey])) {
            unset($entities[$oldKey]);
        }

        $entities[$newKey] = [
            'odata_entity' => $data['odata_entity'],
            'table'        => $data['table'],
            'type'         => $data['type'],
            'icon'         => $data['icon'] ?? 'database',
            'description'  => $data['description'],
        ];
        $this->writeFile($entities);
        $this->syncPermissions();
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
            app(PermissionSyncService::class)->syncFromEntities();
        } catch (\Throwable $e) {
            // Silently fail — permissions table may not exist yet (pre-migration)
            \Illuminate\Support\Facades\Log::warning('Permission sync skipped: ' . $e->getMessage());
        }
    }
}
