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
            <button onclick="openCreateUserModal()"
                class="flex items-center gap-2 bg-brand-600 text-white px-4 py-2.5 rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97]">
                <span class="material-symbols-outlined text-[16px]">person_add</span> Add User
            </button>
        </div>

        {{-- Users Table --}}
        <div class="bg-white rounded-2xl border border-surface-200/60 overflow-hidden shadow-card animate-fade-up">
            <table class="w-full text-left">
                <thead class="border-b border-surface-100">
                    <tr>
                        <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">User
                        </th>
                        <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Email
                        </th>
                        <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Role
                        </th>
                        <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Created
                        </th>
                        <th
                            class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider text-right">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100">
                    @foreach ($users as $u)
                        <tr class="hover:bg-surface-50/50 transition-colors" id="user-row-{{ $u->id }}">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold text-sm shadow-sm">
                                        {{ strtoupper(substr($u->name, 0, 1)) }}
                                    </div>
                                    <span class="font-medium text-surface-800 text-body-md">{{ $u->name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-body-md text-surface-500">{{ $u->email }}</td>
                            <td class="px-5 py-4">
                                @foreach ($u->roles as $r)
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-lg text-label-sm font-semibold
                                                    {{ $r->name === 'Administrator' ? 'bg-red-50 text-red-700' : ($r->name === 'Operator' ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700') }}">
                                        {{ $r->name }}
                                    </span>
                                @endforeach
                            </td>
                            <td class="px-5 py-4 text-body-md text-surface-500">{{ $u->created_at->format('d M Y') }}</td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button onclick="openEditUserModal({{ json_encode($u) }})"
                                        class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200"
                                        title="Edit">
                                        <span class="material-symbols-outlined text-[16px]">edit</span>
                                    </button>
                                    <button onclick="openResetPasswordModal({{ $u->id }}, '{{ $u->name }}')"
                                        class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200"
                                        title="Reset Password">
                                        <span class="material-symbols-outlined text-[16px]">key</span>
                                    </button>
                                    @if ($u->id !== auth()->id())
                                        <button onclick="deleteUser({{ $u->id }}, '{{ $u->name }}')"
                                            class="p-1.5 rounded-lg hover:bg-red-50 text-surface-400 hover:text-red-600 transition-all duration-200"
                                            title="Delete">
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
            <button onclick="openCreateRoleModal()"
                class="flex items-center gap-2 bg-surface-100 text-surface-700 px-4 py-2.5 rounded-xl border border-surface-200 hover:bg-surface-200 transition-all duration-200 text-label-md active:scale-[0.97]">
                <span class="material-symbols-outlined text-[16px]">add</span> New Role
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" @foreach ($roles as $role) <div
            class="bg-white rounded-2xl border border-surface-200/60 p-5 hover:shadow-card-hover transition-all duration-300 animate-fade-up shadow-card group">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2.5">
                    <div
                        class="p-2 {{ $role->name === 'Administrator' ? 'bg-gradient-to-br from-red-50 to-red-100' : 'bg-gradient-to-br from-brand-50 to-brand-100' }} rounded-xl">
                        <span
                            class="material-symbols-outlined {{ $role->name === 'Administrator' ? 'text-red-600' : 'text-brand-600' }} text-[20px]"
                            style="font-variation-settings: 'FILL' 1;">
                            {{ $role->name === 'Administrator' ? 'shield_person' : 'group' }}
                        </span>
                    </div>
                    <h3 class="font-semibold text-surface-800">{{ $role->name }}</h3>
                </div>
                @if ($role->name === 'Administrator')
                    <span class="text-label-sm bg-red-50 text-red-700 px-2.5 py-1 rounded-lg font-semibold">Super</span>
                @endif
            </div>
            <p class="text-body-sm text-surface-500 mb-4">{{ $role->description ?? 'No description' }}</p>
            <div class="flex items-center justify-between text-label-sm text-surface-400 pt-3 border-t border-surface-100">
                <span>{{ $role->users_count }} user(s) &bull;
                    {{ $role->name === 'Administrator' ? 'All' : $role->permissions_count }} permissions</span>
                <div class="flex gap-1">
                    <button
                        onclick="openTablePermissionsModal({{ $role->id }}, '{{ $role->name }}', {{ $role->name === 'Administrator' ? 'true' : 'false' }})"
                        class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200"
                        title="Table Access">
                        <span class="material-symbols-outlined text-[16px]">table_chart</span>
                    </button>
                    <button
                        onclick="openRolePermissionsModal({{ $role->id }}, '{{ $role->name }}', {{ $role->name === 'Administrator' ? 'true' : 'false' }})"
                        class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200"
                        title="Edit Permissions">
                        <span class="material-symbols-outlined text-[16px]">tune</span>
                    </button>
                    @if ($role->name !== 'Administrator' && $role->users_count === 0)
                        <button onclick="deleteRole({{ $role->id }}, '{{ $role->name }}')"
                            class="p-1.5 rounded-lg hover:bg-red-50 text-surface-400 hover:text-red-600 transition-all duration-200"
                            title="Delete">
                            <span class="material-symbols-outlined text-[16px]">delete</span>
                        </button>
                    @endif
                </div>
            </div>
        </div> @endforeach
    </div>
    </div>

    {{-- Modal: Create/Edit User --}}
    <div id="user-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('user-modal')"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div
                class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-md animate-fade-up overflow-hidden">
                <div class="px-5 py-4 border-b border-surface-100">
                    <h3 id="user-modal-title" class="text-h3 text-surface-800">Add User</h3>
                </div>
                <form id="user-form" onsubmit="submitUserForm(event)">
                    <input type="hidden" id="user-id" value="">
                    <div class="p-5 space-y-4">
                        <div>
                            <label class="text-label-md text-surface-600 block mb-1.5">Name</label>
                            <input type="text" id="user-name" required
                                class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                        </div>
                        <div>
                            <label class="text-label-md text-surface-600 block mb-1.5">Email</label>
                            <input type="email" id="user-email" required
                                class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                        </div>
                        <div id="password-field">
                            <label class="text-label-md text-surface-600 block mb-1.5">Password</label>
                            <input type="password" id="user-password"
                                class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200"
                                minlength="6">
                        </div>
                        <div>
                            <label class="text-label-md text-surface-600 block mb-1.5">Roles</label>
                            <div class="space-y-2 max-h-48 overflow-y-auto p-3 bg-surface-50 border border-surface-200 rounded-xl">
                                @foreach ($roles as $role)
                                    <label class="flex items-center gap-2 cursor-pointer hover:bg-surface-100 p-1 rounded-lg transition-colors">
                                        <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" class="user-role-checkbox rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4">
                                        <span class="text-body-sm text-surface-800">{{ $role->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2">
                        <button type="button" onclick="closeModal('user-modal')"
                            class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-100 transition-all duration-200">Cancel</button>
                        <button type="submit" id="user-submit-btn"
                            class="px-4 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97]">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal: Reset Password --}}
    <div id="reset-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('reset-modal')"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div
                class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-sm animate-fade-up overflow-hidden">
                <div class="px-5 py-4 border-b border-surface-100">
                    <h3 class="text-h3 text-surface-800">Reset Password</h3>
                    <p id="reset-user-name" class="text-body-sm text-surface-500 mt-1"></p>
                </div>
                <input type="hidden" id="reset-user-id">
                <div class="p-5">
                    <label class="text-label-md text-surface-600 block mb-1.5">New Password</label>
                    <input type="password" id="reset-password" required minlength="6"
                        class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                </div>
                <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2">
                    <button onclick="closeModal('reset-modal')"
                        class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-100 transition-all duration-200">Cancel</button>
                    <button onclick="submitResetPassword()"
                        class="px-4 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97]">Reset</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Role Permissions --}}
    <div id="perm-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('perm-modal')"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div
                class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-5xl max-h-[80vh] flex flex-col animate-fade-up overflow-hidden">
                <div class="px-5 py-4 border-b border-surface-100">
                    <h3 id="perm-modal-title" class="text-h3 text-surface-800">Role Permissions</h3>
                    <p class="text-body-sm text-surface-500 mt-1">Select permissions for this role</p>
                </div>
                <div id="perm-content" class="p-5 overflow-y-auto flex-1">
                    <p class="text-surface-500">Loading...</p>
                </div>
                <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2">
                    <button onclick="closeModal('perm-modal')"
                        class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-100 transition-all duration-200">Cancel</button>
                    <button id="perm-save-btn" onclick="saveRolePermissions()"
                        class="px-4 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97]">Save
                        Permissions</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Table Permissions --}}
    <div id="table-permissions-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('table-permissions-modal')"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col animate-fade-up overflow-hidden">
                <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center">
                    <div>
                        <h3 id="table-perm-modal-title" class="text-h3 text-surface-800">Table Permissions</h3>
                        <p class="text-body-sm text-surface-500 mt-1">Select table access for this role</p>
                    </div>
                    <div class="w-64">
                        <select id="table-perm-connection" onchange="fetchTablePermissions()" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500">
                            <option value="">Select Connection...</option>
                            @foreach($sqlConnections as $conn)
                                <option value="{{ $conn['key'] }}">{{ $conn['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div id="table-perm-content" class="p-5 overflow-y-auto flex-1">
                    <div class="text-center text-surface-500 py-10">
                        <span class="material-symbols-outlined text-[48px] mb-2 opacity-50">table_chart</span>
                        <p>Select a database connection to load tables.</p>
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2">
                    <button onclick="closeModal('table-permissions-modal')" class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-100 transition-all duration-200">Cancel</button>
                    <button id="table-perm-save-btn" onclick="saveTablePermissions()" class="px-4 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97] disabled:opacity-50 disabled:cursor-not-allowed">Save Permissions</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Create Role --}}
    <div id="role-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeModal('role-modal')"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div
                class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-md animate-fade-up overflow-hidden">
                <div class="px-5 py-4 border-b border-surface-100">
                    <h3 class="text-h3 text-surface-800">Create Role</h3>
                </div>
                <form onsubmit="submitCreateRole(event)">
                    <div class="p-5 space-y-4">
                        <div>
                            <label class="text-label-md text-surface-600 block mb-1.5">Role Name</label>
                            <input type="text" id="role-name" required
                                class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                        </div>
                        <div>
                            <label class="text-label-md text-surface-600 block mb-1.5">Description</label>
                            <input type="text" id="role-description"
                                class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                        </div>
                    </div>
                    <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2">
                        <button type="button" onclick="closeModal('role-modal')"
                            class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-100 transition-all duration-200">Cancel</button>
                        <button type="submit"
                            class="px-4 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97]">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        let currentPermRoleId = null;

        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }

        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        function openCreateUserModal() {
            document.getElementById('user-modal-title').textContent = 'Add User';
            document.getElementById('user-id').value = '';
            document.getElementById('user-name').value = '';
            document.getElementById('user-email').value = '';
            document.getElementById('user-password').value = '';
            document.getElementById('user-password').required = true;
            document.getElementById('password-field').style.display = '';
            document.querySelectorAll('.user-role-checkbox').forEach(cb => cb.checked = false);
            openModal('user-modal');
        }

        function openEditUserModal(user) {
            document.getElementById('user-modal-title').textContent = 'Edit User';
            document.getElementById('user-id').value = user.id;
            document.getElementById('user-name').value = user.name;
            document.getElementById('user-email').value = user.email;
            document.getElementById('user-password').value = '';
            document.getElementById('user-password').required = false;
            document.querySelectorAll('.user-role-checkbox').forEach(cb => cb.checked = false);
            if (user.roles && user.roles.length > 0) {
                user.roles.forEach(r => {
                    const cb = document.querySelector(`.user-role-checkbox[value="${r.id}"]`);
                    if(cb) cb.checked = true;
                });
            }
            openModal('user-modal');
        }

        async function submitUserForm(e) {
            e.preventDefault();
            const userId = document.getElementById('user-id').value;
            const isEdit = !!userId;
            const url = isEdit ? `/api/users/${userId}` : '/api/users';
            const method = isEdit ? 'PUT' : 'POST';

            const role_ids = [...document.querySelectorAll('.user-role-checkbox:checked')].map(cb => cb.value);
            if (role_ids.length === 0) {
                showToast('Please select at least one role', 'error');
                return;
            }

            const body = {
                name: document.getElementById('user-name').value,
                email: document.getElementById('user-email').value,
                role_ids: role_ids,
            };
            const pw = document.getElementById('user-password').value;
            if (pw) body.password = pw;

            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
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
            } catch (err) {
                showToast(err.message, 'error');
            }
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
            if (pw.length < 6) {
                showToast('Password must be at least 6 characters', 'error');
                return;
            }
            try {
                const res = await fetch(`/api/users/${id}/reset-password`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        password: pw
                    }),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) closeModal('reset-modal');
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function deleteUser(id, name) {
            if (!confirm(`Delete user "${name}"? This cannot be undone.`)) return;
            try {
                const res = await fetch(`/api/users/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 800);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        function toggleMatrixAll(checkbox, targetClass) {
            document.querySelectorAll(`.${targetClass}`).forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }

        async function openRolePermissionsModal(roleId, roleName, isSuperadmin) {
            currentPermRoleId = roleId;
            document.getElementById('perm-modal-title').textContent = `Permissions - ${roleName}`;
            document.getElementById('perm-save-btn').style.display = isSuperadmin ? 'none' : '';

            if (isSuperadmin) {
                document.getElementById('perm-content').innerHTML =
                    '<div class="text-center py-8"><span class="material-symbols-outlined text-4xl text-surface-400 mb-2">shield_person</span><p class="text-surface-500">Superadmin has access to all permissions automatically.</p></div>';
                openModal('perm-modal');
                return;
            }

            document.getElementById('perm-content').innerHTML =
                '<p class="text-surface-500">Loading permissions...</p>';
            openModal('perm-modal');

            try {
                const [permRes, roleRes] = await Promise.all([
                    fetch('/api/permissions', {
                        headers: {
                            'Accept': 'application/json'
                        }
                    }),
                    fetch(`/api/roles/${roleId}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    }),
                ]);
                const permData = await permRes.json();
                const roleData = await roleRes.json();
                const assignedIds = roleData.permissions.map(p => p.id);

                const actionsList = ['view', 'create', 'update', 'delete', 'sync', 'export', 'import', 'manage', 'execute', 'access', 'test'];

                let html = `
                    <div class="overflow-x-auto border border-surface-200/80 rounded-xl bg-white shadow-xs">
                        <table class="w-full text-left border-collapse min-w-max">
                            <thead>
                                <tr class="bg-surface-50 border-b border-surface-200/80">
                                    <th class="px-4 py-3 text-xs font-semibold text-surface-700 w-[200px] sm:w-[250px] md:w-[300px] sticky left-0 bg-surface-50 z-10 border-r border-surface-200/80 shadow-[1px_0_0_0_rgba(0,0,0,0.05)]">Menu / Module</th>
                                    <th class="px-3 py-3 text-center border-r border-surface-200/80 bg-surface-50/50">
                                        <div class="flex flex-col items-center gap-1.5">
                                            <span class="text-[10px] font-semibold text-surface-500 uppercase tracking-wide">All</span>
                                            <input type="checkbox" class="rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4 cursor-pointer" onchange="toggleMatrixAll(this, 'row-all')">
                                        </div>
                                    </th>
                    `;

                actionsList.forEach(action => {
                    html += `
                                    <th class="px-2 py-3 text-center">
                                        <div class="flex flex-col items-center gap-1.5">
                                            <span class="text-[10px] font-semibold text-surface-500 uppercase tracking-wide">${action}</span>
                                            <input type="checkbox" class="rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4 cursor-pointer" onchange="toggleMatrixAll(this, 'col-${action}')">
                                        </div>
                                    </th>
                        `;
                });

                html += `
                                    <th class="px-4 py-3 text-xs font-semibold text-surface-700">Other</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100">
                    `;

                let rows = [];

                function flattenMenu(menu, depth = 0) {
                    rows.push({
                        type: 'menu',
                        title: menu.title,
                        icon: menu.icon,
                        depth: depth,
                        permissions: menu.permissions || []
                    });
                    if (menu.children && menu.children.length > 0) {
                        menu.children.forEach(child => flattenMenu(child, depth + 1));
                    }
                }

                permData.menus.forEach(menu => flattenMenu(menu));

                if (permData.otherPermissions && permData.otherPermissions.length > 0) {
                    rows.push({
                        type: 'header',
                        title: 'Lainnya (Sistem)',
                        icon: 'database',
                        depth: 0,
                        permissions: []
                    });

                    const groupedOthers = {};
                    permData.otherPermissions.forEach(p => {
                        const parts = p.permission_key.split('.');
                        let group = parts[0];
                        if (parts.length === 1) group = 'General';
                        else group = group.replace(/_/g, ' ').replace(/([A-Z])/g, ' $1').trim();
                        group = group.charAt(0).toUpperCase() + group.slice(1);

                        if (!groupedOthers[group]) groupedOthers[group] = [];
                        groupedOthers[group].push(p);
                    });

                    for (const [groupName, perms] of Object.entries(groupedOthers)) {
                        rows.push({
                            type: 'other',
                            title: groupName,
                            icon: 'subdirectory_arrow_right',
                            depth: 1,
                            permissions: perms
                        });
                    }
                }

                rows.forEach((row, i) => {
                    const indent = row.depth * 20;
                    const isHeaderOnly = row.type === 'header' || ((row.type === 'menu' || row.type === 'other') && row.permissions.length === 0);
                    const rowId = `r${i}`;
                    const isReadOnly = row.type === 'menu';

                    let rowHtml = `<tr class="hover:bg-surface-50/50 transition-colors group">`;

                    rowHtml += `
                            <td class="px-4 py-2.5 sticky left-0 bg-white group-hover:bg-surface-50/50 z-10 border-r border-surface-200/80 shadow-[1px_0_0_0_rgba(0,0,0,0.05)]">
                                <div class="flex items-center gap-2.5" style="padding-left: ${indent}px">
                                    <span class="material-symbols-outlined text-[18px] text-brand-600/70 shrink-0">${row.depth === 0 ? (row.icon || 'folder') : 'subdirectory_arrow_right'}</span>
                                    <span class="${row.depth === 0 ? 'font-semibold text-surface-800' : 'font-medium text-surface-600'} text-xs truncate" title="${row.title}">${row.title}</span>
                                    ${isReadOnly ? '<span class="text-[9px] px-1.5 py-0.5 bg-yellow-100 text-yellow-700 rounded-full font-bold uppercase tracking-wider">Menu</span>' : ''}
                                </div>
                            </td>
                        `;

                    if (isHeaderOnly) {
                        rowHtml += `<td colspan="${actionsList.length + 2}" class="bg-surface-50/30"></td>`;
                    } else {
                        const permsByAction = {};
                        const otherPerms = [];

                        row.permissions.forEach(p => {
                            let actionCode = null;
                            if (p.action && p.action.code) {
                                actionCode = p.action.code;
                            } else {
                                const parts = p.permission_key.split('.');
                                if (parts.length > 1) {
                                    actionCode = parts[1].toLowerCase();
                                }
                            }

                            if (actionCode && actionsList.includes(actionCode)) {
                                permsByAction[actionCode] = p;
                            } else {
                                otherPerms.push(p);
                            }
                        });

                        rowHtml += `
                                <td class="px-3 py-2.5 text-center border-r border-surface-200/80 bg-surface-50/50">
                                    <input type="checkbox" class="rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4 cursor-pointer" onchange="toggleMatrixAll(this, 'row-all-${rowId}')">
                                </td>
                            `;

                        actionsList.forEach(action => {
                            const p = permsByAction[action];
                            rowHtml += `<td class="px-2 py-2.5 text-center">`;
                            if (p) {
                                const checked = assignedIds.includes(p.id) ? 'checked' : '';
                                rowHtml +=
                                    `<input type="checkbox" name="permissions[]" value="${p.id}" class="perm-checkbox row-all row-all-${rowId} col-${action} rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4 cursor-pointer" ${checked}>`;
                            } else {
                                rowHtml +=
                                    `<span class="text-surface-200 text-[10px] select-none">-</span>`;
                            }
                            rowHtml += `</td>`;
                        });

                        rowHtml += `<td class="px-4 py-2.5">`;
                        if (otherPerms.length > 0) {
                            rowHtml += `<div class="flex flex-wrap gap-1.5">`;
                            otherPerms.forEach(p => {
                                const checked = assignedIds.includes(p.id) ? 'checked' : '';
                                const name = p.action ? p.action.name : p.permission_key.split('.')
                                    .pop();
                                rowHtml += `
                                        <label class="flex items-center gap-1.5 px-2 py-1 rounded bg-surface-100 border border-surface-200 cursor-pointer hover:bg-surface-200 transition-colors">
                                            <input type="checkbox" name="permissions[]" value="${p.id}" class="perm-checkbox row-all row-all-${rowId} rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-3 h-3 cursor-pointer" ${checked}>
                                            <span class="text-[10px] text-surface-700 font-medium capitalize whitespace-nowrap">${name}</span>
                                        </label>
                                    `;
                            });
                            rowHtml += `</div>`;
                        }
                        rowHtml += `</td>`;
                    }

                    rowHtml += `</tr>`;
                    html += rowHtml;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                    `;

                if (permData.apiEndpointPermissions && permData.apiEndpointPermissions.length > 0) {
                    html += `
                        <div class="mt-8 border border-surface-200/80 rounded-2xl bg-white overflow-hidden shadow-sm">
                            <div class="bg-surface-50/80 px-4 py-3 border-b border-surface-200/80 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-brand-600 text-[20px]">api</span>
                                    <h3 class="font-semibold text-surface-800 text-sm">API Endpoint Permissions</h3>
                                </div>
                                <div class="relative w-64">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] text-surface-400">search</span>
                                    <input type="text" onkeyup="filterApiEndpoints(this.value)" placeholder="Search endpoints..." class="w-full pl-9 pr-3 py-1.5 bg-white border border-surface-300 rounded-lg text-xs text-surface-700 focus:ring-1 focus:ring-brand-500 focus:border-brand-500 transition-all">
                                </div>
                            </div>
                            <div class="p-0 overflow-y-auto max-h-96">
                                <table class="w-full text-left text-sm border-collapse">
                                    <thead class="sticky top-0 z-10 bg-surface-50/90 backdrop-blur-sm shadow-[0_1px_0_0_rgba(0,0,0,0.05)]">
                                        <tr>
                                            <th class="px-4 py-2 font-medium text-surface-600 text-xs w-1/3 border-b border-surface-200/80">Endpoint Entity</th>
                                            <th class="px-4 py-2 font-medium text-surface-600 text-xs w-2/3 border-b border-surface-200/80">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;
                    
                    permData.apiEndpointPermissions.forEach((endpoint, i) => {
                        html += `
                            <tr class="api-endpoint-row hover:bg-surface-50/50 transition-colors border-b border-surface-100 last:border-0" data-search="${endpoint.entity.toLowerCase()}">
                                <td class="px-4 py-3 align-top border-r border-surface-100">
                                    <div class="font-medium text-surface-800 text-xs">${endpoint.entity}</div>
                                    <div class="text-[10px] text-surface-500 mt-0.5 font-mono">api.endpoint.${endpoint.entity}.*</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-4">
                        `;
                        
                        endpoint.permissions.forEach(p => {
                            const checked = assignedIds.includes(p.id) ? 'checked' : '';
                            const name = p.action ? p.action.name : p.permission_key.split('.').pop();
                            html += `
                                <label class="flex items-center gap-1.5 cursor-pointer hover:bg-surface-100/80 px-2 py-1 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="permissions[]" value="${p.id}" class="perm-checkbox rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4 cursor-pointer" ${checked}>
                                    <span class="text-xs text-surface-700 font-medium capitalize select-none">${name}</span>
                                </label>
                            `;
                        });
                        
                        html += `
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }

                if (permData.dbConnectionPermissions && permData.dbConnectionPermissions.length > 0) {
                    html += `
                        <div class="mt-8 border border-surface-200/80 rounded-2xl bg-white overflow-hidden shadow-sm">
                            <div class="bg-surface-50/80 px-4 py-3 border-b border-surface-200/80 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-brand-600 text-[20px]">database</span>
                                    <h3 class="font-semibold text-surface-800 text-sm">Database Connection Permissions</h3>
                                </div>
                                <div class="relative w-64">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] text-surface-400">search</span>
                                    <input type="text" onkeyup="filterDbConnections(this.value)" placeholder="Search connections..." class="w-full pl-9 pr-3 py-1.5 bg-white border border-surface-300 rounded-lg text-xs text-surface-700 focus:ring-1 focus:ring-brand-500 focus:border-brand-500 transition-all">
                                </div>
                            </div>
                            <div class="p-0 overflow-y-auto max-h-96">
                                <table class="w-full text-left text-sm border-collapse">
                                    <thead class="sticky top-0 z-10 bg-surface-50/90 backdrop-blur-sm shadow-[0_1px_0_0_rgba(0,0,0,0.05)]">
                                        <tr>
                                            <th class="px-4 py-2 font-medium text-surface-600 text-xs w-1/3 border-b border-surface-200/80">Connection Name</th>
                                            <th class="px-4 py-2 font-medium text-surface-600 text-xs w-2/3 border-b border-surface-200/80">Access</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;
                    
                    permData.dbConnectionPermissions.forEach((conn, i) => {
                        html += `
                            <tr class="db-connection-row hover:bg-surface-50/50 transition-colors border-b border-surface-100 last:border-0" data-search="${conn.entity.toLowerCase()}">
                                <td class="px-4 py-3 align-top border-r border-surface-100">
                                    <div class="font-medium text-surface-800 text-xs">${conn.entity}</div>
                                    <div class="text-[10px] text-surface-500 mt-0.5 font-mono">connection.${conn.key}.access</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-4">
                        `;
                        
                        conn.permissions.forEach(p => {
                            const checked = assignedIds.includes(p.id) ? 'checked' : '';
                            html += `
                                <label class="flex items-center gap-1.5 cursor-pointer hover:bg-surface-100/80 px-2 py-1 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="permissions[]" value="${p.id}" class="perm-checkbox rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4 cursor-pointer" ${checked}>
                                    <span class="text-xs text-surface-700 font-medium capitalize select-none">Allow Connection Access</span>
                                </label>
                            `;
                        });
                        
                        html += `
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }

                document.getElementById('perm-content').innerHTML = html ||
                    '<p class="text-surface-500 text-center py-4">Tidak ada hak akses yang tersedia.</p>';
            } catch (err) {
                document.getElementById('perm-content').innerHTML = `<p class="text-red-600">${err.message}</p>`;
            }
        }

        function filterApiEndpoints(val) {
            const query = val.toLowerCase().trim();
            const rows = document.querySelectorAll('.api-endpoint-row');
            rows.forEach(row => {
                if (row.getAttribute('data-search').includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function filterDbConnections(val) {
            const query = val.toLowerCase().trim();
            const rows = document.querySelectorAll('.db-connection-row');
            rows.forEach(row => {
                if (row.getAttribute('data-search').includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        async function saveRolePermissions() {
            const ids = [...document.querySelectorAll('.perm-checkbox:checked')].map(c => parseInt(c.value));
            try {
                const res = await fetch(`/api/roles/${currentPermRoleId}/permissions`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        permission_ids: ids
                    }),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    closeModal('perm-modal');
                    setTimeout(() => location.reload(), 800);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        function openCreateRoleModal() {
            openModal('role-modal');
        }

        async function submitCreateRole(e) {
            e.preventDefault();
            try {
                const res = await fetch('/api/roles', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        name: document.getElementById('role-name').value,
                        description: document.getElementById('role-description').value,
                    }),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    closeModal('role-modal');
                    setTimeout(() => location.reload(), 800);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function deleteRole(id, name) {
            if (!confirm(`Delete role "${name}"?`)) return;
            try {
                const res = await fetch(`/api/roles/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) setTimeout(() => location.reload(), 800);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        let currentTablePermRoleId = null;

        async function openTablePermissionsModal(roleId, roleName, isSuperAdmin) {
            currentTablePermRoleId = roleId;
            document.getElementById('table-perm-modal-title').textContent = `Table Access: ${roleName}`;
            
            const btn = document.getElementById('table-perm-save-btn');
            const content = document.getElementById('table-perm-content');
            const select = document.getElementById('table-perm-connection');

            if (isSuperAdmin) {
                btn.style.display = 'none';
                select.style.display = 'none';
                content.innerHTML = '<p class="text-surface-500 text-center py-10">Super Administrators have full access to all tables.</p>';
            } else {
                btn.style.display = 'block';
                select.style.display = 'block';
                select.value = ''; // Reset selection
                content.innerHTML = `
                    <div class="text-center text-surface-500 py-10">
                        <span class="material-symbols-outlined text-[48px] mb-2 opacity-50">table_chart</span>
                        <p>Select a database connection to load tables.</p>
                    </div>
                `;
            }
            openModal('table-permissions-modal');
        }

        async function fetchTablePermissions() {
            const connKey = document.getElementById('table-perm-connection').value;
            const content = document.getElementById('table-perm-content');
            const btn = document.getElementById('table-perm-save-btn');

            if (!connKey) {
                content.innerHTML = `
                    <div class="text-center text-surface-500 py-10">
                        <span class="material-symbols-outlined text-[48px] mb-2 opacity-50">table_chart</span>
                        <p>Select a database connection to load tables.</p>
                    </div>
                `;
                btn.disabled = true;
                return;
            }

            content.innerHTML = '<p class="text-surface-500 text-center py-10">Loading tables...</p>';
            btn.disabled = true;

            try {
                const res = await fetch(`/api/roles/${currentTablePermRoleId}/table-permissions?connection=${connKey}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                if (!data.success) throw new Error(data.message);

                if (!data.tables || data.tables.length === 0) {
                    content.innerHTML = '<p class="text-surface-500 text-center py-10">No tables found for this connection.</p>';
                    return;
                }

                let html = `
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                `;

                data.tables.forEach(table => {
                    const isChecked = data.allowed_tables.includes(table) ? 'checked' : '';
                    html += `
                        <label class="flex items-center gap-2 p-3 border border-surface-200 rounded-xl hover:bg-surface-50 cursor-pointer transition-colors">
                            <input type="checkbox" name="table_permissions[]" value="${table}" class="table-perm-checkbox rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4 cursor-pointer" ${isChecked}>
                            <span class="text-sm text-surface-700 font-medium truncate" title="${table}">${table}</span>
                        </label>
                    `;
                });

                html += '</div>';
                content.innerHTML = html;
                btn.disabled = false;
            } catch (err) {
                content.innerHTML = `<p class="text-red-600 text-center py-10">${err.message}</p>`;
            }
        }

        async function saveTablePermissions() {
            const connKey = document.getElementById('table-perm-connection').value;
            if (!connKey) return;

            const selectedTables = [...document.querySelectorAll('.table-perm-checkbox:checked')].map(c => c.value);
            const btn = document.getElementById('table-perm-save-btn');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Saving...';

            try {
                const res = await fetch(`/api/roles/${currentTablePermRoleId}/table-permissions`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        connection: connKey,
                        tables: selectedTables
                    }),
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    closeModal('table-permissions-modal');
                }
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }
    </script>
@endsection