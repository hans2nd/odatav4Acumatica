<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;
use App\Models\Connection;
use Illuminate\Support\Facades\DB;

class QueryValidatorService
{
    public function __construct(
        protected SemanticResolutionService $semanticResolver,
        protected SemanticLearningService $learningService,
        protected RelationshipValidationService $relationshipValidator
    ) {}

    public function validate(QueryPlan $plan, Connection $connection, ?string $domainContext = null): QueryPlan
    {
        if (!$connection->is_analytics_source) {
            throw new \Exception("Unauthorized: The selected connection is not the designated analytics source.");
        }

        if (strtolower((string) ($plan->comparison['type'] ?? '')) === 'table') {
            return $this->validateTableComparison($plan, $connection, $domainContext);
        }

        // 1. Resolve Tables
        $requestedTables = $plan->tables;
        // A fast-path/legacy plan may omit its table. Derive it only from an exact
        // AI Semantic Definition; this is metadata resolution, not LLM inference.
        if (empty($requestedTables) && !empty($plan->metrics)) {
            foreach ($plan->metrics as $metric) {
                $term = strtolower(trim((string) ($metric['name'] ?? '')));
                $definitions = \App\Models\AiSemanticDefinition::where('connection_id', $connection->id)->where('type', 'metric')->get();
                foreach ($definitions as $definition) {
                    $metadata = (array) ($definition->metadata ?? []);
                    $aliases = array_map('strtolower', (array) ($metadata['aliases'] ?? []));
                    if (strtolower($definition->name) === $term || in_array($term, $aliases, true)) $requestedTables[] = $definition->source_table;
                }
            }
            $requestedTables = array_values(array_unique($requestedTables));
        }
        if (empty($requestedTables)) {
            throw new \App\Exceptions\AI\TableResolutionException('Validation Failed: no table can be resolved from approved AI Metadata.', \App\Exceptions\AI\TableResolutionException::TYPE_NOT_FOUND);
        }

        $resolvedTables = [];
        foreach ($requestedTables as $table) {
            $query = DB::table('sync_metadata_definitions')
                ->where('connection_id', $connection->id)
                ->where('chatedp_enabled', true);
                
            if ($domainContext) {
                $query->where(function ($q) use ($domainContext) {
                    $q->whereNull('business_domains')
                      ->orWhere('business_domains', '[]')
                      ->orWhere('business_domains', 'null')
                      ->orWhereJsonContains('business_domains', $domainContext);
                });
            }
            
            $allMetadata = $query->get();
            $candidates = [];
            
            foreach ($allMetadata as $metadata) {
                if (strtolower($metadata->entity_key) === strtolower($table)) {
                    $candidates[] = $metadata->entity_key;
                    continue;
                }
                if ($metadata->logical_name && strtolower($metadata->logical_name) === strtolower($table)) {
                    $candidates[] = $metadata->entity_key;
                    continue;
                }
                $matchedAlias = false;
                if ($metadata->aliases) {
                    $aliases = json_decode($metadata->aliases, true) ?? [];
                    foreach ($aliases as $alias) {
                        if (strtolower(trim($alias)) === strtolower($table)) {
                            $candidates[] = $metadata->entity_key;
                            $matchedAlias = true;
                            break;
                        }
                    }
                }
                if ($matchedAlias) continue;
                
                // Partial discovery
                $search = strtolower(trim(str_replace('%', '', $table)));
                if (!empty($search)) {
                    if (str_contains(strtolower($metadata->entity_key), $search) || 
                        ($metadata->logical_name && str_contains(strtolower($metadata->logical_name), $search))) {
                        $candidates[] = $metadata->entity_key;
                        continue;
                    }
                    if ($metadata->aliases) {
                        $aliases = json_decode($metadata->aliases, true) ?? [];
                        foreach ($aliases as $alias) {
                            if (str_contains(strtolower($alias), $search)) {
                                $candidates[] = $metadata->entity_key;
                                break;
                            }
                        }
                    }
                }
            }
            
            $candidates = array_unique($candidates);
            
            if (count($candidates) === 0) {
                throw new \App\Exceptions\AI\TableResolutionException("Validation Failed: Table '{$table}' is not recognized in the analytics schema cache.", \App\Exceptions\AI\TableResolutionException::TYPE_NOT_FOUND);
            }
            if (count($candidates) > 1) {
                throw new \App\Exceptions\AI\TableResolutionException("Several tables were found for '{$table}'. Please specify which data you want to analyze.", \App\Exceptions\AI\TableResolutionException::TYPE_AMBIGUOUS);
            }
            $resolvedTables[] = $candidates[0];
        }

        // 2. Resolve Dimensions
        $resolvedDimensions = [];
        foreach ($plan->dimensions as $dimension) {
            $result = $this->semanticResolver->resolve($dimension, $connection, $resolvedTables, 'dimension');
            
            if ($result['matched']) {
                $resolvedDimensions[] = $result['physical_mapping'];
            } elseif ($result['ambiguous']) {
                throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous dimension '{$dimension}'. " . $result['message']);
            } else {
                // Smart Fallback to learning
                $learnedTerm = $this->learningService->learnAndReturnMatch($connection, $dimension, 'dimension', $domainContext);
                if ($learnedTerm) {
                    $retryResult = $this->semanticResolver->resolve($learnedTerm, $connection, $resolvedTables, 'dimension');
                    if ($retryResult['matched']) {
                        $resolvedDimensions[] = $retryResult['physical_mapping'];
                        continue;
                    }
                }
                throw new \App\Exceptions\AI\SemanticNotFoundException("Validation Failed: Dimension '{$dimension}' is not recognized in the Semantic Layer for the selected tables.", $dimension, 'dimension');
            }
        }

        // Detail dimensions are resolved with the same semantic allowlist, but
        // remain outside GROUP BY so they cannot alter a ranking's grain.
        $resolvedDetailDimensions = [];
        foreach ($plan->detail_dimensions as $dimension) {
            $result = $this->semanticResolver->resolve($dimension, $connection, $resolvedTables, 'dimension');
            if ($result['matched']) {
                $resolvedDetailDimensions[] = $result['physical_mapping'];
            } elseif ($result['ambiguous']) {
                throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous detail dimension '{$dimension}'. " . $result['message']);
            } else {
                throw new \App\Exceptions\AI\SemanticNotFoundException("Validation Failed: Detail dimension '{$dimension}' is not recognized.", $dimension, 'dimension');
            }
        }

        // 3. Resolve Filters
        $resolvedFilters = [];
        foreach ($plan->filters as $filter) {
            if (isset($filter['field'])) {
                $field = $filter['field'];
                $fieldLower = strtolower(trim($field));
                
                // Special handling for 'date' generic filter
                if ($fieldLower === 'date' || $fieldLower === 'tanggal') {
                    $dateResult = $this->semanticResolver->resolveDateField($connection, $resolvedTables);
                    if ($dateResult['matched']) {
                        $filter['field'] = $dateResult['physical_mapping'];
                        $resolvedFilters[] = $filter;
                        continue;
                    }
                }

                $result = $this->semanticResolver->resolve($field, $connection, $resolvedTables, 'dimension');
                if ($result['matched']) {
                    $filter['field'] = $result['physical_mapping'];
                } elseif ($result['ambiguous']) {
                    throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous filter field '{$field}'. " . $result['message']);
                } else {
                    $learnedTerm = $this->learningService->learnAndReturnMatch($connection, $field, 'dimension', $domainContext);
                    if ($learnedTerm) {
                        $retryResult = $this->semanticResolver->resolve($learnedTerm, $connection, $resolvedTables, 'dimension');
                        if ($retryResult['matched']) {
                            $filter['field'] = $retryResult['physical_mapping'];
                            $resolvedFilters[] = $filter;
                            continue;
                        }
                    }
                    throw new \App\Exceptions\AI\SemanticNotFoundException("Validation Failed: Filter field '{$field}' is not recognized in the Semantic Layer for the selected tables.", $field, 'dimension');
                }
            }
            $resolvedFilters[] = $filter;
        }

        // 4. Resolve Metrics
        $resolvedMetrics = [];
        $intent = $plan->intent;
        
        if (empty($plan->metrics)) {
            if ($plan->intent === 'sales_analysis') {
                $result = $this->semanticResolver->resolveDefaultMetric($connection, $resolvedTables);
                if ($result['matched']) {
                    $resolvedMetrics[] = [
                        'name' => $result['physical_mapping'],
                        'is_expression' => $result['is_expression'],
                        'aggregation' => $result['aggregation'],
                        'alias' => preg_replace('/[^a-zA-Z0-9_]/', '_', $result['logical_term'])
                    ];
                } elseif ($result['ambiguous']) {
                    throw new \App\Exceptions\AI\SemanticAmbiguityException($result['message']);
                } else {
                    $intent = 'data_unavailable';
                }
            } elseif ($plan->intent === 'transaction_count') {
                $resolvedMetrics[] = [
                    'name' => '*',
                    'is_expression' => true,
                    'aggregation' => 'count',
                    'alias' => 'total_transactions'
                ];
            } else {
                // For unrecognized generic intents without explicit metrics
                $resolvedMetrics[] = [
                    'name' => '*',
                    'is_expression' => true,
                    'aggregation' => 'count',
                    'alias' => 'total_records'
                ];
            }
        } elseif (!empty($plan->metrics)) {
            foreach ($plan->metrics as $metricDef) {
                $metricName = $metricDef['name'];
                $result = $this->semanticResolver->resolve($metricName, $connection, $resolvedTables, 'metric');
                if ($result['matched']) {
                    $metricDef['name'] = $result['physical_mapping'];
                    $metricDef['is_expression'] = $result['is_expression'];
                    $metricDef['aggregation'] = $metricDef['aggregation'] ?? $result['aggregation'];
                    $metricDef['alias'] = preg_replace('/[^a-zA-Z0-9_]/', '_', $result['logical_term']);
                    $resolvedMetrics[] = $metricDef;
                } elseif ($result['ambiguous']) {
                    throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous metric '{$metricName}'. " . $result['message']);
                } else {
                    $learnedTerm = $this->learningService->learnAndReturnMatch($connection, $metricName, 'metric', $domainContext);
                    if ($learnedTerm) {
                        $retryResult = $this->semanticResolver->resolve($learnedTerm, $connection, $resolvedTables, 'metric');
                        if ($retryResult['matched']) {
                            $metricDef['name'] = $retryResult['physical_mapping'];
                            $metricDef['is_expression'] = $retryResult['is_expression'];
                            $metricDef['aggregation'] = $metricDef['aggregation'] ?? $retryResult['aggregation'];
                            $metricDef['alias'] = preg_replace('/[^a-zA-Z0-9_]/', '_', $retryResult['logical_term']);
                            $resolvedMetrics[] = $metricDef;
                        } else {
                            throw new \App\Exceptions\AI\SemanticNotFoundException("Validation Failed: Metric '{$metricName}' is not recognized.", $metricName, 'metric');
                        }
                    } else {
                        throw new \App\Exceptions\AI\SemanticNotFoundException("Validation Failed: Metric '{$metricName}' is not recognized.", $metricName, 'metric');
                    }
                }
            }
        }

        // 5. Resolve Sorts
        $resolvedSorts = [];
        if (!empty($plan->sorts)) {
            foreach ($plan->sorts as $sort) {
                $sortKey = $sort['field'] ?? $sort['metric'] ?? $sort['column'] ?? null;
                if (!$sortKey) continue;
                
                $cleanSortKey = preg_replace('/_(SUM|COUNT|AVG|MIN|MAX)$/i', '', $sortKey);

                $result = $this->semanticResolver->resolve($cleanSortKey, $connection, $resolvedTables, 'dimension');
                if (!$result['matched']) {
                    // Try metric resolution for sorting
                    $result = $this->semanticResolver->resolve($cleanSortKey, $connection, $resolvedTables, 'metric');
                }

                if ($result['matched']) {
                    if ($result['semantic_type'] === 'metric') {
                        $agg = $result['aggregation'] ?: 'SUM';
                        $alias = preg_replace('/[^a-zA-Z0-9_]/', '_', $result['logical_term']);
                        $sort['field'] = $alias . '_' . $agg;
                    } else {
                        $sort['field'] = $result['physical_mapping'];
                    }
                } elseif ($result['ambiguous']) {
                    throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous sort field '{$sortKey}'. " . $result['message']);
                } else {
                    $learnedTerm = $this->learningService->learnAndReturnMatch($connection, $cleanSortKey, 'metric', $domainContext);
                    if ($learnedTerm) {
                        $retryResult = $this->semanticResolver->resolve($learnedTerm, $connection, $resolvedTables, 'metric');
                        if ($retryResult['matched']) {
                            $agg = $retryResult['aggregation'] ?: 'SUM';
                            $alias = preg_replace('/[^a-zA-Z0-9_]/', '_', $retryResult['logical_term']);
                            $sort['field'] = $alias . '_' . $agg;
                        } else {
                            throw new \App\Exceptions\AI\SemanticNotFoundException("Validation Failed: Sort field '{$sortKey}' is not recognized.", $cleanSortKey, 'metric');
                        }
                    } else {
                        throw new \App\Exceptions\AI\SemanticNotFoundException("Validation Failed: Sort field '{$sortKey}' is not recognized.", $cleanSortKey, 'metric');
                    }
                }
                $resolvedSorts[] = $sort;
            }
        }

        $relationships = $this->relationshipValidator->validate($connection, $resolvedTables);

        $validatedPlan = new QueryPlan(
            intent: $intent,
            metrics: $resolvedMetrics,
            dimensions: $resolvedDimensions,
            detail_dimensions: $resolvedDetailDimensions,
            filters: $resolvedFilters,
            sorts: $resolvedSorts,
            limit: $plan->limit,
            comparison: $plan->comparison,
            date_context: $plan->date_context,
            domain: $plan->domain,
            relationships: $relationships,
            analysis: $plan->analysis,
            columns: $plan->columns,
            tables: $resolvedTables,
            internal_metadata: [
                'logical_dimensions' => $plan->dimensions,
                'logical_detail_dimensions' => $plan->detail_dimensions,
                'ranking' => strtolower($plan->intent) === 'ranking' ? [
                    'primary_label' => $plan->dimensions[0] ?? null,
                    'detail_label' => $plan->detail_dimensions[0] ?? null,
                    'metric_label' => $plan->metrics[0]['name'] ?? null,
                ] : null,
            ]
        );

        return $validatedPlan;
    }

