<?php

namespace App\Services;

use App\Models\Connection;

class ConnectionService
{
    /**
     * Ensure default connections exist in the database.
     */
    public function ensureDefaults(): void
    {
        try {
            if (!Connection::where('key', 'acumatica_default')->exists()) {
                Connection::create([
                    'key'  => 'acumatica_default',
                    'name' => 'Acumatica ERP (Default)',
                    'type' => 'odata',
                    'credentials' => [
                        'url'       => config('acumatica.url', ''),
                        'tenant'    => config('acumatica.tenant', ''),
                        'username'  => config('acumatica.username', ''),
                        'password'  => config('acumatica.password', ''),
                        'odata_path' => config('acumatica.odata_path', 'odatav4'),
                    ],
                    'is_active' => true,
                ]);
            }

            if (!Connection::where('key', 'database_default')->exists()) {
                $defaultConn = config('database.default', 'sqlsrv');
                Connection::create([
                    'key'  => 'database_default',
                    'name' => 'Data Warehouse DB (Default)',
                    'type' => $defaultConn,
                    'credentials' => [
                        'host'     => config("database.connections.{$defaultConn}.host", ''),
                        'port'     => config("database.connections.{$defaultConn}.port", ''),
                        'database' => config("database.connections.{$defaultConn}.database", ''),
                        'username' => config("database.connections.{$defaultConn}.username", ''),
                        'password' => config("database.connections.{$defaultConn}.password", ''),
                    ],
                    'is_active' => true,
                ]);
            }
        } catch (\Exception $e) {
            // Ignore during initial setup or if table doesn't exist yet
        }
    }

    /**
     * Get all connections as an associative array keyed by connection key.
     */
    public function getAll(): array
    {
        try {
            $connections = Connection::all();
            $result = [];
            foreach ($connections as $conn) {
                $result[$conn->key] = [
                    'name'        => $conn->name,
                    'type'        => $conn->type,
                    'credentials' => $conn->credentials ?? [],
                    'is_active'   => $conn->is_active,
                    'is_connected'=> $conn->is_connected,
                ];
            }
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get a single connection by key.
     */
    public function get(string $key): ?array
    {
        try {
            $conn = Connection::where('key', $key)->first();
            if (!$conn) {
                return null;
            }
            return [
                'name'        => $conn->name,
                'type'        => $conn->type,
                'credentials' => $conn->credentials ?? [],
                'is_active'   => $conn->is_active,
                'is_connected'=> $conn->is_connected,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create or update a connection.
     */
    public function save(string $key, array $data): void
    {
        Connection::updateOrCreate(
            ['key' => $key],
            [
                'name'        => $data['name'],
                'type'        => $data['type'],
                'credentials' => $data['credentials'],
                'is_active'   => $data['is_active'] ?? true,
                'is_connected'=> $data['is_connected'] ?? false,
            ]
        );
    }

    /**
     * Update an existing connection (supports key rename).
     */
    public function update(string $oldKey, string $newKey, array $data): void
    {
        $conn = Connection::where('key', $oldKey)->first();

        if ($conn) {
            $conn->update([
                'key'         => $newKey,
                'name'        => $data['name'],
                'type'        => $data['type'],
                'credentials' => $data['credentials'],
                'is_active'   => $data['is_active'] ?? $conn->is_active,
                'is_connected'=> $data['is_connected'] ?? $conn->is_connected,
            ]);
        } else {
            $this->save($newKey, $data);
        }
    }

    /**
     * Delete a connection by key.
     */
    public function delete(string $key): bool
    {
        return Connection::where('key', $key)->delete() > 0;
    }

    /**
     * Check if a connection key already exists.
     */
    public function exists(string $key): bool
    {
        return Connection::where('key', $key)->exists();
    }



    /**
     * Resolve a connection key to a Laravel DB connection name.
     */
    public function resolveConnectionName(string $connKey): string
    {
        $conn = $this->get($connKey);
        if (!$conn) {
            throw new \Exception("Connection not found: {$connKey}");
        }

        $type = $conn['type'];
        $laravelConnName = 'dyn_' . $connKey;

        if (!config()->has("database.connections.{$laravelConnName}")) {
            if ($type === 'local_env') {
                $defaultConn = config('database.default');
                $mergedConfig = config("database.connections.{$defaultConn}");
            } else {
                $creds = $conn['credentials'] ?? [];
                $mergedConfig = array_merge(
                    is_array(config("database.connections.{$type}")) ? config("database.connections.{$type}") : [],
                    [
                        'driver'   => $type,
                        'host'     => $creds['host'] ?? '',
                        'port'     => $creds['port'] ?? '',
                        'database' => $creds['database'] ?? '',
                        'username' => $creds['username'] ?? '',
                        'password' => $creds['password'] ?? '',
                    ]
                );

                if ($type === 'sqlsrv') {
                    $mergedConfig['encrypt'] = $mergedConfig['encrypt'] ?? 'no';
                    $mergedConfig['trust_server_certificate'] = $mergedConfig['trust_server_certificate'] ?? 'true';
                }
            }

            config(["database.connections.{$laravelConnName}" => $mergedConfig]);
        }

        return $laravelConnName;
    }


}
