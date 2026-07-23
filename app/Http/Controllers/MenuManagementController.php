<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

use App\Models\Action;
use App\Models\Module;
use App\Models\Resource;
use Illuminate\Support\Str;

class MenuManagementController extends Controller
{
    /**
     * Show the Menu Management page.
     */
    public function index()
    {
        $roles = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('permission_key')->get();

        return view('menu-management.index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'activeTab' => 'menu-management',
        ]);
    }

    /**
     * Get the full menu tree as JSON.
     */
    public function getTree(): JsonResponse
    {
        $menus = Menu::orderBy('sort_order')->get();
        $tree = Menu::buildTree($menus);

        return response()->json(['success' => true, 'tree' => $tree]);
    }

    /**
     * Store a new menu item.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:menus,id',
            'icon' => 'nullable|string|max:100',
            'route_name' => 'nullable|string|max:255',
            'route_params' => 'nullable|array',
            'type' => 'nullable|in:link,header,divider',
            'permission_key' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'open_in_new_tab' => 'nullable|boolean',
            'generate_files' => 'nullable|boolean',
        ]);

        // Determine sort_order (append to end)
        $maxSort = Menu::where('parent_id', $validated['parent_id'] ?? null)->max('sort_order') ?? 0;
        $validated['sort_order'] = $maxSort + 10;

        $menuData = collect($validated)->except(['generate_files'])->toArray();
        $menu = Menu::create($menuData);

        // Auto-generate Resource & Permission so it appears in Settings -> Roles & Permissions
        $systemModule = Module::where('code', 'system')->first() ?? Module::first();
        $viewAction = Action::where('code', 'view')->first();

        $slug = Str::slug($menu->route_name ?: $menu->title);
        $resourceSlug = "menu-{$slug}";

        $resource = Resource::firstOrCreate(
            ['slug' => $resourceSlug],
            [
                'name' => "Menu: {$menu->title}",
                'module_id' => $systemModule->id ?? 1,
                'description' => "Hak akses untuk menu {$menu->title}",
                'is_active' => true,
            ]
        );

        $permKey = $menu->permission_key;
        if (empty($permKey)) {
            $permKey = $viewAction ? "{$resource->slug}.{$viewAction->code}" : $resource->slug;
        }

        $perm = Permission::firstOrCreate(
            ['permission_key' => $permKey],
            [
                'resource_id' => $resource->id,
                'action_id' => $viewAction ? $viewAction->id : null,
                'description' => "Can View {$menu->title} Menu",
            ]
        );

        // If permission existed previously without resource_id, update it
        if (!$perm->resource_id) {
            $perm->update([
                'resource_id' => $resource->id,
                'action_id' => $viewAction ? $viewAction->id : null,
            ]);
        }

        $menu->update(['permission_key' => $permKey]);
        $menu->permissions()->syncWithoutDetaching([$perm->id]);

        // Automatically assign new menu permission to all roles
        $roles = Role::all();
        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        if ($request->generate_files && $menu->type === 'link' && !empty($menu->route_name)) {
            $this->generateRouteAndFiles($menu->route_name, $menu->title);
        }

        Cache::forget('sidebar_menus');

        return response()->json([
            'success' => true,
            'message' => "Menu '{$menu->title}' created successfully with auto-generated permission.",
            'menu' => $menu,
        ]);
    }

    /**
     * Update an existing menu item.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $menu = Menu::find($id);

        if (!$menu) {
            return response()->json(['success' => false, 'message' => 'Menu item not found.'], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|integer',
            'icon' => 'nullable|string|max:100',
            'route_name' => 'nullable|string|max:255',
            'route_params' => 'nullable|array',
            'type' => 'nullable|in:link,header,divider',
            'permission_key' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'open_in_new_tab' => 'nullable|boolean',
            'generate_files' => 'nullable|boolean',
        ]);

        // Prevent menu from being its own parent
        if (isset($validated['parent_id']) && $validated['parent_id'] == $id) {
            return response()->json(['success' => false, 'message' => 'A menu cannot be its own parent.'], 422);
        }

        $menuData = collect($validated)->except(['generate_files'])->toArray();
        $menu->update($menuData);

        // Ensure resource and permission exist if set or updated
        if (!empty($menu->permission_key)) {
            $systemModule = Module::where('code', 'system')->first() ?? Module::first();
            $viewAction = Action::where('code', 'view')->first();

            $slug = Str::slug($menu->route_name ?: $menu->title);
            $resourceSlug = "menu-{$slug}";

            $resource = Resource::firstOrCreate(
                ['slug' => $resourceSlug],
                [
                    'name' => "Menu: {$menu->title}",
                    'module_id' => $systemModule->id ?? 1,
                    'description' => "Hak akses untuk menu {$menu->title}",
                    'is_active' => true,
                ]
            );

            $perm = Permission::firstOrCreate(
                ['permission_key' => $menu->permission_key],
                [
                    'resource_id' => $resource->id,
                    'action_id' => $viewAction ? $viewAction->id : null,
                    'description' => "Can View {$menu->title} Menu",
                ]
            );

            if (!$perm->resource_id) {
                $perm->update([
                    'resource_id' => $resource->id,
                    'action_id' => $viewAction ? $viewAction->id : null,
                ]);
            }

            $menu->permissions()->syncWithoutDetaching([$perm->id]);

            $roles = Role::all();
            foreach ($roles as $role) {
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }
        }

        if (!empty($oldRouteName) && $oldRouteName !== $menu->route_name) {
            $this->deleteRouteAndFiles($oldRouteName);
        }
        if (!empty($menu->route_name)) {
            $this->generateRouteAndFiles($menu->route_name, $menu->title);
        }


        Cache::forget('sidebar_menus');

        return response()->json([
            'success' => true,
            'message' => "Menu '{$menu->title}' updated successfully.",
            'menu' => $menu->fresh(),
        ]);
    }

    /**
     * Delete a menu item (children will be orphaned to parent/root) & remove permissions.
     */
    public function destroy(int $id): JsonResponse
    {
        $menu = Menu::find($id);

        if (!$menu) {
            return response()->json([
                'success' => true,
                'message' => 'Menu item has already been deleted.',
            ]);
        }

        $permKey = $menu->permission_key;
        $title = $menu->title;
        $routeName = $menu->route_name;
        $menuType = $menu->type;
        $slug = Str::slug($menu->route_name ?: $menu->title);
        $resourceSlug = "menu-{$slug}";

        // Move children to root/parent (keep main menu intact)
        Menu::where('parent_id', $id)->update(['parent_id' => $menu->parent_id]);

        // Detach pivot relationship from menu_permissions table
        $menu->permissions()->detach();

        $menu->delete();

        // If permission_key exists and is not used by other menus, delete permission & resource
        if (!empty($permKey)) {
            $otherUsage = Menu::where('permission_key', $permKey)->exists();
            if (!$otherUsage) {
                $perm = Permission::where('permission_key', $permKey)->first();
                if ($perm) {
                    $resourceId = $perm->resource_id;

                    // Detach from all roles and delete the permission
                    $perm->roles()->detach();
                    $perm->delete();

                    // If resource has no remaining permissions, delete the resource
                    if ($resourceId) {
                        $resource = Resource::find($resourceId);
                        if ($resource && $resource->permissions()->count() === 0) {
                            $resource->delete();
                        }
                    }
                }
            }
        }

        if ($menuType === 'link' && !empty($routeName)) {
            $this->deleteRouteAndFiles($routeName);
        }

        Cache::forget('sidebar_menus');

        return response()->json([
            'success' => true,
            'message' => "Menu '{$title}' and its role permissions deleted successfully.",
        ]);
    }

    /**
     * Reorder menu items (from drag & drop).
     * Expects: items = [{id, parent_id, sort_order}, ...]
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:menus,id',
            'items.*.parent_id' => 'nullable|integer',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->items as $item) {
            Menu::where('id', $item['id'])->update([
                'parent_id' => $item['parent_id'] ?? null,
                'sort_order' => $item['sort_order'],
            ]);
        }

        Cache::forget('sidebar_menus');

        return response()->json([
            'success' => true,
            'message' => 'Menu order updated successfully.',
        ]);
    }

    /**
     * Get all named Laravel routes for the Route Picker.
     */
    public function getRoutes(): JsonResponse
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn($route) => $route->getName() && !str_starts_with($route->getName(), 'generated::'))
            ->map(fn($route) => [
                'name' => $route->getName(),
                'uri' => '/' . ltrim($route->uri(), '/'),
                'methods' => $route->methods(),
            ])
            ->sortBy('name')
            ->values()
            ->toArray();

        return response()->json(['success' => true, 'routes' => $routes]);
    }

    /**
     * Get Material Symbols icon list.
     */
    public function getIcons(): JsonResponse
    {
        $icons = [
            'dashboard',
            'sync',
            'folder',
            'receipt',
            'article',
            'analytics',
            'build',
            'api',
            'data_object',
            'settings',
            'tune',
            'group',
            'account_tree',
            'terminal',
            'database',
            'storage',
            'cloud_sync',
            'bar_chart',
            'pie_chart',
            'trending_up',
            'table_chart',
            'grid_view',
            'view_list',
            'view_module',
            'category',
            'inventory_2',
            'local_shipping',
            'payments',
            'shopping_cart',
            'store',
            'person',
            'people',
            'security',
            'admin_panel_settings',
            'manage_accounts',
            'key',
            'lock',
            'vpn_key',
            'notifications',
            'mail',
            'chat',
            'forum',
            'help',
            'info',
            'warning',
            'error',
            'check_circle',
            'cancel',
            'schedule',
            'calendar_today',
            'event',
            'alarm',
            'timer',
            'update',
            'download',
            'upload',
            'cloud_upload',
            'cloud_download',
            'backup',
            'restore',
            'delete',
            'edit',
            'add',
            'remove',
            'search',
            'filter_list',
            'sort',
            'swap_vert',
            'compare_arrows',
            'home',
            'star',
            'favorite',
            'bookmark',
            'flag',
            'label',
            'tag',
            'attach_file',
            'link',
            'open_in_new',
            'code',
            'bug_report',
            'memory',
            'dns',
            'hub',
            'speed',
            'bolt',
            'power',
            'wifi',
            'bluetooth',
            'print',
            'share',
            'content_copy',
            'content_paste',
            'visibility',
            'visibility_off',
            'fullscreen',
            'zoom_in',
            'zoom_out',
            'photo',
            'image',
            'videocam',
            'mic',
            'volume_up',
            'play_circle',
            'pause_circle',
            'stop_circle',
            'skip_next',
            'skip_previous',
            'map',
            'place',
            'navigation',
            'directions',
            'explore',
            'work',
            'business',
            'apartment',
            'school',
            'local_hospital',
            'restaurant',
            'local_cafe',
            'local_gas_station',
            'park',
            'flight',
            'token',
            'webhook',
            'rule',
            'gavel',
            'policy',
            'description',
            'note',
            'sticky_note_2',
            'task',
            'checklist',
            'assignment',
            'summarize',
            'receipt_long',
            'request_quote',
            'credit_card',
            'account_balance',
            'savings',
            'currency_exchange',
            'attach_money',
            'money',
            'price_check',
            'sell',
            'inventory',
            'package_2',
            'deployed_code',
            'widgets',
            'extension',
            'integration_instructions',
            'developer_board',
            'monitor_heart',
            'health_and_safety',
            'emergency',
            'verified',
            'new_releases',
            'grade',
            'military_tech',
        ];

        return response()->json(['success' => true, 'icons' => $icons]);
    }

    /**
     * Get permission matrix for all menus × roles.
     */
    public function getPermissionMatrix(): JsonResponse
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $menus = Menu::with('permissions')->orderBy('sort_order')->get();

        $matrix = [];
        foreach ($menus as $menu) {
            $menuPerms = $menu->permissions->pluck('id')->toArray();
            $roleAccess = [];
            foreach ($roles as $role) {
                $rolePermIds = $role->permissions->pluck('id')->toArray();
                // A role has access if it has ANY of the menu's assigned permissions
                // OR if the menu has no permission_key (open to all)
                $roleAccess[$role->id] = empty($menu->permission_key) || !empty(array_intersect($menuPerms, $rolePermIds));
            }
            $matrix[] = [
                'menu_id' => $menu->id,
                'title' => $menu->title,
                'parent_id' => $menu->parent_id,
                'permission_key' => $menu->permission_key,
                'roles' => $roleAccess,
            ];
        }

        return response()->json([
            'success' => true,
            'roles' => $roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name]),
            'matrix' => $matrix,
        ]);
    }

    /**
     * Toggle a role's permission for a specific menu.
     */
    public function toggleRolePermission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'menu_id' => 'required|integer|exists:menus,id',
            'is_granted' => 'required|boolean',
        ]);

        $role = Role::findOrFail($validated['role_id']);
        $menu = Menu::with('permissions')->findOrFail($validated['menu_id']);

        if (empty($menu->permission_key)) {
            return response()->json(['success' => false, 'message' => 'This menu is open to all and cannot have restricted permissions.']);
        }

        $menuPermIds = $menu->permissions->pluck('id')->toArray();
        
        // If the menu has a permission_key but no permissions attached in menu_permissions, we should try to find the permission by key.
        if (empty($menuPermIds)) {
            $permission = Permission::where('permission_key', $menu->permission_key)->first();
            if ($permission) {
                $menuPermIds = [$permission->id];
            } else {
                return response()->json(['success' => false, 'message' => 'Permission key not found in the system.']);
            }
        }

        if ($validated['is_granted']) {
            $role->permissions()->syncWithoutDetaching($menuPermIds);
        } else {
            $role->permissions()->detach($menuPermIds);
        }

        return response()->json(['success' => true, 'message' => 'Permission updated.']);
    }

    /**
     * Publish all changes (clear cache).
     */
    public function publish(): JsonResponse
    {
        Cache::forget('sidebar_menus');

        return response()->json([
            'success' => true,
            'message' => 'Menu published successfully! All users will now see the updated sidebar.',
        ]);
    }

    /**
     * Auto-generate controller, view, and web.php route entry.
     */
    private function generateRouteAndFiles(string $routeName, string $title)
    {
        // Validate if route already exists in Laravel's loaded routes
        if (Route::has($routeName)) {
            return; // Route already exists, do not generate
        }

        $parts = explode('.', $routeName);

        // 1. Controller Name (e.g. ReportCustomDataController)
        $controllerName = '';
        foreach ($parts as $part) {
            $controllerName .= \Illuminate\Support\Str::studly($part);
        }
        $controllerName .= 'Controller';

        // 2. View path (e.g. report/custom-data)
        $viewPath = str_replace('.', '/', $routeName);

        // 3. URL path (e.g. /report/custom-data)
        $urlPath = '/' . str_replace('.', '/', $routeName);

        $controllerFile = app_path("Http/Controllers/{$controllerName}.php");
        $viewFile = resource_path("views/{$viewPath}/index.blade.php");

        // Generate Controller if not exists
        if (!\Illuminate\Support\Facades\File::exists($controllerFile)) {
            $stub = <<<PHP
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class {$controllerName} extends Controller
{
    public function index()
    {
        return view('{$routeName}.index');
    }
}
PHP;
            \Illuminate\Support\Facades\File::put($controllerFile, $stub);
        }

        // Generate View if not exists
        if (!\Illuminate\Support\Facades\File::exists($viewFile)) {
            \Illuminate\Support\Facades\File::makeDirectory(dirname($viewFile), 0755, true, true);
            $viewStub = <<<BLADE
@extends('layouts.app')

@section('content')
<div class="p-6">
    <div class="bg-white rounded-2xl shadow-card p-6 border border-surface-200/60">
        <h1 class="text-2xl font-bold text-surface-800 mb-2">{\$title}</h1>
        <p class="text-surface-600">This is an auto-generated view for route: <code>{\$routeName}</code></p>
    </div>
</div>
@endsection
BLADE;
            // Replace the dummy $title and $routeName vars to actual strings in the stub above.
            $viewStub = str_replace(['{\$title}', '{\$routeName}'], [$title, $routeName], $viewStub);
            \Illuminate\Support\Facades\File::put($viewFile, $viewStub);
        }

        // Append to routes/web.php
        $routesFile = base_path('routes/web.php');
        $routesContent = \Illuminate\Support\Facades\File::get($routesFile);

        // Check if route is already defined in file to avoid duplicates
        if (!str_contains($routesContent, "->name('{$routeName}')")) {
            // Find the last `});` in the file.
            $routeDefinition = "    // Auto-generated route for {$title}\n    Route::get('{$urlPath}', [\\App\\Http\\Controllers\\{$controllerName}::class, 'index'])->name('{$routeName}');\n});";

            // Using regex to replace the last occurrence of `});`
            $routesContent = preg_replace('/\}\);\s*$/', $routeDefinition . "\n", $routesContent);
            \Illuminate\Support\Facades\File::put($routesFile, $routesContent);
        }
    }

    /**
     * Delete auto-generated controller, view, and web.php route entry.
     */
    private function deleteRouteAndFiles(string $routeName)
    {
        if (empty($routeName)) {
            return;
        }

        $parts = explode('.', $routeName);

        $controllerName = '';
        foreach ($parts as $part) {
            $controllerName .= \Illuminate\Support\Str::studly($part);
        }
        $controllerName .= 'Controller';

        $viewPath = str_replace('.', '/', $routeName);

        $controllerFile = app_path("Http/Controllers/{$controllerName}.php");
        $viewDir = resource_path("views/{$viewPath}");
        $viewFile = "{$viewDir}/index.blade.php";

        // 1. Delete Controller
        if (\Illuminate\Support\Facades\File::exists($controllerFile)) {
            \Illuminate\Support\Facades\File::delete($controllerFile);
        }

        // 2. Delete View and Directory if empty
        if (\Illuminate\Support\Facades\File::exists($viewFile)) {
            \Illuminate\Support\Facades\File::delete($viewFile);
        }

        // 3. Cleanup empty directories upwards
        $currentDir = $viewDir;
        $baseViewsDir = resource_path('views');
        
        while ($currentDir && str_starts_with($currentDir, $baseViewsDir) && $currentDir !== $baseViewsDir) {
            if (\Illuminate\Support\Facades\File::isDirectory($currentDir)) {
                $contents = @scandir($currentDir);
                // If it only contains '.' and '..', it is empty
                if (is_array($contents) && count($contents) <= 2) {
                    \Illuminate\Support\Facades\File::deleteDirectory($currentDir);
                } else {
                    // Not empty, stop moving up
                    break;
                }
            }
            // Move up one level
            $currentDir = dirname($currentDir);
        }

        // 3. Remove from routes/web.php
        $routesFile = base_path('routes/web.php');
        if (\Illuminate\Support\Facades\File::exists($routesFile)) {
            $routesContent = \Illuminate\Support\Facades\File::get($routesFile);

            $pattern = '/\n?\s*\/\/\s*Auto-generated route for[^\n]*\n\s*Route::[^\n]*?->name\(\'' . preg_quote($routeName, '/') . '\'\);/';

            $newRoutesContent = preg_replace($pattern, '', $routesContent);

            if ($newRoutesContent !== null && $newRoutesContent !== $routesContent) {
                \Illuminate\Support\Facades\File::put($routesFile, $newRoutesContent);
            }
        }
    }
}
