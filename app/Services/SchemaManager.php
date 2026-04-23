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

        $table->text($name)->nullable();
        return;

        if (is_null($value)) {
            $table->string($name, 255)->nullable();
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

        if (is_array($value) || is_object($value)) {
            $table->text($name)->nullable();
            return;
        }

        // String — try to detect sub-types
        $str = (string) $value;

        // ISO datetime
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $str)) {
            $table->string($name, 50)->nullable();
            return;
        }

        // GUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $str)) {
            $table->string($name, 36)->nullable();
            return;
        }

        // Long text
        $len = mb_strlen($str);
        if ($len > 500) {
            $table->text($name)->nullable();
            return;
        }

        // Default string
        $table->string($name, max(255, $len * 2))->nullable();
    }

    /**
     * OData DAC values are not always type-stable across rows. Store mirrored
     * fields as NVARCHAR(MAX) so later rows cannot fail numeric/date conversion.
     */
    private function ensureODataColumnsAreText(string $tableName, array $columns): void
    {
        foreach ($columns as $originalName) {
            $colName = $this->sanitizeColumnName($originalName);

            if (in_array(strtolower($colName), ['_sync_id', '_synced_at'])) {
                continue;
            }

            $column = DB::table('INFORMATION_SCHEMA.COLUMNS')
                ->selectRaw('DATA_TYPE as data_type, CHARACTER_MAXIMUM_LENGTH as character_maximum_length')
                ->where('TABLE_NAME', $tableName)
                ->where('COLUMN_NAME', $colName)
                ->first();

            if (! $column) {
                continue;
            }

            $type = strtolower((string) $column->data_type);
            $length = (int) $column->character_maximum_length;

            if ($type === 'nvarchar' && $length === -1) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE %s ALTER COLUMN %s NVARCHAR(MAX) NULL',
                $this->wrapSqlServerIdentifier($tableName),
                $this->wrapSqlServerIdentifier($colName),
            ));
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

    private function wrapSqlServerIdentifier(string $name): string
    {
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
