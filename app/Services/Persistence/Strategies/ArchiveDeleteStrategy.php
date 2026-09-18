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

class ArchiveDeleteStrategy implements DeleteStrategyInterface
{
    public function __construct(
        private readonly PersistenceAdapterResolver $adapterResolver
    ) {}

    public function getName(): string
    {
        return DeletePolicy::ARCHIVE;
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
            
            // Berikan informasi tabel archive pada metadata
            $enrichedData = [
                'metadata' => [
                    'archiveTableName' => $context->archiveTableName
                ],
                'data' => $request->records
            ];

            $persistenceRequest = new PersistenceRequest('ARCHIVE_DELETE', $enrichedData);
            
            $persistenceContext = new PersistenceContext(
                $context->connectionName,
                $context->tableName,
                $context->primaryKeys
            );

            // Operasi ini biasanya melibatkan INSERT INTO archive_table SELECT ... lalu DELETE
            $response = $adapter->execute($persistenceRequest, $persistenceContext);

            $statistics->totalDeleted = $response->totalAffectedRows;
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);

            return new DeleteResult(true, $statistics);

        } catch (Throwable $e) {
            $statistics->totalFailed = count($request->records);
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);
            
            throw new DeleteExecutionException(
                "Kegagalan eksekusi Archive Delete: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
