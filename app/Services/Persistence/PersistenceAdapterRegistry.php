<?php

namespace App\Services\Persistence;

use App\Services\Persistence\Contracts\PersistenceAdapterInterface;
use InvalidArgumentException;

class PersistenceAdapterRegistry
{
    /**
     * @var array<string, PersistenceAdapterInterface>
     */
    private array $adapters = [];

    public function register(string $driverName, PersistenceAdapterInterface $adapter): void
    {
        $this->adapters[$driverName] = $adapter;
    }

    public function get(string $driverName): PersistenceAdapterInterface
    {
        if (!isset($this->adapters[$driverName])) {
            throw new InvalidArgumentException("Adapter persistensi tidak ditemukan untuk driver: {$driverName}");
        }

        return $this->adapters[$driverName];
    }

    public function has(string $driverName): bool
    {
        return isset($this->adapters[$driverName]);
    }
}
