<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['resource_id', 'action_id', 'permission_key', 'description'];

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function action()
    {
        return $this->belongsTo(Action::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}