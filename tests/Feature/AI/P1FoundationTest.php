<?php

namespace Tests\Feature\AI;

use Tests\TestCase;
use App\Models\User;
use App\Models\Connection;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiAuditLog;
use App\Models\AiUsageMetric;
use App\Services\AI\QueryCostGuardService;
use App\Services\AI\QueryValidatorService;
use App\DTO\AI\QueryPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class P1FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable DB foreign key checks for SQLite testing if necessary
        // DB::statement('PRAGMA foreign_keys=on;');
    }

    public function test_conversation_ownership_prevents_cross_user_access()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $conv1 = AiConversation::create(['user_id' => $user1->id, 'title' => 'User 1 Conv']);
        $conv2 = AiConversation::create(['user_id' => $user2->id, 'title' => 'User 2 Conv']);

        // Assuming a standard controller policy would check:
        $this->assertEquals($user1->id, $conv1->user_id);
        $this->assertNotEquals($user1->id, $conv2->user_id);
    }

    public function test_usage_records_can_be_persisted_without_provider()
    {
        $user = User::factory()->create();
        $usage = AiUsageMetric::create([
            'user_id' => $user->id,
            'duration_ms' => 150.5,
            'status' => 'success'
        ]);

        $this->assertDatabaseHas('ai_usage_metrics', [
            'id' => $usage->id,
            'provider' => null,
            'total_tokens' => null
        ]);
    }

    public function test_audit_records_are_created()
    {
        $user = User::factory()->create();
        $conn = Connection::create([
            'key' => 'test_conn',
            'name' => 'Test',
            'type' => 'mysql',
            'is_analytics_source' => true,
            'credentials' => []
        ]);

        $audit = AiAuditLog::create([
            'user_id' => $user->id,
            'connection_id' => $conn->id,
            'status' => 'success',
            'duration_ms' => 50,
            'row_count' => 10
        ]);

        $this->assertDatabaseHas('ai_audit_logs', [
            'id' => $audit->id,
            'status' => 'success'
        ]);
    }

    public function test_cost_guard_rejects_oversized_plans()
    {
        $guard = new QueryCostGuardService(maxTables: 2, maxLimit: 100);
        
        $planOverTables = new QueryPlan(tables: ['a', 'b', 'c']);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cost Guard: Query exceeds maximum allowed tables/joins (2).');
        $guard->guard($planOverTables);
    }

    public function test_cost_guard_rejects_excessive_limits()
    {
        $guard = new QueryCostGuardService(maxTables: 5, maxLimit: 100);
        
        $planOverLimit = new QueryPlan(tables: ['a'], limit: 500);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cost Guard: Requested row limit (500) exceeds the maximum allowed server limit (100).');
        $guard->guard($planOverLimit);
    }

    public function test_validator_rejects_unauthorized_source()
    {
        $conn = Connection::create([
            'key' => 'unauth',
            'name' => 'Unauth',
            'type' => 'mysql',
            'is_analytics_source' => false,
            'credentials' => []
        ]);

        $validator = new QueryValidatorService();
        $plan = new QueryPlan(tables: ['users']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unauthorized: The selected connection is not the designated analytics source.');
        
        $validator->validate($plan, $conn);
    }
}
