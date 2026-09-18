<?php

namespace App\Services\Persistence\Strategies;

use App\Services\Persistence\Contracts\DeleteStrategyInterface;
use App\Services\Persistence\DeleteExecutionContext;
use App\Services\Persistence\DeleteRequest;
use App\Services\Persistence\DeleteResult;
use App\Services\Persistence\DeletePolicy;
use App\Services\Persistence\DeleteStatistics;
use App\Services\Persistence\PersistenceAdapterResolver;
use App\Services\Persistence\PersistenceRequest;
use App\Services\Persistence\PersistenceContext;
use App\Services\Persistence\Exceptions\DeleteExecutionException;
use Throwable;

class SoftDeleteStrategy implements DeleteStrategyInterface
{
    public function __construct(
        private readonly PersistenceAdapterResolver $adapterResolver
    ) {}

    public function getName(): string
    {
        return DeletePolicy::SOFT;
    }

    public function execute(DeleteRequest $request, DeleteExecutionContext $context): DeleteResult
    {
        $startTime = microtime(true);
        $statistics = new DeleteStatistics();
        $statistics->totalProcessed = count($request->records);

        if ($context->isProtected) {
            $statistics->totalIgnored = count($request->records);
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);
            return new DeleteResult(true, $statistics);
        }

        try {
            $adapter = $this->adapterResolver->resolve($context->connectionName);
            
            // Sertakan aturan field yang harus dimodifikasi (misal: deleted_at) pada metadata
            $enrichedData = [
                'metadata' => [
                    'softDeleteFields' => $context->softDeleteFields
                ],
                'data' => $request->records
            ];

            $persistenceRequest = new PersistenceRequest('SOFT_DELETE', $enrichedData);
            
            $persistenceContext = new PersistenceContext(
                $context->connectionName,
                $context->tableName,
                $context->primaryKeys
            );

            $response = $adapter->execute($persistenceRequest, $persistenceContext);

            $statistics->totalDeleted = $response->totalAffectedRows;
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);

            return new DeleteResult(true, $statistics);

        } catch (Throwable $e) {
            $statistics->totalFailed = count($request->records);
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);
            
            throw new DeleteExecutionException(
                "Kegagalan eksekusi Soft Delete: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
