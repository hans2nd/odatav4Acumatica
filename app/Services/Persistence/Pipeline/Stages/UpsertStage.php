<?php

namespace App\Services\Persistence\Pipeline\Stages;

use App\Services\Persistence\Contracts\PersistenceStageInterface;
use App\Services\Persistence\Pipeline\PersistencePipelineExecutionContext;
use App\Services\Persistence\Contracts\UpsertEngineInterface;
use App\Services\Persistence\UpsertRequest;
use App\Services\Persistence\UpsertContext;
use Closure;

class UpsertStage implements PersistenceStageInterface
{
    public function __construct(
        private readonly UpsertEngineInterface $upsertEngine
    ) {}

    public function handle(PersistencePipelineExecutionContext $context, Closure $next): PersistencePipelineExecutionContext
    {
        if (!empty($context->upsertRecords)) {
            $upsertRequest = new UpsertRequest($context->upsertRecords);
            $upsertContext = new UpsertContext(
                $context->originalContext->connectionName,
                $context->originalContext->tableName,
                $context->originalContext->primaryKeys
            );

            $result = $this->upsertEngine->upsert($upsertRequest, $upsertContext);

            // Agregasi metrik
            $context->metrics->totalProcessed += $result->statistics->totalProcessed;
            $context->metrics->totalInsertedOrUpdated += $result->statistics->totalInsertedOrUpdated;
            $context->metrics->totalFailed += $result->statistics->totalFailed;
        }

        return $next($context);
    }
}
