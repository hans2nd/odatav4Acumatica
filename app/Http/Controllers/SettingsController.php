<?php

namespace App\Http\Controllers;

use App\Services\EntityConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function index(EntityConfigService $entityConfig)
    {
        $entities = $entityConfig->getAll();

        $dbConnection = config('database.default', 'sqlsrv');

        return view('settings.index', [
            'acumaticaUrl'      => config('acumatica.url'),
            'acumaticaUser'     => config('acumatica.username'),
            'acumaticaPassword' => config('acumatica.password'),
            'acumaticaTenant'   => config('acumatica.tenant'),
            'acumaticaVerifySsl'=> config('acumatica.verify_ssl'),
            'dbConnection'      => $dbConnection,
            'dbHost'            => config("database.connections.{$dbConnection}.host"),
            'dbPort'            => config("database.connections.{$dbConnection}.port"),
            'dbName'            => config("database.connections.{$dbConnection}.database"),
            'dbUsername'        => config("database.connections.{$dbConnection}.username"),
            'dbPassword'        => config("database.connections.{$dbConnection}.password"),
            'entities'          => $entities,
            'activeTab'         => 'settings',
        ]);
    }

    /**
     * Update Acumatica ERP settings in the .env file.
     */
    public function updateAcumatica(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url'        => 'required|url|max:500',
            'username'   => 'required|string|max:100',
            'password'   => 'required|string|max:255',
            'tenant'     => 'nullable|string|max:100',
            'verify_ssl' => 'nullable|boolean',
        ]);

        try {
            $this->setEnvValue('ACUMATICA_URL', $validated['url']);
            $this->setEnvValue('ACUMATICA_USERNAME', $validated['username']);
            $this->setEnvValue('ACUMATICA_PASSWORD', $validated['password']);
            $this->setEnvValue('ACUMATICA_TENANT', $validated['tenant'] ?? '');
            $this->setEnvValue('ACUMATICA_VERIFY_SSL', ($validated['verify_ssl'] ?? false) ? 'true' : 'false');

            // Clear config cache so changes take effect immediately
            Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Acumatica ERP settings updated successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update SQL Server settings in the .env file.
     */
    public function updateDatabase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection' => 'required|in:sqlsrv,mysql',
            'host'       => 'required|string|max:255',
            'port'       => 'required|integer|min:1|max:65535',
            'database'   => 'required|string|max:255',
            'username'   => 'required|string|max:100',
            'password'   => 'nullable|string|max:255',
        ]);

        try {
            // 1. Ensure the database exists on the server
            $tempConfig = array_merge(config("database.connections.{$validated['connection']}"), [
                'host'     => $validated['host'],
                'port'     => $validated['port'],
                'database' => '', // Connect to server level
                'username' => $validated['username'],
                'password' => $validated['password'] ?? '',
            ]);
            
            $this->ensureDatabaseExists($validated['connection'], $tempConfig, $validated['database']);

            // 2. Update .env file
            $this->setEnvValue('DB_CONNECTION', $validated['connection']);
            $this->setEnvValue('DB_HOST', $validated['host']);
            $this->setEnvValue('DB_PORT', (string) $validated['port']);
            $this->setEnvValue('DB_DATABASE', $validated['database']);
            $this->setEnvValue('DB_USERNAME', $validated['username']);
            $this->setEnvValue('DB_PASSWORD', $validated['password'] ?? '');

            // 3. Update runtime config and run migrations
            config([
                'database.default' => $validated['connection'],
                "database.connections.{$validated['connection']}.host" => $validated['host'],
                "database.connections.{$validated['connection']}.port" => $validated['port'],
                "database.connections.{$validated['connection']}.database" => $validated['database'],
                "database.connections.{$validated['connection']}.username" => $validated['username'],
                "database.connections.{$validated['connection']}.password" => $validated['password'] ?? '',
            ]);
            
            DB::purge($validated['connection']);
            Artisan::call('migrate', ['--force' => true]);

            // Clear config cache so changes persist for next requests
            Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Database settings updated and migrations applied successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test database connection with provided settings (without saving).
     */
    public function testConnection(Request $request): JsonResponse
    {
        $type = $request->input('type');

        if ($type === 'database') {
            $validated = $request->validate([
                'connection' => 'required|in:sqlsrv,mysql',
                'host'       => 'required|string',
                'port'       => 'required|integer',
                'database'   => 'required|string',
                'username'   => 'required|string',
                'password'   => 'nullable|string',
            ]);

            try {
                $connectionName = $validated['connection'];
                config([
                    "database.connections.{$connectionName}_test" => array_merge(
                        config("database.connections.{$connectionName}"),
                        [
                            'host'     => $validated['host'],
                            'port'     => $validated['port'],
                            'database' => $validated['database'],
                            'username' => $validated['username'],
                            'password' => $validated['password'] ?? '',
                        ]
                    ),
                ]);

                $start = microtime(true);
                DB::connection("{$connectionName}_test")->getPdo();
                $elapsed = round((microtime(true) - $start) * 1000);

                return response()->json([
                    'success'   => true,
                    'message'   => "Connected successfully ({$elapsed}ms)",
                    'ping_ms'   => $elapsed,
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Connection failed: ' . $e->getMessage(),
                ], 422);
            }
        }

        if ($type === 'acumatica') {
            $validated = $request->validate([
                'url'      => 'required|url',
                'tenant'   => 'nullable|string',
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            try {
                config([
                    'acumatica.url'      => $validated['url'],
                    'acumatica.tenant'   => $validated['tenant'],
                    'acumatica.username' => $validated['username'],
                    'acumatica.password' => $validated['password'],
                ]);

                $odataService = app(\App\Services\AcumaticaODataService::class);
                $result = $odataService->testODataConnection();

                if ($result['connected']) {
                    return response()->json([
                        'success' => true,
                        'message' => "OData connected successfully ({$result['ping_ms']}ms)",
                        'ping_ms' => $result['ping_ms'],
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'OData connection failed: ' . $result['message'],
                    ], 422);
                }
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Connection failed: ' . $e->getMessage(),
                ], 422);
            }
        }

        return response()->json(['success' => false, 'message' => 'Unknown connection type.'], 400);
    }

    /**
     * Update a single key=value pair in the .env file.
     */
    protected function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $content = file_get_contents($envPath);

        // Determine if value needs quotes (contains spaces, special chars, or is empty)
        $quotedValue = $value;
        if ($value === '' || preg_match('/[\s#"\'\\\\]/', $value)) {
            $quotedValue = '"' . addcslashes($value, '"') . '"';
        }

        // Check if key already exists in .env
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $key . '=' . $quotedValue, $content);
        } else {
            // Append at end of file
            $content .= "\n{$key}={$quotedValue}";
        }

        file_put_contents($envPath, $content);
    }

    /**
     * Create the database if it doesn't exist on the target server.
     */
    protected function ensureDatabaseExists(string $driver, array $config, string $dbName): void
    {
        config(['database.connections.server_setup' => $config]);

        try {
            $pdo = DB::connection('server_setup')->getPdo();

            if ($driver === 'mysql') {
                $dbNameSafe = str_replace('`', '``', $dbName);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameSafe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } elseif ($driver === 'sqlsrv') {
                $dbNameSafe = str_replace(']', ']]', $dbName);
                $pdo->exec("
                    IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = '{$dbName}')
                    BEGIN
                        CREATE DATABASE [{$dbNameSafe}]
                    END
                ");
            }
        } finally {
            DB::purge('server_setup');
        }
    }
}
