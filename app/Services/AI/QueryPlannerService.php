<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;

class QueryPlannerService
{
    public function __construct(
        protected OpenRouterClient $openRouterClient,
        protected SemanticCandidateDiscoveryService $discoveryService
    ) {}

    /**
     * Parse natural language into a structured QueryPlan.
     */
    public function plan(string $naturalLanguageRequest, int $connectionId, array $history = [], ?string $domainContext = null, array $activeTables = []): QueryPlan
    {
        // 1. Discover Semantic Candidates
        $candidates = $this->discoveryService->discoverCandidates($naturalLanguageRequest, $connectionId, $domainContext, $activeTables);
        
        if (empty($candidates['tables'])) {
            return QueryPlan::fromArray([
                'intent' => 'data_unavailable'
            ]);
        }

        // 2. Build Constrained Semantic Context
        $contextStr = "Available Logical Semantic Context:\n";
        $contextStr .= json_encode($candidates, JSON_PRETTY_PRINT);

        $currentDate = date('Y-m-d');
        
        // 3. System Prompt enforcing Phase 3 constraints
        $systemPrompt = <<<PROMPT
You are a highly analytical AI semantic layer assistant.
Translate natural language into a strictly logical JSON query plan based ONLY on the provided Semantic Context.
Today's date is {$currentDate}.

CRITICAL RULES:
1. USE LOGICAL CONCEPTS ONLY. Never output SQL. Never guess or invent physical table names or column names.
2. ONLY use terms present in the "Available Logical Semantic Context".
3. FILTER ≠ GROUP BY. If a field is used as a filter (e.g., "for Bandung branch"), it MUST go into "filters", NOT "dimensions". Use "dimensions" ONLY when the user explicitly asks to group, breakdown, or summarize BY a field (e.g., "sales by branch").
4. Represent time as a logical "date_context" string (e.g., "current_month", "previous_month", "ytd", "today", "yesterday", "current_year"). Do NOT calculate actual date boundaries.
5. Resolve ranking explicitly using "sorts" and "limit" (e.g. "top 10" -> limit: 10, sorts: [{"field": "...", "direction": "desc"}]).
5a. For ranking, put exactly the entity being ranked in "dimensions". Put requested accompanying attributes/items in "detail_dimensions". Detail dimensions MUST NOT change the ranking grain.
6. Use aggregation explicitly (e.g., "sum", "avg", "count", "count_distinct", "min", "max").
7. PREFER "Capabilities" and "Question Patterns" from the semantic context to map intents if they match the user's question.
8. BE AWARE of the "transaction_grain". If the user asks for "Orders" and grain is "order_line", consider how to distinct count if necessary, or just rely on the metric logic.
9. For dates, if a specific role is mentioned (like due date vs transaction date), use the appropriate date_field from the "semantic_dates" context.

ENTITY LABEL RESOLUTION (VERY IMPORTANT):
- When the user asks to group, rank, or list by an entity (e.g., "customer", "product", "sales person"), you MUST use the DESCRIPTIVE/NAME column as the dimension, NOT the ID column.
- For example: use "CustomerName" instead of "CustomerID", use "InventoryDescr" instead of "InventoryID", use "SalesPersonCD" instead of numeric IDs.
- If the semantic context provides both an ID field and a Name/Description field for the same entity, ALWAYS prefer the Name/Description field for "dimensions" and "detail_dimensions".
- Only use ID fields when the user explicitly asks for IDs or codes (e.g., "berdasarkan kode customer").

UNSUPPORTED CAPABILITY DETECTION:
- If the user asks for forecasting, prediction, estimation of future values, projection, trend extrapolation, or any form of predictive analytics (e.g., "forecast bulan depan", "prediksi penjualan", "estimasi ke depan", "proyeksi quarter berikutnya"), you MUST return:
  {"intent": "unsupported_capability", "capability": "forecast", "message": "Saat ini ChatEDP hanya dapat menganalisis data historis yang sudah tersedia. Fitur forecasting dan prediksi belum didukung."}
- Do NOT attempt to return historical data disguised as a forecast.

TOP N PER GROUP LIMITATION:
- If the user asks for "Top N per category" (e.g., "top 5 sales per cabang", "3 produk terbaik masing-masing cabang"), this system only supports global Top N ranking, NOT partitioned Top N.
- In this case, apply the Top N globally across all categories combined. Include the category as a "detail_dimension" so users can see which category each item belongs to.
- Do NOT silently ignore the per-group intent. The system will inform the user that the ranking is global.

CONVERSATIONAL INHERITANCE RULES (FOLLOW-UP VS FRESH QUESTIONS):
- Evaluate the user's question against the conversation history. Is it a follow-up to the previous context (e.g., "What about Jakarta?", "How about October?", "cabangnya mana?") or a completely fresh question?
- Set "analysis.is_follow_up" to true if it is a follow-up. Set to false if it is a fresh question.
- STRICT FILTER REPLACEMENT: When a follow-up specifies a NEW value for a filter that already exists in the previous context, FULLY REPLACE the old filter value — do NOT merge or append. For example, if the previous context filtered "cabang = [Jakarta, Sidoarjo]" and the user says "sebutkan untuk cabang Sidoarjo", the filter MUST become "cabang = Sidoarjo" only.
- If the follow-up ADDS a new dimension/filter not previously present, treat it as an addition.
- If the user asks a completely fresh analytical question (e.g. "Compare total sales this month and last month"), RESET everything (dimensions, filters, etc.).

EXPECTED JSON STRUCTURE:
{
  "intent": "sales_analysis",
  "tables": ["logical table name from the Available Logical Semantic Context"],
  "metrics": [
    {
      "name": "sales",
      "aggregation": "avg"
    }
  ],
  "dimensions": [],
  "detail_dimensions": [],
  "filters": [
    {
      "field": "branch",
      "operator": "=",
      "value": "Bandung"
    }
  ],
  "sorts": [],
  "limit": 1,
  "comparison": null,
  "date_context": null,
  "analysis": {
    "is_follow_up": true,
    "mode": "kpi"
  }
}

{$contextStr}
PROMPT;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];
        
        // 4. Attach history safely
        if (!empty($history)) {
            foreach ($history as $msg) {
                // Ensure we don't pass back massive physical context or stack traces
                if ($msg['role'] === 'user' || ($msg['role'] === 'assistant' && strlen($msg['content']) <= 500)) {
                    $messages[] = $msg;
                }
            }
        }
        $messages[] = ['role' => 'user', 'content' => $naturalLanguageRequest];

        // 5. Call LLM
        $jsonResponse = $this->openRouterClient->getCompletion($messages, true);
        if (!$jsonResponse) {
            throw new \Exception("Failed to generate a query plan. OpenRouter returned empty.");
        }

        $jsonResponse = preg_replace('/^```json\s*/i', '', $jsonResponse);
        $jsonResponse = preg_replace('/```$/', '', $jsonResponse);
        $jsonResponse = trim($jsonResponse);

        $parsed = json_decode($jsonResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            throw new \Exception("LLM returned invalid JSON structure.");
        }

        // 6. Return Canonical QueryPlan
        return QueryPlan::fromArray($parsed);
    }
}
