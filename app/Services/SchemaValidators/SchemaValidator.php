<?php

namespace App\Services\SchemaValidators;

use App\Services\SchemaValidators\Contracts\SchemaValidatorInterface;
use App\Services\SchemaValidators\ValidationRuleRegistry;

class SchemaValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidationRuleRegistry $registry
    ) {}

    public function validate(ValidationContext $context): ValidationResult
    {
        $startTime = microtime(true);
        $issues = [];

        foreach ($context->records as $record) {
            foreach ($context->profile->fieldRules as $field => $rulesConfig) {
                // rulesConfig => ['required' => [], 'string_length' => ['max' => 50]]
                $value = $record->get($field);

                foreach ($rulesConfig as $ruleName => $ruleOptions) {
                    if ($this->registry->has($ruleName)) {
                        $rule = $this->registry->get($ruleName);
                        $errorMessage = $rule->validate($field, $value, $ruleOptions);
                        
                        if ($errorMessage !== null) {
                            $issues[] = new ValidationIssue(
                                'error', 
                                $field, 
                                $ruleName, 
                                $errorMessage
                            );
                        }
                    } else {
                        // Warn if a rule is defined but not registered
                        $issues[] = new ValidationIssue(
                            'warning',
                            $field,
                            $ruleName,
                            "Aturan validasi '{$ruleName}' tidak ditemukan di Registry."
                        );
                    }
                }
            }
        }

        $duration = round(microtime(true) - $startTime, 4);
        
        $errors = array_filter($issues, fn($issue) => $issue->level === 'error');
        $isValid = empty($errors);

        return new ValidationResult($isValid, $issues, $duration);
    }
}
