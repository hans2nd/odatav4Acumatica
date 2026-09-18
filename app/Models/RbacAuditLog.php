<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RbacAuditLog extends Model
{
    /**
     * Explicitly defining fillable attributes instead of guarded = []
     * for security-sensitive audit models.
     */
    protected $fillable = [
        'actor_user_id',
        'target_user_id',
        'target_role_id',
        'action',
        'before_state',
        'after_state',
        'ip_address',
        'user_agent',
        'request_id',
    ];

    /**
     * Cast JSON fields automatically.
     */
    protected $casts = [
        'before_state' => 'array',
        'after_state'  => 'array',
    ];

    /**
     * The user who performed the action.
     */
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * The user who was the target of the action (if applicable).
     */
    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * The role that was the target of the action (if applicable).
     */
    public function targetRole()
    {
        return $this->belongsTo(Role::class, 'target_role_id');
    }
}
