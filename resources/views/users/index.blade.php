@extends('layouts.app')
@section('title', 'User Management — PL Sync Data Warehouse')
@section('page-title', 'User Management')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex justify-between items-center animate-fade-up">
        <div>
            <h2 class="text-h1 text-surface-800">Users & Roles</h2>
            <p class="text-body-md text-surface-500 mt-1">Manage user accounts, roles, and permissions</p>
        </div>
        <button onclick="openCreateUserModal()" class="flex items-center gap-2 bg-brand-600 text-white px-4 py-2.5 rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97]">
            <span class="material-symbols-outlined text-[16px]">person_add</span> Add User
        </button>
    </div>

    {{-- Users Table --}}
    <div class="bg-white rounded-2xl border border-surface-200/60 overflow-hidden shadow-card animate-fade-up">
        <table class="w-full text-left">
            <thead class="border-b border-surface-100">
                <tr>
                    <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">User</th>
                    <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Email</th>
                    <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Role</th>
                    <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Created</th>
                    <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-surface-100">
                @foreach($users as $u)
                <tr class="hover:bg-surface-50/50 transition-colors" id="user-row-{{ $u->id }}">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold text-sm shadow-sm">
                                {{ strtoupper(substr($u->name, 0, 1)) }}
                            </div>
                            <span class="font-medium text-surface-800 text-body-md">{{ $u->name }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-body-md text-surface-500">{{ $u->email }}</td>
                    <td class="px-5 py-4">
                        @foreach($u->roles as $r)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-label-sm font-semibold
                            {{ $r->name === 'Administrator' ? 'bg-red-50 text-red-700' : ($r->name === 'Operator' ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700') }}">
                            {{ $r->name }}
                        </span>
                        @endforeach
                    </td>
                    <td class="px-5 py-4 text-body-md text-surface-500">{{ $u->created_at->format('d M Y') }}</td>
                    <td class="px-5 py-4 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <button onclick="openEditUserModal({{ json_encode($u) }})" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200" title="Edit">
                                <span class="material-symbols-outlined text-[16px]">edit</span>
                            </button>
                            <button onclick="openResetPasswordModal({{ $u->id }}, '{{ $u->name }}')" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200" title="Reset Password">
                                <span class="material-symbols-outlined text-[16px]">key</span>
                            </button>
                            @if($u->id !== auth()->id())
                            <button onclick="deleteUser({{ $u->id }}, '{{ $u->name }}')" class="p-1.5 rounded-lg hover:bg-red-50 text-surface-400 hover:text-red-600 transition-all duration-200" title="Delete">
                                <span class="material-symbols-outlined text-[16px]">delete</span>
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
    <div class="flex justify-between items-center mt-8 animate-fade-up">
        <h2 class="text-h1 text-surface-800">Roles & Permissions</h2>
        <button onclick="openCreateRoleModal()" class="flex items-center gap-2 bg-surface-100 text-surface-700 px-4 py-2.5 rounded-xl border border-surface-200 hover:bg-surface-200 transition-all duration-200 text-label-md active:scale-[0.97]">
            <span class="material-symbols-outlined text-[16px]">add</span> New Role
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"        @foreach($roles as $role)
        <div class="bg-white rounded-2xl border border-surface-200/60 p-5 hover:shadow-card-hover transition-all duration-300 animate-fade-up shadow-card group">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 {{ $role->name === 'Administrator' ? 'bg-gradient-to-br from-red-50 to-red-100' : 'bg-gradient-to-br from-brand-50 to-brand-100' }} rounded-xl">
                        <span class="material-symbols-outlined {{ $role->name === 'Administrator' ? 'text-red-600' : 'text-brand-600' }} text-[20px]" style="font-variation-settings: 'FILL' 1;">
                            {{ $role->name === 'Administrator' ? 'shield_person' : 'group' }}
                        </span>
                    </div>
                    <h3 class="font-semibold text-surface-800">{{ $role->name }}</h3>
                </div>
                @if($role->name === 'Administrator')
                    <span class="text-label-sm bg-red-50 text-red-700 px-2.5 py-1 rounded-lg font-semibold">Super</span>
                @endif
            </div>
            <p class="text-body-sm text-surface-500 mb-4">{{ $role->description ?? 'No description' }}</p>
            <div class="flex items-center justify-between text-label-sm text-surface-400 pt-3 border-t border-surface-100">
                <span>{{ $role->users_count }} user(s) &bull; {{ $role->name === 'Administrator' ? 'All' : $role->permissions_count }} permissions</span>
                <div class="flex gap-1">
                    <button onclick="openRolePermissionsModal({{ $role->id }}, '{{ $role->name }}', {{ $role->name === 'Administrator' ? 'true' : 'false' }})" class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200" title="Edit Permissions">
                        <span class="material-symbols-outlined text-[16px]">tune</span>
                    </button>
                    @if($role->name !== 'Administrator' && $role->users_count === 0)
                    <button onclick="deleteRole({{ $role->id }}, '{{ $role->name }}')" class="p-1.5 rounded-lg hover:bg-red-50 text-surface-400 hover:text-red-600 transition-all duration-200" title="Delete">
                        <span class="material-symbols-outlined text-[16px]">delete</span>
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
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('user-modal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-md animate-fade-up overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100">
                <h3 id="user-modal-title" class="text-h3 text-surface-800">Add User</h3>
            </div>
            <form id="user-form" onsubmit="submitUserForm(event)">
                <input type="hidden" id="user-id" value="">
                <div class="p-5 space-y-4">
                    <div>
                        <label class="text-label-md text-surface-600 block mb-1.5">Name</label>
                        <input type="text" id="user-name" required class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                    </div>
                    <div>
                        <label class="text-label-md text-surface-600 block mb-1.5">Email</label>
                        <input type="email" id="user-email" required class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                    </div>
                    <div id="password-field">
                        <label class="text-label-md text-surface-600 block mb-1.5">Password</label>
                        <input type="password" id="user-password" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200" minlength="6">
                    </div>
                    <div>
                        <label class="text-label-md text-surface-600 block mb-1.5">Role</label>
                        <select id="user-role" required class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                            @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('user-modal')" class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-100 transition-all duration-200">Cancel</button>
                    <button type="submit" id="user-submit-btn" class="px-4 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97]">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Reset Password --}}
<div id="reset-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('reset-modal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-sm animate-fade-up overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100">
                <h3 class="text-h3 text-surface-800">Reset Password</h3>
                <p id="reset-user-name" class="text-body-sm text-surface-500 mt-1"></p>
            </div>
            <input type="hidden" id="reset-user-id">
            <div class="p-5">
                <label class="text-label-md text-surface-600 block mb-1.5">New Password</label>
                <input type="password" id="reset-password" required minlength="6" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
            </div>
            <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2">
                <button onclick="closeModal('reset-modal')" class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-100 transition-all duration-200">Cancel</button>
                <button onclick="submitResetPassword()" class="px-4 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97]">Reset</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Role Permissions --}}
<div id="perm-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('perm-modal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-2xl max-h-[80vh] flex flex-col animate-fade-up overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100">
                <h3 id="perm-modal-title" class="text-h3 text-surface-800">Role Permissions</h3>
                <p class="text-body-sm text-surface-500 mt-1">Select permissions for this role</p>
            </div>
            <div id="perm-content" class="p-5 overflow-y-auto flex-1">
                <p class="text-surface-500">Loading...</p>
            </div>
            <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2">
                <button onclick="closeModal('perm-modal')" class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-100 transition-all duration-200">Cancel</button>
                <button id="perm-save-btn" onclick="saveRolePermissions()" class="px-4 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97]">Save Permissions</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Create Role --}}
