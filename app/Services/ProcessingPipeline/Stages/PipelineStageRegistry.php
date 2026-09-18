<?php

namespace App\Services\ProcessingPipeline\Stages;

use App\Services\ProcessingPipeline\Contracts\PipelineStageInterface;
use InvalidArgumentException;

class PipelineStageRegistry
{
    /**
     * @var array<PipelineStageInterface>
     */
    private array $stages = [];

    /**
     * @param PipelineStageInterface $stage
     * @param int $priority Menentukan urutan eksekusi
     */
    public function register(PipelineStageInterface $stage, int $priority = 100): void
    {
        $this->stages[$priority][] = $stage;
    }

    /**
     * @return array<PipelineStageInterface>
     */
    public function getStages(): array
    {
        ksort($this->stages); // Mengurutkan berdasarkan prioritas terkecil ke terbesar
        $flattened = [];
        foreach ($this->stages as $priorityGroup) {
            foreach ($priorityGroup as $stage) {
                $flattened[] = $stage;
            }
        }
        return $flattened;
    }
}
