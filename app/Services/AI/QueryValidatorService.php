<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;
use App\Models\Connection;
use Illuminate\Support\Facades\DB;

class QueryValidatorService
{
    public function __construct(
        protected SemanticResolutionService $semanticResolver,
        protected SemanticLearningService $learningService
    ) {}

    public function validate(QueryPlan $plan, Connection $connection, ?string $domainContext = null): QueryPlan
    {
        if (!$connection->is_analytics_source) {
            throw new \Exception("Unauthorized: The selected connection is not the designated analytics source.");
        }

        // 1. Resolve Tables
        $resolvedTables = [];
        foreach ($plan->tables as $table) {
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

        $validatedPlan = new QueryPlan(
            intent: $intent,
            metrics: $resolvedMetrics,
            dimensions: $resolvedDimensions,
            filters: $resolvedFilters,
            sorts: $resolvedSorts,
            limit: $plan->limit,
            comparison: $plan->comparison,
            date_context: $plan->date_context,
            tables: $resolvedTables,
            internal_metadata: [
                'logical_dimensions' => $plan->dimensions
            ]
        );

        return $validatedPlan;
    }
}
