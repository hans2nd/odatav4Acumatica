<?php

namespace App\Services\AI;

use App\Models\Connection;
use Illuminate\Support\Facades\DB;

class SemanticResolutionService
{
    /**
     * Resolves a logical business term (metric, dimension, date) into a physical SQL column.
     * Uses AiSemanticDefinition, SyncEntityField, and sync_metadata_fields.
     * Does NOT use the LLM to guess physical columns.
     */
    public function resolve(string $logicalTerm, Connection $connection, array $resolvedTables, string $expectedType): array
    {
        $normalizedTerm = $this->normalizeText($logicalTerm);
        if (empty($normalizedTerm)) {
            return $this->noMatchResult("Term is empty or invalid.");
        }

        $candidates = [];

        // 1. Check AiSemanticDefinition (Metrics, specialized dimensions)
        $semanticDefs = \App\Models\AiSemanticDefinition::where('connection_id', $connection->id)
            ->whereIn('source_table', $resolvedTables)
            ->get();

        foreach ($semanticDefs as $def) {
            $score = $this->scoreMatch($def->name, $def->metadata, $normalizedTerm);
            if ($score > 0) {
                // Determine if expression
                $isExpr = false;
                $name = $def->source_column;
                $meta = is_string($def->metadata) ? json_decode($def->metadata, true) : ($def->metadata ?? []);
                $agg = strtoupper($meta['aggregation'] ?? '');
                
                if (!empty($def->expression) && $def->expression !== $def->source_column) {
                    $expr = trim($def->expression);
                    $expr = str_ireplace('AS NUMERIC', 'AS DECIMAL(18,4)', $expr);
                    // A semantic expression may carry its default aggregate even
                    // when metadata omits aggregation. Strip a single aggregate so
                    // the validated plan can safely apply the user-requested one.
                    if (preg_match('/^(SUM|COUNT|AVG|MIN|MAX)\s*\((.*)\)$/i', $expr, $matches)) {
                        $name = $matches[1];
                        $name = $matches[2];
                        $isExpr = !preg_match('/^[a-zA-Z0-9_]+$/', $name);
                    } else {
                        $name = $expr;
                        $isExpr = true;
                    }
                }

                $candidates[] = [
                    'logical_term' => $def->name,
                    'resolved_entity' => $def->source_table,
                    'physical_mapping' => $name,
                    'is_expression' => $isExpr,
                    'aggregation' => $agg ?: 'SUM',
                    'semantic_type' => $def->type,
                    'confidence' => $score,
                    'source' => 'AiSemanticDefinition'
                ];
            }
        }

        // 2. Check SyncEntityField (via SyncEntity)
        $syncEntities = \App\Models\SyncEntity::whereIn('source_object', $resolvedTables)->get();
        if ($syncEntities->isNotEmpty()) {
            $entityIds = $syncEntities->pluck('id')->toArray();
            $fields = \App\Models\SyncEntityField::whereIn('sync_entity_id', $entityIds)
                ->where('is_enabled', true)
                ->whereNotNull('destination_field')
                ->get();

            foreach ($fields as $field) {
                $score = $this->scoreMatch($field->label ?? $field->destination_field, $field->aliases, $normalizedTerm);
                // Extra weight if physical name matches exactly
                if ($this->normalizeText($field->destination_field) === $normalizedTerm) {
                    $score = 100;
                }
                
                if ($score > 0) {
                    $entity = $syncEntities->firstWhere('id', $field->sync_entity_id);
                    $candidates[] = [
                        'logical_term' => $field->label ?? $field->destination_field,
                        'resolved_entity' => $entity->destination_table,
                        'physical_mapping' => $field->destination_field,
                        'is_expression' => false,
                        'aggregation' => null,
                        'semantic_type' => $field->semantic_type,
                        'confidence' => $score,
                        'source' => 'SyncEntityField'
                    ];
                }
            }
        }

        // 3. Check legacy sync_metadata_fields
        $legacyFields = DB::table('sync_metadata_fields')
            ->join('sync_metadata_definitions', 'sync_metadata_definitions.id', '=', 'sync_metadata_fields.definition_id')
            ->where('sync_metadata_definitions.connection_id', $connection->id)
            ->whereIn('sync_metadata_definitions.entity_key', $resolvedTables)
            ->select('sync_metadata_fields.field_name', 'sync_metadata_fields.semantic_type', 'sync_metadata_definitions.entity_key')
            ->get();

        foreach ($legacyFields as $field) {
            // Note: legacy fields rarely had nice labels, so we check raw field name
            if ($this->normalizeText($field->field_name) === $normalizedTerm) {
                $candidates[] = [
                    'logical_term' => $field->field_name,
                    'resolved_entity' => $field->entity_key,
                    'physical_mapping' => $field->field_name,
                    'is_expression' => false,
                    'aggregation' => null,
                    'semantic_type' => $field->semantic_type ?? $expectedType,
                    'confidence' => 100, // Exact physical match
                    'source' => 'LegacyMetadata'
                ];
            }
        }

        // Filter by expected type if provided (loose filtering)
        if ($expectedType) {
            foreach ($candidates as &$c) {
                if (strtolower((string)$c['semantic_type']) === strtolower($expectedType)) {
                    $c['confidence'] += 10;
                }
            }
        }

        if (empty($candidates)) {
            return $this->noMatchResult("No semantic mapping found for logical term: '{$logicalTerm}'");
        }

        // Sort by confidence
        usort($candidates, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        
        $topScore = $candidates[0]['confidence'];
        $topCandidates = array_filter($candidates, fn($c) => $c['confidence'] === $topScore);
        
        // Deduplicate top candidates by physical mapping
        $uniqueMappings = [];
        $distinctCandidates = [];
        foreach ($topCandidates as $c) {
            $key = $c['resolved_entity'] . '.' . $c['physical_mapping'];
            if (!in_array($key, $uniqueMappings)) {
                $uniqueMappings[] = $key;
                $distinctCandidates[] = $c;
            }
        }

        if (count($distinctCandidates) > 1) {
            return [
                'matched' => false,
                'ambiguous' => true,
                'message' => 'Multiple equivalent semantic matches found.',
                'candidates' => $distinctCandidates
            ];
        }

        $match = $distinctCandidates[0];

        return [
            'matched' => true,
            'ambiguous' => false,
            'logical_term' => $match['logical_term'],
            'resolved_entity' => $match['resolved_entity'],
            'physical_mapping' => $match['physical_mapping'],
            'is_expression' => $match['is_expression'],
            'aggregation' => $match['aggregation'],
            'semantic_type' => $match['semantic_type'],
            'confidence' => $match['confidence'],
            'source' => $match['source']
        ];
    }
    
    /**
     * Resolve default metric if none specified
     */
    public function resolveDefaultMetric(Connection $connection, array $resolvedTables): array
    {
        $semanticMetrics = \App\Models\AiSemanticDefinition::where('connection_id', $connection->id)
            ->where('type', 'metric')
            ->whereIn('source_table', $resolvedTables)
            ->get();
            
        $chosenMetric = null;
        foreach ($semanticMetrics as $metric) {
            $metadata = is_string($metric->metadata) ? json_decode($metric->metadata, true) : ($metric->metadata ?? []);
            if (!empty($metadata['is_default'])) {
                $chosenMetric = $metric;
                break;
            }
        }
        
        if (!$chosenMetric && $semanticMetrics->count() === 1) {
            $chosenMetric = $semanticMetrics->first();
        }
        
        if (!$chosenMetric) {
            if ($semanticMetrics->count() > 1) {
                return $this->noMatchResult("Multiple metrics found but no default configured.");
            }
            return $this->noMatchResult("No metrics defined for the selected tables.");
        }
        
        $meta = is_string($chosenMetric->metadata) ? json_decode($chosenMetric->metadata, true) : ($chosenMetric->metadata ?? []);
        $agg = strtoupper($meta['aggregation'] ?? 'SUM');
        
        $name = $chosenMetric->source_column;
        $isExpr = false;
        if (!empty($chosenMetric->expression) && $chosenMetric->expression !== $chosenMetric->source_column) {
            $expr = trim($chosenMetric->expression);
            $expr = str_ireplace('AS NUMERIC', 'AS DECIMAL(18,4)', $expr);
            if (preg_match('/^(SUM|COUNT|AVG|MIN|MAX)\s*\((.*)\)$/i', $expr, $matches)) {
                $name = $matches[2];
            } else {
                $name = $expr;
                $isExpr = true;
            }
        }
        
        return [
            'matched' => true,
            'ambiguous' => false,
            'logical_term' => $chosenMetric->name,
            'resolved_entity' => $chosenMetric->source_table,
            'physical_mapping' => $name,
            'is_expression' => $isExpr,
            'aggregation' => $agg,
            'semantic_type' => 'metric',
            'confidence' => 100,
            'source' => 'AiSemanticDefinition'
        ];
    }
    
    /**
     * Finds the most likely date field for a given set of tables.
     */
    public function resolveDateField(Connection $connection, array $resolvedTables): array
    {
        // Check AiSemanticDefinition for explicit date configurations first
        $semanticDates = \App\Models\AiSemanticDefinition::where('connection_id', $connection->id)
            ->where('type', 'dimension')
            ->whereIn('source_table', $resolvedTables)
            ->get();
            
        foreach ($semanticDates as $sd) {
            $meta = is_string($sd->metadata) ? json_decode($sd->metadata, true) : ($sd->metadata ?? []);
            if (!empty($meta['is_default_date'])) {
                return [
                    'matched' => true,
                    'ambiguous' => false,
                    'logical_term' => $sd->name,
                    'resolved_entity' => $sd->source_table,
                    'physical_mapping' => $sd->source_column,
                    'is_expression' => false,
                    'aggregation' => null,
                    'semantic_type' => 'date',
                    'confidence' => 100,
                    'source' => 'AiSemanticDefinition'
                ];
            }
        }
        
        // Fallback to legacy metadata heuristic
        $dateField = DB::table('sync_metadata_fields')
            ->join('sync_metadata_definitions', 'sync_metadata_definitions.id', '=', 'sync_metadata_fields.definition_id')
            ->where('sync_metadata_definitions.connection_id', $connection->id)
            ->whereIn('sync_metadata_definitions.entity_key', $resolvedTables)
            ->where(function($q) {
                $q->where('sync_metadata_fields.semantic_type', 'date')
                  ->orWhere('sync_metadata_fields.semantic_type', 'datetime')
                  ->orWhere('sync_metadata_fields.field_name', 'like', '%date%')
                  ->orWhere('sync_metadata_fields.field_name', 'like', '%tanggal%');
            })
            // Use ID instead of is_primary_key to prevent errors if column missing in older DBs
            ->orderBy('sync_metadata_fields.id', 'asc')
            ->first();
            
        if ($dateField) {
            return [
                'matched' => true,
                'ambiguous' => false,
                'logical_term' => $dateField->field_name,
                'resolved_entity' => $dateField->entity_key,
                'physical_mapping' => $dateField->field_name,
                'is_expression' => false,
                'aggregation' => null,
                'semantic_type' => 'date',
                'confidence' => 80,
                'source' => 'LegacyMetadata'
            ];
        }
        
        return $this->noMatchResult("No suitable date field found.");
    }

    private function scoreMatch(string $primaryName, $metadataOrAliases, string $normalizedTerm): int
    {
        $normPrimary = $this->normalizeText($primaryName);
        if ($normPrimary === $normalizedTerm) return 100;
        
        $aliases = [];
        if (is_array($metadataOrAliases)) {
            $aliases = $metadataOrAliases['aliases'] ?? $metadataOrAliases;
        } elseif (is_string($metadataOrAliases)) {
            $meta = json_decode($metadataOrAliases, true);
            $aliases = $meta['aliases'] ?? [];
        }
        
        if (is_array($aliases)) {
            foreach ($aliases as $alias) {
                if ($this->normalizeText($alias) === $normalizedTerm) {
                    return 95;
                }
            }
        }
        
        // fuzzy
        if ($normPrimary && str_contains($normPrimary, $normalizedTerm)) {
            return 70;
        }
        
        return 0;
    }

    private function normalizeText(string $text): string
    {
        $text = strtolower($text);
        return trim(preg_replace('/[^a-z0-9]/', '', $text));
    }

    private function noMatchResult(string $reason): array
    {
        return [
            'matched' => false,
            'ambiguous' => false,
            'message' => $reason,
        ];
    }
}
