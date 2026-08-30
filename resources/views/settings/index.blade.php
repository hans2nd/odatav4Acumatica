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
                <div class="flex items-center gap-3">
                    <button id="btn-batch-delete-conn" onclick="batchDeleteConnections()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-red-50 text-red-600 text-label-md hover:bg-red-100 transition-all duration-200 shadow-sm border border-red-100">
                        <span class="material-symbols-outlined text-[14px]">delete</span> Delete Selected (<span id="batch-delete-conn-count">0</span>)
                    </button>
                    <button onclick="openConnectionModal()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 active:scale-[0.97] shadow-sm">
                        <span class="material-symbols-outlined text-[14px]">add</span> Add Connection
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="connection-table">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50">
                            <th class="py-3 px-5 text-left w-12">
                                <input type="checkbox" id="selectAllConnections" onchange="toggleAllConnections(this)" class="w-4 h-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500 cursor-pointer">
                            </th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Key</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Name</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Type</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Status</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-body-md text-surface-700 divide-y divide-surface-100">
                        @foreach ($connections as $key => $c)
                            <tr class="hover:bg-surface-50/50 transition-colors" id="conn-row-{{ $key }}">
                                <td class="py-3 px-5 w-12">
                                    <input type="checkbox" value="{{ $key }}" onchange="updateBatchDeleteConnBtn()" onclick="updateBatchDeleteConnBtn()" class="conn-checkbox w-4 h-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500 cursor-pointer">
                                </td>
                                <td class="py-3 px-5 font-medium font-mono text-body-sm text-surface-800">{{ $key }}</td>
                                <td class="py-3 px-5 font-medium text-surface-800">{{ $c['name'] }}</td>
                                <td class="py-3 px-5">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-label-sm font-semibold
                                        {{ in_array($c['type'], \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes()) ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700' }}">
                                        {{ $providers[$c['type']]['display'] ?? strtoupper($c['type']) }}
                                    </span>
                                </td>
                                <td class="py-3 px-5">
                                    @if(isset($c['is_connected']) && $c['is_connected'])
                                        <span id="status-{{ $key }}" class="text-label-sm text-emerald-600 font-medium flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block shadow-[0_0_8px_rgba(16,185,129,0.4)]"></span> Connected
                                        </span>
                                    @else
                                        <span id="status-{{ $key }}" class="text-label-sm text-surface-400 font-medium flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-surface-300"></span> Disconnected
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-5 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        @if(isset($c['is_connected']) && $c['is_connected'])
                                            <button onclick="disconnectConnection('{{ $key }}')" id="btn-conn-{{ $key }}"
                                                class="px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-600 transition-all duration-200 text-label-sm font-semibold flex items-center gap-1"
                                                title="Disconnect">
                                                <span class="material-symbols-outlined text-[14px]">link_off</span> Disconnect
                                            </button>
                                        @else
                                            <button onclick="testConnection('{{ $key }}')" id="btn-conn-{{ $key }}"
                                                class="px-2.5 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-all duration-200 text-label-sm font-semibold flex items-center gap-1"
                                                title="Test Connection">
                                                <span class="material-symbols-outlined text-[14px]">link</span> Connect
                                            </button>
                                        @endif
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
                <button id="btn-batch-delete" onclick="batchDeleteEntities()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-red-50 text-red-600 text-label-md hover:bg-red-100 transition-all duration-200 shadow-sm border border-red-100">
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
                                <input type="checkbox" value="{{ $key }}" onchange="updateBatchDeleteBtn()" onclick="updateBatchDeleteBtn()" class="entity-checkbox w-4 h-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500 cursor-pointer">
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
                                    <button onclick="manageEntityFields('{{ $key }}')"
                                        class="p-1.5 rounded-lg hover:bg-surface-100 text-surface-400 hover:text-brand-600 transition-all duration-200"
                                        title="Manage Fields">
                                        <span class="material-symbols-outlined text-[16px]">list_alt</span>
                                    </button>
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
                            @foreach($providers as $type => $provider)
                                <option value="{{ $type }}">{{ $provider['display'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Dynamic Fields Container -->
                    <div id="conn-dynamic-fields" class="space-y-4 pt-2 border-t border-surface-100">
                        <!-- Fields will be injected here by JavaScript based on provider schema -->
                    </div>

                    <!-- Diagnostics Results Container -->
                    <div id="conn-diagnostics-container" class="hidden mt-4 pt-4 border-t border-surface-100">
                        <h4 class="text-label-md font-semibold text-surface-700 mb-2">Diagnostic Results</h4>
                        <div id="conn-diagnostics-content" class="text-body-sm text-surface-600 space-y-2">
                        </div>
                    </div>
                </form>
                <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2 shrink-0 bg-surface-50/50">
                    <button type="button" onclick="closeConnectionModal()"
                        class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-200 transition-all duration-200 bg-surface-100 border border-surface-200">
                        Cancel
                    </button>
                    <button type="button" onclick="runDiagnosticsFromForm()" id="conn-diag-btn"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-purple-50 text-purple-600 border border-purple-200 text-label-md hover:bg-purple-100 transition-all duration-200 shadow-sm">
                        <span class="material-symbols-outlined text-[14px]">troubleshoot</span>
                        <span>Diagnostics</span>
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
                                    <option value="" disabled selected>Select Source Connection</option>
                                    @foreach($connections as $k => $c)
                                        @if(isset($c['is_connected']) && $c['is_connected'])
                                            <option value="{{ $k }}">{{ $c['name'] }} ({{ strtoupper($c['type']) }})</option>
                                        @endif
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

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="form-dest-conn" class="text-label-md text-surface-600 block mb-1.5">Destination Connection <span class="text-red-500">*</span></label>
                                <select id="form-dest-conn" required class="w-full bg-white border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                                    <option value="" disabled selected>Select Destination Connection</option>
                                    @foreach($connections as $k => $c)
                                        @if(in_array($c['type'], \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes()) && isset($c['is_connected']) && $c['is_connected'])
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

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="form-sync-mode" class="text-label-md text-surface-600 block mb-1.5">Sync Mode <span class="text-red-500">*</span></label>
                            <select id="form-sync-mode" name="sync_mode" required onchange="toggleWatermarkFields()" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                                <option value="full">Full Sync</option>
                                <option value="incremental">Incremental Sync</option>
                            </select>
                        </div>
                        <div>
                            <label for="form-batch-size" class="text-label-md text-surface-600 block mb-1.5">Batch Size <span class="text-red-500">*</span></label>
                            <input type="number" id="form-batch-size" required min="1" max="100000" class="w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                        </div>
                    </div>
                    
                    <div id="watermark-container" class="grid grid-cols-1 sm:grid-cols-2 gap-4 hidden bg-amber-50/50 p-4 rounded-xl border border-amber-200/60">
                        <div>
                            <label for="form-watermark-type" class="text-label-md text-surface-600 block mb-1.5">Watermark Type</label>
                            <select id="form-watermark-type" class="w-full bg-white border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                                <option value="">None</option>
                                <option value="datetime">DateTime</option>
                                <option value="integer">Integer</option>
                                <option value="string">String</option>
                            </select>
                        </div>
                        <div>
                            <label for="form-watermark-column" class="text-label-md text-surface-600 block mb-1.5">Watermark Column</label>
                            <input type="text" id="form-watermark-column" placeholder="e.g. LastModifiedDateTime" class="w-full bg-white border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                        </div>
                    </div>
                    
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="form-is-active" class="w-4 h-4 text-brand-600 bg-surface-100 border-surface-300 rounded focus:ring-brand-500">
                            <span class="text-label-md text-surface-700">Entity is Active</span>
                        </label>
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

    {{-- Field Management Modal --}}
    <div id="fields-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeFieldsModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-6xl animate-fade-up overflow-hidden max-h-[90vh] flex flex-col">
                <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center shrink-0">
                    <h3 class="text-h3 text-surface-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-brand-500 text-[20px]">list_alt</span>
                        Manage Fields: <span id="fields-modal-entity-name" class="font-mono text-brand-600 ml-1"></span>
                    </h3>
                    <button onclick="closeFieldsModal()"
                        class="p-1.5 rounded-xl hover:bg-surface-100 text-surface-400 hover:text-surface-700 transition-all duration-200">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
                
                <div class="p-4 bg-surface-50/50 border-b border-surface-100 shrink-0 flex justify-between items-center">
                    <div class="relative w-64">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-[18px]">search</span>
                        <input type="text" id="fields-search" onkeyup="filterFieldsTable()" placeholder="Search fields..." class="w-full bg-white border border-surface-200 rounded-xl pl-9 pr-3 py-2 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="discoverFields()" id="btn-discover-fields"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-100 text-surface-600 border border-surface-200 text-label-sm hover:bg-surface-200 transition-all duration-200">
                            <span class="material-symbols-outlined text-[14px]">sync</span>
                            <span>Refresh Metadata</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-y-auto p-0 flex-1 relative min-h-[300px]">
                    <div id="fields-loading" class="absolute inset-0 bg-white/80 backdrop-blur-sm z-10 flex flex-col items-center justify-center hidden">
                        <span class="material-symbols-outlined text-[32px] text-brand-500 spin mb-2">sync</span>
                        <p class="text-body-sm text-surface-600 font-medium" id="fields-loading-text">Loading fields...</p>
                    </div>
                    
                    <table class="w-full text-left border-collapse" id="fields-table">
                        <thead class="bg-surface-50 sticky top-0 z-10 shadow-sm shadow-surface-100/50">
                            <tr>
                                <th class="py-3 px-4 text-label-sm font-semibold text-surface-600 border-b border-surface-100 w-12 text-center">
                                    <input type="checkbox" id="fields-toggle-all" onchange="toggleAllFields(this)" class="w-4 h-4 text-brand-600 rounded border-surface-300 focus:ring-brand-500">
                                </th>
                                <th class="py-3 px-4 text-label-sm font-semibold text-surface-600 border-b border-surface-100">Source Field</th>
                                <th class="py-3 px-4 text-label-sm font-semibold text-surface-600 border-b border-surface-100">Destination Column</th>
                                <th class="py-3 px-4 text-label-sm font-semibold text-surface-600 border-b border-surface-100 w-28">Data Type</th>
                                <th class="py-3 px-4 text-label-sm font-semibold text-surface-600 border-b border-surface-100 w-32">Semantic Type</th>
                                <th class="py-3 px-4 text-label-sm font-semibold text-surface-600 border-b border-surface-100 w-32">Label</th>
                                <th class="py-3 px-4 text-label-sm font-semibold text-surface-600 border-b border-surface-100">Description</th>
                                <th class="py-3 px-4 text-label-sm font-semibold text-surface-600 border-b border-surface-100 w-48">Aliases</th>
                                <th class="py-3 px-4 text-label-sm font-semibold text-surface-600 border-b border-surface-100 w-20 text-center">Is Key</th>
                                <th class="py-3 px-4 text-label-sm font-semibold text-surface-600 border-b border-surface-100 w-20 text-center">Nullable</th>
                                <th class="py-3 px-4 text-label-sm font-semibold text-surface-600 border-b border-surface-100 w-28">Transform</th>
                            </tr>
                        </thead>
                        <tbody id="fields-tbody" class="divide-y divide-surface-100 text-body-sm">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-4 border-t border-surface-100 flex justify-between items-center shrink-0 bg-surface-50/50">
                    <div class="text-label-sm text-surface-500" id="fields-count-summary">0 fields total</div>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeFieldsModal()"
                            class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-200 transition-all duration-200 bg-surface-100 border border-surface-200">
                            Cancel
                        </button>
                        <button type="button" onclick="submitFieldsForm()" id="fields-submit-btn"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 active:scale-[0.97] shadow-sm">
                            <span class="material-symbols-outlined text-[14px]">save</span>
                            <span>Save Mappings</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    // csrfToken is already declared globally in app.blade.php
    const entityData = @json($entities);
    const connectionData = @json($connections);
    const providerSchemas = @json($schemas);
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
            
            document.getElementById('form-sync-mode').value = e.sync_mode || 'full';
            document.getElementById('form-batch-size').value = e.batch_size || 2000;
            document.getElementById('form-watermark-type').value = e.watermark_type || '';
            document.getElementById('form-watermark-column').value = e.watermark_column || '';
            document.getElementById('form-is-active').checked = e.is_active ?? true;
            
            document.getElementById('modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">edit</span> Edit Entity';
            document.getElementById('form-submit-text').textContent = 'Save Changes';
            fetchSourceObjects();
        } else {
            document.getElementById('form-sync-mode').value = 'full';
            document.getElementById('form-batch-size').value = 2000;
            document.getElementById('form-watermark-type').value = '';
            document.getElementById('form-watermark-column').value = '';
            document.getElementById('form-is-active').checked = true;
            
            document.getElementById('modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">add_circle</span> Add Entity';
            document.getElementById('form-submit-text').textContent = 'Create Entity';
            fetchSourceObjects();
        }
        toggleWatermarkFields();
        modal.classList.remove('hidden');
    }

    function toggleWatermarkFields() {
        const mode = document.getElementById('form-sync-mode').value;
        const container = document.getElementById('watermark-container');
        if (mode === 'incremental') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
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

    const providerRegistry = @json($providers);

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
            sync_mode: document.getElementById('form-sync-mode').value,
            batch_size: parseInt(document.getElementById('form-batch-size').value, 10),
            watermark_type: document.getElementById('form-watermark-type').value,
            watermark_column: document.getElementById('form-watermark-column').value.trim(),
            is_active: document.getElementById('form-is-active').checked,
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

    // ── Field Management Modal ─────────────────────────────────────────────
    let currentFieldsEntity = null;
    let currentFieldsData = [];

    async function manageEntityFields(key) {
        currentFieldsEntity = key;
        document.getElementById('fields-modal-entity-name').textContent = key;
        document.getElementById('fields-search').value = '';
        document.getElementById('fields-modal').classList.remove('hidden');
        await loadFields(key);
    }

    function closeFieldsModal() {
        document.getElementById('fields-modal').classList.add('hidden');
        currentFieldsEntity = null;
        currentFieldsData = [];
    }

    async function loadFields(key) {
        const loading = document.getElementById('fields-loading');
        loading.classList.remove('hidden');
        document.getElementById('fields-loading-text').textContent = 'Loading fields...';
        
        try {
            const res = await fetch(`/api/entities/${key}/mappings`);
            const data = await res.json();
            
            if (res.ok) {
                // If it returns an array directly or { success: true, fields: [...] }
                currentFieldsData = data.fields || (Array.isArray(data) ? data : Object.values(data));
                // Convert object to array if needed
                if (!Array.isArray(currentFieldsData) && typeof currentFieldsData === 'object') {
                    currentFieldsData = Object.keys(currentFieldsData).map(k => ({ source_field: k, ...currentFieldsData[k] }));
                }
                
                // Sort by source_field
                currentFieldsData.sort((a, b) => a.source_field.localeCompare(b.source_field));
                
                renderFieldsTable();
            } else {
                showToast(data.message || 'Failed to load fields', 'error');
            }
        } catch (e) {
            showToast('Error: ' + e.message, 'error');
        } finally {
            loading.classList.add('hidden');
        }
    }

    function renderFieldsTable() {
        const tbody = document.getElementById('fields-tbody');
        const filter = document.getElementById('fields-search').value.toLowerCase();
        let html = '';
        let count = 0;
        let allEnabled = true;

        currentFieldsData.forEach((field, index) => {
            if (filter && !field.source_field.toLowerCase().includes(filter) && !(field.destination_field || '').toLowerCase().includes(filter)) {
                return;
            }
            count++;
            
            if (field.is_enabled === false) {
                allEnabled = false;
            }

            html += `
                <tr class="hover:bg-surface-50/50 transition-colors" data-index="${index}">
                    <td class="py-2.5 px-4 text-center border-b border-surface-100">
                        <input type="checkbox" class="field-enabled w-4 h-4 text-brand-600 rounded border-surface-300 focus:ring-brand-500" ${field.is_enabled !== false ? 'checked' : ''} onchange="updateFieldData(${index}, 'is_enabled', this.checked)">
                    </td>
                    <td class="py-2.5 px-4 border-b border-surface-100 font-mono text-surface-900 font-medium">
                        ${field.source_field}
                        <input type="hidden" class="field-source" value="${field.source_field}">
                    </td>
                    <td class="py-2.5 px-4 border-b border-surface-100">
                        <input type="text" class="field-dest w-full bg-white border border-surface-200 rounded-lg px-2 py-1 text-xs focus:ring-2 focus:ring-brand-500/20" value="${field.destination_field || ''}" onchange="updateFieldData(${index}, 'destination_field', this.value)">
                    </td>
                    <td class="py-2.5 px-4 border-b border-surface-100">
                        <input type="text" class="field-type w-full bg-white border border-surface-200 rounded-lg px-2 py-1 text-xs font-mono focus:ring-2 focus:ring-brand-500/20" value="${field.data_type || ''}" onchange="updateFieldData(${index}, 'data_type', this.value)">
                    </td>
                    <td class="py-2.5 px-4 border-b border-surface-100">
                        <select class="field-semantic w-full bg-white border border-surface-200 rounded-lg px-2 py-1 text-xs focus:ring-2 focus:ring-brand-500/20" onchange="updateFieldData(${index}, 'semantic_type', this.value)">
                            <option value="" ${!field.semantic_type ? 'selected' : ''}>None</option>
                            <option value="identifier" ${field.semantic_type === 'identifier' ? 'selected' : ''}>Identifier</option>
                            <option value="metric" ${field.semantic_type === 'metric' ? 'selected' : ''}>Metric</option>
                            <option value="amount" ${field.semantic_type === 'amount' ? 'selected' : ''}>Amount</option>
                            <option value="currency" ${field.semantic_type === 'currency' ? 'selected' : ''}>Currency</option>
                            <option value="dimension" ${field.semantic_type === 'dimension' ? 'selected' : ''}>Dimension</option>
                            <option value="categorical" ${field.semantic_type === 'categorical' ? 'selected' : ''}>Categorical</option>
                            <option value="date" ${field.semantic_type === 'date' ? 'selected' : ''}>Date</option>
                            <option value="datetime" ${field.semantic_type === 'datetime' ? 'selected' : ''}>Datetime</option>
                            <option value="boolean" ${field.semantic_type === 'boolean' ? 'selected' : ''}>Boolean</option>
                            <option value="status" ${field.semantic_type === 'status' ? 'selected' : ''}>Status</option>
                        </select>
                    </td>
                    <td class="py-2.5 px-4 border-b border-surface-100">
                        <input type="text" class="field-label w-full bg-white border border-surface-200 rounded-lg px-2 py-1 text-xs focus:ring-2 focus:ring-brand-500/20" value="${field.label || ''}" placeholder="Label" onchange="updateFieldData(${index}, 'label', this.value)">
                    </td>
                    <td class="py-2.5 px-4 border-b border-surface-100">
                        <input type="text" class="field-description w-full bg-white border border-surface-200 rounded-lg px-2 py-1 text-xs focus:ring-2 focus:ring-brand-500/20" value="${field.description || ''}" placeholder="Description" onchange="updateFieldData(${index}, 'description', this.value)">
                    </td>
                    <td class="py-2.5 px-4 border-b border-surface-100">
                        <input type="text" class="field-aliases w-full bg-white border border-surface-200 rounded-lg px-2 py-1 text-xs focus:ring-2 focus:ring-brand-500/20" value="${(field.aliases || []).join(', ')}" placeholder="tag1, tag2" onchange="updateFieldAliases(${index}, this.value)">
                    </td>
                    <td class="py-2.5 px-4 text-center border-b border-surface-100">
                        <input type="checkbox" class="field-key w-4 h-4 text-amber-500 rounded border-surface-300 focus:ring-amber-500" ${field.is_key ? 'checked' : ''} onchange="updateFieldData(${index}, 'is_key', this.checked)">
                    </td>
                    <td class="py-2.5 px-4 text-center border-b border-surface-100">
                        <input type="checkbox" class="field-nullable w-4 h-4 text-brand-600 rounded border-surface-300 focus:ring-brand-500" ${field.is_nullable !== false ? 'checked' : ''} onchange="updateFieldData(${index}, 'is_nullable', this.checked)">
                    </td>
                    <td class="py-2.5 px-4 border-b border-surface-100">
                        <input type="text" class="field-transform w-full bg-white border border-surface-200 rounded-lg px-2 py-1 text-xs focus:ring-2 focus:ring-brand-500/20" value="${field.transform_rule || ''}" placeholder="e.g. trim" onchange="updateFieldData(${index}, 'transform_rule', this.value)">
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        document.getElementById('fields-count-summary').textContent = `${count} fields visible`;
        document.getElementById('fields-toggle-all').checked = count > 0 && allEnabled;
    }

    function updateFieldData(index, prop, value) {
        if (currentFieldsData[index]) {
            currentFieldsData[index][prop] = value === '' ? null : value;
        }
    }

    function updateFieldAliases(index, value) {
        if (currentFieldsData[index]) {
            if (!value || value.trim() === '') {
                currentFieldsData[index].aliases = null;
            } else {
                currentFieldsData[index].aliases = value.split(',').map(s => s.trim()).filter(s => s.length > 0);
            }
        }
    }

    function filterFieldsTable() {
        renderFieldsTable();
    }

    function toggleAllFields(checkbox) {
        const checked = checkbox.checked;
        const filter = document.getElementById('fields-search').value.toLowerCase();
        
        currentFieldsData.forEach((field) => {
            if (filter && !field.source_field.toLowerCase().includes(filter) && !(field.destination_field || '').toLowerCase().includes(filter)) {
                return;
            }
            field.is_enabled = checked;
        });
        
        renderFieldsTable();
    }

    async function submitFieldsForm() {
        if (!currentFieldsEntity) return;
        
        const btn = document.getElementById('fields-submit-btn');
        btn.disabled = true;
        
        try {
            const payload = { fields: currentFieldsData };
            const res = await fetch(`/api/entities/${currentFieldsEntity}/mappings`, { 
                method: 'PUT', 
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, 
                body: JSON.stringify(payload) 
            });
            const data = await res.json();
            
            if (res.ok && data.success) { 
                showToast(data.message, 'success'); 
                closeFieldsModal(); 
            } else { 
                showToast(data.message || 'Operation failed', 'error'); 
            }
        } catch (e) { 
            showToast('Error: ' + e.message, 'error'); 
        } finally { 
            btn.disabled = false; 
        }
    }

    async function discoverFields() {
        if (!currentFieldsEntity) return;
        
        const btn = document.getElementById('btn-discover-fields');
        btn.disabled = true;
        document.getElementById('fields-loading').classList.remove('hidden');
        document.getElementById('fields-loading-text').textContent = 'Discovering metadata (may take a minute)...';
        
        try {
            // Assuming this triggers the job and we wait a bit or we just trigger the command directly
            const res = await fetch(`/api/entities/${currentFieldsEntity}/discover`, { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } 
            });
            const data = await res.json();
            
            if (res.ok) {
                showToast('Discovery completed', 'success');
                await loadFields(currentFieldsEntity);
            } else {
                showToast(data.message || 'Discovery failed', 'error');
            }
        } catch (e) {
            showToast('Error: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
            document.getElementById('fields-loading').classList.add('hidden');
        }
    }

    // ── Connection Modal ───────────────────────────────────────────────────
    function toggleConnectionFields() {
        const type = document.getElementById('conn-type').value;
        const container = document.getElementById('conn-dynamic-fields');
        container.innerHTML = ''; // clear current fields

        const schema = providerSchemas[type] || [];
        
        schema.forEach(field => {
            const wrapper = document.createElement('div');
            wrapper.className = 'mb-4';

            // Label
            const label = document.createElement('label');
            label.className = 'text-label-md text-surface-600 block mb-1.5';
            label.innerHTML = `${field.label} ${field.required ? '<span class="text-red-500">*</span>' : ''}`;
            wrapper.appendChild(label);

            // Input element
            if (field.type === 'select') {
                const select = document.createElement('select');
                select.id = `dyn_${field.field}`;
                select.className = 'w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20';
                if (field.required) select.required = true;
                
                let optionsData = field.options;
                // If options is empty but default is an object/array, fallback to it
                if ((!optionsData || (Array.isArray(optionsData) && optionsData.length === 0)) && field.default && typeof field.default === 'object') {
                    optionsData = field.default;
                }
                
                for (const [val, text] of Object.entries(optionsData || {})) {
                    const opt = document.createElement('option');
                    opt.value = val;
                    // Handle array of objects format if it was ever passed
                    if (typeof text === 'object' && text !== null) {
                        opt.value = text.value || val;
                        opt.textContent = text.label || text.text || val;
                    } else {
                        opt.textContent = text;
                    }
                    if (field.default === val || field.placeholder === val) opt.selected = true;
                    select.appendChild(opt);
                }
                wrapper.appendChild(select);
            } else if (field.type === 'boolean') {
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = `dyn_${field.field}`;
                checkbox.className = 'w-4 h-4 text-brand-600 border-surface-300 rounded focus:ring-brand-500';
                if (field.default === true || field.default === 'true' || field.default === 1) checkbox.checked = true;
                
                // Wrap in a flex row
                const flex = document.createElement('div');
                flex.className = 'flex items-center gap-2';
                label.className = 'text-label-md text-surface-600 cursor-pointer';
                label.setAttribute('for', checkbox.id);
                
                flex.appendChild(checkbox);
                flex.appendChild(label);
                
                wrapper.innerHTML = ''; // remove original label
                wrapper.appendChild(flex);
            } else if (field.type === 'textarea') {
                const textarea = document.createElement('textarea');
                textarea.id = `dyn_${field.field}`;
                textarea.className = 'w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20';
                textarea.rows = 3;
                if (field.required) textarea.required = true;
                if (field.placeholder) textarea.placeholder = field.placeholder;
                if (field.default !== null && typeof field.default !== 'object') textarea.value = field.default;
                
                wrapper.appendChild(textarea);
            } else {
                const input = document.createElement('input');
                input.type = field.type;
                input.id = `dyn_${field.field}`;
                input.className = 'w-full bg-surface-50 border border-surface-200 rounded-xl px-3.5 py-2.5 font-mono text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20';
                if (field.required) input.required = true;
                if (field.placeholder) input.placeholder = field.placeholder;
                if (field.default !== null && typeof field.default !== 'object') input.value = field.default;
                
                wrapper.appendChild(input);
            }

            // Help Text
            if (field.helpText) {
                const help = document.createElement('p');
                help.className = 'text-[11px] text-surface-400 mt-1';
                help.textContent = field.helpText;
                wrapper.appendChild(help);
            }

            container.appendChild(wrapper);
        });

        // Add Live URL Preview for Acumatica Connections
        if (type === 'acumatica') {
            const previewWrapper = document.createElement('div');
            previewWrapper.className = 'mt-5 p-4 bg-brand-50 rounded-xl border border-brand-200 shadow-inner';
            
            const title = document.createElement('div');
            title.className = 'text-label-md font-semibold text-brand-800 mb-1 flex items-center gap-1.5';
            title.innerHTML = '<span class="material-symbols-outlined text-[16px]">link</span> Computed OData URL Preview';
            
            const urlText = document.createElement('div');
            urlText.id = 'acm-url-preview';
            urlText.className = 'text-body-sm text-brand-700 font-mono break-all font-semibold';
            
            const hintText = document.createElement('div');
            hintText.id = 'acm-entity-hint';
            hintText.className = 'text-[11px] text-brand-600/80 mt-1.5 italic';
            
            previewWrapper.appendChild(title);
            previewWrapper.appendChild(urlText);
            previewWrapper.appendChild(hintText);
            container.appendChild(previewWrapper);
            
            const updatePreview = () => {
                const baseUrlInput = document.getElementById('dyn_base_url');
                const tenantInput = document.getElementById('dyn_tenant');
                const protocolInput = document.getElementById('dyn_protocol');
                const overrideInput = document.getElementById('dyn_odata_url');
                
                if (!baseUrlInput || !tenantInput || !protocolInput) return;
                
                let base = baseUrlInput.value.trim().replace(/\/$/, '');
                let tenant = tenantInput.value.trim().replace(/^\/|\/$/g, '');
                let protocol = protocolInput.value;
                let endpoint = (protocol === 'odata_v3' || protocol === 'v3') ? 'odata' : 'odatav4';
                
                let finalUrl = '';
                if (overrideInput && overrideInput.value.trim() !== '') {
                    finalUrl = overrideInput.value.trim().replace(/\/$/, '');
                } else {
                    if (tenant !== '') {
                        if (base.toLowerCase().endsWith('/' + tenant.toLowerCase())) {
                            finalUrl = `${base}/${endpoint}`;
                        } else {
                            finalUrl = `${base}/${tenant}/${endpoint}`;
                        }
                    } else {
                        finalUrl = `${base}/${endpoint}`;
                    }
                }
                
                document.getElementById('acm-url-preview').textContent = finalUrl || 'https://...';
                document.getElementById('acm-entity-hint').textContent = `* Jika memanggil entity baru contoh INSite, pemanggilan sync nya menjadi: ${finalUrl}/INSite`;
            };
            
            // Allow DOM to render inputs then bind events
            setTimeout(() => {
                ['dyn_base_url', 'dyn_tenant', 'dyn_odata_url'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.addEventListener('input', updatePreview);
                });
                const protoEl = document.getElementById('dyn_protocol');
                if (protoEl) protoEl.addEventListener('change', updatePreview);
                updatePreview();
            }, 50);
        }
    }

    function closeConnectionModal() {
        document.getElementById('connection-modal').classList.add('hidden');
    }

    function openConnectionModal(editKey = null) {
        const modal = document.getElementById('connection-modal');
        const form = document.getElementById('connection-form');
        form.reset();
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

            // Populate dynamic fields
            const schema = providerSchemas[c.type] || [];
            schema.forEach(field => {
                const el = document.getElementById(`dyn_${field.field}`);
                if (el && c.credentials && c.credentials[field.field] !== undefined) {
                    if (field.type === 'boolean') {
                        el.checked = c.credentials[field.field];
                    } else {
                        el.value = c.credentials[field.field];
                    }
                }
            });

            document.getElementById('conn-modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">edit</span> Edit Connection';
            document.getElementById('conn-submit-text').textContent = 'Save Changes';
        } else {
            toggleConnectionFields();
            document.getElementById('conn-modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">add_circle</span> Add Connection';
            document.getElementById('conn-submit-text').textContent = 'Create Connection';
        }
        modal.classList.remove('hidden');
    }

    function getCredentialsFromSchema(type) {
        const schema = providerSchemas[type] || [];
        const credentials = {};
        schema.forEach(field => {
            const el = document.getElementById(`dyn_${field.field}`);
            if (el) {
                credentials[field.field] = field.type === 'boolean' ? el.checked : el.value;
            }
        });
        return credentials;
    }

    async function testConnectionFromForm() {
        if (!document.getElementById('connection-form').reportValidity()) return;
        const type = document.getElementById('conn-type').value;
        const credentials = getCredentialsFromSchema(type);

        const btn = document.getElementById('conn-test-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Testing...';
        
        try {
            const res = await fetch('/api/connections/test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ type, credentials })
            });
            const data = await res.json();
            if (res.ok && data.success) { showToast(data.message, 'success'); }
            else { showToast(data.message || 'Connection failed', 'error'); }
        } catch (e) { showToast('Error: ' + e.message, 'error'); }
        finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">science</span> Test';
        }
    }

    async function runDiagnosticsFromForm() {
        if (!document.getElementById('connection-form').reportValidity()) return;
        const type = document.getElementById('conn-type').value;
        const credentials = getCredentialsFromSchema(type);

        const btn = document.getElementById('conn-diag-btn');
        const container = document.getElementById('conn-diagnostics-container');
        const content = document.getElementById('conn-diagnostics-content');
        
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Running...';
        container.classList.remove('hidden');
        content.innerHTML = '<div class="text-surface-500 animate-pulse">Running full production diagnostics...</div>';
        
        try {
            const res = await fetch('/api/connections/diagnostics', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ type, credentials })
            });
            const data = await res.json();
            
            let html = '<div class="grid grid-cols-2 gap-4">';
            
            // Checks
            if (data.checks && Object.keys(data.checks).length > 0) {
                html += '<div class="space-y-1.5"><h5 class="font-medium text-surface-700 text-xs uppercase tracking-wider mb-2">Checks</h5>';
                for (const [key, passed] of Object.entries(data.checks)) {
                    const icon = passed ? '<span class="text-emerald-500 material-symbols-outlined text-[16px] align-middle">check_circle</span>' : '<span class="text-red-500 material-symbols-outlined text-[16px] align-middle">cancel</span>';
                    html += `<div class="flex items-center justify-between py-1 border-b border-surface-100 last:border-0"><span class="text-surface-600">${key}</span> ${icon}</div>`;
                }
                html += '</div>';
            }
            
            // Summary
            if (data.summary && Object.keys(data.summary).length > 0) {
                html += '<div class="space-y-1.5"><h5 class="font-medium text-surface-700 text-xs uppercase tracking-wider mb-2">Summary</h5>';
                for (const [key, val] of Object.entries(data.summary)) {
                    html += `<div class="flex items-center justify-between py-1 border-b border-surface-100 last:border-0"><span class="text-surface-500">${key}</span> <span class="font-medium text-surface-800 text-right truncate pl-2" title="${val}">${val}</span></div>`;
                }
                if (data.metrics && data.metrics.latency_ms) {
                    html += `<div class="flex items-center justify-between py-1"><span class="text-surface-500">Latency</span> <span class="font-medium text-surface-800">${data.metrics.latency_ms} ms</span></div>`;
                }
                html += '</div>';
            }
            
            html += '</div>';

            // Error
            if (data.error) {
                html += `<div class="mt-4 p-3 rounded-lg bg-red-50 border border-red-200">
                    <div class="font-semibold text-red-700 flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span> ${data.error.title} (${data.error.code})</div>
                    <div class="text-red-600 mt-1">${data.error.message}</div>
                    ${data.error.sql_state ? `<div class="text-xs font-mono text-red-500 mt-2 p-1.5 bg-red-100/50 rounded">SQLSTATE: ${data.error.sql_state}</div>` : ''}
                    <div class="text-xs font-mono text-red-400 mt-1 truncate" title="${data.error.technical_message}">${data.error.technical_message}</div>
                </div>`;
            }

            content.innerHTML = html;
            
            if (res.ok && data.success) { showToast('Diagnostics passed.', 'success'); }
            else { showToast('Diagnostics failed or produced warnings.', 'error'); }
        } catch (e) { 
            content.innerHTML = `<div class="text-red-500 p-3 bg-red-50 rounded-lg border border-red-200">Error: ${e.message}</div>`;
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">troubleshoot</span> Diagnostics';
        }
    }

    async function submitConnectionForm(e) {
        e.preventDefault();
        if(!document.getElementById('connection-form').checkValidity()) {
            document.getElementById('connection-form').reportValidity();
            return;
        }

        const mode = document.getElementById('conn-form-mode').value;
        const originalKey = document.getElementById('conn-form-original-key').value;
        const type = document.getElementById('conn-type').value;
        const credentials = getCredentialsFromSchema(type);

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

    function updateEntityModalConnectionDropdowns() {
        const sourceSelect = document.getElementById('form-source-conn');
        const destSelect = document.getElementById('form-dest-conn');
        if (!sourceSelect || !destSelect) return;
        
        const currentSource = sourceSelect.value;
        const currentDest = destSelect.value;
        
        sourceSelect.innerHTML = '<option value="" disabled selected>Select Source Connection</option>';
        destSelect.innerHTML = '<option value="" disabled selected>Select Destination Connection</option>';
        
        for (const [k, c] of Object.entries(connectionData)) {
            if (c.is_connected) {
                const optionText = `${c.name} (${c.type.toUpperCase()})`;
                
                // Add to Source Connection
                const optSource = document.createElement('option');
                optSource.value = k;
                optSource.textContent = optionText;
                sourceSelect.appendChild(optSource);
                
                // Add to Destination Connection if supported
                if (providerRegistry[c.type] && providerRegistry[c.type].kind === 'database') {
                    const optDest = document.createElement('option');
                    optDest.value = k;
                    optDest.textContent = optionText;
                    destSelect.appendChild(optDest);
                }
            }
        }
        
        if (currentSource && sourceSelect.querySelector(`option[value="${currentSource}"]`)) {
            sourceSelect.value = currentSource;
        } else {
            sourceSelect.value = "";
        }
        
        if (currentDest && destSelect.querySelector(`option[value="${currentDest}"]`)) {
            destSelect.value = currentDest;
        } else {
            destSelect.value = "";
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
                
                connectionData[key].is_connected = true;
                updateEntityModalConnectionDropdowns();
                
                if(!silent) showToast(data.message, 'success');
            } else {
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

    async function disconnectConnection(key) {
        try {
            const btn = document.getElementById('btn-conn-' + key);
            const originalBtnHtml = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-[14px]">progress_activity</span> Disconnecting...';
            btn.disabled = true;

            const res = await fetch(`/api/connections/${key}/disconnect`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            
            if (data.success) {
                const statusEl = document.getElementById('status-' + key);
                const btn = document.getElementById('btn-conn-' + key);
                
                statusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-surface-300"></span> Disconnected';
                statusEl.className = "text-label-sm text-surface-400 font-medium flex items-center gap-1.5";
                
                btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link</span> Connect';
                btn.className = "px-2.5 py-1.5 rounded-lg bg-surface-50 hover:bg-surface-100 text-surface-600 transition-all duration-200 text-label-sm font-semibold flex items-center gap-1";
                btn.setAttribute('onclick', `testConnection('${key}', true)`);
                btn.disabled = false;
                
                connectionData[key].is_connected = false;
                updateEntityModalConnectionDropdowns();
                
                showToast('Disconnected successfully', 'success');
            } else {
                throw new Error(data.message || 'Failed to disconnect');
            }
        } catch(err) {
            console.error(err);
            const btn = document.getElementById('btn-conn-' + key);
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link_off</span> Disconnect';
            btn.disabled = false;
            showToast(err.message, 'error');
        }
    }

    // ── Delete & Batch Delete ──────────────────────────────────────────────
    let deleteTargetBatchKeys = [];

    function deleteEntity(key) { 
        document.getElementById('delete-type').value = 'entity';
        deleteTargetKey = key; 
        deleteTargetBatchKeys = [];
        document.getElementById('delete-name').textContent = `entity "${key}"`; 
        document.getElementById('delete-modal').classList.remove('hidden'); 
    }

    function deleteConnection(key) { 
        document.getElementById('delete-type').value = 'connection';
        deleteTargetKey = key; 
        deleteTargetBatchKeys = [];
        document.getElementById('delete-name').textContent = `connection "${connectionData[key] ? connectionData[key].name : key}"`; 
        document.getElementById('delete-modal').classList.remove('hidden'); 
    }
    
    // ── Batch Delete Connections ───────────────────────────────────────────
    function toggleAllConnections(source) {
        const isChecked = source.checked;
        const checkboxes = document.querySelectorAll('.conn-checkbox');
        checkboxes.forEach(cb => {
            const tr = cb.closest('tr');
            if (!tr || tr.style.display !== 'none') {
                cb.checked = isChecked;
            }
        });
        updateBatchDeleteConnBtn();
    }

    function updateBatchDeleteConnBtn() {
        const checked = document.querySelectorAll('.conn-checkbox:checked');
        const selectedCount = checked.length;
        const btn = document.getElementById('btn-batch-delete-conn');
        const countSpan = document.getElementById('batch-delete-conn-count');
        
        if (countSpan) countSpan.textContent = selectedCount;
        
        if (btn) {
            if (selectedCount > 0) {
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
        
        const selectAll = document.getElementById('selectAllConnections');
        if (selectAll) {
            const visibleCheckboxes = Array.from(document.querySelectorAll('.conn-checkbox')).filter(cb => {
                const tr = cb.closest('tr');
                return !tr || tr.style.display !== 'none';
            });
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

    function batchDeleteConnections() {
        const selected = Array.from(document.querySelectorAll('.conn-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            showToast('Please select at least one connection to delete.', 'error');
            return;
        }
        
        document.getElementById('delete-type').value = 'batch_connection';
        deleteTargetKey = null;
        deleteTargetBatchKeys = selected;
        document.getElementById('delete-name').textContent = `${selected.length} selected connection(s) (${selected.join(', ')})`;
        document.getElementById('delete-modal').classList.remove('hidden');
    }

    // ── Batch Delete Entities ──────────────────────────────────────────────
    function toggleAllEntities(source) {
        const isChecked = source.checked;
        const checkboxes = document.querySelectorAll('.entity-checkbox');
        checkboxes.forEach(cb => {
            const tr = cb.closest('tr');
            if (!tr || tr.style.display !== 'none') {
                cb.checked = isChecked;
            }
        });
        updateBatchDeleteBtn();
    }

    function updateBatchDeleteBtn() {
        const checked = document.querySelectorAll('.entity-checkbox:checked');
        const selectedCount = checked.length;
        const btn = document.getElementById('btn-batch-delete');
        const countSpan = document.getElementById('batch-delete-count');
        
        if (countSpan) countSpan.textContent = selectedCount;
        
        if (btn) {
            if (selectedCount > 0) {
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
        
        const selectAll = document.getElementById('selectAllEntities');
        if (selectAll) {
            const visibleCheckboxes = Array.from(document.querySelectorAll('.entity-checkbox')).filter(cb => {
                const tr = cb.closest('tr');
                return !tr || tr.style.display !== 'none';
            });
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

    function batchDeleteEntities() {
        const selected = Array.from(document.querySelectorAll('.entity-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) {
            showToast('Please select at least one entity to delete.', 'error');
            return;
        }
        
        document.getElementById('delete-type').value = 'batch_entity';
        deleteTargetKey = null;
        deleteTargetBatchKeys = selected;
        document.getElementById('delete-name').textContent = `${selected.length} selected entity(s) (${selected.join(', ')})`;
        document.getElementById('delete-modal').classList.remove('hidden');
    }

    function closeDeleteModal() { 
        document.getElementById('delete-modal').classList.add('hidden'); 
        deleteTargetKey = null; 
        deleteTargetBatchKeys = [];
    }
    
    async function confirmDelete() {
        const type = document.getElementById('delete-type').value;
        const btn = document.getElementById('delete-confirm-btn');
        btn.disabled = true; 
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Deleting…';
        
        try {
            let res, data;
            if (type === 'batch_connection') {
                res = await fetch('/api/connections/batch-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ keys: deleteTargetBatchKeys })
                });
                data = await res.json();
            } else if (type === 'batch_entity') {
                res = await fetch('/api/entities/batch-delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ keys: deleteTargetBatchKeys })
                });
                data = await res.json();
            } else if (type === 'entity') {
                res = await fetch(`/api/entities/${deleteTargetKey}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                });
                data = await res.json();
            } else if (type === 'connection') {
                res = await fetch(`/api/connections/${deleteTargetKey}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                });
                data = await res.json();
            }

            if (res && res.ok && data && data.success) {
                showToast(data.message || 'Deleted successfully', 'success');
                closeDeleteModal();
                setTimeout(() => location.reload(), 600);
            } else {
                showToast(data?.message || 'Delete operation failed', 'error');
            }
        } catch (e) {
            showToast('Error: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">delete</span> Delete';
        }
    }


    document.addEventListener('keydown', (e) => { 
        if (e.key === 'Escape') { 
            closeEntityModal(); 
            closeConnectionModal();
            closeDeleteModal(); 
        } 
    });
</script>
@endsection
