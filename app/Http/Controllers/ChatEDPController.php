<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiAuditLog;
use App\Models\AiUsageMetric;
use App\Models\Connection;
use App\Services\AI\QueryPlannerService;
use App\Services\AI\DateContextResolverService;
use App\Services\AI\QueryValidatorService;
use App\Services\AI\QueryCostGuardService;
use App\Services\AI\QueryCompilerService;
use App\Services\AI\QueryLibraryService;
use App\Services\AI\OpenRouterClient;
use App\Services\AI\SemanticLearningService;
use App\Services\AI\ResultInterpretationService;
use App\Services\AI\QueryRecognitionService;
use App\Models\QueryRepository;
use App\Services\QueryRepositoryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChatEDPController extends Controller
{
    protected $planner;
    protected $dateResolver;
    protected $validator;
    protected $costGuard;
    protected $compiler;
    protected $libraryService;
    protected $executor;
    protected $openRouter;
    protected $learningService;
    protected $interpreter;
    protected $recognizer;

    public function __construct(
        QueryPlannerService $planner,
        DateContextResolverService $dateResolver,
        QueryValidatorService $validator,
        QueryCostGuardService $costGuard,
        QueryCompilerService $compiler,
        QueryLibraryService $libraryService,
        QueryRepositoryService $executor,
        OpenRouterClient $openRouter,
        SemanticLearningService $learningService,
        ResultInterpretationService $interpreter,
        QueryRecognitionService $recognizer
    ) {
        $this->planner = $planner;
        $this->dateResolver = $dateResolver;
        $this->validator = $validator;
        $this->costGuard = $costGuard;
        $this->compiler = $compiler;
        $this->libraryService = $libraryService;
        $this->executor = $executor;
        $this->openRouter = $openRouter;
        $this->learningService = $learningService;
        $this->interpreter = $interpreter;
        $this->recognizer = $recognizer;
    }

    public function index(Request $request)
    {
        $connectionId = \App\Models\AiSetting::getConfig('analytics_connection_id');
        $suggestedQuestions = [];
        
        if ($connectionId) {
            $suggestedQuestions = $this->getSuggestedQuestions($connectionId);
        }

        return view('chatedp.index', compact('suggestedQuestions'));
    }

    protected function getSuggestedQuestions(int $connectionId): array
    {
        $suggestedQuestions = [];
        // Get enabled tables only
        $enabledTables = \Illuminate\Support\Facades\DB::table('sync_metadata_definitions')
            ->where('connection_id', $connectionId)
            ->where('chatedp_enabled', true)
            ->pluck('entity_key');
            
        if ($enabledTables->isEmpty()) {
            return [];
        }

        $definitions = \App\Models\AiSemanticDefinition::where('connection_id', $connectionId)
            ->whereIn('source_table', $enabledTables)
            ->get();
            
        $metrics = $definitions->where('type', 'metric');
        $dimensions = $definitions->where('type', 'dimension');
        
        $primaryMetric = $metrics->first(function($m) {
            return !empty($m->metadata['is_default']);
        }) ?? $metrics->first();
        
        // Fallback to categorical field dimension only if no AiSemanticDefinition dimension
        $primaryDimension = $dimensions->first();
        if (!$primaryDimension) {
            $dimField = \Illuminate\Support\Facades\DB::table('sync_metadata_fields')
                ->join('sync_metadata_definitions', 'sync_metadata_fields.definition_id', '=', 'sync_metadata_definitions.id')
                ->where('sync_metadata_definitions.connection_id', $connectionId)
                ->whereIn('sync_metadata_definitions.entity_key', $enabledTables)
                ->whereIn('sync_metadata_fields.semantic_type', ['dimension', 'categorical'])
                ->first();
            if ($dimField) {
                $primaryDimension = (object)[
                    'name' => $dimField->logical_name ?: ucwords(str_replace(['_', '-'], ' ', preg_replace('/(?<!^)[A-Z]/', ' $0', $dimField->field_name)))
                ];
            }
        }
        
        // Check if any date/datetime field exists in enabled tables
        $hasDateField = \Illuminate\Support\Facades\DB::table('sync_metadata_fields')
            ->join('sync_metadata_definitions', 'sync_metadata_fields.definition_id', '=', 'sync_metadata_definitions.id')
            ->where('sync_metadata_definitions.connection_id', $connectionId)
            ->whereIn('sync_metadata_definitions.entity_key', $enabledTables)
            ->where(function($q) {
                $q->whereIn('sync_metadata_fields.semantic_type', ['date', 'datetime'])
                  ->orWhereIn('sync_metadata_fields.data_type', ['date', 'datetime', 'timestamp', 'datetime2', 'smalldatetime'])
                  ->orWhere('sync_metadata_fields.field_name', 'like', '%date%')
                  ->orWhere('sync_metadata_fields.field_name', 'like', '%tanggal%');
            })
            ->exists();

        if ($primaryMetric) {
            $metricName = $primaryMetric->name;
            
            // 1. Metric + Date (if date capability exists)
            if ($hasDateField) {
                $suggestedQuestions[] = [
                    'title' => "{$metricName} Bulan Ini",
                    'prompt' => "Berapa {$metricName} bulan ini?"
                ];
            } else {
                // Metric-only scalar (if no date field exists)
                $suggestedQuestions[] = [
                    'title' => "Total {$metricName}",
                    'prompt' => "Berapa total {$metricName}?"
                ];
            }
            
            // 2. Metric + Dimension (if dimension capability exists)
            if ($primaryDimension) {
                $dimName = $primaryDimension->name;
                $suggestedQuestions[] = [
                    'title' => "{$metricName} per {$dimName}",
                    'prompt' => "Berapa {$metricName} per {$dimName}?"
                ];
                
                // 3. Top N (if dimension capability exists)
                $suggestedQuestions[] = [
                    'title' => "Top 5 {$dimName}",
                    'prompt' => "Top 5 {$dimName} berdasarkan {$metricName}"
                ];
            }
            
            // 4. Period Comparison (if date capability exists)
            if ($hasDateField) {
                $suggestedQuestions[] = [
                    'title' => "Perbandingan {$metricName}",
                    'prompt' => "Bandingkan {$metricName} bulan ini dengan bulan lalu"
                ];
            }
        }

        return $suggestedQuestions;
    }

    public function conversations(Request $request)
    {
        $conversations = AiConversation::where('user_id', $request->user()->id)
            ->orderBy('updated_at', 'desc')
            ->get();
            
        return response()->json(['conversations' => $conversations]);
    }

    public function storeConversation(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $conversation = AiConversation::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
        ]);

        return response()->json(['conversation' => $conversation]);
    }

    public function showConversation(Request $request, $id)
    {
        $conversation = AiConversation::where('user_id', $request->user()->id)->findOrFail($id);
        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function deleteConversation(Request $request, $id)
    {
        $conversation = AiConversation::where('user_id', $request->user()->id)->findOrFail($id);
        $conversation->messages()->delete();
        $conversation->delete();

        return response()->json(['success' => true]);
    }

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:ai_conversations,id',
            'message' => 'required|string',
        ]);

        $conversation = AiConversation::where('user_id', $request->user()->id)->findOrFail($validated['conversation_id']);
        
        $domainContext = $conversation->metadata['domain_context'] ?? null;

        // 1. Resolve connection (Administrator-selected analytics source)
        // For P0/P1, it was assumed there's a setting for this. Let's find the designated analytics connection.
        $connectionId = \App\Models\AiSetting::getConfig('analytics_connection_id');
        if (!$connectionId) {
            $connection = Connection::first(); // Fallback if none specifically selected
            $connectionId = $connection ? $connection->id : null;
        } else {
            $connection = Connection::find($connectionId);
        }

        if (!$connection) {
            return response()->json(['error' => 'No analytics connection configured.'], 400);
        }

        $startTime = microtime(true);

        // Fetch recent conversation history before adding new message for context optimization
        $recentMessages = AiMessage::where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->take(6)
            ->get()
            ->reverse();

        $compactHistory = [];
        foreach ($recentMessages as $msg) {
            if ($msg->role === 'user') {
                $compactHistory[] = ['role' => 'user', 'content' => $msg->content];
            } elseif ($msg->role === 'assistant') {
                $rawMeta = $msg->metadata;
                if (is_array($rawMeta)) {
                    $metadata = $rawMeta;
                } elseif (is_string($rawMeta)) {
                    $metadata = json_decode($rawMeta, true) ?? [];
                } else {
                    $metadata = [];
                }
                if (isset($metadata['query_plan'])) {
                    $compactHistory[] = [
                        'role' => 'assistant', 
                        'content' => "Previous Analytical Context:\n" . json_encode($metadata['query_plan'])
                    ];
                } elseif (isset($metadata['intent'])) {
                    $compactHistory[] = [
                        'role' => 'assistant', 
                        'content' => "Previous Intent: " . $metadata['intent']
                    ];
                } else {
                    $compactHistory[] = ['role' => 'assistant', 'content' => $msg->content];
                }
            }
        }

        // Save User Message
        $userMessage = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        // Dynamically update conversation title on first message
        if ($conversation->title === 'Analisis Baru' || empty($conversation->title)) {
            $words = explode(' ', $validated['message']);
            $shortTitle = implode(' ', array_slice($words, 0, 5));
            if (count($words) > 5) {
                $shortTitle .= '...';
            }
            $conversation->title = $shortTitle;
            $conversation->save();
        }

        $conversation->touch();

        try {
            $executionSource = 'llm';
            // 1.5 Fast Query Recognition with Exception Safety
            $recognized = null;
            try {
                $recognized = $this->recognizer->recognize($validated['message'], $connection->id, $domainContext);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('ChatEDP_Telemetry', [
                    'event' => 'fast_path_error', 
                    'connection_id' => $connection->id, 
                    'error' => $e->getMessage()
                ]);
            }
            
            $minConfidence = config('chatedp.query_recognition.min_fast_path_confidence', 0.95);
            
            if ($recognized && $recognized['status'] === 'recognized' && $recognized['confidence'] >= $minConfidence && $recognized['plan']) {
                $plan = $recognized['plan'];
                $executionSource = 'fast_path';
                
                \Illuminate\Support\Facades\Log::info('ChatEDP_Telemetry', [
                    'event' => 'fast_path_hit', 
                    'connection_id' => $connection->id, 
                    'confidence' => $recognized['confidence'],
                    'reasons' => $recognized['reasons'] ?? []
                ]);
            } else {
                \Illuminate\Support\Facades\Log::info('ChatEDP_Telemetry', [
                    'event' => 'fast_path_miss', 
                    'connection_id' => $connection->id, 
                    'confidence' => $recognized['confidence'] ?? 0,
                    'reasons' => $recognized['reasons'] ?? [],
                    'status' => $recognized['status'] ?? 'error'
                ]);
                
                // 2. Intent to QueryPlan via LLM (Fallback)
                $plan = $this->planner->plan($validated['message'], $connection->id, $compactHistory, $domainContext);
                
                \Illuminate\Support\Facades\Log::info('ChatEDP_Telemetry', ['event' => 'llm_planner_call', 'connection_id' => $connection->id]);
            }

            if ($plan->intent === 'data_unavailable') {
                $duration = microtime(true) - $startTime;
                
                $assistantMessage = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => 'Pertanyaan tidak dapat diproses. Mungkin pertanyaan Anda berada di luar konteks atau menggunakan istilah data yang belum dikonfigurasi.',
                    'metadata' => [
                        'intent' => 'data_unavailable',
                        'suggestions' => $this->getSuggestedQuestions($connection->id)
                    ]
                ]);
                
                return response()->json([
                    'message' => $assistantMessage,
                    'results' => [],
                    'export_token' => null,
                    'chart_config' => null
                ]);
            }

            // Infer Domain Context from recognized tables
            if (!empty($plan->tables)) {
                $metadataRecords = \Illuminate\Support\Facades\DB::table('sync_metadata_definitions')
                    ->where('connection_id', $connection->id)
                    ->where('chatedp_enabled', true)
                    ->whereIn('entity_key', $plan->tables)
                    ->get();

                $possibleDomains = null;
                $allGlobal = true;

                foreach ($metadataRecords as $meta) {
                    if (!empty($meta->business_domains) && $meta->business_domains !== 'null' && $meta->business_domains !== '[]') {
                        $allGlobal = false;
                        $domains = json_decode($meta->business_domains, true);
                        if (is_array($domains)) {
                            if ($possibleDomains === null) {
                                $possibleDomains = $domains;
                            } else {
                                $possibleDomains = array_intersect($possibleDomains, $domains);
                            }
                        }
                    }
                }

                $newContext = $domainContext;

                if ($allGlobal) {
                    // All tables are global. Keep existing context.
                } else if ($possibleDomains !== null && count($possibleDomains) === 1) {
                    // Exactly one shared confident domain
                    $newContext = array_values($possibleDomains)[0];
                } else if ($possibleDomains !== null && count($possibleDomains) > 1) {
                    // Multiple shared domains. Keep existing if compatible, else clear.
                    if ($domainContext && !in_array($domainContext, $possibleDomains)) {
                        $newContext = null;
                    }
                } else {
                    // No shared domains (e.g. cross-domain join) or incompatible tables. Clear context.
                    $newContext = null;
                }

                if ($newContext !== $domainContext) {
                    $domainContext = $newContext;
                    $metadata = $conversation->metadata ?? [];
                    if ($domainContext) {
                        $metadata['domain_context'] = $domainContext;
                    } else {
                        unset($metadata['domain_context']);
                    }
                    $conversation->metadata = $metadata;
                    $conversation->save();
                }
            }

            // 3. Date Context Resolution & Semantic Validation
            $plan = $this->dateResolver->resolve($plan);

            $validatedPlan = null;
            $maxRetries = 1;
            $retries = 0;

            while (true) {
                try {
                    $validatedPlan = $this->validator->validate($plan, $connection, $domainContext);
                    break;
                } catch (\App\Exceptions\AI\SemanticNotFoundException $e) {
                    if ($retries < $maxRetries) {
                        $retries++;
                        
                        // Try to auto-learn
                        $learned = $this->learningService->learn(
                            $connection,
                            $e->getTerm(),
                            $e->getType(),
                            !empty($plan->tables) ? $plan->tables[0] : null
                        );
                        
                        if ($learned) {
                            continue; // Retry validation with the newly learned definition
                        }
                    }
                    
                    $duration = microtime(true) - $startTime;
                    
                    $assistantMessage = AiMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'assistant',
                        'content' => "Maaf, saya tidak mengenali istilah '{$e->getTerm()}'. Sistem juga tidak dapat menemukan kecocokan di database untuk mempelajarinya secara otomatis.",
                        'metadata' => ['intent' => 'semantic_not_found']
                    ]);
                    
                    return response()->json([
                        'message' => $assistantMessage,
                        'results' => [],
                        'export_token' => null,
                        'chart_config' => null
                    ]);

                } catch (\App\Exceptions\AI\SemanticAmbiguityException $e) {
                    $duration = microtime(true) - $startTime;
                    
                    $assistantMessage = AiMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'assistant',
                        'content' => $e->getMessage(),
                        'metadata' => ['intent' => 'semantic_ambiguity']
                    ]);
                    
                    return response()->json([
                        'message' => $assistantMessage,
                        'results' => [],
                        'export_token' => null,
                        'chart_config' => null
                    ]);
                } catch (\App\Exceptions\AI\TableResolutionException $e) {
                    // Let other exceptions bubble up or handle them specifically if needed
                    throw $e;
                }
            }

            if ($validatedPlan->intent === 'data_unavailable') {
                $duration = microtime(true) - $startTime;
                
                $assistantMessage = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => 'Data tersebut belum tersedia pada sumber data yang terhubung atau tidak ditemukan dalam schema.',
                    'metadata' => ['intent' => 'data_unavailable']
                ]);
                
                return response()->json([
                    'message' => $assistantMessage,
                    'results' => [],
                    'export_token' => null,
                    'chart_config' => null
                ]);
            }

            // 4. Cost Guard
            $this->costGuard->guard($validatedPlan);

            // 5. Query Compiler & Library Check
            try {
                $libraryHit = $this->libraryService->lookup($validatedPlan, $connection, $domainContext);
                $executionSource = 'compiler';
                $matchedRepository = null;

                if ($libraryHit) {
                    $executionSource = 'library_hit';
                    $matchedRepository = $libraryHit->queryRepository;
                    $libraryHit->recordHit();
                }

                // Always safely compile parameterized SQL for the current plan's specific bindings
                [$sql, $bindings] = $this->compiler->compile($validatedPlan, $connection);

                // 6. Secure Execution
                $executionResult = $this->executor->executeForAnalytics($sql, $bindings, $validatedPlan->limit ?? 500);
                $results = $executionResult['data'];
            } catch (\App\Exceptions\AI\SemanticAmbiguityException $e) {
                $assistantMessage = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $e->getMessage(),
                    'metadata' => ['intent' => 'semantic_ambiguity']
                ]);
                
                return response()->json([
                    'message' => $assistantMessage,
                    'results' => [],
                    'export_token' => null,
                    'chart_config' => null
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Query Compilation/Execution Failed: ' . $e->getMessage());
                
                $assistantMessage = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => 'Maaf, terjadi kesalahan teknis saat menerjemahkan atau mengeksekusi kueri Anda: ' . $e->getMessage(),
                    'metadata' => ['intent' => 'error']
                ]);
                
                return response()->json([
                    'message' => $assistantMessage,
                    'results' => [],
                    'export_token' => null,
                    'chart_config' => null
                ]);
            }

            // Promote to Query Library if backed by an authoritative QueryRepository definition
            if ($matchedRepository && !$libraryHit) {
                try {
                    $this->libraryService->promote($validatedPlan, $connection, $matchedRepository, $domainContext);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to promote query to library', ['error' => $e->getMessage()]);
                }
            }

            $duration = microtime(true) - $startTime;

            // 7. Result Evaluation & Formatting
            $interpretation = $this->interpreter->interpret($results, $validatedPlan, $connection->id);
            $results = $interpretation['results'];
            $assistantReply = $interpretation['assistantReply'];
            $presentationMetadata = $interpretation['presentationMetadata'];
            $columnMap = $interpretation['columnMap'];
            $resultsFormatted = true;

            // 8. Summary & Insight Generation
            if (empty($assistantReply)) {
                // Create a compact summary context for the LLM using clean business semantics
                $compactTop = [];
                $limit = min(count($results), 10); // up to top 10 for narrative
                for ($i = 0; $i < $limit; $i++) {
                    $rowArray = (array)$results[$i];
                    $compactRow = [];
                    foreach ($rowArray as $colKey => $colData) {
                        if ($colKey === '#' || $colKey === '_total_rows') continue;
                        $label = $columnMap[$colKey] ?? (is_string($colKey) ? ucwords(str_replace(['_', '-'], ' ', $colKey)) : $colKey);
                        if (is_array($colData) && isset($colData['value'])) {
                            $compactRow[$label] = $colData['formatted_value'];
                        } elseif (is_object($colData) && isset($colData->value)) {
                            $compactRow[$label] = $colData->formatted_value;
                        } else {
                            $compactRow[$label] = $colData;
                        }
                    }
                    $compactTop[] = $compactRow;
                }

                $semanticMetricNames = array_map(function($m) use ($columnMap) {
                    $alias = $m['alias'] ?? preg_replace('/[^a-zA-Z0-9_]/', '_', $m['name']);
                    $agg = strtoupper($m['aggregation'] ?? '');
                    $key = empty($agg) ? $alias : "{$alias}_{$agg}";
                    return $columnMap[$key] ?? $columnMap[$alias] ?? str_replace('_', ' ', $alias);
                }, $validatedPlan->metrics ?? []);

                $semanticDimensionNames = array_map(function($d) use ($columnMap) {
                    return $columnMap[$d] ?? ucwords(str_replace(['_', '-'], ' ', $d));
                }, $validatedPlan->dimensions ?? []);

                $summaryContext = [
                    'type' => !empty($validatedPlan->dimensions) ? 'grouped_ranking' : 'scalar',
                    'total_rows' => count($results),
                    'top_items' => $compactTop,
                    'metrics' => $semanticMetricNames,
                    'dimensions' => $semanticDimensionNames
                ];
                
                $summaryPrompt = "Berikan ringkasan singkat dalam bahasa Indonesia menggunakan format unit ringkas (misal: Rp66,27 juta) berdasarkan data berikut. Sebutkan urutan ranking jika ini adalah data ranking. Jangan buat data fiktif atau hitung ulang angkanya:\n\n" . json_encode($summaryContext);
                $messages = [
                    ['role' => 'system', 'content' => 'Kamu adalah asisten data analisis bisnis. Berikan penjelasan natural yang akurat, singkat, dan langsung pada intinya berdasarkan data JSON yang diberikan. Hindari menyebutkan struktur teknis JSON.'],
                    ['role' => 'user', 'content' => $summaryPrompt]
                ];

                $assistantReply = $this->openRouter->getCompletion($messages, false);
            }
            
            // Extract chart recommendation if any (simplified chart config for now)
            $chartConfig = null;
            $isGrouped = !empty($validatedPlan->dimensions);
            if ($isGrouped && count($results) > 0) {
                $firstRow = (array)$results[0];
                $dims = array_keys(array_filter($firstRow, fn($k) => $k !== '#' && $k !== 'Jumlah Transaksi' && (!isset($presentationMetadata['columns']) || count(array_filter($presentationMetadata['columns'], fn($c) => $c['key'] === $k && $c['type'] === 'text')) > 0), ARRAY_FILTER_USE_KEY));
                $mets = array_keys(array_filter($firstRow, fn($k) => $k !== '#' && (!isset($presentationMetadata['columns']) || count(array_filter($presentationMetadata['columns'], fn($c) => $c['key'] === $k && $c['type'] === 'currency')) > 0), ARRAY_FILTER_USE_KEY));
                
                if (!empty($dims) && !empty($mets)) {
                    $chartConfig = [
                        'type' => 'bar',
                        'x_field' => $dims[0],
                        'y_field' => $mets[0]
                    ];
                }
            }

            // Save Assistant Message
