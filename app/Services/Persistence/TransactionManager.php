<?php

namespace App\Services\Persistence;

use App\Services\Persistence\Contracts\TransactionManagerInterface;
use App\Services\Persistence\Contracts\SavepointManagerInterface;
use App\Services\Persistence\Contracts\TransactionLifecycleInterface;
use App\Services\Persistence\Exceptions\TransactionException;
use Throwable;

class TransactionManager implements TransactionManagerInterface
{
    /** @var array<TransactionLifecycleInterface> */
    private array $lifecycles = [];

    /** @var array<string, int> Tracking nesting level per connection */
    private array $activeTransactions = [];

    public function __construct(
        private readonly PersistenceAdapterResolver $adapterResolver,
        private readonly SavepointManagerInterface $savepointManager
    ) {}

    public function addLifecycle(TransactionLifecycleInterface $lifecycle): void
    {
        $this->lifecycles[] = $lifecycle;
    }

    public function execute(TransactionRequest $request, TransactionContext $context): TransactionResult
    {
        $startTime = microtime(true);
        $connectionName = $context->connectionName;
        $statistics = new TransactionStatistics();

        // Level nesting untuk mendukung Nested Transactions
        $currentNesting = $this->activeTransactions[$connectionName] ?? 0;
        $this->activeTransactions[$connectionName] = $currentNesting + 1;
        $statistics->nestedLevels = $this->activeTransactions[$connectionName];

        $savepointName = null;

        try {
            if ($currentNesting === 0) {
                // Not in transaction, BEGIN
                $this->triggerLifecycles('beforeTransaction', $context);
                $this->sendTransactionCommand($connectionName, 'BEGIN_TRANSACTION', $context->isolationLevel);
            } else {
                // Already in transaction, create SAVEPOINT
                $savepointName = "sp_level_{$this->activeTransactions[$connectionName]}";
                $this->savepointManager->createSavepoint($connectionName, $savepointName);
                $statistics->savepointsCreated++;
            }

            // Execute the operation
            $payload = ($request->operation)();

            if ($currentNesting === 0) {
                // Root transaction komit
                $this->triggerLifecycles('beforeCommit', $context);
                $this->sendTransactionCommand($connectionName, 'COMMIT_TRANSACTION');
                $this->triggerLifecycles('afterCommit', $context);
            } else {
                // Release savepoint if supported/needed
                if ($savepointName) {
                    $this->savepointManager->releaseSavepoint($connectionName, $savepointName);
                }
            }

            $this->activeTransactions[$connectionName]--;
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);
            $result = new TransactionResult(true, $payload, $statistics);
            
            if ($currentNesting === 0) {
                $this->triggerLifecycles('afterTransaction', $result, $context);
            }

            return $result;

        } catch (Throwable $e) {
            $statistics->rolledBack = true;
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);
            
            try {
                if ($currentNesting === 0) {
                    $this->triggerLifecycles('beforeRollback', $context);
                    $this->sendTransactionCommand($connectionName, 'ROLLBACK_TRANSACTION');
                    $this->triggerLifecycles('afterRollback', $context);
                } else if ($savepointName) {
                    $this->savepointManager->rollbackToSavepoint($connectionName, $savepointName);
                }
            } catch (Throwable $rollbackException) {
                // Abaikan kesalahan rollback untuk melemparkan eksepsi utama
            }

            $this->activeTransactions[$connectionName]--;

            $this->triggerLifecycles('onTransactionFailure', $e, $context);

            $result = new TransactionResult(false, null, $statistics, $e);
            
            if ($currentNesting === 0) {
                $this->triggerLifecycles('afterTransaction', $result, $context);
            }

            throw new TransactionException("Kesalahan Transaksi: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    private function triggerLifecycles(string $method, ...$args): void
    {
        foreach ($this->lifecycles as $lifecycle) {
            if (method_exists($lifecycle, $method)) {
                $lifecycle->$method(...$args);
            }
        }
    }

    private function sendTransactionCommand(string $connectionName, string $operation, ?string $isolationLevel = null): void
    {
        $adapter = $this->adapterResolver->resolve($connectionName);
        $request = new PersistenceRequest($operation, ['isolationLevel' => $isolationLevel]);
        $context = new PersistenceContext($connectionName, '');
        $adapter->execute($request, $context);
    }
}
