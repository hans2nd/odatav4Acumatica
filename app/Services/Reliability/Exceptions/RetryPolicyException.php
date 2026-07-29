<?php

namespace App\Services\Reliability\Exceptions;

use RuntimeException;

class RetryPolicyException extends RuntimeException
{
    // Induk eksepsi khusus untuk kegagalan pada Retry Policy Engine
}
