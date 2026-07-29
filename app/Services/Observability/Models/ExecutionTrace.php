<?php

namespace App\Services\Observability\Models;

class ExecutionTrace
{
    /** @var array<string, ExecutionSpan> */
    public array $spans = [];

    public ?float $endTime = null;
    public ?float $durationSeconds = null;

    public function __construct(
        public readonly CorrelationId $correlationId,
        public readonly float $startTime
    ) {}

    public function addSpan(ExecutionSpan $span): void
    {
        $this->spans[$span->name] = $span;
    }

    public function end(): void
    {
        $this->endTime = microtime(true);
        $this->durationSeconds = round($this->endTime - $this->startTime, 4);
    }
}
