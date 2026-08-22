<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class MenuFilterService
{
    /**
     * Apply configured filters to a query builder instance safely.
     * Uses the abstract Query Builder connection, avoiding DB-specific raw SQL.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $filterConfig
     * @param Request $request
     * @return void
     *
     * @throws ValidationException
     */
    public function applyFilters($query, array $filterConfig, Request $request)
    {
        $isSubmitting = $request->has('apply_filters') || $request->has('page');
        $errors = [];

        foreach ($filterConfig as $filter) {
            $field = $filter['field'] ?? null;
            $type = $filter['type'] ?? 'text';
            $operator = $filter['operator'] ?? 'contains';
            $isRequired = $filter['required'] ?? false;

            if (!$field) {
                continue;
            }

            // Date range sends field_from and field_to
            if ($type === 'date_range') {
                $valFrom = $request->input($field . '_from');
                $valTo = $request->input($field . '_to');
                
                if ($isRequired && $isSubmitting && blank($valFrom) && blank($valTo)) {
                    $errors[$field] = "The {$field} filter is required.";
                    continue;
                }

                if (filled($valFrom) && filled($valTo)) {
                    $query->whereBetween($field, [Carbon::parse($valFrom)->startOfDay(), Carbon::parse($valTo)->endOfDay()]);
                } elseif (filled($valFrom)) {
                    $query->where($field, '>=', Carbon::parse($valFrom)->startOfDay());
                } elseif (filled($valTo)) {
                    $query->where($field, '<=', Carbon::parse($valTo)->endOfDay());
                }
                
                continue;
            }

            $value = $request->input($field);

            if ($isRequired && $isSubmitting && blank($value)) {
                $errors[$field] = "The {$field} filter is required.";
                continue;
            }

            if (blank($value)) {
                continue;
            }

            // Apply filter based on allowed operator
            switch ($operator) {
                case 'contains':
                    $query->where($field, 'LIKE', '%' . $value . '%');
                    break;
                case 'starts_with':
                    $query->where($field, 'LIKE', $value . '%');
                    break;
                case 'ends_with':
                    $query->where($field, 'LIKE', '%' . $value);
                    break;
                case 'equals':
                    $query->where($field, '=', $value);
                    break;
                case 'greater_than':
                    $query->where($field, '>', $value);
                    break;
                case 'greater_than_or_equal':
                    $query->where($field, '>=', $value);
                    break;
                case 'less_than':
                    $query->where($field, '<', $value);
                    break;
                case 'less_than_or_equal':
                    $query->where($field, '<=', $value);
                    break;
                case 'before':
                    $query->where($field, '<', Carbon::parse($value)->startOfDay());
                    break;
                case 'after':
                    $query->where($field, '>', Carbon::parse($value)->endOfDay());
                    break;
            }
        }

        if (!empty($errors) && $isSubmitting) {
            throw ValidationException::withMessages($errors);
        }
    }
}
