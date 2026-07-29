<?php

namespace App\Services\Persistence;

use App\Services\Persistence\Contracts\PersistenceAdapterInterface;

class PersistenceAdapterResolver
{
    public function __construct(
        private readonly PersistenceAdapterRegistry $registry
    ) {}

    /**
     * Mengambil adapter yang sesuai dengan nama driver koneksi.
     */
    public function resolve(string $driver): PersistenceAdapterInterface
    {
        return $this->registry->get($driver);
    }
}
