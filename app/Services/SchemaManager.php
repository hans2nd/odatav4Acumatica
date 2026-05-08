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
    public function ensureTable(string $tableName, array $sampleRow): void
    {
        $columns = $this->extractColumns($sampleRow);

        if (! Schema::hasTable($tableName)) {
            $this->createTable($tableName, $sampleRow, $columns);
            Log::info("SchemaManager: Created table [{$tableName}] with " . count($columns) . " columns.");
        } else {
            $added = $this->addMissingColumns($tableName, $sampleRow, $columns);
            if ($added > 0) {
                Log::info("SchemaManager: Added {$added} new columns to [{$tableName}].");
            }
        }

        $this->ensureODataColumnsAreText($tableName, $columns);
    }

    /**
     * Create a new table based on a sample OData record.
     */
    private function createTable(string $tableName, array $sampleRow, array $columns): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($sampleRow, $columns) {
            $table->bigIncrements('_sync_id');

            foreach ($columns as $originalName) {
                $colName = $this->sanitizeColumnName($originalName);
                $value   = $sampleRow[$originalName] ?? null;
                $this->addColumn($table, $colName, $value);
            }

            $table->dateTime('_synced_at')->nullable();
        });
    }

    /**
     * Add columns that exist in the sample data but not yet in the table.
     */
    private function addMissingColumns(string $tableName, array $sampleRow, array $columns): int
    {
        $existing = array_map('strtolower', Schema::getColumnListing($tableName));
        $added = 0;

        foreach ($columns as $originalName) {
            $colName = $this->sanitizeColumnName($originalName);

            if (in_array(strtolower($colName), $existing, true)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($colName, $sampleRow, $originalName) {
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
        if (in_array(strtolower($name), ['_sync_id', '_synced_at'])) {
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
    private function ensureODataColumnsAreText(string $tableName, array $columns): void
    {
        // NO-OP.
        // Previously this altered all columns to NVARCHAR(MAX) / LONGTEXT to avoid type strictness.
        // However, MySQL has a strict 8126 byte inline row size limit.
        // 400+ LONGTEXT columns will exceed this limit and crash the database.
        // By relying on the initial type inference (boolean, decimal, bigInt, text), 
        // we keep the row size small and stable.
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
            fn (string $key) => ! str_starts_with($key, '@') && ! str_starts_with($key, 'odata.')
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
     */
    public function cleanRecord(array $record): array
    {
        $clean = [];

        foreach ($record as $key => $value) {
            if (str_starts_with($key, '@') || str_starts_with($key, 'odata.')) {
                continue;
            }

            $colName = $this->sanitizeColumnName($key);

            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            // OData v4 returns Edm.Binary (like tstamp) as Base64.
            // Convert it to a standard Hex string (e.g., 0x00000000397677AB)
            if (strtolower($colName) === 'tstamp' && is_string($value)) {
                $decoded = base64_decode($value, true);
                if ($decoded !== false) {
                    $value = '0x' . strtoupper(bin2hex($decoded));
                }
            }

            $clean[$colName] = $value;
        }

        $clean['_synced_at'] = now()->toDateTimeString();

        return $clean;
    }

    /**
     * Clean multiple OData records.
     */
    public function cleanRecords(array $records): array
    {
        return array_map(fn (array $row) => $this->cleanRecord($row), $records);
    }

    /**
     * Check if a table exists.
     */
    public function tableExists(string $tableName): bool
    {
        return Schema::hasTable($tableName);
    }

    /**
     * Get the row count for a table.
     */
    public function getRowCount(string $tableName): int
    {
        if (! $this->tableExists($tableName)) {
            return 0;
        }

        return DB::table($tableName)->count();
    }
}
