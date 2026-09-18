<?php

namespace App\Services\Reliability\Recovery\Providers;

use App\Services\Reliability\Contracts\CheckpointProviderInterface;
use App\Services\Reliability\Recovery\CheckpointSnapshot;

class InMemoryCheckpointProvider implements CheckpointProviderInterface
{
    /** @var array<string, CheckpointSnapshot> */
    private array $storage = [];

    public function getLatestCheckpoint(string $jobId): ?CheckpointSnapshot
    {
        return $this->storage[$jobId] ?? null;
    }

    public function saveCheckpoint(CheckpointSnapshot $snapshot): void
    {
        $this->storage[$snapshot->jobId] = $snapshot;
    }

    public function clearCheckpoint(string $jobId): void
    {
        unset($this->storage[$jobId]);
    }
}
