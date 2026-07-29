<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$output = new Symfony\Component\Console\Output\BufferedOutput();
$kernel->handle(
    new Symfony\Component\Console\Input\StringInput('migrate --force'),
    $output
);

echo $output->fetch();
echo "Migrated successfully.";
