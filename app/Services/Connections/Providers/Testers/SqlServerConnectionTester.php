<?php

declare(strict_types=1);

namespace App\Services\Connections\Providers\Testers;

use App\DTO\Connections\ConnectionHealth;
use App\DTO\Connections\ConnectionHealthStatus;
use App\Services\Connections\Providers\SqlServerProvider;
use App\Services\Connections\Providers\Mappers\SqlServerErrorMapper;

class SqlServerConnectionTester
{
    public function __construct(
        private readonly SqlServerErrorMapper $errorMapper
    ) {}

    public function runDiagnostics(SqlServerProvider $provider, array $credentials): ConnectionHealth
    {
        $checks = [
            'Driver Loaded' => false,
            'TCP Reachable' => false,
            'TLS Negotiated' => false,
            'Authentication' => false,
            'Database' => false,
            'Permissions' => false,
        ];
        $summary = [];
        $metrics = [];
        $error = null;
        $status = ConnectionHealthStatus::HEALTHY;
        $globalMessage = 'Connection Successful';

        // Step 1: Verify Driver
        $checks['Driver Loaded'] = extension_loaded('sqlsrv') && extension_loaded('pdo_sqlsrv');
        if (!$checks['Driver Loaded']) {
            return new ConnectionHealth(
                status: ConnectionHealthStatus::ERROR_DRIVER_MISSING,
                message: 'Microsoft SQL Server PHP extensions (sqlsrv, pdo_sqlsrv) are not installed.',
                checks: $checks
            );
        }

        // Step 2: Verify Network
        $host = $credentials['host'] ?? '127.0.0.1';
        $port = $credentials['port'] ?? 1433;
        
        $startNet = microtime(true);
        $fp = @fsockopen($host, (int)$port, $errCode, $errStr, 2);
        if ($fp) {
            $metrics['latency_ms'] = (int) round((microtime(true) - $startNet) * 1000);
            $checks['TCP Reachable'] = true;
            fclose($fp);
        } else {
            $error = $this->errorMapper->map(new \Exception("Network unreachable: {$errStr}", $errCode));
            return new ConnectionHealth(
                status: ConnectionHealthStatus::ERROR_UNREACHABLE,
                message: 'Host unreachable.',
                checks: $checks,
                metrics: $metrics,
                error: $error
            );
        }

        // Step 3-6: Verify PDO, TLS, Auth, DB
        $pdo = null;
        $startPdo = microtime(true);
        try {
            $pdo = $provider->createRuntimeConnection($credentials);
            $checks['TLS Negotiated'] = true;
            $checks['Authentication'] = true;
            $checks['Database'] = true;
            $checks['Permissions'] = true;
        } catch (\Throwable $e) {
            $error = $this->errorMapper->map($e);
            
            // Infer failures based on mapped error
            if ($error->code === 'TLS_NEGOTIATION_FAILED') {
                $checks['TLS Negotiated'] = false;
            } elseif ($error->code === 'INVALID_CREDENTIALS' || $error->code === 'LOGIN_FAILED' || $error->code === 'WINDOWS_AUTH_REQUIRED') {
                $checks['TLS Negotiated'] = true;
                $checks['Authentication'] = false;
            } elseif ($error->code === 'DATABASE_NOT_FOUND') {
                $checks['TLS Negotiated'] = true;
                $checks['Authentication'] = true;
                $checks['Database'] = false;
            } elseif ($error->code === 'LOGIN_TIMEOUT' || $error->code === 'ERROR_UNREACHABLE') {
                // Network failed mid-stream
                $checks['TCP Reachable'] = false;
            }

            return new ConnectionHealth(
                status: ConnectionHealthStatus::ERROR_UNREACHABLE,
                message: $error->message,
                checks: $checks,
                metrics: $metrics,
                error: $error
            );
        }

        // Step 7: Execute Summary Queries
        try {
            $summary['Driver'] = 'PDO_SQLSRV';
            $summary['PDO_SQLSRV Version'] = phpversion('pdo_sqlsrv');
            
            // Safe queries
            $summary['Server Version'] = $pdo->query("SELECT @@VERSION")->fetchColumn();
            $summary['Current Database'] = $pdo->query("SELECT DB_NAME()")->fetchColumn();
            $summary['Current User'] = $pdo->query("SELECT SYSTEM_USER")->fetchColumn();
            $summary['Server Name'] = $pdo->query("SELECT @@SERVERNAME")->fetchColumn();
            
            // Extract ODBC version if possible
            $clientVer = $pdo->getAttribute(\PDO::ATTR_CLIENT_VERSION);
            $summary['ODBC Driver'] = is_array($clientVer) && isset($clientVer['DriverVer']) ? $clientVer['DriverVer'] : (is_string($clientVer) ? $clientVer : 'Unknown');

            $summary['Encryption'] = (!empty($credentials['encrypt'])) ? 'Enabled' : 'Disabled';

        } catch (\Throwable $e) {
            $checks['Permissions'] = false;
            $error = $this->errorMapper->map($e);
            $status = ConnectionHealthStatus::WARNING;
            $globalMessage = 'Connected, but diagnostic queries failed.';
        }

        $metrics['total_time_ms'] = (int) round((microtime(true) - $startPdo) * 1000);

        return new ConnectionHealth(
            status: $status,
            message: $globalMessage,
            pingMs: $metrics['latency_ms'] ?? null,
            checks: $checks,
            metrics: $metrics,
            summary: $summary,
            error: $error
        );
    }
}