<div id="role-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('role-modal')"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-md animate-fade-up overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100">
                <h3 class="text-h3 text-surface-800">Create Role</h3>
            </div>
            <form onsubmit="submitCreateRole(event)">
                <div class="p-5 space-y-4">
                    <div>
                        <label class="text-label-md text-surface-600 block mb-1.5">Role Name</label>
                        <input type="text" id="role-name" required class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                    </div>
                    <div>
                        <label class="text-label-md text-surface-600 block mb-1.5">Description</label>
                        <input type="text" id="role-description" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('role-modal')" class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-100 transition-all duration-200">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97]">Create</button>
                </div>
            </form>
        </div>
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
        document.getElementById('perm-modal-title').textContent = `Permissions - ${roleName}`;
        document.getElementById('perm-save-btn').style.display = isSuperadmin ? 'none' : '';

        if (isSuperadmin) {
            document.getElementById('perm-content').innerHTML = '<div class="text-center py-8"><span class="material-symbols-outlined text-4xl text-surface-400 mb-2">shield_person</span><p class="text-surface-500">Superadmin has access to all permissions automatically.</p></div>';
            openModal('perm-modal');
            return;
        }

        document.getElementById('perm-content').innerHTML = '<p class="text-surface-500">Loading permissions...</p>';
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
            for (const module of permData.modules) {
                html += `<div class="mb-4"><h4 class="text-label-md text-surface-500 uppercase tracking-wider mb-2 border-b border-surface-100 pb-2 font-semibold">${module.name}</h4>`;
                for (const resource of module.resources) {
                    html += `<div class="mb-2 pl-2"><h5 class="text-body-sm font-semibold text-surface-700 mb-1">${resource.name}</h5><div class="grid grid-cols-1 md:grid-cols-3 gap-1">`;
                    for (const p of resource.permissions) {
                        const checked = assignedIds.includes(p.id) ? 'checked' : '';
                        html += `<label class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-surface-50 cursor-pointer transition-colors">
                            <input type="checkbox" class="perm-checkbox rounded border-surface-300 text-brand-600 focus:ring-brand-500/20" value="${p.id}" ${checked}>
                            <span class="text-body-sm text-surface-600">${p.action.name}</span>
                        </label>`;
                    }
                    html += `</div></div>`;
                }
                html += `</div>`;
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
