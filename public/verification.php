<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

header('Content-Type: application/json');

$output = [];
$service = app(\App\Services\ConnectionService::class);
$providerFactory = app(\App\Contracts\Connections\ConnectionProviderFactoryInterface::class);

// Find first SQL server connection in config or database
$allConnections = $service->getAll();
$sqlSrvCreds = null;
foreach ($allConnections as $conn) {
    if ($conn['type'] === 'sqlsrv') {
        $sqlSrvCreds = $conn['credentials'] ?? [];
        break;
    }
}

if (!$sqlSrvCreds) {
    $output['error'] = 'No SQL Server connection found in the registry to test.';
} else {
    try {
        $provider = $providerFactory->make('sqlsrv');
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
}

echo json_encode($output, JSON_PRETTY_PRINT);
