<?php

namespace Tests\Feature\RBAC;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\RbacAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class P4RbacAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::firstOrCreate(['name' => 'Super Administrator', 'is_superadmin' => true, 'is_active' => true]);
        Role::firstOrCreate(['name' => 'Admin', 'is_superadmin' => false, 'is_active' => true]);
        Role::firstOrCreate(['name' => 'User', 'is_superadmin' => false, 'is_active' => true]);
    }

    public function test_user_role_change_creates_audit_with_before_after()
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $superAdminRole = Role::where('is_superadmin', true)->first();
        $superAdmin->roles()->attach($superAdminRole->id);

        $this->actingAs($superAdmin);

        $user = User::factory()->create(['is_active' => true]);
        $role1 = Role::where('name', 'User')->first();
        $role2 = Role::where('name', 'Admin')->first();

        // Initial setup
        $user->roles()->attach($role1->id);
        
        // Emulate controller action: changing User to Admin + User
        app(\App\Services\RbacAuditService::class)->auditUserRoleChange($user, [$role1->id], [$role1->id, $role2->id]);

        $log = RbacAuditLog::where('action', 'USER_ROLE_CHANGED')
            ->where('target_user_id', $user->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($superAdmin->id, $log->actor_user_id);
        $this->assertEquals([$role1->id], $log->before_state['role_ids']);
        $this->assertEquals([$role1->id, $role2->id], $log->after_state['role_ids']);
        $this->assertEquals([$role2->id], $log->after_state['added']);
    }

    public function test_denied_superadmin_assignment_creates_denied_audit()
    {
        $admin = User::factory()->create(['is_active' => true]);
        $adminRole = Role::where('name', 'Admin')->first();
        $admin->roles()->attach($adminRole->id);

        $this->actingAs($admin);

        // Record a denied attempt directly as the controller would before aborting
        app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Unauthorized Super Administrator role assignment in updateUser', 999);

        $log = RbacAuditLog::where('action', 'RBAC_AUTHORIZATION_DENIED')
            ->where('target_user_id', 999)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->actor_user_id);
        $this->assertEquals('Unauthorized Super Administrator role assignment in updateUser', $log->after_state['reason']);
    }

    public function test_sensitive_fields_are_redacted()
    {
        $payload = [
            'name' => 'John',
            'password' => 'secret123',
            'api_token' => 'token456'
        ];

        // Access protected redact method via reflection
        $service = app(\App\Services\RbacAuditService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('redact');
        $method->setAccessible(true);

        $redacted = $method->invokeArgs($service, [$payload]);

        $this->assertArrayHasKey('name', $redacted);
        $this->assertArrayNotHasKey('password', $redacted);
        $this->assertArrayNotHasKey('api_token', $redacted);
    }

    public function test_permission_change_records_delta()
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $this->actingAs($superAdmin);

        $role = Role::where('name', 'User')->first();
        $perm1 = Permission::firstOrCreate(['permission_key' => 'perm.one']);
        $perm2 = Permission::firstOrCreate(['permission_key' => 'perm.two']);

        app(\App\Services\RbacAuditService::class)->auditPermissionChange($role, [$perm1->id], [$perm2->id]);

        $logGranted = RbacAuditLog::where('action', 'PERMISSION_GRANTED')->first();
        $this->assertNotNull($logGranted);
        $this->assertEquals([$perm2->id], $logGranted->after_state['permission_ids']);

        $logRevoked = RbacAuditLog::where('action', 'PERMISSION_REVOKED')->first();
        $this->assertNotNull($logRevoked);
        $this->assertEquals([$perm1->id], $logRevoked->before_state['permission_ids']);
    }

    public function test_superadmin_role_assignment_creates_audit()
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $this->actingAs($superAdmin);

        $user = User::factory()->create(['is_active' => true]);
        $superAdminRole = Role::where('is_superadmin', true)->first();
        
        // From nothing to Superadmin
        app(\App\Services\RbacAuditService::class)->auditUserRoleChange($user, [], [$superAdminRole->id]);

        $log = RbacAuditLog::where('action', 'SUPERADMIN_ROLE_ASSIGNED')->first();
        $this->assertNotNull($log);
        $this->assertEquals([$superAdminRole->id], $log->after_state['role_ids']);
    }

    public function test_user_activation_creates_audit()
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $this->actingAs($superAdmin);

        $user = User::factory()->create(['is_active' => false]);
        
        app(\App\Services\RbacAuditService::class)->auditUserStatusChange($user, false, true);

        $log = RbacAuditLog::where('action', 'USER_ACTIVATED')->first();
        $this->assertNotNull($log);
        $this->assertFalse($log->before_state['is_active']);
        $this->assertTrue($log->after_state['is_active']);
    }
    public function test_user_created_audit()
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $this->actingAs($superAdmin);

        $user = clone $superAdmin;
        $user->id = 999;
        $user->name = 'New User';

        app(\App\Services\RbacAuditService::class)->auditUserCreated($user);

        $log = RbacAuditLog::where('action', 'USER_CREATED')->first();
        $this->assertNotNull($log);
        $this->assertEquals($user->id, $log->after_state['id']);
    }

    public function test_user_deleted_audit()
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $this->actingAs($superAdmin);

        $user = clone $superAdmin;
        $user->id = 999;

        app(\App\Services\RbacAuditService::class)->auditUserDeleted($user);

        $log = RbacAuditLog::where('action', 'USER_DELETED')->first();
        $this->assertNotNull($log);
        $this->assertEquals($user->id, $log->before_state['id']);
    }

    public function test_role_created_audit()
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $this->actingAs($superAdmin);

        $role = Role::firstOrCreate(['name' => 'Test Role', 'is_active' => true]);

        app(\App\Services\RbacAuditService::class)->auditRoleCreated($role);

        $log = RbacAuditLog::where('action', 'ROLE_CREATED')->first();
        $this->assertNotNull($log);
        $this->assertEquals($role->id, $log->after_state['id']);
    }

    public function test_role_deleted_audit()
    {
        $superAdmin = User::factory()->create(['is_active' => true]);
        $this->actingAs($superAdmin);

        $role = Role::firstOrCreate(['name' => 'Test Role', 'is_active' => true]);

        app(\App\Services\RbacAuditService::class)->auditRoleDeleted($role);

        $log = RbacAuditLog::where('action', 'ROLE_DELETED')->first();
        $this->assertNotNull($log);
        $this->assertEquals($role->id, $log->before_state['id']);
    }
}
