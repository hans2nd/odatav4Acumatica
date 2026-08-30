<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;
use App\Models\Connection;
use Illuminate\Support\Facades\DB;

class QueryValidatorService
{
    public function validate(QueryPlan $plan, Connection $connection, ?string $domainContext = null): QueryPlan
    {
        if (!$connection->is_analytics_source) {
            throw new \Exception("Unauthorized: The selected connection is not the designated analytics source.");
        }

        $resolvedTables = [];

        // Validate Tables exist in metadata cache for this connection
        foreach ($plan->tables as $table) {
            // Table Discovery Logic
            
            // Fetch all metadata for this connection
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
                // 1. Exact physical table name
                if (strtolower($metadata->entity_key) === strtolower($table)) {
                    $candidates[] = $metadata->entity_key;
                    continue;
                }
                
                // 2. Exact logical name
                if ($metadata->logical_name && strtolower($metadata->logical_name) === strtolower($table)) {
                    $candidates[] = $metadata->entity_key;
                    continue;
                }
                
                // 3. Exact alias
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
                if ($matchedAlias) {
                    continue;
                }
                
                // 4. Partial candidate discovery
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
        
        // Resolve Semantic Dimensions
        $resolvedDimensions = [];
        $semanticResolver = app(\App\Services\Metadata\SemanticResolutionService::class);
        $physicalTable = $resolvedTables[0] ?? null;
        // Attempt to find the SyncEntity for semantic mapping
        $syncEntity = $physicalTable ? \App\Models\SyncEntity::where('destination_table', $physicalTable)->first() : null;
        $entityContext = $syncEntity ? $syncEntity->code : $physicalTable;

        foreach ($plan->dimensions as $dimension) {
            $result = $semanticResolver->resolveTerm($dimension, $entityContext);
            
            if ($result['matched']) {
                $resolvedDimensions[] = $result['destination_field'];
            } elseif ($result['ambiguous']) {
                throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous dimension '{$dimension}'. " . $result['message']);
            } else {
                throw new \Exception("Validation Failed: Dimension '{$dimension}' is not recognized in the Semantic Layer for the selected tables.");
            }
        }

        $resolvedFilters = [];
        foreach ($plan->filters as $filter) {
            if (isset($filter['field'])) {
                $field = $filter['field'];
                $result = $semanticResolver->resolveTerm($field, $entityContext);

                if ($result['matched']) {
                    $filter['field'] = $result['destination_field'];
                } elseif ($result['ambiguous']) {
                    throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous filter field '{$field}'. " . $result['message']);
                } else {
                    throw new \Exception("Validation Failed: Filter field '{$field}' is not recognized in the Semantic Layer for the selected tables.");
                }
            }
            $resolvedFilters[] = $filter;
        }

        // Semantic Metric Resolution
        $resolvedMetrics = [];
        $intent = $plan->intent;

        if ($plan->intent === 'sales_analysis') {
            $semanticMetrics = \App\Models\AiSemanticDefinition::where('connection_id', $connection->id)
                ->where('type', 'metric')
                ->whereIn('source_table', $resolvedTables)
                ->get();
                
            $chosenMetric = null;

            if ($plan->metric_term) {
                // 1. Explicit metric_term
                $term = $plan->metric_term;
                $termLower = strtolower(trim($term));
                
                // Priority 1: AiSemanticDefinition (Configured Business Metric)
                foreach ($semanticMetrics as $metric) {
                    $metadata = is_string($metric->metadata) ? json_decode($metric->metadata, true) : ($metric->metadata ?? []);
                    $aliases = array_map('trim', array_map('strtolower', $metadata['aliases'] ?? []));
                    $nameLower = strtolower(trim($metric->name));
                    
                    if ($nameLower === $termLower || in_array($termLower, $aliases)) {
                        $chosenMetric = $metric;
                        break;
                    }
                }

                // Priority 2: SemanticResolutionService (Field Level)
                if (!$chosenMetric) {
                    $result = $semanticResolver->resolveTerm($term, $entityContext);

                    if ($result['matched']) {
                        $chosenMetric = (object) [
                            'name' => $term,
                            'source_column' => $result['destination_field'],
                            'expression' => null,
                            'metadata' => ['aggregation' => 'SUM']
                        ];
                    } elseif ($result['ambiguous']) {
                        throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous metric '{$term}'. " . $result['message']);
                    }
                }
            } else {
                // 2. Configured default metric
                foreach ($semanticMetrics as $metric) {
                    $metadata = is_string($metric->metadata) ? json_decode($metric->metadata, true) : ($metric->metadata ?? []);
                    if (!empty($metadata['is_default'])) {
                        $chosenMetric = $metric;
                        break;
                    }
                }
                
                // 3. Multiple metrics without default
                if (!$chosenMetric && $semanticMetrics->count() > 1) {
                    throw new \App\Exceptions\AI\SemanticAmbiguityException(
                        "Beberapa metrik penjualan ditemukan dan tidak ada metrik default yang dikonfigurasi. Mohon spesifikasikan metrik mana yang ingin Anda gunakan (contoh: Net, Gross)."
                    );
                } elseif (!$chosenMetric && $semanticMetrics->count() === 1) {
                    $chosenMetric = $semanticMetrics->first();
                }
            }
            
            if ($chosenMetric) {
                $metadata = is_string($chosenMetric->metadata) ? json_decode($chosenMetric->metadata, true) : ($chosenMetric->metadata ?? []);
                $agg = strtoupper($metadata['aggregation'] ?? '');
                if (empty($agg) && empty($chosenMetric->expression)) {
                    $agg = 'SUM'; // Fallback for simple columns
                }
                $resolvedMetrics[] = [
                    'name' => $chosenMetric->expression ?? $chosenMetric->source_column,
                    'is_expression' => !empty($chosenMetric->expression) && $chosenMetric->expression !== $chosenMetric->source_column,
                    'aggregation' => $agg,
                    'alias' => preg_replace('/[^a-zA-Z0-9_]/', '_', $chosenMetric->name ?? 'metric')
                ];
            } else {
                // 4. No valid metric
                $intent = 'data_unavailable';
            }
        } elseif (!empty($plan->metrics)) {
            // Fallback for non-sales_analysis if any
            // Fetch semantic metrics for the resolved tables once
            $semanticMetrics = \App\Models\AiSemanticDefinition::where('connection_id', $connection->id)
                ->where('type', 'metric')
                ->whereIn('source_table', $resolvedTables)
                ->get();

            foreach ($plan->metrics as $metric) {
                $metricName = $metric['name'];
                $metricNameLower = strtolower(trim($metricName));
                $semanticMetric = null;
                
                // Priority 1: AiSemanticDefinition
                foreach ($semanticMetrics as $sm) {
                    $metadata = is_string($sm->metadata) ? json_decode($sm->metadata, true) : ($sm->metadata ?? []);
                    $aliases = array_map('trim', array_map('strtolower', $metadata['aliases'] ?? []));
                    $nameLower = strtolower(trim($sm->name));
                    
                    if ($nameLower === $metricNameLower || in_array($metricNameLower, $aliases)) {
                        $semanticMetric = $sm;
                        break;
                    }
                }

                if ($semanticMetric && ($semanticMetric->expression || $semanticMetric->source_column)) {
                    $metric['name'] = $semanticMetric->expression ?? $semanticMetric->source_column;
                    $metric['is_expression'] = !empty($semanticMetric->expression) && $semanticMetric->expression !== $semanticMetric->source_column;
                    $metadata = is_string($semanticMetric->metadata) ? json_decode($semanticMetric->metadata, true) : ($semanticMetric->metadata ?? []);
                    $metric['aggregation'] = strtoupper($metadata['aggregation'] ?? '');
                    if (empty($metric['aggregation']) && empty($semanticMetric->expression)) {
                        $metric['aggregation'] = 'SUM';
                    }
                    $metric['alias'] = preg_replace('/[^a-zA-Z0-9_]/', '_', $semanticMetric->name);
                    $resolvedMetrics[] = $metric;
                    continue;
                }

                // Priority 2: SemanticResolutionService
                $result = $semanticResolver->resolveTerm($metricName, $entityContext);

                if ($result['matched']) {
                    $metric['name'] = $result['destination_field'];
                    $metric['is_expression'] = false;
                } elseif ($result['ambiguous']) {
                    throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous metric '{$metricName}'. " . $result['message']);
                } else {
                    throw new \Exception("Validation Failed: Metric '{$metricName}' is not recognized in the Semantic Layer for the selected tables.");
                }
                $resolvedMetrics[] = $metric;
            }
        }

        $resolvedSorts = [];
        if (!empty($plan->sorts)) {
            foreach ($plan->sorts as $sort) {
                $sortKey = $sort['field'] ?? $sort['metric'] ?? $sort['column'] ?? null;
                if (!$sortKey) {
                    continue;
                }

                // Check with SemanticResolutionService first
                $result = $semanticResolver->resolveTerm($sortKey, $entityContext);
                
                if ($result['matched']) {
                    $sort['field'] = $result['destination_field'];
                } elseif ($result['ambiguous']) {
                    throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous sort field '{$sortKey}'. " . $result['message']);
                } else {
                    // Check if it's a known metric (fallback for calculated/business aggregation rules)
                        // Allow matching by name or aliases
                        $semanticMetrics = \App\Models\AiSemanticDefinition::where('connection_id', $connection->id)
                            ->where('type', 'metric')
                            ->whereIn('source_table', $resolvedTables)
                            ->get();
                            
                        $matchedMetric = null;
                        $termLower = strtolower(trim($sortKey));
                        foreach ($semanticMetrics as $m) {
                            $meta = is_string($m->metadata) ? json_decode($m->metadata, true) : ($m->metadata ?? []);
                            $aliases = array_map('trim', array_map('strtolower', $meta['aliases'] ?? []));
                            if (strtolower(trim($m->name)) === $termLower || in_array($termLower, $aliases)) {
                                $matchedMetric = $m;
                                break;
                            }
                        }

                        if ($matchedMetric && $matchedMetric->source_column) {
                            $meta = is_string($matchedMetric->metadata) ? json_decode($matchedMetric->metadata, true) : ($matchedMetric->metadata ?? []);
                            $agg = strtoupper($meta['aggregation'] ?? 'SUM');
                            // QueryCompilerService aliases aggregated metrics as {$col}_{$agg}
                            $sort['field'] = $matchedMetric->source_column . '_' . $agg;
                        } else {
                            // Fallback, assume it's already a physical column
                            $sort['field'] = $sortKey;
                        }
                }
                $resolvedSorts[] = $sort;
            }
        }

        $validatedPlan = new QueryPlan(
            $intent,
            $resolvedTables,
            $plan->columns,
            $resolvedMetrics,
            $resolvedDimensions,
            $resolvedFilters,
            $resolvedSorts,
            $plan->limit,
            $plan->comparison
        );

        // Columns could be validated by looking at sync_metadata_fields.
        // For P1, we ensure the structure rejects invalid references.
        $allFieldsToCheck = array_merge($validatedPlan->columns, $validatedPlan->dimensions);
        foreach ($validatedPlan->filters as $filter) {
            if (isset($filter['field'])) {
                $allFieldsToCheck[] = $filter['field'];
            }
        }

        foreach (array_unique($allFieldsToCheck) as $column) {
            // Simplified check assuming column format "table.column" or just "column"
            $parts = explode('.', $column);
            $colName = count($parts) > 1 ? $parts[1] : $parts[0];
            
            // Make sure column exists in any of the resolved tables
            $exists = DB::table('sync_metadata_fields')
                ->join('sync_metadata_definitions', 'sync_metadata_definitions.id', '=', 'sync_metadata_fields.definition_id')
                ->where('sync_metadata_definitions.connection_id', $connection->id)
                ->whereIn('sync_metadata_definitions.entity_key', $resolvedTables)
                ->where('sync_metadata_fields.field_name', $colName)
                ->exists();
                
            if (!$exists) {
                // If it fails, maybe the LLM outputted a logical name that didn't have a semantic definition map.
                // We shouldn't crash if it's just a filter, but for columns we should.
                throw new \Exception("Validation Failed: Column '{$column}' is not recognized in the analytics schema cache. Ensure it is mapped in the Semantic Layer.");
            }
        }
        
        return $validatedPlan;
    }
}
