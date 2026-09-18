<?php

namespace App\Services\Reliability\Recovery;

class ResumeRequest
{
    /**
     * @param string $jobId ID Unik sinkronisasi/pekerjaan yang akan dipulihkan
     */
    public function __construct(
        public readonly string $jobId
    ) {}
}
