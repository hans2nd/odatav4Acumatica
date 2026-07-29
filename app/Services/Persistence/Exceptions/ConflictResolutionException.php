<?php

namespace App\Services\Persistence\Exceptions;

use RuntimeException;

class ConflictResolutionException extends RuntimeException
{
    // Induk eksepsi khusus untuk resolusi konflik, khususnya dipicu oleh FailFastStrategy
}
