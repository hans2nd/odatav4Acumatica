<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use App\Models\Connection;

class QueryCompilerService
{
    /**
     * Compiles a QueryPlan DTO into parameterized SQL string and bindings array
     * without executing it directly, ensuring no LLM-generated SQL exists.
     *
     * @param QueryPlan $plan
     * @param Connection $connection
     * @return array [0 => string $sql, 1 => array $bindings]
     */
    public function compile(QueryPlan $plan, Connection $connection): array
    {
        if (empty($plan->tables)) {
            throw new \Exception("QueryPlan must have at least one table.");
        }
        // A multi-table plan must be handled by a dedicated comparison executor.
        // Never silently compile only the first table, which could present a false comparison.
        if (count($plan->tables) > 1) {
            throw new \App\Exceptions\AI\SemanticAmbiguityException('Perbandingan lintas tabel memerlukan konfigurasi metrik per tabel pada AI Metadata dan belum dapat dieksekusi sebagai satu kueri.');
        }

        if (strtolower($plan->intent) === 'ranking' && count($plan->dimensions) === 1 && count($plan->detail_dimensions) >= 1 && count($plan->metrics) === 1 && empty($plan->comparison)) {
            return $this->compileRankedDetail($plan, $connection);
        }

        // We use the application's default DB connection to build the grammar.
        // It won't actually execute here.
        // We will pass the specific connection's grammar if it's available via config,
        // but for generating parameterized SQL, Laravel's default is usually safe
        // enough as long as standard SQL is used. 
        $query = DB::connection()->table($plan->tables[0]);

        $selects = [];
        $isComparison = !empty($plan->comparison);
        $dateField = null;
        $currentRange = [];
        $prevRange = [];
        $hasSingleDateContext = false;
        
        // 1. Identify date context for comparison
        if ($isComparison) {
            $compType = strtolower($plan->comparison['type'] ?? 'explicit_periods');
            
            if ($compType === 'explicit_periods' && !empty($plan->comparison['periods'])) {
                $periods = $plan->comparison['periods'];
                if (count($periods) === 2) {
                    $p1 = $periods[0];
                    $p2 = $periods[1];
                    $currentRange = is_array($p1) ? ['start' => $p1[0], 'end' => $p1[1]] : ['start' => $p1, 'end' => $p1];
                    $prevRange = is_array($p2) ? ['start' => $p2[0], 'end' => $p2[1]] : ['start' => $p2, 'end' => $p2];
                }
            }
            
            // Find which field in filters represents the date range
            foreach ($plan->filters as $filter) {
                if (!empty($currentRange) && isset($filter['value']) && is_array($filter['value'])) {
                    if (strtoupper($filter['operator']) === 'BETWEEN' && $filter['value'] === [$currentRange['start'], $currentRange['end']]) {
                        $dateField = $filter['field'];
                        break;
                    }
                }
                
                // Fallback: look for common date field keywords if we couldn't match the exact value
                if (!$dateField && (stripos($filter['field'], 'date') !== false || stripos($filter['field'], 'tanggal') !== false || stripos($filter['field'], 'waktu') !== false)) {
                    $dateField = $filter['field'];
                    if (empty($currentRange)) {
                        $op = strtoupper($filter['operator']);
                        if ($op === '=') {
                            $hasSingleDateContext = true;
                            $currentRange = ['start' => $filter['value'], 'end' => $filter['value']];
                        } elseif ($op === '>=' || $op === '>') {
                            $currentRange['start'] = $filter['value'];
                        } elseif ($op === '<=' || $op === '<') {
                            $currentRange['end'] = $filter['value'];
                        } elseif ($op === 'BETWEEN' && is_array($filter['value']) && count($filter['value']) == 2) {
                            $currentRange['start'] = $filter['value'][0];
                            $currentRange['end'] = $filter['value'][1];
                        }
                    }
                }
            }
            
            if (!$dateField) {
                // Fallback to table's default date field via SemanticResolutionService or Schema
                $semanticResolver = app(\App\Services\AI\SemanticResolutionService::class);
                $dateResult = $semanticResolver->resolveDateField($connection, $plan->tables);
                if ($dateResult['matched']) {
                    $dateField = $dateResult['physical_mapping'];
                }
            }
            
            if (!$dateField) {
                throw new \App\Exceptions\AI\SemanticAmbiguityException("Tidak dapat melakukan perbandingan karena rentang waktu atau tanggal acuan tidak ditentukan dalam konteks.");
            }
            
            if (empty($currentRange) || empty($prevRange)) {
                 throw new \App\Exceptions\AI\SemanticAmbiguityException("Tidak dapat melakukan perbandingan karena periode tidak terdefinisi secara eksplisit. Harap tentukan periode (misal: bulan ini, minggu lalu).");
            }

            foreach ([$currentRange, $prevRange] as $range) {
                foreach (['start', 'end', 'end_next'] as $boundary) {
                    if (isset($range[$boundary]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $range[$boundary])) {
                        throw new \App\Exceptions\AI\SemanticAmbiguityException('Rentang tanggal perbandingan tidak valid.');
                    }
                }
            }
            
            // Adjust ranges for datetime fields to ensure full day coverage
            if ($dateField) {
                $dateFieldMeta = \Illuminate\Support\Facades\DB::table('sync_metadata_fields')
                    ->join('sync_metadata_definitions', 'sync_metadata_definitions.id', '=', 'sync_metadata_fields.definition_id')
                    ->where('sync_metadata_definitions.connection_id', $connection->id)
                    ->whereIn('sync_metadata_definitions.entity_key', $plan->tables)
                    ->where('sync_metadata_fields.field_name', $dateField)
                    ->first();
                
                $semanticType = $dateFieldMeta ? strtolower($dateFieldMeta->semantic_type ?? '') : '';
                $dateDataType = $dateFieldMeta ? strtolower($dateFieldMeta->data_type ?? '') : '';
                
                $isDateTime = false;
                if (in_array($semanticType, ['date', 'datetime'])) {
                    $isDateTime = true;
                } elseif (empty($semanticType) && in_array($dateDataType, ['date', 'datetime', 'timestamp', 'datetime2', 'smalldatetime'])) {
                    $isDateTime = true;
                } elseif (stripos($dateField, 'date') !== false || stripos($dateField, 'tanggal') !== false || stripos($dateField, 'waktu') !== false) {
                    $isDateTime = true;
                }
                
                if ($isDateTime) {
                    if (isset($currentRange['end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $currentRange['end'])) {
                        $currentRange['end_next'] = (new \DateTime($currentRange['end']))->modify('+1 day')->format('Y-m-d');
                        unset($currentRange['end']);
                    }
                    if (isset($prevRange['end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $prevRange['end'])) {
                        $prevRange['end_next'] = (new \DateTime($prevRange['end']))->modify('+1 day')->format('Y-m-d');
                        unset($prevRange['end']);
                    }
                }
            }
        }

        // Add regular columns
        if (!empty($plan->columns)) {
            foreach ($plan->columns as $col) {
                $selects[] = $col;
            }
        }

        // Add metrics (aggregations)
        if (!empty($plan->metrics)) {
            foreach ($plan->metrics as $metric) {
                $agg = strtoupper($metric['aggregation']);
                $col = $metric['name'];
                $isExpr = !empty($metric['is_expression']);
                $alias = $metric['alias'] ?? preg_replace('/[^a-zA-Z0-9_]/', '_', $col);
                
                if (!in_array($agg, ['SUM', 'COUNT', 'AVG', 'MIN', 'MAX', '', 'DERIVED'])) {
                    throw new \Exception("Invalid aggregation function: {$agg}");
                }
                
                $innerCol = $isExpr ? ($metric['expression'] ?? $col) : "`{$col}`";
                $aggPrefix = empty($agg) ? "" : "{$agg}";
                
                // Handle canonical derived metrics compilation
                if ($agg === 'DERIVED' && !empty($metric['expression']) && !empty($metric['depends_on'])) {
                    // For ratio of aggregates, we wrap individual dependencies with SUM
                    // Example: "Margin / NULLIF(NetAmount, 0)" -> "SUM(Margin) / NULLIF(SUM(NetAmount), 0)"
                    $innerCol = $metric['expression'];
                    
                    // Sort dependencies by length desc to prevent partial replacements (e.g. replacing 'Net' inside 'NetAmount')
                    $deps = $metric['depends_on'];
                    usort($deps, fn($a, $b) => strlen($b) - strlen($a));
                    
                    foreach ($deps as $dep) {
                        // Use regex word boundaries to accurately replace identifiers
                        $innerCol = preg_replace('/\b' . preg_quote($dep, '/') . '\b/i', "SUM(`{$dep}`)", $innerCol);
                    }
                    $aggPrefix = ""; // No outer aggregation
                    $isExpr = true;
                }
                
                if ($isComparison) {
                    $startCondition = isset($currentRange['start']) ? "`{$dateField}` >= '{$currentRange['start']}'" : "1=1";
                    if (isset($currentRange['end_next'])) {
                        $endCondition = "`{$dateField}` < '{$currentRange['end_next']}'";
                    } else {
                        $endCondition = isset($currentRange['end']) ? "`{$dateField}` <= '{$currentRange['end']}'" : "1=1";
                    }
                    $currentExpr = empty($aggPrefix)
                        ? "(CASE WHEN {$startCondition} AND {$endCondition} THEN {$innerCol} ELSE 0 END)"
                        : "{$aggPrefix}(CASE WHEN {$startCondition} AND {$endCondition} THEN {$innerCol} ELSE 0 END)";
                    
                    $prevStartCondition = isset($prevRange['start']) ? "`{$dateField}` >= '{$prevRange['start']}'" : "1=1";
                    if (isset($prevRange['end_next'])) {
                        $prevEndCondition = "`{$dateField}` < '{$prevRange['end_next']}'";
                    } else {
                        $prevEndCondition = isset($prevRange['end']) ? "`{$dateField}` <= '{$prevRange['end']}'" : "1=1";
                    }
                    $prevExpr = empty($aggPrefix)
                        ? "(CASE WHEN {$prevStartCondition} AND {$prevEndCondition} THEN {$innerCol} ELSE 0 END)"
                        : "{$aggPrefix}(CASE WHEN {$prevStartCondition} AND {$prevEndCondition} THEN {$innerCol} ELSE 0 END)";
                    
                    $outputs = array_map('strtolower', $plan->comparison['output'] ?? ['value']);
                    
                    if (in_array('value', $outputs) || empty($outputs)) {
                        $selects[] = \Illuminate\Support\Facades\DB::raw("{$currentExpr} as {$alias}_current");
                        $selects[] = \Illuminate\Support\Facades\DB::raw("{$prevExpr} as {$alias}_previous");
                    }
                    
                    if (in_array('difference', $outputs) || in_array('selisih', $outputs)) {
                        $selects[] = \Illuminate\Support\Facades\DB::raw("({$currentExpr} - {$prevExpr}) as {$alias}_difference");
                    }
                    
                    if (in_array('percentage_change', $outputs) || in_array('growth', $outputs) || in_array('persentase', $outputs)) {
                        $selects[] = \Illuminate\Support\Facades\DB::raw("(CASE WHEN {$prevExpr} = 0 THEN NULL ELSE (({$currentExpr} - {$prevExpr}) / {$prevExpr}) * 100 END) as {$alias}_percentage_change");
                    }
                    
                    // Period specific row counts
                    $selects[] = \Illuminate\Support\Facades\DB::raw("COUNT(CASE WHEN {$startCondition} AND {$endCondition} THEN 1 END) as {$alias}_current_row_count");
                    $selects[] = \Illuminate\Support\Facades\DB::raw("COUNT(CASE WHEN {$prevStartCondition} AND {$prevEndCondition} THEN 1 END) as {$alias}_previous_row_count");
                    
                    // Period specific non-null metric counts
                    $selects[] = \Illuminate\Support\Facades\DB::raw("COUNT(CASE WHEN {$startCondition} AND {$endCondition} AND {$innerCol} IS NOT NULL THEN 1 END) as {$alias}_current_non_null_count");
                    $selects[] = \Illuminate\Support\Facades\DB::raw("COUNT(CASE WHEN {$prevStartCondition} AND {$prevEndCondition} AND {$innerCol} IS NOT NULL THEN 1 END) as {$alias}_previous_non_null_count");
                } else {
                    $expr = empty($aggPrefix) ? "({$innerCol})" : "{$aggPrefix}({$innerCol})";
                    $suffix = empty($aggPrefix) ? "" : "_{$aggPrefix}";
                    $selects[] = \Illuminate\Support\Facades\DB::raw("{$expr} as {$alias}{$suffix}");
                }
            }
        }

        if (empty($selects)) {
            $selects = ['*'];
        } else {
            if (!empty($plan->metrics)) {
                $selects[] = DB::raw("COUNT(*) as _total_rows");
            }
        }

        $query->select($selects);

        // Detail dimensions accompany a ranked/grouped entity; aggregating them
        // preserves the primary ranking grain instead of grouping Customer × Item.
        foreach ($plan->detail_dimensions as $detail) {
            $alias = 'detail_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $detail);
            $driver = strtolower((string) $connection->type);
            if (in_array($driver, ['sqlsrv', 'mssql'])) {
                $query->addSelect(DB::raw("STRING_AGG(CAST([{$detail}] AS nvarchar(max)), ', ') as [{$alias}]"));
            } else {
                $query->addSelect(DB::raw("GROUP_CONCAT(DISTINCT `{$detail}` ORDER BY `{$detail}` SEPARATOR ', ') as `{$alias}`"));
            }
        }

        // Add filters
        if (!empty($plan->filters)) {
            $query->where(function ($q) use ($plan, $isComparison, $dateField, $currentRange, $prevRange, $connection) {
                foreach ($plan->filters as $filter) {
                    $field = $filter['field'];
                    $operator = strtoupper($filter['operator']);
                    $value = $filter['value'];
                    
                    if (!in_array($operator, ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'IN', 'NOT IN', 'BETWEEN'])) {
                        throw new \Exception("Invalid operator: {$operator}");
                    }

                    // Skip the date field filters in the main where if it's comparison,
                    // we will add them as a grouped OR condition at the end.
                    if ($isComparison && $field === $dateField) {
                        continue;
                    }

                    // Fetch data type from schema to handle datetime
                    $fieldMeta = \Illuminate\Support\Facades\DB::table('sync_metadata_fields')
                        ->join('sync_metadata_definitions', 'sync_metadata_definitions.id', '=', 'sync_metadata_fields.definition_id')
                        ->where('sync_metadata_definitions.connection_id', $connection->id)
                        ->whereIn('sync_metadata_definitions.entity_key', $plan->tables)
                        ->where('sync_metadata_fields.field_name', $field)
                        ->first();
                        
                    $semanticType = $fieldMeta ? strtolower($fieldMeta->semantic_type ?? '') : '';
                    $dataType = $fieldMeta ? strtolower($fieldMeta->data_type ?? '') : '';
                    
                    $hasTimeComponent = false;
                    if (in_array($semanticType, ['date', 'datetime'])) {
                        $hasTimeComponent = true;
                    } elseif (empty($semanticType) && in_array($dataType, ['date', 'datetime', 'timestamp', 'datetime2', 'smalldatetime'])) {
                        $hasTimeComponent = true;
                    } elseif (stripos($field, 'date') !== false || stripos($field, 'tanggal') !== false || stripos($field, 'waktu') !== false) {
                        $hasTimeComponent = true;
                    }

                    if ($operator === 'IN') {
                        $q->whereIn($field, (array)$value);
                    } elseif ($operator === 'NOT IN') {
                        $q->whereNotIn($field, (array)$value);
                    } elseif ($operator === 'BETWEEN' && is_array($value) && count($value) == 2) {
                        if ($hasTimeComponent && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value[1])) {
                            // If between dates and field has time, the end date should be < next day
                            $suffixStart = ' 00:00:00';
                            $formatOut = 'Y-m-d H:i:s';
                            
                            $nextDay = (new \DateTime($value[1]))->modify('+1 day')->format($formatOut);
                            $q->where($field, '>=', $value[0] . $suffixStart)
                              ->where($field, '<', $nextDay);
                        } else {
                            $q->whereBetween($field, $value);
                        }
                    } else {
                        $isDateMatch = false;
                        $normalizedDate = null;
                        if ($hasTimeComponent && $operator === '=' && is_string($value)) {
                            // Extract just the YYYY-MM-DD part if LLM returned datetime or time suffix
                            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
                                $isDateMatch = true;
                                $normalizedDate = $matches[1];
                            }
                        }

                        if ($isDateMatch) {
                            $nextDay = (new \DateTime($normalizedDate))->modify('+1 day')->format('Y-m-d');
                            $q->where(function ($subQ) use ($field, $normalizedDate, $nextDay) {
                                $subQ->where($field, '>=', $normalizedDate)
                                     ->where($field, '<', $nextDay);
                            });
                        } else {
                            $q->where($field, $operator, $value);
                        }
                    }
                }
                
                if ($isComparison) {
                    $q->where(function ($dateQuery) use ($dateField, $currentRange, $prevRange) {
                        $dateQuery->where(function ($curr) use ($dateField, $currentRange) {
                            if (isset($currentRange['start'])) $curr->where($dateField, '>=', $currentRange['start']);
                            if (isset($currentRange['end_next'])) {
                                $curr->where($dateField, '<', $currentRange['end_next']);
                            } elseif (isset($currentRange['end'])) {
                                $curr->where($dateField, '<=', $currentRange['end']);
                            }
                        })->orWhere(function ($prev) use ($dateField, $prevRange) {
                            if (isset($prevRange['start'])) $prev->where($dateField, '>=', $prevRange['start']);
                            if (isset($prevRange['end_next'])) {
                                $prev->where($dateField, '<', $prevRange['end_next']);
                            } elseif (isset($prevRange['end'])) {
                                $prev->where($dateField, '<=', $prevRange['end']);
                            }
                        });
                    });
                }
            });
        }

        // Add dimensions (GROUP BY)
        if (!empty($plan->dimensions)) {
            foreach ($plan->dimensions as $dim) {
                $query->groupBy($dim);
                if ($selects !== ['*'] && !in_array($dim, $selects)) {
                    $query->addSelect($dim);
                }
            }
        }

        // Add sorting
        if (!empty($plan->sorts)) {
            foreach ($plan->sorts as $sort) {
                $direction = strtolower($sort['direction'] ?? 'asc');
                if (!in_array($direction, ['asc', 'desc'])) {
                    $direction = 'asc';
                }
                $sortField = $sort['field'] ?? $sort['metric'] ?? $sort['column'] ?? null;
                if ($sortField) {
                    $query->orderBy($sortField, $direction);
                }
            }
        }

        // Row limit
        if ($plan->limit) {
            $limit = min(max((int)$plan->limit, 1), 500); 
            $query->limit($limit);
        } else {
            $query->limit(100);
        }

        return [
            $query->toSql(),
            $query->getBindings()
        ];
    }

    /**
     * Produce one row per ranked entity and the highest-value accompanying detail.
     * All identifiers originate from QueryValidatorService; all values remain bindings.
     */
    private function compileRankedDetail(QueryPlan $plan, Connection $connection): array
    {
        $table = $plan->tables[0];
        $primary = $plan->dimensions[0];
        $detail = $plan->detail_dimensions[0];
        $metric = $plan->metrics[0];
        if (!empty($metric['is_expression']) || !preg_match('/^[A-Za-z0-9_]+$/', (string) $metric['name'])) {
            throw new \App\Exceptions\AI\SemanticAmbiguityException('Ranking dengan detail hanya tersedia untuk metric kolom yang tervalidasi.');
        }
        foreach ([$table, $primary, $detail] as $identifier) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $identifier)) throw new \LogicException('Invalid validated ranking identifier.');
        }
        $aggregation = strtoupper($metric['aggregation'] ?? 'SUM');
        if (!in_array($aggregation, ['SUM', 'AVG', 'MIN', 'MAX', 'COUNT'], true)) throw new \LogicException('Invalid ranking aggregation.');

        $applyFilters = function ($query) use ($plan) {
            foreach ($plan->filters as $filter) {
                $operator = strtoupper($filter['operator'] ?? '=');
                $field = $filter['field'];
                $value = $filter['value'] ?? null;
                if ($operator === 'IN') $query->whereIn($field, (array) $value);
                elseif ($operator === 'NOT IN') $query->whereNotIn($field, (array) $value);
                elseif ($operator === 'BETWEEN' && is_array($value) && count($value) === 2) $query->whereBetween($field, $value);
                elseif (in_array($operator, ['=', '!=', '>', '<', '>=', '<=', 'LIKE'], true)) $query->where($field, $operator, $value);
                else throw new \LogicException('Invalid validated ranking filter.');
            }
            return $query;
        };

        $totalQuery = $applyFilters(DB::table($table))
            ->selectRaw("`{$primary}` as ranking_entity, {$aggregation}(`{$metric['name']}`) as total_value, COUNT(*) as transaction_count")
            ->groupBy($primary)
            ->orderByDesc('total_value')->orderBy($primary)->limit(min(max((int) ($plan->limit ?? 10), 1), 500));
        $itemQuery = $applyFilters(DB::table($table))
            ->selectRaw("`{$primary}` as ranking_entity, `{$detail}` as top_detail, {$aggregation}(`{$metric['name']}`) as top_detail_value, ROW_NUMBER() OVER (PARTITION BY `{$primary}` ORDER BY {$aggregation}(`{$metric['name']}`) DESC, `{$detail}` ASC) as detail_rank")
            ->groupBy($primary, $detail);

        $sql = "SELECT ROW_NUMBER() OVER (ORDER BY totals.total_value DESC, totals.ranking_entity ASC) as ranking_rank, totals.ranking_entity, totals.total_value, details.top_detail, details.top_detail_value, totals.transaction_count FROM ({$totalQuery->toSql()}) as totals INNER JOIN ({$itemQuery->toSql()}) as details ON details.ranking_entity = totals.ranking_entity AND details.detail_rank = 1 ORDER BY ranking_rank";
        return [$sql, array_merge($totalQuery->getBindings(), $itemQuery->getBindings())];
    }
}
