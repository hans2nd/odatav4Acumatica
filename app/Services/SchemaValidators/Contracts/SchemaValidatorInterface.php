<?php

namespace App\Services\SchemaValidators\Contracts;

use App\Services\SchemaValidators\ValidationContext;
use App\Services\SchemaValidators\ValidationResult;

interface SchemaValidatorInterface
{
    /**
     * Memvalidasi sekumpulan rekaman data yang telah dinormalisasi menggunakan profil tertentu.
     * 
     * @param ValidationContext $context
     * @return ValidationResult
     */
    public function validate(ValidationContext $context): ValidationResult;
}
