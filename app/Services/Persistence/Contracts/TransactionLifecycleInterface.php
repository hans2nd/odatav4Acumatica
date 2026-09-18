<?php

namespace App\Services\Persistence\Contracts;

use App\Services\Persistence\TransactionContext;
use App\Services\Persistence\TransactionResult;
use Throwable;

interface TransactionLifecycleInterface
{
    public function beforeTransaction(TransactionContext $context): void;
    public function afterTransaction(TransactionResult $result, TransactionContext $context): void;
    public function beforeCommit(TransactionContext $context): void;
    public function afterCommit(TransactionContext $context): void;
    public function beforeRollback(TransactionContext $context): void;
    public function afterRollback(TransactionContext $context): void;
    public function onTransactionFailure(Throwable $exception, TransactionContext $context): void;
}
