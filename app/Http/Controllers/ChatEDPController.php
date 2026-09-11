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
use App\Services\AI\ConversationContextService;
use App\Services\AI\BusinessAnalysisService;
use App\Services\AI\InsightEngineService;
use App\Services\AI\ResponseStrategyService;
use App\Services\AI\ComparisonExecutor;
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
    protected $conversationContext;
    protected $businessAnalysis;
    protected $insightEngine;
    protected $responseStrategy;
    protected $comparisonExecutor;

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
        QueryRecognitionService $recognizer,
        ConversationContextService $conversationContext,
        BusinessAnalysisService $businessAnalysis,
        InsightEngineService $insightEngine,
        ResponseStrategyService $responseStrategy,
        ComparisonExecutor $comparisonExecutor
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
        $this->conversationContext = $conversationContext;
        $this->businessAnalysis = $businessAnalysis;
        $this->insightEngine = $insightEngine;
        $this->responseStrategy = $responseStrategy;
        $this->comparisonExecutor = $comparisonExecutor;
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

        // 1. Try to get suggestions from semantic_profile question_patterns
        $definitions = \Illuminate\Support\Facades\DB::table('sync_metadata_definitions')
            ->where('connection_id', $connectionId)
            ->whereIn('entity_key', $enabledTables)
            ->get();
            
        foreach ($definitions as $def) {
            if ($def->semantic_profile) {
                $profile = json_decode($def->semantic_profile, true);
                if (!empty($profile['question_patterns'])) {
                    foreach ($profile['question_patterns'] as $qp) {
                        $metric = $qp['metric'] ?? '';
                        $dimension = $qp['dimension'] ?? '';
                        $intent = $qp['intent'] ?? 'aggregation';
                        $name = $qp['name'] ?? '';
                        
                        if (empty($metric)) continue;

                        $title = $name ?: "{$intent} {$metric}";
                        $prompt = "";
                        
                        if ($intent === 'ranking') {
                            $dir = ($qp['direction'] ?? 'desc') === 'desc' ? 'Top 5' : 'Bottom 5';
                            $prompt = "{$dir} {$dimension} berdasarkan {$metric}";
                        } elseif ($intent === 'aggregation' && $dimension) {
                            $prompt = "Total {$metric} per {$dimension}";
                        } elseif ($intent === 'aggregation') {
                            $prompt = "Berapa total {$metric}?";
                        } else {
                            $prompt = "Tampilkan {$metric}" . ($dimension ? " per {$dimension}" : "");
                        }

                        $suggestedQuestions[] = [
                            'title' => ucwords(str_replace(['_', '-'], ' ', $title)),
                            'prompt' => $prompt
                        ];
                    }
                }
            }
        }
        
        if (!empty($suggestedQuestions)) {
            $unique = [];
            foreach ($suggestedQuestions as $sq) {
                $unique[$sq['prompt']] = $sq;
            }
            $suggestedQuestions = array_values($unique);
            
            if (count($suggestedQuestions) > 4) {
                shuffle($suggestedQuestions);
                $suggestedQuestions = array_slice($suggestedQuestions, 0, 4);
            }
            return $suggestedQuestions;
        }

        // 2. Fallback to generic suggestions

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
                    'name' => ucwords(str_replace(['_', '-'], ' ', preg_replace('/(?<!^)[A-Z]/', ' $0', $dimField->field_name)))
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

    /**
     * Delete all conversations for the authenticated user on specific dates.
     */
    public function deleteConversationsByDate(Request $request)
    {
        $validated = $request->validate([
            'dates' => 'required|array|min:1',
            'dates.*' => 'date_format:Y-m-d',
        ]);

        $userId = $request->user()->id;
        $deletedCount = 0;

        foreach ($validated['dates'] as $date) {
            $conversations = AiConversation::where('user_id', $userId)
                ->whereDate('created_at', $date)
                ->get();

            foreach ($conversations as $conv) {
                $conv->messages()->delete();
                $conv->delete();
                $deletedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => "{$deletedCount} percakapan berhasil dihapus.",
        ]);
    }

    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:ai_conversations,id',
            'message' => 'required|string',
        ]);

        $conversation = AiConversation::where('user_id', $request->user()->id)->findOrFail($validated['conversation_id']);
        
        $storedContext = $this->conversationContext->resolve($conversation);
        $domainContext = $storedContext['domain'][0] ?? $storedContext['domain'] ?? ($conversation->metadata['domain_context'] ?? null);

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
        $memory = $this->conversationContext->compact($conversation);
        if (!empty($memory['summary']) || !empty($memory['context'])) {
            $compactHistory[] = ['role' => 'assistant', 'content' => 'Conversation summary: ' . json_encode(['summary' => $memory['summary'], 'context' => $memory['context']], JSON_UNESCAPED_UNICODE)];
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
                $activeTables = $memory['context']['tables'] ?? [];
                $plan = $this->planner->plan($validated['message'], $connection->id, $compactHistory, $domainContext, $activeTables);
                
                \Illuminate\Support\Facades\Log::info('ChatEDP_Telemetry', ['event' => 'llm_planner_call', 'connection_id' => $connection->id]);
            }

            if ($plan->intent === 'data_unavailable') {
                $duration = microtime(true) - $startTime;
                
                
                $hasValidContext = !empty($memory['context']['tables']) && !empty($memory['context']['metric']);
                $suggestions = $hasValidContext ? [] : $this->getSuggestedQuestions($connection->id);
                
                $assistantMessage = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => 'Pertanyaan tidak dapat diproses. Mungkin pertanyaan Anda berada di luar konteks atau menggunakan istilah data yang belum dikonfigurasi.',
                    'metadata' => [
                        'intent' => 'data_unavailable',
                        'suggestions' => $suggestions
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
                        $learned = $this->learningService->learnAndReturnMatch(
                            $connection,
                            $e->getTerm(),
                            $e->getType(),
                            $domainContext
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
                    
                    $suggestions = $this->getSuggestedQuestions($connection->id);
                    $content = $e->getMessage();
                    $metadata = ['intent' => 'semantic_ambiguity'];
                    
                    if (!empty($suggestions)) {
                        $metadata['suggestions'] = $suggestions;
                        $titles = array_column($suggestions, 'prompt');
                        $content .= "\n\n**Mungkin Anda bermaksud menanyakan:**\n- " . implode("\n- ", $titles);
                    }

                    $assistantMessage = AiMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => 'assistant',
                        'content' => $content,
                        'metadata' => $metadata
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
                
                $suggestions = $this->getSuggestedQuestions($connection->id);
                $content = 'Data tersebut belum tersedia pada sumber data yang terhubung atau tidak ditemukan dalam schema.';
                $metadata = ['intent' => 'data_unavailable'];
                
                if (!empty($suggestions)) {
                    $metadata['suggestions'] = $suggestions;
                    $titles = array_column($suggestions, 'prompt');
                    $content .= "\n\n**Mungkin Anda bermaksud menanyakan:**\n- " . implode("\n- ", $titles);
                }
                
                $assistantMessage = AiMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $content,
                    'metadata' => $metadata
                ]);
                
                return response()->json([
                    'message' => $assistantMessage,
                    'results' => [],
                    'export_token' => null,
                    'chart_config' => null
                ]);
            }

            $analysisContext = [
                'domain' => $domainContext ? [$domainContext] : [],
                'metric' => $validatedPlan->metrics[0]['alias'] ?? $validatedPlan->metrics[0]['name'] ?? null,
                'dimensions' => $validatedPlan->dimensions,
                'detail_dimensions' => $validatedPlan->detail_dimensions,
                'filters' => $validatedPlan->filters,
                'date_context' => $validatedPlan->date_context,
                'comparison' => $validatedPlan->comparison,
                'tables' => $validatedPlan->tables,
                'relationships' => $validatedPlan->relationships,
                'analysis_mode' => $validatedPlan->analysis['mode'] ?? ($validatedPlan->comparison ? 'comparison' : 'kpi'),
            ];
            
            $isFollowUp = $validatedPlan->analysis['is_follow_up'] ?? false;
            
            $analysisContext = $this->conversationContext->merge($conversation, $analysisContext, !$isFollowUp);

            // 4. Cost Guard
            $this->costGuard->guard($validatedPlan);

            // 5. Query Compiler & Library Check
            try {
                $libraryHit = $this->libraryService->lookup($validatedPlan, $connection, $domainContext);
                $executionSource = 'compiler';
                $matchedRepository = null;
                $comparisonResult = null;

                if ($libraryHit) {
                    $executionSource = 'library_hit';
                    $matchedRepository = $libraryHit->queryRepository;
                    $libraryHit->recordHit();
                }

                if (strtolower((string) ($validatedPlan->comparison['type'] ?? '')) === 'table') {
                    $comparisonResult = $this->comparisonExecutor->execute($validatedPlan, $connection);
                    $executionSource = 'table_comparison';
                    $sql = null;
                    $bindings = [];
                    $results = [];
                } else {
                    // Always safely compile parameterized SQL for the current plan's specific bindings
                    [$sql, $bindings] = $this->compiler->compile($validatedPlan, $connection);
                    $executionResult = $this->executor->executeForAnalytics($sql, $bindings, $validatedPlan->limit ?? 500);
                    $results = $executionResult['data'];
                }
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
                    'content' => config('app.debug') ? 'Maaf, terjadi kesalahan teknis saat menerjemahkan atau mengeksekusi kueri Anda: ' . $e->getMessage() : 'Maaf, analisis tidak dapat dijalankan karena terjadi masalah teknis.',
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
            if ($comparisonResult) {
                $results = [[
                    'left_value' => $comparisonResult['left']['value'], 'right_value' => $comparisonResult['right']['value'],
                    'gap' => $comparisonResult['comparison']['gap'], 'ratio' => $comparisonResult['comparison']['ratio'],
                ]];
                $assistantReply = !$comparisonResult['has_data']
                    ? 'Tidak ada data yang cukup pada salah satu sisi perbandingan untuk filter yang dipilih.'
                    : sprintf('%s: %s\n%s: %s\nGap: %s\nRasio: %s', $comparisonResult['left']['metric'], number_format($comparisonResult['left']['value'], 2, ',', '.'), $comparisonResult['right']['metric'], number_format($comparisonResult['right']['value'], 2, ',', '.'), number_format($comparisonResult['comparison']['gap'], 2, ',', '.'), $comparisonResult['comparison']['ratio'] === null ? '-' : number_format($comparisonResult['comparison']['ratio'] * 100, 2, ',', '.') . '%');
                $presentationMetadata = ['columns' => [], 'visualization' => 'comparison'];
                $columnMap = [];
            } else {
                $interpretation = $this->interpreter->interpret($results, $validatedPlan, $connection->id);
                $results = $interpretation['results'];
                $assistantReply = $interpretation['assistantReply'];
                $presentationMetadata = $interpretation['presentationMetadata'];
                $columnMap = $interpretation['columnMap'];
            }
            $resultsFormatted = true;

            if ($comparisonResult) {
                $analysisContext['comparison_result'] = $comparisonResult;
            }
            $analysis = $this->businessAnalysis->analyze($results, $analysisContext);
            $insight = $this->insightEngine->generate($analysis, $analysisContext);

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
                    'original_question' => $request->input('message') ?? '',
                    'type' => !empty($validatedPlan->dimensions) ? 'grouped_ranking' : 'scalar',
                    'total_rows' => count($results),
                    'top_items' => $compactTop,
                    'metrics' => $semanticMetricNames,
                    'dimensions' => $semanticDimensionNames
                ];
                
                $summaryPrompt = "Berdasarkan data JSON berikut, berikan ringkasan analitik singkat dalam bahasa Indonesia menggunakan angka ringkas (misal: Rp66,27 juta).\n\n" . 
                                 "INSTRUKSI UTAMA:\n" .
                                 "1. Awali jawaban Anda dengan kalimat pembuka yang SANGAT kontekstual berdasarkan 'original_question'. Misalnya: \"Berikut adalah ringkasan penjualan untuk tanggal 2 September 2026 berdasarkan pelanggan:\" atau kalimat serupa yang relevan dengan filter/waktu yang ditanyakan.\n" .
                                 "2. Jika ini adalah data ranking atau grouping, sebutkan urutan rankingnya DAN PASTIKAN ANDA MENYEBUTKAN SEMUA DIMENSI YANG ADA (misalnya jika ada dimensi cabang, sebutkan nama cabangnya untuk setiap entri).\n" .
                                 "3. Jangan membuat data fiktif atau mengubah angka aktual yang diberikan.\n\n" .
                                 "Data JSON:\n" . json_encode($summaryContext, JSON_UNESCAPED_UNICODE);
                $messages = [
                    ['role' => 'system', 'content' => 'Kamu adalah asisten data analisis bisnis. Berikan penjelasan natural yang akurat, singkat, dan langsung pada intinya. Hindari menyebutkan struktur teknis JSON.'],
                    ['role' => 'user', 'content' => $summaryPrompt]
                ];

                $assistantReply = $this->openRouter->getCompletion($messages, false);
            }

            $response = $this->responseStrategy->respond($insight, $analysisContext);
            if (!empty($insight['facts']) && (!$comparisonResult || $comparisonResult['has_data'])) {
                $assistantReply = trim($assistantReply . "\n\n" . $response['content']);
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
                ,'analysis_context' => $analysisContext
                ,'insight' => $insight
                ,'response_strategy' => $response['strategy']
                ,'comparison' => $comparisonResult
            ];
            $mergeData = ['previous_insight' => $insight['headline'] ?? null];
            if (!empty($availabilityContext)) {
                $mergeData['availability_context'] = $availabilityContext;
            }
            $this->conversationContext->merge($conversation, $mergeData);
            $this->conversationContext->updateSummary($conversation);

            if (count($results) === 0) {
                $suggestions = $this->getSuggestedQuestions($connection->id);
                if (!empty($suggestions)) {
                    $metadata['suggestions'] = $suggestions;
                    $titles = array_column($suggestions, 'prompt');
                    $assistantReply .= "\n\n**Mungkin Anda bermaksud menanyakan:**\n- " . implode("\n- ", $titles);
                }
            }

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
                'sql_hash' => $comparisonResult ? hash('sha256', $comparisonResult['left']['sql_hash'] . $comparisonResult['right']['sql_hash']) : hash('sha256', $sql), // Don't store full raw SQL with values
                'duration_ms' => $duration * 1000,
                'row_count' => count($results),
                'status' => 'success'
            ]);

            $responsePayload = [
                'message' => $assistantMessage,
                'results' => array_slice($results, 0, 50), // Send max 50 to UI table
                'export_token' => $exportToken,
                'chart_config' => $chartConfig,
                'insight' => $insight,
                'suggested_analysis' => $insight['recommended_next_analysis'],
                'comparison' => $comparisonResult
            ];
            if (config('app.debug')) {
                $responsePayload['sql'] = $sql;
                $responsePayload['bindings'] = $bindings;
            }
            return response()->json($responsePayload);

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

            $payload = ['error' => config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan saat memproses analisis.'];
            if (config('app.debug')) {
                $payload['trace'] = $e->getTraceAsString();
                $payload['plan'] = isset($plan) ? $plan->toArray() : null;
            }
            return response()->json($payload, 400);
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
