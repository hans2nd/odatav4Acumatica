<?php

namespace Tests\Feature\RBAC;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class P3PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::firstOrCreate(['name' => 'Super Administrator', 'is_superadmin' => true, 'is_active' => true]);
        Role::firstOrCreate(['name' => 'Admin', 'is_superadmin' => false, 'is_active' => true]);
        Role::firstOrCreate(['name' => 'User', 'is_superadmin' => false, 'is_active' => true]);
    }

    public function test_repeated_has_permission_calls_reuse_loaded_permissions()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', 'User')->first();
        $user->roles()->attach($role->id);
        
        $perm1 = Permission::firstOrCreate(['permission_key' => 'test.view']);
        $perm2 = Permission::firstOrCreate(['permission_key' => 'test.manage']);
        $role->permissions()->attach([$perm1->id, $perm2->id]);
        
        // Ensure cache is populated
        DB::enableQueryLog();
        $user->hasPermission('test.view');
        $queriesFirstCall = count(DB::getQueryLog());
        
        DB::flushQueryLog();
        
        // These should hit the memory cache and execute 0 DB queries
        $user->hasPermission('test.manage');
        $user->hasPermission('test.view');
        $user->hasAnyPermission(['test.view']);
        
        $queriesSubsequentCalls = count(DB::getQueryLog());
        
        $this->assertTrue($queriesFirstCall > 0, "First call should execute DB queries.");
        $this->assertEquals(0, $queriesSubsequentCalls, "Subsequent calls should use memoized cache.");
    }

    public function test_is_superadmin_is_memoized_per_request()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('is_superadmin', true)->first();
        $user->roles()->attach($role->id);
        
        DB::enableQueryLog();
        $user->isSuperAdmin();
        $queriesFirstCall = count(DB::getQueryLog());
        
        DB::flushQueryLog();
        
        // This should hit the memory cache
        $user->isSuperAdmin();
        $queriesSubsequentCalls = count(DB::getQueryLog());
        
        $this->assertTrue($queriesFirstCall > 0, "First call should execute DB queries.");
        $this->assertEquals(0, $queriesSubsequentCalls, "Subsequent calls should use memoized cache.");
    }

    public function test_role_mutation_invalidates_request_authorization_cache()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role1 = Role::where('name', 'User')->first();
        $role2 = Role::where('is_superadmin', true)->first();
        
        $user->roles()->attach($role1->id);
        
        // Load cache
        $this->assertFalse($user->isSuperAdmin());
        
        // Mutate roles (this simulates what the controller does)
        $user->roles()->sync([$role2->id]);
        $user->clearAuthorizationCache();
        
        // Cache should be cleared and re-evaluated
        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_multi_role_permissions_are_cached_as_union()
    {
        $user = User::factory()->create(['is_active' => true]);
        $role1 = Role::where('name', 'User')->first();
        $role2 = Role::where('name', 'Admin')->first();
        $user->roles()->attach([$role1->id, $role2->id]);
        
        $perm1 = Permission::firstOrCreate(['permission_key' => 'perm.one']);
        $perm2 = Permission::firstOrCreate(['permission_key' => 'perm.two']);
        $role1->permissions()->attach($perm1->id);
        $role2->permissions()->attach($perm2->id);
        
        // Both should be true
        $this->assertTrue($user->hasPermission('perm.one'));
        $this->assertTrue($user->hasPermission('perm.two'));
        $this->assertFalse($user->hasPermission('perm.three'));
    }
}
