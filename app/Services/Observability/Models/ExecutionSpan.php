<?php

namespace App\Services\Observability\Models;

class ExecutionSpan
{
    /** @var array<ExecutionLog> */
    public array $logs = [];
    
    /** @var array<ExecutionEvent> */
    public array $events = [];

    public ?float $endTime = null;
    public ?float $durationSeconds = null;
    public string $status = 'RUNNING'; // 'RUNNING', 'COMPLETED', 'FAILED'

    public function __construct(
        public readonly string $name,
        public readonly float $startTime
    ) {}

    public function addLog(ExecutionLog $log): void
    {
        $this->logs[] = $log;
    }

    public function addEvent(ExecutionEvent $event): void
    {
        $this->events[] = $event;
    }

    public function end(string $status = 'COMPLETED'): void
    {
        $this->endTime = microtime(true);
        $this->durationSeconds = round($this->endTime - $this->startTime, 4);
        $this->status = $status;
    }
}
