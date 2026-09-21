<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'connection_key',
        'table_name',
        'configuration'
    ];

    protected $casts = [
        'configuration' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'report_template_role');
    }
}
