<?php

namespace App\Services\Metadata;

use App\Models\SyncEntity;
use App\Models\SyncEntityField;

class SemanticResolutionService
{
    /**
     * Resolve a business term to a physical SQL field using semantic metadata.
     * 
     * @param string $term The user or business term to resolve
     * @param string|null $entityContext The entity code or name to restrict the search
     * @param string|null $semanticType Optional filter for semantic_type (e.g., 'metric', 'dimension')
     * @return array Structured resolution result
     */
    public function resolveTerm(string $term, ?string $entityContext = null, ?string $semanticType = null): array
    {
        $normalizedTerm = $this->normalizeText($term);
        if (empty($normalizedTerm)) {
            return $this->noMatchResult('Term is empty or invalid after normalization.');
        }

        $entityQuery = SyncEntity::where('is_active', true);
        
        if ($entityContext) {
            $normalizedContext = $this->normalizeText($entityContext);
            $entityQuery->where(function ($q) use ($normalizedContext, $entityContext) {
                $q->where('code', $entityContext)
                  ->orWhere('name', 'LIKE', '%' . $normalizedContext . '%');
            });
        }

        $entities = $entityQuery->get();
        if ($entities->isEmpty()) {
            return $this->noMatchResult("No active entity found matching context: {$entityContext}");
        }

        $entityIds = $entities->pluck('id')->toArray();
        $fieldsQuery = SyncEntityField::whereIn('sync_entity_id', $entityIds)
                                      ->where('is_enabled', true)
                                      ->whereNotNull('destination_field');
        
        if ($semanticType) {
            $fieldsQuery->where('semantic_type', $semanticType);
        }

        $fields = $fieldsQuery->get();
        if ($fields->isEmpty()) {
            return $this->noMatchResult('No enabled fields available for resolution in the active entities.');
        }

        $candidates = [];

        foreach ($fields as $field) {
            $scoreResult = $this->scoreField($field, $normalizedTerm);
            if ($scoreResult['score'] > 0) {
                $entity = $entities->firstWhere('id', $field->sync_entity_id);
                $candidates[] = [
                    'entity' => $entity->code,
                    'field_id' => $field->id,
                    'destination_field' => $field->destination_field,
                    'semantic_type' => $field->semantic_type,
                    'label' => $field->label,
                    'description' => $field->description,
                    'match_reason' => $scoreResult['reason'],
                    'confidence' => $scoreResult['score'],
                ];
            }
        }

        if (empty($candidates)) {
            return $this->noMatchResult("No field matched the term '{$term}'.");
        }

        // Sort candidates by confidence descending
        usort($candidates, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        $topScore = $candidates[0]['confidence'];
        
        // Find all candidates that share the top score
        $topCandidates = array_filter($candidates, function ($c) use ($topScore) {
            return $c['confidence'] === $topScore;
        });

        if (count($topCandidates) > 1) {
            return [
                'matched' => false,
                'ambiguous' => true,
                'message' => 'Multiple equivalent matches found.',
                'candidates' => array_values($topCandidates)
            ];
        }

        $match = $candidates[0];
        
        return [
            'matched' => true,
            'ambiguous' => false,
            'entity' => $match['entity'],
            'field_id' => $match['field_id'],
            'destination_field' => $match['destination_field'],
            'semantic_type' => $match['semantic_type'],
            'label' => $match['label'],
            'description' => $match['description'],
            'match_reason' => $match['match_reason'],
            'confidence' => $match['confidence'],
        ];
    }

    /**
     * Scores a field against the normalized term.
     */
    protected function scoreField(SyncEntityField $field, string $normalizedTerm): array
    {
        $normDest = $this->normalizeText($field->destination_field ?? '');
        $normSource = $this->normalizeText($field->source_field ?? '');
        $normLabel = $this->normalizeText($field->label ?? '');

        // 100: Exact match on core physical/semantic names
        if ($normDest === $normalizedTerm) return ['score' => 100, 'reason' => 'exact_destination'];
        if ($normSource === $normalizedTerm) return ['score' => 100, 'reason' => 'exact_source'];
        if ($normLabel === $normalizedTerm) return ['score' => 100, 'reason' => 'exact_label'];

        // 95: Exact match within aliases
        if (is_array($field->aliases)) {
            foreach ($field->aliases as $alias) {
                if ($this->normalizeText($alias) === $normalizedTerm) {
                    return ['score' => 95, 'reason' => 'exact_alias'];
                }
            }
        }

        // 80: Partial normalized label
        if ($normLabel && str_contains($normLabel, $normalizedTerm)) {
            return ['score' => 80, 'reason' => 'partial_label'];
        }

        // 70: Partial normalized physical field
        if ($normDest && str_contains($normDest, $normalizedTerm)) {
            return ['score' => 70, 'reason' => 'partial_destination'];
        }
        
        if ($normSource && str_contains($normSource, $normalizedTerm)) {
            return ['score' => 70, 'reason' => 'partial_source'];
        }

        return ['score' => 0, 'reason' => 'no_match'];
    }

    /**
     * Normalizes text for deterministic matching.
     */
    protected function normalizeText(string $text): string
    {
        $text = strtolower($text);
        // Remove common non-alphanumeric separators to normalize spacing/underscores
        $text = preg_replace('/[^a-z0-9]/', '', $text);
        return trim($text);
    }

    /**
     * Standard safe no-match return structure.
     */
    protected function noMatchResult(string $reason): array
    {
        return [
            'matched' => false,
            'ambiguous' => false,
            'message' => $reason,
        ];
    }
}
