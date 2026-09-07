<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;
use App\Services\AI\SchemaCacheService;

class QueryPlannerService
{
    protected OpenRouterClient $openRouterClient;
    protected SchemaCacheService $schemaCacheService;

    public function __construct(OpenRouterClient $openRouterClient, SchemaCacheService $schemaCacheService)
    {
        $this->openRouterClient = $openRouterClient;
        $this->schemaCacheService = $schemaCacheService;
    }

    /**
     * Parse natural language into a structured QueryPlan.
     * Uses OpenRouter to map the NLP to a JSON object representing the plan.
     */
    public function plan(string $naturalLanguageRequest, int $connectionId, array $history = [], ?string $domainContext = null): QueryPlan
    {
        $schemaContext = $this->buildSchemaContext($connectionId, $naturalLanguageRequest, $domainContext);
        if (empty(trim($schemaContext)) || $schemaContext === "No schema available.") {
            return QueryPlan::fromArray([
                'intent' => 'data_unavailable',
                'tables' => [],
                'columns' => [],
                'metrics' => [],
                'dimensions' => [],
                'filters' => [],
                'sorts' => [],
                'limit' => 500
            ]);
        }
        
        $systemPrompt = <<<PROMPT
You are a highly analytical data assistant. Translate natural language into a structured JSON query plan.
Interpret the user's intent based on the Available Semantic Context and output a precise JSON configuration.
DO NOT OUTPUT ANYTHING OTHER THAN JSON.

Available Semantic Context:
{$schemaContext}

IMPORTANT INSTRUCTIONS:
- You MUST output ONLY valid JSON.
- The JSON structure MUST exactly match:
  {
    "intent": "sales_analysis", // Or another intent from Intent Signals
    "tables": ["sales"],
    "metric_term": "revenue", // null if default
    "aggregation": "sum", // count, avg, min, max
    "dimensions": ["customer"],
    "filters": [{"field": "status", "operator": "=", "value": "Active"}],
    "sorts": [{"direction": "desc"}],
    "limit": 10,
    "comparison": {
      "type": "previous_period",
      "period": "month",
      "periods": [["2024-11-02", "2024-11-02"], ["2024-11-03", "2024-11-03"]],
      "output": ["value", "difference", "percentage_change"]
    } 
  }
- Output business concepts (e.g. "revenue", "customer", "date", "sales") for tables and fields. The backend Semantic Layer will resolve these logical terms into physical tables/columns automatically.
- Do NOT guess physical column names like "NetAmount" or "CustomerCD".
- If the user asks for data completely unrelated to the Available Semantic Context, safely reject by outputting {"intent": "data_unavailable"}.
- Do NOT invent or hallucinate table names or columns.
- For intent "sales_analysis", provide "metric_term" (string or null) and "aggregation" (e.g. "sum") at the root. Do NOT use the "metrics" array.
- Resolve any ranking parameters into "sorts" and "limit" (e.g. "top 10" -> limit: 10, direction: "desc").
- Resolve comparison operations into the "comparison" object.
- For transaction count queries, set "aggregation": "count".
- CRITICAL: Do NOT copy 'dimensions', 'filters', or 'sorts' from the Previous Analytical Context unless the user explicitly asks to group, break down, or filter by them in the new question. For a fresh question like "bandingkan total penjualan", reset "dimensions": [] and "sorts": [].
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];
        
        if (!empty($history)) {
            foreach ($history as $msg) {
                // Keep all user messages, but only keep short assistant messages (e.g., ambiguity questions)
                if ($msg['role'] === 'user' || ($msg['role'] === 'assistant' && strlen($msg['content']) <= 250)) {
                    $messages[] = $msg;
                }
            }
        }
        $messages[] = ['role' => 'user', 'content' => $naturalLanguageRequest];

        $jsonResponse = $this->openRouterClient->getCompletion($messages, true);
        
        if (!$jsonResponse) {
            throw new \Exception("Failed to generate a query plan. OpenRouter returned empty.");
        }

        // Clean potential markdown blocks from response if present despite instructions
        $jsonResponse = preg_replace('/^```json\s*/i', '', $jsonResponse);
        $jsonResponse = preg_replace('/```$/', '', $jsonResponse);
        $jsonResponse = trim($jsonResponse);

        $parsed = json_decode($jsonResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            throw new \Exception("LLM returned invalid JSON structure.");
        }

