<?php

namespace App\Services\Persistence\Exceptions;

use RuntimeException;

class DeleteExecutionException extends RuntimeException
{
    // Induk eksepsi khusus untuk kegagalan eksekusi kebijakan Delete.
}
