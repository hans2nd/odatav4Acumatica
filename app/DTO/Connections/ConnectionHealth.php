<?php

declare(strict_types=1);

namespace App\DTO\Connections;

enum ConnectionHealthStatus: string
{
    case HEALTHY = 'healthy';
    case WARNING = 'warning';
    case DISCONNECTED = 'disconnected';
    case ERROR_DRIVER_MISSING = 'error_driver_missing';
    case ERROR_AUTH_FAILED = 'error_auth_failed';
    case ERROR_UNREACHABLE = 'error_unreachable';
}

final readonly class ConnectionHealth
{
    public function __construct(
        public ConnectionHealthStatus $status,
        public string $message = '',
        public ?int $pingMs = null,
        public array $checks = [],
        public array $metrics = [],
        public array $summary = [],
        public ?ConnectionError $error = null
    ) {}
}
