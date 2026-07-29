<?php

namespace App\Services\Persistence;

use App\Services\Persistence\Contracts\SavepointManagerInterface;

class SavepointManager implements SavepointManagerInterface
{
    public function __construct(
        private readonly PersistenceAdapterResolver $adapterResolver
    ) {}

    public function createSavepoint(string $connectionName, string $savepointName): void
    {
        $adapter = $this->adapterResolver->resolve($connectionName);
        $request = new PersistenceRequest('CREATE_SAVEPOINT', ['name' => $savepointName]);
        $context = new PersistenceContext($connectionName, '');
        $adapter->execute($request, $context);
    }

    public function rollbackToSavepoint(string $connectionName, string $savepointName): void
    {
        $adapter = $this->adapterResolver->resolve($connectionName);
        $request = new PersistenceRequest('ROLLBACK_SAVEPOINT', ['name' => $savepointName]);
        $context = new PersistenceContext($connectionName, '');
        $adapter->execute($request, $context);
    }

    public function releaseSavepoint(string $connectionName, string $savepointName): void
    {
        $adapter = $this->adapterResolver->resolve($connectionName);
        $request = new PersistenceRequest('RELEASE_SAVEPOINT', ['name' => $savepointName]);
        $context = new PersistenceContext($connectionName, '');
        $adapter->execute($request, $context);
    }
}
