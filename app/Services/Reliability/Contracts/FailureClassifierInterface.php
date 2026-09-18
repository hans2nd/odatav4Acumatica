<?php

namespace App\Services\Reliability\Contracts;

use App\Services\Reliability\Classification\FailureContext;
use App\Services\Reliability\Classification\FailureClassificationResult;
use Throwable;

interface FailureClassifierInterface
{
    /**
     * Mengevaluasi apakah pengklasifikasi ini mengenali dan bisa mendiagnosis eksepsi tersebut.
     */
    public function supports(Throwable $exception): bool;

    /**
     * Mendiagnosis eksepsi dan mengembalikan hasil klasifikasi.
     * Akan dipanggil jika supports() bernilai true.
     */
    public function classify(Throwable $exception, FailureContext $context): FailureClassificationResult;
}