skip_llm_summary:
            if (!isset($resultsFormatted) || !$resultsFormatted) {
                $formattedPayload = $this->formatResultHeaders($results, $validatedPlan, $connection->id);
                $results = $formattedPayload['data'];
                $presentationMetadata = $formattedPayload['metadata'];
                $columnMap = $formattedPayload['column_map'] ?? [];
            }

            $metadata = [
                'query_plan' => $validatedPlan->toArray(),
                'execution_source' => $executionSource ?? 'compiler',
                'row_count' => count($results),
                'duration' => $duration,
                'chart_config' => $chartConfig ?? null,
                'presentation' => $presentationMetadata ?? null
            ];

            $assistantMessage = AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => trim($assistantReply),
                'metadata' => $metadata
            ]);

            // Save Temporary Result Cache for Export/Report
            $exportToken = Str::random(40);
            Cache::put('chatedp_export_' . $exportToken, [
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
                'results' => $results,
                'metadata' => $metadata,
                'question' => $validated['message'],
                'summary' => trim($assistantReply)
            ], now()->addMinutes(30));

            // Audit & Usage (if token usage was passed)
            $usage = request()->attributes->get('llm_usage');
            if ($usage) {
                AiUsageMetric::create([
                    'user_id' => $request->user()->id,
                    'provider' => 'openrouter',
                    'model' => request()->attributes->get('llm_model', 'unknown'),
                    'request_tokens' => $usage['prompt_tokens'] ?? 0,
                    'response_tokens' => $usage['completion_tokens'] ?? 0,
                    'total_tokens' => $usage['total_tokens'] ?? 0,
                    'duration_ms' => $duration * 1000,
                ]);
            }

            $auditPlanJson = $validatedPlan->toArray();
            $auditPlanJson['_execution_source'] = $executionSource ?? 'compiler';

            AiAuditLog::create([
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
                'connection_id' => $connection->id,
                'query_plan_json' => $auditPlanJson,
                'sql_hash' => hash('sha256', $sql), // Don't store full raw SQL with values
                'duration_ms' => $duration * 1000,
                'row_count' => count($results),
                'status' => 'success'
            ]);

            return response()->json([
                'message' => $assistantMessage,
                'results' => array_slice($results, 0, 50), // Send max 50 to UI table
                'export_token' => $exportToken,
                'chart_config' => $chartConfig,
                'sql' => $sql,
                'bindings' => $bindings
            ]);

        } catch (\App\Exceptions\AI\TableResolutionException $e) {
            $duration = microtime(true) - $startTime;
            
            if ($e->getResolutionType() === \App\Exceptions\AI\TableResolutionException::TYPE_AMBIGUOUS) {
                $content = "Terdapat beberapa tabel yang mirip dengan permintaan Anda. Mohon spesifikasikan data mana yang ingin Anda analisis.\n\n({$e->getMessage()})";
            } else {
                $content = 'Data tersebut belum tersedia pada sumber data yang terhubung atau tidak ditemukan dalam schema.';
            }

            $assistantMessage = AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $content,
                'metadata' => ['intent' => $e->getResolutionType()]
            ]);
            
            return response()->json([
                'message' => $assistantMessage,
                'results' => [],
                'export_token' => null,
                'chart_config' => null
            ]);
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            
            AiAuditLog::create([
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
                'connection_id' => $connection->id,
                'query_plan_json' => null,
                'sql_hash' => null,
                'duration_ms' => $duration * 1000,
                'row_count' => 0,
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);

            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'plan' => isset($plan) ? $plan->toArray() : null
            ], 400);
        }
    }

    public function exportExcel(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            abort(400, 'Export token required.');
        }

        $data = Cache::get('chatedp_export_' . $token);
        if (!$data || $data['user_id'] !== $request->user()->id) {
            abort(404, 'Export session expired or invalid.');
        }

        $results = $data['results'];
        if (empty($results)) {
            abort(404, 'No data to export.');
        }

        $fileName = 'Analysis_Export_' . now()->format('Ymd_His') . '.xlsx';
        $path = storage_path('app/temp_' . $fileName);
        
        $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($path);
        
        foreach ($results as $row) {
            $flatRow = [];
            foreach ((array)$row as $key => $val) {
                if (is_array($val) && array_key_exists('value', $val)) {
                    $flatRow[$key] = $val['value'];
                } elseif (is_object($val) && isset($val->value)) {
                    $flatRow[$key] = $val->value;
                } else {
                    $flatRow[$key] = is_array($val) ? json_encode($val) : (is_object($val) ? json_encode($val) : $val);
                }
            }
            $writer->addRow($flatRow);
        }
        
        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    public function generateReport(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            abort(400, 'Report token required.');
        }

        $data = Cache::get('chatedp_export_' . $token);
        if (!$data || $data['user_id'] !== $request->user()->id) {
            abort(404, 'Report session expired or invalid.');
        }

        return view('chatedp.report', [
            'question' => $data['question'],
            'summary' => $data['summary'],
            'results' => $data['results'],
            'metadata' => $data['metadata'],
            'timestamp' => now()
        ]);
    }

}
