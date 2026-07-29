<?php

namespace App\Services\Orchestration;

class SynchronizationSummary
{
    public string $finalMessage = '';
    public array $stageResults = [];
    public array $errors = [];
}
