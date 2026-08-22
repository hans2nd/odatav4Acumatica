<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueryRepository extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'database_connection_id',
        'category',
        'name',
        'description',
        'sql_text',
        'query_type',
        'parameter_json',
        'ui_state_json',
        'output_column_json',
        'version',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'parameter_json' => 'array',
        'ui_state_json' => 'array',
        'output_column_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function connection()
    {
        return $this->belongsTo(Connection::class, 'database_connection_id');
    }

    public function logs()
    {
        return $this->hasMany(QueryRepositoryLog::class);
    }
}
