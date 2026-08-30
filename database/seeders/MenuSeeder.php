<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing menus
        Menu::query()->delete();

        $sort = 0;

        // === 1. Dashboard ===
        Menu::create([
            'parent_id' => null,
            'title' => 'Dashboard',
            'icon' => 'dashboard',
            'route_name' => 'dashboard',
            'sort_order' => $sort += 10,
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
            'permission_key' => null,
        ]);

        // === 2. ChatEDP ===
        Menu::create([
            'parent_id' => null,
            'title' => 'ChatEDP',
            'icon' => 'smart_toy',
            'route_name' => 'chatedp.index',
            'sort_order' => $sort += 10,
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
            'permission_key' => 'chatedp.access',
        ]);

        // === 3. Report (Parent) ===
        $report = Menu::create([
            'parent_id' => null,
            'title' => 'Report',
            'icon' => 'analytics',
            'route_name' => null,
            'sort_order' => $sort += 10,
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
            'permission_key' => null,
        ]);

        Menu::create([
            'parent_id' => $report->id,
            'title' => 'Report Builder',
            'icon' => 'build',
            'route_name' => 'report.builder',
            'sort_order' => 10,
            'permission_key' => 'report_builder.view',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $report->id,
            'title' => 'Endpoint API',
            'icon' => 'api',
            'route_name' => 'endpoint.api.index',
            'sort_order' => 20,
            'permission_key' => 'endpoint_api.view',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $report->id,
            'title' => 'Query Builder',
            'icon' => 'data_object',
            'route_name' => 'query.builder',
            'sort_order' => 30,
            'permission_key' => 'query_builder.view',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $report->id,
            'title' => 'Query Repository',
            'icon' => 'database',
            'route_name' => 'query-repository.index',
            'sort_order' => 40,
            'permission_key' => 'query_repository.view',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        // === 4. Synchronization (Parent) ===
        $sync = Menu::create([
            'parent_id' => null,
            'title' => 'Synchronization',
            'icon' => 'sync',
            'route_name' => null,
            'sort_order' => $sort += 10,
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
            'permission_key' => null,
        ]);

        Menu::create([
            'parent_id' => $sync->id,
            'title' => 'Master Data',
            'icon' => 'folder',
            'route_name' => 'master-data.index',
            'sort_order' => 10,
            'permission_key' => 'sync_engine.manage',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $sync->id,
            'title' => 'Transaction',
            'icon' => 'receipt',
            'route_name' => 'transactions.index',
            'sort_order' => 20,
            'permission_key' => 'sync_engine.manage',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $sync->id,
            'title' => 'Scheduler',
            'icon' => 'schedule',
            'route_name' => 'scheduler.index',
            'sort_order' => 30,
            'permission_key' => 'sync_engine.manage',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $sync->id,
            'title' => 'Sync History',
            'icon' => 'history',
            'route_name' => 'sync-history.index',
            'sort_order' => 40,
            'permission_key' => 'sync_engine.manage',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $sync->id,
            'title' => 'Logs',
            'icon' => 'article',
            'route_name' => 'logs.index',
            'sort_order' => 50,
            'permission_key' => 'logs.view',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        // === 5. Settings (Parent) ===
        $settings = Menu::create([
            'parent_id' => null,
            'title' => 'Settings',
            'icon' => 'settings',
            'route_name' => null,
            'sort_order' => $sort += 10,
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
            'permission_key' => null,
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'title' => 'General Settings',
            'icon' => 'tune',
            'route_name' => 'settings.index',
            'sort_order' => 10,
            'permission_key' => 'settings.view',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'title' => 'Users & Roles',
            'icon' => 'group',
            'route_name' => 'users.index',
            'sort_order' => 20,
            'permission_key' => 'users.view',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'title' => 'Menu Management',
            'icon' => 'account_tree',
            'route_name' => 'menu-management.index',
            'sort_order' => 30,
            'permission_key' => 'settings.manage',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'title' => 'System Utilities',
            'icon' => 'terminal',
            'route_name' => 'settings.system-utilities',
            'sort_order' => 40,
            'permission_key' => 'settings.manage',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'title' => 'OpenRouter AI',
            'icon' => 'smart_toy',
            'route_name' => 'settings.ai.openrouter',
            'sort_order' => 50,
            'permission_key' => 'ai_settings.view',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'title' => 'AI Metadata',
            'icon' => 'schema',
            'route_name' => 'settings.ai.metadata',
            'sort_order' => 60,
            'permission_key' => 'ai_metadata.view',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Menu::create([
            'parent_id' => $settings->id,
            'title' => 'ChatEDP Analytics',
            'icon' => 'query_stats',
            'route_name' => 'settings.chatedp.analytics',
            'sort_order' => 70,
            'permission_key' => 'settings.manage',
            'type' => 'link',
            'is_active' => true,
            'is_visible' => true,
        ]);

        Cache::forget('sidebar_menus');
    }
}
