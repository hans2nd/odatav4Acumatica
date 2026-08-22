@extends('layouts.app')

@section('title', isset($query) ? 'Edit Query' : 'Create New Query')
@section('page-title', isset($query) ? 'Edit Query' : 'Create New Query')

@section('content')
    <div class="space-y-5">

        {{-- Header --}}
        <div class="flex items-center gap-4 animate-fade-up">
            <div
                class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center shadow-lg shadow-brand-500/20">
                <span class="material-symbols-outlined text-white text-[24px]"
                    style="font-variation-settings: 'FILL' 1;">code</span>
            </div>
            <div class="flex-1">
                <h2 class="text-h2 text-surface-900">{{ isset($query) ? 'Edit Query' : 'Create New Query' }}</h2>
                <p class="text-body-md text-surface-500 mt-0.5">Build SQL queries visually, test them live, and save for reuse.</p>
            </div>
            <a href="{{ route('query-repository.index') }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white border border-surface-200 text-surface-600 text-label-md font-medium hover:bg-surface-50 hover:border-surface-300 transition-all">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Back
            </a>
        </div>

        @if ($errors->any())
            <div class="p-4 text-sm text-red-800 rounded-xl bg-red-50 border border-red-200 animate-fade-up">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="queryForm"
            action="{{ isset($query) ? route('query-repository.update', $query->id) : route('query-repository.store') }}"
            method="POST">
            @csrf
            @if (isset($query))
                @method('PUT')
            @endif
            <input type="hidden" name="ui_state_json" id="ui_state_json" value="">
            <input type="hidden" name="output_column_json" id="output_column_json" value="">
            <input type="hidden" name="sql_text" id="hidden_sql_text" value="">

            {{-- Connection Bar --}}
            <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm p-4 mt-4 mb-4 animate-fade-up">
                <div class="flex-1 mb-4">
                    <label class="text-label-md text-surface-700 mb-1.5 block">Database Connection <span
                            class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <select name="database_connection_id" id="qbConnection"
                            class="flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20"
                            required>
                            <option value="" disabled {{ old('database_connection_id', $query->database_connection_id ?? '') ? '' : 'selected' }}>Select connection...</option>
                            @foreach ($connections as $conn)
                                <option value="{{ $conn->id }}" data-key="{{ $conn->key }}" data-type="{{ $conn->type }}"
                                    {{ old('database_connection_id', $query->database_connection_id ?? '') == $conn->id ? 'selected' : '' }}>
                                    {{ $conn->name }} ({{ strtoupper($conn->type) }})
                                </option>
                            @endforeach
                        </select>
                        <button type="button" id="btnLoadSchema" onclick="fetchTables()" class="hidden px-4 py-2 bg-surface-100 text-surface-700 text-label-sm rounded-xl hover:bg-surface-200 transition-colors font-semibold">
                            Validate
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-label-md text-surface-700 mb-1.5 block">Query Name <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="name" id="queryName" value="{{ old('name', $query->name ?? '') }}"
                            class="w-full bg-white border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all"
                            required placeholder="e.g. Daily Sales Report">
                    </div>
                    <div>
                        <label class="text-label-md text-surface-700 mb-1.5 block">Category</label>
                        <input type="text" name="category" id="queryCategory" value="{{ old('category', $query->category ?? '') }}"
                            class="w-full bg-white border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all"
                            placeholder="e.g. Sales, Finance">
                    </div>
                    <div>
                        <label class="text-label-md text-surface-700 mb-1.5 block">Description</label>
                        <input type="text" name="description" id="queryDescription"
                            value="{{ old('description', $query->description ?? '') }}"
                            class="w-full bg-white border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all"
                            placeholder="Brief description...">
                    </div>
                </div>
                @if (isset($query))
                    <div class="flex items-center gap-2 mt-3">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                            {{ old('is_active', $query->is_active ?? true) ? 'checked' : '' }}
                            class="rounded border-surface-300 text-brand-600 focus:ring-brand-500">
                        <label for="is_active" class="text-label-sm text-surface-700">Active Query</label>
                        <span class="text-label-sm text-surface-400 ml-4">Version: {{ $query->version ?? 1 }}</span>
                    </div>
                @endif
            </div>

        {{-- Query Builder Layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 animate-fade-up">
            {{-- Sidebar (Tables & Columns) --}}
            <div class="lg:col-span-1 space-y-4">
                <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm flex flex-col h-[600px]">
                    <div class="px-4 pt-4 pb-3 border-b border-surface-200 bg-surface-50/50 rounded-t-2xl">
                        <label class="text-label-md text-surface-700 mb-1.5 block">Source Tables</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-[18px]">search</span>
                            <input type="text" id="qbTableSearch" placeholder="Validate connection first..." disabled
                                class="w-full rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 pl-9 py-2 transition-all">
                        </div>
                    </div>
                    
                    {{-- Table List --}}
                    <div class="flex-1 overflow-y-auto p-2" id="qbTableList">
                        <div class="text-center py-8 text-surface-400 text-body-sm">
                            Validate a connection to load schema
                        </div>
                    </div>
                </div>
            </div>

            {{-- Main Area (Builder & Preview) --}}
            <div class="lg:col-span-3 space-y-4">
                {{-- Builder Tabs --}}
                <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm">
                    <div class="flex items-center gap-1 p-2 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl">
                        <button type="button" onclick="switchQbTab('select')" id="qbTab-select"
                            class="px-4 py-2 rounded-lg text-label-sm font-medium text-brand-700 bg-brand-50 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">view_column</span> SELECT
                        </button>
                        <button type="button" onclick="switchQbTab('join')" id="qbTab-join"
                            class="px-4 py-2 rounded-lg text-label-sm font-medium text-surface-600 hover:text-surface-900 hover:bg-surface-100 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">link</span> JOIN
                        </button>
                        <button type="button" onclick="switchQbTab('where')" id="qbTab-where"
                            class="px-4 py-2 rounded-lg text-label-sm font-medium text-surface-600 hover:text-surface-900 hover:bg-surface-100 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">filter_alt</span> WHERE
                        </button>
                        <button type="button" onclick="switchQbTab('orderby')" id="qbTab-orderby"
                            class="px-4 py-2 rounded-lg text-label-sm font-medium text-surface-600 hover:text-surface-900 hover:bg-surface-100 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">sort</span> ORDER
                        </button>
                    </div>

                    <div class="p-4 h-[300px] overflow-y-auto" id="qbTabContent">
                        {{-- Select Tab --}}
                        <div id="qbPanel-select" class="space-y-3">
                            <div class="text-surface-500 text-label-sm mb-2 flex items-center justify-between">
                                <span>Selected Columns</span>
                                <button type="button" onclick="clearQbColumns()"
                                    class="text-red-500 hover:text-red-600 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[16px]">clear_all</span> Clear
                                </button>
                            </div>
                            <div id="qbSelectedColumnsArea" class="space-y-2">
                                <div class="text-surface-400 text-center py-8 border-2 border-dashed border-surface-200 rounded-xl">
                                    Drag and drop columns here from the schema list
                                </div>
                            </div>
                        </div>

                        {{-- Join Tab --}}
                        <div id="qbPanel-join" class="hidden space-y-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-surface-500 text-label-sm">Table Joins</span>
                                <button type="button" onclick="addQbJoin()"
                                    class="text-brand-600 hover:text-brand-700 flex items-center gap-1 text-label-sm font-medium">
                                    <span class="material-symbols-outlined text-[16px]">add</span> Add Join
                                </button>
                            </div>
                            <div id="qbJoinArea" class="space-y-3">
                                <div class="text-surface-400 text-center py-8 border-2 border-dashed border-surface-200 rounded-xl text-body-sm">
                                    No joins defined.
                                </div>
                            </div>
                        </div>

                        {{-- Where Tab --}}
                        <div id="qbPanel-where" class="hidden space-y-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-surface-500 text-label-sm">Conditions</span>
                                <button type="button" onclick="addWhereRow()"
                                    class="text-brand-600 hover:text-brand-700 flex items-center gap-1 text-label-sm font-medium">
                                    <span class="material-symbols-outlined text-[16px]">add</span> Add Condition
                                </button>
                            </div>
                            <div id="qbWhereArea" class="space-y-2">
                                {{-- Conditions go here --}}
                            </div>
                        </div>

                        {{-- Order By Tab --}}
                        <div id="qbPanel-orderby" class="hidden space-y-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-surface-500 text-label-sm">Sorting</span>
                                <button type="button" onclick="addOrderByRow()"
                                    class="text-brand-600 hover:text-brand-700 flex items-center gap-1 text-label-sm font-medium">
                                    <span class="material-symbols-outlined text-[16px]">add</span> Add Sort
                                </button>
                            </div>
                            <div id="qbOrderByArea" class="space-y-2"></div>
                            
                        </div>
                    </div>
                </div>

                {{-- SQL Preview & Editor --}}
                <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm relative group">
                    <div class="absolute right-4 top-3 z-10 flex items-center gap-2">
                        <button type="button" onclick="formatSql()"
                            class="px-2 py-1 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 text-xs font-medium border border-slate-700 transition-colors">
                            Format SQL
                        </button>
                        <button type="button" onclick="copySql()"
                            class="px-2 py-1 rounded bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 text-xs font-medium border border-slate-700 transition-colors">
                            Copy
                        </button>
                    </div>
                    <textarea id="qbSqlPreview" spellcheck="false" class="w-full bg-slate-900 text-slate-100 rounded-xl p-4 pt-12 text-body-sm font-mono focus:outline-none min-h-[220px] resize-y" placeholder="Generated SQL will appear here..."></textarea>
                    
                    {{-- Alert if edited manually --}}
                    <div id="qbManualEditAlert" class="hidden absolute bottom-4 left-4 right-4 px-3 py-2 bg-amber-500/10 border border-amber-500/20 rounded-lg backdrop-blur-sm flex items-start gap-2">
                        <span class="material-symbols-outlined text-amber-500 text-[18px]">warning</span>
                        <div class="text-xs text-amber-500/90 leading-tight">
                            <strong>Manual edit detected!</strong> Visual builder is now disabled for this query to prevent overwriting your custom SQL.<br>
                            <button type="button" onclick="resetBuilder()" class="underline hover:text-amber-400 mt-1">Reset to visual builder (undo manual edits)</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Dynamic Parameters --}}
        <div id="dynamicParameters" class="bg-white rounded-2xl border border-surface-200/80 shadow-sm mt-4 hidden animate-fade-up">
            <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl">
                <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px] text-amber-500">tune</span>
                    Query Parameters
                    <span id="paramCount" class="text-label-sm px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 font-medium">0</span>
                </h3>
            </div>
            <div id="parametersContainer" class="p-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center gap-4 mt-4 animate-fade-up" style="animation-delay: 0.15s;">
            <button type="button" id="btnExecute" onclick="executeQuery()" class="inline-flex items-center gap-2 px-7 py-2.5 rounded-xl bg-gradient-to-r from-brand-600 to-brand-500 text-white text-label-md font-semibold hover:from-brand-500 hover:to-brand-400 hover:shadow-lg hover:shadow-brand-500/25 hover:-translate-y-0.5 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 disabled:hover:-translate-y-0 disabled:hover:shadow-none disabled:cursor-not-allowed border border-brand-500/50" disabled>
                <span class="material-symbols-outlined text-[20px]">play_arrow</span> Test / Preview
            </button>
            <button type="button" onclick="exportQuery('xlsx')" id="btnExportXlsx" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white text-emerald-700 border border-emerald-200 text-label-md font-medium hover:bg-emerald-50 hover:border-emerald-300 hover:shadow-md hover:shadow-emerald-500/10 hover:-translate-y-0.5 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 disabled:hover:-translate-y-0 disabled:hover:shadow-none disabled:cursor-not-allowed" disabled>
                <span class="material-symbols-outlined text-[20px]">table_chart</span> Export XLSX
            </button>
            <button type="button" onclick="exportQuery('csv')" id="btnExportCsv" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white text-sky-700 border border-sky-200 text-label-md font-medium hover:bg-sky-50 hover:border-sky-300 hover:shadow-md hover:shadow-sky-500/10 hover:-translate-y-0.5 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 disabled:hover:-translate-y-0 disabled:hover:shadow-none disabled:cursor-not-allowed" disabled>
                <span class="material-symbols-outlined text-[20px]">csv</span> Export CSV
            </button>
            <div class="ml-auto flex items-center gap-3">
                <a href="{{ route('query-repository.index') }}" class="px-5 py-2.5 rounded-xl bg-white border border-surface-200 text-surface-600 text-label-md font-medium hover:bg-surface-50 hover:border-surface-300 transition-all">Cancel</a>
                <button type="button" onclick="submitForm()" id="btnSubmitSave" class="inline-flex items-center gap-2 px-7 py-2.5 rounded-xl bg-gradient-to-r from-brand-400 to-brand-500 text-black text-label-md font-semibold hover:from-brand-500 hover:to-brand-400 hover:shadow-lg hover:shadow-brand-500/25 transition-all border border-brand-500/50">
                    <span class="material-symbols-outlined text-[20px]">save</span> Save Query
                </button>
            </div>
        </div>
        </form>

        {{-- Results --}}
        <div id="qbResultsContainer" class="hidden animate-fade-up">
            <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm mb-12">
                <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl flex items-center justify-between">
                    <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px] text-emerald-500">table_rows</span> Results
                        <span id="qbResultMeta" class="text-label-sm text-surface-400 font-normal"></span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead id="qbResultHead" class="bg-surface-50 text-surface-500 text-label-sm uppercase tracking-wider"></thead>
                        <tbody id="qbResultBody" class="divide-y divide-surface-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
{{-- CodeMirror for SQL Editor --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/theme/material-ocean.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.13/mode/sql/sql.min.js"></script>
{{-- SQL Formatter --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/sql-formatter/13.0.0/sql-formatter.min.js"></script>

<style>
    .CodeMirror { height: 100% !important; border-radius: 0.75rem; background: transparent; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.875rem; }
    .CodeMirror-scroll { padding-top: 10px; }
    .cm-s-material-ocean .CodeMirror-gutters { background: transparent; border: none; }
</style>

<script>
    // csrfToken is already declared globally in app.blade.php
    
    // Core state
    let qbSchema = [];
    let activeTableIdx = null; // null means showing tables list, otherwise index of active table
    let qbSelectedColumns = []; // { table, column, alias }
    let qbJoins = []; // { type, table, on: { leftCol, rightCol } }
    let qbUsedTables = new Set();
    let isSqlManuallyEdited = false;
    let sqlEditor = null;
    let lastExecutedSql = '';
    
    // Dynamic params state
    const savedParams = @json(isset($query) && $query->parameter_json ? $query->parameter_json : []);
    let currentParams = [];
    const dynamicParamsEl = document.getElementById('dynamicParameters');
    const paramsContainer = document.getElementById('parametersContainer');
    const paramCountEl = document.getElementById('paramCount');

    // UI Elements
    const connSelect = document.getElementById('qbConnection');
    const btnExecute = document.getElementById('btnExecute');
    const btnExportXlsx = document.getElementById('btnExportXlsx');
    const btnExportCsv = document.getElementById('btnExportCsv');
    const searchInput = document.getElementById('qbTableSearch');
    
    document.addEventListener('DOMContentLoaded', () => {
        // Init CodeMirror
        sqlEditor = CodeMirror.fromTextArea(document.getElementById('qbSqlPreview'), {
            mode: 'text/x-sql',
            theme: 'material-ocean',
            lineNumbers: true,
            lineWrapping: true,
            viewportMargin: Infinity
        });

        sqlEditor.on('change', (cm, changeObj) => {
            // If user types/deletes directly (not our automated setValue)
            if (changeObj.origin !== 'setValue') {
                if (!isSqlManuallyEdited) {
                    isSqlManuallyEdited = true;
                    document.getElementById('qbManualEditAlert').classList.remove('hidden');
                }
            }
            handleSqlInput();
            updateButtons();
        });

        if (searchInput) {
            searchInput.addEventListener('input', (e) => renderSchemaList(e.target.value));
        }

        const connSelectEl = document.getElementById('qbConnection');
        if (connSelectEl) {
            connSelectEl.addEventListener('change', () => {
                updateButtons();
                fetchTables();
            });
        }
        
        // Restore existing state if editing or validation failed
        let uiStateRaw = `{!! addslashes(old('ui_state_json', isset($query) && $query->ui_state_json ? (is_string($query->ui_state_json) ? $query->ui_state_json : json_encode($query->ui_state_json)) : '')) !!}`;
        let uiState = null;
        if (uiStateRaw) {
            try { uiState = JSON.parse(uiStateRaw); } catch(e) {}
        }
        const sqlText = `{!! addslashes(old('sql_text', isset($query) ? $query->sql_text : '')) !!}`;

        if(uiState) {
            qbSelectedColumns = uiState.columns || [];
            qbJoins = uiState.joins || [];
            qbUsedTables = new Set(uiState.tables || []);
            isSqlManuallyEdited = uiState.isSqlManuallyEdited || false;

            if(uiState.filters) {
                uiState.filters.forEach(f => {
                    addWhereRow(f.field);
                    const rows = document.querySelectorAll('.qb-where-row');
                    const lastRow = rows[rows.length - 1];
                    if(lastRow) {
                        if(lastRow.querySelector('.qb-where-op')) lastRow.querySelector('.qb-where-op').value = f.operator;
                        if(lastRow.querySelector('.qb-where-val')) lastRow.querySelector('.qb-where-val').value = f.value;
                    }
                });
            }

            if(uiState.orderBy) {
                uiState.orderBy.forEach(o => {
                    addOrderByRow(o.field);
                    const rows = document.querySelectorAll('.qb-orderby-row');
                    const lastRow = rows[rows.length - 1];
                    if(lastRow) {
                        if(lastRow.querySelector('.qb-ob-dir')) lastRow.querySelector('.qb-ob-dir').value = o.direction;
                    }
                });
            }
            
            renderSelectArea();
            renderJoinArea();
        }

        if(sqlText) {
            setTimeout(() => {
                if (sqlEditor) {
                    sqlEditor.setValue(sqlText);
                }
                handleSqlInput();
                if(isSqlManuallyEdited) {
                    document.getElementById('qbManualEditAlert').classList.remove('hidden');
                }
            }, 100);
        }

        if(connSelect && connSelect.value) {
            fetchTables();
            updateButtons();
        }
    });

    function showToast(msg, type = 'success') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', timer: 3000, timerProgressBar: true, showConfirmButton: false, icon: type, title: msg });
        } else {
            alert(msg);
        }
    }

    function updateButtons() {
        const connSelect = document.getElementById('qbConnection');
        const btnExecute = document.getElementById('btnExecute');
        const btnExportXlsx = document.getElementById('btnExportXlsx');
        const btnExportCsv = document.getElementById('btnExportCsv');
        
        const hasSql = sqlEditor && sqlEditor.getValue().trim().length > 0;
        const hasConn = connSelect && connSelect.value;
        const selected = connSelect ? connSelect.options[connSelect.selectedIndex] : null;
        const isOdata = selected && selected.dataset ? selected.dataset.type === 'odata' : false;
        
        if (btnExecute) btnExecute.disabled = !(hasSql && hasConn && !isOdata);
        if (btnExportXlsx) btnExportXlsx.disabled = !lastExecutedSql;
        if (btnExportCsv) btnExportCsv.disabled = !lastExecutedSql;
    }

    // ─── Builder Logic ──────────────────────────────────────────────

    function switchQbTab(tab) {
        ['select', 'join', 'where', 'orderby'].forEach(t => {
            const btn = document.getElementById('qbTab-' + t);
            const panel = document.getElementById('qbPanel-' + t);
            if (t === tab) {
                btn.className = 'px-4 py-2 rounded-lg text-label-sm font-medium text-brand-700 bg-brand-50 transition-all flex items-center gap-2';
                panel.classList.remove('hidden');
            } else {
                btn.className = 'px-4 py-2 rounded-lg text-label-sm font-medium text-surface-600 hover:text-surface-900 hover:bg-surface-100 transition-all flex items-center gap-2';
                panel.classList.add('hidden');
            }
        });
    }

    async function fetchTables() {
        const connSelect = document.getElementById('qbConnection');
        if (!connSelect || !connSelect.value) {
            return;
        }

        // Clear state on connection change
        if (!isSqlManuallyEdited) {
            qbSelectedColumns = [];
            qbJoins = [];
            qbUsedTables = new Set();
            document.getElementById('qbWhereArea').innerHTML = '';
            document.getElementById('qbOrderByArea').innerHTML = '';
            renderSelectArea();
            renderJoinArea();
        }

        const list = document.getElementById('qbTableList');
        const btn = document.getElementById('btnLoadSchema');
        const searchInput = document.getElementById('qbTableSearch');
        
        if (searchInput) {
            searchInput.disabled = true;
            searchInput.placeholder = 'Validate connection first...';
            searchInput.value = '';
        }

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-[20px] spin">sync</span> Loading...';
        }

        list.innerHTML = '<div class="text-center py-8"><span class="material-symbols-outlined spin text-brand-500 text-[32px] mb-2">sync</span><div class="text-surface-600 font-medium">Discovering schema from ERP...</div><div class="text-surface-400 text-xs mt-1">This may take 10-30 seconds depending on the database.</div></div>';
        
        try {
            const selectedOpt = connSelect.options[connSelect.selectedIndex];
            const connKey = selectedOpt ? (selectedOpt.dataset.key || connSelect.value) : connSelect.value;
            console.log('Fetching tables for connection:', connKey);
            const res = await fetch('/api/query-builder/tables?connection=' + encodeURIComponent(connKey), {
                headers: { 'Accept': 'application/json' }
            });
            if(!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
            const data = await res.json();
            console.log('Tables response:', data);
            if(!data.success) throw new Error(data.message || 'Error fetching tables');
            
            qbSchema = (data.tables || []).map(t => ({ name: t, columns: [] }));
            
            if (searchInput) {
                searchInput.disabled = false;
                searchInput.placeholder = 'Search table...';
            }
            
            renderSchemaList();
        } catch(err) {
            console.error('fetchTables error:', err);
            list.innerHTML = `<div class="text-center py-8 text-red-500 text-body-sm">Error: ${err.message}</div>`;
            showToast('Failed to load tables: ' + err.message, 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = 'Validate';
            }
        }
    }

    function renderSchemaList(filter = '') {
        const list = document.getElementById('qbTableList');
        if (!qbSchema.length) {
            list.innerHTML = '<div class="text-center py-8 text-surface-400 text-body-sm">No tables found</div>';
            return;
        }

        const f = filter.toLowerCase();
        let html = '';
        
        if (activeTableIdx !== null) {
            // Drill-down view: showing columns for a specific table
            const tableObj = qbSchema[activeTableIdx];
            
            html += `
            <div class="sticky top-0 bg-white z-10 pb-2 mb-2 border-b border-surface-200">
                <button type="button" onclick="closeTableCols()" class="flex items-center gap-1 text-label-sm text-surface-500 hover:text-brand-600 transition-colors">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    Back to Tables
                </button>
                <div class="mt-2 text-label-md font-semibold text-brand-700 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">table</span>
                    ${tableObj.name}
                </div>
            </div>`;
            
            if (tableObj.columns.length === 0) {
                html += '<div class="py-8 text-center text-surface-400 text-label-sm"><span class="material-symbols-outlined spin text-[24px] align-middle mb-2 block">sync</span> Loading columns...</div>';
            } else {
                let colHtml = '';
                tableObj.columns.forEach(col => {
                    if (f && !(col.name || '').toLowerCase().includes(f)) return;
                    
                    colHtml += `
                    <div class="px-3 py-2 border-b border-surface-100 flex items-center justify-between hover:bg-brand-50 group transition-colors rounded-lg mb-1">
                        <div class="flex flex-col">
                            <span class="text-sm font-mono text-surface-800">${col.name}</span>
                            <span class="text-[11px] text-surface-400 font-medium">${col.type}</span>
                        </div>
                        <button type="button" onclick="addQbColumn(${activeTableIdx}, '${col.name.replace(/'/g, "\\'")}')" class="opacity-0 group-hover:opacity-100 p-1.5 text-brand-600 hover:bg-brand-100 rounded-md transition-all shadow-sm" title="Add to Select">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                        </button>
                    </div>`;
                });
                
                if (!colHtml) colHtml = '<div class="text-center py-8 text-surface-400 text-body-sm">No matching columns</div>';
                html += `<div class="flex flex-col">${colHtml}</div>`;
            }
        } else {
            // Tables list view
            qbSchema.forEach((table, index) => {
                const matchTable = (table.name || '').toLowerCase().includes(f);
                if (f && !matchTable) return;
                
                html += `
                <button type="button" onclick="openTableCols(${index})" class="w-full mb-1.5 px-3 py-2.5 bg-white border border-surface-200 rounded-lg hover:border-brand-300 hover:bg-brand-50 hover:shadow-sm flex items-center justify-between text-left transition-all group">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-surface-400 group-hover:text-brand-500 transition-colors">table</span>
                        <span class="text-body-sm font-medium text-surface-700 group-hover:text-brand-700 transition-colors">${table.name}</span>
                    </div>
                    <span class="material-symbols-outlined text-[16px] text-surface-300 group-hover:text-brand-500 transition-transform group-hover:translate-x-1">chevron_right</span>
                </button>`;
            });
            
            if (!html) html = '<div class="text-center py-8 text-surface-400 text-body-sm">No matching tables</div>';
        }
        
        list.innerHTML = html;
    }

    async function openTableCols(tableIdx) {
        activeTableIdx = tableIdx;
        const searchInput = document.getElementById('qbTableSearch');
        if (searchInput) {
            searchInput.value = ''; // clear search when diving in
            searchInput.placeholder = 'Search columns...';
        }
        
        renderSchemaList(); // Render loading state or existing columns immediately
        
        const tableObj = qbSchema[tableIdx];
        const tableName = tableObj.name;
        
        if (tableObj && tableObj.columns.length === 0) {
            try {
                const connSelect = document.getElementById('qbConnection');
                const selectedOpt = connSelect.options[connSelect.selectedIndex];
                const connKey = selectedOpt ? (selectedOpt.dataset.key || connSelect.value) : connSelect.value;
                const res = await fetch('/api/query-builder/columns?connection=' + encodeURIComponent(connKey) + '&table=' + encodeURIComponent(tableName), {
                    headers: { 'Accept': 'application/json' }
                });
                if(!res.ok) throw new Error('Network error');
                const data = await res.json();
                if (!data.success) throw new Error(data.message || 'Error fetching columns');
                
                tableObj.columns = data.columns;
                
                // Only re-render if the user hasn't backed out yet
                if (activeTableIdx === tableIdx) {
                    renderSchemaList(document.getElementById('qbTableSearch')?.value || '');
                }
            } catch (e) {
                if (activeTableIdx === tableIdx) {
                    const list = document.getElementById('qbTableList');
                    list.innerHTML += `<div class="py-4 text-center text-red-500 text-label-sm">${e.message}</div>`;
                }
            }
        }
    }

    function closeTableCols() {
        activeTableIdx = null;
        const searchInput = document.getElementById('qbTableSearch');
        if (searchInput) {
            searchInput.value = ''; // clear search when backing out
            searchInput.placeholder = 'Search table...';
        }
        renderSchemaList();
    }

    // ─── Select Area ──────────────────────────────
    function addQbColumn(tableIdx, col) {
        const table = typeof tableIdx === 'number' ? qbSchema[tableIdx].name : tableIdx; // Handle old state or direct passing if it happens
        const exists = qbSelectedColumns.find(c => c.table === table && c.column === col);
        if (!exists) {
            qbSelectedColumns.push({ table, column: col, alias: '' });
            qbUsedTables.add(table);
            renderSelectArea();
            updateSqlPreview();
        }
    }

    function removeQbColumn(index) {
        qbSelectedColumns.splice(index, 1);
        recalcUsedTables();
        renderSelectArea();
        updateSqlPreview();
    }

    function clearQbColumns() {
        qbSelectedColumns = [];
        recalcUsedTables();
        renderSelectArea();
        updateSqlPreview();
    }

    function updateColAlias(index, val) {
        if(qbSelectedColumns[index]) {
            qbSelectedColumns[index].alias = val;
            updateSqlPreview();
        }
    }

    function renderSelectArea() {
        const area = document.getElementById('qbSelectedColumnsArea');
        if (!qbSelectedColumns.length) {
            area.innerHTML = '<div class="text-surface-400 text-center py-8 border-2 border-dashed border-surface-200 rounded-xl text-body-sm">Drag and drop or click + to add columns</div>';
            return;
        }

        let html = '';
        qbSelectedColumns.forEach((c, i) => {
            html += `
                <div class="flex items-center gap-2 p-2 bg-surface-50 border border-surface-200 rounded-lg group">
                    <span class="material-symbols-outlined text-surface-400 text-[16px] cursor-move hidden">drag_indicator</span>
                    <div class="flex-1 min-w-0 flex flex-col">
                        <span class="text-xs font-mono text-surface-600 truncate" title="${c.table}.${c.column}">${c.table}.<b>${c.column}</b></span>
                    </div>
                    <input type="text" placeholder="Alias AS..." value="${c.alias || ''}" onchange="updateColAlias(${i}, this.value)" class="w-24 text-xs px-2 py-1 rounded border border-surface-300 focus:outline-none focus:border-brand-500">
                    <button type="button" onclick="removeQbColumn(${i})" class="text-surface-400 hover:text-red-500 transition-colors">
                        <span class="material-symbols-outlined text-[16px]">close</span>
                    </button>
                </div>
            `;
        });
        area.innerHTML = html;
    }

    function recalcUsedTables() {
        qbUsedTables.clear();
        qbSelectedColumns.forEach(c => qbUsedTables.add(c.table));
        qbJoins.forEach(j => { qbUsedTables.add(j.table); if(j.on.leftTable) qbUsedTables.add(j.on.leftTable); });
    }

    // ─── Joins ──────────────────────────────
    function addQbJoin() {
        qbJoins.push({ type: 'LEFT', table: '', on: { leftTable: '', leftCol: '', rightCol: '' } });
        renderJoinArea();
    }

    function removeQbJoin(index) {
        qbJoins.splice(index, 1);
        recalcUsedTables();
        renderJoinArea();
        updateSqlPreview();
    }

    function updateQbJoin(index, field, value) {
        if(field.startsWith('on.')) {
            qbJoins[index].on[field.split('.')[1]] = value;
        } else {
            qbJoins[index][field] = value;
        }
        recalcUsedTables();
        updateSqlPreview();
    }

    function renderJoinArea() {
        const area = document.getElementById('qbJoinArea');
        if (!qbJoins.length) {
            area.innerHTML = '<div class="text-surface-400 text-center py-8 border-2 border-dashed border-surface-200 rounded-xl text-body-sm">No joins defined.</div>';
            return;
        }

        const availTables = [...qbUsedTables];
        
        let html = '';
        qbJoins.forEach((j, i) => {
            html += `
                <div class="p-3 bg-surface-50 border border-surface-200 rounded-lg space-y-2 relative">
                    <button type="button" onclick="removeQbJoin(${i})" class="absolute right-2 top-2 text-surface-400 hover:text-red-500"><span class="material-symbols-outlined text-[16px]">close</span></button>
                    
                    <div class="flex items-center gap-2 pr-6">
                        <select onchange="updateQbJoin(${i}, 'type', this.value)" class="w-24 text-xs px-2 py-1 rounded border border-surface-300">
                            <option value="LEFT" ${j.type === 'LEFT' ? 'selected' : ''}>LEFT</option>
                            <option value="INNER" ${j.type === 'INNER' ? 'selected' : ''}>INNER</option>
                            <option value="RIGHT" ${j.type === 'RIGHT' ? 'selected' : ''}>RIGHT</option>
                        </select>
                        <span class="text-xs text-surface-500">JOIN</span>
                        <input type="text" placeholder="Table name" value="${j.table}" onchange="updateQbJoin(${i}, 'table', this.value)" class="flex-1 text-xs px-2 py-1 rounded border border-surface-300 font-mono">
                    </div>
                    
                    <div class="flex items-center gap-2 pl-4 border-l-2 border-surface-200">
                        <span class="text-xs text-surface-500">ON</span>
                        <input type="text" placeholder="Join table" value="${j.on.leftTable || ''}" onchange="updateQbJoin(${i}, 'on.leftTable', this.value)" class="w-24 text-xs px-2 py-1 rounded border border-surface-300 font-mono">
                        <span class="text-xs text-surface-400">.</span>
                        <input type="text" placeholder="col" value="${j.on.leftCol}" onchange="updateQbJoin(${i}, 'on.leftCol', this.value)" class="w-20 text-xs px-2 py-1 rounded border border-surface-300 font-mono">
                        <span class="text-xs font-bold">=</span>
                        <span class="text-xs text-surface-500 font-mono truncate max-w-[60px]" title="${j.table}">${j.table || '?'}</span>
                        <span class="text-xs text-surface-400">.</span>
                        <input type="text" placeholder="col" value="${j.on.rightCol}" onchange="updateQbJoin(${i}, 'on.rightCol', this.value)" class="w-20 text-xs px-2 py-1 rounded border border-surface-300 font-mono">
                    </div>
                </div>
            `;
        });
        area.innerHTML = html;
    }

    // ─── Where / Conditions ──────────────────────────────
    function addWhereRow(fieldStr = '') {
        const area = document.getElementById('qbWhereArea');
        const id = Date.now();
        const div = document.createElement('div');
        div.className = 'flex items-center gap-2 qb-where-row';
        div.innerHTML = `
            <input type="text" placeholder="table.column" value="${fieldStr}" onchange="updateSqlPreview()" class="qb-where-field w-32 flex-1 text-xs px-2 py-1.5 rounded border border-surface-300 font-mono">
            <select onchange="updateSqlPreview()" class="qb-where-op w-24 text-xs px-2 py-1.5 rounded border border-surface-300">
                <option value="=">=</option>
                <option value=">">></option>
                <option value="<"><</option>
                <option value=">=">>=</option>
                <option value="<="><=</option>
                <option value="!=">!=</option>
                <option value="LIKE">LIKE</option>
                <option value="IN">IN</option>
                <option value="IS NULL">IS NULL</option>
                <option value="IS NOT NULL">IS NOT NULL</option>
            </select>
            <input type="text" placeholder="Value or @{{param}}" oninput="updateSqlPreview()" class="qb-where-val flex-1 text-xs px-2 py-1.5 rounded border border-surface-300 font-mono">
            <button type="button" onclick="this.parentElement.remove(); updateSqlPreview()" class="text-surface-400 hover:text-red-500"><span class="material-symbols-outlined text-[18px]">delete</span></button>
        `;
        area.appendChild(div);
    }

    // ─── Order By ──────────────────────────────
    function addOrderByRow(fieldStr = '') {
        const area = document.getElementById('qbOrderByArea');
        const div = document.createElement('div');
        div.className = 'flex items-center gap-2 qb-orderby-row';
        div.innerHTML = `
            <input type="text" placeholder="table.column" value="${fieldStr}" onchange="updateSqlPreview()" class="qb-ob-field w-32 flex-1 text-xs px-2 py-1.5 rounded border border-surface-300 font-mono">
            <select onchange="updateSqlPreview()" class="qb-ob-dir w-24 text-xs px-2 py-1.5 rounded border border-surface-300">
                <option value="ASC">ASC</option>
                <option value="DESC">DESC</option>
            </select>
            <button type="button" onclick="this.parentElement.remove(); updateSqlPreview()" class="text-surface-400 hover:text-red-500"><span class="material-symbols-outlined text-[18px]">delete</span></button>
        `;
        area.appendChild(div);
    }

    // ─── SQL Generation & Parameter Parsing ──────────────────────────────
    
    function resetBuilder() {
        isSqlManuallyEdited = false;
        document.getElementById('qbManualEditAlert').classList.add('hidden');
        updateSqlPreview();
    }

    function updateSqlPreview() {
        if (isSqlManuallyEdited || !sqlEditor) {
            handleSqlInput();
            return;
        }

        let sql = '';
        
        // SELECT
        if (qbSelectedColumns.length === 0) {
            sql = "SELECT *";
        } else {
            const cols = qbSelectedColumns.map(c => {
                return c.alias ? `${c.table}.${c.column} AS ${c.alias}` : `${c.table}.${c.column}`;
            });
            sql = "SELECT " + cols.join(",\n       ");
        }

        // FROM & JOINS
        const tables = [...qbUsedTables];
        if (tables.length > 0) {
            const baseTable = tables[0];
            sql += `\nFROM ${baseTable}`;
            
            qbJoins.forEach(j => {
                if(j.table && j.on.leftCol && j.on.rightCol) {
                    const leftTbl = j.on.leftTable || baseTable;
                    sql += `\n${j.type} JOIN ${j.table} ON ${leftTbl}.${j.on.leftCol} = ${j.table}.${j.on.rightCol}`;
                }
            });
        }

        // WHERE
        const wheres = [];
        document.querySelectorAll('.qb-where-row').forEach(row => {
            const field = row.querySelector('.qb-where-field').value.trim();
            const op = row.querySelector('.qb-where-op').value;
            const val = row.querySelector('.qb-where-val').value.trim();
            
            if (field) {
                if (op === 'IS NULL' || op === 'IS NOT NULL') {
                    wheres.push(`${field} ${op}`);
                } else if (val) {
                    wheres.push(`${field} ${op} ${val}`);
                }
            }
        });

        if (wheres.length > 0) {
            sql += `\nWHERE ` + wheres.join("\n  AND ");
        }

        // ORDER BY
        const orderBys = [];
        document.querySelectorAll('.qb-orderby-row').forEach(row => {
            const field = row.querySelector('.qb-ob-field').value.trim();
            const dir = row.querySelector('.qb-ob-dir').value;
            if (field) orderBys.push(`${field} ${dir}`);
        });

        if (orderBys.length > 0) {
            sql += `\nORDER BY ` + orderBys.join(", ");
        }

        // Set value silently so it doesn't trigger manual edit flag
        sqlEditor.setValue(sql);
        handleSqlInput();
        updateButtons();
    }

    function handleSqlInput() {
        const val = sqlEditor ? sqlEditor.getValue() : '';
        const regex = /\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g;
        const matches = [...val.matchAll(regex)].map(m => m[1]);
        const unique = [...new Set(matches)];

        if (JSON.stringify(unique) !== JSON.stringify(currentParams)) {
            currentParams = unique;
            if (currentParams.length > 0 && dynamicParamsEl && paramsContainer) {
                dynamicParamsEl.classList.remove('hidden');
                if (paramCountEl) paramCountEl.textContent = currentParams.length;
                let html = '';
                const formatLabel = (str) => str.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                
                currentParams.forEach(p => {
                    let saved = savedParams.find(sp => sp.name === p);
                    if (!saved && savedParams.parameters) saved = savedParams.parameters.find(sp => sp.name === p);
                    const paramType = saved ? saved.type : 'string';
                    const inputType = paramType === 'date' ? 'date' : (paramType === 'number' ? 'number' : 'text');
                    html += `
                    <div>
                        <label class="block text-label-sm font-medium text-surface-600 mb-1">${formatLabel(p)} <span class="text-xs text-surface-400">(${paramType})</span></label>
                        <input type="${inputType}" data-param="${p}" class="query-param-input w-full bg-white border border-surface-300 rounded-lg px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:border-brand-500 transition-all" placeholder="Value...">
                    </div>`;
                });
                paramsContainer.innerHTML = html;
            } else if (dynamicParamsEl && paramsContainer) {
                dynamicParamsEl.classList.add('hidden');
                paramsContainer.innerHTML = '';
            }
        }
    }

    function formatSql() {
        if (!sqlEditor || !window.sqlFormatter) return;
        try {
            const raw = sqlEditor.getValue();
            // Temporarily replace blade tags so formatter doesn't break them
            const safeRaw = raw.replace(/@?\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, '___PARAM_$1___');
            let formatted = sqlFormatter.format(safeRaw, { language: 'sql', tabWidth: 4 });
            formatted = formatted.replace(/___PARAM_([a-zA-Z0-9_]+)___/g, '@{{ $1 }}');
            
            isSqlManuallyEdited = true;
            document.getElementById('qbManualEditAlert').classList.remove('hidden');
            sqlEditor.setValue(formatted);
            showToast('SQL Formatted');
        } catch(e) {
            showToast('Error formatting SQL', 'error');
        }
    }

    function handleSqlInput() {
        const sqlText = typeof sqlEditor !== 'undefined' && sqlEditor ? sqlEditor.getValue() : (document.getElementById('qbSqlPreview')?.value || '');
        const regex = /@\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g;
        let match;
        const params = new Set();
        while ((match = regex.exec(sqlText)) !== null) {
            params.add(match[1]);
        }

        const container = document.getElementById('parametersContainer');
        const countSpan = document.getElementById('paramCount');
        const wrapper = document.getElementById('dynamicParameters');

        if (!container || !wrapper || !countSpan) return;

        // Save existing values
        const existingInputs = container.querySelectorAll('.query-param-input');
        const values = {};
        existingInputs.forEach(input => {
            values[input.dataset.param] = input.value;
        });

        if (params.size === 0) {
            wrapper.classList.add('hidden');
            container.innerHTML = '';
            countSpan.textContent = '0';
            return;
        }

        wrapper.classList.remove('hidden');
        countSpan.textContent = params.size;

        let html = '';
        params.forEach(param => {
            const val = values[param] || '';
            html += `
            <div>
                <label class="text-label-sm text-surface-700 mb-1 block font-medium">@{{ ${param} }}</label>
                <input type="text" data-param="${param}" value="${val}" class="query-param-input w-full bg-white border border-surface-300 rounded-lg px-3 py-1.5 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all placeholder:text-surface-400" placeholder="Value...">
            </div>`;
        });
        container.innerHTML = html;
        updateButtons();
    }

    function copySql() {
        if (!sqlEditor) return;
        navigator.clipboard.writeText(sqlEditor.getValue()).then(() => showToast('SQL copied to clipboard'));
    }

    // ─── Execution ──────────────────────────────
    async function executeQuery() {
        const sql = sqlEditor ? sqlEditor.getValue().trim() : '';
        const connId = connSelect.value;
        if (!sql || !connId) return;

        btnExecute.disabled = true;
        btnExecute.innerHTML = '<span class="material-symbols-outlined text-[18px] spin">sync</span> Executing...';

        const bindings = {};
        document.querySelectorAll('.query-param-input').forEach(input => {
            bindings[input.dataset.param] = input.value;
        });

        try {
            const res = await fetch('/api/report/query-repository/preview', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ database_connection_id: connId, sql_text: sql, bindings })
            });

            const data = await res.json();
            if (data.success) {
                lastExecutedSql = sql;
                renderResults(data);
                showToast(`Executed: ${data.count} rows in ${parseFloat(data.duration).toFixed(3)}s`);
            } else {
                throw new Error(data.message || 'Query failed.');
            }
        } catch (err) {
            showToast(err.message, 'error');
            const container = document.getElementById('qbResultsContainer');
            container.classList.remove('hidden');
            document.getElementById('qbResultMeta').textContent = 'Error';
            document.getElementById('qbResultHead').innerHTML = '';
            document.getElementById('qbResultBody').innerHTML = `<tr><td class="py-8 text-center text-red-500 font-medium">${err.message}</td></tr>`;
        } finally {
            btnExecute.disabled = false;
            btnExecute.innerHTML = '<span class="material-symbols-outlined text-[20px]">play_arrow</span> Test / Preview';
            updateButtons();
        }
    }

    function renderResults(data) {
        const container = document.getElementById('qbResultsContainer');
        const thead = document.getElementById('qbResultHead');
        const tbody = document.getElementById('qbResultBody');
        const meta = document.getElementById('qbResultMeta');

        container.classList.remove('hidden');
        meta.textContent = `${data.count} rows — ${parseFloat(data.duration).toFixed(3)}s`;

        if (!data.columns || data.columns.length === 0) {
            thead.innerHTML = '';
            tbody.innerHTML = '<tr><td class="py-8 text-center text-surface-400">No columns returned</td></tr>';
            return;
        }

        let headHtml = '<tr>';
        data.columns.forEach(col => {
            headHtml += `<th class="py-3 px-4 text-left font-semibold whitespace-nowrap" data-field="${col.name}">${col.name}</th>`;
        });
        headHtml += '</tr>';
        thead.innerHTML = headHtml;

        if (!data.data || data.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${data.columns.length}" class="py-8 text-center text-surface-400">No results found</td></tr>`;
            return;
        }

        let bodyHtml = '';
        data.data.forEach((row, i) => {
            bodyHtml += `<tr class="${i % 2 === 0 ? 'bg-white' : 'bg-surface-50/50'} hover:bg-blue-50/30 transition-colors">`;
            data.columns.forEach(col => {
                const val = row[col.name];
                if (val === null || val === undefined) {
                    bodyHtml += `<td class="py-2.5 px-4 whitespace-nowrap max-w-[300px] truncate"><span class="text-surface-300 italic">NULL</span></td>`;
                } else {
                    const escaped = String(val).replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    bodyHtml += `<td class="py-2.5 px-4 text-surface-700 whitespace-nowrap max-w-[300px] truncate">${escaped}</td>`;
                }
            });
            bodyHtml += '</tr>';
        });
        tbody.innerHTML = bodyHtml;

        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function exportQuery(format) {
        if (!lastExecutedSql) return;
        const connId = connSelect.value;
        const btn = format === 'xlsx' ? btnExportXlsx : btnExportCsv;
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[18px] spin">sync</span> Exporting...';

        const bindings = {};
        document.querySelectorAll('.query-param-input').forEach(input => {
            bindings[input.dataset.param] = input.value;
        });

        try {
            const res = await fetch('/api/report/query-repository/export', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ database_connection_id: connId, sql_text: lastExecutedSql, format, bindings })
            });
            const data = await res.json();

            if (data.success) {
                window.location.href = "/api/report/query-repository/download/" + data.token;
                showToast(`Export ready: ${data.filename}`);
            } else {
                throw new Error(data.message);
            }
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        }
    }

    function submitForm() {
        const btn = document.getElementById('btnSubmitSave');
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined spin text-[16px] align-middle">sync</span> Saving...';

        const filters = [];
        document.querySelectorAll('.qb-where-row').forEach(row => {
            const field = row.querySelector('.qb-where-field')?.value;
            const op = row.querySelector('.qb-where-op')?.value;
            const val = row.querySelector('.qb-where-val')?.value;
            if (field) filters.push({ field, operator: op, value: val });
        });
        
        const orderBy = [];
        document.querySelectorAll('.qb-orderby-row').forEach(row => {
            const field = row.querySelector('.qb-ob-field')?.value;
            const dir = row.querySelector('.qb-ob-dir')?.value || 'ASC';
            if (field) orderBy.push({ field, direction: dir });
        });

        const state = {
            columns: qbSelectedColumns,
            joins: qbJoins,
            tables: [...qbUsedTables],
            filters,
            orderBy,
            isSqlManuallyEdited
        };
        
        let outColumns = [];
        const thead = document.getElementById('qbResultHead');
        if (thead) {
            thead.querySelectorAll('th').forEach(th => {
                const field = th.getAttribute('data-field') || th.textContent.trim();
                outColumns.push(field);
            });
        }

        if (outColumns.length === 0) {
            const savedCols = @json(isset($query) ? $query->output_column_json : []);
            if(savedCols && savedCols.length > 0) {
                if(typeof savedCols[0] === 'object') {
                    outColumns = savedCols.map(c => c.name || c.field);
                } else {
                    outColumns = savedCols;
                }
            } else {
                showToast('Silakan execute query terlebih dahulu untuk mendapatkan kolom-kolomnya sebelum menyimpan.', 'error');
                btn.disabled = false;
                btn.innerHTML = origText;
                return;
            }
        }

        document.getElementById('ui_state_json').value = JSON.stringify(state);
        document.getElementById('output_column_json').value = JSON.stringify(outColumns);
        
        const rawSql = sqlEditor ? sqlEditor.getValue() : '';
        document.getElementById('hidden_sql_text').value = rawSql;

        const form = document.getElementById('queryForm');
        if (!form.reportValidity()) {
            btn.disabled = false;
            btn.innerHTML = origText;
            return;
        }

        form.submit();
    }
</script>
@endsection
