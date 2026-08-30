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
        $config = app(\App\Services\EntityConfigService::class)->get($entityKey);
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

        // Fetch EDMX metadata to accurately resolve semantic_type
        $fields = [];
        try {
            $xmlString = $this->odataService->fetchMetadataXml();
            $xml = simplexml_load_string($xmlString);
            if ($xml !== false) {
                $xml->registerXPathNamespace('edmx', 'http://docs.oasis-open.org/odata/ns/edmx');
                $xml->registerXPathNamespace('edm', 'http://docs.oasis-open.org/odata/ns/edm');

                // Find the EntityType matching the OData Entity name
                $entityTypes = $xml->xpath("//edm:EntityType[@Name='{$odataEntity}']");
                
                if (!empty($entityTypes)) {
                    $entityNode = $entityTypes[0];
                    $properties = $entityNode->xpath("edm:Property");

                    foreach ($properties as $prop) {
                        $name = (string)$prop['Name'];
                        $type = (string)$prop['Type'];
                        $isNullable = (string)$prop['Nullable'] !== 'false';
                        $isPrimaryKey = false; // Primary keys are under <Key><PropertyRef/></Key> but omitting for brevity if not strictly needed

                        $semanticType = null;
                        if (in_array($type, ['Edm.DateTimeOffset', 'Edm.DateTime'])) {
                            $semanticType = 'datetime';
                        } elseif ($type === 'Edm.Date') {
                            $semanticType = 'date';
                        } elseif (in_array($type, ['Edm.Int32', 'Edm.Int64', 'Edm.Decimal', 'Edm.Double', 'Edm.Single'])) {
                            $semanticType = 'number';
                        } elseif ($type === 'Edm.Boolean') {
                            $semanticType = 'boolean';
                        } else {
                            $semanticType = 'string';
                        }

                        $fields[] = new MetadataField(
                            name: $name,
                            dataType: $semanticType === 'string' ? 'text' : $semanticType,
                            isPrimaryKey: $isPrimaryKey,
                            isNullable: $isNullable,
                            semanticType: $semanticType
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Acumatica OData: Failed to parse \$metadata XML for {$odataEntity}. Falling back to fetchPage.", ['error' => $e->getMessage()]);
        }

        // Fallback: If XML parsing failed or entity was not found in XML, use fetchPage
        if (empty($fields)) {
            // Fetch 1 record to extract schema
            $result = $this->odataService->fetchPage($odataEntity, 1, 0, '', false);
            
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
                        isNullable: true,
                        semanticType: $dataType === 'integer' || $dataType === 'float' ? 'number' : ($dataType === 'boolean' ? 'boolean' : 'string')
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
        }

        if ($this->odataService->usesSessionAuth()) {
            $this->odataService->logout();
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
