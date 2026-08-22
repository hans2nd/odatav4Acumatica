<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class DefaultMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sort = 0;

        // === Dashboard ===
        Menu::updateOrCreate(
            ['title' => 'Dashboard', 'parent_id' => null],
            [
                'icon' => 'dashboard',
                'route_name' => 'dashboard',
                'sort_order' => $sort += 10,
                'type' => 'link',
            ]
        );

        // === Report ===
        $report = Menu::updateOrCreate(
            ['title' => 'Report', 'parent_id' => null],
            [
                'icon' => 'analytics',
                'route_name' => null,
                'sort_order' => $sort += 10,
                'type' => 'link',
            ]
        );

        Menu::updateOrCreate(
            ['title' => 'Report Builder', 'parent_id' => $report->id],
            [
                'icon' => 'build',
                'route_name' => 'report.builder',
                'sort_order' => 10,
                'type' => 'link',
            ]
        );

        Menu::updateOrCreate(
            ['title' => 'Endpoint API', 'parent_id' => $report->id],
            [
                'icon' => 'api',
                'route_name' => 'endpoint.api.index',
                'sort_order' => 20,
                'type' => 'link',
            ]
        );

        Menu::updateOrCreate(
            ['title' => 'Query Builder', 'parent_id' => $report->id],
            [
                'icon' => 'data_object',
                'route_name' => 'query.builder',
                'sort_order' => 30,
                'type' => 'link',
            ]
        );

        Menu::updateOrCreate(
            ['title' => 'Query Repository', 'parent_id' => $report->id],
            [
                'icon' => 'database',
                'route_name' => 'query-repository.index',
                'sort_order' => 40,
                'type' => 'link',
            ]
        );

        // === Synchronization ===
        $sync = Menu::updateOrCreate(
            ['title' => 'Synchronization', 'parent_id' => null],
            [
                'icon' => 'sync',
                'route_name' => null,
                'sort_order' => $sort += 10,
                'type' => 'link',
            ]
        );

        Menu::updateOrCreate(
            ['title' => 'Master Data', 'parent_id' => $sync->id],
            [
                'icon' => 'folder',
                'route_name' => 'master-data.index',
                'sort_order' => 10,
                'type' => 'link',
            ]
        );

        Menu::updateOrCreate(
            ['title' => 'Transaction', 'parent_id' => $sync->id],
            [
                'icon' => 'receipt',
                'route_name' => 'transactions.index',
                'sort_order' => 20,
                'type' => 'link',
            ]
        );

        Menu::updateOrCreate(
            ['title' => 'Logs', 'parent_id' => $sync->id],
            [
                'icon' => 'article',
                'route_name' => 'logs.index',
                'sort_order' => 30,
                'type' => 'link',
            ]
        );

        // === Settings ===
        $settings = Menu::updateOrCreate(
            ['title' => 'Settings', 'parent_id' => null],
            [
                'icon' => 'settings',
                'route_name' => null,
                'sort_order' => $sort += 10,
                'type' => 'link',
            ]
        );

        Menu::updateOrCreate(
            ['title' => 'General Settings', 'parent_id' => $settings->id],
            [
                'icon' => 'tune',
                'route_name' => 'settings.index',
                'sort_order' => 10,
                'type' => 'link',
            ]
        );

        Menu::updateOrCreate(
            ['title' => 'Users & Roles', 'parent_id' => $settings->id],
            [
                'icon' => 'group',
                'route_name' => 'users.index',
                'sort_order' => 20,
                'type' => 'link',
            ]
        );

        Menu::updateOrCreate(
            ['title' => 'Menu Management', 'parent_id' => $settings->id],
            [
                'icon' => 'account_tree',
                'route_name' => 'menu-management.index',
                'sort_order' => 30,
                'type' => 'link',
            ]
        );

        Menu::updateOrCreate(
            ['title' => 'System Utilities', 'parent_id' => $settings->id],
            [
                'icon' => 'terminal',
                'route_name' => 'settings.system-utilities',
                'sort_order' => 40,
                'type' => 'link',
            ]
        );
    }
}
