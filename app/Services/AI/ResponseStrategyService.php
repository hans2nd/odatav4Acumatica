<?php

namespace App\Services\AI;

class ResponseStrategyService
{
    public function respond(array $insight, array $context = []): array
    {
        $mode = $context['analysis_mode'] ?? (!empty($context['comparison']) ? 'comparison' : 'kpi');
        $lines = [$insight['headline'] ?? 'Analisis selesai.'];
        if (!empty($insight['drivers'])) $lines[] = 'Catatan: ' . implode(' ', $insight['drivers']);
        if (!empty($insight['recommended_next_analysis'])) $lines[] = 'Langkah berikutnya: ' . implode(', ', $insight['recommended_next_analysis']) . '.';
        return ['strategy' => $mode, 'content' => implode("\n", $lines)];
    }
}
