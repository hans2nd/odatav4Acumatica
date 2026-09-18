@extends('layouts.app')

@section('title', 'Settings — PL Sync Data Warehouse')
@section('page-title', 'Settings')

@section('content')
    <div class="grid grid-cols-1 gap-5">

        {{-- ── Connection Management Card ──────────────────────────────────── --}}
        <x-ui.card
            title="Connection Management"
            icon="lan"
            subtitle="Define data sources and destinations (OData, REST, MySQL, SQL Server, SharePoint)"
            gradient="from-indigo-50 to-blue-100"
            iconColor="text-indigo-600"
        >
            <x-slot:actions>
                <x-ui.btn variant="danger" icon="delete" id="btn-batch-delete-conn" onclick="batchDeleteConnections()">
                    Delete Selected (<span id="batch-delete-conn-count">0</span>)
                </x-ui.btn>
                <x-ui.btn variant="primary" icon="add" onclick="openConnectionModal()">
                    Add Connection
                </x-ui.btn>
            </x-slot:actions>

            <x-ui.data-table
                :columns="[
                    'Key',
                    'Name',
                    'Type',
                    'Status',
                    ['label' => 'Actions', 'class' => 'text-right'],
                ]"
                id="connection-table"
                checkboxId="selectAllConnections"
                checkboxOnchange="toggleAllConnections(this)"
            >
                @foreach ($connections as $key => $c)
                    <tr class="group" id="conn-row-{{ $key }}">
                        <td class="w-12">
                            <input type="checkbox" value="{{ $key }}" onchange="updateBatchDeleteConnBtn()" onclick="updateBatchDeleteConnBtn()" class="conn-checkbox ui-checkbox">
                        </td>
                        <td class="font-medium font-mono text-body-sm text-surface-800">{{ $key }}</td>
                        <td class="font-medium text-surface-800">{{ $c['name'] }}</td>
                        <td>
                            @if($c['type'] === 'sharepoint')
                                <x-ui.badge variant="teal">{{ $providers[$c['type']]['display'] ?? strtoupper($c['type']) }}</x-ui.badge>
                            @elseif(in_array($c['type'], \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes()))
                                <x-ui.badge variant="violet">{{ $providers[$c['type']]['display'] ?? strtoupper($c['type']) }}</x-ui.badge>
                            @else
                                <x-ui.badge variant="info">{{ $providers[$c['type']]['display'] ?? strtoupper($c['type']) }}</x-ui.badge>
                            @endif
                        </td>
                        <td>
                            @if(isset($c['is_connected']) && $c['is_connected'])
                                <span id="status-{{ $key }}" class="text-label-sm text-emerald-600 font-medium flex items-center gap-1.5">
                                    <span class="status-dot status-dot-online"></span> Connected
                                </span>
                            @else
                                <span id="status-{{ $key }}" class="text-label-sm text-surface-400 font-medium flex items-center gap-1.5">
                                    <span class="status-dot status-dot-offline"></span> Disconnected
                                </span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-1.5">
                                @if(isset($c['is_connected']) && $c['is_connected'])
                                    <button onclick="disconnectConnection('{{ $key }}')" id="btn-conn-{{ $key }}"
                                        class="ui-btn ui-btn-sm bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border border-emerald-200"
                                        title="Disconnect">
                                        <span class="material-symbols-outlined text-[14px]">link_off</span> Disconnect
                                    </button>
                                @else
                                    <button onclick="testConnection('{{ $key }}')" id="btn-conn-{{ $key }}"
                                        class="ui-btn ui-btn-sm bg-red-50 text-red-600 hover:bg-red-100 border border-red-200"
                                        title="Test Connection">
                                        <span class="material-symbols-outlined text-[14px]">link</span> Connect
                                    </button>
                                @endif
                                <button onclick="editConnection('{{ $key }}')" class="ui-icon-btn text-surface-400 hover:text-surface-700" title="Edit">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                </button>
                                <button onclick="deleteConnection('{{ $key }}')" class="ui-icon-btn text-surface-400 hover:text-red-600 hover:bg-red-50" title="Delete">
                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    </div>

    {{-- ── Entity Configuration Card ──────────────────────────────────── --}}
    <x-ui.card
        title="Entity Configuration"
        icon="list"
        subtitle="Map data sources to destinations and define entity schemas"
        gradient="from-amber-50 to-orange-100"
        iconColor="text-amber-600"
    >
        <x-slot:actions>
            <x-ui.btn variant="danger" icon="delete" id="btn-batch-delete" onclick="batchDeleteEntities()">
                Delete Selected (<span id="batch-delete-count">0</span>)
            </x-ui.btn>
            <x-ui.btn variant="primary" icon="add" onclick="openEntityModal()">
                Add Entity
            </x-ui.btn>
        </x-slot:actions>

        <x-ui.data-table
            :columns="[
                'Entity Key',
                'Source',
                'Destination',
                'Type',
                ['label' => 'Actions', 'class' => 'text-right'],
            ]"
            id="entity-table"
            checkboxId="selectAllEntities"
            checkboxOnchange="toggleAllEntities(this)"
        >
            @foreach ($entities as $key => $e)
                <tr class="group" id="entity-row-{{ $key }}">
                    <td class="w-12">
                        <input type="checkbox" value="{{ $key }}" onchange="updateBatchDeleteBtn()" onclick="updateBatchDeleteBtn()" class="entity-checkbox ui-checkbox">
                    </td>
                    <td class="font-medium font-mono text-body-sm text-surface-800">
                        {{ $key }}
                        <div class="text-label-sm text-surface-400 font-sans mt-0.5">{{ $e['description'] }}</div>
                    </td>
                    <td>
                        <div class="font-mono text-body-sm text-surface-600 truncate max-w-[200px]" title="{{ $e['odata_entity'] }}">{{ $e['odata_entity'] }}</div>
                        <div class="text-label-sm text-surface-400 mt-0.5"><span class="material-symbols-outlined text-[12px] align-middle mr-0.5">input</span> {{ $e['source_connection'] ?? 'acumatica_default' }}</div>
                    </td>
                    <td>
                        <div class="font-mono text-body-sm text-surface-800">{{ $e['table'] }}</div>
                        <div class="text-label-sm text-surface-400 mt-0.5"><span class="material-symbols-outlined text-[12px] align-middle mr-0.5">output</span> {{ $e['destination_connection'] ?? 'database_default' }}</div>
                    </td>
                    <td>
                        <x-ui.badge :variant="$e['type'] === 'master' ? 'info' : 'violet'">
                            {{ ucfirst($e['type']) }}
                        </x-ui.badge>
                    </td>
                    <td class="text-right">
                        <div class="inline-flex items-center gap-1">
                            <button onclick="manageEntityFields('{{ $key }}')" class="ui-icon-btn text-surface-400 hover:text-brand-600" title="Manage Fields">
                                <span class="material-symbols-outlined text-[16px]">list_alt</span>
                            </button>
                            <button onclick="editEntity('{{ $key }}')" class="ui-icon-btn text-surface-400 hover:text-surface-700" title="Edit">
                                <span class="material-symbols-outlined text-[16px]">edit</span>
                            </button>
                            <button onclick="deleteEntity('{{ $key }}')" class="ui-icon-btn text-surface-400 hover:text-red-600 hover:bg-red-50" title="Delete">
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.data-table>

        <x-slot:footer>
            <div id="entity-pagination" class="px-5 py-3 border-t border-surface-100 flex justify-between items-center w-full" style="background: linear-gradient(0deg, rgba(248,250,252,0.3) 0%, transparent 100%);"></div>
        </x-slot:footer>
    </x-ui.card>

    {{-- ══════════════════════════════════════════════════════════════════ --}}
    {{--                         MODALS                                   --}}
    {{-- ══════════════════════════════════════════════════════════════════ --}}

    {{-- ── Connection Modal (Add / Edit) ──────────────────────────────── --}}
    <x-ui.modal id="connection-modal" title="Add Connection" titleId="conn-modal-title">
        <form id="connection-form" onsubmit="submitConnectionForm(event)" class="overflow-y-auto p-5 space-y-4">
            <input type="hidden" id="conn-form-mode" value="create">
            <input type="hidden" id="conn-form-original-key" value="">
            
            <x-ui.form-group label="Connection Key" :required="true" for="conn-key">
                <input type="text" id="conn-key" name="key" required placeholder="e.g. acm_prod" pattern="[A-Za-z0-9_]*" class="ui-input ui-input-mono">
            </x-ui.form-group>

            <x-ui.form-group label="Display Name" :required="true" for="conn-name">
                <input type="text" id="conn-name" name="name" required placeholder="e.g. Acumatica Production" class="ui-input">
            </x-ui.form-group>

            <x-ui.form-group label="Type" :required="true" for="conn-type">
                <x-ui.custom-select id="conn-type" name="type" :required="true" onchange="toggleConnectionFields()">
                    @foreach($providers as $type => $provider)
                        <option value="{{ $type }}">{{ $provider['display'] }}</option>
                    @endforeach
                </x-ui.custom-select>
            </x-ui.form-group>

            <!-- Dynamic Fields Container -->
            <div id="conn-dynamic-fields" class="space-y-4 pt-2 border-t border-surface-100">
                <!-- Fields will be injected here by JavaScript based on provider schema -->
            </div>

            <!-- Diagnostics Results Container -->
            <div id="conn-diagnostics-container" class="hidden mt-4 pt-4 border-t border-surface-100">
                <h4 class="ui-label font-semibold text-surface-700 mb-2">Diagnostic Results</h4>
                <div id="conn-diagnostics-content" class="text-body-sm text-surface-600 space-y-2"></div>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.btn variant="secondary" onclick="closeConnectionModal()">Cancel</x-ui.btn>
            <x-ui.btn variant="diag" icon="troubleshoot" id="conn-diag-btn" onclick="runDiagnosticsFromForm()">Diagnostics</x-ui.btn>
            <x-ui.btn variant="test" icon="science" id="conn-test-btn" onclick="testConnectionFromForm()">Test</x-ui.btn>
            <x-ui.btn variant="primary" icon="save" id="conn-submit-btn" onclick="submitConnectionForm(event)">
                <span id="conn-submit-text">Create Connection</span>
            </x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>

    {{-- ── Entity Modal (Add / Edit) ──────────────────────────────────── --}}
    <x-ui.modal id="entity-modal" title="Add Entity" titleId="modal-title">
        <form id="entity-form" onsubmit="submitEntityForm(event)" class="overflow-y-auto p-5 space-y-4">
            <input type="hidden" id="form-mode" value="create">
            <input type="hidden" id="form-original-key" value="">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.form-group label="Entity Key" :required="true" for="form-key" hint="PascalCase (e.g. CashAccount)">
                    <input type="text" id="form-key" name="key" required placeholder="e.g. CashAccount" pattern="[A-Za-z][A-Za-z0-9]*" class="ui-input ui-input-mono">
                </x-ui.form-group>
                <x-ui.form-group label="Type" :required="true" for="form-type">
                    <x-ui.custom-select id="form-type" name="type" :required="true">
                        <option value="master">Master Data</option>
                        <option value="transaction">Transaction Data</option>
                    </x-ui.custom-select>
                </x-ui.form-group>
            </div>
            
            <div class="ui-section">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.form-group label="Source Connection" :required="true" for="form-source-conn">
                        <x-ui.custom-select id="form-source-conn" :required="true" onchange="fetchSourceObjects()">
                            <option value="" disabled selected>Select Source Connection</option>
                            @foreach($connections as $k => $c)
                                @if(isset($c['is_connected']) && $c['is_connected'])
                                    <option value="{{ $k }}">{{ $c['name'] }} ({{ strtoupper($c['type']) }})</option>
                                @endif
                            @endforeach
                        </x-ui.custom-select>
                    </x-ui.form-group>
                    <div>
                        <label class="ui-label flex justify-between items-center">
                            Source Object / Entity <span class="text-red-500">*</span>
                            <button type="button" onclick="fetchSourceObjects(true)" tabindex="-1" class="text-[11px] text-brand-600 hover:text-brand-700 flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-[13px]">refresh</span> Reload
                            </button>
                        </label>
                        <div class="relative" id="form-odata-container">
                            <input type="text" id="form-odata" required placeholder="Search or type object name" autocomplete="off"
                                onfocus="document.getElementById('form-odata-dropdown').classList.remove('hidden')"
                                onblur="setTimeout(() => { document.getElementById('form-odata-dropdown').classList.add('hidden'); if(this.value) updateWatermarkOptions(this.value, true); }, 200)"
                                oninput="filterList(this, 'form-odata-list')"
                                class="ui-input ui-input-mono" style="background:#fff;">
                            
                            <div id="form-odata-dropdown" class="absolute z-20 w-full mt-1 bg-white border border-surface-200 rounded-xl shadow-lg max-h-48 overflow-y-auto hidden">
                                <ul id="form-odata-list" class="py-1"></ul>
                            </div>

                            <div id="form-odata-loading" class="absolute right-3 top-2.5 hidden bg-white pl-2">
                                <span class="material-symbols-outlined text-[20px] text-surface-400 spin">sync</span>
                            </div>
                        </div>
                        
                        <select id="form-odata-select" onchange="document.getElementById('form-odata').value = this.value; updateWatermarkOptions(this.value, true)" class="hidden ui-select ui-input-mono" style="background:#fff;">
                            <option value="" disabled selected>Select an object</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.form-group label="Destination Connection" :required="true" for="form-dest-conn">
                        <x-ui.custom-select id="form-dest-conn" :required="true">
                            <option value="" disabled selected>Select Destination Connection</option>
                            @foreach($connections as $k => $c)
                                @if(in_array($c['type'], \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes()) && isset($c['is_connected']) && $c['is_connected'])
                                    <option value="{{ $k }}">{{ $c['name'] }} ({{ strtoupper($c['type']) }})</option>
                                @endif
                            @endforeach
                        </x-ui.custom-select>
                    </x-ui.form-group>
                    <x-ui.form-group label="Target SQL Table" :required="true" for="form-table">
                        <input type="text" id="form-table" required placeholder="e.g. acm_cash_accounts" pattern="[a-z][a-z0-9_]*" class="ui-input ui-input-mono" style="background:#fff;">
                    </x-ui.form-group>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.form-group label="Menu Icon" for="form-icon">
                    <input type="text" id="form-icon" placeholder="e.g. account_balance" class="ui-input ui-input-mono">
                </x-ui.form-group>
                <x-ui.form-group label="Display Description" :required="true" for="form-desc">
                    <input type="text" id="form-desc" required placeholder="e.g. Master Cash Account" class="ui-input">
                </x-ui.form-group>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-ui.form-group label="Sync Mode" :required="true" for="form-sync-mode">
                    <x-ui.custom-select id="form-sync-mode" name="sync_mode" :required="true" onchange="toggleWatermarkFields()">
                        <option value="full">Full Sync</option>
                        <option value="incremental">Incremental Sync</option>
                    </x-ui.custom-select>
                </x-ui.form-group>
                <x-ui.form-group label="Batch Size" :required="true" for="form-batch-size">
                    <input type="number" id="form-batch-size" required min="1" max="100000" class="ui-input">
                </x-ui.form-group>
            </div>
            
            <div id="watermark-container" class="ui-watermark-section hidden">
                <x-ui.form-group label="Watermark Type" for="form-watermark-type">
                    <x-ui.custom-select id="form-watermark-type" onchange="renderWatermarkOptions()">
                        <option value="">None</option>
                        <option value="datetime">DateTime</option>
                        <option value="integer">Integer</option>
                        <option value="string">String</option>
                    </x-ui.custom-select>
                </x-ui.form-group>
                <div>
                    <label for="form-watermark-column" class="ui-label flex justify-between items-center">
                        <span>Watermark Column</span>
                        <span id="watermark-loading" class="text-[11px] text-surface-500 hidden flex items-center gap-1">
                            <span class="material-symbols-outlined text-[13px] spin">sync</span> Loading...
                        </span>
                    </label>
                    <div class="relative">
                        <input type="text" id="form-watermark-column" placeholder="e.g. LastModifiedDateTime" autocomplete="off"
                            onfocus="document.getElementById('form-watermark-dropdown').classList.remove('hidden')"
                            onblur="setTimeout(() => { document.getElementById('form-watermark-dropdown').classList.add('hidden'); }, 200)"
                            oninput="filterList(this, 'watermark-columns-list')"
                            class="ui-input" style="background:#fff;">
                        
                        <div id="form-watermark-dropdown" class="absolute z-20 w-full mt-1 bg-white border border-surface-200 rounded-xl shadow-lg max-h-48 overflow-y-auto hidden">
                            <ul id="watermark-columns-list" class="py-1"></ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="form-is-active" class="ui-checkbox">
                    <span class="text-label-md text-surface-700">Entity is Active</span>
                </label>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.btn variant="secondary" onclick="closeEntityModal()">Cancel</x-ui.btn>
            <x-ui.btn variant="primary" icon="save" id="form-submit-btn" onclick="submitEntityForm(event)">
                <span id="form-submit-text">Save Entity</span>
            </x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>

    {{-- ── Delete Confirm Modal ───────────────────────────────────────── --}}
    <x-ui.modal id="delete-modal" title="Confirm Delete" titleIcon="warning" titleIconColor="text-red-500" maxWidth="sm">
        <div class="p-5">
            <input type="hidden" id="delete-type" value="">
            <p class="text-body-md text-surface-700">
                Are you sure you want to delete <strong id="delete-name" class="text-surface-900"></strong>?
            </p>
            <p class="text-label-sm text-surface-400 mt-2">
                This action cannot be undone.
            </p>
        </div>
        <x-slot:footer>
            <x-ui.btn variant="secondary" onclick="closeDeleteModal()">Cancel</x-ui.btn>
            <x-ui.btn variant="danger-solid" icon="delete" id="delete-confirm-btn" onclick="confirmDelete()">Delete</x-ui.btn>
        </x-slot:footer>
    </x-ui.modal>

    {{-- ── Field Management Modal ─────────────────────────────────────── --}}
    <x-ui.modal id="fields-modal" title='Manage Fields: <span id="fields-modal-entity-name" class="font-mono text-brand-600 ml-1"></span>' titleIcon="list_alt" maxWidth="6xl">
        <div class="p-4 border-b border-surface-100 shrink-0 flex justify-between items-center" style="background: linear-gradient(180deg, rgba(248,250,252,0.5) 0%, transparent 100%);">
            <div class="relative w-64">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-[18px]">search</span>
                <input type="text" id="fields-search" onkeyup="filterFieldsTable()" placeholder="Search fields..." class="ui-input pl-9 pr-3 py-2" style="background:#fff;">
            </div>
            <div class="flex gap-2">
                <x-ui.btn variant="accent" icon="auto_awesome" size="sm" id="btn-suggest-fields-ai" onclick="suggestFieldsAI()">Auto-Suggest (AI)</x-ui.btn>
                <x-ui.btn variant="secondary" icon="sync" size="sm" id="btn-discover-fields" onclick="discoverFields()">Refresh Metadata</x-ui.btn>
            </div>
        </div>

        <div class="overflow-y-auto p-0 flex-1 relative min-h-[300px]">
            <div id="fields-loading" class="ui-loading-overlay hidden">
                <span class="material-symbols-outlined text-[32px] text-brand-500 spin mb-2">sync</span>
                <p class="text-body-sm text-surface-600 font-medium" id="fields-loading-text">Loading fields...</p>
            </div>
            
            <table class="ui-table" id="fields-table">
                <thead class="bg-surface-50 sticky top-0 z-10 shadow-sm shadow-surface-100/50">
                    <tr>
                        <th class="w-12 text-center">
                            <input type="checkbox" id="fields-toggle-all" onchange="toggleAllFields(this)" class="ui-checkbox">
                        </th>
                        <th class="min-w-[160px]">Source Field</th>
                        <th class="min-w-[180px]">Destination Column</th>
                        <th class="min-w-[140px]">Data Type</th>
                        <th class="min-w-[140px]">Semantic Type</th>
                        <th class="min-w-[160px]">Label</th>
                        <th class="min-w-[220px]">Description</th>
                        <th class="min-w-[160px]">Aliases</th>
                        <th class="w-24 text-center">Is Key</th>
                        <th class="w-24 text-center">Nullable</th>
                        <th class="min-w-[140px]">Transform</th>
                    </tr>
                </thead>
                <tbody id="fields-tbody" class="divide-y divide-surface-100 text-body-sm">
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <div class="flex justify-between items-center w-full">
                <div class="text-label-sm text-surface-500" id="fields-count-summary">0 fields total</div>
                <div class="flex gap-2">
                    <x-ui.btn variant="secondary" onclick="closeFieldsModal()">Cancel</x-ui.btn>
                    <x-ui.btn variant="primary" icon="save" id="fields-submit-btn" onclick="submitFieldsForm()">Save Mappings</x-ui.btn>
                </div>
            </div>
        </x-slot:footer>
    </x-ui.modal>

@endsection

@section('scripts')
    @include('settings._scripts')
@endsection
