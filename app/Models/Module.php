<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['code', 'name', 'icon', 'sort_order', 'is_active'];

    public function resources()
    {
        return $this->hasMany(Resource::class);
    }
}