<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            // Only proceed if the connections table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('connections')) {
                return;
            }

            // Register Firebird Database Resolver
            \Illuminate\Database\Connection::resolverFor('firebird', function ($connection, $database, $prefix, $config) {
                $host = $config['host'] ?? '127.0.0.1';
                $port = $config['port'] ?? '3051';
                $db = $config['database'] ?? '';
                $dsn = "firebird:dbname={$host}/{$port}:{$db}";
                $pdo = new \PDO($dsn, $config['username'] ?? '', $config['password'] ?? '');
                return new \Illuminate\Database\Connection($pdo, $database, $prefix, $config);
            });

            $connectionService = app(\App\Services\ConnectionService::class);
            $connections = $connectionService->getAll();

            foreach ($connections as $key => $conn) {
                if (in_array($conn['type'], ['mysql', 'sqlsrv', 'firebird', 'local_env'])) {
                    $creds = $conn['credentials'] ?? [];

                    if ($conn['type'] === 'local_env') {
                        $defaultConn = config('database.default');
                        config(["database.connections.{$key}" => config("database.connections.{$defaultConn}")]);
                    } elseif ($conn['type'] === 'mysql') {
                        config([
                            "database.connections.{$key}" => [
                                'driver' => 'mysql',
                                'url' => env('DATABASE_URL'),
                                'host' => $creds['host'] ?? '127.0.0.1',
                                'port' => $creds['port'] ?? '3306',
                                'database' => $creds['database'] ?? '',
                                'username' => $creds['username'] ?? '',
                                'password' => $creds['password'] ?? '',
                                'unix_socket' => env('DB_SOCKET', ''),
                                'charset' => 'utf8mb4',
                                'collation' => 'utf8mb4_unicode_ci',
                                'prefix' => '',
                                'prefix_indexes' => true,
                                'strict' => true,
                                'engine' => null,
                                'options' => extension_loaded('pdo_mysql') ? array_filter([
                                    \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                                ]) : [],
                            ]
                        ]);
                    } elseif ($conn['type'] === 'sqlsrv') {
                        config([
                            "database.connections.{$key}" => [
                                'driver' => 'sqlsrv',
                                'url' => env('DATABASE_URL'),
                                'host' => $creds['host'] ?? 'localhost',
                                'port' => $creds['port'] ?? '1433',
                                'database' => $creds['database'] ?? '',
                                'username' => $creds['username'] ?? '',
                                'password' => $creds['password'] ?? '',
                                'charset' => 'utf8',
                                'prefix' => '',
                                'prefix_indexes' => true,
                                // 'encrypt' => env('DB_ENCRYPT', 'yes'),
                                // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
                            ]
                        ]);
                    } elseif ($conn['type'] === 'firebird') {
                        config([
                            "database.connections.{$key}" => [
                                'driver' => 'firebird',
                                'host' => $creds['host'] ?? '127.0.0.1',
                                'port' => $creds['port'] ?? '3051',
                                'database' => $creds['database'] ?? '',
                                'username' => $creds['username'] ?? '',
                                'password' => $creds['password'] ?? '',
                                'charset' => 'utf8',
                                'prefix' => '',
                            ]
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore during initial setup or if table doesn't exist yet
        }
    }
}
