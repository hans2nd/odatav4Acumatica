<?php

namespace Tests\Feature\RBAC;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P2ConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::firstOrCreate(['name' => 'Super Administrator', 'is_superadmin' => true, 'is_active' => true]);
        Role::firstOrCreate(['name' => 'Admin', 'is_superadmin' => false, 'is_active' => true]);
        Role::firstOrCreate(['name' => 'User', 'is_superadmin' => false, 'is_active' => true]);
        Role::firstOrCreate(['name' => 'InactiveRole', 'is_superadmin' => false, 'is_active' => false]);
    }

    protected function getSuperAdmin()
    {
        $role = Role::where('is_superadmin', true)->first();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);
        
        $perm = Permission::firstOrCreate(['permission_key' => 'users.manage']);
        $role->permissions()->attach($perm->id);
        
        return $user;
    }

    public function test_users_page_requires_users_view()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'User')->first();
        $user->roles()->attach($role->id);
        
        // Without users.view
        $response = $this->actingAs($user)->get('/users');
        $response->assertStatus(403);
        
        // With users.view
        $perm = Permission::firstOrCreate(['permission_key' => 'users.view']);
        $role->permissions()->attach($perm->id);
        $response = $this->actingAs($user)->get('/users');
        $response->assertSuccessful();
    }

    public function test_query_repository_page_requires_query_repository_view()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'User')->first();
        $user->roles()->attach($role->id);
        
        // Without query_repository.view
        $response = $this->actingAs($user)->get('/report/query-repository');
        $response->assertStatus(403);
        
        // With query_repository.view
        $perm = Permission::firstOrCreate(['permission_key' => 'query_repository.view']);
        $role->permissions()->attach($perm->id);
        $response = $this->actingAs($user)->get('/report/query-repository');
        $response->assertSuccessful();
    }

    public function test_report_builder_page_requires_report_builder_view()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'User')->first();
        $user->roles()->attach($role->id);
        
        // Without report_builder.view
        $response = $this->actingAs($user)->get('/report-builder');
        $response->assertStatus(403);
        
        // With report_builder.view
        $perm = Permission::firstOrCreate(['permission_key' => 'report_builder.view']);
        $role->permissions()->attach($perm->id);
        $response = $this->actingAs($user)->get('/report-builder');
        $response->assertSuccessful();
    }

    public function test_query_builder_page_requires_query_builder_view()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'User')->first();
        $user->roles()->attach($role->id);
        
        // Without query_builder.view
        $response = $this->actingAs($user)->get('/query-builder');
        $response->assertStatus(403);
        
        // With query_builder.view
        $perm = Permission::firstOrCreate(['permission_key' => 'query_builder.view']);
        $role->permissions()->attach($perm->id);
        $response = $this->actingAs($user)->get('/query-builder');
        $response->assertSuccessful();
    }

    public function test_endpoint_api_page_requires_endpoint_api_view()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'User')->first();
        $user->roles()->attach($role->id);
        
        // Without endpoint_api.view
        $response = $this->actingAs($user)->get('/endpoint-api');
        $response->assertStatus(403);
        
        // With endpoint_api.view
        $perm = Permission::firstOrCreate(['permission_key' => 'endpoint_api.view']);
        $role->permissions()->attach($perm->id);
        $response = $this->actingAs($user)->get('/endpoint-api');
        $response->assertSuccessful();
    }

    public function test_settings_page_requires_settings_view()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'User')->first();
        $user->roles()->attach($role->id);
        
        // Without settings.view
        $response = $this->actingAs($user)->get('/settings');
        $response->assertStatus(403);
        
        // With settings.view
        $perm = Permission::firstOrCreate(['permission_key' => 'settings.view']);
        $role->permissions()->attach($perm->id);
        $response = $this->actingAs($user)->get('/settings');
        $response->assertSuccessful();
    }

    public function test_ai_settings_page_requires_ai_settings_view()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'User')->first();
        $user->roles()->attach($role->id);
        
        // Without ai_settings.view
        $response = $this->actingAs($user)->get('/settings/ai/openrouter');
        $response->assertStatus(403);
        
        // With ai_settings.view
        $perm = Permission::firstOrCreate(['permission_key' => 'ai_settings.view']);
        $role->permissions()->attach($perm->id);
        $response = $this->actingAs($user)->get('/settings/ai/openrouter');
        $response->assertSuccessful();
    }

    public function test_users_manage_permission_required_for_mutations()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'User')->first();
        $user->roles()->attach($role->id);
        
        $perm = Permission::firstOrCreate(['permission_key' => 'users.view']);
        $role->permissions()->attach($perm->id);
        
        // Has view, but missing manage -> 403
        $response = $this->actingAs($user)->postJson('/api/users', []);
        $response->assertStatus(403);
        
        // Add manage
        $managePerm = Permission::firstOrCreate(['permission_key' => 'users.manage']);
        $role->permissions()->attach($managePerm->id);
        
        // Has manage -> should pass permission check (might 422 for missing data, but not 403)
        $response = $this->actingAs($user)->postJson('/api/users', []);
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_inactive_user_is_denied_everywhere()
    {
        $user = User::factory()->create(['is_active' => false]); // Inactive
        $role = Role::where('is_superadmin', true)->first();
        $user->roles()->attach($role->id); // Give superadmin
        
        $response = $this->actingAs($user)->get('/users');
        $response->assertStatus(403); // Middleware denies
    }

    public function test_inactive_role_contributes_no_permissions()
    {
        $user = User::factory()->create(['is_active' => true]);
        $inactiveRole = Role::where('name', 'InactiveRole')->first();
        
        $perm = Permission::firstOrCreate(['permission_key' => 'users.view']);
        $inactiveRole->permissions()->attach($perm->id);
        
        $user->roles()->attach($inactiveRole->id);
        
        $response = $this->actingAs($user)->get('/users');
        $response->assertStatus(403); // Inactive role permissions are ignored
    }
}
