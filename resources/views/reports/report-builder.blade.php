@extends('layouts.app')

@section('title', 'Report Builder — PL Sync')
@section('page-title', 'Report Builder')

@section('content')
    {{-- SortableJS CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    <div class="max-w-5xl mx-auto space-y-6">

        {{-- Header --}}
        <div class="flex items-center gap-4">
            <div
                class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center shadow-lg shadow-brand-500/20">
                <span class="material-symbols-outlined text-white text-[24px]"
                    style="font-variation-settings: 'FILL' 1;">build</span>
            </div>
            <div>
                <h2 class="text-h2 text-surface-900">Report Builder</h2>
                <p class="text-body-md text-surface-500 mt-0.5">Choose a database, table, columns, and filters — then
                    download your custom report.</p>
            </div>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm">

            {{-- Step 1: Source Selection --}}
            <div class="p-6 border-b border-surface-100">
                <div class="flex items-center gap-3 mb-4">
                    <div
                        class="w-8 h-8 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 text-label-lg font-bold">
                        1</div>
                    <h3 class="text-h3 text-surface-800">Select Source</h3>
                </div>
                @if(empty($connections))
                    <div class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 flex items-start gap-3">
                        <span class="material-symbols-outlined shrink-0">warning</span>
                        <div>
                            <p class="font-medium text-label-md">No connected database available.</p>
                            <p class="text-body-sm mt-1">You need to connect to at least one database in <a href="{{ route('settings.index') }}" class="underline font-semibold text-amber-800 hover:text-amber-900">Settings &gt; Connection Management</a> before building a report.</p>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-label-md text-surface-700 mb-1.5 block">Database Connection</label>
                            <div class="flex gap-2">
                                <select id="rbConnection" onchange="onConnectionDropdownChange()"
                                    class="flex-1 bg-white border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all cursor-pointer">
                                    <option value="">-- Pilih Connection --</option>
                                    @foreach ($connections as $conn)
                                        <option value="{{ $conn['key'] }}">{{ $conn['name'] }} ({{ $conn['type'] }})</option>
                                    @endforeach
                                </select>
                                <button type="button" onclick="onConnectionChange()"
                                    class="px-4 py-2.5 bg-brand-50 text-brand-700 font-medium rounded-xl border border-brand-200 hover:bg-brand-100 transition-colors whitespace-nowrap">
                                    Connect
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="text-label-md text-surface-700 mb-1.5 block">Table</label>
                            <div class="relative">
                                <input type="text" id="rbTableSearch" placeholder="Search or select table..."
                                    autocomplete="off" disabled
                                    onfocus="document.getElementById('rbTableDropdown').classList.remove('hidden')"
                                    onblur="setTimeout(() => { document.getElementById('rbTableDropdown').classList.add('hidden'); }, 200)"
                                    oninput="filterTableList(this)"
                                    class="w-full bg-white border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all cursor-text disabled:bg-surface-50 disabled:text-surface-400">

                                <div id="rbTableDropdown"
                                    class="absolute z-20 w-full mt-1 bg-white border border-surface-200 rounded-xl shadow-lg max-h-60 overflow-y-auto hidden">
                                    <ul id="rbTableList" class="py-1">
                                        <li class="px-4 py-2 text-sm text-surface-400 italic">-- Select Connection First --</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Step 2: Column Selection --}}
            <div id="stepColumns" class="p-6 border-b border-surface-100 hidden">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-8 h-8 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 text-label-lg font-bold">
                            2</div>
                        <h3 class="text-h3 text-surface-800">Select Columns</h3>
                        <span id="colCountBadge"
                            class="text-label-sm px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 font-medium">0
                            selected</span>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="toggleAllColumns(true)"
                            class="px-3 py-1.5 text-label-sm rounded-lg bg-surface-100 text-surface-700 hover:bg-surface-200 transition-all duration-200">Select
                            All</button>
                        <button onclick="toggleAllColumns(false)"
                            class="px-3 py-1.5 text-label-sm rounded-lg bg-surface-100 text-surface-700 hover:bg-surface-200 transition-all duration-200">Deselect
                            All</button>
                    </div>
                </div>
                <div class="mb-3">
                    <input type="text" id="colSearchInput" placeholder="Search columns..." oninput="filterColumnList()"
                        class="w-full rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2 px-3">
                </div>
                <div id="colListContainer"
                    class="max-h-64 overflow-y-auto rounded-xl border border-surface-200 bg-surface-50 p-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                    {{-- Column checkboxes will be inserted here --}}
                </div>
            </div>

            {{-- Step 3: Filters --}}
            <div id="stepFilters" class="p-6 border-b border-surface-100 hidden">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-8 h-8 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 text-label-lg font-bold">
                            3</div>
                        <h3 class="text-h3 text-surface-800">Filters <span
                                class="text-body-sm text-surface-400 font-normal">(Optional)</span></h3>
                    </div>
                    <button onclick="addReportFilterRow()"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-100 text-surface-700 text-label-sm hover:bg-surface-200 transition-all duration-200">
                        <span class="material-symbols-outlined text-[14px]">add</span> Add Filter
                    </button>
                </div>
                <datalist id="rbFilterFieldDataList"></datalist>
                <div id="rbFiltersContainer" class="space-y-2">
                    {{-- Filter rows --}}
                </div>
                <div id="rbEmptyFiltersMsg"
                    class="py-4 text-center border-2 border-dashed border-surface-200 rounded-xl bg-surface-50">
                    <span class="material-symbols-outlined text-surface-300 text-2xl mb-1 block">filter_alt_off</span>
                    <p class="text-body-sm text-surface-400">No filters added. All data will be exported.</p>
                </div>
            </div>

            {{-- Step 4: Export --}}
            <div id="stepExport" class="p-6 hidden">
                <div class="flex items-center gap-3 mb-4">
                    <div
                        class="w-8 h-8 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 text-label-lg font-bold">
                        4</div>
                    <h3 class="text-h3 text-surface-800">Export Format & Download</h3>
                </div>
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="radio" name="rbFormat" value="xlsx" checked
                                class="w-4 h-4 text-brand-600 border-surface-300 focus:ring-brand-500">
                            <span class="text-body-sm text-surface-700 group-hover:text-surface-900 transition-colors">
                                <span
                                    class="material-symbols-outlined text-[16px] align-middle text-green-600">table_chart</span>
                                Excel (.xlsx)
                            </span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="radio" name="rbFormat" value="csv"
                                class="w-4 h-4 text-brand-600 border-surface-300 focus:ring-brand-500">
                            <span class="text-body-sm text-surface-700 group-hover:text-surface-900 transition-colors">
                                <span class="material-symbols-outlined text-[16px] align-middle text-blue-600">csv</span>
                                CSV (.csv)
                            </span>
                        </label>
                    </div>
                    <button id="btnPrepareDownload" onclick="prepareAndDownload()"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">download</span> Prepare & Download
                    </button>
                </div>

                {{-- Progress / Result --}}
                <div id="rbPrepareProgress" class="hidden mt-4 p-4 bg-surface-50 rounded-xl border border-surface-200">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-brand-500 text-[20px] spin">sync</span>
                        <span class="text-body-sm text-surface-700">Preparing your report, please wait...</span>
                    </div>
                </div>
                <div id="rbDownloadResult" class="hidden mt-4 p-4 bg-green-50 rounded-xl border border-green-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-green-600 text-[20px]"
                                style="font-variation-settings: 'FILL' 1;">check_circle</span>
                            <div>
                                <p class="text-body-sm font-medium text-green-800"><span id="rbResultFilename"></span></p>
                                <p class="text-label-sm text-green-600"><span id="rbResultSize"></span><span
                                        id="rbResultZipped" class="hidden"> — Compressed to ZIP</span></p>
                            </div>
                        </div>
                        <a id="rbDownloadLink" href="#"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-green-600 text-white text-label-md hover:bg-green-700 transition-all duration-200 shadow-sm">
                            <span class="material-symbols-outlined text-[16px]">download</span> Download
                        </a>
                    </div>
                </div>
                <div id="rbErrorResult" class="hidden mt-4 p-4 bg-red-50 rounded-xl border border-red-200">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-red-600 text-[20px]">error</span>
                        <p class="text-body-sm text-red-800" id="rbErrorMsg"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        const preloadedTables = @json($allTables);
        let allColumns = [];

        document.addEventListener('DOMContentLoaded', () => {
            const savedConn = localStorage.getItem('rb_saved_connection');
            if (savedConn) {
                const rbConn = document.getElementById('rbConnection');
                let valid = Array.from(rbConn.options).some(opt => opt.value === savedConn);
                if (valid) {
                    rbConn.value = savedConn;
                    onConnectionChange(true);
                }
            }
        });

        function onConnectionDropdownChange() {
            const tableInput = document.getElementById('rbTableSearch');
            const tableList = document.getElementById('rbTableList');

            tableInput.value = '';
            tableInput.disabled = true;
            tableList.innerHTML =
                '<li class="px-4 py-2 text-sm text-surface-400 italic">-- Select Connection First --</li>';
            hideSteps();
        }

        async function onConnectionChange(isAutoLoad = false) {
            const connKey = document.getElementById('rbConnection').value;
            const tableInput = document.getElementById('rbTableSearch');
            const tableList = document.getElementById('rbTableList');

            if (!connKey) {
                if (!isAutoLoad) showToast('Silakan pilih koneksi terlebih dahulu.', 'warning');
                return;
            }

            localStorage.setItem('rb_saved_connection', connKey);

            // Reset
            tableInput.value = '';
            tableInput.disabled = true;
            tableList.innerHTML =
                '<li class="px-4 py-2 text-sm text-surface-400 italic">-- Select Connection First --</li>';
            hideSteps();

            // Populate tables from Blade (preloaded)
            const tables = preloadedTables[connKey] || [];

            if (tables.length === 0) {
                tableList.innerHTML =
                    '<li class="px-4 py-2 text-sm text-surface-400 italic">-- No tables found --</li>';
            } else {
                let html = '';
                tables.forEach(t => {
                    html +=
                        `<li onclick="selectTable('${t}')" class="px-4 py-2 hover:bg-surface-100 cursor-pointer text-sm font-mono text-surface-800 transition-colors">${t}</li>`;
                });
                tableList.innerHTML = html;
            }

            tableInput.disabled = false;
            if (!isAutoLoad) showToast('Berhasil dikoneksikan ke database, silakan pilih table.', 'success');
        }

        function filterTableList(input) {
            const filter = input.value.toLowerCase();
            const lis = document.getElementById('rbTableList').getElementsByTagName('li');
            for (let i = 0; i < lis.length; i++) {
                if (lis[i].textContent.toLowerCase().includes(filter)) {
                    lis[i].style.display = "";
                } else {
                    lis[i].style.display = "none";
                }
            }
        }

        function selectTable(tableName) {
            const tableInput = document.getElementById('rbTableSearch');
            tableInput.value = tableName;
            document.getElementById('rbTableDropdown').classList.add('hidden');
            onTableChange();
        }

        async function onTableChange() {
            const connKey = document.getElementById('rbConnection').value;
            const table = document.getElementById('rbTableSearch').value;

            if (!connKey || !table) {
                hideSteps();
                return;
            }

            try {
                const baseUrl = "/api/report-builder";
                const res = await fetch(
                    `${baseUrl}/columns?connection=${encodeURIComponent(connKey)}&table=${encodeURIComponent(table)}`
                );
                const data = await res.json();
                if (data.success) {
                    allColumns = data.columns;
                    renderColumns(allColumns);
                    populateFilterDataList(allColumns);
                    showSteps();
                } else {
                    showToast(data.message || 'Failed to load columns', 'error');
                }
            } catch (e) {
                showToast('Error loading columns', 'error');
            }
        }

        // ── Step 2: Columns ──
        let colSortableInstance = null;

        function renderColumns(columns) {
            const container = document.getElementById('colListContainer');
            container.innerHTML = '';

            columns.forEach(col => {
                const label = document.createElement('label');
                label.className =
                    'flex items-center gap-2 py-1.5 px-2 rounded-lg border border-transparent hover:border-surface-200 bg-surface-50 hover:bg-white cursor-pointer transition-all col-item group';
                label.dataset.colname = col.toLowerCase();
                label.innerHTML = `
                    <span class="material-symbols-outlined text-surface-300 text-[16px] cursor-grab drag-handle group-hover:text-surface-500">drag_indicator</span>
                    <input type="checkbox" class="col-checkbox w-4 h-4 text-brand-600 rounded border-surface-300 focus:ring-brand-500" value="${col}" checked onchange="updateColCount()">
                    <span class="text-body-sm text-surface-700 truncate select-none flex-1">${col}</span>
                `;
                container.appendChild(label);
            });

            updateColCount();

            if (colSortableInstance) {
                colSortableInstance.destroy();
            }
            
            colSortableInstance = new Sortable(container, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'opacity-40',
                chosenClass: 'bg-brand-50'
            });
        }

        function filterColumnList() {
            const search = document.getElementById('colSearchInput').value.toLowerCase();
            document.querySelectorAll('.col-item').forEach(item => {
                item.style.display = item.dataset.colname.includes(search) ? '' : 'none';
            });
        }

        function toggleAllColumns(checked) {
            document.querySelectorAll('.col-checkbox').forEach(cb => {
                if (cb.closest('.col-item').style.display !== 'none') {
                    cb.checked = checked;
                }
            });
            updateColCount();
        }

        function updateColCount() {
            const count = document.querySelectorAll('.col-checkbox:checked').length;
            document.getElementById('colCountBadge').textContent = `${count} selected`;
        }

        function getSelectedColumns() {
            return Array.from(document.querySelectorAll('.col-checkbox:checked')).map(cb => cb.value);
        }

        // ── Step 3: Filters ──
        function populateFilterDataList(columns) {
            const dl = document.getElementById('rbFilterFieldDataList');
            dl.innerHTML = '';
            columns.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                dl.appendChild(opt);
            });
        }

        function addReportFilterRow(field = '', op = 'eq', val = '') {
            const container = document.getElementById('rbFiltersContainer');
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 rb-filter-row animate-fade-up';

            row.innerHTML = `
                <input type="text" list="rbFilterFieldDataList" class="rb-filter-field flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2" placeholder="Search or select field..." value="${field}">
                <select class="rb-filter-operator w-36 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2">
                    <option value="eq" ${op === 'eq' ? 'selected' : ''}>Equals (=)</option>
                    <option value="ne" ${op === 'ne' ? 'selected' : ''}>Not Eq (!=)</option>
                    <option value="contains" ${op === 'contains' ? 'selected' : ''}>Contains</option>
                    <option value="startswith" ${op === 'startswith' ? 'selected' : ''}>Starts With</option>
                    <option value="endswith" ${op === 'endswith' ? 'selected' : ''}>Ends With</option>
                    <option value="gt" ${op === 'gt' ? 'selected' : ''}>Greater (>)</option>
                    <option value="ge" ${op === 'ge' ? 'selected' : ''}>Greater Eq (>=)</option>
                    <option value="lt" ${op === 'lt' ? 'selected' : ''}>Less (<)</option>
                    <option value="le" ${op === 'le' ? 'selected' : ''}>Less Eq (<=)</option>
                </select>
                <input type="text" class="rb-filter-value flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2" placeholder="Value..." value="${val}">
                <button onclick="removeReportFilterRow(this)" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all duration-200" title="Remove filter">
                    <span class="material-symbols-outlined text-[16px]">delete</span>
                </button>
            `;

            container.appendChild(row);
            checkEmptyReportFilters();
        }

        function removeReportFilterRow(btn) {
            btn.closest('.rb-filter-row').remove();
            checkEmptyReportFilters();
        }

        function checkEmptyReportFilters() {
            const rows = document.querySelectorAll('.rb-filter-row');
            document.getElementById('rbEmptyFiltersMsg').classList.toggle('hidden', rows.length > 0);
        }

        function getFilters() {
            const filters = [];
            document.querySelectorAll('.rb-filter-row').forEach(row => {
                const field = row.querySelector('.rb-filter-field').value;
                const op = row.querySelector('.rb-filter-operator').value;
                const val = row.querySelector('.rb-filter-value').value;
                if (field && val) {
                    filters.push({
                        field,
                        operator: op,
                        value: val
                    });
                }
            });
            return filters;
        }

        // ── Step 4: Prepare & Download ──
        async function prepareAndDownload() {
            const columns = getSelectedColumns();
            if (columns.length === 0) {
                showToast('Please select at least one column.', 'warning');
                return;
            }

            const connKey = document.getElementById('rbConnection').value;
            const table = document.getElementById('rbTableSearch').value;
            const format = document.querySelector('input[name="rbFormat"]:checked').value;
            const filters = getFilters();

            const btn = document.getElementById('btnPrepareDownload');
            const progress = document.getElementById('rbPrepareProgress');
            const result = document.getElementById('rbDownloadResult');
            const errorEl = document.getElementById('rbErrorResult');

            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-[18px] spin">sync</span> Preparing...';
            progress.classList.remove('hidden');
            result.classList.add('hidden');
            errorEl.classList.add('hidden');

            try {
                const baseUrl = "/api/report-builder/prepare";
                const res = await fetch(baseUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        connection: connKey,
                        table,
                        columns,
                        format,
                        filters
                    }),
                });

                const data = await res.json();

                if (data.success) {
                    document.getElementById('rbResultFilename').textContent = data.filename;
                    document.getElementById('rbResultSize').textContent = data.size;
                    document.getElementById('rbResultZipped').classList.toggle('hidden', !data.zipped);
                    document.getElementById('rbDownloadLink').href = `/api/report-builder/download/${data.token}`;
                    result.classList.remove('hidden');

                    // Auto-trigger download
                    window.location.href = `/api/report-builder/download/${data.token}`;
                } else {
                    document.getElementById('rbErrorMsg').textContent = data.message || 'Failed to prepare report.';
                    errorEl.classList.remove('hidden');
                }
            } catch (e) {
                document.getElementById('rbErrorMsg').textContent = 'Network error while preparing report.';
                errorEl.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML =
                    '<span class="material-symbols-outlined text-[18px]">download</span> Prepare & Download';
                progress.classList.add('hidden');
            }
        }

        // ── Helpers ──
        function hideSteps() {
            document.getElementById('stepColumns').classList.add('hidden');
            document.getElementById('stepFilters').classList.add('hidden');
            document.getElementById('stepExport').classList.add('hidden');
        }

        function showSteps() {
            document.getElementById('stepColumns').classList.remove('hidden');
            document.getElementById('stepFilters').classList.remove('hidden');
            document.getElementById('stepExport').classList.remove('hidden');
        }
    </script>
@endsection
