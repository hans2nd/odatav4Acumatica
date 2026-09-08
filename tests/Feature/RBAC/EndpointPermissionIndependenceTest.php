<?php

namespace Tests\Feature\RBAC;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointPermissionIndependenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure standard testing roles exist
        Role::firstOrCreate(['name' => 'Super Administrator', 'is_superadmin' => true, 'is_active' => true]);
        
        // Define required independent permissions
        Permission::firstOrCreate(['permission_key' => 'endpoint_api.view']);
        Permission::firstOrCreate(['permission_key' => 'api.endpoint.acm_datamentahpenjualan.view']);
        Permission::firstOrCreate(['permission_key' => 'api.endpoint.other_entity.view']);
    }

    public function test_a_menu_access_allowed_endpoint_denied()
    {
        $role = Role::firstOrCreate(['name' => 'Menu Only', 'is_active' => true]);
        $viewMenuPerm = Permission::where('permission_key', 'endpoint_api.view')->first();
        $role->permissions()->attach($viewMenuPerm->id);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        // GET menu page succeeds
        $response = $this->get('/endpoint-api');
        $response->assertStatus(200);

        // GET data endpoint denied (requires api.endpoint.acm_datamentahpenjualan.view)
        $endpointResponse = $this->get('/data/acm_datamentahpenjualan');
        $endpointResponse->assertStatus(403);
    }

    public function test_b_menu_access_allowed_and_endpoint_allowed()
    {
        $role = Role::firstOrCreate(['name' => 'Full Access', 'is_active' => true]);
        
        $viewMenuPerm = Permission::where('permission_key', 'endpoint_api.view')->first();
        $entityPerm = Permission::where('permission_key', 'api.endpoint.acm_datamentahpenjualan.view')->first();
        
        $role->permissions()->attach([$viewMenuPerm->id, $entityPerm->id]);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        // GET menu page succeeds
        $response = $this->get('/endpoint-api');
        $response->assertStatus(200);

        // GET data endpoint succeeds (or at least doesn't 403 authorization error, 404/500 is fine because missing backend data)
        $endpointResponse = $this->get('/data/acm_datamentahpenjualan');
        
        // We just assert it's not 403 Forbidden (could be 404 Not Found or 500 server error since fake data)
        $this->assertNotEquals(403, $endpointResponse->getStatusCode());
    }

    public function test_c_entity_permission_isolation()
    {
        $role = Role::firstOrCreate(['name' => 'Specific Entity Access', 'is_active' => true]);
        $entityPerm = Permission::where('permission_key', 'api.endpoint.acm_datamentahpenjualan.view')->first();
        $role->permissions()->attach($entityPerm->id);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        $this->assertTrue($user->hasPermission('api.endpoint.acm_datamentahpenjualan.view'));
        
        // It does not automatically grant unrelated permissions
        $this->assertFalse($user->hasPermission('api.endpoint.other_entity.view'));
        $this->assertFalse($user->hasPermission('endpoint_api.view'));

        // View menu denied because it specifically requires endpoint_api.view
        $response = $this->get('/endpoint-api');
        $response->assertStatus(403);
        
        // Accessing the other entity fails
        $otherEndpointResponse = $this->get('/data/other_entity');
        $otherEndpointResponse->assertStatus(403);
    }

    public function test_d_multi_role_union()
    {
        $role1 = Role::firstOrCreate(['name' => 'Role 1', 'is_active' => true]);
        $role1->permissions()->attach(Permission::where('permission_key', 'endpoint_api.view')->first()->id);
        
        $role2 = Role::firstOrCreate(['name' => 'Role 2', 'is_active' => true]);
        $role2->permissions()->attach(Permission::where('permission_key', 'api.endpoint.acm_datamentahpenjualan.view')->first()->id);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach([$role1->id, $role2->id]);

        $this->actingAs($user);

        // User effectively has both
        $this->assertTrue($user->hasPermission('endpoint_api.view'));
        $this->assertTrue($user->hasPermission('api.endpoint.acm_datamentahpenjualan.view'));
    }

    public function test_e_immediate_removal_enforcement()
    {
        $role = Role::firstOrCreate(['name' => 'Toggle Access', 'is_active' => true]);
        $entityPerm = Permission::where('permission_key', 'api.endpoint.acm_datamentahpenjualan.view')->first();
        $role->permissions()->attach($entityPerm->id);

        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);

        $this->actingAs($user);
        $this->assertTrue($user->hasPermission('api.endpoint.acm_datamentahpenjualan.view'));

        // Remove permission
        $role->permissions()->detach($entityPerm->id);
        $user->clearAuthorizationCache(); // clear request-level memoization

        $this->assertFalse($user->hasPermission('api.endpoint.acm_datamentahpenjualan.view'));
    }

    public function test_f_superadmin_bypass()
    {
        $superRole = Role::where('is_superadmin', true)->first();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($superRole->id);

        $this->actingAs($user);

        // Superadmin implicitly has everything
        $this->assertTrue($user->hasPermission('api.endpoint.acm_datamentahpenjualan.view'));
        $this->assertTrue($user->hasPermission('api.endpoint.literally_anything.execute'));
    }

    public function test_g_round_trip_permission_persistence()
    {
        $role = Role::firstOrCreate(['name' => 'API Integrator', 'is_active' => true]);
        
        $menuPermId = Permission::where('permission_key', 'endpoint_api.view')->first()->id;
        $entityPermId = Permission::where('permission_key', 'api.endpoint.acm_datamentahpenjualan.view')->first()->id;
        
        // Mock a UI saveRolePermissions payload
        $payload = [
            'permission_ids' => [
                $menuPermId,
                $entityPermId
            ]
        ];
        
        // Save using the UserManagementController method
        $superRole = Role::where('is_superadmin', true)->first();
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdmin->roles()->attach($superRole->id);

        $this->actingAs($superAdmin);

        $response = $this->putJson("/api/roles/{$role->id}/permissions", $payload);
        $response->assertStatus(200);

        // Reload the role
        $role->refresh();
        $savedIds = $role->permissions()->pluck('permissions.id')->toArray();

        // Verify exactly those permissions remain checked
        $this->assertContains($menuPermId, $savedIds);
        $this->assertContains($entityPermId, $savedIds);
        
        // Verify another entity's permission remains unchecked
        $otherPermId = Permission::where('permission_key', 'api.endpoint.other_entity.view')->first()->id;
        $this->assertNotContains($otherPermId, $savedIds);
        
        // Verify there are no duplicate role_permissions
        $this->assertCount(2, $savedIds); // exactly 2 permissions assigned

        // Verify authorization for a regular user with this role
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);

        $this->actingAs($user);
        
        $this->assertTrue($user->hasPermission('endpoint_api.view'));
        $this->assertTrue($user->hasPermission('api.endpoint.acm_datamentahpenjualan.view'));
        $this->assertFalse($user->hasPermission('api.endpoint.other_entity.view'));
    }
}
