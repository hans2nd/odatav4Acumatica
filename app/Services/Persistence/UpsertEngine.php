<?php

namespace App\Services\Persistence;

use App\Services\Persistence\Contracts\UpsertEngineInterface;
use App\Services\Persistence\Contracts\ConflictKeyResolverInterface;
use App\Services\Persistence\Contracts\UpdateFieldResolverInterface;
use App\Services\Persistence\Exceptions\UpsertExecutionException;
use Throwable;

class UpsertEngine implements UpsertEngineInterface
{
    public function __construct(
        private readonly PersistenceAdapterResolver $adapterResolver,
        private readonly ConflictKeyResolverInterface $conflictKeyResolver,
        private readonly UpdateFieldResolverInterface $updateFieldResolver
    ) {}

    public function upsert(UpsertRequest $request, UpsertContext $context): UpsertResult
    {
        $startTime = microtime(true);
        $statistics = new UpsertStatistics();
        
        if (empty($request->records)) {
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);
            return new UpsertResult(true, $statistics);
        }

        try {
            // 1. Resolve Adapter
            $adapter = $this->adapterResolver->resolve($context->connectionName);

            // 2. Resolve Conflict Keys
            $conflictKeys = $this->conflictKeyResolver->resolve($context);

            // 3. Resolve Update Fields
            $updateFields = $this->updateFieldResolver->resolve($request->records, $conflictKeys, $context);

            // 4. Membangun PersistenceRequest yang sudah diperkaya 
            // (Kita bisa injeksi metadata Upsert di dalam payload agar kelak Adapter bisa memakainya)
            $enrichedRecords = [
                'metadata' => [
                    'conflictKeys' => $conflictKeys,
                    'updateFields' => $updateFields,
                ],
                'data' => $request->records
            ];

            $persistenceRequest = new PersistenceRequest('UPSERT', $enrichedRecords);

            $persistenceContext = new PersistenceContext(
                $context->connectionName,
                $context->tableName,
                $context->primaryKeys
            );

            // 5. Delegasi Kueri ke Adapter
            $response = $adapter->execute($persistenceRequest, $persistenceContext);

            // 6. Mengumpulkan metrik statistik
            $statistics->totalProcessed = count($request->records);
            $statistics->totalInsertedOrUpdated = $response->totalAffectedRows;

            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);

            return new UpsertResult(true, $statistics);

        } catch (Throwable $e) {
            $statistics->totalProcessed = count($request->records);
            $statistics->totalFailed = count($request->records);
            $statistics->durationSeconds = round(microtime(true) - $startTime, 4);

            throw new UpsertExecutionException(
                "Kegagalan total saat eksekusi Upsert: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
