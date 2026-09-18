<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = [
        'parent_id', 'module_id', 'query_repository_id', 'filter_config', 'title', 'icon', 'route_name',
        'route_params', 'sort_order', 'is_active', 'is_visible',
        'permission_key', 'type', 'badge', 'open_in_new_tab',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'open_in_new_tab' => 'boolean',
        'route_params' => 'array',
        'filter_config' => 'array',
    ];

    // --- Relationships ---

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function queryRepository()
    {
        return $this->belongsTo(QueryRepository::class, 'query_repository_id');
    }

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort_order');
    }

    public function activeChildren()
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->where('is_active', true)
            ->where('is_visible', true)
            ->orderBy('sort_order');
    }

    /**
     * LEGACY RELATIONSHIP
     * This relationship uses the legacy `menu_permissions` pivot table.
     * It is NOT used for authorization.
     * The canonical permission relationship is `permission()`.
     * Do NOT use this legacy pivot for RBAC decisions.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'menu_permissions');
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class, 'permission_key', 'permission_key');
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    // --- Helpers ---

    /**
     * Check if a given user has access to this menu item.
     */
    public function hasAccess($user): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Super admin sees everything (bypass normal visibility and permission checks)
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$this->is_visible) {
            return false;
        }

        // No permission required = everyone can see
        if (empty($this->permission_key)) {
            return true;
        }

        // Check user permission
        return $user->hasPermission($this->permission_key);
    }

    /**
     * Build a nested tree from a flat collection.
     */
    public static function buildTree($items, $parentId = null): array
    {
        $branch = [];
        foreach ($items as $item) {
            if ($item->parent_id == $parentId) {
                $children = self::buildTree($items, $item->id);
                $node = $item->toArray();
                $node['children'] = $children;
                $branch[] = $node;
            }
        }
        return $branch;
    }
}