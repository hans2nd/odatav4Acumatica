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
                        <div class="flex items-center gap-1.5">
                            <div class="font-mono text-body-sm text-surface-600 truncate max-w-[200px]" title="{{ $e['odata_entity'] }}">{{ $e['odata_entity'] }}</div>
                            @if(($e['source_object_type'] ?? '') === 'stored_procedure' || !empty($e['sp_execution_mode']) || preg_match('/^(dbo\.)?(usp_|sp_)/i', $e['odata_entity'] ?? ''))
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700 border border-indigo-200 shrink-0" title="SQL Server Stored Procedure">SP</span>
                            @endif
                        </div>
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
    <x-ui.modal id="entity-modal" title="Add Entity" titleId="modal-title" maxWidth="3xl">
        <form id="entity-form" onsubmit="submitEntityForm(event)" class="overflow-y-auto p-6 space-y-5">
            <input type="hidden" id="form-mode" value="create">
            <input type="hidden" id="form-original-key" value="">
            <input type="hidden" id="form-source-object-type" value="table_or_view">

            {{-- Langkah 1: Sumber & Tujuan Data --}}
            <div class="ui-section space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-brand-600">1. Sumber &amp; Tujuan Data</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.form-group label="Source Connection" :required="true" for="form-source-conn">
                        <x-ui.custom-select id="form-source-conn" :required="true" onchange="fetchSourceObjects(true)">
                            <option value="" disabled selected>Pilih Source Connection</option>
                            @foreach($connections as $k => $c)
                                @if(isset($c['is_connected']) && $c['is_connected'])
                                    @php($dbLabel = $c['credentials']['database'] ?? $c['database'] ?? null)
                                    <option value="{{ $k }}">{{ $c['name'] }} ({{ strtoupper($c['type']) }}{{ $dbLabel ? ' - DB: ' . $dbLabel : '' }})</option>
                                @endif
                            @endforeach
                        </x-ui.custom-select>
                    </x-ui.form-group>

                    <x-ui.form-group label="Destination Connection" :required="true" for="form-dest-conn">
                        <x-ui.custom-select id="form-dest-conn" :required="true">
                            <option value="" disabled selected>Pilih Destination Connection</option>
                            @foreach($connections as $k => $c)
                                @if(in_array($c['type'], \App\Services\Connections\ConnectionProviderFactory::getDatabaseTypes()) && isset($c['is_connected']) && $c['is_connected'])
                                    @php($dbLabel = $c['credentials']['database'] ?? $c['database'] ?? null)
                                    <option value="{{ $k }}">{{ $c['name'] }} ({{ strtoupper($c['type']) }}{{ $dbLabel ? ' - DB: ' . $dbLabel : '' }})</option>
                                @endif
                            @endforeach
                        </x-ui.custom-select>
                    </x-ui.form-group>
                </div>

                {{-- Source Object with Inline Compact Category Pills --}}
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                        <label class="ui-label mb-0 flex items-center gap-1.5">
                            <span id="source-object-label-text">Source Object / Entity</span>
                            <span class="text-red-500">*</span>
                            <span id="selected-object-type-pill" class="hidden px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider"></span>
                        </label>

                        <div class="flex items-center gap-2.5">
                            {{-- Compact Category Pills (Shown for SQL connections) --}}
                            <div id="sql-object-category-bar" class="hidden inline-flex items-center gap-1">
                                <button type="button" id="btn-obj-cat-all" onclick="setSqlObjectCategoryFilter('all', true)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-all bg-brand-600 text-white border-brand-600 shadow-sm">
                                    <span>Semua</span>
                                    <span id="badge-obj-all" class="px-1 rounded text-[10px] font-bold bg-white/20 text-white">0</span>
                                </button>
                                <button type="button" id="btn-obj-cat-table" onclick="setSqlObjectCategoryFilter('table', true)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-medium border transition-all bg-surface-50 text-surface-700 border-surface-200 hover:bg-surface-100">
                                    <span>Tables</span>
                                    <span id="badge-obj-table" class="px-1 rounded bg-surface-200/80 text-surface-600 text-[10px] font-bold">0</span>
                                </button>
                                <button type="button" id="btn-obj-cat-view" onclick="setSqlObjectCategoryFilter('view', true)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-medium border transition-all bg-surface-50 text-surface-700 border-surface-200 hover:bg-surface-100">
                                    <span>Views</span>
                                    <span id="badge-obj-view" class="px-1 rounded bg-surface-200/80 text-surface-600 text-[10px] font-bold">0</span>
                                </button>
                                <button type="button" id="btn-obj-cat-procedure" onclick="setSqlObjectCategoryFilter('procedure', true)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-medium border transition-all bg-surface-50 text-surface-700 border-surface-200 hover:bg-surface-100">
                                    <span>Stored Proc</span>
                                    <span id="badge-obj-procedure" class="px-1 rounded bg-surface-200/80 text-surface-600 text-[10px] font-bold">0</span>
                                </button>
                            </div>

                            <button type="button" onclick="downloadSourceObject()" id="btn-download-source" class="text-[11px] text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-0.5 hidden">
                                <span class="material-symbols-outlined text-[14px]">download</span> Download
                            </button>
                            <button type="button" onclick="fetchSourceObjects(true)" tabindex="-1" class="text-[11px] text-brand-600 hover:text-brand-700 font-semibold flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-[14px]">refresh</span> Reload
                            </button>
                        </div>
                    </div>

                    <div class="relative" id="form-odata-container">
                        <div class="relative">
                            <span id="form-odata-icon" class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-surface-400 pointer-events-none">search</span>
                            <input type="text" id="form-odata" required placeholder="Cari atau pilih Table, View, Stored Procedure, atau Endpoint..." autocomplete="off"
                                onfocus="openSourceObjectDropdown()"
                                onblur="setTimeout(() => { document.getElementById('form-odata-dropdown').classList.add('hidden'); if(this.value) updateWatermarkOptions(this.value, true); }, 220)"
                                oninput="filterSourceObjectList(this.value)"
                                class="ui-input ui-input-mono pl-9 pr-9" style="background:#fff;">
                            <button type="button" onclick="toggleSourceObjectDropdown()" tabindex="-1" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-700">
                                <span class="material-symbols-outlined text-[18px]">expand_more</span>
                            </button>
                        </div>

                        <div id="form-odata-dropdown" class="absolute z-30 w-full mt-1.5 bg-white border border-surface-200 rounded-xl shadow-xl max-h-64 overflow-y-auto overflow-x-hidden hidden">
                            <div id="form-odata-dropdown-header" class="sticky top-0 z-10 px-3.5 py-2 bg-surface-50 border-b border-surface-200 flex items-center justify-between text-[11px] text-surface-500 font-medium">
                                <span id="form-odata-count-text">Showing all objects</span>
                                <span class="text-[10px] text-surface-400">Klik untuk memilih</span>
                            </div>
                            <ul id="form-odata-list" class="divide-y divide-surface-100"></ul>
                        </div>

                        <div id="form-odata-loading" class="absolute right-8 top-2.5 hidden bg-white pl-2">
                            <span class="material-symbols-outlined text-[20px] text-brand-500 spin">sync</span>
                        </div>
                    </div>

                    <select id="form-odata-select" onchange="document.getElementById('form-odata').value = this.value; updateWatermarkOptions(this.value, true)" class="hidden ui-select ui-input-mono" style="background:#fff;">
                        <option value="" disabled selected>Select an object</option>
                    </select>

                    <div class="mt-3 hidden" id="form-sheet-container">
                        <label class="ui-label flex justify-between items-center mb-1">
                            <span class="text-surface-500">Specific Sheet Name (Opsional, untuk file Excel)</span>
                            <span id="form-sheet-loading" class="text-[11px] text-surface-500 hidden flex items-center gap-1">
                                <span class="material-symbols-outlined text-[13px] spin">sync</span> Loading...
                            </span>
                        </label>
                        <div class="relative">
                            <input type="text" id="form-sheet" placeholder="Cari atau ketik nama sheet" autocomplete="off"
                                onfocus="document.getElementById('form-sheet-dropdown').classList.remove('hidden')"
                                onblur="setTimeout(() => { document.getElementById('form-sheet-dropdown').classList.add('hidden'); }, 200)"
                                oninput="filterList(this, 'form-sheet-list')"
                                class="ui-input ui-input-mono" style="background:#fff;">

                            <div id="form-sheet-dropdown" class="absolute z-20 w-full mt-1 bg-white border border-surface-200 rounded-xl shadow-lg max-h-48 overflow-y-auto hidden">
                                <ul id="form-sheet-list" class="py-1"></ul>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Simplified Stored Procedure Configuration (Only shown when SP is selected) --}}
                <div id="sp-config-wrapper" class="hidden pt-2">
                    <div id="sp-config-fields" class="hidden rounded-xl border border-indigo-200 bg-indigo-50/40 p-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2 text-xs font-bold text-indigo-950">
                                <span class="material-symbols-outlined text-[17px] text-indigo-600">terminal</span>
                                <span>Pengaturan Stored Procedure</span>
                            </div>
                            <button type="button" onclick="applyAcumaticaStagingSpPreset()" class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-semibold transition-colors inline-flex items-center gap-1" title="Isi otomatis template usp_GetIncrementalSalesOrderSync">
                                <span class="material-symbols-outlined text-[14px]">auto_fix_high</span> Preset Otomatis
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="form-sp-mode" class="ui-label text-xs font-semibold text-surface-700">Mode Eksekusi</label>
                                <select id="form-sp-mode" onchange="toggleSpExecutionModeFields()" class="ui-input text-xs font-medium" style="background:#fff;">
                                    <option value="two_phase_delta">Two-Phase Delta (SP Ambil + SP Konfirmasi)</option>
                                    <option value="single_result_set">Standard SP (1 Result Set)</option>
                                </select>
                            </div>
                            <div>
                                <label for="form-sp-schema-object" class="ui-label text-xs font-semibold text-surface-700 flex justify-between items-center">
                                    <span>View / Tabel Referensi Skema</span>
                                    <button type="button" onclick="testLoadSpSchemaColumns()" class="text-[11px] text-indigo-600 hover:text-indigo-800 font-bold inline-flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[13px]">schema</span> Muat Kolom
                                    </button>
                                </label>
                                <input type="text" id="form-sp-schema-object" placeholder="e.g. [DB_Acumatica_Prod].dbo.vw_UsrSyncSalesOrderInvoice" class="ui-input ui-input-mono text-xs" style="background:#fff;">
                            </div>
                        </div>

                        <details class="border-t border-indigo-200/60 pt-2 group">
                            <summary class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-700 hover:text-indigo-900 cursor-pointer select-none list-none">
                                <span class="material-symbols-outlined text-[15px] transition-transform group-open:rotate-90">chevron_right</span>
                                Parameter Lanjutan SP (Otomatis)
                            </summary>
                            <div id="sp-two-phase-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2.5">
                                <div>
                                    <label for="form-sp-process-name" class="ui-label text-xs">@SyncProcessName</label>
                                    <input type="text" id="form-sp-process-name" placeholder="SO_TO_MYSQL_SYNC" class="ui-input ui-input-mono text-xs" style="background:#fff;">
                                </div>
                                <div>
                                    <label for="form-sp-company-id" class="ui-label text-xs">@CompanyID</label>
                                    <input type="number" id="form-sp-company-id" placeholder="2" min="1" class="ui-input ui-input-mono text-xs" style="background:#fff;">
                                </div>
                                <div>
                                    <label for="form-sp-completion-proc" class="ui-label text-xs">Completion SP</label>
                                    <input type="text" id="form-sp-completion-proc" placeholder="dbo.usp_CompleteIncrementalSync" class="ui-input ui-input-mono text-xs" style="background:#fff;">
                                </div>
                                <div>
                                    <label for="form-sp-delete-keys" class="ui-label text-xs">Delete Keys</label>
                                    <input type="text" id="form-sp-delete-keys" placeholder="CompanyID,OrderType,OrderNbr" class="ui-input ui-input-mono text-xs" style="background:#fff;">
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="form-sp-custom-start-date" class="ui-label text-xs">@CustomStartDate (Opsional)</label>
                                    <input type="text" id="form-sp-custom-start-date" placeholder="Kosongkan agar otomatis mengikuti SyncWatermarkControl" class="ui-input ui-input-mono text-xs" style="background:#fff;">
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
            </div>

            {{-- Langkah 2: Penamaan & Tabel Tujuan (Otomatis Terisi) --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-surface-500">2. Penamaan &amp; Metode Sinkronisasi</span>
                    <span class="text-[11px] text-surface-400">Otomatis terisi saat objek dipilih</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.form-group label="Nama Tampilan (Display Name)" :required="true" for="form-desc">
                        <input type="text" id="form-desc" required placeholder="e.g. Sales Order Invoice" oninput="this.dataset.userEdited = '1'" class="ui-input">
                    </x-ui.form-group>
                    <x-ui.form-group label="Kategori Menu (Type)" :required="true" for="form-type">
                        <x-ui.custom-select id="form-type" name="type" :required="true">
                            <option value="master">Master Data</option>
                            <option value="transaction">Transaction Data</option>
                        </x-ui.custom-select>
                    </x-ui.form-group>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.form-group label="Target SQL Table (MySQL)" :required="true" for="form-table" hint="Otomatis dibuat saat simpan/sync">
                        <input type="text" id="form-table" required placeholder="e.g. pl_sales_order_invoices" pattern="[a-z][a-z0-9_]*" oninput="this.dataset.userEdited = '1'" class="ui-input ui-input-mono">
                    </x-ui.form-group>
                    <x-ui.form-group label="Sync Mode" :required="true" for="form-sync-mode">
                        <x-ui.custom-select id="form-sync-mode" name="sync_mode" :required="true" onchange="toggleWatermarkFields()">
                            <option value="full">Full Sync</option>
                            <option value="incremental">Incremental Sync</option>
                            <option value="backdate">Append Only (Backdate Sync)</option>
                        </x-ui.custom-select>
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
            </div>

            {{-- Langkah 3: Pengaturan Tambahan (Collapsible) --}}
            <details id="entity-advanced-details" class="border-t border-surface-200/70 pt-3 group">
                <summary class="inline-flex items-center gap-1.5 text-xs font-semibold text-surface-500 hover:text-surface-800 cursor-pointer select-none list-none">
                    <span class="material-symbols-outlined text-[16px] transition-transform group-open:rotate-90">chevron_right</span>
                    Pengaturan Tambahan (Entity Key, Batch Size, Icon &amp; Status)
                </summary>

                <div class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-ui.form-group label="Entity Key" :required="true" for="form-key" hint="PascalCase (otomatis)">
                            <input type="text" id="form-key" name="key" required placeholder="e.g. SalesOrderInvoice" pattern="[A-Za-z][A-Za-z0-9]*"
                                oninput="this.dataset.userEdited = '1'"
                                oninvalid="document.getElementById('entity-advanced-details').open = true"
                                class="ui-input ui-input-mono">
                        </x-ui.form-group>
                        <x-ui.form-group label="Batch Size" :required="true" for="form-batch-size">
                            <input type="number" id="form-batch-size" required min="1" max="100000"
                                oninvalid="document.getElementById('entity-advanced-details').open = true"
                                class="ui-input">
                        </x-ui.form-group>
                        <x-ui.form-group label="Menu Icon" for="form-icon">
                            <input type="text" id="form-icon" placeholder="e.g. database" class="ui-input ui-input-mono">
                        </x-ui.form-group>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="form-is-active" class="ui-checkbox">
                            <span class="text-label-md text-surface-700">Entity is Active</span>
                        </label>
                    </div>
                </div>
            </details>
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
        {{-- Top Toolbar: Row 1 (Search, Filters, Primary Actions) + Row 2 (Active Keys & Key Actions) --}}
        <div class="p-4 border-b border-surface-100 shrink-0 space-y-2.5 bg-surface-50/70">
            <div class="flex flex-wrap items-center justify-between gap-3">
                {{-- Left: Search + Non-Wrapping Filter Pills --}}
                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="relative w-56">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-[17px]">search</span>
                        <input type="text" id="fields-search" oninput="filterFieldsTable()" placeholder="Cari field atau kolom..." class="ui-input pl-9 pr-8 py-1.5 text-xs" style="background:#fff;">
                        <button type="button" id="fields-search-clear" onclick="clearFieldsSearch()" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-surface-400 hover:text-surface-600">
                            <span class="material-symbols-outlined text-[15px]">close</span>
                        </button>
                    </div>

                    <div class="inline-flex items-center bg-surface-100 p-1 rounded-xl border border-surface-200/80 gap-1">
                        <button type="button" id="fields-filter-all" onclick="setFieldsFilterMode('all')"
                            class="fields-filter-pill whitespace-nowrap px-2.5 py-1 rounded-lg text-xs font-semibold transition-all bg-white text-surface-900 shadow-sm inline-flex items-center gap-1">
                            <span>Semua</span>
                            <span id="badge-count-all" class="text-[11px] text-surface-500">0</span>
                        </button>
                        <button type="button" id="fields-filter-keys" onclick="setFieldsFilterMode('keys')"
                            class="fields-filter-pill whitespace-nowrap px-2.5 py-1 rounded-lg text-xs font-medium transition-all text-surface-600 hover:text-surface-900 inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px] text-amber-500">vpn_key</span>
                            <span>Unique Keys</span>
                            <span id="badge-count-keys" class="px-1.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold">0</span>
                        </button>
                        <button type="button" id="fields-filter-candidates" onclick="setFieldsFilterMode('candidates')"
                            class="fields-filter-pill whitespace-nowrap px-2.5 py-1 rounded-lg text-xs font-medium transition-all text-surface-600 hover:text-surface-900 inline-flex items-center gap-1"
                            title="Tampilkan kolom berpola ID / Code / Key / Number">
                            <span class="material-symbols-outlined text-[14px] text-brand-500">bolt</span>
                            <span>Kandidat Key</span>
                            <span id="badge-count-candidates" class="text-[11px] text-surface-500">0</span>
                        </button>
                        <button type="button" id="fields-filter-enabled" onclick="setFieldsFilterMode('enabled')"
                            class="fields-filter-pill whitespace-nowrap px-2.5 py-1 rounded-lg text-xs font-medium transition-all text-surface-600 hover:text-surface-900 inline-flex items-center gap-1">
                            <span>Aktif</span>
                            <span id="badge-count-enabled" class="text-[11px] text-surface-500">0</span>
                        </button>
                    </div>
                </div>

                {{-- Right: Secondary Column Toggle, AI Suggest, Refresh Metadata --}}
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" id="btn-toggle-adv-cols" onclick="toggleAdvancedFieldCols()"
                        class="whitespace-nowrap px-2.5 py-1.5 rounded-xl border border-surface-200 bg-white text-xs font-medium text-surface-600 hover:bg-surface-100 transition-colors inline-flex items-center gap-1"
                        title="Tampilkan atau sembunyikan kolom lanjutan (Semantic Type, Description, Aliases, Transform)">
                        <span class="material-symbols-outlined text-[15px]">view_column</span>
                        <span>Kolom Lanjutan</span>
                    </button>
                    <x-ui.btn variant="accent" icon="auto_awesome" size="sm" id="btn-suggest-fields-ai" onclick="suggestFieldsAI()">Auto-Suggest (AI)</x-ui.btn>
                    <x-ui.btn variant="secondary" icon="sync" size="sm" id="btn-discover-fields" onclick="discoverFields()">Refresh Metadata</x-ui.btn>
                </div>
            </div>

            {{-- Row 2: Unified Unique Key Chips & Key Actions Bar --}}
            <div id="active-keys-bar" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 rounded-xl bg-amber-50/70 border border-amber-200/80 text-xs">
                <div class="flex flex-wrap items-center gap-1.5 flex-1 min-w-[240px]">
                    <span class="font-semibold text-amber-900 inline-flex items-center gap-1 shrink-0 mr-1">
                        <span class="material-symbols-outlined text-[15px] text-amber-600">vpn_key</span>
                        Unique Key:
                    </span>
                    <div id="active-keys-chips" class="flex flex-wrap items-center gap-1.5 flex-1">
                        <!-- Populated by JS -->
                    </div>
                </div>

                <div class="flex items-center gap-1.5 shrink-0">
                    <button type="button" onclick="autoDetectKeyFields()"
                        class="whitespace-nowrap px-2.5 py-1 rounded-lg bg-white border border-amber-200 text-xs font-semibold text-amber-800 hover:bg-amber-100/70 transition-colors inline-flex items-center gap-1"
                        title="Otomatis pilih kolom ID/Key utama sebagai Unique Key">
                        <span class="material-symbols-outlined text-[14px] text-amber-600">magic_button</span>
                        Auto-Detect Key
                    </button>
                    <button type="button" id="btn-sort-keys-first" onclick="toggleSortKeysFirst()"
                        class="whitespace-nowrap px-2.5 py-1 rounded-lg bg-white border border-surface-200 text-xs font-medium text-surface-700 hover:bg-surface-50 transition-colors inline-flex items-center gap-1"
                        title="Urutkan Unique Key di bagian paling atas tabel">
                        <span class="material-symbols-outlined text-[14px]">swap_vert</span>
                        <span>Keys di Atas</span>
                    </button>
                    <button type="button" onclick="clearAllKeyFields()"
                        class="whitespace-nowrap px-2 py-1 rounded-lg bg-white border border-surface-200 text-xs font-medium text-surface-600 hover:bg-red-50 hover:text-red-600 hover:border-red-200 transition-colors inline-flex items-center gap-1"
                        title="Hapus semua pilihan Unique Key">
                        <span class="material-symbols-outlined text-[14px]">restart_alt</span>
                        Reset Key
                    </button>
                </div>
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
                        <th class="w-12 text-center" title="Aktif / Nonaktifkan field yang tampil">
                            <input type="checkbox" id="fields-toggle-all" onchange="toggleAllFields(this)" class="ui-checkbox">
                        </th>
                        <th class="w-24 text-center bg-amber-50/60 border-x border-amber-100/80" title="Jadikan Unique Key untuk semua field yang tampil">
                            <label class="inline-flex items-center justify-center gap-1 cursor-pointer select-none text-amber-900 font-semibold whitespace-nowrap">
                                <input type="checkbox" id="fields-toggle-all-keys" onchange="toggleAllVisibleKeys(this)" class="w-3.5 h-3.5 text-amber-500 rounded border-amber-300 focus:ring-amber-500 cursor-pointer">
                                <span class="text-[11px]">Unique Key</span>
                            </label>
                        </th>
                        <th class="min-w-[180px]">Source Field</th>
                        <th class="min-w-[170px]">Destination Column</th>
                        <th class="min-w-[155px]">Data Type</th>
                        <th class="w-20 text-center" title="Toggle Nullable untuk semua field yang tampil">
                            <label class="inline-flex items-center justify-center gap-1 cursor-pointer select-none whitespace-nowrap">
                                <input type="checkbox" id="fields-toggle-all-nullable" onchange="toggleAllVisibleNullable(this)" class="ui-checkbox">
                                <span>Nullable</span>
                            </label>
                        </th>
                        <th class="min-w-[160px]">Label</th>
                        <th class="min-w-[145px] adv-field-col hidden">Semantic Type</th>
                        <th class="min-w-[190px] adv-field-col hidden">Description</th>
                        <th class="min-w-[145px] adv-field-col hidden">Aliases</th>
                        <th class="min-w-[130px] adv-field-col hidden">Transform</th>
                    </tr>
                </thead>
                <tbody id="fields-tbody" class="divide-y divide-surface-100 text-body-sm">
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <div class="flex justify-between items-center w-full">
                <div class="flex items-center gap-3">
                    <div class="text-label-sm text-surface-500" id="fields-count-summary">0 fields total</div>
                    <span class="text-surface-300">|</span>
                    <div class="text-label-sm font-medium text-amber-700" id="fields-keys-summary">0 unique keys selected</div>
                </div>
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
