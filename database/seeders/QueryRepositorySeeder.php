<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Module;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QueryRepositorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Get the 'Report' module
        $reportModule = Module::where('code', 'report')->first();
        if (!$reportModule) {
            $this->command->error('Report module not found. Run RbacSeeder first.');
            return;
        }

        // 2. Find parent menu 'Report Builder' or 'Query Builder' to put it nearby
        // Actually, we can put it as a root menu under the Report module, or under a parent. 
        // According to user request: Query Repository harus menjadi submenu dari menu "Report".
        // Let's assume there is a main menu named 'Report' or we just place it under module 'Report'.
        
        // Find existing Report menu parent if exists
        $parentMenu = Menu::where('module_id', $reportModule->id)
                          ->where('route_name', '#')
                          ->whereNull('parent_id')
                          ->first();

        // 3. Create permissions
        $permissions = [
            ['name' => 'View Query Repository', 'code' => 'query-repository.view', 'group_name' => 'Query Repository', 'module_id' => $reportModule->id],
            ['name' => 'Create Query', 'code' => 'query-repository.create', 'group_name' => 'Query Repository', 'module_id' => $reportModule->id],
            ['name' => 'Edit Query', 'code' => 'query-repository.edit', 'group_name' => 'Query Repository', 'module_id' => $reportModule->id],
            ['name' => 'Delete Query', 'code' => 'query-repository.delete', 'group_name' => 'Query Repository', 'module_id' => $reportModule->id],
            ['name' => 'Preview Query', 'code' => 'query-repository.preview', 'group_name' => 'Query Repository', 'module_id' => $reportModule->id],
        ];

        $permissionIds = [];
        foreach ($permissions as $perm) {
            $permissionIds[] = Permission::updateOrCreate(
                ['code' => $perm['code']],
                array_merge($perm, ['uuid' => (string) Str::uuid(), 'guard_name' => 'web'])
            )->id;
        }

        // 4. Create the Menu
        $menu = Menu::updateOrCreate(
            ['route_name' => 'query-repository.index'],
            [
                'parent_id' => $parentMenu ? $parentMenu->id : null,
                'module_id' => $reportModule->id,
                'title' => 'Query Repository',
                'icon' => 'data_object',
                'sort_order' => 15,
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'query-repository.view',
                'type' => 'link',
            ]
        );

        // 5. Attach permissions to menu (for Menu Management UI sync)
        $menu->permissions()->sync($permissionIds);

        $this->command->info('Query Repository menu and permissions seeded successfully.');
    }
}
