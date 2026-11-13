<?php

declare(strict_types=1);

namespace App\Services\Connections\Providers;

use App\Contracts\Connections\ConnectionProviderInterface;
use App\DTO\Connections\ConfigurationField;
use App\DTO\Connections\ConnectionHealth;
use App\DTO\Connections\ConnectionHealthStatus;
use App\DTO\Connections\MetadataNode;
use App\DTO\Connections\MetadataTree;
use App\DTO\Connections\ProviderInfo;
use PDO;
use Throwable;

class SqlServerProvider implements ConnectionProviderInterface
{
    public function getConfigurationSchema(): array
    {
        return [
            new ConfigurationField('host', 'Server Host', 'text', true, '127.0.0.1'),
            new ConfigurationField('port', 'Port', 'number', true, 1433),
            new ConfigurationField('database', 'Database Name', 'text', true),
            new ConfigurationField('username', 'Username', 'text', true),
            new ConfigurationField('password', 'Password', 'password', true),
            new ConfigurationField('encrypt', 'Encrypt Connection', 'boolean', false, true, null, 'Enable SSL encryption.'),
            new ConfigurationField('trust_server_certificate', 'Trust Server Certificate', 'boolean', false, true),
        ];
    }

    public function getValidationRules(): array
    {
        return [
            'credentials.host'                     => 'required|string',
            'credentials.port'                     => 'required|numeric',
            'credentials.database'                 => 'required|string',
            'credentials.username'                 => 'required|string',
            'credentials.password'                 => 'required|string',
            'credentials.encrypt'                  => 'nullable|boolean',
            'credentials.trust_server_certificate' => 'nullable|boolean',
        ];
    }


    public function __construct(
        private readonly \App\Services\Connections\Providers\Mappers\SqlServerErrorMapper $errorMapper,
        private readonly \App\Services\Connections\Providers\Testers\SqlServerConnectionTester $tester
    ) {}

    public function testConnection(array $credentials): ConnectionHealth
    {
        if (!extension_loaded('pdo_sqlsrv')) {
            return new ConnectionHealth(ConnectionHealthStatus::ERROR_DRIVER_MISSING, 'sqlsrv extension missing.');
        }

        $start = microtime(true);
        try {
            $this->createRuntimeConnection($credentials);
            $elapsed = (int) round((microtime(true) - $start) * 1000);
            return new ConnectionHealth(ConnectionHealthStatus::HEALTHY, 'Connected successfully', $elapsed);
        } catch (\Throwable $e) {
            $error = $this->errorMapper->map($e);
            
            // Map the mapped code back to a ConnectionHealthStatus for the fast test
            $status = match ($error->code) {
                'INVALID_CREDENTIALS', 'LOGIN_FAILED', 'WINDOWS_AUTH_REQUIRED' => ConnectionHealthStatus::ERROR_AUTH_FAILED,
                'DRIVER_CONFIGURATION_ERROR' => ConnectionHealthStatus::ERROR_DRIVER_MISSING,
                default => ConnectionHealthStatus::ERROR_UNREACHABLE,
            };

            return new ConnectionHealth($status, $error->message, null, [], [], [], $error);
        }
    }

    public function runDiagnostics(array $credentials): ConnectionHealth
    {
        return $this->tester->runDiagnostics($this, $credentials);
    }

