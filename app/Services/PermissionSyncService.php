<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\ApiEndpoint;

class PermissionSyncService
{
    /**
     * Actions that are auto-generated per entity.
     */
    public const ENTITY_ACTIONS = [
        'view'   => 'View data',
        'sync'   => 'Run sync',
        'export' => 'Export data',
        'create' => 'Create records',
        'update' => 'Update records',
        'delete' => 'Delete records',
    ];

    /**
     * Static (non-entity) permissions.
     */
    public const STATIC_PERMISSIONS = [
        'settings.manage'       => ['display_name' => 'Manage Settings',           'group' => 'System'],
        'entities.manage'       => ['display_name' => 'Manage Entity Config',      'group' => 'System'],
        'logs.view'             => ['display_name' => 'View Sync Logs',            'group' => 'System'],
        'reports.view'          => ['display_name' => 'View Reports',              'group' => 'Reports'],
        'report_builder.manage' => ['display_name' => 'Manage Report Builder',     'group' => 'Reports'],
        'endpoint_api.manage'   => ['display_name' => 'Manage Endpoint API',       'group' => 'Reports'],
        'users.manage'          => ['display_name' => 'Manage Users & Roles',      'group' => 'System'],
        'dashboard.view'        => ['display_name' => 'View Dashboard',            'group' => 'System'],
        'sync.manage'           => ['display_name' => 'Manage ODataV4 Sync',       'group' => 'System'],
    ];

    /**
     * Default role permission mappings.
     * 'operator' and 'viewer' get dynamic permissions auto-assigned.
     */
    public const ROLE_DEFAULTS = [
        'operator' => [
            'static'          => [
                'logs.view', 'reports.view', 'dashboard.view',
                'sync.manage', 'report_builder.manage', 'endpoint_api.manage'
            ],
            'entity_actions'  => ['view', 'sync', 'export'],
            'endpoint_actions'=> ['view'],
        ],
        'viewer' => [
            'static'          => [
                'logs.view', 'reports.view', 'dashboard.view'
            ],
            'entity_actions'  => ['view'],
            'endpoint_actions'=> ['view'],
        ],
    ];

    protected EntityConfigService $entityConfig;

    public function __construct(EntityConfigService $entityConfig)
    {
        $this->entityConfig = $entityConfig;
    }

    /**
     * Synchronize dynamic permissions from the current entity configuration.
     * - Creates missing permissions
     * - Removes orphaned dynamic permissions
     * - Auto-assigns new dynamic permissions to operator/viewer roles
     */
    public function syncFromEntities(): void
    {
        $entities = $this->entityConfig->getAll();
        $expectedPermissions = [];

        // Build expected dynamic permissions
        foreach ($entities as $key => $entity) {
            $type = $entity['type'] ?? 'master';
            $group = $type === 'master' ? 'Master Data' : 'Transactions';

            foreach (self::ENTITY_ACTIONS as $action => $actionLabel) {
                $permName = "{$key}.{$action}";
                $expectedPermissions[$permName] = [
                    'display_name' => "{$actionLabel} — {$entity['description']}",
                    'group'        => $group,
                ];
            }
        }

        // Get current dynamic permissions in DB
        $existingDynamic = Permission::dynamic()->pluck('name')->toArray();

        // Permissions to create
        $toCreate = array_diff(array_keys($expectedPermissions), $existingDynamic);

        // Permissions to delete (orphaned)
        $toDelete = array_diff($existingDynamic, array_keys($expectedPermissions));

        // Create new permissions
        $newPermissions = [];
        foreach ($toCreate as $permName) {
            $newPermissions[] = Permission::create([
                'name'         => $permName,
                'display_name' => $expectedPermissions[$permName]['display_name'],
                'group'        => $expectedPermissions[$permName]['group'],
                'is_dynamic'   => true,
            ]);
        }

        // Delete orphaned permissions
        if (!empty($toDelete)) {
            Permission::dynamic()->whereIn('name', $toDelete)->delete();
        }

        // Auto-assign new dynamic permissions to default roles
        if (!empty($newPermissions)) {
            $this->autoAssignToRoles($newPermissions);
        }
    }

    /**
     * Ensure all static permissions exist in the database.
     */
    public function syncApiEndpoints(): void
    {
        $endpoints = ApiEndpoint::all();
        $expectedPermissions = [];

        foreach ($endpoints as $ep) {
            $permName = "api.endpoint.{$ep->endpoint_slug}.view";
            $expectedPermissions[$permName] = [
                'display_name' => "View API Endpoint - {$ep->name}",
                'group'        => 'API Endpoints',
            ];
        }

        $existingDynamic = Permission::where('name', 'like', 'api.endpoint.%')->pluck('name')->toArray();

        $toCreate = array_diff(array_keys($expectedPermissions), $existingDynamic);
        $toDelete = array_diff($existingDynamic, array_keys($expectedPermissions));

        $newPermissions = [];
        foreach ($toCreate as $permName) {
            $newPermissions[] = Permission::create([
                'name'         => $permName,
                'display_name' => $expectedPermissions[$permName]['display_name'],
                'group'        => $expectedPermissions[$permName]['group'],
                'is_dynamic'   => true,
            ]);
        }

        if (!empty($toDelete)) {
            Permission::whereIn('name', $toDelete)->delete();
        }

        if (!empty($newPermissions)) {
            // Auto-assign new dynamic API permissions
            foreach (self::ROLE_DEFAULTS as $roleName => $config) {
                $role = Role::where('name', $roleName)->first();
                if (!$role) continue;

                if (in_array('view', $config['endpoint_actions'] ?? [])) {
                    $role->permissions()->syncWithoutDetaching(collect($newPermissions)->pluck('id'));
                }
            }
        }
    }

    /**
     * Ensure all static permissions exist in the database and are auto-assigned to default roles.
     */
    public function ensureStaticPermissions(): void
    {
        $newStatics = [];
        foreach (self::STATIC_PERMISSIONS as $name => $meta) {
            $perm = Permission::firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $meta['display_name'],
                    'group'        => $meta['group'],
                    'is_dynamic'   => false,
                ]
            );
            if ($perm->wasRecentlyCreated) {
                $newStatics[] = $perm;
            }
        }

        // Auto assign static permissions to default roles
        foreach (self::ROLE_DEFAULTS as $roleName => $config) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) continue;

            $staticsToAttach = Permission::whereIn('name', $config['static'] ?? [])->pluck('id')->toArray();
            if (!empty($staticsToAttach)) {
                $role->permissions()->syncWithoutDetaching($staticsToAttach);
            }
        }
    }

    /**
     * Full sync: static + dynamic permissions + endpoints.
     */
    public function fullSync(): void
    {
        $this->ensureStaticPermissions();
        $this->syncFromEntities();
        $this->syncApiEndpoints();
    }

    /**
     * Auto-assign newly created dynamic permissions to operator and viewer roles
     * based on their allowed entity_actions.
     *
     * @param Permission[] $newPermissions
     */
    protected function autoAssignToRoles(array $newPermissions): void
    {
        foreach (self::ROLE_DEFAULTS as $roleName => $config) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                continue;
            }

            $allowedActions = $config['entity_actions'];
            $permIdsToAttach = [];

            foreach ($newPermissions as $perm) {
                // Extract action from permission name (e.g., "Customer.view" → "view")
                $parts = explode('.', $perm->name);
                $action = end($parts);

                if (in_array($action, $allowedActions)) {
                    $permIdsToAttach[] = $perm->id;
                }
            }

            if (!empty($permIdsToAttach)) {
                $role->permissions()->syncWithoutDetaching($permIdsToAttach);
            }
        }
    }
}
