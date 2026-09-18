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

class MySqlProvider implements ConnectionProviderInterface
{
    public function getConfigurationSchema(): array
    {
        return [
            new ConfigurationField('host', 'Host', 'text', true, '127.0.0.1'),
            new ConfigurationField('port', 'Port', 'number', true, 3306),
            new ConfigurationField('database', 'Database', 'text', true),
            new ConfigurationField('username', 'Username', 'text', true),
            new ConfigurationField('password', 'Password', 'password', false),
            new ConfigurationField('ssl', 'Use SSL', 'boolean', false, false),
        ];
    }

    public function getValidationRules(): array
    {
        return [
            'credentials.host'     => 'required|string',
            'credentials.port'     => 'required|numeric',
            'credentials.database' => 'required|string',
            'credentials.username' => 'required|string',
            'credentials.password' => 'nullable|string',
            'credentials.ssl'      => 'nullable|boolean',
        ];
    }

    public function testConnection(array $credentials): ConnectionHealth
    {
        $start = microtime(true);
        try {
            $this->createRuntimeConnection($credentials);
            $elapsed = (int) round((microtime(true) - $start) * 1000);
            return new ConnectionHealth(ConnectionHealthStatus::HEALTHY, 'Connected successfully', $elapsed);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Access denied')) {
                return new ConnectionHealth(ConnectionHealthStatus::ERROR_AUTH_FAILED, $msg);
            }
            return new ConnectionHealth(ConnectionHealthStatus::ERROR_UNREACHABLE, $msg);
        }
    }

    public function getDiagnosticInfo(array $credentials): ProviderInfo
    {
        try {
            $pdo = $this->createRuntimeConnection($credentials);
            $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $clientVersion = $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION);
            return new ProviderInfo('MySQL', $clientVersion, $serverVersion);
        } catch (Throwable $e) {
            return new ProviderInfo('MySQL');
        }
    }

    public function discoverMetadata(array $credentials): MetadataTree
    {
        $pdo = $this->createRuntimeConnection($credentials);
        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE' OR Table_type = 'VIEW'");
        
        $nodes = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $type = $row[1] === 'VIEW' ? 'view' : 'table';
            $nodes[] = new MetadataNode(name: $row[0], type: $type);
        }

        return new MetadataTree($nodes);
    }

    public function discoverStructure(array $credentials, string $objectName): MetadataNode
    {
        $pdo = $this->createRuntimeConnection($credentials);
        $objectNameEscaped = '`' . str_replace('`', '``', $objectName) . '`';
        $stmt = $pdo->query("SHOW COLUMNS FROM {$objectNameEscaped}");
        
        $properties = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $properties[] = [
                'name' => $row['Field'],
                'type' => $row['Type'],
            ];
        }

        return new MetadataNode(name: $objectName, type: 'table', properties: $properties);
    }

    public function createRuntimeConnection(array $credentials): PDO
    {
        $host = $credentials['host'] ?? '127.0.0.1';
        $port = $credentials['port'] ?? 3306;
        $db   = $credentials['database'] ?? '';
        $user = $credentials['username'] ?? '';
        $pass = $credentials['password'] ?? '';
        
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ];
        
        if (!empty($credentials['ssl'])) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = true;
        }

        return new PDO($dsn, $user, $pass, $options);
    }

    public function getCapabilities(): array
    {
        return ['metadata', 'query', 'transaction', 'views', 'stored_procedures'];
    }
}
