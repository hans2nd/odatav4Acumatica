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
                $probeResult = $this->buildNoTransactionMessage($plan, $connectionId);
                $assistantReply = $probeResult['message'];
                $availabilityContext = $probeResult['availability_context'];
            } else if ($totalRows > 0 && !$isComparison && !$isGrouped) {
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
            // // Let the LLM handle scalar formatting for better conversational tone
            // $scalarTexts = [];
            // $firstRowArray = (array)$results[0];
            // foreach ($firstRowArray as $colKey => $colData) {
            //     if ($colKey !== 'Jumlah Transaksi' && $colKey !== '_total_rows' && $colKey !== '#') {
            //         $label = $columnMap[$colKey] ?? (is_string($colKey) ? ucwords(str_replace(['_', '-'], ' ', $colKey)) : $colKey);
            //         $formattedVal = is_array($colData) ? ($colData['formatted_value'] ?? $colData['value'] ?? '-') : (is_object($colData) ? ($colData->formatted_value ?? $colData->value ?? '-') : $colData);
            //         $scalarTexts[] = "{$label}: {$formattedVal}";
            //     }
            // }
            // if (!empty($scalarTexts)) {
            //     $assistantReply = implode("\n", $scalarTexts);
            // }
        }

        return [
            'results' => $results,
            'assistantReply' => $assistantReply,
            'presentationMetadata' => $presentationMetadata,
            'columnMap' => $columnMap,
            'availabilityContext' => $availabilityContext ?? null,
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

        $ranking = $plan->internal_metadata['ranking'] ?? null;
        if ($ranking) {
            $metric = $ranking['metric_label'] ?: 'Nilai';
            $primary = $ranking['primary_label'] ?: 'Entity';
            $detail = $ranking['detail_label'] ?: 'Detail';
            $columnMap = [
                'ranking_rank' => 'Rank', 'ranking_entity' => $primary,
                'total_value' => 'Total ' . $metric, 'top_detail' => 'Top ' . $detail,
                'top_detail_value' => $metric . ' Item', 'transaction_count' => 'Transaksi',
            ];
        }

        foreach ($plan->tables as $table) {
            $fields = DB::table('sync_metadata_fields')
                ->join('sync_metadata_definitions', 'sync_metadata_definitions.id', '=', 'sync_metadata_fields.definition_id')
                ->where('sync_metadata_definitions.connection_id', $connectionId)
                ->where('sync_metadata_definitions.entity_key', $table)
                ->select('sync_metadata_fields.field_name', 'sync_metadata_fields.semantic_type')
                ->get();

            foreach ($fields as $field) {
                $logical = $field->field_name;
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
                    'key' => $columnMap[$key],
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

    /**
     * Build a contextual "no transaction" message based on the date_context type and data availability probe.
     */
    private function buildNoTransactionMessage(QueryPlan $plan, ?int $connectionId): array
    {
        $context = $plan->date_context;
        
        $defaultResponse = [
            'message' => "Tidak ada transaksi yang ditemukan.",
            'availability_context' => null
        ];

        if (empty($context)) {
            return $defaultResponse;
        }

        $contextLower = strtolower(trim($context));


        // Logical period keywords
        $periodLabels = [
            'today' => 'hari ini', 'hari_ini' => 'hari ini',
            'yesterday' => 'kemarin', 'kemarin' => 'kemarin',
            'current_week' => 'minggu ini', 'this_week' => 'minggu ini', 'minggu_ini' => 'minggu ini',
            'previous_week' => 'minggu lalu', 'last_week' => 'minggu lalu', 'minggu_lalu' => 'minggu lalu',
            'current_month' => 'bulan ini', 'this_month' => 'bulan ini', 'bulan_ini' => 'bulan ini',
            'previous_month' => 'bulan lalu', 'last_month' => 'bulan lalu', 'bulan_lalu' => 'bulan lalu',
            'current_year' => 'tahun ini', 'this_year' => 'tahun ini', 'tahun_ini' => 'tahun ini',
            'previous_year' => 'tahun lalu', 'last_year' => 'tahun lalu', 'tahun_lalu' => 'tahun lalu',
            'ytd' => 'year-to-date', 'year_to_date' => 'year-to-date',
            'mtd' => 'month-to-date', 'month_to_date' => 'month-to-date',
        ];

        if (isset($periodLabels[$contextLower])) {
            $baseMessage = "Tidak ada transaksi pada {$periodLabels[$contextLower]}.";
            return $this->performAvailabilityProbe($plan, $connectionId, $baseMessage, true);
        }

        // Explicit month/year patterns
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $monthLookup = [
            'januari' => 1, 'january' => 1, 'jan' => 1,
            'februari' => 2, 'february' => 2, 'feb' => 2,
            'maret' => 3, 'march' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'mei' => 5, 'may' => 5,
            'juni' => 6, 'june' => 6, 'jun' => 6,
            'juli' => 7, 'july' => 7, 'jul' => 7,
            'agustus' => 8, 'august' => 8, 'agu' => 8, 'aug' => 8,
            'september' => 9, 'sep' => 9,
            'oktober' => 10, 'october' => 10, 'okt' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11, 'nopember' => 11,
            'desember' => 12, 'december' => 12, 'des' => 12, 'dec' => 12,
        ];

        $normalized = str_replace('_', ' ', $contextLower);
        $cleaned = preg_replace('/^bulan\s+/', '', $normalized);
        $cleaned = preg_replace('/\s+tahun\s+/', ' ', $cleaned);

        // Month name + optional year
        foreach ($monthLookup as $name => $monthNum) {
            if (preg_match('/^' . preg_quote($name, '/') . '(?:\s+(\d{4}))?$/', $cleaned, $m)) {
                $label = $monthNames[$monthNum];
                if (!empty($m[1])) {
                    $baseMessage = "Tidak ada transaksi pada bulan {$label} {$m[1]}.";
                    return $this->performAvailabilityProbe($plan, $connectionId, $baseMessage, false, ['month' => $label, 'year' => $m[1]]);
                }
                $baseMessage = "Tidak ada transaksi pada bulan {$label}.";
                return $this->performAvailabilityProbe($plan, $connectionId, $baseMessage, false, ['month' => $label]);
            }
        }

        // YYYY-MM
        if (preg_match('/^(\d{4})-(\d{2})$/', $normalized, $m)) {
            $monthNum = (int)$m[2];
            if (isset($monthNames[$monthNum])) {
                $baseMessage = "Tidak ada transaksi pada bulan {$monthNames[$monthNum]} {$m[1]}.";
                return $this->performAvailabilityProbe($plan, $connectionId, $baseMessage, false, ['month' => $monthNames[$monthNum], 'year' => $m[1]]);
            }
        }

        // Bare year
        $yearCleaned = preg_replace('/^tahun\s+/', '', $normalized);
        if (preg_match('/^(\d{4})$/', $yearCleaned)) {
            $baseMessage = "Tidak ada transaksi pada tahun {$yearCleaned}.";
            return $this->performAvailabilityProbe($plan, $connectionId, $baseMessage, false, ['year' => $yearCleaned]);
        }

        // Fallback for exact dates
        $baseMessage = "Tidak ada transaksi pada tanggal tersebut.";
        return $this->performAvailabilityProbe($plan, $connectionId, $baseMessage, true);
    }

    /**
     * Probes the database to find actual min/max dates for the active table.
     */
    private function performAvailabilityProbe(QueryPlan $plan, ?int $connectionId, string $baseMessage, bool $isLogical, array $requested = []): array
    {
        if (!$connectionId || empty($plan->tables)) {
            return ['message' => $baseMessage, 'availability_context' => null];
        }

        $table = $plan->tables[0];
        
        // Find date field from filters
        $dateField = null;
        $requestedStart = null;
        $requestedEnd = null;
        foreach ($plan->filters as $filter) {
            if ($filter['operator'] === 'BETWEEN' && is_array($filter['value']) && count($filter['value']) === 2) {
                // Heuristic: BETWEEN filter with array of 2 is typically the date filter
                $dateField = $filter['field'];
                $requestedStart = $filter['value'][0];
                $requestedEnd = $filter['value'][1];
                break;
            }
        }

        if (!$dateField) {
            return ['message' => $baseMessage, 'availability_context' => null];
        }

        try {
            $connection = \App\Models\Connection::find($connectionId);
            if (!$connection) {
                return ['message' => $baseMessage, 'availability_context' => null];
            }

            $executor = app(\App\Services\QueryRepositoryService::class);
            $query = "SELECT MIN(`{$dateField}`) as min_date, MAX(`{$dateField}`) as max_date, COUNT(*) as total_rows FROM `{$table}`";
            $result = $executor->executeForAnalytics($query, [], 1, $connection);

            if (empty($result['data']) || !isset($result['data'][0])) {
                return ['message' => $baseMessage, 'availability_context' => null];
            }

            $row = (array)$result['data'][0];
            $totalRows = (int)($row['total_rows'] ?? 0);

            if ($totalRows === 0) {
                return [
                    'message' => "Dataset belum memiliki transaksi.",
                    'availability_context' => [
                        'status' => 'empty_table',
                        'table' => $table,
                        'date_field' => $dateField,
                    ]
                ];
            }

            $minDate = $row['min_date'] ?? null;
            $maxDate = $row['max_date'] ?? null;

            if ($minDate && $maxDate) {
                // Format for user
                $minDateObj = \Carbon\Carbon::parse($minDate);
                $maxDateObj = \Carbon\Carbon::parse($maxDate);
                
                $minDateStr = $minDateObj->translatedFormat('d F Y');
                $maxDateStr = $maxDateObj->translatedFormat('d F Y');

                $availabilityContext = [
                    'table' => $table,
                    'date_field' => $dateField,
                    'available_from' => $minDateObj->format('Y-m-d'),
                    'available_to' => $maxDateObj->format('Y-m-d'),
                ];

                $requestedLabel = !empty($requested['month']) ? $requested['month'] . ' ' . ($requested['year'] ?? '') : $plan->date_context;
                $requestedLabel = trim($requestedLabel);

                if ($requestedStart && $requestedEnd) {
                    $reqStartObj = \Carbon\Carbon::parse($requestedStart);
                    $reqEndObj = \Carbon\Carbon::parse($requestedEnd);

                    if ($reqEndObj->lt($minDateObj) || $reqStartObj->gt($maxDateObj)) {
                        // Case A: Outside range
                        $availabilityContext['status'] = 'outside_available_range';
                        $message = "Data untuk {$requestedLabel} tidak tersedia pada dataset yang sedang dianalisis. Transaksi tersedia mulai {$minDateStr} sampai {$maxDateStr}.";
                        
                        return [
                            'message' => $message,
                            'availability_context' => $availabilityContext
                        ];
                    } else {
                        // Case B: Inside range but 0 rows
                        $availabilityContext['status'] = 'inside_range_zero_filter';
                        $message = "Periode {$requestedLabel} tersedia dalam dataset (rentang data: {$minDateStr} - {$maxDateStr}), namun tidak ada transaksi yang cocok dengan kriteria filter Anda saat ini.";
                        
                        return [
                            'message' => $message,
                            'availability_context' => $availabilityContext
                        ];
                    }
                }

                // Fallback if we couldn't parse start/end
                $availabilityContext['status'] = 'unknown_range';
                $message = "{$baseMessage} Transaksi pada dataset ini tersedia mulai {$minDateStr} sampai {$maxDateStr}.";
                return [
                    'message' => $message,
                    'availability_context' => $availabilityContext
                ];
            }

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Availability probe failed', ['error' => $e->getMessage()]);
        }

        return ['message' => $baseMessage, 'availability_context' => null];
    }
}
