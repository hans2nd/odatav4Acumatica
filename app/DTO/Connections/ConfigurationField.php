<?php

declare(strict_types=1);

namespace App\DTO\Connections;

final readonly class ConfigurationField
{
    public function __construct(
        public string $field,
        public string $label,
        public string $type = 'text', // text, number, password, boolean, select
        public bool $required = false,
        public mixed $default = null,
        public ?string $placeholder = null,
        public ?string $helpText = null,
        public array $options = [] // for select type
    ) {}
}
