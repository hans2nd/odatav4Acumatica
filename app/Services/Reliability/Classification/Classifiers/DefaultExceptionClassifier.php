<?php

namespace App\Services\Reliability\Classification\Classifiers;

use App\Services\Reliability\Contracts\FailureClassifierInterface;
use App\Services\Reliability\Classification\FailureContext;
use App\Services\Reliability\Classification\FailureClassificationResult;
use App\Services\Reliability\Classification\FailureDiagnostic;
use App\Services\Reliability\Classification\Enums\FailureCategory;
use App\Services\Reliability\Classification\Enums\FailureSeverity;
use App\Services\Reliability\Classification\Enums\RecoveryHint;
use Throwable;
use PDOException;
use InvalidArgumentException;

class DefaultExceptionClassifier implements FailureClassifierInterface
{
    public function supports(Throwable $exception): bool
    {
        // Sebagai classifier default, ia mampu mendukung standar Exception umum di PHP
        return true; 
    }

    public function classify(Throwable $exception, FailureContext $context): FailureClassificationResult
    {
        $diagnostic = new FailureDiagnostic(
            $exception->getMessage(),
            get_class($exception),
            $exception->getCode(),
            $exception->getFile(),
            $exception->getLine()
        );

        if ($exception instanceof PDOException) {
            return $this->classifyDatabaseException($exception, $diagnostic);
        }

        if ($exception instanceof InvalidArgumentException) {
            return new FailureClassificationResult(
                FailureCategory::VALIDATION,
                FailureSeverity::WARNING,
                RecoveryHint::SKIP,
                $diagnostic
            );
        }

        // Kategori tidak terdefinisi secara khusus
        return new FailureClassificationResult(
            FailureCategory::UNKNOWN,
            FailureSeverity::CRITICAL,
            RecoveryHint::MANUAL_INTERVENTION,
            $diagnostic
        );
    }

    private function classifyDatabaseException(PDOException $e, FailureDiagnostic $diagnostic): FailureClassificationResult
    {
        // Contoh sederhana klasifikasi deadlock/timeout MySQL. (Error 1213: Deadlock, 1205: Lock wait timeout)
        $code = is_numeric($e->getCode()) ? (int)$e->getCode() : 0;
        
        // Di PDO, kode SQLSTATE terkadang ada di eInfo.
        $sqlState = $e->errorInfo[0] ?? $e->getCode();

        if ($sqlState === '40001' || $code === 1213 || $code === 1205) {
            return new FailureClassificationResult(
                FailureCategory::DATABASE,
                FailureSeverity::RECOVERABLE,
                RecoveryHint::RETRY,
                $diagnostic
            );
        }

        // Misal kesalahan sintaks (42000) atau tabel tidak ditemukan
        if ($sqlState === '42S02' || $sqlState === '42000') {
            return new FailureClassificationResult(
                FailureCategory::DATABASE,
                FailureSeverity::FATAL,
                RecoveryHint::ABORT,
                $diagnostic
            );
        }

        return new FailureClassificationResult(
            FailureCategory::DATABASE,
            FailureSeverity::CRITICAL,
            RecoveryHint::ESCALATION,
            $diagnostic
        );
    }
}
