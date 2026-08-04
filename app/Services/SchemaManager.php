<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SchemaManager
{
    /**
     * Ensure the target table exists and has all necessary columns.
     * Creates the table from a sample data row if it doesn't exist,
     * or adds missing columns if the table already exists.
     */
    public function ensureTable(?string $connectionName, string $tableName, array $sampleRow): void
    {
        $columns = $this->extractColumns($sampleRow);

        $driver = DB::connection($connectionName)->getDriverName();
        $isMysql = in_array($driver, ['mysql', 'mariadb']);

        if ($isMysql) {
            try {
                DB::connection($connectionName)->statement('SET innodb_strict_mode = 0;');
            } catch (\Exception $e) {}
        }

        if (! Schema::connection($connectionName)->hasTable($tableName)) {
            $this->createTable($connectionName, $tableName, $sampleRow, $columns);
            Log::info("SchemaManager: Created table [{$tableName}] on connection [{$connectionName}] with " . count($columns) . " columns.");
        } else {
            $added = $this->addMissingColumns($connectionName, $tableName, $sampleRow, $columns);
            if ($added > 0) {
                Log::info("SchemaManager: Added {$added} new columns to [{$tableName}] on connection [{$connectionName}].");
            }
        }

        $this->ensureODataColumnsAreText($connectionName, $tableName, $columns);
        $this->ensureRowHashColumn($connectionName, $tableName);

        if ($isMysql) {
            try {
                DB::connection($connectionName)->statement('SET innodb_strict_mode = 1;');
            } catch (\Exception $e) {}
        }
    }

    /**
     * Create a new table based on a sample OData record.
     */
    private function createTable(?string $connectionName, string $tableName, array $sampleRow, array $columns): void
    {
        Schema::connection($connectionName)->create($tableName, function (Blueprint $table) use ($sampleRow, $columns) {
            $table->bigIncrements('_sync_id');

            foreach ($columns as $originalName) {
                $colName = $this->sanitizeColumnName($originalName);
                $value   = $sampleRow[$originalName] ?? null;
                $this->addColumn($table, $colName, $value);
            }

            $table->dateTime('_synced_at')->nullable();
            $table->string('_row_hash', 64)->nullable()->index();
        });
    }

    /**
     * Add columns that exist in the sample data but not yet in the table.
     */
    private function addMissingColumns(?string $connectionName, string $tableName, array $sampleRow, array $columns): int
    {
        $existing = array_map('strtolower', Schema::connection($connectionName)->getColumnListing($tableName));
        $added = 0;

        foreach ($columns as $originalName) {
            $colName = $this->sanitizeColumnName($originalName);

            if (in_array(strtolower($colName), $existing, true)) {
                continue;
            }

            Schema::connection($connectionName)->table($tableName, function (Blueprint $table) use ($colName, $sampleRow, $originalName) {
                $value = $sampleRow[$originalName] ?? null;
                $this->addColumn($table, $colName, $value);
            });

            $added++;
        }

        return $added;
    }

    /**
     * Add a single column to a Blueprint with an inferred SQL type.
     */
    private function addColumn(Blueprint $table, string $name, mixed $value): void
    {
        // Avoid reserved internal columns
        if (in_array(strtolower($name), ['_sync_id', '_synced_at', '_row_hash'])) {
            return;
        }

        if (is_bool($value)) {
            $table->boolean($name)->nullable();
            return;
        }

        if (is_int($value)) {
            $table->bigInteger($name)->nullable();
            return;
        }

        if (is_float($value)) {
            $table->decimal($name, 28, 8)->nullable();
            return;
        }

        $lowerName = strtolower($name);

        // Detect Unique Keys for Upsert
        if ($lowerName === 'id') {
            $table->string($name, 64)->nullable()->unique();
            return;
        }

        if ($lowerName === 'noteid') {
            $table->string($name, 64)->nullable()->unique();
            return;
        }

        // Detect DateTime columns used for filtering/incremental sync
        if ($lowerName === 'lastmodifieddatetime' || $lowerName === 'createddatetime') {
            $table->string($name, 64)->nullable()->index();
            return;
        }

        // For EVERYTHING ELSE (null, string, array, object), use TEXT.
        // We do NOT use VARCHAR because VARCHAR(255) takes up to 1020 bytes inline in utf8mb4,
        // which rapidly depletes MySQL's 8126 byte row limit on wide tables like SalesOrders.
        // TEXT stores data off-page and only takes a 20-byte pointer inline.
        $table->text($name)->nullable();
    }

    /**
     * OData DAC values are not always type-stable across rows. Store mirrored
     * fields as NVARCHAR(MAX) so later rows cannot fail numeric/date conversion.
     */
    private function ensureODataColumnsAreText(?string $connectionName, string $tableName, array $columns): void
    {
        // NO-OP.
    }

    /**
     * Ensure the _row_hash column exists on an existing table.
     * This column stores an MD5 hash of all business columns for dedup.
     */
    private function ensureRowHashColumn(?string $connectionName, string $tableName): void
    {
        $columns = array_map('strtolower', Schema::connection($connectionName)->getColumnListing($tableName));
        if (!in_array('_row_hash', $columns)) {
            Schema::connection($connectionName)->table($tableName, function (Blueprint $table) {
                $table->string('_row_hash', 64)->nullable()->index();
            });
            Log::info("SchemaManager: Added _row_hash column to [{$tableName}] on connection [{$connectionName}].");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Extract column names from a sample OData row, filtering out
     * OData metadata properties like @odata.context, @odata.etag, etc.
     */
    private function extractColumns(array $row): array
    {
        return array_values(array_filter(
            array_keys($row),
            fn (string $key) => ! str_starts_with($key, '@') && ! str_starts_with($key, 'odata.') && $key !== '__metadata'
        ));
    }

    /**
     * Sanitize a column name so it is safe for SQL Server.
     */
    public function sanitizeColumnName(string $name): string
    {
        $name = str_replace(['.', ' ', '-', '/', '\\', '(', ')'], '_', $name);
        $name = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');

        if ($name === '' || preg_match('/^\d/', $name)) {
            $name = 'col_' . $name;
        }

        return $name;
    }

    private function wrapIdentifier(string $name, string $driver): string
    {
        if ($driver === 'mysql') {
            return '`' . str_replace('`', '``', $name) . '`';
        }
        
        return '[' . str_replace(']', ']]', $name) . ']';
    }

    /**
     * Clean a single OData record for database insertion, sanitizing keys
     * and converting complex values.
     *
     * @param array $record
     * @param array|null $columnMap  Pre-built map of original→sanitized names (for batch use)
     */
    public function cleanRecord(array $record, ?array $columnMap = null): array
    {
        $clean = [];

        foreach ($record as $key => $value) {
            if (str_starts_with($key, '@') || str_starts_with($key, 'odata.') || $key === '__metadata') {
                continue;
            }

            $colName = $columnMap[$key] ?? $this->sanitizeColumnName($key);

            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            if (is_resource($value)) {
                $value = stream_get_contents($value);
            }

            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                $value = '0x' . strtoupper(bin2hex($value));
            }

            // OData v4 returns Edm.Binary (like tstamp) as Base64 (12 chars).
            // SQL Server returns it as raw 8-byte binary, or sometimes as a 16-char hex string depending on PDO driver config.
            // Convert everything to a standard Hex string (e.g., 0x00000000397677AB)
            if (strtolower($colName) === 'tstamp' && is_string($value) && !str_starts_with($value, '0x')) {
                // If it is 16 chars and valid hex, prefix with 0x
                if (strlen($value) === 16 && ctype_xdigit($value)) {
                    $value = '0x' . strtoupper($value);
                } 
                // If it is exactly 12 characters, and it doesn't have invalid base64 chars, it might be base64
                elseif (strlen($value) === 12 && preg_match('/^[a-zA-Z0-9+\/]+={0,2}$/', $value)) {
                    $decoded = base64_decode($value, true);
                    if ($decoded !== false) {
                        $value = '0x' . strtoupper(bin2hex($decoded));
                    } else {
                        // fallback if base64 decode somehow fails
                        $value = '0x' . strtoupper(bin2hex($value));
                    }
                } 
                // Otherwise, it must be raw binary data (even if length isn't exactly 8 for some weird reason)
                else {
                    $value = '0x' . strtoupper(bin2hex($value));
                }
            }

            $clean[$colName] = $value;
        }

        $clean['_synced_at'] = now()->toDateTimeString();

        // Compute _row_hash from all business columns for dedup
        $hashData = $clean;
        unset($hashData['_synced_at'], $hashData['_row_hash'], $hashData['_sync_id']);
        ksort($hashData);
        $hashString = '';
        foreach ($hashData as $v) {
            $hashString .= '|' . (string)($v ?? '');
        }
        $clean['_row_hash'] = md5($hashString);

        return $clean;
    }

    /**
     * Clean multiple OData records.
     * Builds a column-name map from the first record and reuses it for all
     * subsequent records — avoids calling sanitizeColumnName() millions of
     * times on repeated key sets.
     */
    public function cleanRecords(array $records): array
    {
        if (empty($records)) {
            return [];
        }

        // Build a column-name mapping cache from the first record's keys.
        // OData rows from the same entity always share the same set of keys,
        // so one map is sufficient for the whole batch.
        $columnMap = [];
        foreach (array_keys($records[0]) as $key) {
            if (str_starts_with($key, '@') || str_starts_with($key, 'odata.') || $key === '__metadata') {
                continue;
            }
            $columnMap[$key] = $this->sanitizeColumnName($key);
        }

        // Now use the cached map for every record.
        $result = [];

        foreach ($records as $record) {
            $result[] = $this->cleanRecord((array)$record, $columnMap);
        }

        return $result;
    }

    protected array $tableExistsCache = [];

    /**
     * Check if a table exists.
     */
    public function tableExists(?string $connectionName, string $tableName): bool
    {
        $cacheKey = ($connectionName ?? 'default') . '.' . $tableName;
        if (!array_key_exists($cacheKey, $this->tableExistsCache)) {
            try {
                $this->tableExistsCache[$cacheKey] = Schema::connection($connectionName)->hasTable($tableName);
            } catch (\Exception $e) {
                $this->tableExistsCache[$cacheKey] = false;
                Log::warning("SchemaManager: Connection [{$connectionName}] failed or not configured.", ['error' => $e->getMessage()]);
            }
        }
        return $this->tableExistsCache[$cacheKey];
    }

    /**
     * Get the row count for a table.
     */
    public function getRowCount(?string $connectionName, string $tableName): int
    {
        if (! $this->tableExists($connectionName, $tableName)) {
            return 0;
        }

        return DB::connection($connectionName)->table($tableName)->count();
    }
}
