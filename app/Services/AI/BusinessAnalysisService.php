<?php

namespace App\Services\AI;

class BusinessAnalysisService
{
    /** Produces evidence-only facts; it deliberately does not infer causality. */
    public function analyze(array $results, array $context = []): array
    {
        $facts = [];
        $drivers = [];
        if (($context['comparison']['type'] ?? null) === 'table' && !empty($context['comparison_result'])) {
            $comparison = $context['comparison_result']['comparison'];
            $facts[] = ['type' => 'table_gap', 'field' => 'gap', 'value' => (float) $comparison['gap'], 'ratio' => $comparison['ratio']];
            $drivers[] = 'Gap menunjukkan perbedaan agregat dan memerlukan investigasi lebih lanjut sebelum disimpulkan sebagai penyebab bisnis.';
        }
        foreach ($results as $row) {
            $row = (array) $row;
            foreach ($row as $key => $value) {
                if (str_ends_with((string) $key, '_percentage_change') && is_numeric($value)) {
                    $facts[] = ['type' => (float) $value < 0 ? 'decline' : 'growth', 'field' => $key, 'value' => (float) $value];
                }
            }
        }
        if (!empty($context['dimensions']) && count($results) > 1) $drivers[] = 'Breakdown tersedia untuk investigasi contributor per ' . implode(', ', (array) $context['dimensions']) . '.';
        return ['facts' => $facts, 'drivers' => $drivers, 'risks' => [], 'opportunities' => []];
    }
}
