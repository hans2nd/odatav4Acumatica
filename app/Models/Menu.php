<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = ['parent_id', 'module_id', 'title', 'icon', 'route_name', 'sort_order'];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('sort_order');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'menu_permissions');
    }
}