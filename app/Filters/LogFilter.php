<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LogFilter
{
    public function apply(Builder $query, Request $request): Builder
    {
        // 1. Global Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('entity_key', 'like', "%{$search}%")
                  ->orWhere('entity_description', 'like', "%{$search}%")
                  ->orWhere('error_message', 'like', "%{$search}%")
                  ->orWhere('current_stage', 'like', "%{$search}%")
                  ->orWhere('execution_source', 'like', "%{$search}%");
            });
        }

        // 2. Exact Match Filters (Multiple values supported via array)
        $exactFilters = ['status', 'type', 'source', 'entity_key'];
        foreach ($exactFilters as $filter) {
            if ($request->has($filter) && !empty($request->input($filter))) {
                $value = $request->input($filter);
                if (is_array($value)) {
                    $query->whereIn($filter, $value);
                } else {
                    $query->where($filter, $value);
                }
            }
        }

        // 3. Date Range Filters
        if ($start = $request->input('date_from')) {
            $query->where('started_at', '>=', $start . ' 00:00:00');
        }
        if ($end = $request->input('date_to')) {
            $query->where('started_at', '<=', $end . ' 23:59:59');
        }

        // 4. Duration Range
        if ($duration = $request->input('duration')) {
            switch ($duration) {
                case '<1m':  $query->where('duration_seconds', '<', 60); break;
                case '1-5m': $query->whereBetween('duration_seconds', [60, 300]); break;
                case '5-15m': $query->whereBetween('duration_seconds', [300, 900]); break;
                case '15-30m': $query->whereBetween('duration_seconds', [900, 1800]); break;
                case '30-60m': $query->whereBetween('duration_seconds', [1800, 3600]); break;
                case '>60m': $query->where('duration_seconds', '>', 3600); break;
            }
        }
        
        // 5. Sorting
        $sortField = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        
        // Validate sort field to prevent SQL injection
        $allowedSorts = ['id', 'started_at', 'completed_at', 'duration_seconds', 'rows_per_second', 'records_inserted', 'records_fetched', 'peak_memory_mb', 'status'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('id');
        }

        return $query;
    }
}
