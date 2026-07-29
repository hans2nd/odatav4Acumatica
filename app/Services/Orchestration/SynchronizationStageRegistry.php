<?php

namespace App\Services\Orchestration;

use App\Services\Orchestration\Contracts\SynchronizationStageInterface;

class SynchronizationStageRegistry
{
    /** @var array<SynchronizationStageInterface> */
    private array $stages = [];

    public function addStage(SynchronizationStageInterface $stage): void
    {
        $this->stages[] = $stage;
    }

    /**
     * @return array<SynchronizationStageInterface>
     */
    public function getStages(): array
    {
        return $this->stages;
    }
}
