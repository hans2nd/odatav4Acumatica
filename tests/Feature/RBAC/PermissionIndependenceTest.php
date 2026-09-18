<?php

namespace Tests\Feature\RBAC;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionIndependenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure standard testing roles exist
        Role::firstOrCreate(['name' => 'Super Administrator', 'is_superadmin' => true, 'is_active' => true]);
        
        // Define required independent permissions
        Permission::firstOrCreate(['permission_key' => 'query_repository.view']);
        Permission::firstOrCreate(['permission_key' => 'query_repository.update']);
        Permission::firstOrCreate(['permission_key' => 'query_repository.create']);
        Permission::firstOrCreate(['permission_key' => 'query_repository.delete']);
        Permission::firstOrCreate(['permission_key' => 'query_repository.execute']);
        Permission::firstOrCreate(['permission_key' => 'query_repository.manage']);
    }

    public function test_role_with_only_view_permission()
    {
        $role = Role::firstOrCreate(['name' => 'View Only', 'is_active' => true]);
        $viewPerm = Permission::where('permission_key', 'query_repository.view')->first();
        $role->permissions()->attach($viewPerm->id);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        // GET page succeeds
        $response = $this->get('/report/query-repository');
        $response->assertStatus(200);

        // UPDATE endpoint denied (requires query_repository.update)
        $updateResponse = $this->putJson('/report/query-repository/1', []);
        $updateResponse->assertStatus(403);
    }

    public function test_role_with_view_and_update_permissions()
    {
        $role = Role::firstOrCreate(['name' => 'Editor', 'is_active' => true]);
        
        $viewPerm = Permission::where('permission_key', 'query_repository.view')->first();
        $updatePerm = Permission::where('permission_key', 'query_repository.update')->first();
        
        $role->permissions()->attach([$viewPerm->id, $updatePerm->id]);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        // GET page succeeds
        $response = $this->get('/report/query-repository');
        $response->assertStatus(200);

        // UPDATE succeeds (or at least doesn't 403 authorization error, 404/422 is fine because missing record/data)
        $updateResponse = $this->putJson('/report/query-repository/9999', []);
        $updateResponse->assertStatus(404); // Passed authorization, failed to find record

        // CREATE denied
        $createResponse = $this->postJson('/report/query-repository', []);
        $createResponse->assertStatus(403);

        // DELETE denied
        $deleteResponse = $this->deleteJson('/report/query-repository/1');
        $deleteResponse->assertStatus(403);
        
        // EXECUTE denied
        $executeResponse = $this->postJson('/api/report/query-repository/preview', []);
        $executeResponse->assertStatus(403);
    }

    public function test_role_with_only_update_permission()
    {
        $role = Role::firstOrCreate(['name' => 'Updater Only', 'is_active' => true]);
        $updatePerm = Permission::where('permission_key', 'query_repository.update')->first();
        $role->permissions()->attach($updatePerm->id);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        $this->assertTrue($user->hasPermission('query_repository.update'));
        
        // It does not automatically grant unrelated permissions
        $this->assertFalse($user->hasPermission('query_repository.view'));
        $this->assertFalse($user->hasPermission('query_repository.create'));
        $this->assertFalse($user->hasPermission('query_repository.delete'));
        $this->assertFalse($user->hasPermission('query_repository.execute'));
        $this->assertFalse($user->hasPermission('query_repository.manage'));

        // VIEW page denied because it specifically requires query_repository.view
        $response = $this->get('/report/query-repository');
        $response->assertStatus(403);
        
        // UPDATE succeeds (404/422 meaning authorization passed)
        $updateResponse = $this->putJson('/report/query-repository/9999', []);
        $updateResponse->assertStatus(404);
    }
}
