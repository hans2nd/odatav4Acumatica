<?php

namespace App\Services\Reliability\Classification\Classifiers;

use App\Services\Reliability\Contracts\FailureClassifierInterface;

class FailureClassifierRegistry
{
    /** @var array<FailureClassifierInterface> */
    private array $classifiers = [];

    public function addClassifier(FailureClassifierInterface $classifier): void
    {
        $this->classifiers[] = $classifier;
    }

    /**
     * @return array<FailureClassifierInterface>
     */
    public function getClassifiers(): array
    {
        return $this->classifiers;
    }
}
