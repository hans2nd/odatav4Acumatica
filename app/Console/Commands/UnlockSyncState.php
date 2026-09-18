<?php

namespace App\Console\Commands;

use App\Services\Contracts\SyncStateServiceInterface;
use Illuminate\Console\Command;
use App\Models\SyncSchedule;

class UnlockSyncState extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:unlock 
                            {scheduleId? : The ID of the schedule to unlock} 
                            {--all : Unlock all stuck schedules}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Releases a stuck synchronization lock for a specific schedule ID or all schedules';

    /**
     * Execute the console command.
     */
    public function handle(SyncStateServiceInterface $syncStateService)
    {
        if ($this->option('all')) {
            $this->info('Attempting to unlock all schedules...');
            $schedules = SyncSchedule::all();
            $count = 0;
            
            foreach ($schedules as $schedule) {
                try {
                    $status = $syncStateService->checkSynchronizationStatus($schedule->id);
                    if ($status !== 'Idle') {
                        $syncStateService->resetSynchronizationState($schedule->id);
                        $this->line("Unlocked schedule ID: {$schedule->id}");
                        $count++;
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to unlock schedule {$schedule->id}: " . $e->getMessage());
                }
            }
            
            $this->info("Successfully unlocked {$count} schedules.");
            return self::SUCCESS;
        }

        $scheduleId = $this->argument('scheduleId');

        if (!$scheduleId) {
            $this->error('Please provide a schedule ID (e.g., php artisan sync:unlock 1) or use the --all option.');
            return self::FAILURE;
        }

        try {
            $syncStateService->resetSynchronizationState((int) $scheduleId);
            $this->info("Successfully released lock and reset status for schedule ID {$scheduleId}.");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to release lock: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
