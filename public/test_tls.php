<?php
header('Content-Type: application/json');

$host = '127.0.0.1';
$port = 1433;
$db = 'master';
$user = 'sa';
$pass = 'secret'; // Dummy password since we just want to see if it reaches TDS/Login

$combinations = [
    ['encrypt' => 'false', 'trust' => 'false'],
    ['encrypt' => 'false', 'trust' => 'true'],
    ['encrypt' => 'true',  'trust' => 'false'],
    ['encrypt' => 'true',  'trust' => 'true'],
    ['encrypt' => 'no',    'trust' => 'no'],
    ['encrypt' => 'no',    'trust' => 'yes'],
    ['encrypt' => 'yes',   'trust' => 'no'],
    ['encrypt' => 'yes',   'trust' => 'yes'],
    ['encrypt' => 0,       'trust' => 0],
    ['encrypt' => 1,       'trust' => 1],
];

$results = [];

foreach ($combinations as $combo) {
    $dsn = "sqlsrv:Server={$host},{$port};Database={$db};Encrypt={$combo['encrypt']};TrustServerCertificate={$combo['trust']};LoginTimeout=3";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 5,
    ];

    $result = [
        'encrypt_value' => $combo['encrypt'],
        'trust_value' => $combo['trust'],
        'dsn' => $dsn,
        'options' => $options,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $result['status'] = 'Success';
        $result['message'] = 'Connected successfully';
        $result['sqlstate'] = null;
    } catch (\PDOException $e) {
        $result['status'] = 'Failure';
        $result['message'] = $e->getMessage();
        $result['sqlstate'] = $e->getCode();
    } catch (\Throwable $e) {
        $result['status'] = 'Fatal Error';
        $result['message'] = $e->getMessage();
        $result['sqlstate'] = $e->getCode();
    }
    
    $results[] = $result;
}

echo json_encode(['tests' => $results], JSON_PRETTY_PRINT);
