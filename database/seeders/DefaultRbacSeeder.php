<?php

namespace Database\Seeders;

use App\Models\Action;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultRbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Modules
        $modules = [
            ['code' => 'dashboard', 'name' => 'Dashboard', 'icon' => 'dashboard', 'sort_order' => 10],
            ['code' => 'master-data', 'name' => 'Master Data', 'icon' => 'folder', 'sort_order' => 20],
            ['code' => 'transaction', 'name' => 'Transaction', 'icon' => 'receipt', 'sort_order' => 30],
            ['code' => 'sync', 'name' => 'Synchronization', 'icon' => 'sync', 'sort_order' => 40],
            ['code' => 'report', 'name' => 'Report', 'icon' => 'analytics', 'sort_order' => 50],
            ['code' => 'settings', 'name' => 'Settings', 'icon' => 'settings', 'sort_order' => 60],
            ['code' => 'system', 'name' => 'System', 'icon' => 'memory', 'sort_order' => 70],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(['code' => $module['code']], $module);
        }

        // 2. Actions
        $actions = [
            ['code' => 'view', 'name' => 'View', 'sort_order' => 10],
            ['code' => 'create', 'name' => 'Create', 'sort_order' => 20],
            ['code' => 'update', 'name' => 'Update', 'sort_order' => 30],
            ['code' => 'delete', 'name' => 'Delete', 'sort_order' => 40],
            ['code' => 'export', 'name' => 'Export', 'sort_order' => 50],
            ['code' => 'import', 'name' => 'Import', 'sort_order' => 60],
            ['code' => 'sync', 'name' => 'Sync', 'sort_order' => 70],
            ['code' => 'execute', 'name' => 'Execute', 'sort_order' => 80],
            ['code' => 'manage', 'name' => 'Manage', 'sort_order' => 90],
            ['code' => 'access', 'name' => 'Access', 'sort_order' => 100],
            ['code' => 'test', 'name' => 'Test', 'sort_order' => 110],
        ];

        foreach ($actions as $action) {
            Action::updateOrCreate(['code' => $action['code']], $action);
        }

        // 3. Roles
        $adminRole = Role::updateOrCreate(
            ['name' => 'Super Administrator'],
            [
                'description' => 'System Administrator with full access',
                'is_active' => true,
                'is_superadmin' => true,
            ]
        );

        $operatorRole = Role::updateOrCreate(
            ['name' => 'Operator'],
            [
                'description' => 'Standard system operator',
                'is_active' => true,
            ]
        );

        $viewerRole = Role::updateOrCreate(
            ['name' => 'Viewer'],
            [
                'description' => 'Read-only access',
                'is_active' => true,
            ]
        );

        // 4. Default Administrator Account
        $adminEmail = env('DEFAULT_ADMIN_EMAIL', 'admin@plsync.com');
        $adminPassword = env('DEFAULT_ADMIN_PASSWORD', 'password');

        $adminUser = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($adminPassword),
            ]
        );

        // Assign Role to User
        $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);

        // 5. Generate Permissions
        $generator = new PermissionGeneratorService();
        $generator->generate();

        // 6. Assign All Permissions to Super Administrator
        $allPermissionIds = Permission::pluck('id');
        $adminRole->permissions()->sync($allPermissionIds);
    }
}