    public function getDiagnosticInfo(array $credentials): ProviderInfo
    {
        try {
            $pdo = $this->createRuntimeConnection($credentials);
            $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $clientVersion = $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION);
            return new ProviderInfo('SQL Server', $clientVersion, $serverVersion);
        } catch (Throwable $e) {
            return new ProviderInfo('SQL Server');
        }
    }

    public function discoverMetadata(array $credentials): MetadataTree
    {
        $pdo = $this->createRuntimeConnection($credentials);
        $stmt = $pdo->query("SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE IN ('BASE TABLE', 'VIEW') ORDER BY TABLE_TYPE, TABLE_SCHEMA, TABLE_NAME");
        
        $nodes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $type = $row['TABLE_TYPE'] === 'VIEW' ? 'view' : 'table';
            $name = isset($row['TABLE_SCHEMA']) && $row['TABLE_SCHEMA'] !== 'dbo' 
                ? $row['TABLE_SCHEMA'] . '.' . $row['TABLE_NAME'] 
                : $row['TABLE_NAME'];
            $nodes[] = new MetadataNode(name: $name, type: $type);
        }

        // Also discover Stored Procedures (excluding internal completion callbacks and diagram helper SPs)
        $excludedProcedures = [
            'usp_completeincrementalsync',
            'sp_upgraddiagrams',
            'sp_helpdiagrams',
            'sp_helpdiagramdefinition',
            'sp_creatediagram',
            'sp_renamediagram',
            'sp_alterdiagram',
            'sp_dropdiagram',
        ];
        try {
            $procStmt = $pdo->query("SELECT SCHEMA_NAME(schema_id) AS ROUTINE_SCHEMA, name AS ROUTINE_NAME FROM sys.procedures WHERE is_ms_shipped = 0 ORDER BY name");
            while ($row = $procStmt->fetch(PDO::FETCH_ASSOC)) {
                $schema = $row['ROUTINE_SCHEMA'] ?? 'dbo';
                $procName = $row['ROUTINE_NAME'] ?? '';
                if (!$procName || in_array(strtolower($procName), $excludedProcedures, true)) {
                    continue;
                }
                $fullName = ($schema !== 'dbo') ? "{$schema}.{$procName}" : $procName;
                $nodes[] = new MetadataNode(name: $fullName, type: 'procedure');
            }
        } catch (Throwable $e) {
            try {
                $procStmt = $pdo->query("SELECT ROUTINE_SCHEMA, ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_TYPE = 'PROCEDURE' ORDER BY ROUTINE_NAME");
                while ($row = $procStmt->fetch(PDO::FETCH_ASSOC)) {
                    $schema = $row['ROUTINE_SCHEMA'] ?? 'dbo';
                    $procName = $row['ROUTINE_NAME'] ?? '';
                    if (!$procName || in_array(strtolower($procName), $excludedProcedures, true)) {
                        continue;
                    }
                    $fullName = ($schema !== 'dbo') ? "{$schema}.{$procName}" : $procName;
                    $nodes[] = new MetadataNode(name: $fullName, type: 'procedure');
                }
            } catch (Throwable $inner) {
                // Ignore if user lacks permission to list routines
            }
        }

        return new MetadataTree($nodes);
    }

    public function discoverStructure(array $credentials, string $objectName): MetadataNode
    {
        $pdo = $this->createRuntimeConnection($credentials);

        $parsed = $this->parseSqlObjectIdentifier($objectName);
        $properties = $this->queryColumnsFromInformationSchema(
            $pdo,
            $parsed['database'],
            $parsed['schema'],
            $parsed['object']
        );

        $nodeType = 'table';

        // If no columns found in INFORMATION_SCHEMA.COLUMNS, check if it is a Stored Procedure
        if (empty($properties)) {
            $spResult = $this->discoverStoredProcedureColumns($pdo, $objectName);
            $properties = $spResult['columns'];
            if (!empty($properties) || $spResult['is_procedure']) {
                $nodeType = 'procedure';
            }
        }

        return new MetadataNode(
            name: $objectName,
            type: $nodeType,
            properties: $properties,
            isQueryable: true
        );
    }

    /**
     * Parse 1-part, 2-part, or 3-part SQL Server identifier (e.g. [DB_Acumatica_Prod].dbo.vw_UsrSyncSalesOrderInvoice).
     *
     * @return array{database: ?string, schema: ?string, object: string}
     */
    public function parseSqlObjectIdentifier(string $rawIdentifier): array
    {
        $clean = trim($rawIdentifier);
        // Split by dot outside brackets or strip brackets from each segment
        $parts = preg_split('/\.(?=(?:[^\]]*\[[^\]]*\])*[^\]]*$)/', $clean) ?: [$clean];
        $parts = array_map(fn($p) => trim($p, " \t\n\r\0\x0B[]"), $parts);
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));

        if (count($parts) >= 3) {
            return [
                'database' => $parts[0],
                'schema'   => $parts[1],
                'object'   => $parts[2],
            ];
        }

        if (count($parts) === 2) {
            return [
                'database' => null,
                'schema'   => $parts[0],
                'object'   => $parts[1],
            ];
        }

        return [
            'database' => null,
            'schema'   => null,
            'object'   => $parts[0] ?? $clean,
        ];
    }

    /**
     * Query column list from INFORMATION_SCHEMA.COLUMNS (supports cross-database 3-part names).
     */
    private function queryColumnsFromInformationSchema(PDO $pdo, ?string $database, ?string $schema, string $object): array
    {
        $objectEscaped = str_replace("'", "''", $object);
        $schemaClause = '';
        if ($schema !== null && $schema !== '') {
            $schemaEscaped = str_replace("'", "''", $schema);
            $schemaClause = "TABLE_SCHEMA = '{$schemaEscaped}' AND ";
        }

        $infoSchemaPrefix = '';
        if ($database !== null && $database !== '') {
            $dbSafe = str_replace(']', ']]', $database);
            $infoSchemaPrefix = "[{$dbSafe}].";
        }

        $query = "SELECT COLUMN_NAME, DATA_TYPE FROM {$infoSchemaPrefix}INFORMATION_SCHEMA.COLUMNS WHERE {$schemaClause}TABLE_NAME = '{$objectEscaped}' ORDER BY ORDINAL_POSITION";

        try {
            $stmt = $pdo->query($query);
            $properties = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $properties[] = [
                    'name' => $row['COLUMN_NAME'],
                    'type' => $row['DATA_TYPE'],
                ];
            }
            return $properties;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Discover output columns and metadata for a SQL Server Stored Procedure.
     *
     * @return array{is_procedure: bool, columns: array, sp_info: array}
     */
    private function discoverStoredProcedureColumns(PDO $pdo, string $objectName): array
    {
        $spInfo = $this->inspectStoredProcedureWithPdo($pdo, $objectName);
        $columns = [];

        // 1. If the SP references a target View/Table in its final SELECT (e.g. [DB_Acumatica_Prod].dbo.vw_UsrSyncSalesOrderInvoice),
        //    resolve the columns from that View/Table first (essential when SP uses #TempTables and returns multiple result sets).
        if (!empty($spInfo['schema_object'])) {
            $parsedRef = $this->parseSqlObjectIdentifier($spInfo['schema_object']);
            $columns = $this->queryColumnsFromInformationSchema(
                $pdo,
                $parsedRef['database'],
                $parsedRef['schema'],
                $parsedRef['object']
            );
        }

        // 2. Fallback: Use sys.dm_exec_describe_first_result_set_for_object for standard single-result-set SPs
        if (empty($columns) && $spInfo['is_procedure']) {
            try {
                $qualified = $spInfo['qualified_name'];
                $QualifiedEscaped = str_replace("'", "''", $qualified);
                $stmt = $pdo->query("
                    SELECT name AS COLUMN_NAME, system_type_name AS DATA_TYPE
                    FROM sys.dm_exec_describe_first_result_set_for_object(OBJECT_ID('{$QualifiedEscaped}'), 0)
                    WHERE is_hidden = 0 AND name IS NOT NULL
                    ORDER BY column_ordinal
                ");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rawType = (string) ($row['DATA_TYPE'] ?? 'nvarchar');
                    // Strip precision/length e.g. decimal(18,6) -> decimal
                    $baseType = strtolower(preg_replace('/\(.*\)$/', '', $rawType));
                    $columns[] = [
                        'name' => $row['COLUMN_NAME'],
                        'type' => $baseType,
                    ];
                }
            } catch (Throwable $e) {
                // Ignore if SP uses temp tables that prevent static first-result-set description
            }
        }

        return [
            'is_procedure' => $spInfo['is_procedure'],
            'columns'      => $columns,
            'sp_info'      => $spInfo,
        ];
    }

    /**
     * Inspect a Stored Procedure definition and parameters to auto-detect Two-Phase Delta SP configuration.
     */
    public function inspectStoredProcedure(array $credentials, string $objectName): array
    {
        $pdo = $this->createRuntimeConnection($credentials);
        return $this->inspectStoredProcedureWithPdo($pdo, $objectName);
    }

    private function inspectStoredProcedureWithPdo(PDO $pdo, string $objectName): array
    {
        $parsed = $this->parseSqlObjectIdentifier($objectName);
        $schema = $parsed['schema'] ?: 'dbo';
        $proc = $parsed['object'];

        // If user accidentally passed the completion SP (usp_CompleteIncrementalSync),
        // redirect inspection to the corresponding data-fetching SP (e.g. usp_GetIncrementalSalesOrderSync).
        if (strcasecmp($proc, 'usp_CompleteIncrementalSync') === 0) {
            try {
                $getStmt = $pdo->query("SELECT TOP 1 SCHEMA_NAME(schema_id) AS sname, name FROM sys.procedures WHERE name LIKE 'usp_GetIncremental%' ORDER BY name");
                if ($getRow = $getStmt->fetch(PDO::FETCH_ASSOC)) {
                    $schema = $getRow['sname'] ?: 'dbo';
                    $proc = $getRow['name'];
                }
            } catch (Throwable $e) {
            }
        }

        $qualified = "{$schema}.{$proc}";
        $qualifiedEscaped = str_replace("'", "''", $qualified);

        $info = [
            'is_procedure'      => false,
            'qualified_name'    => $qualified,
            'execution_mode'    => 'single_result_set',
            'sync_process_name' => null,
            'company_id'        => null,
            'completion_proc'   => null,
            'schema_object'     => null,
            'delete_keys'       => null,
            'parameters'        => [],
        ];

        try {
            $stmt = $pdo->query("
                SELECT
                    o.type_desc,
                    OBJECT_DEFINITION(o.object_id) AS definition
                FROM sys.objects o
                WHERE o.object_id = OBJECT_ID('{$qualifiedEscaped}')
                  AND o.type IN ('P', 'PC')
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return $info;
            }

            $info['is_procedure'] = true;
            $definition = (string) ($row['definition'] ?? '');

            // Fetch parameters
            $paramStmt = $pdo->query("
                SELECT p.name, TYPE_NAME(p.user_type_id) AS type_name, p.is_output
                FROM sys.parameters p
                WHERE p.object_id = OBJECT_ID('{$qualifiedEscaped}')
                ORDER BY p.parameter_id
            ");
            $paramNamesLower = [];
            while ($pRow = $paramStmt->fetch(PDO::FETCH_ASSOC)) {
                $pName = (string) ($pRow['name'] ?? '');
                $info['parameters'][] = [
                    'name'      => $pName,
                    'type'      => $pRow['type_name'] ?? '',
                    'is_output' => (bool) ($pRow['is_output'] ?? false),
                ];
                $paramNamesLower[strtolower($pName)] = (bool) ($pRow['is_output'] ?? false);
            }

            // Detect Two-Phase Delta SP signature (@BatchLogID OUTPUT & @NewWatermark OUTPUT)
            if (isset($paramNamesLower['@batchlogid']) && isset($paramNamesLower['@newwatermark'])) {
                $info['execution_mode'] = 'two_phase_delta';
            }

            if ($definition !== '') {
                // Extract default @SyncProcessName
                if (preg_match('/@SyncProcessName\s+[A-Za-z0-9_()]+(?:\s*=\s*N?\'([^\']+)\')/i', $definition, $m)) {
                    $info['sync_process_name'] = $m[1];
                }
                // Extract default @CompanyID
                if (preg_match('/@CompanyID\s+INT(?:\s*=\s*(\d+))/i', $definition, $m)) {
                    $info['company_id'] = (int) $m[1];
                }
                // Extract delete keys from SELECT ... FROM #ChangedOrders
                if (preg_match('/SELECT\s+([^\r\n;]+?)\s+FROM\s+#ChangedOrders/i', $definition, $m)) {
                    $rawCols = array_map('trim', explode(',', $m[1]));
                    $cleanCols = [];
                    foreach ($rawCols as $c) {
                        $c = preg_replace('/^.*\./', '', $c); // strip alias prefix
                        $c = trim($c, " []\t\n\r");
                        if ($c && $c !== '*') {
                            $cleanCols[] = $c;
                        }
                    }
                    if (!empty($cleanCols)) {
                        $info['delete_keys'] = implode(',', $cleanCols);
                    }
                }
                // Extract target View or Table from SELECT ... FROM [DB].dbo.vw_... or dbo.vw_...
                if (preg_match_all('/FROM\s+((?:\[[^\]]+\]\.|\w+\.){1,2}(?:\[[^\]]+\]|vw_[A-Za-z0-9_]+|[A-Za-z0-9_]+))/i', $definition, $matches)) {
                    $candidates = $matches[1] ?? [];
                    // Prioritize views (vw_) or 3-part names in the final SELECT
                    foreach (array_reverse($candidates) as $cand) {
                        if (stripos($cand, 'vw_') !== false) {
                            $info['schema_object'] = $cand;
                            break;
                        }
                    }
                    if (!$info['schema_object'] && !empty($candidates)) {
                        $lastCand = end($candidates);
                        if (stripos($lastCand, 'SyncWatermarkControl') === false && stripos($lastCand, 'SyncExecutionLog') === false) {
                            $info['schema_object'] = $lastCand;
                        }
                    }
                }
            }

            // Check if dbo.usp_CompleteIncrementalSync exists in the same database
            if ($info['execution_mode'] === 'two_phase_delta') {
                $compCheck = $pdo->query("SELECT name, SCHEMA_NAME(schema_id) AS schema_name FROM sys.procedures WHERE name = 'usp_CompleteIncrementalSync'");
                if ($compRow = $compCheck->fetch(PDO::FETCH_ASSOC)) {
                    $cSchema = $compRow['schema_name'] ?: 'dbo';
                    $info['completion_proc'] = "{$cSchema}.{$compRow['name']}";
                } else {
                    $info['completion_proc'] = 'dbo.usp_CompleteIncrementalSync';
                }
            }
        } catch (Throwable $e) {
            // Ignore inspection errors
        }

        return $info;
    }

    public function createRuntimeConnection(array $credentials): PDO
    {
        $host = $credentials['host'] ?? '127.0.0.1';
        $port = $credentials['port'] ?? 1433;
        $db   = $credentials['database'] ?? '';
        $user = $credentials['username'] ?? '';
        $pass = $credentials['password'] ?? '';
        
        $encrypt = !empty($credentials['encrypt']) ? 'yes' : 'no';
        $trust   = !empty($credentials['trust_server_certificate']) ? 'yes' : 'no';

        $dsn = "sqlsrv:Server={$host},{$port};Database={$db};Encrypt={$encrypt};TrustServerCertificate={$trust};LoginTimeout=5";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 30,
        ];

        return new PDO($dsn, $user, $pass, $options);
    }

    public function getCapabilities(): array
    {
        return ['metadata', 'query', 'views', 'stored_procedures', 'sql_server_specific'];
    }
}
