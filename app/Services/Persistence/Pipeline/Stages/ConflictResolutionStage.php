<?php

namespace App\Services\Persistence\Pipeline\Stages;

use App\Services\Persistence\Contracts\PersistenceStageInterface;
use App\Services\Persistence\Pipeline\PersistencePipelineExecutionContext;
use App\Services\Persistence\Contracts\ConflictResolutionEngineInterface;
use App\Services\Persistence\ConflictResolutionRequest;
use App\Services\Persistence\ConflictResolutionContext;
use Closure;

class ConflictResolutionStage implements PersistenceStageInterface
{
    public function __construct(
        private readonly ConflictResolutionEngineInterface $conflictEngine
    ) {}

    public function handle(PersistencePipelineExecutionContext $context, Closure $next): PersistencePipelineExecutionContext
    {
        if (empty($context->upsertRecords)) {
            return $next($context);
        }

        // Contoh skenario: Di sini kita asumsikan upsertRecords mungkin memiliki pasangan tujuan 
        // Jika belum ada destinationRecord di payload, ConflictResolution bisa digunakan nanti saat 
        // sinkronisasi 2 arah berjalan. Sementara ini, anggaplah $context->upsertRecords adalah $sourceRecords.

        $resolvedRecords = [];
        $policy = $context->originalContext->conflictPolicy;
        
        $conflictContext = new ConflictResolutionContext(
            $context->originalContext->tableName,
            $policy
        );

        foreach ($context->upsertRecords as $record) {
            // Ini disederhanakan. Pada praktiknya, data existing dari DB akan diambil terlebih dahulu.
            // Untuk mematuhi Non-Goals (Dilarang membaca Source / DB), kita anggap existingRecord = null
            // Ini akan memicu insert otomatis atau resolve lewat strategi spesifik.
            
            $existingRecord = null; 

            $request = new ConflictResolutionRequest((array) $record, $existingRecord, 'PRIMARY_KEY_CONFLICT');
            $result = $this->conflictEngine->resolve($request, $conflictContext);

            $context->metrics->conflictCount++;

            if ($result->decision === 'SKIPPED') {
                $context->metrics->totalSkipped++;
            } else if ($result->resolvedRecord !== null) {
                $resolvedRecords[] = $result->resolvedRecord;
            }
        }

        // Tulis ulang daftar rekaman ke konteks untuk dilanjutkan ke UpsertStage
        $context->upsertRecords = $resolvedRecords;

        return $next($context);
    }
}
