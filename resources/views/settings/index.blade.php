@extends('layouts.app')

@section('title', 'Settings — Enterprise Sync Engine')
@section('page-title', 'Settings')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-lg">
        {{-- Acumatica Connection --}}
        <form id="acumatica-form" class="bg-surface-container-lowest border border-outline-variant rounded flex flex-col fade-in" onsubmit="saveAcumatica(event)">
            <div class="p-md border-b border-outline-variant bg-surface-container-low/50 flex justify-between items-center">
                <h2 class="font-h2 text-h2 text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-secondary">cloud</span>
                    Acumatica ERP
                </h2>
                <div class="flex items-center gap-sm">
                    <button type="button" onclick="testAcumaticaConnection()" id="acm-test-btn" class="inline-flex items-center gap-1 px-3 py-1.5 rounded border border-outline-variant font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low transition-colors active:scale-[0.97]">
                        <span class="material-symbols-outlined text-sm">lan</span> Test
                    </button>
                    <button type="submit" id="acm-save-btn" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-[0.97]">
                        <span class="material-symbols-outlined text-sm">save</span> Save
                    </button>
                </div>
            </div>
            <div class="p-md space-y-md">
                <div>
                    <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">Instance URL</label>
                    <input type="url" id="acm-url" value="{{ $acumaticaUrl }}" required class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                </div>
                <div>
                    <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">Tenant</label>
                    <input type="text" id="acm-tenant" value="{{ $acumaticaTenant }}" class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                </div>
                <div>
                    <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">Username</label>
                    <input type="text" id="acm-username" value="{{ $acumaticaUser }}" required class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                </div>
                <div>
                    <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">Password</label>
                    <input type="password" id="acm-password" value="{{ $acumaticaPassword }}" required class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                </div>
                <div class="flex gap-sm">
                    <div class="flex-1">
                        <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">OData Version</label>
                        <select id="acm-odata-path" required class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                            <option value="odatav4" {{ $odataPath === 'odatav4' ? 'selected' : '' }}>ODatav4</option>
                            <option value="odata" {{ $odataPath === 'odata' ? 'selected' : '' }}>OData</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">OData Endpoint Preview</label>
                    <div class="bg-surface-container-low rounded px-3 py-2 font-code text-code text-on-surface-variant border border-outline-variant break-all" id="acm-odata-preview">
                        {{ $acumaticaUrl }}/{{ $odataPath }}/
                    </div>
                </div>
            </div>
        </form>

        {{-- Database Connection --}}
        <form id="sql-form" class="bg-surface-container-lowest border border-outline-variant rounded flex flex-col fade-in" onsubmit="saveDatabase(event)">
            <div class="p-md border-b border-outline-variant bg-surface-container-low/50 flex justify-between items-center">
                <h2 class="font-h2 text-h2 text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-secondary">database</span>
                    Database
                </h2>
                <div class="flex items-center gap-sm">
                    <button type="button" onclick="testDbConnection()" id="sql-test-btn" class="inline-flex items-center gap-1 px-3 py-1.5 rounded border border-outline-variant font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low transition-colors active:scale-[0.97]">
                        <span class="material-symbols-outlined text-sm">lan</span> Test
                    </button>
                    <button type="submit" id="sql-save-btn" class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-[0.97]">
                        <span class="material-symbols-outlined text-sm">save</span> Save
                    </button>
                </div>
            </div>
            <div class="p-md space-y-md">
                <div>
                    <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">Connection Type</label>
                    <select id="db-connection" onchange="handleConnectionTypeChange()" required class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                        <option value="sqlsrv" {{ $dbConnection === 'sqlsrv' ? 'selected' : '' }}>SQL Server</option>
                        <option value="mysql" {{ $dbConnection === 'mysql' ? 'selected' : '' }}>MySQL</option>
                    </select>
                </div>
                <div>
                    <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">Host</label>
                    <input type="text" id="db-host" value="{{ $dbHost }}" required class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                </div>
                <div>
                    <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">Port</label>
                    <input type="number" id="db-port" value="{{ $dbPort }}" required class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                </div>
                <div>
                    <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">Database</label>
                    <input type="text" id="db-name" value="{{ $dbName }}" required class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                </div>
                <div>
                    <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">Username</label>
                    <input type="text" id="db-username" value="{{ $dbUsername }}" required class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                </div>
                <div>
                    <label class="font-label-md text-label-md text-on-surface-variant block mb-xs">Password</label>
                    <input type="password" id="db-password" value="{{ $dbPassword }}" class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                </div>
            </div>
        </form>
    </div>

    {{-- Entity Configuration --}}
    <div class="bg-surface-container-lowest border border-outline-variant rounded flex flex-col fade-in mt-lg">
        <div class="p-md border-b border-outline-variant bg-surface-container-low/50 flex justify-between items-center">
            <div>
                <h2 class="font-h2 text-h2 text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-secondary">list</span>
                    Entity Configuration
                </h2>
                <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">
                    Managed dynamically — add, edit, or remove sync entities
                </p>
            </div>
            <button onclick="openEntityModal()"
                class="inline-flex items-center gap-1 px-4 py-2 rounded bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-[0.97]">
                <span class="material-symbols-outlined text-sm">add</span> Add Entity
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="entity-table">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Key</th>
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Type</th>
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Icon</th>
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">OData Entity</th>
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">SQL Table</th>
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Description</th>
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="font-body-md text-body-md text-on-surface divide-y divide-outline-variant/50">
                    @foreach ($entities as $key => $e)
                        <tr class="hover:bg-surface-container-low/50 transition-colors" id="entity-row-{{ $key }}">
                            <td class="py-3 px-md font-medium font-code text-code">{{ $key }}</td>
                            <td class="py-3 px-md">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded font-label-sm text-label-sm
                            {{ $e['type'] === 'master' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-purple-50 text-purple-700 border border-purple-200' }}">
                                    {{ ucfirst($e['type']) }}
                                </span>
                            </td>
                            <td class="py-3 px-md">
                                <span class="material-symbols-outlined text-secondary text-sm">{{ $e['icon'] ?? 'database' }}</span>
                            </td>
                            <td class="py-3 px-md font-code text-code text-on-surface-variant">{{ $e['odata_entity'] }}</td>
                            <td class="py-3 px-md font-code text-code">{{ $e['table'] }}</td>
                            <td class="py-3 px-md text-on-surface-variant">{{ $e['description'] }}</td>
                            <td class="py-3 px-md text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button onclick="editEntity('{{ $key }}')"
                                        class="p-1.5 rounded hover:bg-surface-container-low text-on-surface-variant hover:text-on-surface transition-colors"
                                        title="Edit">
                                        <span class="material-symbols-outlined text-sm">edit</span>
                                    </button>
                                    <button onclick="deleteEntity('{{ $key }}')"
                                        class="p-1.5 rounded hover:bg-red-50 text-on-surface-variant hover:text-red-700 transition-colors"
                                        title="Delete">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div id="entity-pagination" class="p-md border-t border-outline-variant flex justify-between items-center"></div>
    </div>

    {{-- CLI Commands --}}
    <div class="bg-surface-container-lowest border border-outline-variant rounded flex flex-col fade-in mt-lg">
        <div class="p-md border-b border-outline-variant bg-surface-container-low/50">
            <h2 class="font-h2 text-h2 text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">terminal</span>
                Artisan Commands
            </h2>
        </div>
        <div class="p-md space-y-md">
            <div>
                <p class="font-label-md text-label-md text-on-surface mb-xs">Setup database</p>
                <code class="block bg-inverse-surface text-inverse-on-surface rounded px-4 py-2 font-code text-code">php
                    artisan acumatica:setup</code>
            </div>
            <div>
                <p class="font-label-md text-label-md text-on-surface mb-xs">Sync all entities</p>
                <code class="block bg-inverse-surface text-inverse-on-surface rounded px-4 py-2 font-code text-code">php
                    artisan acumatica:sync</code>
            </div>
            <div>
                <p class="font-label-md text-label-md text-on-surface mb-xs">Sync specific entity</p>
                <code class="block bg-inverse-surface text-inverse-on-surface rounded px-4 py-2 font-code text-code">php
                    artisan acumatica:sync Customer</code>
            </div>
            <div>
                <p class="font-label-md text-label-md text-on-surface mb-xs">Sync by type</p>
                <code class="block bg-inverse-surface text-inverse-on-surface rounded px-4 py-2 font-code text-code">php
                    artisan acumatica:sync --type=master</code>
            </div>
            <div>
                <p class="font-label-md text-label-md text-on-surface mb-xs">List entities</p>
                <code class="block bg-inverse-surface text-inverse-on-surface rounded px-4 py-2 font-code text-code">php
                    artisan acumatica:sync --list</code>
            </div>
        </div>
    </div>

    {{-- Entity Modal (Add / Edit) --}}
    <div id="entity-modal" class="fixed inset-0 z-50 hidden">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeEntityModal()"></div>
        {{-- Modal Content --}}
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-2xl w-full max-w-lg fade-in overflow-hidden">
                {{-- Header --}}
                <div class="p-md border-b border-outline-variant bg-surface-container-low/50 flex justify-between items-center">
                    <h3 class="font-h2 text-h2 text-on-surface flex items-center gap-2" id="modal-title">
                        <span class="material-symbols-outlined text-secondary">add_circle</span>
                        Add Entity
                    </h3>
                    <button onclick="closeEntityModal()"
                        class="p-1 rounded hover:bg-surface-container-low text-on-surface-variant hover:text-on-surface transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                {{-- Form --}}
                <form id="entity-form" onsubmit="submitEntityForm(event)">
                    <input type="hidden" id="form-mode" value="create">
                    <input type="hidden" id="form-original-key" value="">
                    <div class="p-md space-y-md">
                        {{-- Entity Key --}}
                        <div>
                            <label for="form-key" class="font-label-md text-label-md text-on-surface-variant block mb-xs">
                                Entity Key <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="form-key" name="key" required
                                placeholder="e.g. CashAccount"
                                pattern="[A-Za-z][A-Za-z0-9]*"
                                class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                            <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">PascalCase, no spaces (e.g. CashAccount, InventoryItem)</p>
                        </div>
                        {{-- Type --}}
                        <div>
                            <label for="form-type" class="font-label-md text-label-md text-on-surface-variant block mb-xs">
                                Type <span class="text-red-500">*</span>
                            </label>
                            <select id="form-type" name="type" required
                                class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-body-md text-body-md text-on-surface focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                                <option value="master">Master</option>
                                <option value="transaction">Transaction</option>
                            </select>
                        </div>
                        {{-- OData Entity --}}
                        <div>
                            <label for="form-odata" class="font-label-md text-label-md text-on-surface-variant block mb-xs">
                                OData Entity <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="form-odata" name="odata_entity" required
                                placeholder="e.g. PX_Objects_CA_CashAccount"
                                class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                            <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">Acumatica DAC entity name</p>
                        </div>
                        {{-- SQL Table --}}
                        <div>
                            <label for="form-table" class="font-label-md text-label-md text-on-surface-variant block mb-xs">
                                SQL Table <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="form-table" name="table" required
                                placeholder="e.g. acm_cash_accounts"
                                pattern="[a-z][a-z0-9_]*"
                                class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                            <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">snake_case, e.g. acm_cash_accounts</p>
                        </div>
                        {{-- Icon --}}
                        <div>
                            <label for="form-icon" class="font-label-md text-label-md text-on-surface-variant block mb-xs">
                                Icon
                            </label>
                            <div class="flex items-center gap-sm">
                                <input type="text" id="form-icon" name="icon"
                                    placeholder="e.g. account_balance"
                                    class="flex-1 bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-code text-code text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                    oninput="updateIconPreview()">
                                <div class="p-2 bg-surface-container-low rounded border border-outline-variant">
                                    <span class="material-symbols-outlined text-secondary" id="icon-preview">database</span>
                                </div>
                            </div>
                            <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">
                                <a href="https://fonts.google.com/icons" target="_blank" class="text-primary hover:underline">Browse Material Symbols →</a>
                            </p>
                        </div>
                        {{-- Description --}}
                        <div>
                            <label for="form-desc" class="font-label-md text-label-md text-on-surface-variant block mb-xs">
                                Description <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="form-desc" name="description" required
                                placeholder="e.g. Master Cash Account"
                                class="w-full bg-surface-container-low border border-outline-variant rounded px-3 py-2 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant/40 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                        </div>
                    </div>
                    {{-- Footer --}}
                    <div class="p-md border-t border-outline-variant bg-surface-container-low/30 flex justify-end gap-sm">
                        <button type="button" onclick="closeEntityModal()"
                            class="px-4 py-2 rounded font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low transition-colors">
                            Cancel
                        </button>
                        <button type="submit" id="form-submit-btn"
                            class="inline-flex items-center gap-1 px-4 py-2 rounded bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-[0.97]">
                            <span class="material-symbols-outlined text-sm">save</span>
                            <span id="form-submit-text">Create Entity</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Delete Confirm Modal --}}
    <div id="delete-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-surface-container-lowest rounded-xl border border-outline-variant shadow-2xl w-full max-w-sm fade-in overflow-hidden">
                <div class="p-md border-b border-outline-variant bg-surface-container-low/50">
                    <h3 class="font-h2 text-h2 text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-error">warning</span>
                        Confirm Delete
                    </h3>
                </div>
                <div class="p-md">
                    <p class="font-body-md text-body-md text-on-surface">
                        Are you sure you want to delete entity <strong id="delete-entity-name"></strong>?
                    </p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant mt-sm">
                        This will remove the entity configuration. The SQL table will NOT be deleted.
                    </p>
                </div>
                <div class="p-md border-t border-outline-variant bg-surface-container-low/30 flex justify-end gap-sm">
                    <button onclick="closeDeleteModal()"
                        class="px-4 py-2 rounded font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-low transition-colors">
                        Cancel
                    </button>
                    <button onclick="confirmDelete()" id="delete-confirm-btn"
                        class="inline-flex items-center gap-1 px-4 py-2 rounded bg-error text-on-error font-label-md text-label-md hover:bg-red-700 transition-colors active:scale-[0.97]">
                        <span class="material-symbols-outlined text-sm">delete</span>
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    // ── Entity data cache ──────────────────────────────────────────────────
    const entityData = @json($entities);
    let deleteTargetKey = null;

    // ── OData URL preview ─────────────────────────────────────────────────
    const acmUrlInput = document.getElementById('acm-url');
    const acmODataPathInput = document.getElementById('acm-odata-path');

    function updateODataPreview() {
        const url = acmUrlInput.value.replace(/\/+$/, '');
        const path = acmODataPathInput.value;
        document.getElementById('acm-odata-preview').textContent = url + '/' + path + '/';
    }

    if (acmUrlInput) {
        acmUrlInput.addEventListener('input', updateODataPreview);
    }
    if (acmODataPathInput) {
        acmODataPathInput.addEventListener('change', updateODataPreview);
    }

    // ── Save Acumatica Settings ───────────────────────────────────────────
    async function saveAcumatica(e) {
        e.preventDefault();
        const btn = document.getElementById('acm-save-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm spin">sync</span> Saving…';
        try {
            const res = await fetch('/api/settings/acumatica', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({
                    url: document.getElementById('acm-url').value.trim(),
                    tenant: document.getElementById('acm-tenant').value.trim(),
                    username: document.getElementById('acm-username').value.trim(),
                    password: document.getElementById('acm-password').value,
                    odata_path: document.getElementById('acm-odata-path').value,
                    verify_ssl: false,
                }),
            });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
        } catch (err) { showToast('Error: ' + err.message, 'error'); }
        finally { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined text-sm">save</span> Save'; }
    }

    // ── Save SQL Server Settings ──────────────────────────────────────────
    async function saveDatabase(e) {
        e.preventDefault();
        const btn = document.getElementById('sql-save-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm spin">sync</span> Saving…';
        try {
            const res = await fetch('/api/settings/database', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({
                    connection: document.getElementById('db-connection').value,
                    host: document.getElementById('db-host').value.trim(),
                    port: parseInt(document.getElementById('db-port').value),
                    database: document.getElementById('db-name').value.trim(),
                    username: document.getElementById('db-username').value.trim(),
                    password: document.getElementById('db-password').value,
                }),
            });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
        } catch (err) { showToast('Error: ' + err.message, 'error'); }
        finally { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined text-sm">save</span> Save'; }
    }

    // ── Test Acumatica Connection ─────────────────────────────────────────
    async function testAcumaticaConnection() {
        const btn = document.getElementById('acm-test-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm spin">sync</span> Testing…';
        try {
            const res = await fetch('/api/settings/test-connection', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({
                    type: 'acumatica',
                    url: document.getElementById('acm-url').value.trim(),
                    tenant: document.getElementById('acm-tenant').value.trim(),
                    username: document.getElementById('acm-username').value.trim(),
                    password: document.getElementById('acm-password').value,
                    odata_path: document.getElementById('acm-odata-path').value,
                }),
            });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
        } catch (err) { showToast('Error: ' + err.message, 'error'); }
        finally { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined text-sm">lan</span> Test'; }
    }

    // ── Test DB Connection ────────────────────────────────────────────────
    async function testDbConnection() {
        const btn = document.getElementById('sql-test-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-sm spin">sync</span> Testing…';
        try {
            const res = await fetch('/api/settings/test-connection', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({
                    type: 'database',
                    connection: document.getElementById('db-connection').value,
                    host: document.getElementById('db-host').value.trim(),
                    port: parseInt(document.getElementById('db-port').value),
                    database: document.getElementById('db-name').value.trim(),
                    username: document.getElementById('db-username').value.trim(),
                    password: document.getElementById('db-password').value,
                }),
            });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
        } catch (err) { showToast('Error: ' + err.message, 'error'); }
        finally { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined text-sm">lan</span> Test'; }
    }

    // ── Entity Pagination (client-side, 25 per page) ──────────────────────
    const ENTITY_PAGE_SIZE = 25;
    let entityCurrentPage = 1;
    function paginateEntities() {
        const rows = document.querySelectorAll('#entity-table tbody tr');
        const total = rows.length;
        const totalPages = Math.max(1, Math.ceil(total / ENTITY_PAGE_SIZE));
        if (entityCurrentPage > totalPages) entityCurrentPage = totalPages;
        const start = (entityCurrentPage - 1) * ENTITY_PAGE_SIZE;
        const end = start + ENTITY_PAGE_SIZE;
        rows.forEach((row, i) => { row.style.display = (i >= start && i < end) ? '' : 'none'; });
        // Update pagination controls
        const container = document.getElementById('entity-pagination');
        if (!container) return;
        if (totalPages <= 1) { container.innerHTML = ''; return; }
        let html = `<span class="font-label-sm text-label-sm text-on-surface-variant">Page ${entityCurrentPage} of ${totalPages} (${total} entities)</span><div class="flex items-center gap-1">`;
        html += `<button onclick="entityGoPage(${entityCurrentPage - 1})" ${entityCurrentPage <= 1 ? 'disabled' : ''} class="p-1 rounded hover:bg-surface-container-low text-on-surface-variant disabled:opacity-30"><span class="material-symbols-outlined text-sm">chevron_left</span></button>`;
        for (let p = 1; p <= totalPages; p++) {
            html += `<button onclick="entityGoPage(${p})" class="px-2 py-0.5 rounded font-label-sm text-label-sm ${p === entityCurrentPage ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container-low'}">${p}</button>`;
        }
        html += `<button onclick="entityGoPage(${entityCurrentPage + 1})" ${entityCurrentPage >= totalPages ? 'disabled' : ''} class="p-1 rounded hover:bg-surface-container-low text-on-surface-variant disabled:opacity-30"><span class="material-symbols-outlined text-sm">chevron_right</span></button>`;
        html += '</div>';
        container.innerHTML = html;
    }
    function entityGoPage(p) { entityCurrentPage = p; paginateEntities(); }
    document.addEventListener('DOMContentLoaded', paginateEntities);

    // ── Modal: Open for Create ─────────────────────────────────────────────
    function openEntityModal(editKey = null) {
        const modal = document.getElementById('entity-modal');
        const title = document.getElementById('modal-title');
        const submitText = document.getElementById('form-submit-text');
        const form = document.getElementById('entity-form');

        form.reset();
        document.getElementById('form-mode').value = 'create';
        document.getElementById('form-original-key').value = '';

        if (editKey && entityData[editKey]) {
            const e = entityData[editKey];
            document.getElementById('form-mode').value = 'edit';
            document.getElementById('form-original-key').value = editKey;
            document.getElementById('form-key').value = editKey;
            document.getElementById('form-type').value = e.type;
            document.getElementById('form-odata').value = e.odata_entity;
            document.getElementById('form-table').value = e.table;
            document.getElementById('form-icon').value = e.icon || '';
            document.getElementById('form-desc').value = e.description;
            title.innerHTML = '<span class="material-symbols-outlined text-secondary">edit</span> Edit Entity';
            submitText.textContent = 'Save Changes';
        } else {
            title.innerHTML = '<span class="material-symbols-outlined text-secondary">add_circle</span> Add Entity';
            submitText.textContent = 'Create Entity';
        }
        updateIconPreview();
        modal.classList.remove('hidden');
        document.getElementById('form-key').focus();
    }

    function closeEntityModal() { document.getElementById('entity-modal').classList.add('hidden'); }
    function updateIconPreview() { document.getElementById('icon-preview').textContent = document.getElementById('form-icon').value.trim() || 'database'; }
    function editEntity(key) { openEntityModal(key); }

    // ── Submit form (Create / Update) ─────────────────────────────────────
    async function submitEntityForm(event) {
        event.preventDefault();
        const mode = document.getElementById('form-mode').value;
        const originalKey = document.getElementById('form-original-key').value;
        const submitBtn = document.getElementById('form-submit-btn');
        const submitText = document.getElementById('form-submit-text');
        const payload = {
            key: document.getElementById('form-key').value.trim(),
            odata_entity: document.getElementById('form-odata').value.trim(),
            table: document.getElementById('form-table').value.trim(),
            type: document.getElementById('form-type').value,
            icon: document.getElementById('form-icon').value.trim() || 'database',
            description: document.getElementById('form-desc').value.trim(),
        };
        submitBtn.disabled = true;
        submitText.textContent = mode === 'edit' ? 'Saving…' : 'Creating…';
        try {
            const url = mode === 'edit' ? `/api/entities/${originalKey}` : '/api/entities';
            const method = mode === 'edit' ? 'PUT' : 'POST';
            const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (res.ok && data.success) { showToast(data.message, 'success'); closeEntityModal(); setTimeout(() => location.reload(), 800); }
            else { showToast(data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Operation failed'), 'error'); }
        } catch (e) { showToast('Error: ' + e.message, 'error'); }
        finally { submitBtn.disabled = false; submitText.textContent = mode === 'edit' ? 'Save Changes' : 'Create Entity'; }
    }

    // ── Delete ─────────────────────────────────────────────────────────────
    function deleteEntity(key) { deleteTargetKey = key; document.getElementById('delete-entity-name').textContent = key; document.getElementById('delete-modal').classList.remove('hidden'); }
    function closeDeleteModal() { document.getElementById('delete-modal').classList.add('hidden'); deleteTargetKey = null; }
    async function confirmDelete() {
        if (!deleteTargetKey) return;
        const btn = document.getElementById('delete-confirm-btn');
        btn.disabled = true; btn.innerHTML = '<span class="material-symbols-outlined text-sm spin">sync</span> Deleting…';
        try {
            const res = await fetch(`/api/entities/${deleteTargetKey}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } });
            const data = await res.json();
            if (res.ok && data.success) { showToast(data.message, 'success'); closeDeleteModal(); setTimeout(() => location.reload(), 800); }
            else { showToast(data.message || 'Delete failed', 'error'); }
        } catch (e) { showToast('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined text-sm">delete</span> Delete'; }
    }

    function handleConnectionTypeChange() {
        const type = document.getElementById('db-connection').value;
        const portInput = document.getElementById('db-port');
        if (type === 'mysql') {
            if (portInput.value == '1433' || portInput.value === '') portInput.value = '3306';
        } else if (type === 'sqlsrv') {
            if (portInput.value == '3306' || portInput.value === '') portInput.value = '1433';
        }
    }

    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeEntityModal(); closeDeleteModal(); } });
</script>
@endsection

