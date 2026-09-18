<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;
use App\Models\Permission;

class SchedulerMenuSeeder extends Seeder
{
    public function run()
    {
        // First ensure the permission exists
        $permission = Permission::firstOrCreate(
            ['name' => 'sync_engine.manage'],
            ['group' => 'Sync Engine', 'description' => 'Manage sync engine & schedules']
        );

        $menu = Menu::firstOrCreate(
            ['route_name' => 'scheduler.index'],
            [
                'title' => 'Scheduler',
                'icon' => 'schedule',
                'is_active' => true,
                'is_visible' => true,
                'sort_order' => 25, // Place it somewhere appropriate
            ]
        );

        $menu->permissions()->syncWithoutDetaching([$permission->id]);
    }
}
