<?php

namespace App\Services\Coordinators;

use App\Services\Coordinators\Contracts\IncrementalExecutionCoordinatorInterface;
use App\Services\Contracts\SyncStateServiceInterface;
use App\Services\Queries\Contracts\IncrementalQueryBuilderInterface;
use App\Services\Readers\Contracts\SourceDataReaderInterface;
use App\Services\Watermarks\Contracts\CompositeWatermarkInterface;
use App\Services\Readers\ReaderContext;
use App\Services\Readers\SourcePage;
use App\Services\Watermarks\WatermarkValue;
use App\Services\Readers\SourceRecordCollection;

class IncrementalExecutionCoordinator implements IncrementalExecutionCoordinatorInterface
{
    public function __construct(
        private readonly SyncStateServiceInterface $stateService,
        private readonly IncrementalQueryBuilderInterface $queryBuilder,
        private readonly SourceDataReaderInterface $reader,
        private readonly CompositeWatermarkInterface $watermarkEngine
    ) {}

    public function execute(ExecutionContext $context, ?SourcePage $page = null): ExecutionResult
    {
        $startTime = microtime(true);

        // 1. Memuat Status Sinkronisasi
        $state = $this->stateService->getSyncState($context->scheduleId);
        $currentWatermark = new WatermarkValue($state->last_timestamp, $state->last_pk);

        // 2. Membangun Kueri Inkremental
        $query = $this->queryBuilder
            ->withCompositeWatermark(
                $context->timestampField,
                $context->primaryKeyField,
                $currentWatermark->timestamp,
                $currentWatermark->primaryKey
            )
            ->withOrdering($context->timestampField, $context->primaryKeyField, 'asc')
            ->withLimit($context->pageSize)
            ->withCustomFilters($context->customFilters)
            ->build();

        // 3. Membaca Sumber Data
        $readerContext = new ReaderContext($context->entityKey, $context->pageSize);
        $sourceResult = $this->reader->execute($query, $readerContext, $page);

        // 4. Resolusi Watermark
        $candidateWatermark = $this->extractHighestWatermark($sourceResult->getRecords(), $context);
        $nextWatermark = $this->watermarkEngine->determineNextCheckpoint($currentWatermark, $candidateWatermark);

        // Validasi kontinuitas (time-travel protection)
        $this->watermarkEngine->validateContinuity($currentWatermark, $nextWatermark);

        $duration = round(microtime(true) - $startTime, 4);
        $statistics = new ExecutionStatistics($sourceResult->getRecords()->count(), $duration);

        // 5. Mengembalikan Hasil
        return new ExecutionResult(
            $sourceResult->getRecords(),
            $nextWatermark,
            $sourceResult->getNextPage(),
            $statistics,
            $sourceResult->hasMoreData()
        );
    }

    private function extractHighestWatermark(SourceRecordCollection $records, ExecutionContext $context): WatermarkValue
    {
        if ($records->isEmpty()) {
            return new WatermarkValue(null, null);
        }

        $highestTs = null;
        $highestPk = null;

        // Karena kueri dijamin ASC, iterasi ini memastikan elemen terakhir adalah watermark tertinggi.
        foreach ($records as $record) {
            $recordArray = (array) $record;
            
            $ts = $recordArray[$context->timestampField] ?? null;
            $pk = $recordArray[$context->primaryKeyField] ?? null;

            if ($ts !== null) {
                $highestTs = (string) $ts;
                $highestPk = $pk !== null ? (string) $pk : null;
            }
        }

        return new WatermarkValue($highestTs, $highestPk);
    }
}
