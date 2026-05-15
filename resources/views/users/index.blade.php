@extends('layouts.app')
@section('title', 'User Management — PL Sync Engine')
@section('page-title', 'User Management')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-h2 font-h2 text-on-surface">Users & Roles</h2>
            <p class="text-body-md text-on-surface-variant mt-1">Manage user accounts, roles, and permissions</p>
        </div>
        <button onclick="openCreateUserModal()" class="flex items-center gap-2 bg-primary text-on-primary px-4 py-2 rounded hover:bg-primary-container hover:text-on-primary-container transition-colors text-label-md font-label-md">
            <span class="material-symbols-outlined text-sm">person_add</span> Add User
        </button>
    </div>

    {{-- Users Table --}}
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-surface-container-low border-b border-outline-variant">
                <tr>
                    <th class="px-6 py-3 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Role</th>
                    <th class="px-6 py-3 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-label-sm font-label-sm text-on-surface-variant uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant">
                @foreach($users as $u)
                <tr class="hover:bg-surface-container-low transition-colors" id="user-row-{{ $u->id }}">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-primary-container flex items-center justify-center text-on-primary-container font-bold text-sm">
                                {{ strtoupper(substr($u->name, 0, 1)) }}
                            </div>
                            <span class="font-medium text-on-surface text-body-md">{{ $u->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-body-md text-on-surface-variant">{{ $u->email }}</td>
                    <td class="px-6 py-4">
                        @foreach($u->roles as $r)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $r->is_superadmin ? 'bg-red-100 text-red-800' : ($r->name === 'operator' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800') }}">
                            {{ $r->display_name }}
                        </span>
                        @endforeach
                    </td>
                    <td class="px-6 py-4 text-body-md text-on-surface-variant">{{ $u->created_at->format('d M Y') }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <button onclick="openEditUserModal({{ json_encode($u) }})" class="p-1.5 rounded hover:bg-surface-container transition-colors text-on-surface-variant" title="Edit">
                                <span class="material-symbols-outlined text-sm">edit</span>
                            </button>
                            <button onclick="openResetPasswordModal({{ $u->id }}, '{{ $u->name }}')" class="p-1.5 rounded hover:bg-surface-container transition-colors text-on-surface-variant" title="Reset Password">
                                <span class="material-symbols-outlined text-sm">key</span>
                            </button>
                            @if($u->id !== auth()->id())
                            <button onclick="deleteUser({{ $u->id }}, '{{ $u->name }}')" class="p-1.5 rounded hover:bg-red-50 transition-colors text-red-600" title="Delete">
                                <span class="material-symbols-outlined text-sm">delete</span>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Roles Section --}}
    <div class="flex justify-between items-center mt-8">
        <h2 class="text-h2 font-h2 text-on-surface">Roles & Permissions</h2>
        <button onclick="openCreateRoleModal()" class="flex items-center gap-2 bg-surface-container-low text-on-surface px-4 py-2 rounded border border-outline-variant hover:bg-surface-container transition-colors text-label-md font-label-md">
            <span class="material-symbols-outlined text-sm">add</span> New Role
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($roles as $role)
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-on-surface-variant" style="font-variation-settings: 'FILL' 1;">
                        {{ $role->is_superadmin ? 'shield_person' : 'group' }}
                    </span>
                    <h3 class="font-semibold text-on-surface">{{ $role->display_name }}</h3>
                </div>
                @if($role->is_superadmin)
                    <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Super</span>
                @endif
            </div>
            <p class="text-sm text-on-surface-variant mb-3">{{ $role->description ?? 'No description' }}</p>
            <div class="flex items-center justify-between text-xs text-on-surface-variant">
                <span>{{ $role->users_count }} user(s) · {{ $role->is_superadmin ? 'All' : $role->permissions_count }} permissions</span>
                <div class="flex gap-1">
                    <button onclick="openRolePermissionsModal({{ $role->id }}, '{{ $role->display_name }}', {{ $role->is_superadmin ? 'true' : 'false' }})" class="p-1 rounded hover:bg-surface-container transition-colors" title="Edit Permissions">
                        <span class="material-symbols-outlined text-sm">tune</span>
                    </button>
                    @if(!$role->is_superadmin && $role->users_count === 0)
                    <button onclick="deleteRole({{ $role->id }}, '{{ $role->display_name }}')" class="p-1 rounded hover:bg-red-50 text-red-600 transition-colors" title="Delete">
                        <span class="material-symbols-outlined text-sm">delete</span>
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

{{-- Modal: Create/Edit User --}}
<div id="user-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="closeModal('user-modal')"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-surface-container-lowest rounded-2xl shadow-2xl border border-outline-variant p-6">
        <h3 id="user-modal-title" class="text-h2 font-h2 text-on-surface mb-4">Add User</h3>
        <form id="user-form" onsubmit="submitUserForm(event)">
            <input type="hidden" id="user-id" value="">
            <div class="space-y-4">
                <div>
                    <label class="block text-label-sm font-label-sm text-on-surface-variant mb-1">Name</label>
                    <input type="text" id="user-name" required class="w-full px-3 py-2 border border-outline-variant rounded-lg bg-surface text-on-surface text-body-md focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div>
                    <label class="block text-label-sm font-label-sm text-on-surface-variant mb-1">Email</label>
                    <input type="email" id="user-email" required class="w-full px-3 py-2 border border-outline-variant rounded-lg bg-surface text-on-surface text-body-md focus:ring-2 focus:ring-primary focus:border-primary">
                </div>
                <div id="password-field">
                    <label class="block text-label-sm font-label-sm text-on-surface-variant mb-1">Password</label>
                    <input type="password" id="user-password" class="w-full px-3 py-2 border border-outline-variant rounded-lg bg-surface text-on-surface text-body-md focus:ring-2 focus:ring-primary focus:border-primary" minlength="6">
                </div>
                <div>
                    <label class="block text-label-sm font-label-sm text-on-surface-variant mb-1">Role</label>
                    <select id="user-role" required class="w-full px-3 py-2 border border-outline-variant rounded-lg bg-surface text-on-surface text-body-md focus:ring-2 focus:ring-primary focus:border-primary">
                        @foreach($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeModal('user-modal')" class="px-4 py-2 text-on-surface-variant hover:bg-surface-container rounded-lg transition-colors text-label-md">Cancel</button>
                <button type="submit" id="user-submit-btn" class="px-4 py-2 bg-primary text-on-primary rounded-lg hover:bg-primary-container hover:text-on-primary-container transition-colors text-label-md font-label-md">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal: Reset Password --}}
<div id="reset-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="closeModal('reset-modal')"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-sm bg-surface-container-lowest rounded-2xl shadow-2xl border border-outline-variant p-6">
        <h3 class="text-h2 font-h2 text-on-surface mb-2">Reset Password</h3>
        <p id="reset-user-name" class="text-body-md text-on-surface-variant mb-4"></p>
        <input type="hidden" id="reset-user-id">
        <div>
            <label class="block text-label-sm font-label-sm text-on-surface-variant mb-1">New Password</label>
            <input type="password" id="reset-password" required minlength="6" class="w-full px-3 py-2 border border-outline-variant rounded-lg bg-surface text-on-surface text-body-md">
        </div>
        <div class="flex justify-end gap-2 mt-6">
            <button onclick="closeModal('reset-modal')" class="px-4 py-2 text-on-surface-variant hover:bg-surface-container rounded-lg transition-colors text-label-md">Cancel</button>
            <button onclick="submitResetPassword()" class="px-4 py-2 bg-primary text-on-primary rounded-lg hover:bg-primary-container hover:text-on-primary-container transition-colors text-label-md font-label-md">Reset</button>
        </div>
    </div>
</div>

{{-- Modal: Role Permissions --}}
<div id="perm-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="closeModal('perm-modal')"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl max-h-[80vh] bg-surface-container-lowest rounded-2xl shadow-2xl border border-outline-variant flex flex-col">
        <div class="p-6 border-b border-outline-variant">
            <h3 id="perm-modal-title" class="text-h2 font-h2 text-on-surface">Role Permissions</h3>
            <p class="text-body-md text-on-surface-variant mt-1">Select permissions for this role</p>
        </div>
        <div id="perm-content" class="p-6 overflow-y-auto flex-1">
            <p class="text-on-surface-variant">Loading...</p>
        </div>
        <div class="p-4 border-t border-outline-variant flex justify-end gap-2">
            <button onclick="closeModal('perm-modal')" class="px-4 py-2 text-on-surface-variant hover:bg-surface-container rounded-lg transition-colors text-label-md">Cancel</button>
            <button id="perm-save-btn" onclick="saveRolePermissions()" class="px-4 py-2 bg-primary text-on-primary rounded-lg hover:bg-primary-container hover:text-on-primary-container transition-colors text-label-md font-label-md">Save Permissions</button>
        </div>
    </div>
</div>

{{-- Modal: Create Role --}}
<div id="role-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="closeModal('role-modal')"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-surface-container-lowest rounded-2xl shadow-2xl border border-outline-variant p-6">
        <h3 class="text-h2 font-h2 text-on-surface mb-4">Create Role</h3>
        <form onsubmit="submitCreateRole(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-label-sm font-label-sm text-on-surface-variant mb-1">Name (lowercase, no spaces)</label>
                    <input type="text" id="role-name" required pattern="^[a-z][a-z0-9_]*$" class="w-full px-3 py-2 border border-outline-variant rounded-lg bg-surface text-on-surface text-body-md">
                </div>
                <div>
                    <label class="block text-label-sm font-label-sm text-on-surface-variant mb-1">Display Name</label>
                    <input type="text" id="role-display-name" required class="w-full px-3 py-2 border border-outline-variant rounded-lg bg-surface text-on-surface text-body-md">
                </div>
                <div>
                    <label class="block text-label-sm font-label-sm text-on-surface-variant mb-1">Description</label>
                    <input type="text" id="role-description" class="w-full px-3 py-2 border border-outline-variant rounded-lg bg-surface text-on-surface text-body-md">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeModal('role-modal')" class="px-4 py-2 text-on-surface-variant hover:bg-surface-container rounded-lg transition-colors text-label-md">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-on-primary rounded-lg hover:bg-primary-container hover:text-on-primary-container transition-colors text-label-md font-label-md">Create</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let currentPermRoleId = null;

    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function openCreateUserModal() {
        document.getElementById('user-modal-title').textContent = 'Add User';
        document.getElementById('user-id').value = '';
        document.getElementById('user-name').value = '';
        document.getElementById('user-email').value = '';
        document.getElementById('user-password').value = '';
        document.getElementById('user-password').required = true;
        document.getElementById('password-field').style.display = '';
        openModal('user-modal');
    }

    function openEditUserModal(user) {
        document.getElementById('user-modal-title').textContent = 'Edit User';
        document.getElementById('user-id').value = user.id;
        document.getElementById('user-name').value = user.name;
        document.getElementById('user-email').value = user.email;
        document.getElementById('user-password').value = '';
        document.getElementById('user-password').required = false;
        if (user.roles && user.roles.length > 0) {
            document.getElementById('user-role').value = user.roles[0].id;
        }
        openModal('user-modal');
    }

    async function submitUserForm(e) {
        e.preventDefault();
        const userId = document.getElementById('user-id').value;
        const isEdit = !!userId;
        const url = isEdit ? `/api/users/${userId}` : '/api/users';
        const method = isEdit ? 'PUT' : 'POST';

        const body = {
            name: document.getElementById('user-name').value,
            email: document.getElementById('user-email').value,
            role_id: document.getElementById('user-role').value,
        };
        const pw = document.getElementById('user-password').value;
        if (pw) body.password = pw;

        try {
            const res = await fetch(url, {
                method, headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                closeModal('user-modal');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(data.message || 'Validation error', 'error');
            }
        } catch (err) { showToast(err.message, 'error'); }
    }

    function openResetPasswordModal(id, name) {
        document.getElementById('reset-user-id').value = id;
        document.getElementById('reset-user-name').textContent = `Set new password for "${name}"`;
        document.getElementById('reset-password').value = '';
        openModal('reset-modal');
    }

    async function submitResetPassword() {
        const id = document.getElementById('reset-user-id').value;
        const pw = document.getElementById('reset-password').value;
        if (pw.length < 6) { showToast('Password must be at least 6 characters', 'error'); return; }
        try {
            const res = await fetch(`/api/users/${id}/reset-password`, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ password: pw }),
            });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) closeModal('reset-modal');
        } catch (err) { showToast(err.message, 'error'); }
    }

    async function deleteUser(id, name) {
        if (!confirm(`Delete user "${name}"? This cannot be undone.`)) return;
        try {
            const res = await fetch(`/api/users/${id}`, {
                method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => location.reload(), 800);
        } catch (err) { showToast(err.message, 'error'); }
    }

    async function openRolePermissionsModal(roleId, roleName, isSuperadmin) {
        currentPermRoleId = roleId;
        document.getElementById('perm-modal-title').textContent = `Permissions — ${roleName}`;
        document.getElementById('perm-save-btn').style.display = isSuperadmin ? 'none' : '';

        if (isSuperadmin) {
            document.getElementById('perm-content').innerHTML = '<div class="text-center py-8"><span class="material-symbols-outlined text-4xl text-on-surface-variant mb-2">shield_person</span><p class="text-on-surface-variant">Superadmin has access to all permissions automatically.</p></div>';
            openModal('perm-modal');
            return;
        }

        document.getElementById('perm-content').innerHTML = '<p class="text-on-surface-variant">Loading permissions...</p>';
        openModal('perm-modal');

        try {
            const [permRes, roleRes] = await Promise.all([
                fetch('/api/permissions', { headers: { 'Accept': 'application/json' } }),
                fetch(`/api/roles/${roleId}`, { headers: { 'Accept': 'application/json' } }),
            ]);
            const permData = await permRes.json();
            const roleData = await roleRes.json();
            const assignedIds = roleData.permissions.map(p => p.id);

            let html = '';
            for (const [group, perms] of Object.entries(permData.permissions)) {
                html += `<div class="mb-4"><h4 class="text-label-md font-label-md text-on-surface uppercase tracking-wider mb-2 border-b border-outline-variant pb-1">${group}</h4><div class="grid grid-cols-1 md:grid-cols-2 gap-1">`;
                for (const p of perms) {
                    const checked = assignedIds.includes(p.id) ? 'checked' : '';
                    html += `<label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-surface-container-low cursor-pointer transition-colors">
                        <input type="checkbox" class="perm-checkbox rounded border-outline text-primary focus:ring-primary" value="${p.id}" ${checked}>
                        <span class="text-body-md text-on-surface">${p.display_name}</span>
                    </label>`;
                }
                html += '</div></div>';
            }
            document.getElementById('perm-content').innerHTML = html;
        } catch (err) {
            document.getElementById('perm-content').innerHTML = `<p class="text-red-600">${err.message}</p>`;
        }
    }

    async function saveRolePermissions() {
        const ids = [...document.querySelectorAll('.perm-checkbox:checked')].map(c => parseInt(c.value));
        try {
            const res = await fetch(`/api/roles/${currentPermRoleId}/permissions`, {
                method: 'PUT', headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ permission_ids: ids }),
            });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) { closeModal('perm-modal'); setTimeout(() => location.reload(), 800); }
        } catch (err) { showToast(err.message, 'error'); }
    }

    function openCreateRoleModal() { openModal('role-modal'); }

    async function submitCreateRole(e) {
        e.preventDefault();
        try {
            const res = await fetch('/api/roles', {
                method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    name: document.getElementById('role-name').value,
                    display_name: document.getElementById('role-display-name').value,
                    description: document.getElementById('role-description').value,
                }),
            });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) { closeModal('role-modal'); setTimeout(() => location.reload(), 800); }
        } catch (err) { showToast(err.message, 'error'); }
    }

    async function deleteRole(id, name) {
        if (!confirm(`Delete role "${name}"?`)) return;
        try {
            const res = await fetch(`/api/roles/${id}`, {
                method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => location.reload(), 800);
        } catch (err) { showToast(err.message, 'error'); }
    }
</script>
@endsection
