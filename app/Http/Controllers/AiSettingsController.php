<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AiSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AiSettingsController extends Controller
{
    /**
     * Middleware could be applied here or in routes/web.php
     * We will rely on routes/web.php with appropriate middleware to enforce 'ai_settings.view' etc.
     */
    public function index(Request $request)
    {
        if (!$request->user()->hasPermission('ai_settings.view')) {
            abort(403, 'Unauthorized.');
        }

        $isConfigured = AiSetting::where('key', 'openrouter_api_key')->exists();
        $baseUrl = AiSetting::getConfig('openrouter_base_url', 'https://openrouter.ai/api/v1');
        $model = AiSetting::getConfig('openrouter_model', 'openai/gpt-4o');
        $timeout = AiSetting::getConfig('openrouter_timeout', 30);
        $lastTest = AiSetting::getConfig('openrouter_last_test_result', 'Never');
        $lastTestTime = AiSetting::getConfig('openrouter_last_test_time', '');
        $analyticsConnectionId = AiSetting::getConfig('analytics_connection_id');
        $connections = \App\Models\Connection::all();

        return view('settings.ai.openrouter', compact(
            'isConfigured', 'baseUrl', 'model', 'timeout', 'lastTest', 'lastTestTime', 'analyticsConnectionId', 'connections'
        ));
    }

    public function update(Request $request)
    {
        if (!$request->user()->hasPermission('ai_settings.update')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'api_key' => 'nullable|string',
            'base_url' => 'required|url',
            'model' => 'required|string',
            'timeout' => 'required|integer|min:5|max:120',
            'analytics_connection_id' => 'required|exists:connections,id',
        ]);

        if (!empty($validated['api_key'])) {
            AiSetting::setConfig('openrouter_api_key', $validated['api_key'], true);
        }

        AiSetting::setConfig('openrouter_base_url', $validated['base_url']);
        AiSetting::setConfig('openrouter_model', $validated['model']);
        AiSetting::setConfig('openrouter_timeout', $validated['timeout']);
        AiSetting::setConfig('analytics_connection_id', $validated['analytics_connection_id']);

        return redirect()->back()->with('success', 'AI configuration saved successfully.');
    }

    public function disconnect(Request $request)
    {
        if (!$request->user()->hasPermission('ai_settings.update')) {
            abort(403, 'Unauthorized.');
        }

        AiSetting::where('key', 'openrouter_api_key')->delete();
        AiSetting::setConfig('openrouter_last_test_result', 'Disconnected');
        AiSetting::setConfig('openrouter_last_test_time', now()->format('Y-m-d H:i:s'));

        return redirect()->back()->with('success', 'Successfully disconnected from OpenRouter. AI features are now disabled.');
    }

    public function testConnection(Request $request)
    {
        if (!$request->user()->hasPermission('ai_settings.test')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $inputKey = $request->input('api_key');
        // If empty in request, fallback to DB
        $apiKey = !empty($inputKey) ? $inputKey : AiSetting::getConfig('openrouter_api_key', null, true);
        
        if (!$apiKey) {
            return response()->json([
                'success' => false, 
                'reason' => 'missing_api_key',
                'message' => 'API Key OpenRouter tidak ditemukan atau tidak dikonfigurasi.'
            ]);
        }

        $model = $request->input('model');
        $baseUrl = $request->input('base_url', 'https://openrouter.ai/api/v1');
        $timeout = (int) $request->input('timeout', 30);

        $routingService = app(\App\Services\AI\OpenRouterRoutingService::class);
        $routingPayload = $routingService->resolveRoutingPayload($model);

        $payload = [
            'messages' => [
                ['role' => 'system', 'content' => 'You are a connectivity test. Reply with OK.'],
                ['role' => 'user', 'content' => 'Respond with OK.']
            ],
            'max_tokens' => 10
        ];
        
        $payload['model'] = $routingPayload['model'];

        if (isset($routingPayload['provider'])) {
            $payload['provider'] = $routingPayload['provider'];
        }

        \Illuminate\Support\Facades\Log::info('OpenRouter Test Payload: ' . json_encode($payload));

        try {
            $startTime = microtime(true);
            $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
                ->timeout($timeout)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name'),
                ])
                ->post(rtrim($baseUrl, '/') . '/chat/completions', $payload);
            
            \Illuminate\Support\Facades\Log::info('OpenRouter Test Response: ' . $response->body());
            
            $durationMs = round((microtime(true) - $startTime) * 1000);
            $now = now()->format('Y-m-d H:i:s');
            
            if ($response->successful()) {
                $data = $response->json();
                $providerUsed = $data['provider'] ?? 'Unknown';
                $modelUsed = $data['model'] ?? $model;
                
                AiSetting::setConfig('openrouter_last_test_result', 'Success');
                AiSetting::setConfig('openrouter_last_test_time', $now);
                
                return response()->json([
                    'success' => true,
                    'status' => 'Ready',
                    'model' => $modelUsed,
                    'provider' => $providerUsed,
                    'latency_ms' => $durationMs,
                    'message' => 'Konfigurasi valid dan siap digunakan.'
                ]);
            }

            $status = $response->status();
            $body = $response->body();
            
            AiSetting::setConfig('openrouter_last_test_result', 'Failed');
            AiSetting::setConfig('openrouter_last_test_time', $now);

            if ($status === 401 || $status === 403) {
                return response()->json([
                    'success' => false, 
                    'reason' => 'invalid_api_key', 
                    'message' => 'API Key OpenRouter tidak valid atau tidak lagi aktif.'
                ]);
            }

            if ($status === 429) {
                return response()->json([
                    'success' => false, 
                    'reason' => 'rate_limited', 
                    'message' => 'OpenRouter tidak merespons dalam batas waktu yang ditentukan.'
                ]);
            }

            if ($status === 404 && str_contains($body, 'No endpoints available matching your guardrail restrictions and data policy')) {
                return response()->json([
                    'success' => false,
                    'reason' => 'no_compliant_endpoint',
                    'message' => 'Tidak ada endpoint AI yang kompatibel dengan kebijakan keamanan data aplikasi untuk model yang dipilih. Silakan pilih model lain.'
                ]);
            }
            
            if (str_contains($body, 'does not exist') || $status === 404) {
                return response()->json([
                    'success' => false, 
                    'reason' => 'invalid_model', 
                    'message' => 'Model yang dipilih tidak ditemukan atau tidak tersedia.'
                ]);
            }

            return response()->json([
                'success' => false, 
                'reason' => 'provider_error', 
                'message' => 'Provider error. HTTP ' . $status
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $now = now()->format('Y-m-d H:i:s');
            AiSetting::setConfig('openrouter_last_test_result', 'Timeout');
            AiSetting::setConfig('openrouter_last_test_time', $now);
            return response()->json([
                'success' => false, 
                'reason' => 'timeout', 
                'message' => 'OpenRouter tidak merespons dalam batas waktu yang ditentukan.'
            ]);
        } catch (\Exception $e) {
            $now = now()->format('Y-m-d H:i:s');
            AiSetting::setConfig('openrouter_last_test_result', 'Error');
            AiSetting::setConfig('openrouter_last_test_time', $now);
            return response()->json([
                'success' => false, 
                'reason' => 'connection_error', 
                'message' => 'Tidak dapat terhubung ke OpenRouter.'
            ]);
        }
    }

    public function getModelProviders(Request $request, $modelId)
    {
        if (!$request->user()->hasPermission('ai_settings.view')) {
            abort(403, 'Unauthorized.');
        }

        // We decode the model ID as it might have slashes (e.g., openai/gpt-4o)
        $modelId = urldecode($modelId);
        
        $routingService = app(\App\Services\AI\OpenRouterRoutingService::class);
        $providers = $routingService->getModelProviders($modelId);

        return response()->json(['success' => true, 'providers' => $providers]);
    }

    public function metadataIndex(Request $request)
    {
        $connectionId = \App\Models\AiSetting::getConfig('analytics_connection_id');
        
        $connectionName = 'None';
        if ($connectionId) {
            $connection = \App\Models\Connection::find($connectionId);
            if ($connection) {
                $connectionName = $connection->name;
            }
        }

        // System table prefixes to hide by default
        $systemPrefixes = ['ai_', 'jobs', 'failed_jobs', 'password_reset', 'personal_access', 'cache', 'sessions', 'migrations'];

        $query = \Illuminate\Support\Facades\DB::table('sync_metadata_definitions');
        if ($connectionId) {
            $query->where('connection_id', $connectionId);
        }

        $allTables = $query->get();

        // Separate system from business tables
        $tables = [];
        $systemTables = [];
        foreach ($allTables as $table) {
            $isSystem = false;
            foreach ($systemPrefixes as $prefix) {
                if (str_starts_with(strtolower($table->entity_key), $prefix)) {
                    $isSystem = true;
                    break;
                }
            }
            if ($isSystem) {
                $systemTables[] = $table;
            } else {
                $tables[] = $table;
            }
        }

        // Apply search filter
        $search = $request->query('search');
        if ($search) {
            $search = strtolower($search);
            $tables = array_values(array_filter($tables, function ($t) use ($search) {
                return str_contains(strtolower($t->entity_key), $search)
                    || ($t->logical_name && str_contains(strtolower($t->logical_name), $search))
                    || ($t->description && str_contains(strtolower($t->description), $search));
            }));
        }

        // Apply role filter
        $roleFilter = $request->query('role');
        if ($roleFilter && $roleFilter !== 'all') {
            $tables = array_values(array_filter($tables, fn($t) => ($t->data_role ?? 'other') === $roleFilter));
        }

        // Apply status filter
        $statusFilter = $request->query('status');
        if ($statusFilter === 'enabled') {
            $tables = array_values(array_filter($tables, fn($t) => $t->chatedp_enabled));
        } elseif ($statusFilter === 'disabled') {
            $tables = array_values(array_filter($tables, fn($t) => !$t->chatedp_enabled));
        }

        // Show system tables if requested
        $showSystem = $request->query('show_system');
        if ($showSystem) {
            $tables = array_merge($tables, $systemTables);
        }

        $dataRoles = [
            'sales_transaction' => 'Sales Transaction',
            'customer_master' => 'Customer Master',
            'product_master' => 'Product Master',
            'inventory' => 'Inventory',
            'reference' => 'Reference / Lookup',
            'other' => 'Other',
            'ignore' => 'Ignore',
        ];

        return view('settings.ai.metadata', compact(
            'tables', 'connectionId', 'connectionName', 'dataRoles',
            'search', 'roleFilter', 'statusFilter', 'showSystem',
            'systemTables'
        ));
    }

    public function metadataUpdate(Request $request, $id)
    {
        $validated = $request->validate([
            'logical_name' => 'nullable|string|max:100',
            'aliases' => 'nullable|string',
            'description' => 'nullable|string',
            'data_role' => 'required|string|in:sales_transaction,customer_master,product_master,inventory,reference,other,ignore',
            'business_domains' => 'nullable|array',
            'business_domains.*' => 'string',
            'chatedp_enabled' => 'nullable|boolean',
        ]);

        $aliasesArray = [];
        if (!empty($validated['aliases'])) {
            $aliasesArray = array_values(array_filter(array_map('trim', explode("\n", $validated['aliases']))));
        }

        $semanticProfile = [
            'data_role' => $validated['data_role'],
            'logical_name' => $validated['logical_name'],
            'business_domains' => $validated['business_domains'] ?? [],
            'aliases' => $aliasesArray,
            'metrics' => [],
            'dimensions' => []
        ];

        \Illuminate\Support\Facades\DB::table('sync_metadata_definitions')->where('id', $id)->update([
            'logical_name' => $validated['logical_name'],
            'aliases' => empty($aliasesArray) ? null : json_encode($aliasesArray),
            'description' => $validated['description'],
            'data_role' => $validated['data_role'],
            'business_domains' => isset($validated['business_domains']) ? json_encode($validated['business_domains']) : null,
            'chatedp_enabled' => $request->has('chatedp_enabled') ? 1 : 0,
            // we will update semantic_profile again below if there are metrics/dimensions
            'semantic_profile' => json_encode($semanticProfile),
        ]);

        // Handle semantic metric/dimension configuration for any data role
        $def = \Illuminate\Support\Facades\DB::table('sync_metadata_definitions')->find($id);
        if ($def) {
            $connectionId = $def->connection_id;
            $sourceTable = $def->entity_key;

            // Save semantic metrics
            $metricNames = $request->input('metric_names', []);
            $metricColumns = $request->input('metric_columns', []);
            $metricAliases = $request->input('metric_aliases', []);
            $metricAggregations = $request->input('metric_aggregations', []);
            $metricExpressions = $request->input('metric_expressions', []);
            $metricDescriptions = $request->input('metric_descriptions', []);
            $defaultMetricIndex = $request->input('metric_default', -1);

            // Delete existing metrics for this table
            \App\Models\AiSemanticDefinition::where('connection_id', $connectionId)
                ->where('source_table', $sourceTable)
                ->where('type', 'metric')
                ->delete();

            foreach ($metricNames as $i => $name) {
                $col = $metricColumns[$i] ?? null;
                $aliasesStr = $metricAliases[$i] ?? '';
                $agg = $metricAggregations[$i] ?? 'SUM';
                $expr = trim($metricExpressions[$i] ?? '');
                $desc = trim($metricDescriptions[$i] ?? '');
                $isDefault = ((string)$i === (string)$defaultMetricIndex);
                $name = trim($name);

                if (!empty($name) && !empty($col)) {
                    $aliasesArr = array_values(array_filter(array_map('trim', explode(',', $aliasesStr))));
                    
                    // If no custom expression provided, generate a simple one
                    $expression = !empty($expr) ? $expr : strtoupper(trim($agg)) . '(' . $col . ')';

                    $semanticProfile['metrics'][] = [
                        'name' => $name,
                        'source_column' => $col,
                        'aggregation' => strtoupper(trim($agg)),
                        'aliases' => $aliasesArr,
                        'is_default' => $isDefault
                    ];

                    \App\Models\AiSemanticDefinition::updateOrCreate(
                        [
                            'connection_id' => $connectionId,
                            'source_table' => $sourceTable,
                            'type' => 'metric',
                            'name' => $name,
                        ],
                        [
                            'description' => !empty($desc) ? $desc : null,
                            'expression' => $expression,
                            'source_column' => $col,
                            'metadata' => [
                                'aliases' => $aliasesArr,
                                'aggregation' => strtoupper(trim($agg)),
                                'is_default' => $isDefault
                            ]
                        ]
                    );
                }
            }

            // Save semantic dimensions
            $dimNames = $request->input('dimension_names', []);
            $dimColumns = $request->input('dimension_columns', []);
            $dimAliases = $request->input('dimension_aliases', []);

            \App\Models\AiSemanticDefinition::where('connection_id', $connectionId)
                ->where('source_table', $sourceTable)
                ->where('type', 'dimension')
                ->delete();

            foreach ($dimNames as $i => $name) {
                $col = $dimColumns[$i] ?? null;
                $aliasesStr = $dimAliases[$i] ?? '';
                $name = trim($name);
                
                if (!empty($name) && !empty($col)) {
                    $aliasesArr = array_values(array_filter(array_map('trim', explode(',', $aliasesStr))));
                    
                    $semanticProfile['dimensions'][] = [
                        'name' => $name,
                        'source_column' => $col,
                        'aliases' => $aliasesArr
                    ];

                    \App\Models\AiSemanticDefinition::updateOrCreate(
                        [
                            'connection_id' => $connectionId,
                            'source_table' => $sourceTable,
                            'type' => 'dimension',
                            'name' => $name,
                        ],
                        [
                            'description' => null,
                            'expression' => $col,
                            'source_column' => $col,
                            'metadata' => [
                                'aliases' => $aliasesArr
                            ]
                        ]
                    );
                }
            }

            // Update semantic profile with dimensions and metrics included
            \Illuminate\Support\Facades\DB::table('sync_metadata_definitions')->where('id', $id)->update([
                'semantic_profile' => json_encode($semanticProfile),
            ]);
        }

        return back()->with('success', 'Table configuration saved.');
    }

    /**
     * API endpoint: return columns and semantic config for a specific table definition.
     */
    public function metadataDetail(Request $request, $id)
    {
        $def = \Illuminate\Support\Facades\DB::table('sync_metadata_definitions')->find($id);
        if (!$def) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $columns = \Illuminate\Support\Facades\DB::table('sync_metadata_fields')
            ->where('definition_id', $def->id)
            ->pluck('field_name')
            ->toArray();

        $metrics = \App\Models\AiSemanticDefinition::where('connection_id', $def->connection_id)
            ->where('source_table', $def->entity_key)
            ->where('type', 'metric')
            ->get(['id', 'name', 'description', 'expression', 'source_column', 'metadata']);

        $dimensions = \App\Models\AiSemanticDefinition::where('connection_id', $def->connection_id)
            ->where('source_table', $def->entity_key)
            ->where('type', 'dimension')
            ->get(['id', 'name', 'source_column', 'metadata']);

        return response()->json([
            'table' => $def,
            'columns' => $columns,
            'aliases' => $def->aliases ? json_decode($def->aliases, true) : [],
            'business_domains' => $def->business_domains ? json_decode($def->business_domains, true) : [],
            'metrics' => $metrics,
            'dimensions' => $dimensions,
        ]);
    }

    public function suggestMetadata(Request $request, $id)
    {
        $def = \Illuminate\Support\Facades\DB::table('sync_metadata_definitions')->find($id);
        if (!$def) {
            return response()->json(['error' => 'Table metadata not found.'], 404);
        }

        if (!$def->chatedp_enabled) {
            return response()->json([
                'error' => 'Table is not enabled for ChatEDP. Enable ChatEDP first before generating metric suggestions.',
                'metrics' => [],
                'dimensions' => []
            ], 422);
        }

        // Fetch physical fields and their semantic metadata
        $fields = \Illuminate\Support\Facades\DB::table('sync_metadata_fields')
            ->where('definition_id', $def->id)
            ->get(['field_name', 'data_type', 'semantic_type']);

        // Load existing AiSemanticDefinition records for duplicate protection
        $existingDefinitions = \App\Models\AiSemanticDefinition::where('connection_id', $def->connection_id)
            ->where('source_table', $def->entity_key)
            ->get();

        $existingMetricNames = $existingDefinitions->where('type', 'metric')->pluck('name')->map(fn($n) => strtolower($n))->toArray();
        $existingMetricCols = $existingDefinitions->where('type', 'metric')->pluck('source_column')->filter()->toArray();
        $existingDimCols = $existingDefinitions->where('type', 'dimension')->pluck('source_column')->filter()->toArray();

        $metrics = [];
        $dimensions = [];
        $dataRole = strtolower($def->data_role ?? 'other');

        foreach ($fields as $f) {
            $col = $f->field_name;
            $type = strtolower($f->semantic_type ?? '');
            $cleanName = ucwords(str_replace(['_', '-'], ' ', preg_replace('/(?<!^)[A-Z]/', ' $0', $col)));

            // --- Level 2: Contextual Candidates (high metadata evidence) ---
            if ($dataRole === 'sales_transaction' && in_array(strtolower($col), ['netamount', 'totalamount', 'linetotal', 'docamount'])) {
                if (!in_array('revenue', $existingMetricNames) && !in_array('total sales', $existingMetricNames)) {
                    $metrics[] = [
                        'name' => 'Revenue',
                        'source_column' => $col,
                        'aggregation' => 'SUM',
                        'expression' => "SUM({$col})",
                        'description' => "Total revenue calculated from {$col}",
                        'aliases' => ['sales', 'penjualan', 'total revenue', 'omset'],
                        'is_default' => count($metrics) === 0,
                        'reason' => "data_role=sales_transaction, field={$col}",
                        'confidence' => 'high'
                    ];
                    continue;
                }
            } elseif ($dataRole === 'sales_transaction' && in_array(strtolower($col), ['orderqty', 'qty', 'quantity'])) {
                if (!in_array('total quantity', $existingMetricNames) && !in_array('volume', $existingMetricNames)) {
                    $metrics[] = [
                        'name' => 'Total Quantity',
                        'source_column' => $col,
                        'aggregation' => 'SUM',
                        'expression' => "SUM({$col})",
                        'description' => "Total order quantity from {$col}",
                        'aliases' => ['qty', 'volume', 'jumlah pesanan', 'total qty'],
                        'is_default' => false,
                        'reason' => "data_role=sales_transaction, field={$col}",
                        'confidence' => 'high'
                    ];
                    continue;
                }
            }

            // --- Level 1: Generic Deterministic Candidates ---
            if ($type === 'amount' || $type === 'metric') {
                $metricName = 'Total ' . $cleanName;
                if (!in_array(strtolower($metricName), $existingMetricNames) && !in_array($col, $existingMetricCols)) {
                    $metrics[] = [
                        'name' => $metricName,
                        'source_column' => $col,
                        'aggregation' => 'SUM',
                        'expression' => "SUM({$col})",
                        'description' => "Sum of {$cleanName}",
                        'aliases' => [strtolower($cleanName), 'total ' . strtolower($cleanName)],
                        'is_default' => count($metrics) === 0,
                        'reason' => "semantic_type={$type}",
                        'confidence' => 'high'
                    ];
                }
            } elseif ($type === 'price' || $type === 'rate') {
                $metricName = 'Average ' . $cleanName;
                if (!in_array(strtolower($metricName), $existingMetricNames) && !in_array($col, $existingMetricCols)) {
                    $metrics[] = [
                        'name' => $metricName,
                        'source_column' => $col,
                        'aggregation' => 'AVG',
                        'expression' => "AVG({$col})",
                        'description' => "Average {$cleanName}",
                        'aliases' => ['rata-rata ' . strtolower($cleanName), 'avg ' . strtolower($cleanName)],
                        'is_default' => false,
                        'reason' => "semantic_type={$type}",
                        'confidence' => 'high'
                    ];
                }
            } elseif ($type === 'identifier') {
                $metricName = 'Count ' . $cleanName;
                if (!in_array(strtolower($metricName), $existingMetricNames)) {
                    $metrics[] = [
                        'name' => $metricName,
                        'source_column' => $col,
                        'aggregation' => 'COUNT',
                        'expression' => "COUNT(DISTINCT {$col})",
                        'description' => "Distinct count of {$cleanName}",
                        'aliases' => ['jumlah ' . strtolower($cleanName), 'count ' . strtolower($cleanName)],
                        'is_default' => false,
                        'reason' => "semantic_type=identifier",
                        'confidence' => 'medium'
                    ];
                }
            }

            // Dimensions (Categorical / Status / Dimensions)
            if (in_array($type, ['dimension', 'categorical', 'status'])) {
                if (!in_array($col, $existingDimCols)) {
                    $dimensions[] = [
                        'name' => $cleanName,
                        'source_column' => $col,
                        'aliases' => [strtolower($cleanName)]
                    ];
                }
            }
        }

        return response()->json([
            'metrics' => $metrics,
            'dimensions' => $dimensions
        ]);
    }

    public function refreshSchema(Request $request)
    {
        $connectionId = \App\Models\AiSetting::getConfig('analytics_connection_id');
        if (!$connectionId) {
            return back()->with('error', 'No analytics connection configured.');
        }

        $connection = \App\Models\Connection::find($connectionId);
        if (!$connection || !$connection->is_analytics_source) {
            return back()->with('error', 'Configured analytics connection is invalid or not an analytics source.');
        }

        try {
            $schemaCacheService = app(\App\Services\AI\SchemaCacheService::class);
            $schemaCacheService->cacheSchema($connection);
            
            $tablesCount = \Illuminate\Support\Facades\DB::table('sync_metadata_definitions')
                ->where('connection_id', $connectionId)->count();
                
            $columnsCount = \Illuminate\Support\Facades\DB::table('sync_metadata_fields')
                ->join('sync_metadata_definitions', 'sync_metadata_fields.definition_id', '=', 'sync_metadata_definitions.id')
                ->where('sync_metadata_definitions.connection_id', $connectionId)
                ->count();

            return back()->with('success', "Schema refreshed successfully. Discovered {$tablesCount} tables and {$columnsCount} columns.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to refresh schema: ' . $e->getMessage());
        }
    }
}
