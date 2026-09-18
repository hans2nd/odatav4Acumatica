<?php

namespace App\Services\AI;

use App\DTO\AI\QueryPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DateContextResolverService
{
    /**
     * Resolves logical date_context from the QueryPlan into deterministic physical boundaries.
     * Inserts these boundaries into the plan's filters or comparison object.
     */
    public function resolve(QueryPlan $plan): QueryPlan
    {
        $context = $plan->date_context;
        if (empty($context)) {
            return $plan;
        }
        $filters = $plan->filters;
        $comparison = $plan->comparison;

        $contextLower = strtolower(trim($context));
        $now = Carbon::now();
        $start = null;
        $end = null;

        // Common Logical Contexts
        switch ($contextLower) {
            case 'today':
            case 'hari_ini':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'yesterday':
            case 'kemarin':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                break;
            case 'current_week':
            case 'minggu_ini':
            case 'this_week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                break;
            case 'previous_week':
            case 'last_week':
            case 'minggu_lalu':
                $start = $now->copy()->subWeek()->startOfWeek();
                $end = $now->copy()->subWeek()->endOfWeek();
                break;
            case 'current_month':
            case 'this_month':
            case 'bulan_ini':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
            case 'previous_month':
            case 'last_month':
            case 'bulan_lalu':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
                break;
            case 'current_year':
            case 'this_year':
            case 'tahun_ini':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                break;
            case 'previous_year':
            case 'last_year':
            case 'tahun_lalu':
                $start = $now->copy()->subYear()->startOfYear();
                $end = $now->copy()->subYear()->endOfYear();
                break;
            case 'ytd':
            case 'year_to_date':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfDay();
                break;
            case 'mtd':
            case 'month_to_date':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfDay();
                break;
            default:
                // Try to parse explicit month/year patterns like "november_2024", "2024-11", "2024"
                $parsed = $this->parseExplicitDateContext($contextLower, $now);
                if ($parsed) {
                    $start = $parsed['start'];
                    $end = $parsed['end'];
                } else {
                    Log::warning("DateContextResolverService: Unrecognized date context '{$contextLower}'. Ignoring.");
                }
                break;
        }

        if ($start && $end) {
            $formattedStart = $start->format('Y-m-d');
            $formattedEnd = $end->format('Y-m-d');
            
            // If the plan has a comparison intent without explicit periods, inject this as the base period
            if (!empty($comparison) && strtolower((string) ($comparison['type'] ?? '')) !== 'table' && empty($comparison['periods'])) {
                $comparison['periods'] = [
                    [$formattedStart, $formattedEnd],
                    $this->calculatePreviousPeriod($start, $end, $comparison['type'] ?? 'previous_period')
                ];
                $comparison['type'] = 'explicit_periods'; // Force explicit to prevent compiler overrides
            } else {
                // Determine the correct date field to filter on.
                // We inject it as a filter with the logical name 'date' (which QueryValidatorService will map to physical)
                $hasDateFilter = false;
                foreach ($filters as &$filter) {
                    if (isset($filter['field']) && (strtolower($filter['field']) === 'date' || strtolower($filter['field']) === 'tanggal')) {
                        $filter['operator'] = 'BETWEEN';
                        $filter['value'] = [$formattedStart, $formattedEnd];
                        $hasDateFilter = true;
                        break;
                    }
                }
                
                if (!$hasDateFilter) {
                    $filters[] = [
                        'field' => 'date',
                        'operator' => 'BETWEEN',
                        'value' => [$formattedStart, $formattedEnd]
                    ];
                }
            }
        }

        return new QueryPlan(
            intent: $plan->intent, metrics: $plan->metrics, dimensions: $plan->dimensions, filters: $filters,
            sorts: $plan->sorts, limit: $plan->limit, comparison: $comparison, date_context: $plan->date_context,
            domain: $plan->domain, relationships: $plan->relationships, analysis: $plan->analysis, columns: $plan->columns,
            tables: $plan->tables, internal_metadata: $plan->internal_metadata
        );
    }

    /**
     * Parse explicit date context strings that the LLM may return.
     * Handles: "november_2024", "november 2024", "2024-11", "2024", "desember", etc.
     *
     * @return array{start: Carbon, end: Carbon}|null
     */
    private function parseExplicitDateContext(string $context, Carbon $now): ?array
    {
        // Normalize separators: replace underscores, dashes (when not part of YYYY-MM), multiple spaces
        $normalized = str_replace('_', ' ', $context);
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        $monthNames = [
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

        // Pattern 1: "month year" or "month" (e.g. "november 2024", "desember")
        // Also handles "bulan november 2024", "bulan 11 tahun 2024"
        $cleaned = preg_replace('/^bulan\s+/', '', $normalized);
        $cleaned = preg_replace('/\s+tahun\s+/', ' ', $cleaned);

        // Try month name + optional year
        foreach ($monthNames as $name => $monthNum) {
            if (preg_match('/^' . preg_quote($name, '/') . '(?:\s+(\d{4}))?$/', $cleaned, $m)) {
                $year = !empty($m[1]) ? (int)$m[1] : $now->year;
                $start = Carbon::create($year, $monthNum, 1)->startOfDay();
                $end = $start->copy()->endOfMonth();
                return ['start' => $start, 'end' => $end];
            }
        }

        // Pattern 2: "bulan NN tahun YYYY" or "NN/YYYY" or "NN YYYY" (numeric month)
        if (preg_match('/^(\d{1,2})\s+(\d{4})$/', $cleaned, $m) ||
            preg_match('/^(\d{1,2})\/(\d{4})$/', $normalized, $m)) {
            $monthNum = (int)$m[1];
            $year = (int)$m[2];
            if ($monthNum >= 1 && $monthNum <= 12) {
                $start = Carbon::create($year, $monthNum, 1)->startOfDay();
                $end = $start->copy()->endOfMonth();
                return ['start' => $start, 'end' => $end];
            }
        }

        // Pattern 3: "YYYY-MM" ISO format
        if (preg_match('/^(\d{4})-(\d{2})$/', $normalized, $m)) {
            $year = (int)$m[1];
            $monthNum = (int)$m[2];
            if ($monthNum >= 1 && $monthNum <= 12) {
                $start = Carbon::create($year, $monthNum, 1)->startOfDay();
                $end = $start->copy()->endOfMonth();
                return ['start' => $start, 'end' => $end];
            }
        }

        // Pattern 4: Bare year "YYYY" (e.g. "2024", "tahun 2024")
        $yearCleaned = preg_replace('/^tahun\s+/', '', $normalized);
        if (preg_match('/^(\d{4})$/', $yearCleaned, $m)) {
            $year = (int)$m[1];
            if ($year >= 1900 && $year <= 2100) {
                $start = Carbon::create($year, 1, 1)->startOfDay();
                $end = Carbon::create($year, 12, 31)->endOfDay();
                return ['start' => $start, 'end' => $end];
            }
        }

        return null;
    }

    private function calculatePreviousPeriod(Carbon $start, Carbon $end, string $type): array
    {
        $prevStart = $start->copy();
        $prevEnd = $end->copy();

        $diffInDays = $start->diffInDays($end);

        if ($type === 'same_period_previous_year') {
            $prevStart->subYear();
            $prevEnd->subYear();
        } else {
            // "previous_period"
            if ($diffInDays >= 28 && $diffInDays <= 31) {
                // Roughly a month
                $prevStart->subMonth()->startOfMonth();
                $prevEnd->subMonth()->endOfMonth();
            } elseif ($diffInDays == 6 || $diffInDays == 7) {
                // A week
                $prevStart->subWeek();
                $prevEnd->subWeek();
            } elseif ($diffInDays > 300) {
                // A year
                $prevStart->subYear()->startOfYear();
                $prevEnd->subYear()->endOfYear();
            } else {
                // Exact day shift
                $prevStart->subDays($diffInDays + 1);
                $prevEnd->subDays($diffInDays + 1);
            }
        }

        return [$prevStart->format('Y-m-d'), $prevEnd->format('Y-m-d')];
    }
}
