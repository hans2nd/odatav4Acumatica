<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;
use Illuminate\Support\Facades\DB;

class ResultInterpretationService
{
    /**
     * Format results for display and generate an assistant reply based on deterministic rules.
     */
    public function interpret(array $results, QueryPlan $plan, int $connectionId): array
    {
        $totalRows = count($results);
        $assistantReply = null;
        $presentationMetadata = null;
        $isGrouped = !empty($plan->dimensions);
        $isComparison = !empty($plan->comparison);

        if (!empty($plan->metrics)) {
            $firstRow = $results[0] ?? null;
            $totalRowsAgg = null;
            if ($firstRow) {
                $totalRowsAgg = is_array($firstRow) ? ($firstRow['_total_rows'] ?? null) : ($firstRow->_total_rows ?? null);
            }

            if ($isComparison && !$isGrouped && $totalRows > 0) {
                $metricConfig = $plan->metrics[0];
                $alias = $metricConfig['alias'] ?? preg_replace('/[^a-zA-Z0-9_]/', '_', $metricConfig['name']);
                $col = $metricConfig['name'];

                $valCurrent = is_array($firstRow) ? ($firstRow[$alias . '_current'] ?? $firstRow[$col . '_current'] ?? null) : ($firstRow->{$alias . '_current'} ?? $firstRow->{$col . '_current'} ?? null);
                $valPrev = is_array($firstRow) ? ($firstRow[$alias . '_previous'] ?? $firstRow[$col . '_previous'] ?? null) : ($firstRow->{$alias . '_previous'} ?? $firstRow->{$col . '_previous'} ?? null);
                $diff = is_array($firstRow) ? ($firstRow[$alias . '_difference'] ?? $firstRow[$col . '_difference'] ?? null) : ($firstRow->{$alias . '_difference'} ?? $firstRow->{$col . '_difference'} ?? null);
                $pct = is_array($firstRow) ? ($firstRow[$alias . '_percentage_change'] ?? $firstRow[$col . '_percentage_change'] ?? null) : ($firstRow->{$alias . '_percentage_change'} ?? $firstRow->{$col . '_percentage_change'} ?? null);

                $rowCountCurrent = is_array($firstRow) ? ($firstRow[$alias . '_current_row_count'] ?? $firstRow[$col . '_current_row_count'] ?? 0) : ($firstRow->{$alias . '_current_row_count'} ?? $firstRow->{$col . '_current_row_count'} ?? 0);
                $rowCountPrev = is_array($firstRow) ? ($firstRow[$alias . '_previous_row_count'] ?? $firstRow[$col . '_previous_row_count'] ?? 0) : ($firstRow->{$alias . '_previous_row_count'} ?? $firstRow->{$col . '_previous_row_count'} ?? 0);
                $nonNullCurrent = is_array($firstRow) ? ($firstRow[$alias . '_current_non_null_count'] ?? $firstRow[$col . '_current_non_null_count'] ?? 0) : ($firstRow->{$alias . '_current_non_null_count'} ?? $firstRow->{$col . '_current_non_null_count'} ?? 0);
                
                $comparisonConf = $plan->comparison;
                $compType = $comparisonConf['type'] ?? 'previous_period';
                $periodType = $comparisonConf['period'] ?? 'month';

                $currentLabel = 'Periode Saat Ini';
                $prevLabel = 'Periode Pembanding';

                if ($compType === 'explicit_periods' && !empty($comparisonConf['periods'])) {
                    $p1 = $comparisonConf['periods'][0] ?? null;
                    $p2 = $comparisonConf['periods'][1] ?? null;
                    
                    $formatDate = function($p) {
                        if (is_array($p) && count($p) === 2) {
                            return $p[0] === $p[1] ? $p[0] : "{$p[0]} s/d {$p[1]}";
                        }
                        return is_string($p) ? $p : 'Periode';
                    };
                    
                    if ($p1) $currentLabel = $formatDate($p1);
                    if ($p2) $prevLabel = $formatDate($p2);
                } else {
                    if ($periodType === 'day') {
                        $currentLabel = 'Hari Ini';
                        $prevLabel = 'Hari Sebelumnya';
                    } elseif ($periodType === 'month') {
                        $currentLabel = 'Bulan Ini';
                        $prevLabel = 'Bulan Sebelumnya';
                    } elseif ($periodType === 'year') {
                        $currentLabel = 'Tahun Ini';
                        $prevLabel = 'Tahun Sebelumnya';
                    } elseif ($periodType === 'week') {
                        $currentLabel = 'Minggu Ini';
                        $prevLabel = 'Minggu Sebelumnya';
                    }
                    if ($compType === 'same_period_previous_year') {
                        $prevLabel = 'Tahun Sebelumnya (Periode Sama)';
                    }
                }

                $results = [[
                    $currentLabel => $rowCountCurrent == 0 ? '-' : $valCurrent,
                    $prevLabel => $rowCountPrev == 0 ? '-' : $valPrev,
                    'Selisih' => ($rowCountCurrent == 0 || $rowCountPrev == 0) ? '-' : $diff,
                    'Persentase (%)' => ($rowCountCurrent == 0 || $rowCountPrev == 0 || ($pct === null && $valPrev == 0)) ? '-' : round((float)$pct, 2) . '%'
                ]];

                if ($rowCountCurrent == 0) {
                    $assistantReply = "Tidak ada transaksi pada periode saat ini.";
                } elseif ($rowCountPrev == 0) {
                    $valFmt = number_format((float)$valCurrent, 0, ',', '.');
                    $assistantReply = "Nilai pada periode saat ini sebesar {$valFmt}. Tidak ada transaksi pada periode pembanding, sehingga persentase perubahan tidak dapat dihitung.";
                } elseif ($nonNullCurrent == 0) {
                    $assistantReply = "Transaksi ditemukan, tetapi nilai tidak tersedia.";
                } else {
                    $valFmt = number_format((float)$valCurrent, 0, ',', '.');
                    $prevFmt = number_format((float)$valPrev, 0, ',', '.');
                    if ($pct === null && $valPrev == 0) {
                        $assistantReply = "Nilai periode saat ini sebesar {$valFmt}. Nilai periode pembanding adalah 0, sehingga persentase perubahan tidak dapat dihitung.";
                    } else {
                        $diffFmt = number_format((float)abs($diff), 0, ',', '.');
                        $pctFmt = round((float)$pct, 2) . '%';
                        $trend = $diff > 0 ? "naik" : ($diff < 0 ? "turun" : "sama");
                        if ($trend === "sama") {
                            $assistantReply = "Nilai periode saat ini sebesar {$valFmt}, sama dengan periode pembanding yang juga sebesar {$prevFmt}.";
                        } else {
                            $assistantReply = "Nilai periode saat ini sebesar {$valFmt}, sedangkan periode pembanding sebesar {$prevFmt}. Terdapat selisih sebesar {$diffFmt} ({$trend} {$pctFmt}).";
                        }
                    }
                }
            } elseif ($totalRows === 0 || ($totalRows === 1 && !$isGrouped && $totalRowsAgg !== null && $totalRowsAgg == 0)) {
                $assistantReply = "Tidak ada transaksi pada tanggal tersebut.";
            } else if ($totalRows > 0 && !$isComparison) {
                $metricConfig = $plan->metrics[0];
                $alias = $metricConfig['alias'] ?? preg_replace('/[^a-zA-Z0-9_]/', '_', $metricConfig['name']);
                $aggSuffix = empty($metricConfig['aggregation']) ? '' : '_' . strtoupper($metricConfig['aggregation']);
                
                $possibleKeys = [
                    $alias . $aggSuffix,
                    $alias,
                    $metricConfig['name'] . $aggSuffix,
                    $metricConfig['name'],
                ];
                
                $aggValue = null;
                foreach ($possibleKeys as $pk) {
                    $val = is_array($firstRow) ? ($firstRow[$pk] ?? null) : ($firstRow->$pk ?? null);
                    if ($val !== null) {
                        $aggValue = $val;
                        break;
                    }
                }
                
                if ($aggValue === null) {
                    $assistantReply = "Transaksi ditemukan, tetapi nilai tidak tersedia.";
                }
            }
        }

        $formattedPayload = $this->formatResultHeaders($results, $plan, $connectionId);
        $results = $formattedPayload['data'];
        $presentationMetadata = $formattedPayload['metadata'];
        $columnMap = $formattedPayload['column_map'] ?? [];

        if (!$isGrouped && !$isComparison && $totalRows === 1 && empty($assistantReply)) {
            $scalarTexts = [];
            $firstRowArray = (array)$results[0];
            foreach ($firstRowArray as $colKey => $colData) {
                if ($colKey !== 'Jumlah Transaksi' && $colKey !== '_total_rows' && $colKey !== '#') {
                    $label = $columnMap[$colKey] ?? (is_string($colKey) ? ucwords(str_replace(['_', '-'], ' ', $colKey)) : $colKey);
                    $formattedVal = is_array($colData) ? ($colData['formatted_value'] ?? $colData['value'] ?? '-') : (is_object($colData) ? ($colData->formatted_value ?? $colData->value ?? '-') : $colData);
                    $scalarTexts[] = "{$label}: {$formattedVal}";
                }
            }
            if (!empty($scalarTexts)) {
                $assistantReply = implode("\n", $scalarTexts);
            }
        }

        return [
            'results' => $results,
            'assistantReply' => $assistantReply,
            'presentationMetadata' => $presentationMetadata,
            'columnMap' => $columnMap,
        ];
    }

