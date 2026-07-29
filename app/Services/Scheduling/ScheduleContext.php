<?php

namespace App\Services\Scheduling;

class ScheduleContext
{
    /**
     * @param string $lastExecutionTimestamp Kapan terakhir kali sinkronisasi ini berjalan sukses
     * @param bool $isRunning Apakah sinkronisasi ini sedang berstatus RUNNING saat ini (mencegah duplikat)
     * @param array $additionalContext Konteks tambahan
     */
    public function __construct(
        public readonly ?string $lastExecutionTimestamp = null,
        public readonly bool $isRunning = false,
        public readonly array $additionalContext = []
    ) {}
}
