<?php

declare(strict_types=1);

namespace App\DTO\Connections;

final readonly class ConnectionError
{
    public function __construct(
        public string $code,
        public string $title,
        public string $message,
        public string $technical_message,
        public ?string $sql_state = null,
        public ?string $native_error_code = null
    ) {}

    public function toArray(): array
    {
        return [
            'code'              => $this->code,
            'title'             => $this->title,
            'message'           => $this->message,
            'technical_message' => $this->technical_message,
            'sql_state'         => $this->sql_state,
            'native_error_code' => $this->native_error_code,
        ];
    }
}
