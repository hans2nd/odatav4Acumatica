<?php

declare(strict_types=1);

namespace App\DTO\Connections;

final readonly class ProviderInfo
{
    public function __construct(
        public string $name,
        public string $driverVersion = 'Unknown',
        public string $serverVersion = 'Unknown',
        public string $protocolVersion = 'Unknown',
        public array $additionalInfo = []
    ) {}
}
