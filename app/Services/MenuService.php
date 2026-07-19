<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Module;
use Illuminate\Support\Facades\Cache;

class MenuService
{
    /**
     * Get dynamic menus for the authenticated user based on their permissions.
     */
    public function getUserMenus($user)
    {
        if (!$user) {
            return [];
        }

        $userPermissionKeys = $user->isSuperAdmin()
            ? collect(['*']) // Admin gets all
            : $this->getUserPermissionKeys($user);

        // Fetch Modules ordered by sort_order
        $modules = Module::where('is_active', true)->orderBy('sort_order')->get();
        $menuStructure = [];

        foreach ($modules as $module) {
                // Determine if we should show this module
                // A module is shown if it has menus the user can see, OR resources the user has access to.
                
                // For Master Data and Transaction, we can dynamically build submenus from resources!
                $submenus = [];
                
                if ($module->code === 'master-data' || $module->code === 'transaction') {
                    // Skip rendering as top-level modules, they are now under 'sync'
                    continue;
                }

                // If module is 'report', add static report builder and dynamic endpoints
                if ($module->code === 'report') {
                    if ($userPermissionKeys->contains('*') || $userPermissionKeys->contains('report_builder.view') || $userPermissionKeys->contains('report_builder.manage')) {
                        $submenus[] = ['route' => 'report.builder', 'label' => 'Report Builder', 'key' => 'report.builder'];
                    }
                    if ($userPermissionKeys->contains('*') || $userPermissionKeys->contains('endpoint_api.view') || $userPermissionKeys->contains('endpoint_api.manage')) {
                        $submenus[] = ['route' => 'endpoint.api.index', 'label' => 'Endpoint API', 'key' => 'endpoint.api'];
                    }
                    if ($userPermissionKeys->contains('*') || $userPermissionKeys->contains('query_builder.view') || $userPermissionKeys->contains('query_builder.manage')) {
                        $submenus[] = ['route' => 'query.builder', 'label' => 'Query Builder', 'key' => 'query.builder'];
                    }
                    
                    // Additional dynamic reports
                    $syncedTransactions = \App\Http\Controllers\DynamicReportController::getSyncedTransactionEntities();
                    foreach ($syncedTransactions as $eKey => $eConfig) {
                        if ($userPermissionKeys->contains('*') || $userPermissionKeys->contains("{$eKey}.view")) {
                            $submenus[] = [
                                'route' => 'report.dynamic.show',
                                'route_param' => $eKey,
                                'label' => $eConfig['description'] ?? $eKey,
                                'key' => 'report.' . $eKey,
                            ];
                        }
                    }
                }

                if ($module->code === 'sync') {
                    // Inject Master Data
                    $masterDataModule = $modules->where('code', 'master-data')->first();
                    if ($masterDataModule) {
                        $hasMasterAccess = false;
                        foreach ($masterDataModule->resources as $resource) {
                            if ($userPermissionKeys->contains('*') || $userPermissionKeys->contains("{$resource->slug}.view")) {
                                $hasMasterAccess = true;
                                break;
                            }
                        }
                        if ($hasMasterAccess) {
                            $submenus[] = ['route' => 'master-data.index', 'label' => 'Master Data', 'key' => 'master-data'];
                        }
                    }

                    // Inject Transaction
                    $transactionModule = $modules->where('code', 'transaction')->first();
                    if ($transactionModule) {
                        $hasTransAccess = false;
                        foreach ($transactionModule->resources as $resource) {
                            if ($userPermissionKeys->contains('*') || $userPermissionKeys->contains("{$resource->slug}.view")) {
                                $hasTransAccess = true;
                                break;
                            }
                        }
                        if ($hasTransAccess) {
                            $submenus[] = ['route' => 'transactions.index', 'label' => 'Transaction', 'key' => 'transaction'];
                        }
                    }

                    if ($userPermissionKeys->contains('*') || $userPermissionKeys->contains('logs.view')) {
                        $submenus[] = ['route' => 'logs.index', 'label' => 'Logs', 'key' => 'logs'];
                    }
                }

                if ($module->code === 'settings') {
                    if ($userPermissionKeys->contains('*') || $userPermissionKeys->contains('settings.view') || $userPermissionKeys->contains('settings.manage')) {
                        $submenus[] = ['route' => 'settings.index', 'label' => 'General Settings', 'key' => 'settings'];
                    }
                    if ($userPermissionKeys->contains('*') || $userPermissionKeys->contains('users.view') || $userPermissionKeys->contains('users.manage')) {
                        $submenus[] = ['route' => 'users.index', 'label' => 'Users & Roles', 'key' => 'users'];
                    }
                }

                if ($module->code === 'dashboard') {
                    $menuStructure[] = [
                        'route' => 'dashboard',
                        'icon' => $module->icon,
                        'label' => $module->name,
                        'key' => $module->code,
                    ];
                    continue; // No submenus for dashboard
                }

                // Only add module if it has submenus
                if (count($submenus) > 0) {
                    $menuStructure[] = [
                        'label' => $module->name,
                        'icon' => $module->icon,
                        'key' => $module->code,
                        'submenus' => $submenus,
                    ];
                }
            }

        return $menuStructure;
    }

    private function getUserPermissionKeys($user)
    {
        $roleIds = $user->roles()->pluck('roles.id');
        return \App\Models\Permission::whereHas('roles', function ($q) use ($roleIds) {
            $q->whereIn('roles.id', $roleIds);
        })->pluck('permission_key');
    }
}