    private function formatResultHeaders(array $results, QueryPlan $plan, int $connectionId): array
    {
        if (empty($results)) {
            return [
                'data' => [],
                'metadata' => [
                    'columns' => [],
                    'visualization' => 'table'
                ],
                'column_map' => []
            ];
        }

        $firstRow = is_array($results[0]) ? $results[0] : (array)$results[0];
        $columnMap = [];
        $columnsMetadata = [];

        foreach ($plan->tables as $table) {
            $fields = DB::table('sync_metadata_fields')
                ->join('sync_metadata_definitions', 'sync_metadata_definitions.id', '=', 'sync_metadata_fields.definition_id')
                ->where('sync_metadata_definitions.connection_id', $connectionId)
                ->where('sync_metadata_definitions.entity_key', $table)
                ->select('sync_metadata_fields.field_name', 'sync_metadata_fields.logical_name', 'sync_metadata_fields.semantic_type')
                ->get();

            foreach ($fields as $field) {
                $logical = $field->logical_name ?: $field->field_name;
                $columnMap[$field->field_name] = $logical;
                
                $aggKeys = [
                    $field->field_name,
                    $field->field_name . '_SUM',
                    $field->field_name . '_AVG',
                    $field->field_name . '_COUNT',
                ];
                
                foreach ($aggKeys as $key) {
                    $columnMap[$key] = $logical;
                }
            }
        }

        foreach (array_keys($firstRow) as $key) {
            $isDimension = in_array($key, $plan->dimensions);
            $isMetric = false;
            foreach ($plan->metrics as $metric) {
                if ($key === $metric['name'] || $key === ($metric['alias'] ?? '') || $key === $metric['name'] . '_' . ($metric['aggregation'] ?? 'SUM')) {
                    $isMetric = true;
                    break;
                }
            }

            if (!isset($columnMap[$key])) {
                $columnMap[$key] = ucwords(str_replace(['_', '-'], ' ', $key));
            }
            
            if ($key !== '_total_rows' && $key !== 'row_count' && $key !== '#') {
                $columnsMetadata[] = [
                    'key' => $key,
                    'label' => $columnMap[$key],
                    'type' => $isDimension ? 'dimension' : ($isMetric ? 'metric' : 'attribute')
                ];
            }
        }

        // Setup Sort and Limit for Grouped queries
        if (!empty($plan->dimensions)) {
            $sortField = null;
            $sortDir = 'desc';
            
            if (!empty($plan->sorts)) {
                $sortField = $plan->sorts[0]['field'];
                $sortDir = strtolower($plan->sorts[0]['direction'] ?? 'desc');
            } elseif (!empty($plan->metrics)) {
                $m = $plan->metrics[0];
                $sortField = $m['name'] . '_' . ($m['aggregation'] ?? 'SUM');
                $sortDir = 'desc';
                // Find if alias exists in first row
                $alias = $m['alias'] ?? preg_replace('/[^a-zA-Z0-9_]/', '_', $m['name']);
                if (array_key_exists($alias, $firstRow)) {
                    $sortField = $alias;
                }
            }
            
            if ($sortField) {
                usort($results, function($a, $b) use ($sortField, $sortDir) {
                    $valA = is_array($a) ? ($a[$sortField] ?? 0) : ($a->$sortField ?? 0);
                    $valB = is_array($b) ? ($b[$sortField] ?? 0) : ($b->$sortField ?? 0);
                    if ($valA == $valB) return 0;
                    if ($sortDir === 'asc') {
                        return ($valA < $valB) ? -1 : 1;
                    } else {
                        return ($valA > $valB) ? -1 : 1;
                    }
                });
            }
            
            if (isset($plan->limit) && $plan->limit > 0) {
                $results = array_slice($results, 0, $plan->limit);
            }
        }

        $formattedResults = [];
        foreach ($results as $index => $row) {
            $rowArray = is_array($row) ? $row : (array)$row;
            $formattedRow = ['#' => $index + 1];

            foreach ($rowArray as $key => $value) {
                if ($key === '_total_rows' || $key === 'row_count') {
                    $formattedRow['Jumlah Transaksi'] = [
                        'value' => $value,
                        'formatted_value' => is_numeric($value) ? number_format((float)$value, 0, ',', '.') : $value,
                        'raw_key' => $key
                    ];
                    continue;
                }

                $formattedVal = $value;
                if (is_numeric($value)) {
                    $isCount = stripos($key, 'count') !== false || stripos($key, 'jumlah') !== false;
                    $formattedVal = $isCount ? number_format((float)$value, 0, ',', '.') : number_format((float)$value, 2, ',', '.');
                }

                $formattedRow[$columnMap[$key] ?? $key] = [
                    'value' => $value,
                    'formatted_value' => $formattedVal,
                    'raw_key' => $key
                ];
            }
            $formattedResults[] = $formattedRow;
        }

        return [
            'data' => $formattedResults,
            'metadata' => [
                'columns' => $columnsMetadata,
                'visualization' => (!empty($plan->dimensions) && !empty($plan->metrics)) ? 'bar_chart' : 'table'
            ],
            'column_map' => $columnMap
        ];
    }
}
