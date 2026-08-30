<?php

namespace App\Exceptions\AI;

use Exception;

class TableResolutionException extends Exception
{
    public const TYPE_NOT_FOUND = 'not_found';
    public const TYPE_AMBIGUOUS = 'ambiguous';

    protected string $resolutionType;

    public function __construct(string $message, string $resolutionType)
    {
        parent::__construct($message);
        $this->resolutionType = $resolutionType;
    }

    public function getResolutionType(): string
    {
        return $this->resolutionType;
    }
}
