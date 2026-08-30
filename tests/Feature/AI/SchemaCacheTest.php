<?php

namespace Tests\Feature\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Connection;
use App\Services\AI\SchemaCacheService;
use App\Models\AiSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class SchemaCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'DefaultRbacSeeder']);
    }

    protected function createAdminUser()
    {
        $admin = User::whereHas('roles', function($q) {
            $q->where('name', 'Super Administrator');
        })->first();
        
        if (!$admin) {
            $admin = User::factory()->create();
            $role = \Spatie\Permission\Models\Role::where('name', 'Super Administrator')->first();
            if ($role) {
                $admin->roles()->sync([$role->id]);
            } else {
                \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
                    return true;
                });
            }
        }
        return $admin;
    }

    public function test_schema_cache_discovers_physical_tables_and_preserves_semantics()
    {
        // 1. Create a dummy connection
        $connection = Connection::create([
            'name' => 'Test Analytics',
            'key' => 'test_analytics',
            'type' => 'mysql',
            'credentials' => config('database.connections.' . config('database.default')),
        ]);
        $connection->is_analytics_source = true;
        $connection->save();

        AiSetting::setConfig('analytics_connection_id', (string)$connection->id);

        // 2. Set up test table
        Schema::connection('mysql')->create('test_discovery_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('qty');
        });

        // Add dummy semantics first
        DB::table('sync_metadata_definitions')->insert([
            'entity_key' => 'test_discovery_table',
            'connection_id' => $connection->id,
            'logical_name' => 'DiscoveryTable',
            'aliases' => json_encode(['disc', 'test']),
            'description' => 'Test desc',
            'provider' => 'analytics',
            'metadata_version' => '1.0',
            'metadata_hash' => 'hash',
            'is_valid' => true,
        ]);

        // 3. Cache the schema (mocking the dbName dynamically to mysql for testing)
        // Note: The SchemaCacheService relies on dynamically creating a connection 'ai_schema_cache_{$id}'.
        // For testing, since type is mysql, it will use current env credentials which is odatav4_testing.
        $service = app(SchemaCacheService::class);
        $service->cacheSchema($connection);

        // 4. Assertions
        $def = DB::table('sync_metadata_definitions')
            ->where('connection_id', $connection->id)
            ->where('entity_key', 'test_discovery_table')
            ->first();

        $this->assertNotNull($def);
        $this->assertEquals('DiscoveryTable', $def->logical_name); // semantics preserved
        $this->assertEquals('Test desc', $def->description);

        $fields = DB::table('sync_metadata_fields')
            ->where('definition_id', $def->id)
            ->pluck('field_name')
            ->toArray();

        $this->assertContains('id', $fields);
        $this->assertContains('name', $fields);
        $this->assertContains('qty', $fields);

        // Cleanup
        Schema::connection('mysql')->dropIfExists('test_discovery_table');
    }

    public function test_manual_refresh_schema_via_ui()
    {
        $admin = $this->createAdminUser();
        $connection = Connection::create([
            'name' => 'Local Analytics',
            'key' => 'local_analytics',
            'type' => 'mysql',
            'credentials' => config('database.connections.' . config('database.default')),
        ]);
        $connection->is_analytics_source = true;
        $connection->save();
        
        AiSetting::setConfig('analytics_connection_id', (string)$connection->id);

        $response = $this->actingAs($admin)->post(route('settings.ai.metadata.refresh'));

        $response->assertStatus(302);
        $response->assertSessionHas('success');
    }
}
