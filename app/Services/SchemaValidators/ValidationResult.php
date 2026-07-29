<?php

namespace App\Services\SchemaValidators;

class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $issues,
        public readonly float $durationSeconds
    ) {}

    public function getErrors(): array
    {
        return array_filter($this->issues, fn(ValidationIssue $issue) => $issue->level === 'error');
    }

    public function getWarnings(): array
    {
        return array_filter($this->issues, fn(ValidationIssue $issue) => $issue->level === 'warning');
    }
}
