<?php

namespace App\Services\Reliability\Exceptions;

use RuntimeException;

class FailureClassificationException extends RuntimeException
{
    // Eksepsi jika klasifikasi itu sendiri mengalami kegagalan sistemik
}
