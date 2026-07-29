<?php

namespace App\Services\Persistence\Pipeline;

use App\Services\Persistence\Contracts\PersistenceStageInterface;
use InvalidArgumentException;

class PersistenceStageRegistry
{
    /** @var array<PersistenceStageInterface> */
    private array $stages = [];

    public function addStage(PersistenceStageInterface $stage): void
    {
        $this->stages[] = $stage;
    }

    /**
     * @return array<PersistenceStageInterface>
     */
    public function getStages(): array
    {
        return $this->stages;
    }
}
