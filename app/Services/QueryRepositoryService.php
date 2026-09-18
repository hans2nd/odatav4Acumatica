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
     * Only allows SELECT and WITH.
     */
    public function validateSql(string $sql): void
    {
        // 1. Remove strings (single and double quotes) to prevent false positives in WHERE clauses
        $cleanSql = preg_replace('/\'[^\']*\'/', '', $sql);
        $cleanSql = preg_replace('/"[^"]*"/', '', $cleanSql);
        
        // 2. Remove comments to prevent bypasses
        $cleanSql = preg_replace('/--.*$/m', '', $cleanSql);
        $cleanSql = preg_replace('/\/\*.*?\*\//s', '', $cleanSql);
        $cleanSql = trim($cleanSql);

        $blockedKeywords = [
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', 'RENAME', 'GRANT', 'REVOKE', 'REPLACE', 'EXEC', 'EXECUTE', 'CALL', 'INTO', 'OUTFILE', 'DUMPFILE', 'MERGE', 'UPSERT'
        ];

        // Use word boundary to avoid false positives (e.g. SELECT * FROM updates)
        $pattern = '/\b(' . implode('|', $blockedKeywords) . ')\b/i';

        if (preg_match($pattern, $cleanSql, $matches)) {
            throw new Exception("Query contains forbidden keyword: " . strtoupper($matches[1]) . ". Only read-only queries are allowed.");
        }

        // Basic check for SELECT or WITH, allowing optional starting parenthesis
        if (!preg_match('/^\s*\(?\s*(SELECT|WITH)\b/i', $cleanSql)) {
            throw new Exception("Query must start with SELECT or WITH.");
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

        // Convert {{param}} to ? and build flat bindings array
        $parsedSql = $sql;
        $pdoBindings = [];
        
        if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $sql, $matches) > 0) {
            foreach ($matches[1] as $param) {
                $rawVal = $bindings[$param] ?? null;
                if ($rawVal === '' || $rawVal === null) {
                    if (str_contains(strtolower($param), 'date')) {
                        $rawVal = date('Y-m-d');
                    } else {
                        $rawVal = '%';
                    }
                }
                $pdoBindings[] = $rawVal;
            }
            $parsedSql = preg_replace('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', '?', $sql);
        } else {
            $pdoBindings = $bindings;
        }

        // Add limit 100 if it's a SELECT and doesn't have LIMIT/TOP
        $cleanSql = trim(preg_replace('/--.*$/m', '', $parsedSql));
        $cleanSql = trim(preg_replace('/\/\*.*?\*\//s', '', $cleanSql)); // remove block comments

        $hasLimit = preg_match('/\bLIMIT\b/i', $cleanSql) || preg_match('/\bTOP\s+\d+/i', $cleanSql);

        if (preg_match('/^(SELECT|WITH)\b/i', $cleanSql) && !$hasLimit) {
            if (in_array($connection->type, ['mysql', 'pgsql', 'sqlite', 'local_env'])) {
                $parsedSql .= " LIMIT 100";
            } else if ($connection->type === 'sqlsrv') {
                $parsedSql = preg_replace('/^(\s*SELECT\s+(?:DISTINCT\s+)?)/i', '$1 TOP 100 ', $parsedSql, 1);
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
        $results = DB::connection($dbName)->select($parsedSql, $pdoBindings);
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

    /**
     * Dedicated Analytics/AI execution layer
     */
    public function executeForAnalytics(string $sql, array $bindings = [], int $limit = 500): array
    {
        $this->validateSql($sql);

        // Administrator resolves the AI connection server-side
        $aiConnection = Connection::where('is_analytics_source', true)->first();
        if (!$aiConnection) {
            throw new Exception("No database connection has been designated as the Analytics/AI data source by the Administrator.");
        }

        // Check read-only enforcement
        if (!$aiConnection->read_only) {
            throw new Exception("The designated AI connection is not marked as read-only. Administrator must flag this connection as read-only for safety.");
        }

        $parsedSql = $sql;
        $pdoBindings = [];
        
        if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $sql, $matches) > 0) {
            foreach ($matches[1] as $param) {
                $pdoBindings[] = $bindings[$param] ?? null;
            }
            $parsedSql = preg_replace('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', '?', $sql);
        } else {
            $pdoBindings = $bindings;
        }

        $timeout = (int) env('ANALYTICS_QUERY_TIMEOUT', 30);
        $timeoutMs = $timeout * 1000;

        // Apply strict server-side row limit based on driver type
        if (in_array($aiConnection->type, ['mysql', 'pgsql', 'sqlite'])) {
            $parsedSql = "SELECT * FROM (" . rtrim($parsedSql, ';') . ") AS __analytics_wrap LIMIT {$limit}";
            if ($aiConnection->type === 'mysql') {
                // Safely inject query-scoped timeout hint into outer wrap
                $parsedSql = preg_replace('/^SELECT/i', "SELECT /*+ MAX_EXECUTION_TIME({$timeoutMs}) */", $parsedSql, 1);
            }
        } else if ($aiConnection->type === 'sqlsrv') {
            if (preg_match('/^\s*WITH\b/i', $parsedSql)) {
                throw new Exception("SQL Server queries containing CTEs (WITH) cannot be safely transformed for server-side row limits. Query rejected.");
            }
            // Remove existing TOP if any, inject strict TOP
            $parsedSql = preg_replace('/^(\s*SELECT\s+(?:DISTINCT\s+)?)(?:TOP\s+\d+\s+)?/i', '$1 TOP ' . $limit . ' ', $parsedSql, 1);
        }

        // Connect
        $dbName = 'analytics_connection_' . $aiConnection->id;
        $config = is_string($aiConnection->credentials) ? (json_decode($aiConnection->credentials, true) ?? []) : (array) $aiConnection->credentials;
        
        config(["database.connections.{$dbName}" => [
            'driver' => $aiConnection->type,
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

        $connection = DB::connection($dbName);
        $pdo = $connection->getPdo();
        $originalSqlsrvTimeout = null;

        // Setup query-scoped timeout
        if ($aiConnection->type === 'pgsql') {
            // Local timeout inside a transaction guarantees it won't leak
            $connection->beginTransaction();
            $connection->statement("SET LOCAL statement_timeout = {$timeoutMs}");
        } else if ($aiConnection->type === 'sqlsrv' && $pdo) {
            $originalSqlsrvTimeout = $pdo->getAttribute(\PDO::SQLSRV_ATTR_QUERY_TIMEOUT);
            $pdo->setAttribute(\PDO::SQLSRV_ATTR_QUERY_TIMEOUT, $timeout);
        }

        $start = microtime(true);
        $results = [];

        try {
            $results = $connection->select($parsedSql, $pdoBindings);
        } finally {
            // Guarantee cleanup and rollback of timeout scopes
            if ($aiConnection->type === 'pgsql') {
                $connection->rollBack();
            } else if ($aiConnection->type === 'sqlsrv' && $pdo && $originalSqlsrvTimeout !== null) {
                $pdo->setAttribute(\PDO::SQLSRV_ATTR_QUERY_TIMEOUT, $originalSqlsrvTimeout);
            }
            DB::purge($dbName);
        }

        $duration = microtime(true) - $start;

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
     * Reusable execution layer for dynamic queries (Query Builder)
     * Preserves SQL security validation and centralized connection resolution.
     */
    public function executeDynamic(string $connKey, string $sql, array $bindings = []): array
    {
        $this->validateSql($sql);
        $connName = app(\App\Services\ConnectionService::class)->resolveConnectionName($connKey);
        
        $start = microtime(true);
        $results = DB::connection($connName)->select($sql, $bindings);
        $duration = microtime(true) - $start;

        return [
            'data' => $results,
            'duration' => $duration
        ];
    }

    /**
     * Match a validated QueryPlan against saved QueryRepository queries
     */
    public function findCompatibleRepositoryQuery(\App\DTO\AI\QueryPlan $plan, Connection $connection): array
    {
        $repos = QueryRepository::where('database_connection_id', $connection->id)
            ->where('is_active', true)
            ->whereNotNull('ui_state_json')
            ->get();

        foreach ($repos as $repo) {
            $uiState = is_string($repo->ui_state_json) ? json_decode($repo->ui_state_json, true) : $repo->ui_state_json;
            if (!$uiState || !isset($uiState['tables']) || !isset($uiState['columns'])) {
                continue;
            }

            // 0. Contract mismatch checks
            // Repositories currently do not support comparison queries natively
            if (!empty($plan->comparison)) {
                continue;
            }
            
            // Limit match
            $repoLimit = (int)($uiState['limit'] ?? 500);
            $planLimit = (int)($plan->limit ?? 500);
            if ($repoLimit !== $planLimit) {
                continue;
            }

            // Sorts match
            $repoSorts = $uiState['orderBy'] ?? [];
            $planSorts = $plan->sorts ?? [];
            if (count($repoSorts) !== count($planSorts)) {
                continue;
            }

            $sortMismatch = false;
            foreach ($planSorts as $idx => $pSort) {
                $pField = trim($pSort['field'] ?? $pSort['metric'] ?? $pSort['column'] ?? '');
                $pDir = strtoupper(trim($pSort['direction'] ?? 'ASC'));
                
                $rSort = $repoSorts[$idx] ?? [];
                $rField = trim($rSort['field'] ?? '');
                $rDir = strtoupper(trim($rSort['direction'] ?? 'ASC'));
                
                if ($pDir !== $rDir) {
                    $sortMismatch = true;
                    break;
                }
                
                $rFieldParts = explode('.', $rField);
                $rFieldUnqualified = end($rFieldParts);
                
                if (strtolower($pField) !== strtolower($rField) && strtolower($pField) !== strtolower($rFieldUnqualified)) {
                    $sortMismatch = true;
                    break;
                }
            }
            if ($sortMismatch) {
                continue;
            }

            // 1. Tables match exactly
            $repoTables = $uiState['tables'];
            $planTables = $plan->tables;
            if (count(array_diff($repoTables, $planTables)) > 0 || count(array_diff($planTables, $repoTables)) > 0) {
                continue;
            }

            // 2. Columns (Dimensions vs Metrics)
            $repoDims = [];
            $repoMetrics = [];
            foreach ($uiState['columns'] as $col) {
                if (!empty($col['aggregate'])) {
                    $repoMetrics[] = [
                        'name' => $col['field'],
                        'aggregation' => strtoupper($col['aggregate'])
                    ];
                } else {
                    $repoDims[] = $col['field'];
                }
            }

            $planDims = $plan->dimensions ?? [];
            if (count(array_diff($repoDims, $planDims)) > 0 || count(array_diff($planDims, $repoDims)) > 0) {
                continue;
            }

            // Metrics exact match
            $planMetrics = $plan->metrics ?? [];
            if (count($repoMetrics) !== count($planMetrics)) {
                continue;
            }
            
            $metricMismatch = false;
            foreach ($repoMetrics as $rm) {
                $found = false;
                foreach ($planMetrics as $pm) {
                    if ($rm['name'] === $pm['name'] && $rm['aggregation'] === strtoupper($pm['aggregation'] ?? 'SUM')) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $metricMismatch = true;
                    break;
                }
            }
            if ($metricMismatch) {
                continue;
            }

            // 3. Filters Match exactly
            $repoFilters = $uiState['filters'] ?? [];
            $planFilters = $plan->filters ?? [];
            if (count($repoFilters) !== count($planFilters)) {
                continue;
            }

            $mappedBindings = [];
            $filterMismatch = false;
            $usedRepoFilters = [];

            foreach ($planFilters as $pFilter) {
                $pField = trim($pFilter['field'] ?? '');
                $pOp = $this->normalizeOperator($pFilter['operator'] ?? '=');
                $pVal = (string)($pFilter['value'] ?? '');
                
                $candidates = [];
                
                foreach ($repoFilters as $idx => $rFilter) {
                    if (in_array($idx, $usedRepoFilters)) {
                        continue;
                    }
                    
                    $rFieldRaw = trim($rFilter['field'] ?? '');
                    $rOp = $this->normalizeOperator($rFilter['operator'] ?? 'eq');
                    
                    if ($rOp !== $pOp) {
                        continue;
                    }
                    
                    // Case 1: Exact string match
                    if (strtolower($rFieldRaw) === strtolower($pField)) {
                        $candidates[] = ['idx' => $idx, 'filter' => $rFilter, 'exact' => true];
                    } 
                    // Case 2: Unqualified match (repo has Table.Field, plan has Field)
                    else if (str_contains($rFieldRaw, '.')) {
                        $parts = explode('.', $rFieldRaw);
                        $rFieldUnqualified = end($parts);
                        
                        if (strtolower($rFieldUnqualified) === strtolower($pField)) {
                            $candidates[] = ['idx' => $idx, 'filter' => $rFilter, 'exact' => false];
                        }
                    }
                }
                
                $exactCandidates = array_filter($candidates, fn($c) => $c['exact']);
                if (count($exactCandidates) > 0) {
                    $candidates = $exactCandidates;
                }
                
                if (count($candidates) === 0 || count($candidates) > 1) {
                    $filterMismatch = true;
                    break;
                }
                
                $match = array_values($candidates)[0];
                $rFilter = $match['filter'];
                $rVal = $rFilter['value'] ?? '';

                if (preg_match('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $rVal, $matches)) {
                    $mappedBindings[$matches[1]] = $pFilter['value'] ?? null;
                    $usedRepoFilters[] = $match['idx'];
                } else {
                    if ((string)$rVal === $pVal) {
                        $usedRepoFilters[] = $match['idx'];
                    } else {
                        $filterMismatch = true;
                        break;
                    }
                }
            }
            
            if ($filterMismatch) {
                continue;
            }

            return [
                'matched' => true,
                'repository_id' => $repo->id,
                'sql' => $repo->sql_text,
                'bindings' => $mappedBindings,
                'reason' => 'compatible_definition'
            ];
        }

        return ['matched' => false];
    }

    private function normalizeOperator(string $op): string
    {
        $map = [
            'eq' => '=', 'equals' => '=', '=' => '=',
            'ne' => '!=', 'neq' => '!=', '!=' => '!=', '<>' => '!=',
            'gt' => '>', '>' => '>',
            'lt' => '<', '<' => '<',
            'ge' => '>=', '>=' => '>=',
            'le' => '<=', '<=' => '<=',
            'contains' => 'LIKE', 'startswith' => 'LIKE', 'endswith' => 'LIKE', 'like' => 'LIKE',
            'is_null' => 'IS NULL', 'not_null' => 'IS NOT NULL',
            'is null' => 'IS NULL', 'is not null' => 'IS NOT NULL'
        ];
        return $map[strtolower(trim($op))] ?? '=';
    }

    /**
     * Reusable execution layer for streaming (Report Builder & Endpoint API)
     * Preserves SQL security validation and memory-safe streaming.
     */
    public function executeStream(string $connKey, string $sql, array $bindings = [])
    {
        $this->validateSql($sql);
        $connName = app(\App\Services\ConnectionService::class)->resolveConnectionName($connKey);
        
        return DB::connection($connName)->cursor($sql, $bindings);
    }

    /**
     * Reusable execution layer for UI grids and paginated reports.
     * Parses {{parameters}} and returns a Query Builder instance.
     */
    public function executeBuilder(string $connKey, string $sql, array $bindings = [])
    {
        $this->validateSql($sql);
        $connName = app(\App\Services\ConnectionService::class)->resolveConnectionName($connKey);

        $parsedSql = $sql;
        $pdoBindings = [];
        
        if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $sql, $matches) > 0) {
            foreach ($matches[1] as $param) {
                $pdoBindings[] = $bindings[$param] ?? null;
            }
            $parsedSql = preg_replace('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', '?', $sql);
        }

        if (in_array(app(\App\Services\ConnectionService::class)->get($connKey)['type'] ?? '', ['sqlsrv'])) {
            if (preg_match('/^\s*WITH\b/i', $parsedSql)) {
                throw new \Exception("SQL Server queries containing CTEs (WITH) cannot be used as a dynamic Menu/Report source. Query rejected.");
            }
        }

        $query = DB::connection($connName)->query()->fromSub($parsedSql, 'subquery');
        
        if (!empty($pdoBindings)) {
            $query->addBinding($pdoBindings, 'from');
        }

        return $query;
    }
}
