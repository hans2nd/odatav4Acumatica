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
        $stmt = $pdo->query("SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE IN ('BASE TABLE', 'VIEW')");
        
        $nodes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $type = $row['TABLE_TYPE'] === 'VIEW' ? 'view' : 'table';
            $name = isset($row['TABLE_SCHEMA']) && $row['TABLE_SCHEMA'] !== 'dbo' 
                ? $row['TABLE_SCHEMA'] . '.' . $row['TABLE_NAME'] 
                : $row['TABLE_NAME'];
            $nodes[] = new MetadataNode(name: $name, type: $type);
        }

        return new MetadataTree($nodes);
    }

    public function discoverStructure(array $credentials, string $objectName): MetadataNode
    {
        $pdo = $this->createRuntimeConnection($credentials);
        
        $parts = explode('.', $objectName);
        if (count($parts) === 2) {
            $schemaName = str_replace("'", "''", $parts[0]);
            $tableName = str_replace("'", "''", $parts[1]);
            $query = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$schemaName}' AND TABLE_NAME = '{$tableName}' ORDER BY ORDINAL_POSITION";
        } else {
            $objectNameEscaped = str_replace("'", "''", $objectName);
            $query = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$objectNameEscaped}' ORDER BY ORDINAL_POSITION";
        }
        
        $stmt = $pdo->query($query);
        
        $properties = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $properties[] = [
                'name' => $row['COLUMN_NAME'],
                'type' => $row['DATA_TYPE'],
            ];
        }

        return new MetadataNode(name: $objectName, type: 'table', properties: $properties);
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
