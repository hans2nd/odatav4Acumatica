<?php

namespace App\Services\AI;

class InsightEngineService
{
    public function generate(array $analysis, array $context = []): array
    {
        $facts = $analysis['facts'] ?? [];
        $headline = 'Analisis selesai.';
        if ($facts) {
            $fact = $facts[0];
            if ($fact['type'] === 'table_gap') {
                $headline = 'Terdapat potential gap sebesar ' . number_format(abs($fact['value']), 2, ',', '.') . ' antara dua agregat yang dibandingkan.';
            } else $headline = $fact['type'] === 'decline'
                ? 'Nilai turun ' . number_format(abs($fact['value']), 2, ',', '.') . '% dibanding periode pembanding.'
                : 'Nilai naik ' . number_format($fact['value'], 2, ',', '.') . '% dibanding periode pembanding.';
        }
        return ['headline' => $headline, 'facts' => $facts, 'drivers' => $analysis['drivers'] ?? [], 'risks' => $analysis['risks'] ?? [], 'opportunities' => $analysis['opportunities'] ?? [], 'recommended_next_analysis' => !empty($context['dimensions']) ? ['Lihat contributor pada dimensi berikutnya'] : []];
    }
}
