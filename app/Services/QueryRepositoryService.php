<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\QueryRepository;
use App\Models\QueryRepositoryLog;
use Illuminate\Support\Facades\DB;
use Exception;

class QueryRepositoryService
{
    /**
     * Block harmful queries.
     * Only allows SELECT, WITH, EXEC, CALL and EXECUTE.
     */
    public function validateSql(string $sql): void
    {
        $blockedKeywords = [
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', 'RENAME', 'GRANT', 'REVOKE', 'REPLACE'
        ];

        // Use word boundary to avoid false positives (e.g. SELECT * FROM updates)
        $pattern = '/\b(' . implode('|', $blockedKeywords) . ')\b/i';

        if (preg_match($pattern, $sql, $matches)) {
            throw new Exception("Query contains forbidden keyword: " . strtoupper($matches[1]) . ". Only read-only queries are allowed.");
        }

        // Basic check for SELECT or EXEC
        if (!preg_match('/^\s*(SELECT|WITH|EXEC|EXECUTE|CALL)\b/i', $sql)) {
            throw new Exception("Query must start with SELECT, WITH, EXEC, EXECUTE, or CALL.");
        }
    }

    /**
     * Find parameters like {{branch}} in the SQL string.
     */
    public function extractParameters(string $sql): array
    {
        $parameters = [];
        // Matches {{param_name}} with optional spaces
        if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $sql, $matches)) {
            $uniqueParams = array_unique($matches[1]);
            foreach ($uniqueParams as $param) {
                // Infer basic type
                $type = 'string';
                if (str_contains(strtolower($param), 'date')) {
                    $type = 'date';
                } elseif (str_contains(strtolower($param), 'id')) {
                    $type = 'number';
                }
                
                $parameters[] = [
                    'name' => $param,
                    'type' => $type
                ];
            }
        }
        return array_values($parameters); // reset indices
    }

    /**
     * Preview SQL logic
     */
    public function executePreview(Connection $connection, string $sql, array $bindings = []): array
    {
        $this->validateSql($sql);

        // Replace {{params}} with actual bindings or dummy data for preview
        $parsedSql = $sql;
        if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $sql, $matches)) {
            $uniqueParams = array_unique($matches[1]);
            foreach ($uniqueParams as $param) {
                $rawVal = $bindings[$param] ?? '';
                if ($rawVal === '' || $rawVal === null) {
                    // Provide realistic default placeholder for preview if empty
                    if (str_contains(strtolower($param), 'date')) {
                        $rawVal = date('Y-m-d');
                    } else {
                        $rawVal = '%';
                    }
                }
                
                $escaped = addslashes((string)$rawVal);

                $patternToReplace = '/\{\{\s*' . preg_quote($param, '/') . '\s*\}\}/';
                while (preg_match($patternToReplace, $parsedSql, $m, PREG_OFFSET_CAPTURE)) {
                    $pos = $m[0][1];
                    $matchLen = strlen($m[0][0]);
                    
                    // Count unescaped single quotes before this position to determine if it's inside quotes
                    $before = substr($parsedSql, 0, $pos);
                    $beforeClean = str_replace("''", "", $before);
                    $beforeClean = str_replace("\\'", "", $beforeClean);
                    $quoteCount = substr_count($beforeClean, "'");
                    
                    if ($quoteCount % 2 !== 0) {
                        // Inside quotes: do not add extra quotes
                        $replacement = $escaped;
                    } else {
                        // Outside quotes: wrap non-numeric values in single quotes
                        $replacement = is_numeric($rawVal) ? $escaped : "'" . $escaped . "'";
                    }
                    
                    $parsedSql = substr_replace($parsedSql, $replacement, $pos, $matchLen);
                }
            }
        }

        // Add limit 100 if it's a SELECT and doesn't have LIMIT/TOP
        $cleanSql = trim(preg_replace('/--.*$/m', '', $parsedSql));
        $cleanSql = trim(preg_replace('/\/\*.*?\*\//s', '', $cleanSql)); // remove block comments

        $hasLimit = preg_match('/\bLIMIT\b/i', $cleanSql) || preg_match('/\bTOP\s+\d+/i', $cleanSql);

        if (preg_match('/^(SELECT|WITH)\b/i', $cleanSql) && !$hasLimit) {
            if (in_array($connection->type, ['mysql', 'pgsql', 'sqlite', 'local_env'])) {
                $parsedSql .= " LIMIT 100";
            } else if ($connection->type === 'sqlsrv') {
                // Inject TOP 100 after SELECT
                $parsedSql = preg_replace('/(\bSELECT\b)/i', '$1 TOP 100', $parsedSql, 1);
            }
        }

        // Establish DB connection dynamically
        $dbName = 'preview_connection_' . $connection->id;
        
        if ($connection->type === 'local_env') {
            $dbName = config('database.default');
        } elseif ($connection->type === 'odata') {
            throw new \Exception("Cannot execute raw SQL against OData connections. Please use a relational database connection.");
        } else {
            $config = is_string($connection->credentials) ? (json_decode($connection->credentials, true) ?? []) : (array) $connection->credentials;
            
            config(["database.connections.{$dbName}" => [
                'driver' => $connection->type,
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? '3306',
                'database' => $config['database'] ?? '',
                'username' => $config['username'] ?? '',
                'password' => $config['password'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false,
                'engine' => null,
                'trust_server_certificate' => true,
            ]]);
        }

        $start = microtime(true);
        $results = DB::connection($dbName)->select($parsedSql);
        $duration = microtime(true) - $start;

        if ($connection->type !== 'local_env') {
            DB::purge($dbName);
        }

        $resultsArray = array_map(function($item) {
            return (array) $item;
        }, $results);

        return [
            'data' => $resultsArray,
            'duration' => $duration,
            'count' => count($resultsArray),
            'columns' => $this->extractOutputColumns($resultsArray)
        ];
    }

    /**
     * Extract columns from preview data
     */
    public function extractOutputColumns(array $previewData): array
    {
        if (empty($previewData)) {
            return [];
        }

        $firstRow = $previewData[0];
        $columns = [];

        foreach ($firstRow as $key => $value) {
            $type = 'string';
            if (is_int($value)) $type = 'integer';
            if (is_float($value)) $type = 'decimal';
            if (is_bool($value)) $type = 'boolean';

            $columns[] = [
                'name' => $key,
                'alias' => $key,
                'datatype' => $type,
                'nullable' => true 
            ];
        }

        return $columns;
    }

    /**
     * Log execution
     */
    public function logExecution($repositoryId, $userId, $duration, $success, $errorMessage = null): void
    {
        QueryRepositoryLog::create([
            'query_repository_id' => $repositoryId,
            'user_id' => $userId,
            'executed_at' => now(),
            'duration' => $duration,
            'success' => $success,
            'error_message' => $errorMessage
        ]);
    }
}
