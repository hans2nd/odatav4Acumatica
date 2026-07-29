<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Contracts\Metadata\MetadataRepositoryInterface;

class DiscoverEntityMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly string $entityKey
    ) {}

    public function handle(MetadataRepositoryInterface $metadataRepository): void
    {
        // Force refresh to discover and cache the metadata
        $metadataRepository->getMetadata($this->entityKey, true, \App\Enums\RefreshReason::QUEUE);
    }
}
