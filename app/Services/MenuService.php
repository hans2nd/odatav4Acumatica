<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Facades\Cache;

class MenuService
{
    /**
     * Invalidate menu cache globally across all users.
     */
    public static function clearMenuCache(): void
    {
        try {
            $ver = (int) Cache::get('menus_version', 1);
            Cache::put('menus_version', $ver + 1, 86400 * 30);
        } catch (\Throwable $e) {
            Cache::forget('menus_version');
        }
        try {
            $globalVer = (int) Cache::get('global_perm_ver', 1);
            Cache::put('global_perm_ver', $globalVer + 1, 86400 * 30);
        } catch (\Throwable $e) {
            Cache::forget('global_perm_ver');
        }
        Cache::forget('sidebar_menus');
    }

    /**
     * Invalidate permission and menu cache for a specific user (or all if null).
     */
    public static function clearUserCache(?int $userId = null): void
    {
        if ($userId) {
            try {
                $ver = (int) Cache::get("user_perm_ver_{$userId}", 1);
                Cache::put("user_perm_ver_{$userId}", $ver + 1, 86400 * 30);
            } catch (\Throwable $e) {
                Cache::forget("user_perm_ver_{$userId}");
            }
        }
        self::clearMenuCache();
        try {
            $globalVer = (int) Cache::get('global_perm_ver', 1);
            Cache::put('global_perm_ver', $globalVer + 1, 86400 * 30);
        } catch (\Throwable $e) {
            Cache::forget('global_perm_ver');
        }
    }

    /**
     * Get dynamic menus for the authenticated user based on their permissions.
     * Uses versioned caching to avoid database queries on every page load.
     */
    public function getUserMenus($user)
    {
        if (!$user) {
            return [];
        }

        $globalVer = Cache::get('global_perm_ver', 1);
        $menuVersion = Cache::get('menus_version', 1);
        $userPermVersion = Cache::get("user_perm_ver_{$user->id}", 1);
        $cacheKey = "user_menus_{$user->id}_g{$globalVer}_v{$menuVersion}_{$userPermVersion}";

        return Cache::remember($cacheKey, 3600, function () use ($user) {
            return $this->buildUserMenus($user);
        });
    }

    /**
     * Build menu tree from database for the specified user.
     */
    protected function buildUserMenus($user): array
    {
        // Get all active & visible menus ordered by sort_order
        $allMenus = Menu::where('is_active', true)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get();

        // Build tree and filter by user permissions
        $rootMenus = $allMenus->whereNull('parent_id');
        $menuStructure = [];

        foreach ($rootMenus as $rootMenu) {
            if (!$rootMenu->hasAccess($user)) {
                continue;
            }

            $children = $this->getAccessibleChildren($allMenus, $rootMenu->id, $user);

            if ($rootMenu->route_name) {
                // Top-level menu with a direct route (e.g. Dashboard)
                $item = [
                    'route' => $rootMenu->route_name,
                    'route_param' => $rootMenu->route_params,
                    'icon' => $rootMenu->icon,
                    'label' => $rootMenu->title,
                    'key' => $rootMenu->route_name,
                    'badge' => $rootMenu->badge,
                    'open_in_new_tab' => $rootMenu->open_in_new_tab,
                ];

                if (!empty($children)) {
                    $item['submenus'] = $children;
                }

                $menuStructure[] = $item;
            } elseif (!empty($children)) {
                // Parent-only menu (no route, just a group header)
                $menuStructure[] = [
                    'label' => $rootMenu->title,
                    'icon' => $rootMenu->icon,
                    'key' => 'menu-' . $rootMenu->id,
                    'submenus' => $children,
                ];
            }
        }

        return $menuStructure;
    }

    /**
     * Recursively get children that the user has access to.
     */
    private function getAccessibleChildren($allMenus, $parentId, $user): array
    {
        $children = $allMenus->where('parent_id', $parentId);
        $result = [];

        foreach ($children as $child) {
            if (!$child->hasAccess($user)) {
                continue;
            }

            $grandchildren = $this->getAccessibleChildren($allMenus, $child->id, $user);

            $item = [
                'route' => $child->route_name,
                'route_param' => $child->route_params,
                'label' => $child->title,
                'key' => $child->route_name ?? 'menu-' . $child->id,
                'icon' => $child->icon,
                'badge' => $child->badge,
                'open_in_new_tab' => $child->open_in_new_tab,
            ];

            if (!empty($grandchildren)) {
                $item['submenus'] = $grandchildren;
            }

            $result[] = $item;
        }

        return $result;
    }
}