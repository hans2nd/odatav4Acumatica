<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing menus
        Menu::query()->delete();

        $sort = 0;

        // === Dashboard (top-level, no children) ===
        Menu::create([
            'title' => 'Dashboard',
            'icon' => 'dashboard',
            'route_name' => 'dashboard',
            'sort_order' => $sort += 10,
            'type' => 'link',
        ]);

        // === Report (parent) ===
        $report = Menu::create([
            'title' => 'Report',
            'icon' => 'analytics',
            'sort_order' => $sort += 10,
            'type' => 'link',
        ]);

        Menu::create([
            'parent_id' => $report->id,
            'title' => 'Report Builder',
            'icon' => 'build',
            'route_name' => 'report.builder',
            'sort_order' => 10,
            'permission_key' => 'report_builder.view',
            'type' => 'link',
        ]);

        Menu::create([
            'parent_id' => $report->id,
            'title' => 'Endpoint API',
            'icon' => 'api',
            'route_name' => 'endpoint.api.index',
            'sort_order' => 20,
            'permission_key' => 'endpoint_api.view',
            'type' => 'link',
        ]);

        Menu::create([
            'parent_id' => $report->id,
            'title' => 'Query Builder',
            'icon' => 'data_object',
            'route_name' => 'query.builder',
            'sort_order' => 30,
            'permission_key' => 'query_builder.view',
            'type' => 'link',
        ]);

        Menu::create([
            'parent_id' => $report->id,
            'title' => 'Query Repository',
            'icon' => 'database',
            'route_name' => 'query-repository.index',
            'sort_order' => 40,
            'permission_key' => 'query_repository.view',
            'type' => 'link',
        ]);

        // === Synchronization (parent) ===
        $sync = Menu::create([
            'title' => 'Synchronization',
            'icon' => 'sync',
            'sort_order' => $sort += 10,
            'type' => 'link',
        ]);

        Menu::create([
            'parent_id' => $sync->id,
            'title' => 'Master Data',
            'icon' => 'folder',
            'route_name' => 'master-data.index',
            'sort_order' => 10,
            'type' => 'link',
        ]);

        Menu::create([
            'parent_id' => $sync->id,
            'title' => 'Transaction',
            'icon' => 'receipt',
            'route_name' => 'transactions.index',
            'sort_order' => 20,
            'type' => 'link',
        ]);

        Menu::create([
            'parent_id' => $sync->id,
            'title' => 'Logs',
            'icon' => 'article',
            'route_name' => 'logs.index',
            'sort_order' => 30,
            'permission_key' => 'logs.view',
            'type' => 'link',
        ]);


        // === Settings (parent) ===
        $settings = Menu::create([
            'title' => 'Settings',
            'icon' => 'settings',
            'sort_order' => $sort += 10,
            'type' => 'link',
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'title' => 'General Settings',
            'icon' => 'tune',
            'route_name' => 'settings.index',
            'sort_order' => 10,
            'permission_key' => 'settings.view',
            'type' => 'link',
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'title' => 'Users & Roles',
            'icon' => 'group',
            'route_name' => 'users.index',
            'sort_order' => 20,
            'permission_key' => 'users.view',
            'type' => 'link',
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'title' => 'Menu Management',
            'icon' => 'account_tree',
            'route_name' => 'menu-management.index',
            'sort_order' => 30,
            'permission_key' => 'settings.manage',
            'type' => 'link',
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'title' => 'System Utilities',
            'icon' => 'terminal',
            'route_name' => 'settings.system-utilities',
            'sort_order' => 40,
            'permission_key' => 'settings.manage',
            'type' => 'link',
        ]);
    }
}
