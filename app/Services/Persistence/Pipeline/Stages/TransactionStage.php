<?php

namespace App\Services\Persistence\Pipeline\Stages;

use App\Services\Persistence\Contracts\PersistenceStageInterface;
use App\Services\Persistence\Pipeline\PersistencePipelineExecutionContext;
use App\Services\Persistence\Contracts\TransactionManagerInterface;
use App\Services\Persistence\TransactionRequest;
use App\Services\Persistence\TransactionContext;
use Closure;

class TransactionStage implements PersistenceStageInterface
{
    public function __construct(
        private readonly TransactionManagerInterface $transactionManager
    ) {}

    public function handle(PersistencePipelineExecutionContext $context, Closure $next): PersistencePipelineExecutionContext
    {
        $transactionContext = new TransactionContext(
            $context->originalContext->connectionName
        );

        // Bungkus semua eksekusi tahapan ($next) di bawah lindungan transaksi
        $request = new TransactionRequest(function() use ($context, $next) {
            return $next($context);
        });

        $result = $this->transactionManager->execute($request, $transactionContext);

        // Agregasi Metrik Transaksi
        $context->metrics->transactionCount++;
        if ($result->isSuccess) {
            $context->metrics->commitCount++;
        } else {
            $context->metrics->rollbackCount++;
        }

        // Jika fungsi transaksional mengembalikan konteks akhir
        if ($result->isSuccess && $result->payload instanceof PersistencePipelineExecutionContext) {
            return $result->payload;
        }

        return $context;
    }
}
