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
        $queryRepositories = \App\Models\QueryRepository::where('is_active', true)->orderBy('name')->get();

        return view('menu-management.index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'queryRepositories' => $queryRepositories,
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
            'query_repository_id' => 'nullable|integer|exists:query_repositories,id',
            'filter_config' => 'nullable|array',
        ]);

        if (array_key_exists('filter_config', $validated)) {
            $validated['filter_config'] = $this->validateFilterConfig($validated['filter_config'], $validated['query_repository_id'] ?? null);
        }

        // Determine sort_order (append to end)
        $maxSort = Menu::where('parent_id', $validated['parent_id'] ?? null)->max('sort_order') ?? 0;
        $validated['sort_order'] = $maxSort + 10;
        
        // Use generic dynamic runtime for new Query Repository menus
        if (!empty($validated['query_repository_id']) && empty($validated['route_name'])) {
            $validated['route_name'] = 'report.dynamic';
            $request->merge(['generate_files' => false]);
        }

        $menuData = collect($validated)->except(['generate_files'])->toArray();
        $menu = Menu::create($menuData);
        
        if ($menu->route_name === 'report.dynamic') {
            $menu->update(['route_params' => ['menuId' => $menu->id]]);
        }

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

        // Superadmin bypasses this, so we don't assign it to any roles yet.

        if ($request->generate_files && $menu->type === 'link' && !empty($menu->route_name)) {
            $this->generateRouteAndFiles($menu->route_name, $menu->title, $menu->query_repository_id, false, $menu->id);
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
            'query_repository_id' => 'nullable|integer|exists:query_repositories,id',
            'filter_config' => 'nullable|array',
        ]);

        if (array_key_exists('filter_config', $validated)) {
            $validated['filter_config'] = $this->validateFilterConfig($validated['filter_config'], $validated['query_repository_id'] ?? null);
        }

        // Prevent menu from being its own parent
        if (isset($validated['parent_id']) && $validated['parent_id'] == $id) {
            return response()->json(['success' => false, 'message' => 'A menu cannot be its own parent.'], 422);
        }
        
        // Switch to generic dynamic runtime if switching to query repository
        $oldRouteName = $menu->route_name;
        if (!empty($validated['query_repository_id']) && empty($validated['route_name'])) {
            $validated['route_name'] = 'report.dynamic';
            $validated['route_params'] = ['menuId' => $menu->id];
            $request->merge(['generate_files' => false]);
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
        }

        if (!empty($oldRouteName) && $oldRouteName !== $menu->route_name) {
            $this->deleteRouteAndFiles($oldRouteName);
        }
        if (!empty($menu->route_name)) {
            $this->generateRouteAndFiles($menu->route_name, $menu->title, $menu->query_repository_id, true, $menu->id);
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
        $roles = Role::with('permissions')->where('is_active', true)->orderBy('name')->get();
        // We don't need menu_permissions anymore, so we don't need to load 'permissions' on Menu
        $allMenus = Menu::orderBy('sort_order')->get();

        $orderedMenus = [];
        $buildTree = function ($parentId = null) use ($allMenus, &$orderedMenus, &$buildTree) {
            foreach ($allMenus->where('parent_id', $parentId) as $menu) {
                $orderedMenus[] = $menu;
                $buildTree($menu->id);
            }
        };
        $buildTree(null);

        $permissionsByKey = Permission::all()->keyBy('permission_key');

        $matrix = [];
        foreach ($orderedMenus as $menu) {
            $menuPermId = null;
            if (!empty($menu->permission_key)) {
                $perm = $permissionsByKey->get($menu->permission_key);
                if ($perm) {
                    $menuPermId = $perm->id;
                }
            }

            $roleAccess = [];
            foreach ($roles as $role) {
                $rolePermIds = $role->permissions->pluck('id')->toArray();
                // A role has access if it has the menu's permission_key mapped permission
                // OR if the menu has no permission_key (open to all)
                $roleAccess[$role->id] = empty($menu->permission_key) || ($menuPermId && in_array($menuPermId, $rolePermIds));
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
            'roles' => $roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name, 'is_superadmin' => $r->is_superadmin]),
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
        $menu = Menu::findOrFail($validated['menu_id']);

        if ($role->is_superadmin) {
            return response()->json(['success' => false, 'message' => 'Super Administrator automatically has all permissions.']);
        }

        if (empty($menu->permission_key)) {
            return response()->json(['success' => false, 'message' => 'This menu is open to all and cannot have restricted permissions.']);
        }

        $permission = Permission::where('permission_key', $menu->permission_key)->first();
        if (!$permission) {
            return response()->json(['success' => false, 'message' => 'Permission key not found in the system.']);
        }

        if ($validated['is_granted']) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        } else {
            $role->permissions()->detach([$permission->id]);
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
     */    private function generateRouteAndFiles(string $routeName, string $title, ?int $queryRepositoryId = null, bool $forceOverwrite = false, ?int $menuId = null)
    {
        // Validate if route already exists in Laravel's loaded routes and we're not forcing overwrite
        if (Route::has($routeName) && !$forceOverwrite) {
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

        // Generate Controller if not exists or if force overwriting
        if ($forceOverwrite || !\Illuminate\Support\Facades\File::exists($controllerFile)) {
            if ($queryRepositoryId) {
                $stub = <<<PHP
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QueryRepository;
use App\Models\Connection;
use Illuminate\Support\Facades\DB;
use App\Models\Menu;
use App\Services\MenuFilterService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class {$controllerName} extends Controller
{
    public function index(Request \$request, MenuFilterService \$filterService, \App\Services\ConnectionService \$connService)
    {
        \$queryId = {$queryRepositoryId};
        \$menuId = {$menuId};
        \$queryRepo = QueryRepository::find(\$queryId);
        \$menu = Menu::find(\$menuId);
        
        if (!\$queryRepo) {
            return view('{$routeName}.index', ['error' => 'Query repository not found.']);
        }
        
        \$conn = Connection::find(\$queryRepo->database_connection_id);
        if (!\$conn) {
            return view('{$routeName}.index', ['error' => 'Database connection not found.']);
        }
        
        \$rawSql = \$queryRepo->sql_text;
        \$columns = is_array(\$queryRepo->output_column_json) ? \$queryRepo->output_column_json : json_decode(\$queryRepo->output_column_json, true) ?? [];
        
        \$qrService = app(\App\Services\QueryRepositoryService::class);
        \$query = \$qrService->executeBuilder(\$conn->key, \$rawSql, \$request->all());
        
        \$filterConfig = \$menu ? \$menu->filter_config : null;
        
        if (\$filterConfig === null) {
            // Legacy behavior
            foreach (\$columns as \$col) {
                \$field = is_array(\$col) ? (\$col['field'] ?? null) : \$col;
                if (\$field && \$request->filled(\$field)) {
                    \$query->where(\$field, 'LIKE', '%' . \$request->input(\$field) . '%');
                }
            }
        } elseif (is_array(\$filterConfig) && count(\$filterConfig) > 0) {
            try {
                \$filterService->applyFilters(\$query, \$filterConfig, \$request);
            } catch (ValidationException \$e) {
                return view('{$routeName}.index', [
                    'error' => collect(\$e->errors())->flatten()->first(),
                    'columns' => \$columns,
                    'filterConfig' => \$filterConfig,
                    'results' => collect([]), // Empty results on validation error
                ]);
            }
        }
        if (\$request->input('export') === 'csv' || \$request->input('export') === 'xlsx') {
            \$format = \$request->input('export');
            \$fileName = (\$menu ? Str::slug(\$menu->name) : 'export') . '_' . date('Ymd_His') . '.' . \$format;
            \$writer = \Spatie\SimpleExcel\SimpleExcelWriter::streamDownload(\$fileName);
            
            // For XLSX, Spatie uses Spout which writes to temp disk then streams, preventing memory issues.
            foreach (\$query->cursor() as \$row) {
                \$writer->addRow((array) \$row);
            }
            return \$writer->toBrowser();
        }
        
        try {
            // Using simplePaginate for huge datasets
            \$results = \$query->simplePaginate(20)->withQueryString();
        } catch (\Exception \$e) {
            return view('{$routeName}.index', ['error' => \$e->getMessage()]);
        }
        
        return view('{$routeName}.index', [
            'results' => \$results,
            'columns' => \$columns,
            'filterConfig' => \$filterConfig,
        ]);
    }
}
PHP;
            } else {
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
            }
            \Illuminate\Support\Facades\File::put($controllerFile, $stub);
        }

        // Generate View if not exists or if force overwriting
        if ($forceOverwrite || !\Illuminate\Support\Facades\File::exists($viewFile)) {
            \Illuminate\Support\Facades\File::makeDirectory(dirname($viewFile), 0755, true, true);

            if ($queryRepositoryId) {
                $viewStub = <<<BLADE
@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between animate-fade-up">
        <div>
            <h2 class="text-h2 text-surface-900">{\$title}</h2>
            <p class="text-body-md text-surface-500 mt-0.5">Data generated from Query Builder</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ url()->current() }}?export=csv&{{ http_build_query(request()->except('export')) }}" onclick="showExportProgress()" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-surface-200 text-surface-700 hover:bg-surface-50 hover:text-surface-900 rounded-xl text-label-md transition-all shadow-sm">
                <span class="material-symbols-outlined text-[18px]">csv</span> Export CSV
            </a>
            <a href="{{ url()->current() }}?export=xlsx&{{ http_build_query(request()->except('export')) }}" onclick="showExportProgress()" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-surface-200 text-surface-700 hover:bg-surface-50 hover:text-surface-900 rounded-xl text-label-md transition-all shadow-sm">
                <span class="material-symbols-outlined text-[18px]">table_chart</span> Export Excel
            </a>
            <form method="POST" action="{{ route('menu-management.refresh-files', ['route_name' => '{\$routeName}']) }}" onsubmit="return confirm('Regenerate files to pull latest columns from Query Builder?');">
                @csrf
                <button type="submit" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-surface-200 text-surface-700 hover:bg-surface-50 hover:text-surface-900 rounded-xl text-label-md transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">refresh</span> Refresh Columns
                </button>
            </form>
        </div>
    </div>

    @if(isset(\$error))
        <div class="p-4 bg-red-50 text-red-600 rounded-xl border border-red-200 animate-fade-up">
            {{ \$error }}
        </div>
    @endif
    
    @if(!isset(\$error) || (isset(\$results) && collect(\$results)->isNotEmpty()) || isset(\$filterConfig))
        @php
            \$hasFilters = false;
            \$renderFilters = [];
            
            if (!isset(\$filterConfig)) {
                // Legacy
                \$hasFilters = true;
                foreach(\$columns as \$col) {
                    \$field = is_array(\$col) ? (\$col['field'] ?? null) : \$col;
                    if(\$field) {
                        \$renderFilters[] = [
                            'field' => \$field,
                            'label' => Str::title(str_replace('_', ' ', \$field)),
                            'type' => 'text'
                        ];
                    }
                }
            } elseif (is_array(\$filterConfig) && count(\$filterConfig) > 0) {
                \$hasFilters = true;
                \$renderFilters = \$filterConfig;
            }
        @endphp

        @if(\$hasFilters)
        <!-- Filter Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-surface-200/80 p-5 animate-fade-up" style="animation-delay: 0.1s">
            <form method="GET" action="{{ url()->current() }}" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <input type="hidden" name="apply_filters" value="1">
                @foreach(\$renderFilters as \$filter)
                    @php 
                        \$field = \$filter['field'];
                        \$type = \$filter['type'] ?? 'text';
                        \$label = \$filter['label'] ?? Str::title(str_replace('_', ' ', \$field));
                        \$placeholder = \$filter['placeholder'] ?? 'Search...';
                        \$required = \$filter['required'] ?? false;
                    @endphp
                    @if(\$type === 'date_range')
                        <div class="col-span-1 md:col-span-2">
                            <label class="block text-label-sm text-surface-700 mb-1.5">{{ \$label }} {!! \$required ? '<span class="text-red-500">*</span>' : '' !!}</label>
                            <div class="flex items-center gap-2">
                                <input type="date" name="{{ \$field }}_from" value="{{ request(\$field . '_from') }}" class="w-full bg-white border border-surface-300 rounded-xl px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                                <span>-</span>
                                <input type="date" name="{{ \$field }}_to" value="{{ request(\$field . '_to') }}" class="w-full bg-white border border-surface-300 rounded-xl px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                            </div>
                        </div>
                    @else
                        <div>
                            <label class="block text-label-sm text-surface-700 mb-1.5">{{ \$label }} {!! \$required ? '<span class="text-red-500">*</span>' : '' !!}</label>
                            @if(\$type === 'date')
                                <input type="date" name="{{ \$field }}" value="{{ request(\$field) }}" class="w-full bg-white border border-surface-300 rounded-xl px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                            @elseif(\$type === 'number')
                                <input type="number" step="any" name="{{ \$field }}" value="{{ request(\$field) }}" placeholder="{{ \$placeholder }}" class="w-full bg-white border border-surface-300 rounded-xl px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                            @else
                                <input type="text" name="{{ \$field }}" value="{{ request(\$field) }}" placeholder="{{ \$placeholder }}" class="w-full bg-white border border-surface-300 rounded-xl px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                            @endif
                        </div>
                    @endif
                @endforeach
                <div class="md:col-span-3 lg:col-span-4 flex justify-end gap-3 mt-2">
                    <a href="{{ url()->current() }}" class="px-5 py-2.5 bg-white border border-surface-200 text-surface-700 hover:bg-surface-50 rounded-xl text-label-md transition-all">Clear</a>
                    <button type="submit" class="px-5 py-2.5 bg-brand-600 text-white hover:bg-brand-700 rounded-xl text-label-md transition-all shadow-sm shadow-brand-500/20">Apply Filters</button>
                </div>
            </form>
        </div>
        @endif
        
        <!-- Data Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-surface-200/80 overflow-hidden animate-fade-up" style="animation-delay: 0.2s">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-50/50 border-b border-surface-200">
                            @foreach(\$columns as \$col)
                                @php \$field = is_array(\$col) ? (\$col['field'] ?? null) : \$col; @endphp
                                @if(\$field)
                                <th class="px-6 py-4 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">{{ Str::title(str_replace('_', ' ', \$field)) }}</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 text-body-sm text-surface-700">
                        @if(isset(\$results) && collect(\$results)->isNotEmpty())
                            @foreach(\$results as \$row)
                                @php \$rowArray = array_change_key_case((array) \$row, CASE_LOWER); @endphp
                                <tr class="hover:bg-surface-50/80 transition-colors">
                                    @foreach(\$columns as \$col)
                                        @php \$field = is_array(\$col) ? (\$col['field'] ?? null) : \$col; @endphp
                                        @if(\$field)
                                        <td class="px-6 py-4 whitespace-nowrap">{{ \$rowArray[strtolower(\$field)] ?? '-' }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="{{ count(\$columns) }}" class="px-6 py-12 text-center text-surface-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="material-symbols-outlined text-[48px] text-surface-300 mb-2">database</span>
                                        <p class="text-label-md text-surface-600">No data found matching your filters.</p>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            
            @if(isset(\$results) && method_exists(\$results, 'links'))
                <div class="p-4 border-t border-surface-200/80 bg-surface-50/30">
                    {{ \$results->links() }}
                </div>
            @endif
        </div>
    @endif
</div>

@section('scripts')
<div id="exportProgressModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="absolute inset-0 bg-surface-900/50 backdrop-blur-sm"></div>
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-sm w-full mx-4 relative z-10 text-center animate-fade-up">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-brand-50 text-brand-600 mb-4">
            <span class="material-symbols-outlined text-[32px] spin">sync</span>
        </div>
        <h3 class="text-h3 text-surface-900 mb-2">Preparing Export...</h3>
        <p class="text-body-md text-surface-500">Your download will start shortly. Please wait and do not close this window.</p>
    </div>
</div>
<script>
    function showExportProgress() {
        document.getElementById('exportProgressModal').classList.remove('hidden');
        // Hide modal after 5 seconds assuming browser download started
        setTimeout(() => {
            document.getElementById('exportProgressModal').classList.add('hidden');
        }, 5000);
    }
</script>
@endsection
@endsection
BLADE;
            } else {
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
            }
            // Replace the dummy $title and $routeName vars to actual strings in the stub above.
            $viewStub = str_replace(['{$title}', '{$routeName}', '{$menuId}', '{$queryRepositoryId}'], [$title, $routeName, $menuId, $queryRepositoryId], $viewStub);
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


    /**
     * Refresh the generated files for a menu to update columns from query builder.
     */
    public function refreshGeneratedFiles(Request $request, string $routeName)
    {
        $menu = Menu::where('route_name', $routeName)->first();
        if (!$menu) {
            return redirect()->back()->with('error', 'Menu not found.');
        }

        if (!$menu->query_repository_id) {
            return redirect()->back()->with('error', 'Menu is not linked to a Query Repository.');
        }

        $this->generateRouteAndFiles($menu->route_name, $menu->title, $menu->query_repository_id, true, $menu->id);

        return redirect()->back()->with('success', 'Columns and UI successfully refreshed!');
    }

    /**
     * Validate filter configuration against the selected query repository.
     */
    private function validateFilterConfig(?array $filterConfig, ?int $queryRepositoryId)
    {
        if ($filterConfig === null) {
            return null;
        }

        if (empty($filterConfig)) {
            return [];
        }

        if (!$queryRepositoryId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'filter_config' => 'Query repository is required when configuring filters.'
            ]);
        }

        $queryRepo = \App\Models\QueryRepository::find($queryRepositoryId);
        if (!$queryRepo) {
            return [];
        }

        $outputColumns = is_array($queryRepo->output_column_json) ? $queryRepo->output_column_json : json_decode($queryRepo->output_column_json, true) ?? [];
        $validFields = [];
        foreach ($outputColumns as $col) {
            $validFields[] = is_array($col) ? ($col['field'] ?? null) : $col;
        }

        $allowedOperators = ['contains', 'starts_with', 'ends_with', 'equals', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'before', 'after', 'between'];

        $seenFields = [];
        $validatedConfig = [];

        foreach ($filterConfig as $filter) {
            $field = $filter['field'] ?? null;
            if (!$field || !in_array($field, $validFields)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'filter_config' => "Invalid filter field: {$field}"
                ]);
            }

            if (in_array($field, $seenFields)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'filter_config' => "Duplicate filter field: {$field}"
                ]);
            }
            $seenFields[] = $field;

            $operator = $filter['operator'] ?? 'contains';
            if (!in_array($operator, $allowedOperators)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'filter_config' => "Invalid operator: {$operator}"
                ]);
            }

            $validatedConfig[] = [
                'field' => $field,
                'label' => $filter['label'] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $field)),
                'type' => $filter['type'] ?? 'text',
                'operator' => $operator,
                'placeholder' => $filter['placeholder'] ?? '',
                'required' => (bool)($filter['required'] ?? false),
            ];
        }

        return $validatedConfig;
    }
}
