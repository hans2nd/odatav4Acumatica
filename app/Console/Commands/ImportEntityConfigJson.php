<?php

namespace App\Console\Commands;

use App\Models\SyncEntity;
use App\Models\SyncEntityField;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportEntityConfigJson extends Command
{
    protected $signature = 'entities:import-json
                            {--seed : Also import default_entities from config as seed data}
                            {--force : Overwrite existing entities instead of skipping}';

    protected $description = 'Import entity configuration from acumatica_entities.json into the sync_entities database table (idempotent)';

    public function handle(): int
    {
        $imported = 0;
        $skipped = 0;
        $rows = [];

        // 1. Import from JSON file if it exists
        $jsonPath = storage_path('app/acumatica_entities.json');
        if (File::exists($jsonPath)) {
            $jsonEntities = json_decode(File::get($jsonPath), true) ?? [];
            $this->info("Found " . count($jsonEntities) . " entities in acumatica_entities.json");

            foreach ($jsonEntities as $key => $config) {
                $result = $this->importEntity($key, $config);
                $rows[] = [$key, $config['odata_entity'] ?? '—', $result];
                $result === 'imported' ? $imported++ : $skipped++;
            }
        } else {
            $this->warn("No acumatica_entities.json found at {$jsonPath} — skipping JSON import.");
        }

        // 2. Optionally import default_entities from config as seed data
        if ($this->option('seed')) {
            $defaults = config('acumatica.default_entities', []);
            $this->info("Seeding " . count($defaults) . " default entities from config.");

            foreach ($defaults as $key => $config) {
                $result = $this->importEntity($key, $config);
                $rows[] = [$key, $config['odata_entity'] ?? '—', $result];
                $result === 'imported' ? $imported++ : $skipped++;
            }
        }

        // 3. Summary
        if (empty($rows)) {
            $this->warn('No entities to import.');
            return self::SUCCESS;
        }

        $this->table(['Entity Key', 'Source Object', 'Status'], $rows);
        $this->newLine();
        $this->info("✅ Imported: {$imported} | Skipped (existing): {$skipped}");

        return self::SUCCESS;
    }

    private function importEntity(string $key, array $config): string
    {
        // Known fields that map to sync_entities columns
        $knownKeys = [
            'odata_entity', 'table', 'type', 'icon', 'description',
            'source_connection', 'destination_connection', 'schedule_minutes',
            'available_fields',
        ];

        // Collect any unmapped properties into extra_attributes
        $extra = [];
        foreach ($config as $k => $v) {
            if (!in_array($k, $knownKeys)) {
                $extra[$k] = $v;
            }
        }

        $existing = SyncEntity::where('code', $key)->first();

        if ($existing && !$this->option('force')) {
            return 'skipped';
        }

        $entity = SyncEntity::updateOrCreate(
            ['code' => $key],
            [
                'name'                      => $config['description'] ?? $key,
                'source_object'             => $config['odata_entity'] ?? $key,
                'destination_table'         => $config['table'] ?? 'acm_' . strtolower($key),
                'type'                      => $config['type'] ?? 'master',
                'icon'                      => $config['icon'] ?? 'database',
                'description'               => $config['description'] ?? null,
                'source_connection_id'      => $config['source_connection'] ?? 'acumatica_default',
                'destination_connection_id' => $config['destination_connection'] ?? 'database_default',
                'schedule_minutes'          => $config['schedule_minutes'] ?? null,
                'is_active'                 => true,
                'sync_mode'                 => 'full',
                'batch_size'                => 2000,
                'extra_attributes'          => !empty($extra) ? $extra : null,
            ]
        );

        // Import available_fields as SyncEntityField rows
        if (!empty($config['available_fields']) && is_array($config['available_fields'])) {
            foreach ($config['available_fields'] as $fieldName) {
                SyncEntityField::updateOrCreate(
                    [
                        'sync_entity_id' => $entity->id,
                        'source_field'   => $fieldName,
                    ],
                    [
                        // Only set defaults on create; don't overwrite existing manual mappings
                    ]
                );
            }
        }

        return 'imported';
    }
}
