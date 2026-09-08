<?php

namespace App\Services\AI;

use App\Models\Connection;
use App\Models\AiSemanticDefinition;
use Illuminate\Support\Facades\Log;

class SemanticLearningService
{
    public function __construct(
        protected SemanticCandidateDiscoveryService $discoveryService,
        protected OpenRouterClient $openRouter
    ) {}

    /**
     * Map an unknown term to a known logical business concept.
     */
    public function learnAndReturnMatch(Connection $connection, string $term, string $type, ?string $domainContext = null): ?string
    {
        try {
            // 1. Get logical semantic context
            $candidates = $this->discoveryService->discoverCandidates($term, $connection->id, $domainContext);
            
            $availableLogicalTerms = [];
            if ($type === 'metric') {
                $availableLogicalTerms = $candidates['metrics'] ?? [];
            } else {
                $availableLogicalTerms = $candidates['dimensions'] ?? [];
            }

            if (empty($availableLogicalTerms)) {
                // Try falling back to all context if filtered is empty
                $allCandidates = $this->discoveryService->discoverCandidates('', $connection->id, $domainContext);
                $availableLogicalTerms = ($type === 'metric') ? ($allCandidates['metrics'] ?? []) : ($allCandidates['dimensions'] ?? []);
            }

            if (empty($availableLogicalTerms)) {
                return null;
            }

            // 2. Build Prompt mapping unknown term to logical terms
            $logicalContextJson = json_encode(array_values(array_unique($availableLogicalTerms)), JSON_PRETTY_PRINT);
            
            $prompt = <<<EOT
The user requested a {$type} named "{$term}", but it does not match our exact semantic definitions.
Here are the available logical business concepts (metrics/dimensions) for this data:
{$logicalContextJson}

Based on semantic similarity and business logic, which logical concept is the MOST LIKELY intended match for the term "{$term}"?
Return a JSON object with the following structure:
{
    "matched_logical_term": "The exact logical concept name from the list above",
    "confidence": "high/medium/low",
    "reason": "Brief explanation"
}

If no logical concept seems to match at all, return {"matched_logical_term": null}.
Only return the JSON.
EOT;

            // 3. Call LLM
            // Note: Verify the method exists. OpenRouterClient has getCompletion($messages, $jsonFormat)
            $response = $this->openRouter->getCompletion([
                ['role' => 'system', 'content' => 'You are a business data analyst assistant. Your job is to map an unrecognized user term to a known logical concept. Respond ONLY with a JSON object.'],
                ['role' => 'user', 'content' => $prompt]
            ], true);

            $response = preg_replace('/^```json\s*/i', '', $response);
            $response = preg_replace('/```$/', '', $response);

            $result = json_decode(trim($response), true);

            if ($result && !empty($result['matched_logical_term']) && isset($result['confidence'])) {
                $matchedLogical = $result['matched_logical_term'];
                $confidence = strtolower($result['confidence']);

                // Only auto-learn on high confidence
                if ($confidence === 'high') {
                    $this->saveSemanticAlias($connection->id, $matchedLogical, $term);
                    Log::info("ChatEDP Smart Learning: Successfully learned '{$term}' as logical concept '{$matchedLogical}'.");
                    return $matchedLogical;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error("ChatEDP Smart Learning Failed for term '{$term}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Add the new term as an alias to the matching AiSemanticDefinition.
     */
    protected function saveSemanticAlias(int $connectionId, string $logicalTerm, string $alias)
    {
        // Find by exact logical name or existing alias
        $existing = AiSemanticDefinition::where('connection_id', $connectionId)
            ->where('name', $logicalTerm)
            ->first();

        if ($existing) {
            $aliasLower = strtolower(trim($alias));
            
            // Check if this alias already exists in ANY other definition
            $conflict = AiSemanticDefinition::where('connection_id', $connectionId)
                ->where('metadata', 'LIKE', '%"' . $aliasLower . '"%')
                ->first();
                
            if ($conflict) {
                Log::info("ChatEDP Smart Learning: Alias '{$alias}' already exists on '{$conflict->name}'. Aborting save.");
                return;
            }

            $metadata = is_string($existing->metadata) ? json_decode($existing->metadata, true) : ($existing->metadata ?? []);
            
            if (!isset($metadata['aliases'])) {
                $metadata['aliases'] = [];
            }
            
            $existingAliases = array_map('strtolower', $metadata['aliases']);
            
            if (!in_array($aliasLower, $existingAliases)) {
                $metadata['aliases'][] = $aliasLower;
                $existing->metadata = $metadata;
                $existing->save();
            }
        }
        // Note: If no existing logical definition is found (e.g., if it's purely from SyncEntityField fallback), 
        // we skip saving to AiSemanticDefinition for now. AiSemanticDefinition is the authority for learned aliases.
    }
}
