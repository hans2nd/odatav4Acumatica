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
    public function plan(string $naturalLanguageRequest, int $connectionId, array $history = [], ?string $domainContext = null): QueryPlan
    {
        // 1. Discover Semantic Candidates
        $candidates = $this->discoveryService->discoverCandidates($naturalLanguageRequest, $connectionId, $domainContext);
        
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
6. Use aggregation explicitly (e.g., "sum", "avg", "count", "count_distinct", "min", "max").

CONVERSATIONAL INHERITANCE RULES (FOLLOW-UP VS FRESH QUESTIONS):
- If the user asks a follow-up question (e.g. "What about Jakarta?"), inherit the "metric" from the previous context, replace the filter, and RESET "dimensions" to empty unless they explicitly ask to keep grouping.
- If the user asks a fresh analytical question (e.g. "Compare total sales this month and last month"), RESET everything (dimensions, filters, etc.).

EXPECTED JSON STRUCTURE:
{
  "intent": "sales_analysis",
  "metrics": [
    {
      "name": "sales",
      "aggregation": "avg"
    }
  ],
  "dimensions": [],
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
  "date_context": null
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
