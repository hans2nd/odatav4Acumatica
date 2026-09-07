<?php

namespace App\Exceptions\AI;

use Exception;

class SemanticNotFoundException extends Exception
{
    protected $term;
    protected $type;

    /**
     * @param string $message The error message
     * @param string $term The term that was not found
     * @param string $type The type of term ('dimension' or 'metric')
     */
    public function __construct(string $message, string $term = '', string $type = 'dimension')
    {
        parent::__construct($message);
        $this->term = $term;
        $this->type = $type;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
