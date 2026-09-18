<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar_url',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Roles assigned to this user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * @var bool|null
     */
    protected $cachedIsSuperAdmin = null;

    /**
     * @var array|null
     */
    protected $cachedPermissionsList = null;

    /**
     * Clear request-level authorization caches.
     */
    public function clearAuthorizationCache(): void
    {
        $this->cachedIsSuperAdmin = null;
        $this->cachedPermissionsList = null;
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if the user is a superadmin (has an active role with is_superadmin = true).
     */
    public function isSuperAdmin(): bool
    {
        // Graceful fallback if migration hasn't run and column is missing
        if (array_key_exists('is_active', $this->attributes) && !$this->attributes['is_active']) {
            return false;
        }

        if ($this->cachedIsSuperAdmin !== null) {
            return $this->cachedIsSuperAdmin;
        }

        $this->cachedIsSuperAdmin = $this->roles()
            ->where('roles.is_active', true)
            ->where('roles.is_superadmin', true)
            ->exists();

        return $this->cachedIsSuperAdmin;
    }

    /**
     * Load effective permissions from all active roles, deduplicating keys.
     */
    protected function loadEffectivePermissions(): void
    {
        if ($this->cachedPermissionsList !== null) {
            return; // Already loaded
        }

        $roleIds = $this->roles()->where('roles.is_active', true)->pluck('roles.id');

        $this->cachedPermissionsList = \App\Models\Permission::whereHas('roles', function ($q) use ($roleIds) {
            $q->whereIn('roles.id', $roleIds);
        })->pluck('permission_key')->unique()->values()->toArray();
    }

    /**
     * Check if the user has a specific permission (through any of their roles).
     */
    public function hasPermission(string $permission): bool
    {
        if (array_key_exists('is_active', $this->attributes) && !$this->attributes['is_active']) {
            return false;
        }

        // Superadmin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->loadEffectivePermissions();
        return in_array($permission, $this->cachedPermissionsList, true);
    }

    /**
     * Check if the user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if (array_key_exists('is_active', $this->attributes) && !$this->attributes['is_active']) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->loadEffectivePermissions();
        return count(array_intersect($permissions, $this->cachedPermissionsList)) > 0;
    }

    public function allPermissions(): \Illuminate\Database\Eloquent\Collection
    {
        if (array_key_exists('is_active', $this->attributes) && !$this->attributes['is_active']) {
            return \Illuminate\Database\Eloquent\Collection::make([]);
        }

        if ($this->isSuperAdmin()) {
            return \App\Models\Permission::all();
        }

        // We load full objects here, bypassing the string cache since this requests Eloquent models
        $roleIds = $this->roles()->where('roles.is_active', true)->pluck('roles.id');

        return \App\Models\Permission::whereHas('roles', function ($q) use ($roleIds) {
            $q->whereIn('roles.id', $roleIds);
        })->get();
    }

    /**
     * Get the primary role display name.
     */
    public function getPrimaryRoleAttribute(): string
    {
        $role = $this->roles()->first();
        return $role ? $role->name : 'No Role';
    }

    /**
     * Get the primary role name.
     */
    public function getPrimaryRoleNameAttribute(): string
    {
        $role = $this->roles()->first();
        return $role ? $role->name : '';
    }
}