    /** Legacy alias retained for callers created before validate() was renamed. */
    public function validateAndResolve(QueryPlan $plan, Connection $connection, ?string $domainContext = null): QueryPlan
    {
        return $this->validate($plan, $connection, $domainContext);
    }

    private function validateTableComparison(QueryPlan $plan, Connection $connection, ?string $domainContext): QueryPlan
    {
        $comparison = $plan->comparison ?? [];
        $left = (array) ($comparison['left'] ?? []);
        $right = (array) ($comparison['right'] ?? []);
        if (empty($left['table']) || empty($right['table']) || empty($left['metric']) || empty($right['metric'])) {
            throw new \App\Exceptions\AI\SemanticAmbiguityException('Perbandingan tabel memerlukan tabel dan metrik untuk kedua sisi.');
        }

        $sidePlan = function (array $side) use ($plan): QueryPlan {
            $metric = is_array($side['metric']) ? $side['metric'] : ['name' => $side['metric'], 'aggregation' => 'sum'];
            return new QueryPlan(
                intent: $plan->intent,
                metrics: [$metric],
                filters: $plan->filters,
                date_context: $plan->date_context,
                domain: $plan->domain,
                tables: [(string) $side['table']],
                analysis: $plan->analysis
            );
        };

        // Reuse the normal single-table validator: enabled table/domain/field rules
        // remain authoritative for each independent aggregate.
        $leftPlan = $this->validate($sidePlan($left), $connection, $domainContext);
        $rightPlan = $this->validate($sidePlan($right), $connection, $domainContext);
        $relationships = $this->relationshipValidator->validate($connection, [$leftPlan->tables[0], $rightPlan->tables[0]]);

        return new QueryPlan(
            intent: $plan->intent,
            filters: $plan->filters,
            limit: min($plan->limit ?? 1, 1),
            comparison: [
                'type' => 'table',
                'left' => ['table' => $leftPlan->tables[0], 'metric' => $leftPlan->metrics[0]],
                'right' => ['table' => $rightPlan->tables[0], 'metric' => $rightPlan->metrics[0]],
                'relationship' => $relationships[0] ?? null,
                'output' => $comparison['output'] ?? ['gap', 'percentage_difference', 'ratio'],
            ],
            date_context: $plan->date_context,
            domain: $plan->domain,
            relationships: $relationships,
            analysis: $plan->analysis + ['mode' => 'table_comparison'],
            tables: [$leftPlan->tables[0], $rightPlan->tables[0]],
            internal_metadata: ['table_comparison' => ['left_plan' => $leftPlan->toArray(), 'right_plan' => $rightPlan->toArray()]]
        );
    }
}
