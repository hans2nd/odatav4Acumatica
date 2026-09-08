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
                Log::warning("DateContextResolverService: Unrecognized date context '{$contextLower}'. Ignoring.");
                break;
        }

        if ($start && $end) {
            $formattedStart = $start->format('Y-m-d');
            $formattedEnd = $end->format('Y-m-d');
            
            // If the plan has a comparison intent without explicit periods, inject this as the base period
            if (!empty($plan->comparison) && empty($plan->comparison['periods'])) {
                $plan->comparison['periods'] = [
                    [$formattedStart, $formattedEnd],
                    $this->calculatePreviousPeriod($start, $end, $plan->comparison['type'] ?? 'previous_period')
                ];
                $plan->comparison['type'] = 'explicit_periods'; // Force explicit to prevent compiler overrides
            } else {
                // Determine the correct date field to filter on.
                // We inject it as a filter with the logical name 'date' (which QueryValidatorService will map to physical)
                $hasDateFilter = false;
                foreach ($plan->filters as &$filter) {
                    if (isset($filter['field']) && (strtolower($filter['field']) === 'date' || strtolower($filter['field']) === 'tanggal')) {
                        $filter['operator'] = 'BETWEEN';
                        $filter['value'] = [$formattedStart, $formattedEnd];
                        $hasDateFilter = true;
                        break;
                    }
                }
                
                if (!$hasDateFilter) {
                    $plan->filters[] = [
                        'field' => 'date',
                        'operator' => 'BETWEEN',
                        'value' => [$formattedStart, $formattedEnd]
                    ];
                }
            }
        }

        return $plan;
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
