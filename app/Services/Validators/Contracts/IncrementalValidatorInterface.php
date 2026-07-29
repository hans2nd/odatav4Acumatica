<?php

namespace App\Services\Validators\Contracts;

use App\Services\Validators\ValidationContext;
use App\Services\Validators\ValidationResult;

interface IncrementalValidatorInterface
{
    /**
     * Mendaftarkan aturan validasi baru.
     * 
     * @param ValidationRuleInterface $rule Aturan yang mengimplementasikan logika spesifik.
     */
    public function addRule(ValidationRuleInterface $rule): void;

    /**
     * Mengevaluasi seluruh aturan yang terdaftar terhadap konteks eksekusi.
     * 
     * @param ValidationContext $context
     * @return ValidationResult Himpunan hasil validasi (termasuk kumpulan isu jika tidak valid).
     */
    public function validate(ValidationContext $context): ValidationResult;
}
