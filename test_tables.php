<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$req = Illuminate\Http\Request::create('/api/query-builder/tables', 'GET', ['connection' => '6']);
$res = app()->handle($req);
echo "With connection ID (6):\n";
echo $res->getContent() . "\n\n";

$conn = App\Models\Connection::find(6);
$req2 = Illuminate\Http\Request::create('/api/query-builder/tables', 'GET', ['connection' => $conn->key ?? 'null']);
$res2 = app()->handle($req2);
echo "With connection Key ({$conn->key}):\n";
echo substr($res2->getContent(), 0, 500) . "...\n";
