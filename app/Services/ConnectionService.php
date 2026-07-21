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
     * Get table columns safely for any driver, specifically handling Firebird.
     */
    public function getTableColumns(string $connName, string $table): array
    {
        $driver = \Illuminate\Support\Facades\DB::connection($connName)->getDriverName();
        
        if ($driver === 'firebird') {
            $results = \Illuminate\Support\Facades\DB::connection($connName)->select("
                SELECT r.RDB\$FIELD_NAME AS FIELD_NAME
                FROM RDB\$RELATION_FIELDS r
                WHERE TRIM(r.RDB\$RELATION_NAME) = ?
                ORDER BY r.RDB\$FIELD_POSITION
            ", [trim($table)]);
            
            $columns = [];
            foreach ($results as $row) {
                $r = (array) $row;
                $columns[] = trim($r['FIELD_NAME'] ?? current($r));
            }
            return $columns;
        }

        return \Illuminate\Support\Facades\DB::connection($connName)->getSchemaBuilder()->getColumnListing($table);
    }

    /**
     * Get tables safely for any driver.
     */
    public function getTables(string $connName): array
    {
        $driver = \Illuminate\Support\Facades\DB::connection($connName)->getDriverName();
        $tables = [];

        if ($driver === 'mysql') {
            $results = \Illuminate\Support\Facades\DB::connection($connName)->select("SHOW TABLES");
            foreach ($results as $row) {
                $tables[] = array_values((array) $row)[0];
            }
        } elseif ($driver === 'sqlsrv') {
            $results = \Illuminate\Support\Facades\DB::connection($connName)->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE IN ('BASE TABLE', 'VIEW')");
            foreach ($results as $row) {
                $rowArr = (array) $row;
                $tables[] = $rowArr['TABLE_NAME'] ?? $rowArr['table_name'] ?? current($rowArr);
            }
        } elseif ($driver === 'firebird') {
            $results = \Illuminate\Support\Facades\DB::connection($connName)->select("
                SELECT r.RDB\$RELATION_NAME AS TABLE_NAME
                FROM RDB\$RELATIONS r
                WHERE r.RDB\$VIEW_BLR IS NULL 
                  AND (r.RDB\$SYSTEM_FLAG IS NULL OR r.RDB\$SYSTEM_FLAG = 0)
            ");
            foreach ($results as $row) {
                $r = (array) $row;
                $tables[] = trim($r['TABLE_NAME'] ?? current($r));
            }
        } else {
            // Fallback for pgsql, sqlite, etc. using SchemaBuilder if supported
            try {
                $tables = \Illuminate\Support\Facades\DB::connection($connName)->getSchemaBuilder()->getTableListing();
            } catch (\Exception $e) {
                // Ignore fallback errors
            }
        }

        sort($tables);
        return $tables;
    }
}
