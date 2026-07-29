<?php

namespace App\Services\Scheduling;

class ScheduleExecutionSummary
{
    /** @var string Penjelasan atau alasan penjadwalan (Misal: "Skipped: Already Running") */
    public string $reason = '';

    /** @var array<string, mixed> Metadata tambahan saat proses dijadwalkan */
    public array $metadata = [];
}
