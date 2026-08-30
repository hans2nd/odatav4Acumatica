<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\SyncSchedule;
use App\Models\SyncState;
use App\Services\SyncStateService;
use App\Repositories\SyncStateRepository;
use App\Exceptions\SyncState\SyncStateLockException;
use App\Jobs\SyncEntityJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use App\Services\SyncService;
use Mockery;

class SyncStateLockTest extends TestCase
{
    use RefreshDatabase;

    protected SyncStateService $syncStateService;
    protected SyncSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->syncStateService = new SyncStateService(new SyncStateRepository());
        
        $this->schedule = SyncSchedule::create([
            'entity_key' => 'TestEntity',
            'is_active' => true,
        ]);
        
        $this->syncStateService->initializeSyncState($this->schedule->id, 'TestEntity');
    }

    /** @test */
    public function test_successful_lock_acquisition()
    {
        $workerId = 'worker_test_1';
        $acquired = $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $workerId);
        
        $this->assertTrue($acquired);
        $state = SyncState::where('sync_schedule_id', $this->schedule->id)->first();
        $this->assertEquals($workerId, $state->lock_owner);
        $this->assertNotNull($state->lock_expires_at);
    }

    /** @test */
    public function test_concurrent_worker_rejection()
    {
        $worker1 = 'worker_test_1';
        $worker2 = 'worker_test_2';
        
        $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $worker1);
        
        $this->expectException(SyncStateLockException::class);
        $this->expectExceptionMessage("Lock for schedule ID {$this->schedule->id} is already held by {$worker1} (active lease).");
        
        $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $worker2);
    }

    /** @test */
    public function test_successful_release()
    {
        $workerId = 'worker_test_1';
        $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $workerId);
        $this->syncStateService->releaseSynchronizationLock($this->schedule->id, $workerId);
        
        $state = SyncState::where('sync_schedule_id', $this->schedule->id)->first();
        $this->assertNull($state->lock_owner);
    }

    /** @test */
    public function test_release_after_exception()
    {
        $workerId = 'worker_test_1';
        $syncService = app(SyncService::class);
        
        // We simulate a failure in startSynchronization which throws an exception.
        // We will mock the repository to throw an exception on startSynchronization.
        $mockRepo = Mockery::mock(SyncStateRepository::class)->makePartial();
        $mockRepo->shouldReceive('markStarted')->andThrow(new \Exception("Mock failure"));
        $service = new SyncStateService($mockRepo);
        
        $service->acquireSynchronizationLock($this->schedule->id, $workerId);
        
        try {
            $service->startSynchronization($this->schedule->id);
            $this->fail('Expected exception was not thrown.');
        } catch (\Exception $e) {
            $service->releaseSynchronizationLock($this->schedule->id, $workerId);
        }

        $state = SyncState::where('sync_schedule_id', $this->schedule->id)->first();
        $this->assertNull($state->lock_owner);
    }

    /** @test */
    public function test_same_job_retry_retains_worker_id()
    {
        $job = new SyncEntityJob('TestEntity');
        $workerId1 = $job->workerId;
        
        // Serializing and deserializing simulates queue push/pop
        $serialized = serialize($job);
        $unserializedJob = unserialize($serialized);
        
        $this->assertEquals($workerId1, $unserializedJob->workerId);
        $this->assertStringStartsWith('worker_TestEntity_', $unserializedJob->workerId);
    }

    /** @test */
    public function test_expired_lock_can_be_reclaimed()
    {
        $worker1 = 'worker_test_1';
        $worker2 = 'worker_test_2'; // Or same worker, doesn't matter for reclaiming expired
        
        $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $worker1);
        
        // Simulate expiration by moving time forward past 5 minutes
        Carbon::setTestNow(Carbon::now()->addMinutes(6));
        
        // worker 2 should be able to claim it because it's expired
        $acquired = $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $worker2);
        $this->assertTrue($acquired);
        
        $state = SyncState::where('sync_schedule_id', $this->schedule->id)->first();
        $this->assertEquals($worker2, $state->lock_owner);
        
        Carbon::setTestNow(); // reset
    }

    /** @test */
    public function test_active_lock_cannot_be_stolen_by_another_worker()
    {
        $worker1 = 'worker_test_1';
        $worker2 = 'worker_test_2';
        
        $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $worker1);
        
        $this->expectException(SyncStateLockException::class);
        $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $worker2);
    }

    /** @test */
    public function test_same_owner_can_refresh_its_own_lease_safely()
    {
        $worker1 = 'worker_test_1';
        
        $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $worker1);
        $state1 = SyncState::where('sync_schedule_id', $this->schedule->id)->first();
        
        Carbon::setTestNow(Carbon::now()->addMinutes(2));
        
        $this->syncStateService->heartbeatSynchronizationLock($this->schedule->id, $worker1);
        
        $state2 = SyncState::where('sync_schedule_id', $this->schedule->id)->first();
        
        $this->assertTrue($state2->lock_expires_at->gt($state1->lock_expires_at));
        
        Carbon::setTestNow();
    }

    /** @test */
    public function test_long_running_active_worker_remains_protected_while_heartbeat_is_active()
    {
        $worker1 = 'worker_test_1';
        $worker2 = 'worker_test_2';
        
        $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $worker1);
        
        // Advance 4 minutes (still within 5 min TTL)
        Carbon::setTestNow(Carbon::now()->addMinutes(4));
        
        // Heartbeat extends it by 5 minutes
        $this->syncStateService->heartbeatSynchronizationLock($this->schedule->id, $worker1);
        
        // Advance another 2 minutes (Total 6 mins from start). 
        // Without heartbeat, it would be expired now.
        Carbon::setTestNow(Carbon::now()->addMinutes(2));
        
        // Worker 2 attempts to steal. Should fail because heartbeat extended the lease!
        $this->expectException(SyncStateLockException::class);
        $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $worker2);
        
        Carbon::setTestNow();
    }

    /** @test */
    public function test_one_worker_cannot_release_another_workers_lock()
    {
        $worker1 = 'worker_test_1';
        $worker2 = 'worker_test_2';
        
        $this->syncStateService->acquireSynchronizationLock($this->schedule->id, $worker1);
        
        $this->expectException(SyncStateLockException::class);
        $this->expectExceptionMessage("Cannot release lock for schedule ID {$this->schedule->id}. Owned by {$worker1}, but {$worker2} attempted release.");
        
        $this->syncStateService->releaseSynchronizationLock($this->schedule->id, $worker2);
    }
}
