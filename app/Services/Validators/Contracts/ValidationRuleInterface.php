<?php

namespace App\Services\Validators\Contracts;

use App\Services\Validators\ValidationContext;
use App\Services\Validators\ValidationIssue;

interface ValidationRuleInterface
{
    /**
     * Mengevaluasi konteks eksekusi terhadap sebuah aturan validasi spesifik.
     * Mengembalikan objek ValidationIssue jika ditemukan pelanggaran, atau null jika lolos.
     * 
     * @param ValidationContext $context
     * @return ValidationIssue|null
     */
    public function validate(ValidationContext $context): ?ValidationIssue;
}
