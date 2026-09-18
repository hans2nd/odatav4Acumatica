<?php

namespace App\Services\Reliability\Pipeline;

use App\Services\Reliability\Contracts\ReliabilityStageInterface;

class ReliabilityStageRegistry
{
    /** @var array<ReliabilityStageInterface> */
    private array $stages = [];

    public function addStage(ReliabilityStageInterface $stage): void
    {
        $this->stages[] = $stage;
    }

    /**
     * @return array<ReliabilityStageInterface>
     */
    public function getStages(): array
    {
        return $this->stages;
    }
}
