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
        return $this->roles()
            ->where('is_active', true)
            ->where('is_superadmin', true)
            ->exists();
    }

    /**
     * Check if the user has a specific permission (through any of their roles).
     */
    public function hasPermission(string $permission): bool
    {
        // Superadmin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->roles()
            ->where('is_active', true)
            ->whereHas('permissions', function ($q) use ($permission) {
                $q->where('permission_key', $permission);
            })
            ->exists();
    }

    /**
     * Check if the user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->roles()
            ->where('is_active', true)
            ->whereHas('permissions', function ($q) use ($permissions) {
                $q->whereIn('permission_key', $permissions);
            })
            ->exists();
    }

    /**
     * Get all permissions aggregated from all roles.
     */
    public function allPermissions(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::all();
        }

        $roleIds = $this->roles()->where('is_active', true)->pluck('roles.id');

        return Permission::whereHas('roles', function ($q) use ($roleIds) {
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
