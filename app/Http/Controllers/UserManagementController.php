<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Show the user management page.
     */
    public function index()
    {
        $users = User::with('roles')->get();
        $roles = Role::withCount(['users', 'permissions'])->get();
        $modules = \App\Models\Module::with(['resources.permissions.action'])->orderBy('sort_order')->get();

        $connections = app(\App\Services\ConnectionService::class)->getAll();
        $sqlConnections = [];
        foreach ($connections as $key => $conn) {
            if (in_array($conn['type'] ?? '', ['sqlserver', 'mysql', 'pgsql', 'sqlite', 'firebird'])) {
                $sqlConnections[] = [
                    'key' => $key,
                    'name' => $conn['name'] ?? $key,
                ];
            }
        }

        return view('users.index', [
            'users'          => $users,
            'roles'          => $roles,
            'modules'        => $modules,
            'sqlConnections' => $sqlConnections,
            'activeTab'      => 'users',
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        // Backward compatibility
        if ($request->has('role_id') && !$request->has('role_ids')) {
            $request->merge(['role_ids' => [$request->input('role_id')]]);
        }

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:6',
            'role_ids'   => 'required|array|min:1',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $targetRoles = Role::whereIn('id', $validated['role_ids'])->get();
        $targetHasSuperAdmin = $targetRoles->where('is_superadmin', true)->isNotEmpty();

        if ($targetHasSuperAdmin && (!auth()->user() || !auth()->user()->isSuperAdmin())) {
            app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Unauthorized Super Administrator role assignment in storeUser');
            abort(403, 'You are not authorized to assign the Super Administrator role.');
        }

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->roles()->sync($validated['role_ids']);
        app(\App\Services\RbacAuditService::class)->auditUserCreated($user);
        app(\App\Services\RbacAuditService::class)->auditUserRoleChange($user, [], $validated['role_ids']);

        return response()->json([
            'success' => true,
            'message' => "User '{$user->name}' created successfully.",
            'user'    => $user->load('roles'),
        ]);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        // Backward compatibility
        if ($request->has('role_id') && !$request->has('role_ids')) {
            $request->merge(['role_ids' => [$request->input('role_id')]]);
        }

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'   => 'nullable|string|min:6',
            'role_ids'   => 'required|array|min:1',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $targetRoles = Role::whereIn('id', $validated['role_ids'])->get();
        $targetHasSuperAdmin = $targetRoles->where('is_superadmin', true)->isNotEmpty();

        if ($targetHasSuperAdmin && (!auth()->user() || !auth()->user()->isSuperAdmin())) {
            app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Unauthorized Super Administrator role assignment in updateUser', $user->id);
            abort(403, 'You are not authorized to assign the Super Administrator role.');
        }

        if ($user->isSuperAdmin() && (!auth()->user() || !auth()->user()->isSuperAdmin())) {
            app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Unauthorized modification of Super Administrator in updateUser', $user->id);
            abort(403, 'You are not authorized to modify a Super Administrator.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $validated, $targetHasSuperAdmin) {
            $user->update([
                'name'  => $validated['name'],
                'email' => $validated['email'],
            ]);

            if (!empty($validated['password'])) {
                $user->update(['password' => $validated['password']]);
            }

            if ($user->isSuperAdmin() && !$targetHasSuperAdmin) {
                $activeSuperAdminCount = User::where('is_active', true)
                    ->whereHas('roles', function($q) {
                        $q->where('roles.is_superadmin', true)->where('roles.is_active', true);
                    })->lockForUpdate()->count();
                    
                if ($activeSuperAdminCount <= 1) {
                    app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Attempted to remove the last active Super Administrator role', $user->id);
                    abort(403, 'At least one active Super Administrator must remain.');
                }
            }

            $beforeRoleIds = $user->roles()->pluck('roles.id')->toArray();
            $user->roles()->sync($validated['role_ids']);
            app(\App\Services\RbacAuditService::class)->auditUserRoleChange($user, $beforeRoleIds, $validated['role_ids']);
            $user->clearAuthorizationCache();
            \App\Services\MenuService::clearUserCache($user->id);
        });

        return response()->json([
            'success' => true,
            'message' => "User '{$user->name}' updated successfully.",
            'user'    => $user->load('roles'),
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroyUser(User $user): JsonResponse
    {
        // Prevent deleting self
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        if ($user->isSuperAdmin() && (!auth()->user() || !auth()->user()->isSuperAdmin())) {
            app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Unauthorized deletion of Super Administrator', $user->id);
            abort(403, 'You are not authorized to delete a Super Administrator.');
        }

        $name = $user->name;

        if ($user->isSuperAdmin()) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
                $activeSuperAdminCount = User::where('is_active', true)
                    ->whereHas('roles', function($q) {
                        $q->where('roles.is_superadmin', true)->where('roles.is_active', true);
                    })->lockForUpdate()->count();
                if ($activeSuperAdminCount <= 1) {
                    app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Attempted to delete the last active Super Administrator', $user->id);
                    abort(403, 'At least one active Super Administrator must remain.');
                }
                $user->roles()->detach();
                $user->delete();
                app(\App\Services\RbacAuditService::class)->auditUserDeleted($user);
                \App\Services\MenuService::clearUserCache($user->id);
            });
        } else {
            $user->roles()->detach();
            $user->delete();
            app(\App\Services\RbacAuditService::class)->auditUserDeleted($user);
            \App\Services\MenuService::clearUserCache($user->id);
        }

        return response()->json([
            'success' => true,
            'message' => "User '{$name}' deleted successfully.",
        ]);
    }

    /**
     * Reset a user's password (admin only).
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        if ($user->isSuperAdmin() && (!auth()->user() || !auth()->user()->isSuperAdmin())) {
            app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Unauthorized password reset of Super Administrator', $user->id);
            abort(403, 'You are not authorized to reset password for a Super Administrator.');
        }

        $user->update(['password' => $validated['password']]);

        return response()->json([
            'success' => true,
            'message' => "Password for '{$user->name}' has been reset.",
        ]);
    }

    /**
     * Get role details with permissions.
     */
    public function showRole(Role $role): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'role'        => $role,
            'permissions' => $role->permissions()->get(),
        ]);
    }

    /**
     * Update role permissions.
     */
    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->is_superadmin) {
            if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
                app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Unauthorized modification of Super Administrator role permissions', null, $role->id);
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to modify Super Administrator permissions.',
                ], 403);
            }
            app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Attempted to modify immutable Super Administrator role permissions', null, $role->id);
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify permissions for superadmin role. Full access is implicit.',
            ], 422);
        }

        $validated = $request->validate([
            'permission_ids'   => 'present|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $beforePermissionIds = $role->permissions()->pluck('permissions.id')->toArray();
        $role->permissions()->sync($validated['permission_ids']);
        app(\App\Services\RbacAuditService::class)->auditPermissionChange($role, $beforePermissionIds, $validated['permission_ids']);
        foreach ($role->users as $roleUser) {
            $roleUser->clearAuthorizationCache();
        }
        \App\Services\MenuService::clearMenuCache();

        return response()->json([
            'success' => true,
            'message' => "Permissions for role '{$role->name}' updated successfully.",
            'count'   => count($validated['permission_ids']),
        ]);
    }

    /**
     * Create a new role.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100|unique:roles,name',
            'description'  => 'nullable|string|max:255',
        ]);

        $role = Role::create($validated);
        app(\App\Services\RbacAuditService::class)->auditRoleCreated($role);

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' created successfully.",
            'role'    => $role,
        ]);
    }

    /**
     * Delete a role.
     */
    public function destroyRole(Role $role): JsonResponse
    {
        if ($role->is_superadmin) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the superadmin role.',
            ], 422);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete role '{$role->name}' – it still has assigned users.",
            ], 422);
        }

        $name = $role->name;
        $role->permissions()->detach();
        $role->delete();
        app(\App\Services\RbacAuditService::class)->auditRoleDeleted($role);
        \App\Services\MenuService::clearMenuCache();

        return response()->json([
            'success' => true,
            'message' => "Role '{$name}' deleted successfully.",
        ]);
    }

    public function toggleActiveStatus(Request $request, User $user): JsonResponse
    {
        if ($user->isSuperAdmin() && (!auth()->user() || !auth()->user()->isSuperAdmin())) {
            app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Unauthorized modification of Super Administrator status', $user->id);
            abort(403, 'You are not authorized to modify a Super Administrator.');
        }

        if ($user->id === auth()->id() && $user->is_active) {
            app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Attempted self-deactivation', $user->id);
            abort(403, 'You cannot deactivate your own account.');
        }

        $newState = !$user->is_active;

        $beforeStatus = $user->is_active;

        if ($user->isSuperAdmin() && !$newState) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user, $newState, $beforeStatus) {
                $activeSuperAdminCount = User::where('is_active', true)
                    ->whereHas('roles', function($q) {
                        $q->where('roles.is_superadmin', true)->where('roles.is_active', true);
                    })->lockForUpdate()->count();
                if ($activeSuperAdminCount <= 1) {
                    app(\App\Services\RbacAuditService::class)->auditDeniedAttempt('Attempted to deactivate the last active Super Administrator', $user->id);
                    abort(403, 'At least one active Super Administrator must remain.');
                }
                $user->update(['is_active' => $newState]);
                $user->clearAuthorizationCache();
                \App\Services\MenuService::clearUserCache($user->id);
                app(\App\Services\RbacAuditService::class)->auditUserStatusChange($user, $beforeStatus, $newState);
            });
        } else {
            $user->update(['is_active' => $newState]);
            $user->clearAuthorizationCache();
            \App\Services\MenuService::clearUserCache($user->id);
            app(\App\Services\RbacAuditService::class)->auditUserStatusChange($user, $beforeStatus, $newState);
        }

        return response()->json([
            'success' => true,
            'message' => "User '{$user->name}' status updated successfully.",
            'is_active' => $user->is_active
        ]);
    }

    public function permissions(): JsonResponse
    {
        // 1. Fetch menu tree with permissions
        $menus = \App\Models\Menu::with(['permission.action'])->orderBy('sort_order')->get();
        
        // 2. Fetch any orphaned or non-menu permissions
        $menuPermissionKeys = \App\Models\Menu::whereNotNull('permission_key')->pluck('permission_key')->toArray();
        $otherPermissions = \App\Models\Permission::with('action')->whereNotIn('permission_key', $menuPermissionKeys)->get();

        // Load entity definitions
        $allEntities = app(\App\Services\EntityConfigService::class)->getAll();

        // Create a mapping from entity_key to their permissions
        $entityPermissions = [];
        $remainingOther = [];

        foreach ($otherPermissions as $perm) {
            $parts = explode('.', $perm->permission_key);
            $prefix = $parts[0];

            if (isset($allEntities[$prefix])) {
                $entityPermissions[$prefix][] = $perm;
            } else {
                $remainingOther[] = $perm;
            }
        }

        $menusArray = $menus->toArray();
        foreach ($menusArray as &$m) {
            $m['permissions'] = !empty($m['permission']) ? [$m['permission']] : [];
            unset($m['permission']);
        }
        unset($m);
        
        $masterDataMenuId = null;
        $transactionMenuId = null;
        
        foreach ($menusArray as $menu) {
            if ($menu['route_name'] === 'master-data.index') {
                $masterDataMenuId = $menu['id'];
            } elseif ($menu['route_name'] === 'transactions.index') {
                $transactionMenuId = $menu['id'];
            }
        }

        // Add virtual menus for entities
        foreach ($entityPermissions as $entityKey => $perms) {
            $config = $allEntities[$entityKey];
            $type = $config['type']; // 'master' or 'transaction'
            
            $parentId = null;
            if ($type === 'master' && $masterDataMenuId) {
                $parentId = $masterDataMenuId;
            } elseif ($type === 'transaction' && $transactionMenuId) {
                $parentId = $transactionMenuId;
            }
            
            // Create a virtual menu item
            $virtualMenu = [
                'id' => 'virtual-' . $entityKey,
                'parent_id' => $parentId,
                'title' => $config['description'] ?? preg_replace('/([A-Z])/', ' $1', $entityKey),
                'icon' => $config['icon'] ?? 'database',
                'permissions' => $perms,
                'children' => [],
                'sort_order' => 9999,
            ];
            
            $menusArray[] = $virtualMenu;
        }

        // Map remaining other permissions directly to their matching menus
        $finalOther = [];
        $apiEndpointPermissions = [];

        foreach ($remainingOther as $perm) {
            $parts = explode('.', $perm->permission_key);
            $prefix = $parts[0];
            
            // Extract api.endpoint.* permissions
            if ($prefix === 'api' && isset($parts[1]) && $parts[1] === 'endpoint') {
                $endpointSlug = $parts[2] ?? '';
                if ($endpointSlug) {
                    if (!isset($apiEndpointPermissions[$endpointSlug])) {
                        $apiEndpointPermissions[$endpointSlug] = [
                            'entity' => $endpointSlug,
                            'permissions' => []
                        ];
                    }
                    $apiEndpointPermissions[$endpointSlug]['permissions'][] = $perm;
                } else {
                    $finalOther[] = $perm;
                }
                continue;
            }

            // Extract connection.*.access permissions (Database Connections Access)
            if ($prefix === 'connection' && isset($parts[2]) && $parts[2] === 'access') {
                continue; // We'll handle dbConnections separately below
            }

            $assigned = false;

            // Special case: sync_engine goes to Synchronization
            if ($prefix === 'sync_engine') {
                foreach ($menusArray as &$m) {
                    if ($m['title'] === 'Synchronization') {
                        if (!isset($m['permissions'])) $m['permissions'] = [];
                        $m['permissions'][] = $perm;
                        $assigned = true;
                        break;
                    }
                }
                unset($m);
            }

            // General case: match prefix to menu's permission_key prefix
            if (!$assigned) {
                foreach ($menusArray as &$m) {
                    if (!empty($m['permission_key'])) {
                        $menuPrefix = explode('.', $m['permission_key'])[0];
                        if ($menuPrefix === $prefix) {
                            if (!isset($m['permissions'])) $m['permissions'] = [];
                            $m['permissions'][] = $perm;
                            $assigned = true;
                            break;
                        }
                    }
                }
                unset($m);
            }

            if (!$assigned) {
                $finalOther[] = $perm;
            }
        }

        // Build the tree from the array
        $buildArrayTree = function ($items, $parentId = null) use (&$buildArrayTree) {
            $branch = [];
            foreach ($items as $item) {
                if ($item['parent_id'] == $parentId) {
                    $children = $buildArrayTree($items, $item['id']);
                    $item['children'] = $children;
                    $branch[] = $item;
                }
            }
            return $branch;
        };

        $menuTree = $buildArrayTree($menusArray);

        // Ensure DB connection permissions exist
        $dbConnectionPermissions = [];
        
        // Use ConnectionService to get the name/metadata
        $allConnections = app(\App\Services\ConnectionService::class)->getAll();
        
        $reportModule = \App\Models\Module::where('code', 'report')->first();
        $accessAction = \App\Models\Action::where('code', 'access')->first() ?? \App\Models\Action::firstOrCreate(['code' => 'access'], ['name' => 'Access', 'sort_order' => 100]);

        foreach ($allConnections as $key => $conn) {
            if (in_array($conn['type'] ?? '', \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes())) {
                
                $resource = \App\Models\Resource::firstOrCreate(
                    ['slug' => "connection.{$key}"],
                    [
                        'name' => "Database Connection: " . ($conn['name'] ?? $key),
                        'module_id' => $reportModule ? $reportModule->id : 1,
                        'description' => "Dynamically generated Database Connection"
                    ]
                );

                $perm = \App\Models\Permission::firstOrCreate(
                    [
                        'permission_key' => "connection.{$key}.access"
                    ],
                    [
                        'resource_id' => $resource->id,
                        'action_id' => $accessAction->id,
                        'description' => "Access connection " . ($conn['name'] ?? $key)
                    ]
                );
                
                $dbConnectionPermissions[] = [
                    'entity' => $conn['name'] ?? $key,
                    'key' => $key,
                    'permissions' => [$perm]
                ];
            }
        }

        return response()->json([
            'success'                 => true,
            'menus'                   => $menuTree,
            'otherPermissions'        => $finalOther,
            'apiEndpointPermissions'  => array_values($apiEndpointPermissions),
            'dbConnectionPermissions' => $dbConnectionPermissions,
        ]);
    }

    public function getTablePermissions(Request $request, \App\Models\Role $role)
    {
        $connKey = $request->query('connection');
        if (!$connKey) {
            return response()->json(['success' => false, 'message' => 'Connection key is required']);
        }

        try {
            $connManager = app(\App\Services\Connections\ConnectionManager::class);
            $tree = $connManager->discoverMetadata($connKey);
            $systemTables = [
                'migrations', 'users', 'password_reset_tokens', 'personal_access_tokens',
                'failed_jobs', 'jobs', 'api_endpoints', 'connections', 
                'sync_jobs', 'sync_logs', 'sync_metadata_definitions', 
                'sync_metadata_fields', 'sync_metadata_history', 
                'sync_metadata_refresh_history', 'permissions', 'roles', 
                'model_has_permissions', 'model_has_roles', 'role_has_permissions',
                'cache', 'cache_locks', 'sessions'
            ];

            $tables = [];
            foreach ($tree->nodes as $node) {
                if (!in_array(strtolower($node->name), $systemTables)) {
                    $tables[] = $node->name;
                }
            }
            sort($tables);

            // Fetch the role's current table permissions for this connection
            $rolePermissions = $role->permissions()
                ->where('permission_key', 'LIKE', "connection.{$connKey}.table.%.access")
                ->pluck('permission_key')
                ->toArray();

            $allowedTables = [];
            foreach ($rolePermissions as $perm) {
                if (preg_match('/connection\.[^\.]+\.table\.(.+)\.access/', $perm, $matches)) {
                    $allowedTables[] = $matches[1];
                }
            }

            return response()->json([
                'success' => true,
                'tables' => $tables,
                'allowed_tables' => $allowedTables
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateTablePermissions(Request $request, \App\Models\Role $role)
    {
        if ($role->is_superadmin) {
            return response()->json(['success' => false, 'message' => 'Cannot modify permissions for superadmin role.'], 422);
        }

        $request->validate([
            'connection' => 'required|string',
            'tables' => 'nullable|array',
            'tables.*' => 'string'
        ]);

        $connKey = $request->input('connection');
        $allowedTables = $request->input('tables', []);

        // Find or create the resource for the connection
        $resource = \App\Models\Resource::firstOrCreate(
            ['name' => "Database Connection"],
            ['slug' => 'database-connection', 'description' => "Database Connection permissions"]
        );

        $accessAction = \App\Models\Action::firstOrCreate(
            ['code' => 'access'],
            ['name' => 'Access']
        );

        $permissionIdsToSync = [];
        foreach ($allowedTables as $table) {
            $perm = \App\Models\Permission::firstOrCreate(
                ['permission_key' => "connection.{$connKey}.table.{$table}.access"],
                [
                    'resource_id' => $resource->id,
                    'action_id' => $accessAction->id,
                    'description' => "Access table {$table} on {$connKey}"
                ]
            );
            $permissionIdsToSync[] = $perm->id;
        }

        // We want to sync ONLY the table permissions for THIS connection.
        // So we get the role's current permissions, remove the ones for this connection's tables,
        // and then merge the new ones.
        $currentPermIds = $role->permissions()->pluck('permissions.id')->toArray();
        $connTablePermIds = \App\Models\Permission::where('permission_key', 'LIKE', "connection.{$connKey}.table.%.access")
            ->pluck('id')->toArray();

        $filteredPermIds = array_diff($currentPermIds, $connTablePermIds);
        $finalPermIds = array_merge($filteredPermIds, $permissionIdsToSync);

        $role->permissions()->sync($finalPermIds);
        foreach ($role->users as $roleUser) {
            $roleUser->clearAuthorizationCache();
        }
        \App\Services\MenuService::clearMenuCache();

        return response()->json([
            'success' => true,
            'message' => 'Table permissions updated successfully.'
        ]);
    }
}
