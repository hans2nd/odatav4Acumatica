<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Show the user management page.
     */
    public function index()
    {
        $users = User::with('roles')->get();
        $roles = Role::withCount(['users', 'permissions'])->get();
        $modules = \App\Models\Module::with(['resources.permissions.action'])->orderBy('sort_order')->get();

        return view('users.index', [
            'users'       => $users,
            'roles'       => $roles,
            'modules'     => $modules,
            'activeTab'   => 'users',
        ]);
    }

    /**
     * Create a new user.
     */
    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id'  => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->roles()->attach($validated['role_id']);

        return response()->json([
            'success' => true,
            'message' => "User '{$user->name}' created successfully.",
            'user'    => $user->load('roles'),
        ]);
    }

    /**
     * Update a user.
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role_id'  => 'required|exists:roles,id',
        ]);

        $user->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => $validated['password']]);
        }

        $user->roles()->sync([$validated['role_id']]);

        return response()->json([
            'success' => true,
            'message' => "User '{$user->name}' updated successfully.",
            'user'    => $user->load('roles'),
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroyUser(User $user): JsonResponse
    {
        // Prevent deleting self
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $name = $user->name;
        $user->roles()->detach();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "User '{$name}' deleted successfully.",
        ]);
    }

    /**
     * Reset a user's password (admin only).
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user->update(['password' => $validated['password']]);

        return response()->json([
            'success' => true,
            'message' => "Password for '{$user->name}' has been reset.",
        ]);
    }

    /**
     * Get role details with permissions.
     */
    public function showRole(Role $role): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'role'        => $role,
            'permissions' => $role->permissions()->get(),
        ]);
    }

    /**
     * Update role permissions.
     */
    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->name === 'Administrator') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify permissions for superadmin role.',
            ], 422);
        }

        $validated = $request->validate([
            'permission_ids'   => 'present|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->sync($validated['permission_ids']);

        return response()->json([
            'success' => true,
            'message' => "Permissions for role '{$role->name}' updated successfully.",
            'count'   => count($validated['permission_ids']),
        ]);
    }

    /**
     * Create a new role.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:50|unique:roles,name|regex:/^[a-z][a-z0-9_]*$/',
            'name' => 'required|string|max:100',
            'description'  => 'nullable|string|max:255',
        ]);

        $role = Role::create($validated);

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' created successfully.",
            'role'    => $role,
        ]);
    }

    /**
     * Delete a role.
     */
    public function destroyRole(Role $role): JsonResponse
    {
        if ($role->name === 'Administrator') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the superadmin role.',
            ], 422);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete role '{$role->name}' – it still has assigned users.",
            ], 422);
        }

        $name = $role->name;
        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => "Role '{$name}' deleted successfully.",
        ]);
    }

    /**
     * Get all permissions grouped.
     */
    public function permissions(): JsonResponse
    {
        $modules = \App\Models\Module::with(['resources.permissions.action'])->orderBy('sort_order')->get();

        return response()->json([
            'success'     => true,
            'modules'     => $modules,
        ]);
    }
}
