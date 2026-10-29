<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class DefaultMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sort = 0;

        // === 1. Dashboard ===
        Menu::updateOrCreate(
            ['route_name' => 'dashboard'],
            [
                'parent_id' => null,
                'title' => 'Dashboard',
                'icon' => 'dashboard',
                'sort_order' => $sort += 10,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => null,
            ]
        );

        // === 2. ChatEDP ===
        Menu::updateOrCreate(
            ['route_name' => 'chatedp.index'],
            [
                'parent_id' => null,
                'title' => 'ChatEDP',
                'icon' => 'smart_toy',
                'sort_order' => $sort += 10,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'chatedp.access',
            ]
        );

        // === 3. Report (Parent) ===
        $report = Menu::updateOrCreate(
            ['title' => 'Report', 'parent_id' => null],
            [
                'icon' => 'analytics',
                'route_name' => null,
                'sort_order' => $sort += 10,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => null,
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'report.builder'],
            [
                'parent_id' => $report->id,
                'title' => 'Report Builder',
                'icon' => 'build',
                'sort_order' => 10,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'report_builder.view',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'endpoint.api.index'],
            [
                'parent_id' => $report->id,
                'title' => 'Endpoint API',
                'icon' => 'api',
                'sort_order' => 20,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'endpoint_api.view',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'query.builder'],
            [
                'parent_id' => $report->id,
                'title' => 'Query Builder',
                'icon' => 'data_object',
                'sort_order' => 30,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'query_builder.view',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'query-repository.index'],
            [
                'parent_id' => $report->id,
                'title' => 'Query Repository',
                'icon' => 'database',
                'sort_order' => 40,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'query_repository.view',
            ]
        );

        // === 4. Synchronization (Parent) ===
        $sync = Menu::updateOrCreate(
            ['title' => 'Synchronization', 'parent_id' => null],
            [
                'icon' => 'sync',
                'route_name' => null,
                'sort_order' => $sort += 10,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => null,
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'master-data.index'],
            [
                'parent_id' => $sync->id,
                'title' => 'Master Data',
                'icon' => 'folder',
                'sort_order' => 10,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'sync_engine.manage',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'transactions.index'],
            [
                'parent_id' => $sync->id,
                'title' => 'Transaction',
                'icon' => 'receipt',
                'sort_order' => 20,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'sync_engine.manage',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'scheduler.index'],
            [
                'parent_id' => $sync->id,
                'title' => 'Scheduler',
                'icon' => 'schedule',
                'sort_order' => 30,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'sync_engine.view',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'sync-history.index'],
            [
                'parent_id' => $sync->id,
                'title' => 'Sync History',
                'icon' => 'history',
                'sort_order' => 40,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'sync_engine.view',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'logs.index'],
            [
                'parent_id' => $sync->id,
                'title' => 'Logs',
                'icon' => 'article',
                'sort_order' => 50,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'logs.view',
            ]
        );

        // === 5. Settings (Parent) ===
        $settings = Menu::updateOrCreate(
            ['title' => 'Settings', 'parent_id' => null],
            [
                'icon' => 'settings',
                'route_name' => null,
                'sort_order' => $sort += 10,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => null,
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'settings.index'],
            [
                'parent_id' => $settings->id,
                'title' => 'General Settings',
                'icon' => 'tune',
                'sort_order' => 10,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'settings.view',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'users.index'],
            [
                'parent_id' => $settings->id,
                'title' => 'Users & Roles',
                'icon' => 'group',
                'sort_order' => 20,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'users.view',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'security-monitor.index'],
            [
                'parent_id' => $settings->id,
                'title' => 'Security & Access Monitor',
                'icon' => 'shield_lock',
                'sort_order' => 25,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'users.view',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'menu-management.index'],
            [
                'parent_id' => $settings->id,
                'title' => 'Menu Management',
                'icon' => 'account_tree',
                'sort_order' => 30,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'settings.manage',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'settings.system-utilities'],
            [
                'parent_id' => $settings->id,
                'title' => 'System Utilities',
                'icon' => 'terminal',
                'sort_order' => 40,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'settings.manage',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'settings.ai.openrouter'],
            [
                'parent_id' => $settings->id,
                'title' => 'OpenRouter AI',
                'icon' => 'smart_toy',
                'sort_order' => 50,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'ai_settings.view',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'settings.ai.metadata'],
            [
                'parent_id' => $settings->id,
                'title' => 'AI Metadata',
                'icon' => 'schema',
                'sort_order' => 60,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'ai_metadata.view',
            ]
        );

        Menu::updateOrCreate(
            ['route_name' => 'settings.chatedp.analytics'],
            [
                'parent_id' => $settings->id,
                'title' => 'ChatEDP Analytics',
                'icon' => 'query_stats',
                'sort_order' => 70,
                'type' => 'link',
                'is_active' => true,
                'is_visible' => true,
                'permission_key' => 'settings.manage',
            ]
        );

        Cache::forget('sidebar_menus');
    }
}
