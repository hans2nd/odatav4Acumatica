<?php

namespace App\Services\Metadata\Providers;

use App\Contracts\Metadata\MetadataProviderInterface;
use App\DTO\Metadata\MetadataEntity;
use App\DTO\Metadata\MetadataField;
use App\Services\AcumaticaODataService;

class AcumaticaMetadataProvider implements MetadataProviderInterface
{
    public function __construct(
        private AcumaticaODataService $odataService,
        private \App\Services\ConnectionService $connectionService,
        private \App\Services\Connections\ConnectionManager $connectionManager
    ) {}

    public function fetchMetadata(string $entityKey): MetadataEntity
    {
        $config = config("acumatica.entities.{$entityKey}");
        if (!$config) {
            throw new \RuntimeException("Entity configuration not found for: {$entityKey}");
        }

        // Fast fallback 1: Config (from previous executions)
        if (!empty($config['available_fields'])) {
            $fields = [];
            foreach ($config['available_fields'] as $fieldName) {
                $fields[] = new MetadataField(name: $fieldName);
            }
            return new MetadataEntity(entityKey: $entityKey, fields: $fields, providerVersion: 'ConfigCache');
        }

        // Fast fallback 2: Local Database (Destination Table)
        try {
            $tableName = $config['table'];
            $connName = $config['destination_connection'] ?? 'database_default';
            $firstRow = \Illuminate\Support\Facades\DB::connection($connName)->table($tableName)->first();
            if ($firstRow) {
                $columns = array_keys((array)$firstRow);
            } else {
                $node = clone $this->connectionManager->discoverStructure($connName, $tableName);
                $columns = [];
                foreach ($node->properties as $prop) {
                    $columns[] = is_array($prop) ? ($prop['name'] ?? $prop['Field'] ?? '') : (string)$prop;
                }
                $columns = array_filter($columns);
            }
            
            if (!empty($columns)) {
                $dbColumns = array_values(array_filter($columns, fn($col) => !in_array(strtolower($col), ['_sync_id', '_synced_at', '_row_hash'])));
                if (!empty($dbColumns)) {
                    $fields = [];
                    foreach ($dbColumns as $col) {
                        $fields[] = new MetadataField(name: $col);
                    }
                    return new MetadataEntity(entityKey: $entityKey, fields: $fields, providerVersion: 'LocalDB');
                }
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, proceed to Acumatica
        }

        // Fallback 3: Acumatica OData
        $odataEntity = $config['odata_entity'];
        $sourceConnName = $config['source_connection'] ?? 'acumatica_default';
        $sourceConnection = $this->connectionService->get($sourceConnName);
        
        if ($sourceConnection) {
            $this->odataService->setConnectionConfig($sourceConnection);
        }

        if ($this->odataService->usesSessionAuth()) {
            $this->odataService->login();
        }

        // Fetch 1 record to extract schema
        $result = $this->odataService->fetchPage($odataEntity, 1, 0, '', false);
        
        if ($this->odataService->usesSessionAuth()) {
            $this->odataService->logout();
        }
        
        $fields = [];
        if (!empty($result['value']) && is_array($result['value'])) {
            $sampleRow = $result['value'][0];
            foreach ($sampleRow as $key => $value) {
                if ($key === '@odata.id' || $key === '@odata.editLink' || str_contains($key, '@')) {
                    continue; // Skip OData navigational metadata
                }

                $dataType = 'string';
                if (is_int($value)) $dataType = 'integer';
                if (is_float($value)) $dataType = 'float';
                if (is_bool($value)) $dataType = 'boolean';
                
                $isPrimaryKey = (strcasecmp($key, 'id') === 0 || preg_match('/ID$/i', $key));

                $fields[] = new MetadataField(
                    name: $key,
                    dataType: $dataType,
                    isPrimaryKey: $isPrimaryKey,
                    isNullable: true
                );
            }
        } elseif (!empty($result['data']) && is_array($result['data'])) {
             $sampleRow = $result['data'][0];
             foreach ($sampleRow as $key => $value) {
                if ($key === '@odata.id' || $key === '@odata.editLink' || str_contains($key, '@')) {
                    continue;
                }
                $fields[] = new MetadataField(name: $key);
             }
        } else {
            // Ensure at least CompanyID is available to avoid breaking the UI
            $fields[] = new MetadataField(name: 'CompanyID');
        }

        // Always ensure CompanyID exists for Acumatica
        $hasCompanyId = false;
        foreach ($fields as $f) {
            if (strcasecmp($f->name, 'CompanyID') === 0) $hasCompanyId = true;
        }
        if (!$hasCompanyId) {
            $fields[] = new MetadataField(name: 'CompanyID');
        }

        // Update config cache for future instant loads
        $fieldNames = array_map(fn($f) => $f->name, $fields);
        app(\App\Services\EntityConfigService::class)->updateAvailableFields($entityKey, $fieldNames);

        return new MetadataEntity(
            entityKey: $entityKey,
            fields: $fields,
            providerVersion: 'ODataV4'
        );
    }
}
