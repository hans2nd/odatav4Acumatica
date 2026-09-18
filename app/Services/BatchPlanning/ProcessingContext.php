<?php

namespace App\Services\BatchPlanning;

use App\Services\BatchPlanning\Contracts\ProcessingContextInterface;
use App\Services\BatchPlanning\ExecutionProfile;

class ProcessingContext implements ProcessingContextInterface
{
    /**
     * @param string $entityName
     * @param array<\App\Services\ChangeDetection\ChangeDetectionResult> $changeResults
     * @param ExecutionProfile $profile
     */
    public function __construct(
        private readonly string $entityName,
        private readonly array $changeResults,
        private readonly ExecutionProfile $profile
    ) {}

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getChangeResults(): array
    {
        return $this->changeResults;
    }

    public function getExecutionProfile(): ExecutionProfile
    {
        return $this->profile;
    }
}
