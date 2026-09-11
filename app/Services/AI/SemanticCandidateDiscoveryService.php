<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

class SemanticCandidateDiscoveryService
{
    public function __construct(
        private SchemaCacheService $schemaCacheService
    ) {}

    /**
     * Discover relevant semantic candidates based on the user prompt.
     * Returns a highly curated set of logical candidates without exposing physical names.
     */
    public function discoverCandidates(string $userPrompt, int $connectionId, ?string $domainContext = null, array $activeTables = []): array
    {
        // 1. Fetch full schema context (logical representations)
        $cache = $this->schemaCacheService->getCache($connectionId, $domainContext);
        $allTables = $cache->tables ?? [];

        $discovered = [
            'tables' => [],
            'metrics' => [],
            'dimensions' => [],
            'date_fields' => [],
            'semantic_dates' => [],
            'filterable_fields' => [],
            'capabilities' => [],
            'hierarchies' => [],
            'question_patterns' => [],
            'transaction_grain' => []
        ];

        // 2. Score and filter tables based on the user prompt
        $normalizedPrompt = Str::lower($userPrompt);

        foreach ($allTables as $table) {
            $tableScore = $this->scoreRelevance($table, $normalizedPrompt);
            
            // Persist previously active tables to ensure context isn't lost on short follow-ups
            if (in_array($table['logical_name'], $activeTables) || in_array($table['physical_name'] ?? '', $activeTables)) {
                $tableScore += 10;
            }
            
            $metricScores = [];
            foreach ($table['metrics'] ?? [] as $metric) {
                $metricScores[$metric['name']] = $this->scoreRelevance(['logical_name' => $metric['name'], 'aliases' => $metric['aliases'] ?? []], $normalizedPrompt);
            }

            $dimScores = [];
            foreach ($table['dimensions'] ?? [] as $dim) {
                $dimScores[$dim['name']] = $this->scoreRelevance(['logical_name' => $dim['name'], 'aliases' => $dim['aliases'] ?? []], $normalizedPrompt);
            }

            $maxChildScore = max(array_merge([0], array_values($metricScores), array_values($dimScores)));
            $totalScore = $tableScore + $maxChildScore;

            // If the table passes a minimal relevance threshold, include its semantic entities
            if ($totalScore > 0) {
                // Register table
                $discovered['tables'][] = [
                    'logical_name' => $table['logical_name'],
                    'description' => $table['description'] ?? '',
                    'relevance_score' => $totalScore
                ];

                // Metrics
                foreach ($table['metrics'] ?? [] as $metric) {
                    if ($metricScores[$metric['name']] > 0 || $totalScore >= 3) {
                        $discovered['metrics'][] = [
                            'name' => $metric['name'],
                            'aggregation' => $metric['aggregation'] ?? 'SUM',
                        ];
                    }
                }

                // Dimensions
                foreach ($table['dimensions'] ?? [] as $dim) {
                    if ($dimScores[$dim['name']] > 0 || $totalScore >= 3) {
                        $discovered['dimensions'][] = $dim['name'];
                    }
                }

                // Global / table-wide date fields
                foreach ($table['date_fields'] ?? [] as $dateField) {
                    $discovered['date_fields'][] = $dateField;
                }
                
                foreach ($table['semantic_dates'] ?? [] as $sd) {
                    $discovered['semantic_dates'][] = $sd;
                }
                
                // Advanced Semantic Constructs
                if (!empty($table['capabilities'])) {
                    $discovered['capabilities'] = array_merge($discovered['capabilities'], $table['capabilities']);
                }
                if (!empty($table['hierarchies'])) {
                    $discovered['hierarchies'] = array_merge($discovered['hierarchies'], $table['hierarchies']);
                }
                if (!empty($table['question_patterns'])) {
                    $discovered['question_patterns'] = array_merge($discovered['question_patterns'], $table['question_patterns']);
                }
                if (!empty($table['transaction_grain'])) {
                    $discovered['transaction_grain'][] = [
                        'table' => $table['logical_name'],
                        'grain' => $table['transaction_grain']
                    ];
                }

                // Filterable fields (only keeping those that might be somewhat relevant to avoid explosion)
                foreach ($table['filterable_fields'] ?? [] as $filterField) {
                    if (str_contains($normalizedPrompt, Str::lower($filterField)) || $totalScore >= 3) {
                        $discovered['filterable_fields'][] = $filterField;
                    }
                }
            }
        }

        // 3. Deduplicate
        $discovered['metrics'] = array_map("unserialize", array_unique(array_map("serialize", $discovered['metrics'])));
        $discovered['dimensions'] = array_values(array_unique($discovered['dimensions']));
        $discovered['date_fields'] = array_values(array_unique($discovered['date_fields']));
        $discovered['semantic_dates'] = array_map("unserialize", array_unique(array_map("serialize", $discovered['semantic_dates'])));
        $discovered['filterable_fields'] = array_values(array_unique($discovered['filterable_fields']));
        
        // Advanced semantics deduction
        $discovered['capabilities'] = array_map("unserialize", array_unique(array_map("serialize", $discovered['capabilities'])));
        $discovered['hierarchies'] = array_map("unserialize", array_unique(array_map("serialize", $discovered['hierarchies'])));
        $discovered['question_patterns'] = array_map("unserialize", array_unique(array_map("serialize", $discovered['question_patterns'])));
        
        // Sort tables by relevance
        usort($discovered['tables'], fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        // Remove scores before returning to LLM to save tokens
        $discovered['tables'] = array_map(function($t) {
            unset($t['relevance_score']);
            return $t;
        }, $discovered['tables']);

        return $discovered;
    }

    /**
     * Deterministic relevance scoring
     */
    private function scoreRelevance(array $item, string $normalizedPrompt): int
    {
        $score = 0;
        
        $logicalName = Str::lower($item['logical_name'] ?? '');
        $aliases = array_map(fn($a) => Str::lower((string)$a), $item['aliases'] ?? []);
        $description = Str::lower($item['description'] ?? '');

        // 1. Exact logical-name match
        if ($logicalName && preg_match('/\b' . preg_quote($logicalName, '/') . '\b/', $normalizedPrompt)) {
            $score += 10;
        }

        // 2. Exact/Normalized alias match
        foreach ($aliases as $alias) {
            if ($alias && preg_match('/\b' . preg_quote($alias, '/') . '\b/', $normalizedPrompt)) {
                $score += 8;
                break;
            }
        }

        // 3. Partial logical-name match (fuzzy)
        if ($logicalName && str_contains($normalizedPrompt, $logicalName)) {
            $score += 5;
        }

        // 4. Description relevance
        if ($description) {
            $words = explode(' ', $normalizedPrompt);
            $matchCount = 0;
            foreach ($words as $word) {
                if (strlen($word) > 4 && str_contains($description, $word)) {
                    $matchCount++;
                }
            }
            if ($matchCount > 0) {
                $score += (2 * $matchCount);
            }
        }

        // 5. Implicit domains (e.g. sales, transaction, order)
        if (str_contains($normalizedPrompt, 'jual') || str_contains($normalizedPrompt, 'sales') || str_contains($normalizedPrompt, 'omzet')) {
            if (str_contains($logicalName, 'sale') || str_contains($logicalName, 'jual') || str_contains($logicalName, 'penjualan')) {
                $score += 3;
            }
        }

        return $score;
    }
}
