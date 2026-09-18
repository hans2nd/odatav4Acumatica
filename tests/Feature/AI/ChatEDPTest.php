<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Models\Connection;
use App\Models\AiSetting;
use App\Models\AiConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use App\Models\Role;

class ChatEDPTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup mock connection & table for execution tests
        $conn = Connection::create([
            'key' => 'analytics_conn',
            'name' => 'Analytics DB',
            'type' => config('database.default'), // Use the same driver as the test DB
            'credentials' => config('database.connections.' . config('database.default'))
        ]);
        
        // Force set is_analytics_source and read_only since they might not be in $fillable
        \DB::table('connections')->where('id', $conn->id)->update([
            'is_analytics_source' => 1,
            'read_only' => 1
        ]);

        AiSetting::setConfig('analytics_connection_id', $conn->id);
        AiSetting::setConfig('openrouter_api_key', 'sk-test', true);

        // We need an actual table for the QueryCompiler to not fail syntax
        if (!Schema::hasTable('DataMentah')) {
            Schema::create('DataMentah', function (Blueprint $table) {
                $table->id();
                $table->string('CustomerID');
                $table->integer('OrderTotal');
            });
            \DB::table('DataMentah')->insert(['CustomerID' => 'CUST-001', 'OrderTotal' => 1000]);
        }
        
        // Mock Schema Cache metadata for QueryValidatorService
        $defId = \DB::table('sync_metadata_definitions')->insertGetId([
            'entity_key' => 'DataMentah',
            'connection_id' => $conn->id,
            'provider' => 'acumatica',
            'provider_version' => '1.0',
            'metadata_version' => '1.0',
            'metadata_hash' => 'hash',
            'is_valid' => true,
            'last_synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        \DB::table('sync_metadata_fields')->insert([
            ['definition_id' => $defId, 'field_name' => 'CustomerID', 'data_type' => 'string', 'is_primary_key' => false, 'is_nullable' => false, 'created_at' => now(), 'updated_at' => now()],
            ['definition_id' => $defId, 'field_name' => 'OrderTotal', 'data_type' => 'integer', 'is_primary_key' => false, 'is_nullable' => false, 'created_at' => now(), 'updated_at' => now()]
        ]);
    }

    private function createNormalUser()
    {
        return User::factory()->create();
    }

    public function test_authenticated_user_can_create_conversation()
    {
        $user = $this->createNormalUser();
        
        $response = $this->actingAs($user)->postJson(route('api.chatedp.conversations.store'), [
            'title' => 'My First Analysis'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('conversation.title', 'My First Analysis');
                 
        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $user->id,
            'title' => 'My First Analysis'
        ]);
    }

    public function test_send_message_generates_plan_compiles_executes_and_caches_for_export()
    {
        $user = $this->createNormalUser();
        $conversation = AiConversation::create(['user_id' => $user->id, 'title' => 'Test']);

        // Mock OpenRouter Responses
        Http::fake([
            // First call: QueryPlanner
            'openrouter.ai/api/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'intent' => 'sales_analysis',
                                    'tables' => ['DataMentah'],
                                    'columns' => ['CustomerID', 'OrderTotal'],
                                    'filters' => [
                                        ['field' => 'CustomerID', 'operator' => '=', 'value' => 'CUST-001']
                                    ]
                                ])
                            ]
                        ]
                    ]
                ], 200)
                // Second call: Summary Generation
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => 'The total for CUST-001 is 1000.'
                            ]
                        ]
                    ]
                ], 200)
        ]);

        $response = $this->actingAs($user)->postJson(route('api.chatedp.messages.send'), [
            'conversation_id' => $conversation->id,
            'message' => 'Berapa total penjualan untuk CUST-001?'
        ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }
        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'results', 'export_token']);

        $token = $response->json('export_token');
        
        // Assert Export Token stores data in Cache correctly
        $this->assertTrue(Cache::has('chatedp_export_' . $token));

        // Test Export endpoint with the token
        $exportResponse = $this->actingAs($user)->get(route('api.chatedp.export') . '?token=' . $token);
        
        $actualHeader = $exportResponse->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment; filename=Analysis_Export_', $actualHeader);

        // Verify Audit Log
        $this->assertDatabaseHas('ai_audit_logs', [
            'user_id' => $user->id,
            'status' => 'success'
        ]);
    }

    public function test_follow_up_question_creates_new_query_plan()
    {
        $user = $this->createNormalUser();
        $conversation = AiConversation::create(['user_id' => $user->id, 'title' => 'Test']);

        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::sequence()
                // First question -> Planner
                ->push([
                    'choices' => [['message' => ['content' => json_encode(['intent' => 'sales_analysis', 'tables' => ['DataMentah'], 'filters' => [['field' => 'CustomerID', 'operator' => '=', 'value' => 'CUST-001']]])]]]
                ], 200)
                // First question -> Summary
                ->push(['choices' => [['message' => ['content' => 'Sales is 1000.']]]], 200)
                // Follow up -> Planner (expecting different filters)
                ->push([
                    'choices' => [['message' => ['content' => json_encode(['intent' => 'sales_analysis', 'tables' => ['DataMentah'], 'filters' => [['field' => 'CustomerID', 'operator' => '=', 'value' => 'CUST-002']]])]]]
                ], 200)
                // Follow up -> Summary
                ->push(['choices' => [['message' => ['content' => 'Sales is 0.']]]], 200)
        ]);

        // First message
        $response1 = $this->actingAs($user)->postJson(route('api.chatedp.messages.send'), [
            'conversation_id' => $conversation->id,
            'message' => 'Penjualan CUST-001'
        ]);
        if (isset($response1->json()['message']['metadata']['intent']) && $response1->json()['message']['metadata']['intent'] !== 'sales_analysis') {
            dump("Response 1 Intent was not sales_analysis", $response1->json());
        }
        $response1->assertStatus(200);

        $response2 = $this->actingAs($user)->postJson(route('api.chatedp.messages.send'), [
            'conversation_id' => $conversation->id,
            'message' => 'Kalau CUST-002?'
        ]);
        if ($response2->status() !== 200) dump($response2->json());
        $response2->assertStatus(200);

        // We should have 2 distinct query plans in audit logs
        $logs = \App\Models\AiAuditLog::where('conversation_id', $conversation->id)->get();
        $this->assertCount(2, $logs);
        
        $plan1 = $logs[0]->query_plan_json;
        $plan2 = $logs[1]->query_plan_json;
        
        $this->assertEquals('CUST-001', $plan1['filters'][0]['value']);
        $this->assertEquals('CUST-002', $plan2['filters'][0]['value']);
    }

    public function test_data_unavailable_safely_rejects_query()
    {
        $user = $this->createNormalUser();
        $conversation = AiConversation::create(['user_id' => $user->id, 'title' => 'Test']);

        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [['message' => ['content' => json_encode(['intent' => 'data_unavailable'])]]]
                ], 200)
        ]);

        $response = $this->actingAs($user)->postJson(route('api.chatedp.messages.send'), [
            'conversation_id' => $conversation->id,
            'message' => 'Stock ABC-001 sekarang berapa?'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('message.metadata.intent', 'data_unavailable');
                 
        $this->assertStringContainsString('Data tersebut belum tersedia', $response->json('message.content'));
    }

    public function test_unauthorized_user_cannot_access_export_token()
    {
        $user1 = $this->createNormalUser();
        $user2 = $this->createNormalUser();
        $conversation = AiConversation::create(['user_id' => $user1->id, 'title' => 'Test']);

        $token = 'test-token-123';
        Cache::put('chatedp_export_' . $token, [
            'user_id' => $user1->id, // Belongs to user1
            'conversation_id' => $conversation->id,
            'results' => [['id' => 1]],
            'metadata' => [],
            'question' => 'test',
            'summary' => 'test summary'
        ], now()->addMinutes(30));

        // User 2 tries to access User 1's export
        $response = $this->actingAs($user2)->get(route('api.chatedp.export') . '?token=' . $token);
        
        $response->assertStatus(404); // Or 403 depending on implementation, abort(404) is used in controller
    }

    public function test_analytics_connection_cannot_be_forged_by_user()
    {
        $user = $this->createNormalUser();
        $conversation = AiConversation::create(['user_id' => $user->id, 'title' => 'Test']);

        // Set up a fake alternative connection that the user might try to forge
        $connForbidden = Connection::create([
            'key' => 'forbidden_conn',
            'name' => 'Secret DB',
            'type' => 'mysql',
        ]);

        Http::fake([
            'openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode(['intent' => 'sales_analysis', 'tables' => ['DataMentah'], 'filters' => []])]]]], 200)
        ]);

        // Attempt to pass forbidden_connection_id in request
        $response = $this->actingAs($user)->postJson(route('api.chatedp.messages.send'), [
            'conversation_id' => $conversation->id,
            'message' => 'Sales test',
            'connection_id' => $connForbidden->id, // FORGING ATTEMPT
            'analytics_connection_id' => $connForbidden->id // FORGING ATTEMPT
        ]);

        $response->assertStatus(200);

        // Verify the audit log shows the query was executed against the SYSTEM-CONFIGURED connection, NOT the forged one
        $systemConfiguredConnectionId = AiSetting::getConfig('analytics_connection_id');
        $log = \App\Models\AiAuditLog::where('conversation_id', $conversation->id)->first();
        
        $this->assertEquals($systemConfiguredConnectionId, $log->connection_id);
        $this->assertNotEquals($connForbidden->id, $log->connection_id);
    }
}
