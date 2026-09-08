<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;
use App\Models\AiQueryLibrary;
use App\Models\Connection;
use Illuminate\Support\Str;

class QueryRecognitionService
{
    public function __construct(
        private SemanticCandidateDiscoveryService $discoveryService
    ) {}

    public function recognize(string $requestText, int $connectionId, ?string $domainContext = null): ?array
    {
        $config = config('chatedp.query_recognition', []);
        if (empty($config['enabled'])) {
            return null;
        }

        $reasons = [];
        $confidence = 0.0;
        
        $stopwords = $config['stopwords'] ?? [];
        $ambiguousKeywords = $config['ambiguous_terms'] ?? [];
        $dateKeywords = $config['date_keywords'] ?? [];
        $aggKeywords = $config['aggregation_keywords'] ?? [];
        
        // 1. Normalize
        $normalized = Str::lower(trim(preg_replace('/[^\w\s-]/', '', $requestText)));
        
        $words = explode(' ', $normalized);
        $words = array_filter($words, fn($w) => !in_array($w, $stopwords) && trim($w) !== '');
        $normalized = implode(' ', $words);

        // 2. Check for Ambiguous Semantic Terms (Rankings, limits)
        foreach ($ambiguousKeywords as $amb) {
            if (str_contains($normalized, $amb)) {
                $reasons[] = 'ambiguous_term_detected:' . $amb;
                return $this->result('unrecognized', 0.0, $reasons);
            }
        }

        // 3. Extract Date Context
        $dateContext = null;
        foreach ($dateKeywords as $phrase => $context) {
            if (str_contains($normalized, $phrase)) {
                $dateContext = $context;
                $normalized = trim(str_replace($phrase, '', $normalized));
                $reasons[] = 'date_context_matched';
                $confidence += 0.2; // Optional evidence
                break;
            }
        }

        // 4. Extract Aggregation
        $aggregation = 'sum'; // default
        $explicitAggregation = false;
        foreach ($aggKeywords as $phrase => $agg) {
            if (str_contains($normalized, $phrase)) {
                $aggregation = $agg;
                $normalized = trim(str_replace($phrase, '', $normalized));
                $explicitAggregation = true;
                $reasons[] = 'aggregation_matched';
                break; // Take first
            }
        }

        // 5. Discover semantic terms
        $candidates = $this->discoveryService->discoverCandidates($normalized, $connectionId, $domainContext);
        
        $matchedMetrics = [];
        $matchedDimensions = [];

        foreach ($candidates['metrics'] ?? [] as $metric) {
            $nameLower = Str::lower($metric['name']);
            if (str_contains($normalized, $nameLower)) {
                $matchedMetrics[] = ['name' => $metric['name'], 'aggregation' => $aggregation];
                $normalized = trim(str_replace($nameLower, '', $normalized));
                $reasons[] = 'metric_matched';
                $confidence += 0.5; // Strong evidence
            }
        }
        
        if (empty($matchedMetrics)) {
            $reasons[] = 'no_metrics_found';
            return $this->result('unrecognized', 0.0, $reasons);
        }

        // Check for 'berdasarkan X', 'by X', 'per X', 'tiap X', 'setiap X'
        $dimensionPattern = '/(?:berdasarkan|per|by|tiap|setiap)\s+([a-z0-9_]+)/i';
        if (preg_match($dimensionPattern, $normalized, $matches)) {
            $dimText = $matches[1];
            foreach ($candidates['dimensions'] ?? [] as $dim) {
                if (str_contains($dimText, Str::lower($dim)) || str_contains(Str::lower($dim), $dimText)) {
                    $matchedDimensions[] = $dim;
                    $normalized = trim(str_replace($matches[0], '', $normalized));
                    $reasons[] = 'dimension_matched';
                    $confidence += 0.2; // Optional evidence
                }
            }
        }

        $remainingText = trim(preg_replace('/\s+/', ' ', $normalized));
        $residualWords = count(array_filter(explode(' ', $remainingText)));
        
        if ($residualWords > 0) {
            $confidence -= ($residualWords * 0.1); 
            $reasons[] = "residual_words:$residualWords";
        } else {
            $reasons[] = 'no_residual_text';
            $confidence += 0.1; 
        }

        // 6. Construct Logical Plan from Semantic Evidence
        $filters = [];
        $threshold = $config['min_fast_path_confidence'] ?? 0.95;
        
        // Scan QueryLibrary for structural hint
        $libraries = AiQueryLibrary::where('connection_id', $connectionId)->where('is_active', true)->get();
        $libraryHit = false;

        foreach ($libraries as $lib) {
            $canonical = $lib->canonical_plan_json;
            if (!$this->matchMetrics($matchedMetrics, $canonical['metrics'] ?? [], false)) {
                // strict aggregation checking off for library hint
                continue;
            }
            if (!$this->matchDimensions($matchedDimensions, $canonical['dimensions'] ?? [])) {
                continue;
            }

            $expectedFilters = $canonical['filters'] ?? [];
            if (count($expectedFilters) === 1 && !empty($remainingText)) {
                // Map the residual text safely
                $filters[] = [
                    'field' => $expectedFilters[0]['field'],
                    'operator' => $expectedFilters[0]['operator'] ?? '=',
                    'value' => $remainingText
                ];
                $reasons[] = 'filter_candidate_extracted_via_library';
                $confidence += 0.3;
                $libraryHit = true;
                break;
            } elseif (count($expectedFilters) === 0 && empty($remainingText)) {
                $reasons[] = 'exact_library_structure_match';
                $confidence += 0.3;
                $libraryHit = true;
                break;
            }
        }

        // If library misses but we have no residual text, we can still form a plan!
        if (!$libraryHit && empty($remainingText)) {
            $reasons[] = 'standalone_semantic_match_no_library';
            // Increase confidence to pass threshold organically
            $confidence += 0.35; 
        } elseif (!$libraryHit && !empty($remainingText)) {
            // We have residual text but no library structure to map it to. Too risky!
            $reasons[] = 'unresolved_residual_text';
            return $this->result('unrecognized', 0.0, $reasons);
        }

        $plan = new QueryPlan(
            intent: 'sales_analysis',
            metrics: $matchedMetrics,
            dimensions: $matchedDimensions,
            filters: $filters,
            date_context: $dateContext
        );
        
        $cappedConfidence = min(1.0, max(0.0, $confidence));
        $status = $cappedConfidence >= $threshold ? 'recognized' : 'uncertain';

        return $this->result($status, $cappedConfidence, $reasons, $plan);
    }
    
    private function result(string $status, float $confidence, array $reasons, ?QueryPlan $plan = null): array
    {
        return [
            'status' => $status,
            'confidence' => $confidence,
            'reasons' => $reasons,
            'plan' => $plan,
            'source' => $plan ? 'fast_path_recognition' : 'miss'
        ];
    }

    private function matchMetrics(array $extracted, array $canonical, bool $strictAggregation = true): bool
    {
        if (count($extracted) !== count($canonical)) {
            return false;
        }

        foreach ($extracted as $ext) {
            $found = false;
            foreach ($canonical as $can) {
                if (Str::lower($ext['name']) === Str::lower($can['name'])) {
                    if (!$strictAggregation || Str::lower($ext['aggregation']) === Str::lower($can['aggregation'])) {
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }

    private function matchDimensions(array $extracted, array $canonical): bool
    {
        if (count($extracted) !== count($canonical)) {
            return false;
        }

        foreach ($extracted as $ext) {
            $found = false;
            foreach ($canonical as $can) {
                if (Str::lower($ext) === Str::lower($can)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }
}
