<?php

namespace App\Services\Persistence\Strategies;

use App\Services\Persistence\Contracts\DeleteStrategyInterface;
use App\Services\Persistence\DeleteExecutionContext;
use App\Services\Persistence\DeleteRequest;
use App\Services\Persistence\DeleteResult;
use App\Services\Persistence\DeletePolicy;
use App\Services\Persistence\DeleteStatistics;

class IgnoreDeleteStrategy implements DeleteStrategyInterface
{
    public function getName(): string
    {
        return DeletePolicy::IGNORE;
    }

    public function execute(DeleteRequest $request, DeleteExecutionContext $context): DeleteResult
    {
        // Strategi Ignore tidak pernah memanggil Adapter sama sekali.
        $startTime = microtime(true);
        
        $statistics = new DeleteStatistics();
        $statistics->totalProcessed = count($request->records);
        $statistics->totalIgnored = count($request->records);
        $statistics->durationSeconds = round(microtime(true) - $startTime, 4);

        return new DeleteResult(true, $statistics);
    }
}
