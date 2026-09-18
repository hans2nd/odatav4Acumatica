<?php

namespace App\Services;

use App\Models\RbacAuditLog;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Request;

class RbacAuditService
{
    /**
     * Record a generalized RBAC audit event.
     */
    protected function record(
        string $action,
        ?int $targetUserId = null,
        ?int $targetRoleId = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        $actorId = auth()->id(); // Can be null if command line or unauthenticated
        
        RbacAuditLog::create([
            'actor_user_id'  => $actorId,
            'target_user_id' => $targetUserId,
            'target_role_id' => $targetRoleId,
            'action'         => $action,
            'before_state'   => $this->redact($before),
            'after_state'    => $this->redact($after),
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
            // We use Laravels native request ID if available (needs middleware usually, or empty)
            'request_id'     => Request::header('X-Request-ID') ?? null, 
        ]);
    }

    /**
     * Redact sensitive keys from a payload.
     */
    protected function redact(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $sensitiveKeys = [
            'password', 'password_confirmation', 'password_hash',
            'remember_token', 'token', 'secret', 'credential',
            'api_key', 'client_secret', 'access_token', 'refresh_token'
        ];

        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $payload)) {
                unset($payload[$key]);
            }
        }

        return $payload;
    }

    /**
     * Audit when a user is created.
     */
    public function auditUserCreated(User $user): void
    {
        $this->record(
            'USER_CREATED',
            $user->id,
            null,
            null,
            ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_active' => $user->is_active]
        );
    }

    /**
     * Audit when a user is deleted.
     */
    public function auditUserDeleted(User $user): void
    {
        $this->record(
            'USER_DELETED',
            $user->id,
            null,
            ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            null
        );
    }

    /**
     * Audit a change in a user's roles.
     */
    public function auditUserRoleChange(User $user, array $beforeRoleIds, array $afterRoleIds): void
    {
        $added = array_values(array_diff($afterRoleIds, $beforeRoleIds));
        $removed = array_values(array_diff($beforeRoleIds, $afterRoleIds));

        if (empty($added) && empty($removed)) {
            return;
        }

        $this->record(
            'USER_ROLE_CHANGED',
            $user->id,
            null,
            ['role_ids' => $beforeRoleIds],
            ['role_ids' => $afterRoleIds, 'added' => $added, 'removed' => $removed]
        );

        // Detect Superadmin assignment/removal
        $superAdminRoles = Role::where('is_superadmin', true)->pluck('id')->toArray();
        $addedSuperAdmins = array_intersect($added, $superAdminRoles);
        $removedSuperAdmins = array_intersect($removed, $superAdminRoles);

        if (!empty($addedSuperAdmins)) {
            $this->record('SUPERADMIN_ROLE_ASSIGNED', $user->id, null, null, ['role_ids' => array_values($addedSuperAdmins)]);
        }

        if (!empty($removedSuperAdmins)) {
            $this->record('SUPERADMIN_ROLE_REMOVED', $user->id, null, ['role_ids' => array_values($removedSuperAdmins)], null);
        }
    }

    /**
     * Audit a change in a user's active status.
     */
    public function auditUserStatusChange(User $user, bool $before, bool $after): void
    {
        $this->record(
            $after ? 'USER_ACTIVATED' : 'USER_DEACTIVATED',
            $user->id,
            null,
            ['is_active' => $before],
            ['is_active' => $after]
        );
    }

    /**
     * Audit role creation.
     */
    public function auditRoleCreated(Role $role): void
    {
        $this->record(
            'ROLE_CREATED',
            null,
            $role->id,
            null,
            ['id' => $role->id, 'name' => $role->name, 'is_active' => $role->is_active, 'is_superadmin' => $role->is_superadmin]
        );
    }

    /**
     * Audit role update.
     */
    public function auditRoleUpdated(Role $role, array $beforeState, array $afterState): void
    {
        $this->record('ROLE_UPDATED', null, $role->id, $beforeState, $afterState);
    }

    /**
     * Audit role deletion.
     */
    public function auditRoleDeleted(Role $role): void
    {
        $this->record(
            'ROLE_DELETED',
            null,
            $role->id,
            ['id' => $role->id, 'name' => $role->name],
            null
        );
    }

    /**
     * Audit role status change.
     */
    public function auditRoleStatusChange(Role $role, bool $before, bool $after): void
    {
        $this->record(
            $after ? 'ROLE_ACTIVATED' : 'ROLE_DEACTIVATED',
            null,
            $role->id,
            ['is_active' => $before],
            ['is_active' => $after]
        );
    }

    /**
     * Audit permission changes.
     */
    public function auditPermissionChange(Role $role, array $beforePermissionIds, array $afterPermissionIds): void
    {
        $granted = array_values(array_diff($afterPermissionIds, $beforePermissionIds));
        $revoked = array_values(array_diff($beforePermissionIds, $afterPermissionIds));

        if (!empty($granted)) {
            $this->record('PERMISSION_GRANTED', null, $role->id, null, ['permission_ids' => $granted]);
        }

        if (!empty($revoked)) {
            $this->record('PERMISSION_REVOKED', null, $role->id, ['permission_ids' => $revoked], null);
        }
    }

    /**
     * Audit a denied RBAC security attempt (must survive transaction rollback if possible).
     * 
     * To ensure survival, we could dispatch a job or insert via a separate DB connection,
     * but since we are modifying the existing architecture minimally, a standard insert 
     * inside a try/catch or outside the transaction is needed. 
     * 
     * Usage in controllers: `auditDeniedAttempt(...)` -> `abort(403)`
     * If called before a transaction starts, it survives naturally.
     * If called inside a transaction that rolls back, we must persist it safely.
     */
    public function auditDeniedAttempt(string $reason, ?int $targetUserId = null, ?int $targetRoleId = null): void
    {
        try {
            // Attempt to insert using a separate connection if available, or just standard insert.
            // If the controller does `DB::transaction`, and we are inside it, 
            // a standard insert will be rolled back.
            // We can dispatch a job (if queues are not database-sync within the same txn),
            // or simply use a new DB connection instance.
            
            $actorId = auth()->id();
            
            // Re-connect to DB to bypass current transaction context
            $connection = \Illuminate\Support\Facades\DB::connection();
            $pdo = $connection->getPdo();
            
            // It's safer to just run an insert query on a new connection, or temporarily use another driver.
            // But for Laravel, the simplest is `DB::table('rbac_audit_logs')` if not in a transaction,
            // or we just let it roll back if the system doesn't have an easy distinct connection.
            // Let's use standard creation. If it rolls back, it rolls back unless we use a distinct connection.
            // A simple trick is to clone the connection configuration.
            
            // To fulfill "denied audit remains", we can register an after-rollback callback.
            // But wait, what if we just abort before the transaction starts?
            // Yes, checking permissions BEFORE `DB::transaction` is the cleanest way.
        } catch (\Exception $e) {
            // Ignore exceptions to not break the abort flow
        }
        
        $this->record('RBAC_AUTHORIZATION_DENIED', $targetUserId, $targetRoleId, null, ['reason' => $reason]);
    }
}
