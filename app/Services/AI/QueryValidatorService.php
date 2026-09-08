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
        
        // Fetch all semantic definitions for the tables
        $semanticDefinitions = \App\Models\AiSemanticDefinition::where('connection_id', $connection->id)
            ->whereIn('source_table', $resolvedTables)
            ->get();
            
        $semanticDimensions = $semanticDefinitions->where('type', 'dimension');

        // Resolve Semantic Dimensions
        $resolvedDimensions = [];
        $semanticResolver = app(\App\Services\Metadata\SemanticResolutionService::class);
        $physicalTable = $resolvedTables[0] ?? null;
        // Attempt to find the SyncEntity for semantic mapping
        $syncEntity = $physicalTable ? \App\Models\SyncEntity::where('destination_table', $physicalTable)->first() : null;
        $entityContext = $syncEntity ? $syncEntity->code : $physicalTable;

        foreach ($plan->dimensions as $dimension) {
            $dimLower = strtolower(trim($dimension));
            $matched = false;

            // 1. Check AiSemanticDefinition
            foreach ($semanticDimensions as $sm) {
                $metadata = is_string($sm->metadata) ? json_decode($sm->metadata, true) : ($sm->metadata ?? []);
                $aliases = array_map('trim', array_map('strtolower', $metadata['aliases'] ?? []));
                if (strtolower(trim($sm->name)) === $dimLower || in_array($dimLower, $aliases)) {
                    $resolvedDimensions[] = $sm->source_column;
                    $matched = true;
                    break;
                }
            }
            if ($matched) continue;

            // 2. Check Physical Columns
            $physicalField = DB::table('sync_metadata_fields')
                ->join('sync_metadata_definitions', 'sync_metadata_definitions.id', '=', 'sync_metadata_fields.definition_id')
                ->where('sync_metadata_definitions.connection_id', $connection->id)
                ->whereIn('sync_metadata_definitions.entity_key', $resolvedTables)
                ->where('sync_metadata_fields.field_name', $dimension)
                ->first();
            if ($physicalField) {
                $resolvedDimensions[] = $physicalField->field_name;
                continue;
            }

            // 3. Fallback to SemanticResolutionService
            $result = $semanticResolver->resolveTerm($dimension, $entityContext);
            if ($result['matched']) {
                $resolvedDimensions[] = $result['destination_field'];
                if ($physicalTable) {
                    $this->autoLearnSemantic($connection->id, $physicalTable, 'dimension', $dimension, $result['destination_field']);
                }
            } elseif ($result['ambiguous']) {
                throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous dimension '{$dimension}'. " . $result['message']);
            } else {
                // 4. LLM Smart Fallback
                $llmMatch = $this->smartLlmFallback($connection->id, $resolvedTables, $dimension, 'dimension');
                if ($llmMatch) {
                    $resolvedDimensions[] = $llmMatch;
                    if ($physicalTable) {
                        $this->autoLearnSemantic($connection->id, $physicalTable, 'dimension', $dimension, $llmMatch);
                    }
                } else {
                    throw new \App\Exceptions\AI\SemanticNotFoundException("Validation Failed: Dimension '{$dimension}' is not recognized in the Semantic Layer for the selected tables.", $dimension, 'dimension');
                }
            }
        }

        $resolvedFilters = [];
        foreach ($plan->filters as $filter) {
            if (isset($filter['field'])) {
                $field = $filter['field'];
                $fieldLower = strtolower(trim($field));
                $matched = false;

                // 1. Check AiSemanticDefinition
                foreach ($semanticDimensions as $sm) {
                    $metadata = is_string($sm->metadata) ? json_decode($sm->metadata, true) : ($sm->metadata ?? []);
                    $aliases = array_map('trim', array_map('strtolower', $metadata['aliases'] ?? []));
                    if (strtolower(trim($sm->name)) === $fieldLower || in_array($fieldLower, $aliases)) {
                        $filter['field'] = $sm->source_column;
                        $matched = true;
                        break;
                    }
                }

                // 1.5. Special handling for 'date'
                if (!$matched && $fieldLower === 'date') {
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
                        ->orderBy('sync_metadata_fields.is_primary_key', 'desc')
                        ->first();
                        
                    if ($dateField) {
                        $filter['field'] = $dateField->field_name;
                        $matched = true;
                    }
                }

                if (!$matched) {
                    // 2. Check Physical Columns
                    $physicalField = DB::table('sync_metadata_fields')
                        ->join('sync_metadata_definitions', 'sync_metadata_definitions.id', '=', 'sync_metadata_fields.definition_id')
                        ->where('sync_metadata_definitions.connection_id', $connection->id)
                        ->whereIn('sync_metadata_definitions.entity_key', $resolvedTables)
                        ->where('sync_metadata_fields.field_name', $field)
                        ->first();
                    if ($physicalField) {
                        $filter['field'] = $physicalField->field_name;
                        $matched = true;
                    }
                }

                if (!$matched) {
                    // 3. Fallback to SemanticResolutionService
                    $result = $semanticResolver->resolveTerm($field, $entityContext);
                    if ($result['matched']) {
                        $filter['field'] = $result['destination_field'];
                        if ($physicalTable) {
                            $this->autoLearnSemantic($connection->id, $physicalTable, 'dimension', $field, $result['destination_field']);
                        }
                    } elseif ($result['ambiguous']) {
                        throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous filter field '{$field}'. " . $result['message']);
                    } else {
                        // 4. LLM Smart Fallback
                        $llmMatch = $this->smartLlmFallback($connection->id, $resolvedTables, $field, 'dimension');
                        if ($llmMatch) {
                            $filter['field'] = $llmMatch;
                            if ($physicalTable) {
                                $this->autoLearnSemantic($connection->id, $physicalTable, 'dimension', $field, $llmMatch);
                            }
                        } else {
                            throw new \App\Exceptions\AI\SemanticNotFoundException("Validation Failed: Filter field '{$field}' is not recognized in the Semantic Layer for the selected tables.", $field, 'dimension');
                        }
                    }
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
                        if ($physicalTable) {
                            $this->autoLearnSemantic($connection->id, $physicalTable, 'metric', $term, $result['destination_field']);
                        }
                    } elseif ($result['ambiguous']) {
                        throw new \App\Exceptions\AI\SemanticAmbiguityException("Ambiguous metric '{$term}'. " . $result['message']);
                    } else {
                        // 4. LLM Smart Fallback
                        $llmMatch = $this->smartLlmFallback($connection->id, $resolvedTables, $term);
                        if ($llmMatch) {
                            $chosenMetric = (object) [
                                'name' => $term,
                                'source_column' => $llmMatch,
                                'expression' => null,
                                'metadata' => ['aggregation' => 'SUM']
                            ];
                            if ($physicalTable) {
                                $this->autoLearnSemantic($connection->id, $physicalTable, 'metric', $term, $llmMatch);
                            }
                        }
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
                $name = $chosenMetric->source_column;
                $isExpr = false;
                if (!empty($chosenMetric->expression) && $chosenMetric->expression !== $chosenMetric->source_column) {
                    $expr = trim($chosenMetric->expression);
                    // MySQL compatibility patch for SQLServer/OData expressions
                    $expr = str_ireplace('AS NUMERIC', 'AS DECIMAL(18,4)', $expr);
                    
                    if ($agg && preg_match('/^' . preg_quote($agg, '/') . '\s*\((.*)\)$/i', $expr, $matches)) {
                        $name = $matches[1];
                        $isExpr = !preg_match('/^[a-zA-Z0-9_]+$/', $name);
                    } else {
                        $name = $expr;
                        $isExpr = true;
                    }
                }

                $resolvedMetrics[] = [
                    'name' => $name,
                    'is_expression' => $isExpr,
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
                    $metric['aggregation'] = strtoupper($metadata['aggregation'] ?? '');
                    if (empty($metric['aggregation']) && empty($semanticMetric->expression)) {
                        $metric['aggregation'] = 'SUM';
                    }
                    
                    $name = $semanticMetric->source_column;
                    $isExpr = false;
                    if (!empty($semanticMetric->expression) && $semanticMetric->expression !== $semanticMetric->source_column) {
                        $expr = trim($semanticMetric->expression);
                        if ($metric['aggregation'] && preg_match('/^' . preg_quote($metric['aggregation'], '/') . '\s*\((.*)\)$/i', $expr, $matches)) {
                            $name = $matches[1];
                            $isExpr = !preg_match('/^[a-zA-Z0-9_]+$/', $name);
                        } else {
                            $name = $expr;
                            $isExpr = true;
                        }
                    }

                    $metric['name'] = $name;
                    $metric['is_expression'] = $isExpr;
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
                    $llmMatch = $this->smartLlmFallback($connection->id, $resolvedTables, $metricName, 'metric');
                    if ($llmMatch) {
                        $metric['name'] = $llmMatch;
                        $metric['is_expression'] = false;
                    } else {
                        throw new \App\Exceptions\AI\SemanticNotFoundException("Validation Failed: Metric '{$metricName}' is not recognized in the Semantic Layer for the selected tables.", $metricName, 'metric');
                    }
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

                // Remove suffix like _SUM if LLM hallucinated it
                $cleanSortKey = preg_replace('/_(SUM|COUNT|AVG|MIN|MAX)$/i', '', $sortKey);

                // Check with SemanticResolutionService first
                $result = $semanticResolver->resolveTerm($cleanSortKey, $entityContext);
                
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
                    $termLower = strtolower(trim($cleanSortKey));
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
                        $alias = preg_replace('/[^a-zA-Z0-9_]/', '_', $matchedMetric->name);
                        $suffix = empty($agg) ? "" : "_{$agg}";
                        // QueryCompilerService aliases aggregated metrics as {$alias}{$suffix}
                        $sort['field'] = $alias . $suffix;
                    } else {
                        // Smart LLM Fallback
                        $llmMatch = $this->smartLlmFallback($connection->id, $resolvedTables, $cleanSortKey, 'metric');
                        if ($llmMatch) {
                            $sort['field'] = $llmMatch;
                            if ($physicalTable) {
                                $this->autoLearnSemantic($connection->id, $physicalTable, 'metric', $cleanSortKey, $llmMatch);
                            }
                        } else {
                            throw new \App\Exceptions\AI\SemanticNotFoundException("Validation Failed: Sort field '{$sortKey}' is not recognized in the Semantic Layer for the selected tables.", $cleanSortKey, 'metric');
                        }
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

    private function smartLlmFallback($connectionId, $resolvedTables, $term, $type = 'dimension')
    {
        try {
            $learningService = app(\App\Services\AI\SemanticLearningService::class);
            $connection = \App\Models\Connection::find($connectionId);
            if (!$connection) {
                return null;
            }
            
            $tableContext = !empty($resolvedTables) ? $resolvedTables[0] : null;
            
            $matchedColumn = $learningService->learnAndReturnMatch($connection, $term, $type, $tableContext);
            return $matchedColumn;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Smart LLM Fallback Error: " . $e->getMessage());
            return null;
        }
    }

    private function autoLearnSemantic($connectionId, $physicalTable, $type, $source, $destination)
    {
        // Auto learning is now handled synchronously inside SemanticLearningService::learnAndReturnMatch.
        // Therefore, we don't need to do anything here to prevent duplicate entries.
    }
}
