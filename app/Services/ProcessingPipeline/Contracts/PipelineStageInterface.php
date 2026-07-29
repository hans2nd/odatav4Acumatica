<?php

namespace App\Services\ProcessingPipeline\Contracts;

use App\Services\ProcessingPipeline\PipelineContext;
use App\Services\ProcessingPipeline\PipelineMetrics;
use App\Services\ProcessingPipeline\PipelineExecutionContext;

interface PipelineStageInterface
{
    /**
     * Nama identifikasi tahap pita perakitan
     */
    public function getName(): string;

    /**
     * Mengeksekusi mutasi data di tahapan tersebut.
     * @param mixed $payload Objek tak-terubahkan dari tahapan sebelumnya
     * @param PipelineContext $context Pengaturan dan sumber daya
     * @param PipelineExecutionContext $executionContext Pengaturan eksekusi dan Callback
     * @param PipelineMetrics $metrics DTO agregasi
     * @return mixed Hasil akhir yang akan di-oper ke tahap selanjutnya
     */
    public function execute(mixed $payload, PipelineContext $context, PipelineExecutionContext $executionContext, PipelineMetrics $metrics): mixed;
}
