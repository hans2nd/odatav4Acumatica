<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\PermissionSyncService;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create roles
        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin', 'description' => 'Full system access', 'is_superadmin' => true]
        );

        $operator = Role::firstOrCreate(
            ['name' => 'operator'],
            ['display_name' => 'Operator', 'description' => 'Can view, sync, and export data']
        );

        $viewer = Role::firstOrCreate(
            ['name' => 'viewer'],
            ['display_name' => 'Viewer', 'description' => 'Read-only access to data']
        );

        // 2. Sync all permissions (static + dynamic from entities)
        $permService = app(PermissionSyncService::class);
        $permService->fullSync();

        // 3. Assign static permissions to operator & viewer
        $staticPermsOperator = \App\Models\Permission::whereIn('name', PermissionSyncService::ROLE_DEFAULTS['operator']['static'])->pluck('id');
        $operator->permissions()->syncWithoutDetaching($staticPermsOperator);

        $staticPermsViewer = \App\Models\Permission::whereIn('name', PermissionSyncService::ROLE_DEFAULTS['viewer']['static'])->pluck('id');
        $viewer->permissions()->syncWithoutDetaching($staticPermsViewer);

        // 4. Create sample users
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@plsync.com'],
            ['name' => 'Administrator', 'password' => 'password']
        );
        $adminUser->roles()->syncWithoutDetaching([$admin->id]);

        $operatorUser = User::firstOrCreate(
            ['email' => 'operator@plsync.com'],
            ['name' => 'Operator User', 'password' => 'password']
        );
        $operatorUser->roles()->syncWithoutDetaching([$operator->id]);

        $viewerUser = User::firstOrCreate(
            ['email' => 'viewer@plsync.com'],
            ['name' => 'Viewer User', 'password' => 'password']
        );
        $viewerUser->roles()->syncWithoutDetaching([$viewer->id]);

        $this->command->info('RBAC seeded: 3 roles, ' . \App\Models\Permission::count() . ' permissions, 3 users.');
    }
}
