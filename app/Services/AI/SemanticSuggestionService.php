<?php

namespace App\Services\AI;

use App\Models\Connection;
use App\Services\QueryRepositoryService;
use App\Services\AI\OpenRouterClient;
use App\Services\AI\SemanticLearningService;
use Illuminate\Support\Facades\Log;

class SemanticSuggestionService
{
    public function __construct(
        protected QueryRepositoryService $queryRepo,
        protected OpenRouterClient $openRouter,
        protected SemanticLearningService $learningService
    ) {}

    /**
     * Generate semantic suggestions using LLM, data profiling, and existing metadata.
     */
    public function generateSuggestions(Connection $connection, \stdClass $tableDef, \Illuminate\Support\Collection $fields): array
    {
        $profile = $this->profileData($connection, $tableDef->entity_key, $fields);
        
        $prompt = $this->buildPrompt($tableDef, $fields, $profile);
        $response = $this->openRouter->getCompletion($prompt, true);
        
        $suggestions = $this->parseAndValidateResponse($response, $fields);
        
        // Verify Grain Evidence statically and statistically (AC-08)
        $suggestions = $this->verifyGrainEvidence($connection, $tableDef->entity_key, $suggestions, $fields);
        
        // Capture new patterns to Learning System
        $this->captureLearnings($connection->id, $suggestions);

        return $suggestions;
    }

