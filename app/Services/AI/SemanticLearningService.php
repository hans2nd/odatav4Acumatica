<?php

namespace App\Services\AI;

use App\Models\Connection;
use App\Models\AiSemanticDefinition;
use Illuminate\Support\Facades\Log;
use App\Services\AI\SchemaCacheService;

class SemanticLearningService
{
    protected $schemaCache;
    protected $openRouter;

    public function __construct(SchemaCacheService $schemaCache, OpenRouterClient $openRouter)
    {
        $this->schemaCache = $schemaCache;
        $this->openRouter = $openRouter;
    }

    /**
     * Attempt to learn a new semantic definition for an unrecognized term.
     *
     * @param Connection $connection
     * @param string $term The unrecognized term (e.g. "Bandung")
     * @param string $type The type of term ('dimension' or 'metric')
     * @param string|null $tableContext The table context if known
     * @return bool True if learned successfully, false otherwise
     */
    public function learn(Connection $connection, string $term, string $type = 'dimension', ?string $tableContext = null): bool
    {
        $match = $this->learnAndReturnMatch($connection, $term, $type, $tableContext);
        return $match !== null;
    }

    /**
     * Attempt to learn a new semantic definition and return the matched physical column.
     *
     * @param Connection $connection
     * @param string $term The unrecognized term
     * @param string $type The type of term
     * @param string|null $tableContext The table context
     * @return string|null The matched column name or null if not found
     */
    public function learnAndReturnMatch(Connection $connection, string $term, string $type = 'dimension', ?string $tableContext = null): ?string
    {
        try {
            // Get schema for the connection
            $schema = $this->schemaCache->getSchema($connection);
            
            // Limit schema to context table if provided
            if ($tableContext && isset($schema[$tableContext])) {
                $schemaContext = [$tableContext => $schema[$tableContext]];
            } else {
                $schemaContext = $schema;
            }

            // Create a prompt for the LLM to map the term
            $prompt = $this->buildPrompt($term, $type, $schemaContext);

            // Call LLM
            $response = $this->openRouter->chat([
                ['role' => 'system', 'content' => 'You are a data analyst assistant. Your job is to map an unrecognized user term to a physical database column based on the provided schema. Respond ONLY with a JSON object.'],
                ['role' => 'user', 'content' => $prompt]
            ], true); // Force JSON response if supported

            // Remove markdown code blocks if any
            $response = preg_replace('/```json\s*/', '', $response);
            $response = preg_replace('/```\s*/', '', $response);

            $result = json_decode(trim($response), true);

            if ($result && isset($result['matched_column']) && isset($result['table'])) {
                $matchedColumn = $result['matched_column'];
                $table = $result['table'];

                if (!$matchedColumn || !$table) {
                    return null;
                }

                // Save to ai_semantic_definitions
                $this->saveSemanticAlias($connection->id, $table, $matchedColumn, $term, $type);
                
                Log::info("ChatEDP Smart Learning: Successfully learned '{$term}' as '{$matchedColumn}' in table '{$table}'.");
                return $matchedColumn;
            }

            return null;

        } catch (\Exception $e) {
            Log::error("ChatEDP Smart Learning Failed for term '{$term}': " . $e->getMessage());
            return null;
        }
    }

    protected function buildPrompt(string $term, string $type, array $schema): string
    {
        $schemaJson = json_encode($schema, JSON_PRETTY_PRINT);
        
        return <<<EOT
The user requested a {$type} named "{$term}", but it does not exist in our semantic definitions.
Here is the available physical database schema:
{$schemaJson}

Based on the physical column names and types, which column is the MOST LIKELY match for the term "{$term}"?
Return a JSON object with the following structure:
{
    "matched_column": "The exact physical column name",
    "table": "The exact table name where the column is found",
    "confidence": "high/medium/low",
    "reason": "Brief explanation"
}

If no column seems to match at all, return {"matched_column": null, "table": null}.
Only return the JSON.
EOT;
    }

    protected function saveSemanticAlias(int $connectionId, string $table, string $column, string $alias, string $type)
    {
        // First check if the column is already defined in semantic definitions
        $existing = AiSemanticDefinition::where('connection_id', $connectionId)
            ->where('source_table', $table)
            ->where('source_column', $column)
            ->where('type', $type)
            ->first();

        if ($existing) {
            $metadata = is_string($existing->metadata) ? json_decode($existing->metadata, true) : ($existing->metadata ?? []);
            
            if (!isset($metadata['aliases'])) {
                $metadata['aliases'] = [];
            }
            
            // Add new alias if it doesn't exist
            if (!in_array(strtolower($alias), array_map('strtolower', $metadata['aliases']))) {
                $metadata['aliases'][] = strtolower($alias);
                $existing->metadata = $metadata;
                $existing->save();
            }
        } else {
            // Create new semantic definition
            $metadata = [
                'aliases' => [strtolower($alias)],
                'description' => "Auto-learned from user query for term '{$alias}'"
            ];
            
            if ($type === 'metric') {
                $metadata['aggregation'] = 'SUM'; // Default aggregation for auto-learned metrics
            }

            AiSemanticDefinition::create([
                'connection_id' => $connectionId,
                'type' => $type,
                'name' => ucfirst($column), // User friendly name
                'source_table' => $table,
                'source_column' => $column,
                'metadata' => $metadata,
            ]);
        }
    }
}