        return QueryPlan::fromArray($parsed);
    }

    protected function buildSchemaContext(int $connectionId, string $requestText, ?string $domainContext = null): string
    {
        $metadata = $this->schemaCacheService->getCache($connectionId, $domainContext);
        if (!$metadata || empty($metadata->tables)) {
            return "No schema available.";
        }

        $requestLower = strtolower($requestText);
        $intentSignals = [];
        
        // 1. System/Query Operations
        if (preg_match('/\b(top|bottom|terbesar|terkecil|terlaris|tertinggi|terendah)\b/i', $requestLower)) $intentSignals['ranking'] = true;
        if (preg_match('/\b(bandingkan|dibanding|growth|kenaikan|penurunan|selisih|vs)\b/i', $requestLower)) $intentSignals['comparison'] = true;
        if (preg_match('/\b(tren|trend|pergerakan|pertumbuhan)\b/i', $requestLower)) $intentSignals['trend'] = true;
        if (preg_match('/\b(jumlah transaksi|total order|berapa transaksi|jumlah order)\b/i', $requestLower)) $intentSignals['transaction_count'] = true;
        if (preg_match('/\b(cari transaksi|detail transaksi|nomor nota|invoice)\b/i', $requestLower)) $intentSignals['transaction_lookup'] = true;

        $hasDateContext = preg_match('/\b(hari|minggu|bulan|tahun|tanggal)\b/i', $requestLower);

        $matchedTables = [];
        $matchedMetrics = [];
        $matchedDimensions = [];
        $matchedDateFields = [];
        
        foreach ($metadata->tables as $table) {
            $isTableRelevant = false;
            $tableRole = strtolower($table['table_role'] ?? $table['data_role'] ?? 'other');
            
            // Check table aliases and logical name
            $tableTerms = array_merge([$table['logical_name']], $table['aliases'] ?? []);
            foreach ($tableTerms as $term) {
                if (!empty(trim($term)) && str_contains($requestLower, strtolower(trim($term)))) {
                    $isTableRelevant = true;
                    if ($tableRole === 'sales_transaction') {
                        $intentSignals['sales_analysis'] = true;
                    }
                    break;
                }
            }
            
            // Metrics
            $tableMetrics = [];
            foreach ($table['metrics'] ?? [] as $m) {
                $mName = is_array($m) ? ($m['name'] ?? '') : (string)$m;
                if (empty($mName)) continue;
                
                $mTerms = array_merge([$mName], is_array($m) ? ($m['aliases'] ?? []) : []);
                foreach ($mTerms as $term) {
                    if (!empty(trim($term)) && str_contains($requestLower, strtolower(trim($term)))) {
                        $isTableRelevant = true;
                        
                        $mStr = $mName;
                        if (is_array($m) && !empty($m['is_default'])) {
                            $mStr .= "\ndefault";
                        }
                        $tableMetrics[] = $mStr;
                        
                        if ($tableRole === 'sales_transaction') {
                            $intentSignals['sales_analysis'] = true;
                        }
                        break;
                    }
                }
            }

            // Dimensions
            $tableDims = [];
            foreach ($table['dimensions'] ?? [] as $d) {
                $dName = is_array($d) ? ($d['name'] ?? '') : (string)$d;
                if (empty($dName)) continue;
                
                // Track date fields explicitly
                $dNameLower = strtolower($dName);
                if (str_contains($dNameLower, 'date') || str_contains($dNameLower, 'tanggal') || str_contains($dNameLower, 'waktu')) {
                    $matchedDateFields[] = $dName;
                }

                $dTerms = array_merge([$dName], is_array($d) ? ($d['aliases'] ?? []) : []);
                foreach ($dTerms as $term) {
                    if (!empty(trim($term)) && str_contains($requestLower, strtolower(trim($term)))) {
                        $isTableRelevant = true;
                        $tableDims[] = $dName;
                        
                        // Infer business intents from dimension name
                        if (str_contains($dNameLower, 'customer') || str_contains($dNameLower, 'pelanggan')) {
                            $intentSignals['customer_analysis'] = true;
                        }
                        if (str_contains($dNameLower, 'product') || str_contains($dNameLower, 'produk') || str_contains($dNameLower, 'item')) {
                            $intentSignals['product_analysis'] = true;
                        }
                        if (str_contains($dNameLower, 'branch') || str_contains($dNameLower, 'cabang')) {
                            $intentSignals['branch_analysis'] = true;
                        }
                        if (str_contains($dNameLower, 'company') || str_contains($dNameLower, 'perusahaan')) {
                            $intentSignals['company_analysis'] = true;
                        }
                        break;
                    }
                }
            }
            
            // Auto-fallback to sales intent if it's the only available main table and we have query signals
            if (empty($matchedTables) && $tableRole === 'sales_transaction' && ($hasDateContext || !empty($intentSignals))) {
                $isTableRelevant = true;
                $intentSignals['sales_analysis'] = true;
            }
            
            if ($isTableRelevant) {
                if (empty($tableMetrics)) {
                    foreach ($table['metrics'] ?? [] as $m) {
                        if (is_array($m) && !empty($m['is_default'])) {
                            $tableMetrics[] = $m['name'] . "\ndefault";
                            $intentSignals['sales_analysis'] = true;
                            break;
                        }
                    }
                }
                
                $matchedTables[] = $table['logical_name'];
                $matchedMetrics = array_merge($matchedMetrics, $tableMetrics);
                $matchedDimensions = array_merge($matchedDimensions, $tableDims);
            }
        }
        
        $context = [];
        
        if (!empty($intentSignals)) {
            $context[] = "Intent Signals:\n" . implode("\n", array_keys($intentSignals));
        }
        
        if (!empty($matchedTables)) {
            $context[] = "Table:\n" . implode("\n", array_unique($matchedTables));
        }
        
        if (!empty($matchedMetrics)) {
            $context[] = "Metric:\n" . implode("\n", array_unique($matchedMetrics));
        }
        
        if (!empty($matchedDimensions)) {
            $context[] = "Dimension:\n" . implode("\n", array_unique($matchedDimensions));
        }
        
        if ($hasDateContext && !empty($matchedDateFields)) {
            $context[] = "Date:\n" . implode("\n", array_unique($matchedDateFields));
        }
        
        if (isset($intentSignals['comparison'])) {
            $context[] = "Comparison:\nprevious_period\nsame_period_previous_year\nexplicit_periods\nmonth\nweek\nyear\nday";
        }
        
        if (empty($context)) {
            return "No schema available.";
        }
        
        return implode("\n\n", $context);
    }
}
