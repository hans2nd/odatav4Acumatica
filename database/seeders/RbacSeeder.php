<?php

namespace Database\Seeders;

use App\Models\Action;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
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
            ['code' => 'manage', 'name' => 'Manage', 'sort_order' => 90], // Generic action for settings, etc.
        ];

        foreach ($actions as $action) {
            Action::updateOrCreate(['code' => $action['code']], $action);
        }

        // 3. Roles
        $adminRole = Role::updateOrCreate(['name' => 'Administrator'], [
            'description' => 'System Administrator with full access'
        ]);

        Role::updateOrCreate(['name' => 'Operator'], [
            'description' => 'Standard system operator'
        ]);

        Role::updateOrCreate(['name' => 'Viewer'], [
            'description' => 'Read-only access'
        ]);

        // Assgin Admin role to all existing users for now
        $users = User::all();
        foreach ($users as $user) {
            $user->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        // 4. Generate Permissions
        $generator = new PermissionGeneratorService();
        $generator->generate();
    }
}