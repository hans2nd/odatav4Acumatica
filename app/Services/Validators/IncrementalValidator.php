<?php

namespace App\Services\Validators;

use App\Services\Validators\Contracts\IncrementalValidatorInterface;
use App\Services\Validators\Contracts\ValidationRuleInterface;

class IncrementalValidator implements IncrementalValidatorInterface
{
    /**
     * @var array<ValidationRuleInterface>
     */
    private array $rules = [];

    public function addRule(ValidationRuleInterface $rule): void
    {
        $this->rules[] = $rule;
    }

    public function validate(ValidationContext $context): ValidationResult
    {
        $issues = [];

        foreach ($this->rules as $rule) {
            $issue = $rule->validate($context);
            if ($issue !== null) {
                $issues[] = $issue;
            }
        }

        $isValid = empty($issues);

        return new ValidationResult($isValid, $issues);
    }
}
