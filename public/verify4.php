<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('HTTP/1.1 200 OK');
header('Content-Type: application/json');

$output = [];
$output['runtime'] = [
    'PHP_VERSION' => PHP_VERSION,
    'PDO_DRIVERS' => PDO::getAvailableDrivers(),
    'pdo_sqlsrv_version' => phpversion('pdo_sqlsrv'),
    'sqlsrv_version' => phpversion('sqlsrv'),
];
$output['constants'] = [
    'PDO::SQLSRV_ATTR_QUERY_TIMEOUT' => defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT') ? 'Exists' : 'Missing',
    'PDO::ATTR_TIMEOUT' => defined('PDO::ATTR_TIMEOUT') ? 'Exists' : 'Missing',
    'PDO::ATTR_ERRMODE' => defined('PDO::ATTR_ERRMODE') ? 'Exists' : 'Missing',
];

$sqlSrvCreds = config('database.connections.sqlsrv', []);

if (empty($sqlSrvCreds['host'])) {
    $sqlSrvCreds = [
        'host' => '127.0.0.1',
        'port' => 1433,
        'database' => 'master',
        'username' => 'sa',
        'password' => 'secret',
        'encrypt' => false,
        'trust_server_certificate' => true,
    ];
}

try {
    $provider = new \App\Services\Connections\Providers\SqlServerProvider();
    $health = $provider->testConnection($sqlSrvCreds);
    $output['test_result'] = [
        'status' => $health->status->value,
        'message' => $health->message,
    ];
    
    $host = $sqlSrvCreds['host'] ?? '127.0.0.1';
    $port = $sqlSrvCreds['port'] ?? 1433;
    $db   = $sqlSrvCreds['database'] ?? '';
    
    $encrypt = ($sqlSrvCreds['encrypt'] ?? true) ? 'true' : 'false';
    $trust   = ($sqlSrvCreds['trust_server_certificate'] ?? true) ? 'true' : 'false';

    $output['final_dsn'] = "sqlsrv:Server={$host},{$port};Database={$db};Encrypt={$encrypt};TrustServerCertificate={$trust};LoginTimeout=5";
    $output['final_options'] = [
        'PDO::ATTR_ERRMODE' => 'PDO::ERRMODE_EXCEPTION',
        'PDO::SQLSRV_ATTR_QUERY_TIMEOUT' => 30,
    ];
} catch (\Throwable $e) {
    $output['exception'] = $e->getMessage();
}

echo json_encode($output, JSON_PRETTY_PRINT);
