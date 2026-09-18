<?php

namespace App\Services\ProcessingPipeline;

class PipelineMetrics
{
    public float $executionDuration = 0.0;
    public array $stageDurations = [];
    
    public int $processedRecords = 0;
    public int $skippedRecords = 0;
    public int $validationFailures = 0;
    public int $detectedChanges = 0;
    public int $generatedBatches = 0;
}
