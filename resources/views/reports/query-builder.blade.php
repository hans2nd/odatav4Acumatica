@extends('layouts.app')

@section('title', 'Query Builder - PL Sync')
@section('page-title', 'Query Builder')

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex items-center gap-4 animate-fade-up">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-500 to-purple-700 flex items-center justify-center shadow-lg shadow-violet-500/20">
            <span class="material-symbols-outlined text-white text-[24px]" style="font-variation-settings: 'FILL' 1;">database</span>
        </div>
        <div>
            <h2 class="text-h2 text-surface-900">Query Builder</h2>
            <p class="text-body-md text-surface-500 mt-0.5">Build SQL queries visually with drag & drop. Select tables, join them, add filters, and execute.</p>
        </div>
    </div>

    @if(empty($connections))
        <div class="p-6 rounded-2xl bg-amber-50 border border-amber-200 text-amber-700 flex items-start gap-3 animate-fade-up">
            <span class="material-symbols-outlined shrink-0 mt-0.5">warning</span>
            <div>
                <p class="font-medium text-label-md">No connected database available.</p>
                <p class="text-body-sm mt-1">Connect to at least one database in <a href="{{ route('settings.index') }}" class="underline font-semibold text-amber-800 hover:text-amber-900">Settings &gt; Connection Management</a> before using Query Builder.</p>
            </div>
        </div>
    @else

    {{-- Connection Bar --}}
    <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm p-4 flex flex-wrap items-end gap-4 animate-fade-up">
        <div class="flex-1 min-w-[200px]">
            <label class="text-label-md text-surface-700 mb-1.5 block">Database Connection</label>
            <select id="qbConnection" onchange="onQbConnectionChange()"
                class="w-full bg-white border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all cursor-pointer">
                <option value="">-- Select Connection --</option>
                @foreach ($connections as $conn)
                    <option value="{{ $conn['key'] }}">{{ $conn['name'] }} ({{ strtoupper($conn['type']) }})</option>
                @endforeach
            </select>
        </div>
        <div id="qbConnectionStatus" class="text-label-sm text-surface-400 flex items-center gap-1.5 pb-1">
            <span class="w-1.5 h-1.5 rounded-full bg-surface-300"></span> Not connected
        </div>
    </div>

    {{-- Main Layout: Top Section (3 Columns) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 animate-fade-up" style="animation-delay: 0.1s;">

        {{-- Tables List --}}
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm flex flex-col" style="height: calc(100vh - 220px); min-height: 400px;">
            <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl">
                <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px] text-violet-500">table_chart</span>
                    Tables
                </h3>
                <input type="text" id="qbTableSearch" placeholder="Search tables..."
                    oninput="filterQbTables()"
                    class="w-full mt-2 rounded-lg border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-1.5 px-3">
            </div>
            <div id="qbTableList" class="overflow-y-auto flex-1 p-2 space-y-1">
                <div class="text-center py-8 text-surface-400">
                    <span class="material-symbols-outlined text-3xl mb-1 block">dns</span>
                    <p class="text-body-sm">Select a connection to load tables</p>
                </div>
            </div>
        </div>

        {{-- Fields List --}}
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm flex flex-col" style="height: calc(100vh - 220px); min-height: 400px;">
            <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2 truncate">
                    <span class="material-symbols-outlined text-[16px] text-blue-500">view_list</span>
                    <span id="qbActiveTableName">Fields</span>
                </h3>
            </div>
            <div id="qbFieldList" class="overflow-y-auto flex-1 p-2 space-y-0.5">
                <div class="text-center py-8 text-surface-400">
                    <p class="text-body-sm">Select a table to view its fields</p>
                </div>
            </div>
        </div>

        {{-- SELECT area --}}
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm flex flex-col" style="height: calc(100vh - 220px); min-height: 400px;">
            <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl flex items-center justify-between">
                <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px] text-blue-500">view_column</span>
                    SELECT <span id="qbSelectCount" class="text-label-sm px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 font-medium">0 columns</span>
                </h3>
                <button onclick="clearAllSelected()" class="text-label-sm text-surface-400 hover:text-red-500 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">delete_sweep</span> Clear
                </button>
            </div>
            <div id="qbSelectDrop"
                class="overflow-y-auto flex-1 p-3 flex flex-col gap-2 transition-all duration-200"
                ondragover="event.preventDefault(); this.classList.add('bg-blue-50', 'border-blue-300');"
                ondragleave="this.classList.remove('bg-blue-50', 'border-blue-300');"
                ondrop="handleSelectDrop(event)">
                <div id="qbSelectPlaceholder" class="w-full text-center py-6 border-2 border-dashed border-surface-200 rounded-xl text-surface-400 my-auto mx-auto max-w-[80%]">
                    <span class="material-symbols-outlined text-2xl mb-1 block">drag_indicator</span>
                    <p class="text-body-sm">Drag fields from the left panel here</p>
                </div>
            </div>
        </div>

    </div>

    {{-- Bottom Section: Rest of Builder --}}
    <div class="space-y-4 animate-fade-up" style="animation-delay: 0.2s;">

            {{-- JOINs area --}}
            <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm">
                <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl flex items-center justify-between">
                    <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px] text-amber-500">join_inner</span>
                        JOIN <span id="qbJoinCount" class="text-label-sm px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 font-medium">0 joins</span>
                    </h3>
                    <button onclick="addJoinRow()" id="btnAddJoin" class="text-label-sm text-brand-600 hover:text-brand-700 transition-colors flex items-center gap-1 hidden">
                        <span class="material-symbols-outlined text-[14px]">add</span> Add Join
                    </button>
                </div>
                <div id="qbJoinContainer" class="p-4 space-y-2">
                    <div id="qbJoinPlaceholder" class="text-center py-4 border-2 border-dashed border-surface-200 rounded-xl text-surface-400">
                        <p class="text-body-sm">JOINs will appear automatically when you select fields from multiple tables</p>
                    </div>
                </div>
            </div>

            {{-- WHERE area --}}
            <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm">
                <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl flex items-center justify-between">
                    <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px] text-emerald-500">filter_alt</span>
                        WHERE <span class="text-body-sm text-surface-400 font-normal">(Optional)</span>
                    </h3>
                    <button onclick="addWhereRow()" class="text-label-sm text-brand-600 hover:text-brand-700 transition-colors flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">add</span> Add Filter
                    </button>
                </div>
                <div id="qbWhereContainer" class="p-4 space-y-2">
                    <div id="qbWherePlaceholder" class="text-center py-4 border-2 border-dashed border-surface-200 rounded-xl text-surface-400">
                        <p class="text-body-sm">No filters. All rows will be returned.</p>
                    </div>
                </div>
            </div>

            {{-- ORDER BY + LIMIT --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm">
                    <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl flex items-center justify-between">
                        <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-indigo-500">sort</span>
                            ORDER BY <span class="text-body-sm text-surface-400 font-normal">(Optional)</span>
                        </h3>
                        <button onclick="addOrderByRow()" class="text-label-sm text-brand-600 hover:text-brand-700 transition-colors flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">add</span> Add
                        </button>
                    </div>
                    <div id="qbOrderByContainer" class="p-4 space-y-2">
                        <div id="qbOrderByPlaceholder" class="text-center py-3 text-surface-400 text-body-sm">No sorting applied</div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm">
                    <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl">
                        <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-rose-500">123</span>
                            LIMIT
                        </h3>
                    </div>
                    <div class="p-4">
                        <input type="number" id="qbLimit" value="500" min="1" max="10000"
                            class="w-full rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2 px-3">
                        <p class="text-label-sm text-surface-400 mt-1">Max: 10,000 rows</p>
                    </div>
                </div>
            </div>

            {{-- SQL Preview --}}
            <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm">
                <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl flex items-center justify-between">
                    <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px] text-slate-500">code</span>
                        SQL Preview
                    </h3>
                    <button onclick="copySql()" class="text-label-sm text-surface-400 hover:text-surface-700 transition-colors flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">content_copy</span> Copy
                    </button>
                </div>
                <div class="p-4">
                    <pre id="qbSqlPreview" class="bg-slate-900 text-slate-100 rounded-xl p-4 text-body-sm font-mono overflow-x-auto whitespace-pre-wrap break-all min-h-[60px]">-- Your query will appear here</pre>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-wrap items-center gap-4">
                <button onclick="executeQuery()" id="btnExecute"
                    class="inline-flex items-center gap-2 px-7 py-2.5 rounded-xl bg-gradient-to-r from-brand-600 to-brand-500 text-white text-label-md font-semibold hover:from-brand-500 hover:to-brand-400 hover:shadow-lg hover:shadow-brand-500/25 hover:-translate-y-0.5 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 disabled:hover:-translate-y-0 disabled:hover:shadow-none disabled:cursor-not-allowed border border-brand-500/50" disabled>
                    <span class="material-symbols-outlined text-[20px]">play_arrow</span> Execute Query
                </button>
                <button onclick="exportQuery('xlsx')" id="btnExportXlsx"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white text-emerald-700 border border-emerald-200 text-label-md font-medium hover:bg-emerald-50 hover:border-emerald-300 hover:shadow-md hover:shadow-emerald-500/10 hover:-translate-y-0.5 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 disabled:hover:-translate-y-0 disabled:hover:shadow-none disabled:cursor-not-allowed" disabled>
                    <span class="material-symbols-outlined text-[20px]">table_chart</span> Export XLSX
                </button>
                <button onclick="exportQuery('csv')" id="btnExportCsv"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white text-sky-700 border border-sky-200 text-label-md font-medium hover:bg-sky-50 hover:border-sky-300 hover:shadow-md hover:shadow-sky-500/10 hover:-translate-y-0.5 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 disabled:hover:-translate-y-0 disabled:hover:shadow-none disabled:cursor-not-allowed" disabled>
                    <span class="material-symbols-outlined text-[20px]">csv</span> Export CSV
                </button>
            </div>

            {{-- Results --}}
            <div id="qbResultsContainer" class="hidden">
                <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm">
                    <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl flex items-center justify-between">
                        <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-emerald-500">table_rows</span>
                            Results
                            <span id="qbResultMeta" class="text-label-sm text-surface-400 font-normal"></span>
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="qbResultTable" class="w-full text-body-sm">
                            <thead id="qbResultHead" class="bg-surface-50 text-surface-500 text-label-sm uppercase tracking-wider"></thead>
                            <tbody id="qbResultBody" class="divide-y divide-surface-100"></tbody>
                        </table>
                    </div>
                </div>
            </div>

    </div>
    @endif
</div>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    let qbTables = [];
    let qbTableColumns = {}; // { tableName: [{name, type, key}] }
    let qbSelectedColumns = []; // [{table, field, alias, aggregate}]
    let qbJoins = []; // [{type, table, on_left, on_right}]
    let qbUsedTables = new Set();
    let lastExecutedSql = '';

    function showToast(msg, type = 'success') {
        Swal.fire({
            toast: true, position: 'top-end', timer: 3000, timerProgressBar: true,
            showConfirmButton: false,
            icon: type, title: msg,
        });
    }

    // ─── Connection ───────────────────────────────────────────
    async function onQbConnectionChange() {
        const connKey = document.getElementById('qbConnection').value;
        const statusEl = document.getElementById('qbConnectionStatus');
        const tableList = document.getElementById('qbTableList');

        if (!connKey) {
            statusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-surface-300"></span> Not connected';
            tableList.innerHTML = '<div class="text-center py-8 text-surface-400"><span class="material-symbols-outlined text-3xl mb-1 block">dns</span><p class="text-body-sm">Select a connection to load tables</p></div>';
            qbTables = [];
            qbTableColumns = {};
            resetQueryState();
            return;
        }

        statusEl.innerHTML = '<span class="material-symbols-outlined text-[12px] spin align-middle text-amber-500">sync</span> <span class="text-amber-500">Loading...</span>';
        tableList.innerHTML = '<div class="text-center py-8 text-surface-400"><span class="material-symbols-outlined text-3xl spin mb-1 block">sync</span><p class="text-body-sm">Loading tables...</p></div>';

        try {
            const res = await fetch(`/api/query-builder/tables?connection=${connKey}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (data.success) {
                qbTables = data.tables;
                qbTableColumns = {};
                statusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]"></span> <span class="text-emerald-600">' + qbTables.length + ' tables</span>';
                renderTableExplorer();
                resetQueryState();
            } else {
                throw new Error(data.message);
            }
        } catch (err) {
            statusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> <span class="text-red-500">Failed</span>';
            tableList.innerHTML = '<div class="text-center py-8 text-red-400"><span class="material-symbols-outlined text-3xl mb-1 block">error</span><p class="text-body-sm">' + err.message + '</p></div>';
            showToast(err.message, 'error');
        }
    }

    function resetQueryState() {
        qbSelectedColumns = [];
        qbJoins = [];
        qbUsedTables = new Set();
        lastExecutedSql = '';
        renderSelectArea();
        renderJoinArea();
        document.getElementById('qbWhereContainer').innerHTML = '<div id="qbWherePlaceholder" class="text-center py-4 border-2 border-dashed border-surface-200 rounded-xl text-surface-400"><p class="text-body-sm">No filters. All rows will be returned.</p></div>';
        document.getElementById('qbOrderByContainer').innerHTML = '<div id="qbOrderByPlaceholder" class="text-center py-3 text-surface-400 text-body-sm">No sorting applied</div>';
        document.getElementById('qbResultsContainer').classList.add('hidden');
        document.getElementById('qbLimit').value = 500;
        updateSqlPreview();
        updateButtons();
    }

    // ─── Table Explorer ───────────────────────────────────────
    let activeQbTable = null;

    function renderTableExplorer() {
        const container = document.getElementById('qbTableList');
        const fieldsEl = document.getElementById('qbFieldList');
        document.getElementById('qbActiveTableName').textContent = 'Fields';
        fieldsEl.innerHTML = '<div class="text-center py-8 text-surface-400"><p class="text-body-sm">Select a table to view its fields</p></div>';
        activeQbTable = null;

        if (qbTables.length === 0) {
            container.innerHTML = '<div class="text-center py-8 text-surface-400"><p class="text-body-sm">No tables found</p></div>';
            return;
        }

        let html = '';
        for (const table of qbTables) {
            html += `
            <div class="qb-table-item" data-table="${table}">
                <button onclick="selectQbTable('${table}')" id="qb-btn-table-${table}" class="qb-table-btn w-full flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-surface-100 text-left transition-all group">
                    <span class="material-symbols-outlined text-[14px] text-violet-400 group-hover:text-violet-600 transition-colors" style="font-variation-settings: 'FILL' 1;">table_chart</span>
                    <span class="text-body-sm text-surface-700 font-medium truncate flex-1">${table}</span>
                </button>
            </div>`;
        }
        container.innerHTML = html;
    }

    async function selectQbTable(table) {
        activeQbTable = table;
        
        // Update active state in UI
        document.querySelectorAll('.qb-table-btn').forEach(btn => {
            btn.classList.remove('bg-violet-50', 'text-violet-800', 'ring-1', 'ring-violet-300');
        });
        const btn = document.getElementById('qb-btn-table-' + table);
        if (btn) btn.classList.add('bg-violet-50', 'text-violet-800', 'ring-1', 'ring-violet-300');

        document.getElementById('qbActiveTableName').textContent = table;
        const fieldsEl = document.getElementById('qbFieldList');

        if (qbTableColumns[table]) {
            renderTableFields(table);
            return;
        }

        fieldsEl.innerHTML = '<div class="py-8 text-center text-surface-400"><span class="material-symbols-outlined text-2xl spin mb-1 block">sync</span><p class="text-label-sm">Loading fields...</p></div>';

        try {
            const connKey = document.getElementById('qbConnection').value;
            const res = await fetch(`/api/query-builder/columns?connection=${connKey}&table=${table}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (data.success) {
                qbTableColumns[table] = data.columns;
                getAllFieldOptions();
                if (activeQbTable === table) renderTableFields(table);
            } else {
                if (activeQbTable === table) fieldsEl.innerHTML = '<div class="py-4 text-center text-red-400 text-label-sm">Failed to load</div>';
            }
        } catch (err) {
            if (activeQbTable === table) fieldsEl.innerHTML = '<div class="py-4 text-center text-red-400 text-label-sm">' + err.message + '</div>';
        }
    }

    function renderTableFields(table) {
        if (activeQbTable !== table) return; // Ignore if user navigated away
        
        const fieldsEl = document.getElementById('qbFieldList');
        const columns = qbTableColumns[table] || [];

        if (columns.length === 0) {
            fieldsEl.innerHTML = '<div class="py-4 text-center text-surface-400 text-label-sm">No fields found</div>';
            return;
        }

        let html = '';
        for (const col of columns) {
            const isKey = col.key === 'PRI';
            const isSelected = qbSelectedColumns.some(c => c.table === table && c.field === col.name);
            const typeColor = getTypeColor(col.type);

            html += `
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg cursor-grab hover:bg-violet-50 transition-colors group ${isSelected ? 'bg-blue-50 ring-1 ring-blue-300' : ''}"
                 draggable="true"
                 ondragstart="onFieldDragStart(event, '${table}', '${col.name}')"
                 ondblclick="toggleField('${table}', '${col.name}')">
                ${isKey ? '<span class="material-symbols-outlined text-[14px] text-amber-500" style="font-variation-settings: \'FILL\' 1;" title="Primary Key">key</span>' : '<span class="w-[14px]"></span>'}
                <span class="text-label-sm text-surface-700 truncate flex-1 select-none font-medium ${isSelected ? 'text-blue-800' : ''}">${col.name}</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded ${typeColor} font-mono uppercase">${col.type.split('(')[0]}</span>
                ${isSelected ? '<span class="material-symbols-outlined text-[16px] text-blue-600">check_circle</span>' : ''}
            </div>`;
        }
        fieldsEl.innerHTML = html;
    }

    function getTypeColor(type) {
        type = type.toLowerCase();
        if (type.includes('int') || type.includes('decimal') || type.includes('float') || type.includes('double') || type.includes('numeric') || type.includes('money')) return 'bg-blue-50 text-blue-600';
        if (type.includes('varchar') || type.includes('text') || type.includes('char') || type.includes('nvarchar')) return 'bg-emerald-50 text-emerald-600';
        if (type.includes('date') || type.includes('time')) return 'bg-orange-50 text-orange-600';
        if (type.includes('bit') || type.includes('bool')) return 'bg-rose-50 text-rose-600';
        return 'bg-surface-100 text-surface-500';
    }

    function filterQbTables() {
        const search = document.getElementById('qbTableSearch').value.toLowerCase();
        document.querySelectorAll('.qb-table-item').forEach(el => {
            el.style.display = el.dataset.table.toLowerCase().includes(search) ? '' : 'none';
        });
    }

    // ─── Drag & Drop ──────────────────────────────────────────
    let draggedChipIndex = null;

    function onFieldDragStart(event, table, field) {
        event.dataTransfer.setData('text/plain', JSON.stringify({ type: 'addField', table, field }));
        event.dataTransfer.effectAllowed = 'copy';
    }

    function onChipDragStart(event, idx) {
        draggedChipIndex = idx;
        event.dataTransfer.setData('text/plain', JSON.stringify({ type: 'reorder', idx }));
        event.dataTransfer.effectAllowed = 'move';
        setTimeout(() => event.target.classList.add('opacity-40'), 0);
    }
    
    function onChipDragEnd(event) {
        event.target.classList.remove('opacity-40');
        draggedChipIndex = null;
    }

    function onChipDragOver(event, idx) {
        event.preventDefault(); // Necessary to allow drop
    }

    function onChipDrop(event, idx) {
        event.preventDefault();
        event.stopPropagation(); // Prevent dropping on container
        try {
            const data = JSON.parse(event.dataTransfer.getData('text/plain'));
            if (data.type === 'reorder' && data.idx !== idx) {
                const item = qbSelectedColumns.splice(data.idx, 1)[0];
                qbSelectedColumns.splice(idx, 0, item);
                renderSelectArea();
                updateSqlPreview();
            } else if (data.type === 'addField') {
                if (!qbSelectedColumns.some(c => c.table === data.table && c.field === data.field)) {
                    qbSelectedColumns.splice(idx, 0, { table: data.table, field: data.field, alias: null, aggregate: null });
                    syncUsedTables();
                    renderSelectArea();
                    renderJoinArea();
                    updateSqlPreview();
                    updateButtons();
                    if (qbTableColumns[data.table]) renderTableFields(data.table);
                }
            }
        } catch (e) {}
    }

    function handleSelectDrop(event) {
        event.preventDefault();
        event.currentTarget.classList.remove('bg-blue-50', 'border-blue-300');

        try {
            const data = JSON.parse(event.dataTransfer.getData('text/plain'));
            if (data.type === 'addField') {
                addSelectedColumn(data.table, data.field);
            } else if (data.type === 'reorder') {
                const item = qbSelectedColumns.splice(data.idx, 1)[0];
                qbSelectedColumns.push(item);
                renderSelectArea();
                updateSqlPreview();
            }
        } catch (e) {}
    }

    function toggleField(table, field) {
        const idx = qbSelectedColumns.findIndex(c => c.table === table && c.field === field);
        if (idx >= 0) {
            qbSelectedColumns.splice(idx, 1);
        } else {
            qbSelectedColumns.push({ table, field, alias: null, aggregate: null });
        }
        syncUsedTables();
        renderSelectArea();
        renderJoinArea();
        updateSqlPreview();
        updateButtons();
        renderTableFields(table);
    }

    function addSelectedColumn(table, field) {
        if (qbSelectedColumns.some(c => c.table === table && c.field === field)) return;
        qbSelectedColumns.push({ table, field, alias: null, aggregate: null });
        syncUsedTables();
        renderSelectArea();
        renderJoinArea();
        updateSqlPreview();
        updateButtons();
        if (qbTableColumns[table]) renderTableFields(table);
    }

    function removeSelectedColumn(idx) {
        const removed = qbSelectedColumns.splice(idx, 1)[0];
        syncUsedTables();
        renderSelectArea();
        renderJoinArea();
        updateSqlPreview();
        updateButtons();
        if (qbTableColumns[removed.table]) renderTableFields(removed.table);
    }

    function clearAllSelected() {
        const tables = [...new Set(qbSelectedColumns.map(c => c.table))];
        qbSelectedColumns = [];
        syncUsedTables();
        renderSelectArea();
        renderJoinArea();
        updateSqlPreview();
        updateButtons();
        tables.forEach(t => { if (qbTableColumns[t]) renderTableFields(t); });
    }

    function syncUsedTables() {
        qbUsedTables = new Set(qbSelectedColumns.map(c => c.table));
        // Remove joins for tables no longer used
        qbJoins = qbJoins.filter(j => qbUsedTables.has(j.table));
    }

    function setAggregate(idx, agg) {
        qbSelectedColumns[idx].aggregate = agg || null;
        renderSelectArea();
        updateSqlPreview();
    }

    // ─── Render SELECT Area ──────────────────────────────────
    function renderSelectArea() {
        const container = document.getElementById('qbSelectDrop');
        const placeholder = document.getElementById('qbSelectPlaceholder');
        const countBadge = document.getElementById('qbSelectCount');

        countBadge.textContent = qbSelectedColumns.length + ' columns';

        if (qbSelectedColumns.length === 0) {
            // Show placeholder, remove chips
            container.querySelectorAll('.qb-chip').forEach(el => el.remove());
            if (!placeholder) {
                container.innerHTML = '<div id="qbSelectPlaceholder" class="w-full text-center py-6 border-2 border-dashed border-surface-200 rounded-xl text-surface-400"><span class="material-symbols-outlined text-2xl mb-1 block">drag_indicator</span><p class="text-body-sm">Drag fields from the left panel here</p></div>';
            } else {
                placeholder.classList.remove('hidden');
            }
            return;
        }

        let html = '';
        qbSelectedColumns.forEach((col, i) => {
            const aggLabel = col.aggregate ? `${col.aggregate.toUpperCase()}(` : '';
            const aggEnd = col.aggregate ? ')' : '';
            html += `
            <div class="qb-chip flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-label-sm group hover:border-blue-300 transition-all w-full shadow-sm cursor-move"
                 draggable="true"
                 ondragstart="onChipDragStart(event, ${i})"
                 ondragend="onChipDragEnd(event)"
                 ondragover="onChipDragOver(event, ${i})"
                 ondrop="onChipDrop(event, ${i})">
                <select onchange="setAggregate(${i}, this.value)" class="bg-transparent border-none text-[11px] text-blue-600 font-bold p-0 pr-1 focus:ring-0 cursor-pointer uppercase" style="width: auto;">
                    <option value="" ${!col.aggregate ? 'selected' : ''}>—</option>
                    <option value="count" ${col.aggregate === 'count' ? 'selected' : ''}>COUNT</option>
                    <option value="sum" ${col.aggregate === 'sum' ? 'selected' : ''}>SUM</option>
                    <option value="avg" ${col.aggregate === 'avg' ? 'selected' : ''}>AVG</option>
                    <option value="min" ${col.aggregate === 'min' ? 'selected' : ''}>MIN</option>
                    <option value="max" ${col.aggregate === 'max' ? 'selected' : ''}>MAX</option>
                </select>
                <div class="flex-1 truncate">
                    <span class="text-blue-500 font-medium">${col.table}.</span><span class="font-bold">${col.field}</span>
                </div>
                <button onclick="removeSelectedColumn(${i})" class="ml-1 text-blue-400 hover:text-red-500 transition-colors flex-shrink-0">
                    <span class="material-symbols-outlined text-[16px]">close</span>
                </button>
            </div>`;
        });
        container.innerHTML = html;
    }

    // ─── JOINs ────────────────────────────────────────────────
    function guessJoinCondition(tableA, tableB) {
        const colsA = (qbTableColumns[tableA] || []).map(c => c.name.toLowerCase());
        const colsB = (qbTableColumns[tableB] || []).map(c => c.name.toLowerCase());
        
        // Priority 1: tableB_id in tableA = id in tableB
        const bId = tableB.toLowerCase() + '_id';
        if (colsA.includes(bId) && colsB.includes('id')) {
            const exactBId = (qbTableColumns[tableA] || []).find(c => c.name.toLowerCase() === bId)?.name || bId;
            const exactId = (qbTableColumns[tableB] || []).find(c => c.name.toLowerCase() === 'id')?.name || 'id';
            return { left: tableA + '.' + exactBId, right: tableB + '.' + exactId };
        }
        
        // Priority 2: tableA_id in tableB = id in tableA
        const aId = tableA.toLowerCase() + '_id';
        if (colsB.includes(aId) && colsA.includes('id')) {
            const exactAId = (qbTableColumns[tableB] || []).find(c => c.name.toLowerCase() === aId)?.name || aId;
            const exactId = (qbTableColumns[tableA] || []).find(c => c.name.toLowerCase() === 'id')?.name || 'id';
            return { left: tableA + '.' + exactId, right: tableB + '.' + exactAId };
        }
        
        // Priority 3: any matching field name except common timestamps
        const ignored = ['created_at', 'updated_at', 'deleted_at', 'id'];
        for (const col of (qbTableColumns[tableA] || [])) {
            if (!ignored.includes(col.name.toLowerCase()) && colsB.includes(col.name.toLowerCase())) {
                const exactRight = (qbTableColumns[tableB] || []).find(c => c.name.toLowerCase() === col.name.toLowerCase())?.name || col.name;
                return { left: tableA + '.' + col.name, right: tableB + '.' + exactRight };
            }
        }
        
        // Fallback
        return { left: tableA + '.', right: tableB + '.' };
    }

    function renderJoinArea() {
        const container = document.getElementById('qbJoinContainer');
        const placeholder = document.getElementById('qbJoinPlaceholder');
        const countBadge = document.getElementById('qbJoinCount');
        const btnAdd = document.getElementById('btnAddJoin');
        const tables = [...qbUsedTables];

        if (tables.length <= 1) {
            container.innerHTML = '<div id="qbJoinPlaceholder" class="text-center py-4 border-2 border-dashed border-surface-200 rounded-xl text-surface-400"><p class="text-body-sm">JOINs will appear automatically when you select fields from multiple tables</p></div>';
            qbJoins = [];
            countBadge.textContent = '0 joins';
            btnAdd.classList.add('hidden');
            return;
        }

        btnAdd.classList.remove('hidden');

        // Auto-create joins for newly added tables
        const primaryTable = tables[0];
        for (let i = 1; i < tables.length; i++) {
            const t = tables[i];
            if (!qbJoins.some(j => j.table === t)) {
                const guessed = guessJoinCondition(primaryTable, t);
                qbJoins.push({ type: 'INNER', table: t, on_left: guessed.left, on_right: guessed.right });
            }
        }

        countBadge.textContent = qbJoins.length + ' joins';
        let html = '';
        qbJoins.forEach((join, i) => {
            html += buildJoinRowHtml(join, i, tables);
        });
        container.innerHTML = html;
    }

    function buildJoinRowHtml(join, idx, tables) {
        const allFields = getAllFieldOptions();
        return `
        <div class="flex flex-wrap items-center gap-2 p-3 bg-amber-50/50 rounded-xl border border-amber-100">
            <select onchange="updateJoin(${idx}, 'type', this.value)" class="rounded-lg border-amber-200 bg-white text-label-sm py-1.5 px-2 focus:ring-amber-300">
                <option value="INNER" ${join.type==='INNER'?'selected':''}>INNER JOIN</option>
                <option value="LEFT" ${join.type==='LEFT'?'selected':''}>LEFT JOIN</option>
                <option value="RIGHT" ${join.type==='RIGHT'?'selected':''}>RIGHT JOIN</option>
            </select>
            <span class="text-label-sm text-surface-500 font-semibold">${join.table}</span>
            <span class="text-label-sm text-surface-400">ON</span>
            <input type="text" value="${join.on_left}" onchange="updateJoin(${idx}, 'on_left', this.value)"
                placeholder="table.field" list="qb-fields-list"
                class="rounded-lg border-surface-200 bg-white text-label-sm py-1.5 px-2 w-[180px] focus:ring-brand-300">
            <span class="text-label-sm text-surface-400">=</span>
            <input type="text" value="${join.on_right}" onchange="updateJoin(${idx}, 'on_right', this.value)"
                placeholder="table.field" list="qb-fields-list"
                class="rounded-lg border-surface-200 bg-white text-label-sm py-1.5 px-2 w-[180px] focus:ring-brand-300">
            <button onclick="removeJoin(${idx})" class="text-surface-400 hover:text-red-500 transition-colors ml-auto">
                <span class="material-symbols-outlined text-[16px]">delete</span>
            </button>
        </div>`;
    }

    function addJoinRow() {
        const tables = [...qbUsedTables];
        if (tables.length < 2) return;
        const guessed = guessJoinCondition(tables[0], tables[tables.length-1]);
        qbJoins.push({ type: 'INNER', table: tables[tables.length-1], on_left: guessed.left, on_right: guessed.right });
        renderJoinArea();
        updateSqlPreview();
    }

    function updateJoin(idx, key, value) {
        qbJoins[idx][key] = value;
        updateSqlPreview();
    }

    function removeJoin(idx) {
        qbJoins.splice(idx, 1);
        renderJoinArea();
        updateSqlPreview();
    }

    function getAllFieldOptions() {
        let options = [];
        let dataListHtml = '';
        for (const [table, cols] of Object.entries(qbTableColumns)) {
            for (const col of cols) {
                const val = table + '.' + col.name;
                options.push(val);
                dataListHtml += `<option value="${val}">`;
            }
        }
        
        let dl = document.getElementById('qb-fields-list');
        if (!dl) {
            dl = document.createElement('datalist');
            dl.id = 'qb-fields-list';
            document.body.appendChild(dl);
        }
        dl.innerHTML = dataListHtml;
        
        return options;
    }

    // ─── WHERE ────────────────────────────────────────────────
    function addWhereRow() {
        const container = document.getElementById('qbWhereContainer');
        const placeholder = document.getElementById('qbWherePlaceholder');
        if (placeholder) placeholder.remove();

        const idx = container.querySelectorAll('.qb-where-row').length;
        const fields = getAllFieldOptions();
        const fieldOpts = fields.map(f => `<option value="${f}">${f}</option>`).join('');

        const div = document.createElement('div');
        div.className = 'qb-where-row flex flex-wrap items-center gap-2 p-3 bg-emerald-50/50 rounded-xl border border-emerald-100';
        div.innerHTML = `
            <select class="qb-where-field rounded-lg border-surface-200 bg-white text-label-sm py-1.5 px-2 w-[180px] focus:ring-brand-300" onchange="updateSqlPreview()">
                <option value="">-- Field --</option>
                ${fieldOpts}
            </select>
            <select class="qb-where-op rounded-lg border-surface-200 bg-white text-label-sm py-1.5 px-2 focus:ring-brand-300" onchange="updateSqlPreview()">
                <option value="eq">=</option>
                <option value="ne">!=</option>
                <option value="gt">&gt;</option>
                <option value="ge">&gt;=</option>
                <option value="lt">&lt;</option>
                <option value="le">&lt;=</option>
                <option value="contains">LIKE %..%</option>
                <option value="startswith">LIKE ..%</option>
                <option value="endswith">LIKE %..</option>
                <option value="is_null">IS NULL</option>
                <option value="not_null">IS NOT NULL</option>
            </select>
            <input type="text" class="qb-where-val rounded-lg border-surface-200 bg-white text-label-sm py-1.5 px-2 flex-1 min-w-[120px] focus:ring-brand-300" placeholder="Value..." oninput="updateSqlPreview()">
            <button onclick="this.closest('.qb-where-row').remove(); updateSqlPreview(); checkWherePlaceholder();" class="text-surface-400 hover:text-red-500 transition-colors">
                <span class="material-symbols-outlined text-[16px]">delete</span>
            </button>`;
        container.appendChild(div);
        updateSqlPreview();
    }

    function checkWherePlaceholder() {
        const container = document.getElementById('qbWhereContainer');
        if (container.querySelectorAll('.qb-where-row').length === 0) {
            container.innerHTML = '<div id="qbWherePlaceholder" class="text-center py-4 border-2 border-dashed border-surface-200 rounded-xl text-surface-400"><p class="text-body-sm">No filters. All rows will be returned.</p></div>';
        }
    }

    // ─── ORDER BY ─────────────────────────────────────────────
    function addOrderByRow() {
        const container = document.getElementById('qbOrderByContainer');
        const placeholder = document.getElementById('qbOrderByPlaceholder');
        if (placeholder) placeholder.remove();

        const fields = getAllFieldOptions();
        const fieldOpts = fields.map(f => `<option value="${f}">${f}</option>`).join('');

        const div = document.createElement('div');
        div.className = 'qb-orderby-row flex items-center gap-2';
        div.innerHTML = `
            <select class="qb-ob-field rounded-lg border-surface-200 bg-white text-label-sm py-1.5 px-2 flex-1 focus:ring-brand-300" onchange="updateSqlPreview()">
                <option value="">-- Field --</option>
                ${fieldOpts}
            </select>
            <select class="qb-ob-dir rounded-lg border-surface-200 bg-white text-label-sm py-1.5 px-2 focus:ring-brand-300" onchange="updateSqlPreview()">
                <option value="ASC">ASC</option>
                <option value="DESC">DESC</option>
            </select>
            <button onclick="this.closest('.qb-orderby-row').remove(); updateSqlPreview(); checkOrderByPlaceholder();" class="text-surface-400 hover:text-red-500 transition-colors">
                <span class="material-symbols-outlined text-[16px]">delete</span>
            </button>`;
        container.appendChild(div);
        updateSqlPreview();
    }

    function checkOrderByPlaceholder() {
        const container = document.getElementById('qbOrderByContainer');
        if (container.querySelectorAll('.qb-orderby-row').length === 0) {
            container.innerHTML = '<div id="qbOrderByPlaceholder" class="text-center py-3 text-surface-400 text-body-sm">No sorting applied</div>';
        }
    }

    // ─── SQL Preview ──────────────────────────────────────────
    function updateSqlPreview() {
        const previewEl = document.getElementById('qbSqlPreview');

        if (qbSelectedColumns.length === 0) {
            previewEl.textContent = '-- Your query will appear here';
            return;
        }

        const limit = parseInt(document.getElementById('qbLimit').value) || 500;

        // SELECT
        let selectParts = qbSelectedColumns.map(col => {
            let ref = `${col.table}.${col.field}`;
            if (col.aggregate) {
                ref = `${col.aggregate.toUpperCase()}(${ref})`;
            }
            return ref;
        });

        let sql = 'SELECT ' + selectParts.join(',\n       ');

        // FROM
        const tables = [...qbUsedTables];
        sql += '\nFROM ' + tables[0];

        // JOINs
        for (const join of qbJoins) {
            if (join.on_left && join.on_right && join.on_left.includes('.') && join.on_right.includes('.')) {
                sql += `\n${join.type} JOIN ${join.table} ON ${join.on_left} = ${join.on_right}`;
            }
        }

        // WHERE
        const whereRows = document.querySelectorAll('.qb-where-row');
        let whereAdded = false;
        whereRows.forEach((row, i) => {
            const field = row.querySelector('.qb-where-field')?.value;
            const op = row.querySelector('.qb-where-op')?.value;
            const val = row.querySelector('.qb-where-val')?.value;
            if (!field) return;

            const prefix = !whereAdded ? '\nWHERE ' : '\n  AND ';
            whereAdded = true;

            switch (op) {
                case 'eq': sql += prefix + field + " = '" + val + "'"; break;
                case 'ne': sql += prefix + field + " != '" + val + "'"; break;
                case 'gt': sql += prefix + field + " > '" + val + "'"; break;
                case 'ge': sql += prefix + field + " >= '" + val + "'"; break;
                case 'lt': sql += prefix + field + " < '" + val + "'"; break;
                case 'le': sql += prefix + field + " <= '" + val + "'"; break;
                case 'contains': sql += prefix + field + " LIKE '%" + val + "%'"; break;
                case 'startswith': sql += prefix + field + " LIKE '" + val + "%'"; break;
                case 'endswith': sql += prefix + field + " LIKE '%" + val + "'"; break;
                case 'is_null': sql += prefix + field + " IS NULL"; break;
                case 'not_null': sql += prefix + field + " IS NOT NULL"; break;
            }
        });

        // GROUP BY (if any aggregate is used)
        const hasAgg = qbSelectedColumns.some(c => c.aggregate);
        if (hasAgg) {
            const nonAgg = qbSelectedColumns.filter(c => !c.aggregate).map(c => `${c.table}.${c.field}`);
            if (nonAgg.length > 0) {
                sql += '\nGROUP BY ' + nonAgg.join(', ');
            }
        }

        // ORDER BY
        const obRows = document.querySelectorAll('.qb-orderby-row');
        let obAdded = false;
        obRows.forEach(row => {
            const field = row.querySelector('.qb-ob-field')?.value;
            const dir = row.querySelector('.qb-ob-dir')?.value || 'ASC';
            if (!field) return;
            sql += (!obAdded ? '\nORDER BY ' : ', ') + field + ' ' + dir;
            obAdded = true;
        });

        sql += '\nLIMIT ' + limit;

        previewEl.textContent = sql;
    }

    function copySql() {
        const text = document.getElementById('qbSqlPreview').textContent;
        navigator.clipboard.writeText(text).then(() => showToast('SQL copied!'));
    }

    function updateButtons() {
        const hasColumns = qbSelectedColumns.length > 0;
        const hasConnection = !!document.getElementById('qbConnection').value;
        const enabled = hasColumns && hasConnection;

        document.getElementById('btnExecute').disabled = !enabled;
        document.getElementById('btnExportXlsx').disabled = !enabled;
        document.getElementById('btnExportCsv').disabled = !enabled;
    }

    // ─── Execute ──────────────────────────────────────────────
    async function executeQuery() {
        const connKey = document.getElementById('qbConnection').value;
        if (!connKey || qbSelectedColumns.length === 0) return;

        const btn = document.getElementById('btnExecute');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[18px] spin">sync</span> Executing...';

        // Gather WHERE filters
        const filters = [];
        document.querySelectorAll('.qb-where-row').forEach(row => {
            const field = row.querySelector('.qb-where-field')?.value;
            const op = row.querySelector('.qb-where-op')?.value;
            const val = row.querySelector('.qb-where-val')?.value;
            if (field) filters.push({ field, operator: op, value: val });
        });

        // Gather ORDER BY
        const orderBy = [];
        document.querySelectorAll('.qb-orderby-row').forEach(row => {
            const field = row.querySelector('.qb-ob-field')?.value;
            const dir = row.querySelector('.qb-ob-dir')?.value || 'ASC';
            if (field) orderBy.push({ field, direction: dir });
        });

        // GROUP BY (auto from aggregates)
        const groupBy = [];
        const hasAgg = qbSelectedColumns.some(c => c.aggregate);
        if (hasAgg) {
            qbSelectedColumns.filter(c => !c.aggregate).forEach(c => groupBy.push(c.table + '.' + c.field));
        }

        const payload = {
            connection: connKey,
            tables: [...qbUsedTables],
            columns: qbSelectedColumns.map(c => ({ table: c.table, field: c.field, alias: c.alias, aggregate: c.aggregate })),
            joins: qbJoins.filter(j => j.on_left.includes('.') && j.on_right.includes('.')).map(j => ({
                type: j.type,
                table: j.table,
                on_left: j.on_left,
                on_right: j.on_right,
            })),
            filters,
            orderBy,
            groupBy,
            limit: parseInt(document.getElementById('qbLimit').value) || 500,
        };

        try {
            const res = await fetch('/api/query-builder/execute', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.success) {
                lastExecutedSql = data.sql;
                renderResults(data);
                showToast(`Query executed: ${data.row_count} rows in ${data.elapsed_ms}ms`);
            } else {
                throw new Error(data.message);
            }
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">play_arrow</span> Execute Query';
        }
    }

    function renderResults(data) {
        const container = document.getElementById('qbResultsContainer');
        const thead = document.getElementById('qbResultHead');
        const tbody = document.getElementById('qbResultBody');
        const meta = document.getElementById('qbResultMeta');

        container.classList.remove('hidden');
        meta.textContent = `${data.row_count} rows - ${data.elapsed_ms}ms`;

        // Header
        let headHtml = '<tr>';
        data.columns.forEach(col => {
            headHtml += `<th class="py-3 px-4 text-left font-semibold whitespace-nowrap">${col}</th>`;
        });
        headHtml += '</tr>';
        thead.innerHTML = headHtml;

        // Body
        let bodyHtml = '';
        if (data.rows.length === 0) {
            bodyHtml = `<tr><td colspan="${data.columns.length}" class="py-8 text-center text-surface-400">No results found</td></tr>`;
        } else {
            data.rows.forEach((row, i) => {
                bodyHtml += `<tr class="${i % 2 === 0 ? 'bg-white' : 'bg-surface-50/50'} hover:bg-blue-50/30 transition-colors">`;
                data.columns.forEach(col => {
                    const val = row[col];
                    bodyHtml += `<td class="py-2.5 px-4 text-surface-700 whitespace-nowrap max-w-[300px] truncate">${val !== null && val !== undefined ? val : '<span class="text-surface-300 italic">NULL</span>'}</td>`;
                });
                bodyHtml += '</tr>';
            });
        }
        tbody.innerHTML = bodyHtml;

        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ─── Export ────────────────────────────────────────────────
    async function exportQuery(format) {
        if (!lastExecutedSql) {
            showToast('Execute the query first before exporting', 'warning');
            return;
        }

        const connKey = document.getElementById('qbConnection').value;
        const btn = format === 'xlsx' ? document.getElementById('btnExportXlsx') : document.getElementById('btnExportCsv');
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[18px] spin">sync</span> Exporting...';

        try {
            const res = await fetch('/api/query-builder/export', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ connection: connKey, sql: lastExecutedSql, format }),
            });
            const data = await res.json();

            if (data.success) {
                window.location.href = `/api/query-builder/download/${data.token}`;
                showToast(`Export ready: ${data.filename} (${data.size})`);
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

    // Listen to limit changes
    document.getElementById('qbLimit')?.addEventListener('input', updateSqlPreview);
</script>
@endsection
