<?php

namespace Tests\Feature\RBAC;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P1MultiRoleTest extends TestCase
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
    
    protected function getAdmin()
    {
        $role = Role::where('name', 'Admin')->first();
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role->id);
        
        $perm = Permission::firstOrCreate(['permission_key' => 'users.manage']);
        $role->permissions()->attach($perm->id);
        
        return $user;
    }

    public function test_user_can_be_created_with_multiple_roles()
    {
        $admin = $this->getSuperAdmin();
        $role1 = Role::where('name', 'Admin')->first();
        $role2 = Role::where('name', 'User')->first();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role_ids' => [$role1->id, $role2->id]
        ]);

        $response->assertSuccessful();
        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertEquals(2, $user->roles()->count());
    }

    public function test_user_can_be_updated_with_multiple_roles()
    {
        $admin = $this->getSuperAdmin();
        $target = User::factory()->create(['is_active' => true]);
        $role1 = Role::where('name', 'Admin')->first();
        $role2 = Role::where('name', 'User')->first();

        $response = $this->actingAs($admin)->putJson("/api/users/{$target->id}", [
            'name' => 'Updated User',
            'email' => $target->email,
            'role_ids' => [$role1->id, $role2->id]
        ]);

        $response->assertSuccessful();
        $this->assertEquals(2, $target->roles()->count());
    }

    public function test_explicit_role_removal_works()
    {
        $admin = $this->getSuperAdmin();
        $target = User::factory()->create(['is_active' => true]);
        $role1 = Role::where('name', 'Admin')->first();
        $role2 = Role::where('name', 'User')->first();
        $target->roles()->attach([$role1->id, $role2->id]);

        // Remove role2, keep role1
        $response = $this->actingAs($admin)->putJson("/api/users/{$target->id}", [
            'name' => 'Updated User',
            'email' => $target->email,
            'role_ids' => [$role1->id]
        ]);

        $response->assertSuccessful();
        $this->assertEquals(1, $target->roles()->count());
        $this->assertEquals($role1->id, $target->roles()->first()->id);
    }

    public function test_legacy_role_id_is_normalized_safely()
    {
        $admin = $this->getSuperAdmin();
        $role = Role::where('name', 'User')->first();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Legacy User',
            'email' => 'legacy@example.com',
            'password' => 'password123',
            'role_id' => $role->id // Legacy format
        ]);

        $response->assertSuccessful();
        $user = User::where('email', 'legacy@example.com')->first();
        $this->assertEquals(1, $user->roles()->count());
        $this->assertEquals($role->id, $user->roles()->first()->id);
    }

    public function test_duplicate_role_ids_are_handled()
    {
        $admin = $this->getSuperAdmin();
        $role = Role::where('name', 'User')->first();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Dup User',
            'email' => 'dup@example.com',
            'password' => 'password123',
            'role_ids' => [$role->id, $role->id]
        ]);

        $response->assertSuccessful();
        $user = User::where('email', 'dup@example.com')->first();
        $this->assertEquals(1, $user->roles()->count());
    }

    public function test_nonexistent_role_is_rejected()
    {
        $admin = $this->getSuperAdmin();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Bad User',
            'email' => 'bad@example.com',
            'password' => 'password123',
            'role_ids' => [9999]
        ]);

        $response->assertStatus(422);
    }

    public function test_empty_role_ids_are_rejected()
    {
        $admin = $this->getSuperAdmin();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Empty User',
            'email' => 'empty@example.com',
            'password' => 'password123',
            'role_ids' => []
        ]);

        $response->assertStatus(422);
    }

    public function test_non_superadmin_cannot_assign_superadmin_in_role_ids()
    {
        $admin = $this->getAdmin();
        $superRole = Role::where('is_superadmin', true)->first();
        $userRole = Role::where('name', 'User')->first();

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Hacker User',
            'email' => 'hack@example.com',
            'password' => 'password123',
            'role_ids' => [$userRole->id, $superRole->id]
        ]);

        $response->assertStatus(403);
        $this->assertNull(User::where('email', 'hack@example.com')->first());
    }

    public function test_non_superadmin_cannot_modify_existing_superadmin()
    {
        $admin = $this->getAdmin();
        $superTarget = $this->getSuperAdmin();
        $userRole = Role::where('name', 'User')->first();

        $response = $this->actingAs($admin)->putJson("/api/users/{$superTarget->id}", [
            'name' => 'Downgraded User',
            'email' => $superTarget->email,
            'role_ids' => [$userRole->id]
        ]);

        $response->assertStatus(403);
        $this->assertTrue($superTarget->fresh()->isSuperAdmin());
    }

    public function test_last_superadmin_cannot_lose_superadmin_role_in_multi_role_update()
    {
        $superadmin = $this->getSuperAdmin();
        $userRole = Role::where('name', 'User')->first();

        // Attempt to remove Superadmin role from self (last superadmin)
        $response = $this->actingAs($superadmin)->putJson("/api/users/{$superadmin->id}", [
            'name' => $superadmin->name,
            'email' => $superadmin->email,
            'role_ids' => [$userRole->id]
        ]);

        $response->assertStatus(403);
        $this->assertTrue($superadmin->fresh()->isSuperAdmin());
    }

    public function test_last_superadmin_protection_is_atomic()
    {
        $superadmin = $this->getSuperAdmin();
        $userRole = Role::where('name', 'User')->first();

        // Start with only superadmin role
        $this->assertEquals(1, $superadmin->roles()->count());

        $response = $this->actingAs($superadmin)->putJson("/api/users/{$superadmin->id}", [
            'name' => $superadmin->name,
            'email' => $superadmin->email,
            'role_ids' => [$userRole->id] // Attempt to replace with user role
        ]);

        $response->assertStatus(403);
        
        // Assert no partial changes were made (should NOT have gained User role, and should NOT have lost Superadmin)
        $this->assertEquals(1, $superadmin->fresh()->roles()->count());
        $this->assertTrue($superadmin->fresh()->isSuperAdmin());
    }
}
