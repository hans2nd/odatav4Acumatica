<?php

namespace App\Services\Persistence\Pipeline\Stages;

use App\Services\Persistence\Contracts\PersistenceStageInterface;
use App\Services\Persistence\Pipeline\PersistencePipelineExecutionContext;
use App\Services\Persistence\DeleteStrategyResolver;
use App\Services\Persistence\DeleteRequest;
use App\Services\Persistence\DeleteExecutionContext;
use Closure;

class DeleteStage implements PersistenceStageInterface
{
    public function __construct(
        private readonly DeleteStrategyResolver $deleteResolver
    ) {}

    public function handle(PersistencePipelineExecutionContext $context, Closure $next): PersistencePipelineExecutionContext
    {
        if (!empty($context->deleteRecords)) {
            $deleteRequest = new DeleteRequest($context->deleteRecords);
            
            $policy = $context->originalContext->deletePolicy;
            $strategy = $this->deleteResolver->resolve($policy);

            $deleteContext = new DeleteExecutionContext(
                $context->originalContext->connectionName,
                $context->originalContext->tableName,
                $context->originalContext->primaryKeys,
                $policy
            );

            $result = $strategy->execute($deleteRequest, $deleteContext);

            // Agregasi metrik
            $context->metrics->totalProcessed += $result->statistics->totalProcessed;
            $context->metrics->totalDeleted += $result->statistics->totalDeleted;
            $context->metrics->totalSkipped += $result->statistics->totalIgnored;
            $context->metrics->totalFailed += $result->statistics->totalFailed;
        }

        return $next($context);
    }
}