    protected function profileData(Connection $connection, string $tableName, \Illuminate\Support\Collection $fields): array
    {
        $profile = [];
        try {
            // 1. Fetch 5 sample rows safely
            $query = "SELECT * FROM `" . $tableName . "` LIMIT 5";
            $sampleResult = $this->queryRepo->executeForAnalytics($query, [], 5, $connection);
            $samples = $sampleResult['data'] ?? [];
            
            // Mask PII in samples
            foreach ($samples as &$row) {
                foreach ($row as $col => $val) {
                    if (is_string($val) && preg_match('/(name|email|phone|address|identity|ktp|contact)/i', $col)) {
                        $row[$col] = '[MASKED_PII]';
                    }
                }
            }
            $profile['samples'] = $samples;

            // 2. Aggregate profiling for key columns (dates & categorical)
            $aggSelects = [];
            foreach ($fields as $field) {
                $col = $field->field_name;
                $type = strtolower($field->semantic_type ?? $field->data_type ?? '');
                
                if (in_array($type, ['date', 'datetime', 'timestamp'])) {
                    $aggSelects[] = "MIN(`{$col}`) as min_{$col}, MAX(`{$col}`) as max_{$col}";
                }
                if (in_array($type, ['status', 'categorical', 'boolean']) || preg_match('/(status|type|category|group|cd|id)$/i', $col)) {
                    // Only count distinct if we suspect low cardinality
                    $aggSelects[] = "COUNT(DISTINCT `{$col}`) as distinct_{$col}";
                }
            }
            
            if (!empty($aggSelects)) {
                $aggQuery = "SELECT COUNT(*) as total_rows, " . implode(', ', $aggSelects) . " FROM `" . $tableName . "`";
                $aggResult = $this->queryRepo->executeForAnalytics($aggQuery, [], 1, $connection);
                if (!empty($aggResult['data'])) {
                    $profile['stats'] = $aggResult['data'][0];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Data profiling failed for Suggest Semantics on table {$tableName}: " . $e->getMessage());
        }
        
        return $profile;
    }

    protected function verifyGrainEvidence(Connection $connection, string $tableName, array $suggestions, \Illuminate\Support\Collection $fields): array
    {
        if (empty($suggestions['transaction_grain']) || empty($suggestions['transaction_grain']['keys'])) {
            return $suggestions;
        }

        $grain = &$suggestions['transaction_grain'];
        $proposedKeys = $grain['keys'];
        $validCols = $fields->pluck('field_name')->map(fn($f) => strtolower($f))->toArray();

        // 1. Schema Evidence
        $schemaEvidencePKs = [];
        foreach ($fields as $f) {
            if (!empty($f->is_primary_key) || !empty($f->is_unique)) {
                $schemaEvidencePKs[] = strtolower($f->field_name);
            }
        }

        $schemaMatchCount = 0;
        $schemaEvidenceMsgs = [];
        
        // Sanitize keys
        $safeKeys = [];
        foreach ($proposedKeys as $k) {
            $kLower = strtolower($k);
            if (in_array($kLower, $validCols)) {
                $safeKeys[] = $k;
                if (in_array($kLower, $schemaEvidencePKs)) {
                    $schemaMatchCount++;
                }
            }
        }
        
        if (count($safeKeys) > 0 && count($safeKeys) === count($schemaEvidencePKs) && $schemaMatchCount === count($schemaEvidencePKs)) {
            $schemaEvidenceMsgs[] = "Proposed keys match Primary Key constraints perfectly.";
        } else if ($schemaMatchCount > 0) {
            $schemaEvidenceMsgs[] = "Proposed keys partially match Primary Key constraints.";
        } else {
            $schemaEvidenceMsgs[] = "No Primary Key evidence supports the proposed keys.";
        }

        // 2. Statistical Evidence (Provider-aware)
        $uniquenessRatio = 0;
        $statisticalEvidenceMsgs = [];
        if (count($safeKeys) > 0) {
            try {
                // Provider-compatible distinct composite check
                // DB::raw or explicit columns depending on supported dialects. Most support COUNT(DISTINCT a, b)
                $keyCols = array_map(fn($k) => "`{$k}`", $safeKeys);
                $keyStr = implode(', ', $keyCols);
                $query = "SELECT COUNT(*) as total_rows, COUNT(DISTINCT {$keyStr}) as distinct_keys FROM `" . $tableName . "`";
                
                $result = $this->queryRepo->executeForAnalytics($query, [], 1, $connection);
                if (!empty($result['data'][0])) {
                    $total = (float) $result['data'][0]->total_rows;
                    $distinct = (float) $result['data'][0]->distinct_keys;
                    
                    if ($total > 0) {
                        $uniquenessRatio = $distinct / $total;
                        if ($uniquenessRatio >= 0.99) {
                            $statisticalEvidenceMsgs[] = implode(' + ', $safeKeys) . " covers rows uniquely (Ratio: " . round($uniquenessRatio, 2) . ").";
                        } else {
                            $statisticalEvidenceMsgs[] = implode(' + ', $safeKeys) . " does not uniquely identify rows (Ratio: " . round($uniquenessRatio, 2) . ").";
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Grain profiling failed on table {$tableName}: " . $e->getMessage());
                $statisticalEvidenceMsgs[] = "Statistical verification failed or unsupported by provider.";
            }
        }

        // 3. Confidence Normalization
        $llmConfidence = $grain['confidence'] ?? 0.5;
        
        $baseConfidence = $llmConfidence * 0.4; // LLM accounts for max 40%
        $schemaConfidence = ($schemaMatchCount > 0 && $schemaMatchCount === count($safeKeys)) ? 0.3 : 0.0;
        $statConfidence = ($uniquenessRatio >= 0.99) ? 0.3 : ($uniquenessRatio * 0.3);
        
        $finalConfidence = $baseConfidence + $schemaConfidence + $statConfidence;

        $grain['uniqueness_ratio'] = $uniquenessRatio;
        $grain['schema_evidence'] = $schemaEvidenceMsgs;
        $grain['statistical_evidence'] = $statisticalEvidenceMsgs;
        $grain['llm_confidence'] = $llmConfidence;
        $grain['confidence'] = round(min(1.0, max(0.0, $finalConfidence)), 2);
        
        // If keys didn't validate, wipe them out
        if (count($safeKeys) !== count($proposedKeys)) {
            $grain['keys'] = $safeKeys;
            $grain['confidence'] = 0.0; // Automatically invalidate if LLM hallucinated keys
        }

        return $suggestions;
    }

    protected function buildPrompt(\stdClass $tableDef, \Illuminate\Support\Collection $fields, array $profile): array
    {
        $dataRole = strtolower($tableDef->data_role ?? 'unknown');
        
        $systemMsg = <<<EOT
You are an expert BI Data Architect. Given a database table schema, lightweight data profiling stats, and sample values, you will extract deep business semantics.
Your goal is to understand not just column names, but the BUSINESS MEANING and ANALYTICAL CAPABILITIES of this table.

RULES:
1. Do not invent metrics or dimensions that don't exist.
2. Differentiate Order Level vs Line Level grain (e.g. OrderNbr vs LineNbr).
3. Do not arbitrarily sum multiple quantities (like Qty1, Qty2) together into one metric unless logically safe. Treat them as separate measures.
4. Extract Derived Metrics ONLY if safe (e.g., Margin % = (NetSales - COGS) / NULLIF(NetSales,0) * 100). Do not invent SQL that fails.
5. Identify Hierarchies (e.g., Principal -> Category -> Product) based on schema naming patterns.
6. Identify Dates and assign roles (e.g., transaction_date vs due_date).
7. Suggest Business Capabilities (e.g., Sales Analysis, Margin Analysis).
8. Suggest Question Patterns (e.g., ranking customer by sales).
9. Output STRICT JSON exactly matching the requested schema.
10. Assign confidence (0.00 to 1.00) and provide evidence for important decisions.
EOT;

        $userMsg = "Table Name: {$tableDef->entity_key}\nLogical Name: {$tableDef->logical_name}\nData Role: {$dataRole}\nDescription: {$tableDef->description}\n\n";
        $userMsg .= "Fields Definition:\n" . json_encode($fields, JSON_PRETTY_PRINT) . "\n\n";
        $userMsg .= "Data Profiling Stats (Masked):\n" . json_encode($profile, JSON_PRETTY_PRINT) . "\n\n";
        
        $userMsg .= <<<EOT
Provide your response strictly as JSON with this schema:
{
  "transaction_grain": {
    "level": "order_line|order|customer|product|aggregate|unknown",
    "keys": ["col1"],
    "confidence": 0.95,
    "evidence": ["..."]
  },
  "metrics": [
    {
      "name": "Net Sales",
      "source_column": "NetAmount",
      "aggregation": "SUM",
      "expression": "SUM(NetAmount)",
      "confidence": 0.9,
      "aliases": ["sales", "revenue"]
    }
  ],
  "derived_metrics": [
    {
      "name": "Margin %",
      "expression": "SUM(Margin) / NULLIF(SUM(NetAmount), 0) * 100",
      "depends_on": ["Margin", "NetAmount"],
      "confidence": 0.8
    }
  ],
  "dimensions": [
    {
      "name": "Customer",
      "source_column": "CustomerID",
      "aliases": ["client", "buyer"],
      "confidence": 0.9
    }
  ],
  "dates": [
    {
      "name": "Order Date",
      "source_column": "OrderDate",
      "role": "transaction_date|due_date|created_date",
      "confidence": 0.9
    }
  ],
  "hierarchies": [
    {
      "name": "Product Hierarchy",
      "levels": ["PrincipalID", "GroupID", "ProductID"],
      "confidence": 0.8
    }
  ],
  "capabilities": [
    {
      "name": "Sales Analysis",
      "required_metrics": ["Net Sales"],
      "dimensions": ["Customer", "Branch"],
      "dates": ["OrderDate"],
      "confidence": 0.9
    }
  ],
  "question_patterns": [
    {
      "name": "Top Customers",
      "intent": "ranking",
      "metric": "Net Sales",
      "dimension": "Customer",
      "direction": "desc",
      "confidence": 0.9
    }
  ],
  "synonyms": [
    {
      "name": "Net Sales",
      "synonyms": ["omzet", "penjualan bersih"]
    }
  ]
}
EOT;

        return [
            ['role' => 'system', 'content' => $systemMsg],
            ['role' => 'user', 'content' => $userMsg]
        ];
    }

    protected function parseAndValidateResponse(string $response, \Illuminate\Support\Collection $fields): array
    {
        // Extract JSON
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $matches)) {
            $response = $matches[1];
        }

        $parsed = json_decode($response, true);
        if (!$parsed || !is_array($parsed)) {
            throw new \Exception("Invalid JSON returned from Semantic Suggestion.");
        }
        
        $validCols = $fields->pluck('field_name')->map(fn($f) => strtolower($f))->toArray();
        
        // Ensure structure exists
        $normalized = [
            'transaction_grain' => $parsed['transaction_grain'] ?? null,
            'metrics' => [],
            'derived_metrics' => [],
            'dimensions' => [],
            'dates' => [],
            'hierarchies' => [],
            'capabilities' => [],
            'question_patterns' => [],
            'synonyms' => $parsed['synonyms'] ?? []
        ];

        // Validate basic source column matches for standard fields
        $schemaEvidencePKs = [];
        foreach ($fields as $f) {
            if (!empty($f->is_primary_key) || !empty($f->is_unique)) {
                $schemaEvidencePKs[] = strtolower($f->field_name);
            }
        }
        
        foreach (['metrics', 'dimensions', 'dates'] as $key) {
            if (isset($parsed[$key]) && is_array($parsed[$key])) {
                foreach ($parsed[$key] as $item) {
                    if (isset($item['source_column'])) {
                        $colLower = strtolower($item['source_column']);
                        if (in_array($colLower, $validCols)) {
                            // AC-03 Metric Classification Hard Block for Identifiers
                            if ($key === 'metrics' && strtoupper($item['aggregation'] ?? '') === 'SUM') {
                                $isIdentifier = preg_match('/(id|nbr|code|cd)$/i', $colLower) || in_array($colLower, $schemaEvidencePKs);
                                if ($isIdentifier) {
                                    continue; // Reject SUM on identifiers
                                }
                            }
                            $normalized[$key][] = $item;
                        }
                    }
                }
            }
        }
        
        // Validate derived metrics dependencies
        if (isset($parsed['derived_metrics']) && is_array($parsed['derived_metrics'])) {
            foreach ($parsed['derived_metrics'] as $item) {
                $safe = true;
                if (!empty($item['depends_on']) && is_array($item['depends_on'])) {
                    foreach ($item['depends_on'] as $dep) {
                        if (!in_array(strtolower($dep), $validCols)) {
                            $safe = false;
                        }
                    }
                }
                
                // Strict Tokenizer / Whitelist for Expression Security (AC-05)
                if (!empty($item['expression'])) {
                    $expr = $item['expression'];
                    
                    // Replace operators with space (keep original spaces)
                    $cleanExpr = preg_replace('/[\+\-\*\/\%\(\)\,]/', ' ', $expr);
                    
                    // Allowed functions
                    $allowedFuncs = ['NULLIF', 'COALESCE', 'ABS', 'ROUND', 'SUM', 'AVG', 'MIN', 'MAX', 'COUNT'];
                    $cleanExpr = preg_replace('/\b(' . implode('|', $allowedFuncs) . ')\b/i', ' ', $cleanExpr);
                    
                    // Remove standalone numbers/decimals
                    $cleanExpr = preg_replace('/\b[0-9\.]+\b/', ' ', $cleanExpr);
                    
                    // What remains should only be valid column names (case-insensitive)
                    $remainingTokens = array_filter(explode(' ', trim($cleanExpr)));
                    foreach ($remainingTokens as $token) {
                        if (!empty($token) && !in_array(strtolower($token), $validCols)) {
                            $safe = false; // Token is neither a valid column, nor a whitelisted function/operator
                            break;
                        }
                    }
                }
                
                if ($safe && !empty($item['depends_on'])) {
                    // Safe merging into generic metrics for UI compatibility
                    $normalized['derived_metrics'][] = $item;
                }
            }
        }

        // Collect valid semantic names for cross-validation
        $validMetrics = array_merge(
            array_column($normalized['metrics'], 'name'),
            array_column($normalized['derived_metrics'], 'name')
        );
        $validDimensions = array_column($normalized['dimensions'], 'name');

        // Validate hierarchies (AC-11)
        if (isset($parsed['hierarchies']) && is_array($parsed['hierarchies'])) {
            foreach ($parsed['hierarchies'] as $item) {
                if (isset($item['levels']) && is_array($item['levels'])) {
                    $valid = true;
                    foreach ($item['levels'] as $lvl) {
                        if (!in_array(strtolower($lvl), $validCols) && !in_array(strtolower($lvl), array_map('strtolower', $validDimensions))) {
                            $valid = false;
                        }
                    }
                    if ($valid && count($item['levels']) >= 2) {
                        $normalized['hierarchies'][] = $item;
                    }
                }
            }
        }
        
        // Validate Capabilities (AC-12)
        if (isset($parsed['capabilities']) && is_array($parsed['capabilities'])) {
            foreach ($parsed['capabilities'] as $item) {
                $valid = true;
                if (!empty($item['required_metrics'])) {
                    foreach ($item['required_metrics'] as $req) {
                        if (!in_array(strtolower($req), array_map('strtolower', $validMetrics))) {
                            $valid = false;
                        }
                    }
                }
                if ($valid) {
                    $normalized['capabilities'][] = $item;
                }
            }
        }
        
        // Validate Question Patterns (AC-13)
        if (isset($parsed['question_patterns']) && is_array($parsed['question_patterns'])) {
            foreach ($parsed['question_patterns'] as $item) {
                $valid = true;
                if (!empty($item['metric']) && !in_array(strtolower($item['metric']), array_map('strtolower', $validMetrics))) {
                    $valid = false;
                }
                if (!empty($item['dimension']) && !in_array(strtolower($item['dimension']), array_map('strtolower', $validDimensions))) {
                    $valid = false;
                }
                if ($valid) {
                    $normalized['question_patterns'][] = $item;
                }
            }
        }

        // Helper to normalize confidence
        $normalizeConfidence = function($val) {
            if ($val === null) return 0.5;
            if (!is_numeric($val)) return 0.5;
            $val = (float) $val;
            if ($val > 10.0 && $val <= 100.0) {
                $val = $val / 100.0;
            } else if ($val > 1.0) {
                return 1.0;
            }
            if ($val < 0.0) return 0.0;
            return $val;
        };
        
        // Normalize confidences
        foreach (['metrics', 'derived_metrics', 'dimensions', 'dates', 'hierarchies', 'capabilities', 'question_patterns'] as $key) {
            if (!empty($normalized[$key])) {
                foreach ($normalized[$key] as &$item) {
                    $item['confidence'] = $normalizeConfidence($item['confidence'] ?? 0.8);
                }
            }
        }
        
        if (!empty($normalized['transaction_grain'])) {
            $normalized['transaction_grain']['confidence'] = $normalizeConfidence($normalized['transaction_grain']['confidence'] ?? 0.8);
        }

        return $normalized;
    }

    protected function captureLearnings(int $connectionId, array $suggestions): void
    {
        // Capture synonyms with medium/low confidence as candidates
        if (!empty($suggestions['synonyms'])) {
            foreach ($suggestions['synonyms'] as $synMap) {
                $canonical = $synMap['name'] ?? null;
                $synonyms = $synMap['synonyms'] ?? [];
                
                if ($canonical && !empty($synonyms)) {
                    foreach ($synonyms as $syn) {
                        // Push to learning candidate system
                        $this->learningService->captureCandidate(
                            connectionId: $connectionId,
                            term: $syn,
                            type: 'synonym', // generic synonym map
                            mapping: $canonical,
                            confidence: 0.6, // auto medium for background captures
                            reason: "Suggested by AI semantic profiler"
                        );
                    }
                }
            }
        }
        
        // The rest of the suggestions will be returned to the UI for human approval.
    }
}
