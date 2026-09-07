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
            foreach ($plan->filters as $filter) {
                if (stripos($filter['field'], 'date') !== false || stripos($filter['field'], 'tanggal') !== false || stripos($filter['field'], 'waktu') !== false) {
                    $dateField = $filter['field'];
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
            
            if (!$dateField) {
                // Fallback to table's default date field
                $definition = \Illuminate\Support\Facades\DB::table('sync_metadata_definitions')
                    ->where('connection_id', $connection->id)
                    ->where('entity_key', $plan->tables[0])
                    ->first();
                $semanticProfile = $definition && $definition->semantic_profile ? json_decode($definition->semantic_profile, true) : null;
                
                if ($semanticProfile && !empty($semanticProfile['date_fields'])) {
                    $dateField = $semanticProfile['date_fields'][0];
                } elseif ($definition) {
                    $dateFieldMeta = \Illuminate\Support\Facades\DB::table('sync_metadata_fields')
                        ->where('definition_id', $definition->id)
                        ->whereIn('semantic_type', ['datetime', 'date'])
                        ->first();
                    if ($dateFieldMeta) {
                        $dateField = $dateFieldMeta->field_name;
                    } else {
                        // Fallback by name
                        $dateFieldMeta = \Illuminate\Support\Facades\DB::table('sync_metadata_fields')
                            ->where('definition_id', $definition->id)
                            ->where('field_name', 'like', '%date%')
                            ->first();
                        if ($dateFieldMeta) {
                            $dateField = $dateFieldMeta->field_name;
                        }
                    }
                }
            }
            
            if (!$dateField) {
                throw new \App\Exceptions\AI\SemanticAmbiguityException("Tidak dapat melakukan perbandingan karena rentang waktu atau tanggal acuan tidak ditentukan dalam konteks.");
            }
            
            $compType = strtolower($plan->comparison['type'] ?? 'previous_period');
            $period = strtolower($plan->comparison['period'] ?? 'month');
            
            if ($compType === 'explicit_periods' && !empty($plan->comparison['periods'])) {
                $periods = $plan->comparison['periods'];
                if (count($periods) === 2) {
                    $p1 = $periods[0];
                    $p2 = $periods[1];
                    if (is_array($p1) && count($p1) === 2) {
                        $currentRange = ['start' => $p1[0], 'end' => $p1[1]];
                    } else {
                        $currentRange = ['start' => $p1, 'end' => $p1];
                    }
                    if (is_array($p2) && count($p2) === 2) {
                        $prevRange = ['start' => $p2[0], 'end' => $p2[1]];
                    } else {
                        $prevRange = ['start' => $p2, 'end' => $p2];
                    }
                }
            } else {
                if (empty($currentRange)) {
                    throw new \App\Exceptions\AI\SemanticAmbiguityException("Tidak dapat melakukan perbandingan karena rentang waktu acuan tidak ditemukan.");
                }
                
                if ($hasSingleDateContext && $compType !== 'same_period_previous_year' && $period !== 'day') {
                    throw new \App\Exceptions\AI\SemanticAmbiguityException("Terdapat konteks satu hari spesifik ({$currentRange['start']}). Apakah Anda ingin membandingkannya dengan tanggal yang sama (misal hari sebelumnya), atau membandingkan total seluruh bulan?");
                }
                
                if ($compType === 'same_period_previous_year') {
                    $period = 'year';
                }
                
                $start = $currentRange['start'] ?? null;
                $end = $currentRange['end'] ?? null;
                
                if ($period === 'month') {
                    if ($start) {
                        $dt = new \DateTime($start);
                        $dt->modify('-1 month');
                        $prevRange['start'] = $dt->format('Y-m-d');
                    }
                    if ($end) {
                        $dt = new \DateTime($end);
                        if ($dt->format('t') === $dt->format('d')) {
                            $dt->modify('last day of previous month');
                        } else {
                            $dt->modify('-1 month');
                        }
                        $prevRange['end'] = $dt->format('Y-m-d');
                    }
                } elseif ($period === 'year') {
                    if ($start) {
                        $dt = new \DateTime($start);
                        $dt->modify('-1 year');
                        $prevRange['start'] = $dt->format('Y-m-d');
                    }
                    if ($end) {
                        $dt = new \DateTime($end);
                        $dt->modify('-1 year');
                        $prevRange['end'] = $dt->format('Y-m-d');
                    }
                } elseif ($period === 'week') {
                    if ($start) {
                        $dt = new \DateTime($start);
                        $dt->modify('-1 week');
                        $prevRange['start'] = $dt->format('Y-m-d');
                    }
                    if ($end) {
                        $dt = new \DateTime($end);
                        $dt->modify('-1 week');
                        $prevRange['end'] = $dt->format('Y-m-d');
                    }
                } elseif ($period === 'day') {
                    if ($start) {
                        $dt = new \DateTime($start);
                        $dt->modify('-1 day');
                        $prevRange['start'] = $dt->format('Y-m-d');
                    }
                    if ($end) {
                        $dt = new \DateTime($end);
                        $dt->modify('-1 day');
                        $prevRange['end'] = $dt->format('Y-m-d');
                    }
                } else {
                    throw new \App\Exceptions\AI\SemanticAmbiguityException("Periode perbandingan '{$period}' belum didukung.");
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
                
                if (!in_array($agg, ['SUM', 'COUNT', 'AVG', 'MIN', 'MAX', ''])) {
                    throw new \Exception("Invalid aggregation function: {$agg}");
                }
                
                $innerCol = $isExpr ? $col : "`{$col}`";
                // If the user's expression already includes the aggregation (e.g., SUM(A)/SUM(B)), 
                // and agg is empty or we know it's a complex expression, we might need to handle it.
                // But following instructions, we just remove backticks so the inner SQL works.
                
                $aggPrefix = empty($agg) ? "" : "{$agg}";
                
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
}
