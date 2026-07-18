@extends('layouts.app')

@section('title', 'Settings — PL Sync Data Warehouse')
@section('page-title', 'Settings')

@section('content')
    <div class="grid grid-cols-1 gap-5">
        {{-- Connections Configuration --}}
        <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden animate-fade-up">
            <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center bg-surface-50/50">
                <div>
                    <h2 class="text-h3 text-surface-800 flex items-center gap-2.5">
                        <div class="p-2 bg-gradient-to-br from-indigo-50 to-blue-100 rounded-lg">
                            <span class="material-symbols-outlined text-indigo-600 text-[18px]">lan</span>
                        </div>
                        Connection Management
                    </h2>
                    <p class="text-label-sm text-surface-400 mt-1 ml-[42px]">
                        Define data sources and destinations (OData, REST, MySQL, SQL Server)
                    </p>
                </div>
                <button onclick="openConnectionModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 active:scale-[0.97] shadow-sm">
                    <span class="material-symbols-outlined text-[14px]">add</span> Add Connection
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="connection-table">
                    <thead>
                        <tr class="border-b border-surface-100">
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Key</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Name</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Type</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Status</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-body-md text-surface-700 divide-y divide-surface-100">
                        @foreach ($connections as $key => $c)
                            <tr class="hover:bg-surface-50/50 transition-colors">
                                <td class="py-3 px-5 font-medium font-mono text-body-sm text-surface-800">{{ $key }}</td>
                                <td class="py-3 px-5 font-medium text-surface-800">{{ $c['name'] }}</td>
                                <td class="py-3 px-5">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-label-sm font-semibold
                                        {{ in_array($c['type'], ['mysql', 'sqlsrv']) ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700' }}">
                                        {{ strtoupper($c['type']) }}
                                    </span>
                                </td>
                                <td class="py-3 px-5">
                                    <span id="status-{{ $key }}" class="text-label-sm text-surface-400 font-medium flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-surface-300"></span> Unchecked
                                    </span>
                                </td>
                                <td class="py-3 px-5 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button onclick="testConnection('{{ $key }}')" id="btn-conn-{{ $key }}"
                                            class="px-2.5 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-all duration-200 text-label-sm font-semibold flex items-center gap-1"
                                            title="Test Connection">
                                            <span class="material-symbols-outlined text-[14px]">link</span> Connect
                                        </button>
                                        <button onclick="editConnection('{{ $key }}')"
                                            class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200"
                                            title="Edit">
                                            <span class="material-symbols-outlined text-[16px]">edit</span>
                                        </button>
                                        <button onclick="deleteConnection('{{ $key }}')"
                                            class="p-1.5 rounded-lg hover:bg-red-50 text-surface-400 hover:text-red-600 transition-all duration-200"
                                            title="Delete">
                                            <span class="material-symbols-outlined text-[16px]">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Entity Configuration --}}
    <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden animate-fade-up mt-5">
        <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center bg-surface-50/50">
            <div>
                <h2 class="text-h3 text-surface-800 flex items-center gap-2.5">
                    <div class="p-2 bg-gradient-to-br from-amber-50 to-orange-100 rounded-lg">
                        <span class="material-symbols-outlined text-amber-600 text-[18px]">list</span>
                    </div>
                    Entity Configuration
                </h2>
                <p class="text-label-sm text-surface-400 mt-1 ml-[42px]">
                    Map data sources to destinations and define entity schemas
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button id="btn-batch-delete" onclick="batchDeleteEntities()" class="hidden items-center gap-1.5 px-4 py-2 rounded-xl bg-red-50 text-red-600 text-label-md hover:bg-red-100 transition-all duration-200 shadow-sm border border-red-100">
                    <span class="material-symbols-outlined text-[14px]">delete</span> Delete Selected (<span id="batch-delete-count">0</span>)
                </button>
                <button onclick="openEntityModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 active:scale-[0.97] shadow-sm">
                    <span class="material-symbols-outlined text-[14px]">add</span> Add Entity
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="entity-table">
                <thead class="bg-surface-50 border-b border-surface-200">
                    <tr>
                        <th class="py-3 px-5 text-left w-12">
                            <input type="checkbox" id="selectAllEntities" onchange="toggleAllEntities(this)" class="w-4 h-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500 cursor-pointer">
                        </th>
                        <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Entity Key</th>
                        <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Source</th>
                        <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Destination</th>
                        <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Type</th>
                        <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-body-md text-surface-700 divide-y divide-surface-100">
                    @foreach ($entities as $key => $e)
                        <tr class="hover:bg-surface-50/50 transition-colors" id="entity-row-{{ $key }}">
                            <td class="py-3 px-5 w-12">
                                <input type="checkbox" value="{{ $key }}" onchange="updateBatchDeleteBtn()" class="entity-checkbox w-4 h-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500 cursor-pointer">
                            </td>
                            <td class="py-3 px-5 font-medium font-mono text-body-sm text-surface-800">{{ $key }}
                                <div class="text-label-sm text-surface-400 font-sans mt-0.5">{{ $e['description'] }}</div>
                            </td>
                            <td class="py-3 px-5">
                                <div class="font-mono text-body-sm text-surface-600 truncate max-w-[200px]" title="{{ $e['odata_entity'] }}">{{ $e['odata_entity'] }}</div>
                                <div class="text-label-sm text-surface-400 mt-0.5"><span class="material-symbols-outlined text-[12px] align-middle mr-0.5">input</span> {{ $e['source_connection'] ?? 'acumatica_default' }}</div>
                            </td>
                            <td class="py-3 px-5">
                                <div class="font-mono text-body-sm text-surface-800">{{ $e['table'] }}</div>
                                <div class="text-label-sm text-surface-400 mt-0.5"><span class="material-symbols-outlined text-[12px] align-middle mr-0.5">output</span> {{ $e['destination_connection'] ?? 'database_default' }}</div>
                            </td>
                            <td class="py-3 px-5">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-label-sm font-semibold
                                    {{ $e['type'] === 'master' ? 'bg-sky-50 text-sky-700' : 'bg-violet-50 text-violet-700' }}">
                                    {{ ucfirst($e['type']) }}
                                </span>
                            </td>
                            <td class="py-3 px-5 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button onclick="editEntity('{{ $key }}')"
                                        class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200"
                                        title="Edit">
                                        <span class="material-symbols-outlined text-[16px]">edit</span>
                                    </button>
                                    <button onclick="deleteEntity('{{ $key }}')"
                                        class="p-1.5 rounded-lg hover:bg-red-50 text-surface-400 hover:text-red-600 transition-all duration-200"
                                        title="Delete">
                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div id="entity-pagination" class="px-5 py-3 border-t border-surface-100 flex justify-between items-center bg-surface-50/30"></div>
    </div>

    {{-- Connection Modal (Add / Edit) --}}
    <div id="connection-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeConnectionModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-2xl animate-fade-up overflow-hidden max-h-[90vh] flex flex-col">
                <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center shrink-0">
                    <h3 class="text-h3 text-surface-800 flex items-center gap-2" id="conn-modal-title">
                        <span class="material-symbols-outlined text-brand-500 text-[20px]">add_circle</span>
                        Add Connection
                    </h3>
                    <button onclick="closeConnectionModal()"
                        class="p-1.5 rounded-xl hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
                <form id="connection-form" onsubmit="submitConnectionForm(event)" class="overflow-y-auto p-5 space-y-4">
                    <input type="hidden" id="conn-form-mode" value="create">
                    <input type="hidden" id="conn-form-original-key" value="">
                    
                    <div>
                        <label for="conn-key" class="text-label-md text-surface-600 block mb-1.5">Connection Key <span class="text-red-500">*</span></label>
                        <input type="text" id="conn-key" name="key" required placeholder="e.g. acm_prod" pattern="[A-Za-z0-9_]*" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 placeholder:text-surface-400 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                    </div>
                    <div>
                        <label for="conn-name" class="text-label-md text-surface-600 block mb-1.5">Display Name <span class="text-red-500">*</span></label>
                        <input type="text" id="conn-name" name="name" required placeholder="e.g. Acumatica Production" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 placeholder:text-surface-400 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                    </div>
                    <div>
                        <label for="conn-type" class="text-label-md text-surface-600 block mb-1.5">Type <span class="text-red-500">*</span></label>
                        <select id="conn-type" name="type" required onchange="toggleConnectionFields()" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                            <option value="odata">OData / Acumatica ERP</option>
                            <option value="rest_api">REST API</option>
                            <option value="mysql">MySQL</option>
                            <option value="sqlsrv">SQL Server</option>
                        </select>
                    </div>

                    <!-- DB Fields -->
                    <div id="conn-fields-db" class="hidden space-y-4 pt-2 border-t border-surface-100">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-label-md text-surface-600 block mb-1.5">Host</label>
                                <input type="text" id="conn-db-host" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                            </div>
                            <div>
                                <label class="text-label-md text-surface-600 block mb-1.5">Port</label>
                                <input type="number" id="conn-db-port" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                            </div>
                        </div>
                        <div>
                            <label class="text-label-md text-surface-600 block mb-1.5 flex justify-between items-center">
                                Database
                                <button type="button" onclick="fetchServerDatabases()" tabindex="-1" class="text-[11px] text-brand-600 hover:text-brand-700 flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[13px]">refresh</span> Load List
                                </button>
                            </label>
                            <div class="relative">
                                <input type="text" id="conn-db-name" placeholder="Search or enter DB name" autocomplete="off"
                                    onfocus="document.getElementById('conn-db-dropdown').classList.remove('hidden')"
                                    onblur="setTimeout(() => { document.getElementById('conn-db-dropdown').classList.add('hidden'); }, 200)"
                                    oninput="filterList(this, 'conn-db-list')"
                                    class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                                
                                <div id="conn-db-dropdown" class="absolute z-20 w-full mt-1 bg-white border border-surface-200 rounded-xl shadow-lg max-h-48 overflow-y-auto hidden">
                                    <ul id="conn-db-list" class="py-1"></ul>
                                </div>
                                
                                <div id="conn-db-loading" class="absolute right-3 top-2.5 hidden bg-surface-50 pl-2">
                                    <span class="material-symbols-outlined text-[20px] text-surface-400 spin">sync</span>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-label-md text-surface-600 block mb-1.5">Username</label>
                                <input type="text" id="conn-db-user" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                            </div>
                            <div>
                                <label class="text-label-md text-surface-600 block mb-1.5">Password</label>
                                <input type="password" id="conn-db-pass" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                            </div>
                        </div>
                    </div>

                    <!-- OData Fields -->
                    <div id="conn-fields-odata" class="space-y-4 pt-2 border-t border-surface-100">
                        <div>
                            <label class="text-label-md text-surface-600 block mb-1.5">URL</label>
                            <input type="url" id="conn-odata-url" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                        </div>
                        <div>
                            <label class="text-label-md text-surface-600 block mb-1.5">Tenant</label>
                            <input type="text" id="conn-odata-tenant" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="text-label-md text-surface-600 block mb-1.5">Username</label>
                                <input type="text" id="conn-odata-user" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                            </div>
                            <div>
                                <label class="text-label-md text-surface-600 block mb-1.5">Password</label>
                                <input type="password" id="conn-odata-pass" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                            </div>
                        </div>
                        <div>
                            <label class="text-label-md text-surface-600 block mb-1.5">OData Path</label>
                            <select id="conn-odata-path" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                                <option value="odatav4">odatav4</option>
                                <option value="odata">odata</option>
                            </select>
                        </div>
                    </div>

                    <!-- REST API Fields -->
                    <div id="conn-fields-rest" class="hidden space-y-4 pt-2 border-t border-surface-100">
                        <div>
                            <label class="text-label-md text-surface-600 block mb-1.5">Base URL</label>
                            <input type="url" id="conn-rest-url" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                        </div>
                        <div>
                            <label class="text-label-md text-surface-600 block mb-1.5">Auth Header / Bearer Token</label>
                            <input type="text" id="conn-rest-token" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                        </div>
                    </div>
                </form>
                <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2 shrink-0 bg-surface-50/50">
                    <button type="button" onclick="closeConnectionModal()"
                        class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-200 transition-all duration-200 bg-surface-100 border border-surface-200">
                        Cancel
                    </button>
                    <button type="button" onclick="testConnectionFromForm()" id="conn-test-btn"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-label-md hover:bg-blue-100 transition-all duration-200 shadow-sm">
                        <span class="material-symbols-outlined text-[14px]">science</span>
                        <span>Test</span>
                    </button>
                    <button type="button" onclick="submitConnectionForm(event)" id="conn-submit-btn"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 active:scale-[0.97] shadow-sm">
                        <span class="material-symbols-outlined text-[14px]">save</span>
                        <span id="conn-submit-text">Create Connection</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Entity Modal (Add / Edit) --}}
    <div id="entity-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeEntityModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-2xl animate-fade-up overflow-hidden max-h-[90vh] flex flex-col">
                <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center shrink-0">
                    <h3 class="text-h3 text-surface-800 flex items-center gap-2" id="modal-title">
                        <span class="material-symbols-outlined text-brand-500 text-[20px]">add_circle</span>
                        Add Entity
                    </h3>
                    <button onclick="closeEntityModal()"
                        class="p-1.5 rounded-xl hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
                <form id="entity-form" onsubmit="submitEntityForm(event)" class="overflow-y-auto p-5 space-y-4">
                    <input type="hidden" id="form-mode" value="create">
                    <input type="hidden" id="form-original-key" value="">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="form-key" class="text-label-md text-surface-600 block mb-1.5">Entity Key <span class="text-red-500">*</span></label>
                            <input type="text" id="form-key" name="key" required placeholder="e.g. CashAccount" pattern="[A-Za-z][A-Za-z0-9]*" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 placeholder:text-surface-400 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                            <p class="text-[11px] text-surface-400 mt-1">PascalCase (e.g. CashAccount)</p>
                        </div>
                        <div>
                            <label for="form-type" class="text-label-md text-surface-600 block mb-1.5">Type <span class="text-red-500">*</span></label>
                            <select id="form-type" name="type" required class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                                <option value="master">Master Data</option>
                                <option value="transaction">Transaction Data</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-surface-50/50 rounded-xl border border-surface-200/60 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="form-source-conn" class="text-label-md text-surface-600 block mb-1.5">Source Connection <span class="text-red-500">*</span></label>
                                <select id="form-source-conn" required onchange="fetchSourceObjects()" class="w-full bg-white border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                                    @foreach($connections as $k => $c)
                                        <option value="{{ $k }}">{{ $c['name'] }} ({{ strtoupper($c['type']) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-label-md text-surface-600 block mb-1.5 flex justify-between items-center">
                                    Source Object / Entity <span class="text-red-500">*</span>
                                    <button type="button" onclick="fetchSourceObjects(true)" tabindex="-1" class="text-[11px] text-brand-600 hover:text-brand-700 flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[13px]">refresh</span> Reload
                                    </button>
                                </label>
                                <div class="relative">
                                    <input type="text" id="form-odata" required placeholder="Search or type object name" autocomplete="off"
                                        onfocus="document.getElementById('form-odata-dropdown').classList.remove('hidden')"
                                        onblur="setTimeout(() => { document.getElementById('form-odata-dropdown').classList.add('hidden'); }, 200)"
                                        oninput="filterList(this, 'form-odata-list')"
                                        class="w-full bg-white border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                                    
                                    <div id="form-odata-dropdown" class="absolute z-20 w-full mt-1 bg-white border border-surface-200 rounded-xl shadow-lg max-h-48 overflow-y-auto hidden">
                                        <ul id="form-odata-list" class="py-1"></ul>
                                    </div>

                                    <div id="form-odata-loading" class="absolute right-3 top-2.5 hidden bg-white pl-2">
                                        <span class="material-symbols-outlined text-[20px] text-surface-400 spin">sync</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 justify-center py-2">
                            <span class="material-symbols-outlined text-surface-300">arrow_downward</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="form-dest-conn" class="text-label-md text-surface-600 block mb-1.5">Destination Connection <span class="text-red-500">*</span></label>
                                <select id="form-dest-conn" required class="w-full bg-white border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                                    @foreach($connections as $k => $c)
                                        @if(in_array($c['type'], ['mysql', 'sqlsrv', 'sqlite']))
                                            <option value="{{ $k }}">{{ $c['name'] }} ({{ strtoupper($c['type']) }})</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="form-table" class="text-label-md text-surface-600 block mb-1.5">Target SQL Table <span class="text-red-500">*</span></label>
                                <input type="text" id="form-table" required placeholder="e.g. acm_cash_accounts" pattern="[a-z][a-z0-9_]*" class="w-full bg-white border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="form-icon" class="text-label-md text-surface-600 block mb-1.5">Menu Icon</label>
                            <input type="text" id="form-icon" placeholder="e.g. account_balance" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                        </div>
                        <div>
                            <label for="form-desc" class="text-label-md text-surface-600 block mb-1.5">Display Description <span class="text-red-500">*</span></label>
                            <input type="text" id="form-desc" required placeholder="e.g. Master Cash Account" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                        </div>
                    </div>
                </form>
                <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2 shrink-0 bg-surface-50/50">
                    <button type="button" onclick="closeEntityModal()"
                        class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-200 transition-all duration-200 bg-surface-100 border border-surface-200">
                        Cancel
                    </button>
                    <button type="button" onclick="submitEntityForm(event)" id="form-submit-btn"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 active:scale-[0.97] shadow-sm">
                        <span class="material-symbols-outlined text-[14px]">save</span>
                        <span id="form-submit-text">Save Entity</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Confirm Modal --}}
    <div id="delete-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-sm animate-fade-up overflow-hidden">
                <div class="px-5 py-4 border-b border-surface-100">
                    <h3 class="text-h3 text-surface-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-500 text-[20px]">warning</span>
                        Confirm Delete
                    </h3>
                </div>
                <div class="p-5">
                    <input type="hidden" id="delete-type" value="">
                    <p class="text-body-md text-surface-700">
                        Are you sure you want to delete <strong id="delete-name" class="text-surface-900"></strong>?
                    </p>
                    <p class="text-label-sm text-surface-400 mt-2">
                        This action cannot be undone.
                    </p>
                </div>
                <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2">
                    <button onclick="closeDeleteModal()"
                        class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-100 transition-all duration-200">
                        Cancel
                    </button>
                    <button onclick="confirmDelete()" id="delete-confirm-btn"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-red-600 text-white text-label-md hover:bg-red-700 transition-all duration-200 active:scale-[0.97] shadow-sm">
                        <span class="material-symbols-outlined text-[14px]">delete</span>
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    const entityData = @json($entities);
    const connectionData = @json($connections);
    let deleteTargetKey = null;

    // ── Entity Pagination ──────────────────────────────────────────────────
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
        const container = document.getElementById('entity-pagination');
        if (!container) return;
        if (totalPages <= 1) { container.innerHTML = ''; return; }
        let html = `<span class="text-label-sm text-surface-500">Page ${entityCurrentPage} of ${totalPages} (${total} entities)</span><div class="flex items-center gap-1">`;
        html += `<button onclick="entityGoPage(${entityCurrentPage - 1})" ${entityCurrentPage <= 1 ? 'disabled' : ''} class="p-1 rounded-lg hover:bg-surface-100 text-surface-400 disabled:opacity-30"><span class="material-symbols-outlined text-[14px]">chevron_left</span></button>`;
        for (let p = 1; p <= totalPages; p++) {
            html += `<button onclick="entityGoPage(${p})" class="px-2.5 py-0.5 rounded-lg text-label-sm ${p === entityCurrentPage ? 'bg-brand-600 text-white' : 'text-surface-500 hover:bg-surface-100'}">${p}</button>`;
        }
        html += `<button onclick="entityGoPage(${entityCurrentPage + 1})" ${entityCurrentPage >= totalPages ? 'disabled' : ''} class="p-1 rounded-lg hover:bg-surface-100 text-surface-400 disabled:opacity-30"><span class="material-symbols-outlined text-[14px]">chevron_right</span></button>`;
        html += '</div>';
        container.innerHTML = html;
        if(typeof updateBatchDeleteBtn === 'function') updateBatchDeleteBtn();
    }
    function entityGoPage(p) { entityCurrentPage = p; paginateEntities(); }
    document.addEventListener('DOMContentLoaded', paginateEntities);

    // Aliases for HTML buttons
    function editEntity(key) { openEntityModal(key); }
    function editConnection(key) { openConnectionModal(key); }

    // ── Entity Modal ───────────────────────────────────────────────────────
    function openEntityModal(editKey = null) {
        const modal = document.getElementById('entity-modal');
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
            if(e.source_connection) {
                document.getElementById('form-source-conn').value = e.source_connection;
            }
            if(e.destination_connection) {
                document.getElementById('form-dest-conn').value = e.destination_connection;
            }
            document.getElementById('modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">edit</span> Edit Entity';
            document.getElementById('form-submit-text').textContent = 'Save Changes';
            fetchSourceObjects();
        } else {
            document.getElementById('modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">add_circle</span> Add Entity';
            document.getElementById('form-submit-text').textContent = 'Create Entity';
            fetchSourceObjects();
        }
        modal.classList.remove('hidden');
    }

    function filterList(input, listId) {
        const filter = input.value.toLowerCase();
        const lis = document.getElementById(listId).getElementsByTagName('li');
        for (let i = 0; i < lis.length; i++) {
            const txtValue = lis[i].textContent || lis[i].innerText;
            if (txtValue.toLowerCase().indexOf(filter) > -1) {
                lis[i].style.display = "";
            } else {
                lis[i].style.display = "none";
            }
        }
    }

    let cachedSourceObjects = {};
    try {
        const stored = localStorage.getItem('cached_source_objects');
        if (stored) cachedSourceObjects = JSON.parse(stored);
    } catch(e) {}

    function renderSourceObjects(objects) {
        requestAnimationFrame(() => {
            const list = document.getElementById('form-odata-list');
            
            if (objects.length === 0) {
                list.innerHTML = '<li class="px-3.5 py-2 text-sm text-surface-400 italic">No objects found</li>';
            } else {
                let html = '';
                objects.forEach(obj => {
                    html += `<li onclick="document.getElementById('form-odata').value = '${obj}'" class="px-3.5 py-2 hover:bg-surface-100 cursor-pointer text-sm font-mono text-surface-800 transition-colors">${obj}</li>`;
                });
                list.innerHTML = html;
            }
        });
    }

    async function fetchSourceObjects(force = false) {
        const connKey = document.getElementById('form-source-conn').value;
        if (!connKey) return;
        
        if (!force && cachedSourceObjects[connKey]) {
            renderSourceObjects(cachedSourceObjects[connKey]);
            return;
        }
        
        const loading = document.getElementById('form-odata-loading');
        loading.classList.remove('hidden');
        
        try {
            const res = await fetch(`/api/connections/${connKey}/objects`);
            const data = await res.json();
            if (res.ok && data.success && data.objects) {
                // Sort objects alphabetically
                data.objects.sort((a, b) => a.localeCompare(b));
                cachedSourceObjects[connKey] = data.objects;
                try {
                    localStorage.setItem('cached_source_objects', JSON.stringify(cachedSourceObjects));
                } catch(e) {}
                
                renderSourceObjects(data.objects);

                if (document.activeElement === document.getElementById('form-odata')) {
                    document.getElementById('form-odata-dropdown').classList.remove('hidden');
                }
            }
        } catch (e) {
            console.error('Failed to fetch source objects', e);
        } finally {
            loading.classList.add('hidden');
        }
    }

    function closeEntityModal() { document.getElementById('entity-modal').classList.add('hidden'); }

    async function submitEntityForm(event) {
        if(event) event.preventDefault();
        if(!document.getElementById('entity-form').checkValidity()) {
            document.getElementById('entity-form').reportValidity();
            return;
        }

        const mode = document.getElementById('form-mode').value;
        const originalKey = document.getElementById('form-original-key').value;
        const btn = document.getElementById('form-submit-btn');
        const payload = {
            key: document.getElementById('form-key').value.trim(),
            odata_entity: document.getElementById('form-odata').value.trim(),
            table: document.getElementById('form-table').value.trim(),
            type: document.getElementById('form-type').value,
            icon: document.getElementById('form-icon').value.trim() || 'database',
            description: document.getElementById('form-desc').value.trim(),
            source_connection: document.getElementById('form-source-conn').value,
            destination_connection: document.getElementById('form-dest-conn').value,
        };
        btn.disabled = true;
        try {
            const url = mode === 'edit' ? `/api/entities/${originalKey}` : '/api/entities';
            const method = mode === 'edit' ? 'PUT' : 'POST';
            const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (res.ok && data.success) { showToast(data.message, 'success'); closeEntityModal(); setTimeout(() => location.reload(), 800); }
            else { showToast(data.message || 'Operation failed', 'error'); }
        } catch (e) { showToast('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; }
    }

    // ── Connection Modal ───────────────────────────────────────────────────
    function toggleConnectionFields() {
        const type = document.getElementById('conn-type').value;
        document.getElementById('conn-fields-db').classList.add('hidden');
        document.getElementById('conn-fields-odata').classList.add('hidden');
        document.getElementById('conn-fields-rest').classList.add('hidden');
        
        if (type === 'mysql' || type === 'sqlsrv') {
            document.getElementById('conn-fields-db').classList.remove('hidden');
        } else if (type === 'odata') {
            document.getElementById('conn-fields-odata').classList.remove('hidden');
        } else if (type === 'rest_api') {
            document.getElementById('conn-fields-rest').classList.remove('hidden');
        }
    }

    function openConnectionModal(editKey = null) {
        const modal = document.getElementById('connection-modal');
        document.getElementById('connection-form').reset();
        document.getElementById('conn-form-mode').value = 'create';
        document.getElementById('conn-form-original-key').value = '';

        if (editKey && connectionData[editKey]) {
            const c = connectionData[editKey];
            document.getElementById('conn-form-mode').value = 'edit';
            document.getElementById('conn-form-original-key').value = editKey;
            document.getElementById('conn-key').value = editKey;
            document.getElementById('conn-name').value = c.name;
            document.getElementById('conn-type').value = c.type;
            
            toggleConnectionFields();
            if (c.type === 'mysql' || c.type === 'sqlsrv') {
                document.getElementById('conn-db-host').value = c.credentials.host || '';
                document.getElementById('conn-db-port').value = c.credentials.port || '';
                document.getElementById('conn-db-name').value = c.credentials.database || '';
                document.getElementById('conn-db-user').value = c.credentials.username || '';
                document.getElementById('conn-db-pass').value = c.credentials.password || '';
            } else if (c.type === 'odata') {
                document.getElementById('conn-odata-url').value = c.credentials.url || '';
                document.getElementById('conn-odata-tenant').value = c.credentials.tenant || '';
                document.getElementById('conn-odata-user').value = c.credentials.username || '';
                document.getElementById('conn-odata-pass').value = c.credentials.password || '';
                document.getElementById('conn-odata-path').value = c.credentials.odata_path || 'odatav4';
            } else if (c.type === 'rest_api') {
                document.getElementById('conn-rest-url').value = c.credentials.url || '';
                document.getElementById('conn-rest-token').value = c.credentials.token || '';
            }

            document.getElementById('conn-modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">edit</span> Edit Connection';
            document.getElementById('conn-submit-text').textContent = 'Save Changes';
        } else {
            toggleConnectionFields();
            document.getElementById('conn-modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">add_circle</span> Add Connection';
            document.getElementById('conn-submit-text').textContent = 'Create Connection';
        }
        modal.classList.remove('hidden');
    }

    function closeConnectionModal() { document.getElementById('connection-modal').classList.add('hidden'); }

    async function testConnectionFromForm() {
        if(!document.getElementById('connection-form').checkValidity()) {
            document.getElementById('connection-form').reportValidity();
            return;
        }

        const type = document.getElementById('conn-type').value;
        let credentials = {};

        if (type === 'mysql' || type === 'sqlsrv') {
            credentials = {
                host: document.getElementById('conn-db-host').value,
                port: document.getElementById('conn-db-port').value,
                database: document.getElementById('conn-db-name').value,
                username: document.getElementById('conn-db-user').value,
                password: document.getElementById('conn-db-pass').value,
            };
        } else if (type === 'odata') {
            credentials = {
                url: document.getElementById('conn-odata-url').value,
                tenant: document.getElementById('conn-odata-tenant').value,
                username: document.getElementById('conn-odata-user').value,
                password: document.getElementById('conn-odata-pass').value,
                odata_path: document.getElementById('conn-odata-path').value,
            };
        } else if (type === 'rest_api') {
            credentials = {
                url: document.getElementById('conn-rest-url').value,
                token: document.getElementById('conn-rest-token').value,
            };
        }

        const btn = document.getElementById('conn-test-btn');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Testing...';

        try {
            const res = await fetch('/api/connections/test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ type, credentials })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Connection failed', 'error');
            }
        } catch (e) {
            showToast('Error: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    async function submitConnectionForm(event) {
        if(event) event.preventDefault();
        if(!document.getElementById('connection-form').checkValidity()) {
            document.getElementById('connection-form').reportValidity();
            return;
        }

        const mode = document.getElementById('conn-form-mode').value;
        const originalKey = document.getElementById('conn-form-original-key').value;
        const type = document.getElementById('conn-type').value;
        let credentials = {};

        if (type === 'mysql' || type === 'sqlsrv') {
            credentials = {
                host: document.getElementById('conn-db-host').value,
                port: document.getElementById('conn-db-port').value,
                database: document.getElementById('conn-db-name').value,
                username: document.getElementById('conn-db-user').value,
                password: document.getElementById('conn-db-pass').value,
            };
        } else if (type === 'odata') {
            credentials = {
                url: document.getElementById('conn-odata-url').value,
                tenant: document.getElementById('conn-odata-tenant').value,
                username: document.getElementById('conn-odata-user').value,
                password: document.getElementById('conn-odata-pass').value,
                odata_path: document.getElementById('conn-odata-path').value,
            };
        } else if (type === 'rest_api') {
            credentials = {
                url: document.getElementById('conn-rest-url').value,
                token: document.getElementById('conn-rest-token').value,
            };
        }

        const payload = {
            key: document.getElementById('conn-key').value.trim(),
            name: document.getElementById('conn-name').value.trim(),
            type: type,
            credentials: credentials
        };

        const btn = document.getElementById('conn-submit-btn');
        btn.disabled = true;
        try {
            const url = mode === 'edit' ? `/api/connections/${originalKey}` : '/api/connections';
            const method = mode === 'edit' ? 'PUT' : 'POST';
            const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (res.ok && data.success) { showToast(data.message, 'success'); closeConnectionModal(); setTimeout(() => location.reload(), 800); }
            else { showToast(data.message || 'Operation failed', 'error'); }
        } catch (e) { showToast('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; }
    }

    let lastDbFetchKey = '';
    async function fetchServerDatabases() {
        const type = document.getElementById('conn-type').value;
        if (type !== 'mysql' && type !== 'sqlsrv') return;

        const host = document.getElementById('conn-db-host').value;
        const port = document.getElementById('conn-db-port').value;
        const user = document.getElementById('conn-db-user').value;
        const pass = document.getElementById('conn-db-pass').value;

        if (!host || !user) {
            showToast('Please fill Host and Username first', 'error');
            return;
        }

        const currentKey = `${type}-${host}-${port}-${user}-${pass}`;
        if (lastDbFetchKey === currentKey) {
            // Already fetched, just show dropdown if there are items
            document.getElementById('conn-db-dropdown').classList.remove('hidden');
            return; 
        }
        
        const loading = document.getElementById('conn-db-loading');
        loading.classList.remove('hidden');
        
        try {
            const res = await fetch('/api/connections/databases', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ type, credentials: { host, port, username: user, password: pass } })
            });
            const data = await res.json();
            if (res.ok && data.success && data.databases) {
                // Sort databases alphabetically
                data.databases.sort((a, b) => a.localeCompare(b));
                
                const list = document.getElementById('conn-db-list');
                
                if (data.databases.length === 0) {
                    list.innerHTML = '<li class="px-3.5 py-2 text-sm text-surface-400 italic">No databases found</li>';
                } else {
                    let html = '';
                    data.databases.forEach(db => {
                        html += `<li onclick="document.getElementById('conn-db-name').value = '${db}'" class="px-3.5 py-2 hover:bg-surface-100 cursor-pointer text-sm font-mono text-surface-800 transition-colors">${db}</li>`;
                    });
                    list.innerHTML = html;
                }
                
                if (document.activeElement === document.getElementById('conn-db-name')) {
                    document.getElementById('conn-db-dropdown').classList.remove('hidden');
                }
                
                lastDbFetchKey = currentKey;
            } else {
                showToast(data.message || 'Failed to fetch databases', 'error');
            }
        } catch (e) {
            console.error('Failed to fetch databases', e);
            showToast('Error fetching databases', 'error');
        } finally {
            loading.classList.add('hidden');
        }
    }

    async function testConnection(key, silent = false) {
        if (!connectionData[key]) return;
        const c = connectionData[key];
        const payload = { type: c.type, credentials: c.credentials, key: key };

        const statusEl = document.getElementById('status-' + key);
        const btn = document.getElementById('btn-conn-' + key);
        
        btn.disabled = true;
        statusEl.innerHTML = '<span class="material-symbols-outlined text-[12px] spin align-middle">sync</span> Ping...';
        statusEl.className = "text-label-sm text-amber-500 font-medium flex items-center gap-1";

        try {
            const res = await fetch('/api/connections/test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (res.ok && data.success) {
                statusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block shadow-[0_0_8px_rgba(16,185,129,0.4)]"></span> ' + (data.ping_ms ? data.ping_ms + 'ms' : 'Connected');
                statusEl.className = "text-label-sm text-emerald-600 font-medium flex items-center gap-1.5";
                
                btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link_off</span> Disconnect';
                btn.className = "px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-600 transition-all duration-200 text-label-sm font-semibold flex items-center gap-1";
                btn.setAttribute('onclick', `disconnectConnection('${key}')`);
                
                localStorage.setItem('conn_status_' + key, 'Connected');
                if(!silent) showToast(data.message, 'success');
            } else {
                localStorage.removeItem('conn_status_' + key);
                throw new Error(data.message || 'Connection failed');
            }
        } catch (e) {
            statusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block shadow-[0_0_8px_rgba(239,68,68,0.4)]"></span> Failed';
            statusEl.className = "text-label-sm text-red-600 font-medium flex items-center gap-1.5";
            
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link</span> Connect';
            btn.className = "px-2.5 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-all duration-200 text-label-sm font-semibold flex items-center gap-1";
            btn.setAttribute('onclick', `testConnection('${key}')`);
            if(!silent) showToast('Error: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
        }
    }

    function disconnectConnection(key) {
        localStorage.removeItem('conn_status_' + key);
        
        const statusEl = document.getElementById('status-' + key);
        const btn = document.getElementById('btn-conn-' + key);
        
        statusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-surface-300"></span> Disconnected';
        statusEl.className = "text-label-sm text-surface-400 font-medium flex items-center gap-1.5";
        
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link</span> Connect';
        btn.className = "px-2.5 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-all duration-200 text-label-sm font-semibold flex items-center gap-1";
        btn.setAttribute('onclick', `testConnection('${key}')`);
    }

    // ── Delete ─────────────────────────────────────────────────────────────
    function deleteEntity(key) { 
        document.getElementById('delete-type').value = 'entity';
        deleteTargetKey = key; 
        document.getElementById('delete-name').textContent = key; 
        document.getElementById('delete-modal').classList.remove('hidden'); 
    }
    function deleteConnection(key) { 
        document.getElementById('delete-type').value = 'connection';
        deleteTargetKey = key; 
        document.getElementById('delete-name').textContent = connectionData[key] ? connectionData[key].name : key; 
        document.getElementById('delete-modal').classList.remove('hidden'); 
    }
    
    // ── Batch Delete Entities ──────────────────────────────────────────────
    function toggleAllEntities(source) {
        const checkboxes = document.querySelectorAll('.entity-checkbox');
        checkboxes.forEach(cb => {
            if (cb.closest('tr').style.display !== 'none') {
                cb.checked = source.checked;
            }
        });
        updateBatchDeleteBtn();
    }

    function updateBatchDeleteBtn() {
        const selectedCount = document.querySelectorAll('.entity-checkbox:checked').length;
        const btn = document.getElementById('btn-batch-delete');
        const countSpan = document.getElementById('batch-delete-count');
        
        if (selectedCount > 0) {
            countSpan.textContent = selectedCount;
            btn.classList.remove('hidden');
            btn.classList.add('inline-flex');
        } else {
            btn.classList.add('hidden');
            btn.classList.remove('inline-flex');
        }
        
        const selectAll = document.getElementById('selectAllEntities');
        if(selectAll) {
            const visibleCheckboxes = Array.from(document.querySelectorAll('.entity-checkbox')).filter(cb => cb.closest('tr').style.display !== 'none');
            if (visibleCheckboxes.length > 0) {
                const allChecked = visibleCheckboxes.every(cb => cb.checked);
                const someChecked = visibleCheckboxes.some(cb => cb.checked);
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
        }
    }

    async function batchDeleteEntities() {
        const selected = Array.from(document.querySelectorAll('.entity-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) return;
        
        if(!confirm(`Are you sure you want to delete ${selected.length} entities?`)) return;
        
        const btn = document.getElementById('btn-batch-delete');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Deleting...';
        
        let successCount = 0;
        for (const key of selected) {
            try {
                const res = await fetch(`/api/entities/${key}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                });
                if(res.ok) successCount++;
            } catch(e) {
                console.error('Failed to delete ' + key);
            }
        }
        
        if (successCount > 0) {
            showToast(`Successfully deleted ${successCount} entities`, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast('Failed to delete entities', 'error');
            btn.disabled = false;
            btn.innerHTML = `<span class="material-symbols-outlined text-[14px]">delete</span> Delete Selected (<span id="batch-delete-count">${selected.length}</span>)`;
            updateBatchDeleteBtn();
        }
    }

    function closeDeleteModal() { document.getElementById('delete-modal').classList.add('hidden'); deleteTargetKey = null; }
    
    async function confirmDelete() {
        if (!deleteTargetKey) return;
        const type = document.getElementById('delete-type').value;
        const btn = document.getElementById('delete-confirm-btn');
        btn.disabled = true; 
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Deleting…';
        try {
            const url = type === 'entity' ? `/api/entities/${deleteTargetKey}` : `/api/connections/${deleteTargetKey}`;
            const res = await fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } });
            const data = await res.json();
            if (res.ok && data.success) { showToast(data.message, 'success'); closeDeleteModal(); setTimeout(() => location.reload(), 800); }
            else { showToast(data.message || 'Delete failed', 'error'); }
        } catch (e) { showToast('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">delete</span> Delete'; }
    }

    document.addEventListener('DOMContentLoaded', () => {
        paginateEntities();
        
        // Restore connection statuses from localStorage without re-testing
        const keys = Object.keys(connectionData);
        for (const key of keys) {
            const status = localStorage.getItem('conn_status_' + key);
            if (status === 'Connected') {
                const statusEl = document.getElementById('status-' + key);
                const btn = document.getElementById('btn-conn-' + key);
                if (statusEl && btn) {
                    statusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block shadow-[0_0_8px_rgba(16,185,129,0.4)]"></span> Connected';
                    statusEl.className = "text-label-sm text-emerald-600 font-medium flex items-center gap-1.5";
                    
                    btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link_off</span> Disconnect';
                    btn.className = "px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-600 transition-all duration-200 text-label-sm font-semibold flex items-center gap-1";
                    btn.setAttribute('onclick', `disconnectConnection('${key}')`);
                }
            }
        }
    });

    document.addEventListener('keydown', (e) => { 
        if (e.key === 'Escape') { 
            closeEntityModal(); 
            closeConnectionModal();
            closeDeleteModal(); 
        } 
    });
</script>
@endsection
