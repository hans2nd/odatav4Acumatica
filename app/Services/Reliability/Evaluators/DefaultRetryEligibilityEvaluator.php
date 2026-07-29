<?php

namespace App\Services\Reliability\Evaluators;

use App\Services\Reliability\Contracts\RetryEligibilityEvaluatorInterface;
use App\Services\Reliability\RetryContext;
use App\Services\Reliability\RetryPolicy;
use Throwable;

class DefaultRetryEligibilityEvaluator implements RetryEligibilityEvaluatorInterface
{
    private string $rejectionReason = '';

    public function isEligible(Throwable $exception, int $currentAttempt, RetryContext $context): bool
    {
        $this->rejectionReason = '';

        if ($context->policy === RetryPolicy::NO_RETRY) {
            $this->rejectionReason = 'Kebijakan ditetapkan ke NO_RETRY.';
            return false;
        }

        if ($context->policy !== RetryPolicy::INFINITE && $currentAttempt >= $context->maxAttempts) {
            $this->rejectionReason = "Batas maksimum percobaan ulang telah tercapai ({$context->maxAttempts} attempts).";
            return false;
        }

        // Idealnya, kita mendeteksi tipe eksepsi khusus DB transien (Deadlock, Timeout)
        // Jika menemukan SyntaxError atau Fatal Exception non-transien, return false.
        // Di sini kita asumsikan semua Exception adalah transien untuk keperluan demonstrasi
        
        // Contoh implementasi di dunia nyata:
        // if ($exception instanceof \PDOException && in_array($exception->getCode(), ['HY000', '40001'])) { return true; }
        // else { $this->rejectionReason = 'Eksepsi bersifat Non-Transien (Fatal).'; return false; }

        return true;
    }

    public function getRejectionReason(): string
    {
        return $this->rejectionReason;
    }
}
