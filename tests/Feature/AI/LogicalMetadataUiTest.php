<?php

namespace Tests\Feature\AI;

use App\Models\Connection;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\AiSetting;

class LogicalMetadataUiTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $user;
    protected $connection;
    protected $definitionId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->connection = Connection::create([
            'name' => 'Analytics DB',
            'key' => 'analytics_db',
            'type' => 'mysql',
        ]);
        $this->connection->is_analytics_source = true;
        $this->connection->save();

        AiSetting::setConfig('analytics_connection_id', $this->connection->id);

        $this->definitionId = DB::table('sync_metadata_definitions')->insertGetId([
            'entity_key' => 'acm_datamentahpenjualan',
            'connection_id' => $this->connection->id,
            'logical_name' => null,
            'aliases' => null,
            'description' => null
        ]);

        $this->user = User::factory()->create();
        
        // Seed RBAC so that ai_metadata.view and ai_metadata.update exist
        $this->artisan('db:seed', ['--class' => 'DefaultRbacSeeder']);
        
        $this->admin = User::whereHas('roles', function($q) {
            $q->where('name', 'Super Administrator');
        })->first();
        
        if (!$this->admin) {
            $this->admin = User::factory()->create();
            $role = Role::where('name', 'Super Administrator')->first();
            $this->admin->roles()->sync([$role->id]);
        }
    }

    public function test_route_exists_and_unauthorized_cannot_access()
    {
        $response = $this->actingAs($this->user)->get('/settings/ai/metadata');
        $response->assertStatus(403);
    }

    public function test_authorized_admin_can_view_metadata()
    {
        $response = $this->actingAs($this->admin)->get('/settings/ai/metadata');
        $response->assertStatus(200);
        
        // acm_datamentahpenjualan is displayed
        $response->assertSee('acm_datamentahpenjualan');
        // Analytics Connection is displayed
        $response->assertSee('Analytics DB');
    }

    public function test_metadata_can_be_saved()
    {
        $response = $this->actingAs($this->admin)->post('/settings/ai/metadata/' . $this->definitionId, [
            'logical_name' => 'DataMentahPenjualan',
            'aliases' => "data mentah penjualan\npenjualan mentah\nraw sales",
            'description' => 'Raw synchronized sales transaction data'
        ]);

        $response->assertSessionHas('success');
        $response->assertRedirect();

        $definition = DB::table('sync_metadata_definitions')->where('id', $this->definitionId)->first();
        $this->assertEquals('DataMentahPenjualan', $definition->logical_name);
        
        $aliases = json_decode($definition->aliases, true);
        $this->assertCount(3, $aliases);
        $this->assertContains('data mentah penjualan', $aliases);
        
        $this->assertEquals('Raw synchronized sales transaction data', $definition->description);
    }

    public function test_metadata_remains_isolated_by_connection_id()
    {
        $otherConnection = Connection::create([
            'name' => 'Other DB',
            'key' => 'other_db',
            'type' => 'mysql',
        ]);
        
        DB::table('sync_metadata_definitions')->insert([
            'entity_key' => 'other_table',
            'connection_id' => $otherConnection->id,
            'logical_name' => 'OtherTable'
        ]);

        $response = $this->actingAs($this->admin)->get('/settings/ai/metadata');
        $response->assertStatus(200);
        
        // Should only see current connection's tables
        $response->assertSee('acm_datamentahpenjualan');
        $response->assertDontSee('other_table');
    }
}
