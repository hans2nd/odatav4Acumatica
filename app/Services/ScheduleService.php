<?php

namespace App\Services;

use App\Models\SyncSchedule;
use App\Models\SyncJob;
use App\Jobs\SyncEntityJob;
use App\DTOs\ScheduleData;
use Illuminate\Contracts\Bus\Dispatcher;
use Exception;
use InvalidArgumentException;

class ScheduleService
{
    public function __construct(
        private CronBuilder $cronBuilder,
        private EntityConfigService $configService,
        private Dispatcher $dispatcher
    ) {}

    public function getAllSchedules(): \Illuminate\Database\Eloquent\Collection
    {
        $entities = $this->configService->getAll();
        $schedules = SyncSchedule::all()->keyBy('entity_key');

        foreach ($entities as $key => $config) {
            if (!$schedules->has($key)) {
                $schedules->put($key, new SyncSchedule([
                    'entity_key' => $key,
                    'is_active' => false,
                    'cron_expression' => '0 0 * * *' // Default daily
                ]));
            }
        }

        return $schedules->values();
    }

    public function updateSchedule(string $entityKey, ScheduleData $data): SyncSchedule
    {
        if ($data->preset) {
            $cron = $this->cronBuilder->fromPreset(
                $data->preset,
                $data->time,
                $data->dayOfWeek,
                $data->dayOfMonth,
                $data->customCron
            );
        } else {
            $cron = $data->cronExpression ?? '0 0 * * *';
        }

        if (!$this->cronBuilder->validate($cron)) {
            throw new InvalidArgumentException("Invalid cron expression generated: {$cron}");
        }

        $schedule = SyncSchedule::updateOrCreate(
            ['entity_key' => $entityKey],
            [
                'cron_expression' => $cron,
                'is_active' => $data->isActive,
            ]
        );

        return $schedule;
    }

    public function toggleStatus(string $entityKey, bool $isActive): SyncSchedule
    {
        $schedule = SyncSchedule::firstOrCreate(
            ['entity_key' => $entityKey],
            ['cron_expression' => '0 0 * * *']
        );

        $schedule->update(['is_active' => $isActive]);

        return $schedule;
    }

    public function getSchedule(string $entityKey): SyncSchedule
    {
        return SyncSchedule::firstOrCreate(
            ['entity_key' => $entityKey],
            ['cron_expression' => '0 0 * * *']
        );
    }

    public function runNow(string $entityKey): SyncJob
    {
        if (!$this->configService->exists($entityKey)) {
            throw new InvalidArgumentException("Unknown entity: {$entityKey}");
        }
        
        // Ensure no pending/running jobs exist for this entity to prevent duplicate overlaps
        $activeJob = SyncJob::where('entity', $entityKey)
            ->whereIn('status', [SyncJob::STATUS_PENDING, SyncJob::STATUS_RUNNING])
            ->first();

        if ($activeJob) {
            throw new Exception("Entity is already running.");
        }

        $jobRecord = SyncJob::create([
            'entity' => $entityKey,
            'status' => SyncJob::STATUS_PENDING,
            'progress' => 0,
        ]);

        // Dispatch via injected dispatcher for perfect testability
        $this->dispatcher->dispatch(new SyncEntityJob($entityKey, [], 'manual', $jobRecord->id));

        return $jobRecord;
    }
}
