<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'group',
        'is_dynamic',
    ];

    protected $casts = [
        'is_dynamic' => 'boolean',
    ];

    /**
     * Roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    /**
     * Scope: only dynamic (auto-generated from entities) permissions.
     */
    public function scopeDynamic(Builder $query): Builder
    {
        return $query->where('is_dynamic', true);
    }

    /**
     * Scope: only static (manually defined) permissions.
     */
    public function scopeStatic(Builder $query): Builder
    {
        return $query->where('is_dynamic', false);
    }
}
