@extends('layouts.app')

@section('title', 'Report Builder — PL Sync')
@section('page-title', 'Report Builder')

@section('content')
    {{-- SortableJS CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    <div class="max-w-5xl mx-auto space-y-6">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
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
            
            <div class="relative">
                <button onclick="toggleTemplateDropdown()" class="px-4 py-2 bg-white border border-surface-200 rounded-xl text-body-sm font-medium text-surface-700 shadow-sm hover:bg-surface-50 transition-colors inline-flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-brand-600">bookmark</span>
                    Saved Reports
                    <span class="material-symbols-outlined text-[18px] text-surface-400 transition-transform duration-200" id="templateDropdownIcon">expand_more</span>
                </button>
                <div id="templateDropdown" class="absolute right-0 mt-2 w-72 bg-white border border-surface-200 rounded-xl shadow-lg z-30 hidden flex flex-col">
                    <div class="p-3 border-b border-surface-100 bg-surface-50 rounded-t-xl flex justify-between items-center">
                        <h4 class="text-label-sm text-surface-600 font-bold uppercase tracking-wider">Your Templates</h4>
                        <a href="{{ route('report-templates.index') }}" class="text-surface-400 hover:text-brand-600 transition-colors" title="Manage Templates">
                            <span class="material-symbols-outlined text-[16px]">settings</span>
                        </a>
                    </div>
                    <div id="templateList" class="max-h-60 overflow-y-auto py-1">
                        <!-- Templates rendered here -->
                    </div>
                </div>
            </div>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm flex flex-col">
            
            {{-- Tabs --}}
            <div class="flex border-b border-surface-100 overflow-x-auto hide-scrollbar bg-surface-50 rounded-t-2xl px-2 pt-2">
                <button onclick="switchTab('source')" id="tab-source" class="flex-1 min-w-[150px] pb-3 pt-2 px-4 border-b-2 border-brand-600 text-brand-700 flex flex-col items-center gap-1 transition-all">
                    <span class="text-label-sm font-bold uppercase tracking-wider">Step 1</span>
                    <span class="font-medium text-body-sm">Data Source</span>
                </button>
                <button onclick="switchTab('columns')" id="tab-columns" class="flex-1 min-w-[150px] pb-3 pt-2 px-4 border-b-2 border-transparent text-surface-400 hover:text-surface-600 flex flex-col items-center gap-1 transition-all opacity-50 pointer-events-none">
                    <span class="text-label-sm font-bold uppercase tracking-wider">Step 2</span>
                    <span class="font-medium text-body-sm">Columns</span>
                </button>
                <button onclick="switchTab('filters')" id="tab-filters" class="flex-1 min-w-[150px] pb-3 pt-2 px-4 border-b-2 border-transparent text-surface-400 hover:text-surface-600 flex flex-col items-center gap-1 transition-all opacity-50 pointer-events-none">
                    <span class="text-label-sm font-bold uppercase tracking-wider">Step 3</span>
                    <span class="font-medium text-body-sm">Filters</span>
                </button>
                <button onclick="switchTab('export')" id="tab-export" class="flex-1 min-w-[150px] pb-3 pt-2 px-4 border-b-2 border-transparent text-surface-400 hover:text-surface-600 flex flex-col items-center gap-1 transition-all opacity-50 pointer-events-none">
                    <span class="text-label-sm font-bold uppercase tracking-wider">Step 4</span>
                    <span class="font-medium text-body-sm">Export</span>
                </button>
            </div>

            <div class="p-6 relative min-h-[450px]">
                {{-- Step 1: Source Selection --}}
                <div id="stepSource" class="animate-fade-in">
                    <h3 class="text-h3 text-surface-800 mb-4">Select Database & Table</h3>
                    
                    @if(empty($connections))
                        <div class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 flex items-start gap-3">
                            <span class="material-symbols-outlined shrink-0">warning</span>
                            <div>
                                <p class="font-medium text-label-md">No connected database available.</p>
                                <p class="text-body-sm mt-1">You need to connect to at least one database in <a
                                        href="{{ route('settings.index') }}"
                                        class="underline font-semibold text-amber-800 hover:text-amber-900">Settings &gt; Connection
                                        Management</a> before building a report.</p>
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                                    <input type="text" id="rbTableSearch" placeholder="Search or select table..." autocomplete="off"
                                        disabled onfocus="document.getElementById('rbTableDropdown').classList.remove('hidden')"
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

                {{-- Step 2: Column Selection & Ordering --}}
                <div id="stepColumns" class="hidden animate-fade-in">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-h3 text-surface-800">Select & Order Columns</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Available Columns -->
                        <div class="flex flex-col border border-surface-200 rounded-xl bg-white h-[340px] shadow-sm">
                            <div class="px-4 py-3 bg-surface-50 border-b border-surface-200 flex justify-between items-center rounded-t-xl">
                                <span class="text-label-md font-semibold text-surface-700">Available Columns</span>
                                <button onclick="addAllColumns()" class="text-xs font-medium text-brand-600 hover:text-brand-800 transition-colors">Add All &rarr;</button>
                            </div>
                            <div class="p-2 border-b border-surface-100">
                                <input type="text" id="colSearchInput" placeholder="Search available..." oninput="filterAvailableColumns()" class="w-full rounded-lg border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-1.5 px-3">
                            </div>
                            <div id="availableColumnsList" class="flex-1 overflow-y-auto p-2 space-y-1">
                                <!-- Available columns inserted here -->
                            </div>
                        </div>

                        <!-- Selected Columns -->
                        <div class="flex flex-col border border-surface-200 rounded-xl bg-white h-[340px] shadow-sm">
                            <div class="px-4 py-3 bg-surface-50 border-b border-surface-200 flex justify-between items-center rounded-t-xl">
                                <div class="flex items-center gap-2">
                                    <span class="text-label-md font-semibold text-surface-700">Selected Columns</span>
                                    <span id="colCountBadge" class="text-[10px] px-2 py-0.5 rounded-full bg-brand-100 text-brand-700 font-bold">0</span>
                                </div>
                                <button onclick="removeAllColumns()" class="text-xs font-medium text-red-600 hover:text-red-800 transition-colors">&larr; Remove All</button>
                            </div>
                            <div class="p-2 border-b border-surface-100 bg-surface-50">
                                <p class="text-[11px] text-surface-500 text-center"><span class="material-symbols-outlined text-[14px] align-middle">drag_indicator</span> Drag items to reorder them in the report</p>
                            </div>
                            <div id="selectedColumnsList" class="flex-1 overflow-y-auto p-2 space-y-1 bg-surface-50/30">
                                <!-- Selected columns inserted here -->
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 3: Filters --}}
                <div id="stepFilters" class="hidden animate-fade-in">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-h3 text-surface-800">Filters <span class="text-body-sm text-surface-400 font-normal">(Optional)</span></h3>
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
                        class="py-4 text-center border-2 border-dashed border-surface-200 rounded-xl bg-surface-50 mt-4">
                        <span class="material-symbols-outlined text-surface-300 text-2xl mb-1 block">filter_alt_off</span>
                        <p class="text-body-sm text-surface-400">No filters added. All data will be exported.</p>
                    </div>
                </div>

                {{-- Step 4: Export --}}
                <div id="stepExport" class="hidden animate-fade-in">
                    <h3 class="text-h3 text-surface-800 mb-4">Export Format & Download</h3>
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
                        <button type="button" onclick="showSaveReportModal()"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-surface-100 text-surface-700 text-label-md hover:bg-surface-200 border border-surface-200 transition-all duration-200 shadow-sm">
                            <span class="material-symbols-outlined text-[18px]">save</span> Save Report
                        </button>
                    </div>

                    {{-- Progress / Result --}}
                    <div id="rbPrepareProgress" class="hidden mt-4 p-4 bg-surface-50 rounded-xl border border-surface-200">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="material-symbols-outlined text-brand-500 text-[20px] spin">sync</span>
                            <span id="rbProgressText" class="text-body-sm text-surface-700 font-medium">Preparing your report,
                                please wait...</span>
                        </div>
                        <div class="w-full bg-surface-200 rounded-full h-2.5 overflow-hidden">
                            <div id="rbProgressBar" class="bg-brand-600 h-2.5 rounded-full transition-all duration-300"
                                style="width: 0%"></div>
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
                        <div id="rbDownloadNotice" class="mt-3 pt-3 border-t border-green-200 hidden">
                            <p class="text-xs text-green-700 font-medium">
                                <span class="material-symbols-outlined text-[14px] align-middle mr-1">info</span>
                                Finishing & downloading... Please wait and check your browser's download manager.
                            </p>
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
            
            {{-- Navigation Footer --}}
            <div class="px-6 py-4 border-t border-surface-100 bg-surface-50 rounded-b-2xl flex justify-between items-center" id="tabFooter">
                <button onclick="prevTab()" id="btnPrev" class="px-4 py-2 rounded-xl border border-surface-200 bg-white text-surface-700 font-medium hover:bg-surface-50 transition-colors invisible">Back</button>
                <button onclick="nextTab()" id="btnNext" class="px-4 py-2 rounded-xl bg-brand-600 text-white font-medium hover:bg-brand-700 transition-colors hidden">Next Step</button>
            </div>
        </div>
    </div>

    {{-- Save Report Modal --}}
    <div id="saveReportModal" class="fixed inset-0 z-50 hidden bg-surface-900/50 backdrop-blur-sm flex items-center justify-center">
        <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-fade-up">
            <div class="p-6 border-b border-surface-100 flex justify-between items-center bg-surface-50">
                <h3 class="text-h3 text-surface-900">Save Report Template</h3>
                <button onclick="hideSaveReportModal()" class="text-surface-400 hover:text-surface-600 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-6">
                <label class="block text-label-md text-surface-700 mb-2">Report Name</label>
                <input type="text" id="saveReportName" placeholder="e.g. Monthly Sales Summary" class="w-full bg-white border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                <p class="text-xs text-surface-500 mt-2">This will save your connection, table, selected columns, and filters.</p>
            </div>
            <div class="p-6 border-t border-surface-100 bg-surface-50 flex justify-end gap-3">
                <button onclick="hideSaveReportModal()" class="px-4 py-2 bg-white border border-surface-200 rounded-xl text-body-sm font-medium text-surface-700 hover:bg-surface-50 transition-colors">Cancel</button>
                <button onclick="saveReportTemplate()" id="btnSaveReportConfirm" class="px-4 py-2 bg-brand-600 rounded-xl text-body-sm font-medium text-white hover:bg-brand-700 transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">save</span> Save
                </button>
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
                '<li class="px-4 py-2 text-sm text-surface-400 italic">-- Loading tables... --</li>';
            hideSteps();

            try {
                // Fetch tables dynamically with refresh flag if not auto-loading
                const refreshParam = isAutoLoad ? '0' : '1';
                const res = await fetch(`/api/report-builder/tables?connection=${encodeURIComponent(connKey)}&refresh=${refreshParam}`);
                const data = await res.json();
                
                if (data.success) {
                    const tables = data.tables || [];
                    preloadedTables[connKey] = tables; // Update local state
                    if (tables.length === 0) {
                        tableList.innerHTML = '<li class="px-4 py-2 text-sm text-surface-400 italic">-- No tables found --</li>';
                    } else {
                        renderRbTableDropdown(tables);
                    }
                    tableInput.disabled = false;
                    if (!isAutoLoad) showToast('Berhasil dikoneksikan dan daftar table telah di-refresh.', 'success');
                } else {
                    tableList.innerHTML = '<li class="px-4 py-2 text-sm text-red-500 italic">-- Failed to load tables --</li>';
                    if (!isAutoLoad) showToast(data.message || 'Gagal mengambil table', 'error');
                }
            } catch (e) {
                tableList.innerHTML = '<li class="px-4 py-2 text-sm text-red-500 italic">-- Error loading tables --</li>';
                if (!isAutoLoad) showToast('Error loading tables', 'error');
            }
        }

        function renderRbTableDropdown(tables) {
            const tableList = document.getElementById('rbTableList');
            const maxTables = 100;
            const visibleTables = tables.slice(0, maxTables);
            let html = '';
            visibleTables.forEach(t => {
                html +=
                    `<li onclick="selectTable('${t}')" class="px-4 py-2 hover:bg-surface-100 cursor-pointer text-sm font-mono text-surface-800 transition-colors">${t}</li>`;
            });
            
            if (tables.length > maxTables) {
                html += `<li class="px-4 py-3 text-surface-400 text-xs text-center border-t border-surface-100 italic">Showing first ${maxTables} tables. Type to search more...</li>`;
            }
            tableList.innerHTML = html;
        }

        function filterTableList(input) {
            const filter = input.value.toLowerCase();
            const connKey = document.getElementById('rbConnection').value;
            if (!connKey) return;
            
            const tables = preloadedTables[connKey] || [];
            const filtered = tables.filter(t => t.toLowerCase().includes(filter));
            
            if (filtered.length === 0) {
                document.getElementById('rbTableList').innerHTML = '<li class="px-4 py-2 text-sm text-surface-400 italic">-- No matches found --</li>';
            } else {
                renderRbTableDropdown(filtered);
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
        let availableColumns = [];
        let selectedColumns = [];

        function renderColumns(columns) {
            availableColumns = [];
            selectedColumns = [...columns];
            updateColumnUI();
        }

        function updateColumnUI() {
            const availContainer = document.getElementById('availableColumnsList');
            const selContainer = document.getElementById('selectedColumnsList');
            
            availContainer.innerHTML = '';
            selContainer.innerHTML = '';
            
            availableColumns.forEach(col => {
                const el = document.createElement('div');
                el.className = 'col-item flex items-center justify-between px-3 py-2 bg-white border border-surface-100 rounded-lg hover:border-brand-300 hover:shadow-sm cursor-pointer transition-all group';
                el.dataset.colname = col.toLowerCase();
                el.innerHTML = `
                    <span class="text-body-sm text-surface-700 group-hover:text-brand-700 truncate">${col}</span>
                    <span class="material-symbols-outlined text-[18px] text-surface-300 group-hover:text-brand-500">add</span>
                `;
                el.onclick = () => selectColumn(col);
                availContainer.appendChild(el);
            });
            
            selectedColumns.forEach(col => {
                const el = document.createElement('div');
                el.className = 'flex items-center gap-2 px-3 py-2 bg-white border border-surface-200 rounded-lg shadow-sm cursor-grab group';
                el.dataset.colname = col;
                el.innerHTML = `
                    <span class="material-symbols-outlined text-[16px] text-surface-300 drag-handle hover:text-surface-500">drag_indicator</span>
                    <span class="text-body-sm text-surface-800 font-medium truncate flex-1">${col}</span>
                    <button type="button" onclick="deselectColumn('${col}')" class="text-surface-300 hover:text-red-500 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                `;
                selContainer.appendChild(el);
            });
            
            document.getElementById('colCountBadge').textContent = selectedColumns.length;
            
            if (colSortableInstance) {
                colSortableInstance.destroy();
            }
            
            colSortableInstance = new Sortable(selContainer, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'opacity-40',
                chosenClass: 'bg-brand-50',
                onEnd: function (evt) {
                    const newOrder = [];
                    selContainer.querySelectorAll('div[data-colname]').forEach(el => {
                        newOrder.push(el.dataset.colname);
                    });
                    selectedColumns = newOrder;
                }
            });
            
            filterAvailableColumns();
        }

        function selectColumn(col) {
            availableColumns = availableColumns.filter(c => c !== col);
            selectedColumns.push(col);
            updateColumnUI();
        }
        
        function deselectColumn(col) {
            selectedColumns = selectedColumns.filter(c => c !== col);
            availableColumns.push(col);
            availableColumns.sort();
            updateColumnUI();
        }
        
        function addAllColumns() {
            selectedColumns = [...selectedColumns, ...availableColumns];
            availableColumns = [];
            updateColumnUI();
        }
        
        function removeAllColumns() {
            availableColumns = [...availableColumns, ...selectedColumns].sort();
            selectedColumns = [];
            updateColumnUI();
        }

        function filterAvailableColumns() {
            const search = document.getElementById('colSearchInput').value.toLowerCase();
            document.querySelectorAll('#availableColumnsList .col-item').forEach(item => {
                item.style.display = item.dataset.colname.includes(search) ? '' : 'none';
            });
        }
        
        function getSelectedColumns() {
            return selectedColumns;
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
                    <input type="text" list="rbFilterFieldDataList" class="rb-filter-field flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2" placeholder="Search or select field..." value="${field}" oninput="updateRbFilterType(this)" onchange="updateRbFilterType(this)">
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

        function updateRbFilterType(fieldInput) {
            const valInput = fieldInput.closest('.rb-filter-row').querySelector('.rb-filter-value');
            const fieldName = fieldInput.value.toLowerCase();

            // Common keywords indicating a date field
            const dateKeywords = ['date', 'time', '_at', 'dt', 'tgl', 'tanggal'];
            if (dateKeywords.some(kw => fieldName.includes(kw))) {
                valInput.type = 'date';
            } else {
                valInput.type = 'text';
            }
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
            document.getElementById('rbProgressBar').style.width = '0%';
            result.classList.add('hidden');
            errorEl.classList.add('hidden');

            try {
                const downloadId = Date.now().toString() + Math.floor(Math.random() * 1000);
                const userLocale = Intl.NumberFormat().resolvedOptions().locale;
                const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

                const payload = {
                    connection: connKey,
                    table: table,
                    format: format,
                    download_id: downloadId,
                    locale: userLocale,
                    timezone: userTimezone,
                    columns: columns,
                    filters: filters
                };

                const fetchPromise = fetch('/api/report-builder/prepare', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                });

                const progressText = document.getElementById('rbProgressText');
                progressText.textContent = 'Preparing your report, please wait...';

                // Poll for progress
                const pollInterval = setInterval(async () => {
                    try {
                        const res = await fetch(`/api/report-builder/progress?download_id=${downloadId}`);
                        const data = await res.json();
                        
                        if (data.success && !data.done) {
                            progressText.textContent = `Streaming... ${data.percentage}% (${data.current.toLocaleString()} / ${data.total.toLocaleString()} rows)`;
                            document.getElementById('rbProgressBar').style.width = `${data.percentage}%`;
                        }
                    } catch (e) {}
                }, 1000);

                const res = await fetchPromise;
                const data = await res.json();
                
                clearInterval(pollInterval);
                
                if (data.success) {
                    document.getElementById('rbProgressBar').style.width = '100%';
                    document.getElementById('rbResultFilename').textContent = data.filename;
                    document.getElementById('rbResultSize').textContent = data.size;
                    document.getElementById('rbResultZipped').classList.add('hidden');

                    const downloadLink = document.getElementById('rbDownloadLink');
                    downloadLink.href = `/api/report-builder/download/${data.token}`;
                    downloadLink.style.display = 'inline-flex';

                    result.classList.remove('hidden');
                    document.getElementById('rbDownloadNotice').classList.add('hidden');

                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">download</span> Prepare & Download';
                    progress.classList.add('hidden');
                } else {
                    throw new Error(data.message || 'Export failed');
                }

            } catch (e) {
                document.getElementById('rbErrorMsg').textContent = e.message || 'Error initiating download.';
                errorEl.classList.remove('hidden');

                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">download</span> Prepare & Download';
                progress.classList.add('hidden');
            }
        }

        // ── Helpers & Tab Navigation ──
        const steps = ['source', 'columns', 'filters', 'export'];
        let currentStepIndex = 0;
        
        function hideSteps() {
            // Disable tabs 2, 3, 4
            for(let i = 1; i <= 3; i++) {
                const tab = document.getElementById('tab-' + steps[i]);
                tab.classList.add('opacity-50', 'pointer-events-none');
                tab.classList.remove('text-brand-700', 'border-brand-600');
                tab.classList.add('text-surface-400', 'border-transparent');
            }
            switchTab('source');
        }

        function showSteps() {
            // Enable tabs 2, 3, 4
            ['tab-columns', 'tab-filters', 'tab-export'].forEach(id => {
                const tab = document.getElementById(id);
                if (tab) {
                    tab.classList.remove('opacity-50', 'pointer-events-none');
                } else {
                    console.warn(`Tab element ${id} not found.`);
                }
            });
            const btnNextSource = document.getElementById('btnNextSource');
            if (btnNextSource) {
                btnNextSource.classList.remove('hidden');
                btnNextSource.classList.add('inline-flex');
            }
        }
        
        function switchTab(stepId) {
            const index = steps.indexOf(stepId);
            if (index === -1) return;
            
            // Cannot navigate to disabled tabs
            if (document.getElementById('tab-' + stepId).classList.contains('pointer-events-none')) return;

            currentStepIndex = index;
            
            // Hide all steps
            steps.forEach(s => {
                document.getElementById('step' + s.charAt(0).toUpperCase() + s.slice(1)).classList.add('hidden');
                
                // Reset tab style
                const tab = document.getElementById('tab-' + s);
                tab.classList.remove('border-brand-600', 'text-brand-700');
                tab.classList.add('border-transparent', 'text-surface-400');
                
                // Set step badge inside tab
                const badge = tab.querySelector('span:first-child');
                badge.classList.remove('text-brand-600');
                badge.classList.add('text-surface-400');
            });
            
            // Show active step
            document.getElementById('step' + stepId.charAt(0).toUpperCase() + stepId.slice(1)).classList.remove('hidden');
            
            // Set active tab style
            const activeTab = document.getElementById('tab-' + stepId);
            activeTab.classList.remove('border-transparent', 'text-surface-400');
            activeTab.classList.add('border-brand-600', 'text-brand-700');
            const activeBadge = activeTab.querySelector('span:first-child');
            activeBadge.classList.remove('text-surface-400');
            activeBadge.classList.add('text-brand-600');
            
            // Update Footer Buttons
            const btnPrev = document.getElementById('btnPrev');
            const btnNext = document.getElementById('btnNext');
            const tabFooter = document.getElementById('tabFooter');
            
            if (currentStepIndex === 0) {
                btnPrev.classList.add('invisible');
                btnNext.classList.add('hidden');
            } else {
                btnPrev.classList.remove('invisible');
                if (currentStepIndex === steps.length - 1) {
                    btnNext.classList.add('hidden');
                } else {
                    btnNext.classList.remove('hidden');
                }
            }
        }
        
        function nextTab() {
            if (currentStepIndex < steps.length - 1) {
                switchTab(steps[currentStepIndex + 1]);
            }
        }
        
        function prevTab() {
            if (currentStepIndex > 0) {
                switchTab(steps[currentStepIndex - 1]);
            }
        }

        // ── Templates Logic ──
        let templates = @json($templates);
        let currentLoadedTemplateId = null;
        
        document.addEventListener('DOMContentLoaded', () => {
            renderTemplateList();
            
            // Check if template ID is passed via URL
            const urlParams = new URLSearchParams(window.location.search);
            const templateId = urlParams.get('template');
            if (templateId) {
                // Ensure templates are loaded before calling loadTemplate
                setTimeout(() => {
                    loadTemplate(parseInt(templateId));
                }, 100);
            }
        });

        function toggleTemplateDropdown() {
            const dropdown = document.getElementById('templateDropdown');
            const icon = document.getElementById('templateDropdownIcon');
            
            if (dropdown.classList.contains('hidden')) {
                dropdown.classList.remove('hidden');
                icon.style.transform = 'rotate(180deg)';
                renderTemplateList();
            } else {
                dropdown.classList.add('hidden');
                icon.style.transform = 'rotate(0deg)';
            }
        }
        
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('templateDropdown');
            const button = dropdown.previousElementSibling;
            if (!dropdown.contains(event.target) && !button.contains(event.target)) {
                dropdown.classList.add('hidden');
                document.getElementById('templateDropdownIcon').style.transform = 'rotate(0deg)';
            }
        });

        function renderTemplateList() {
            const listEl = document.getElementById('templateList');
            if (templates.length === 0) {
                listEl.innerHTML = `
                    <div class="px-4 py-6 flex flex-col items-center text-center opacity-50">
                        <span class="material-symbols-outlined text-[32px] mb-2">inventory_2</span>
                        <p class="text-label-sm font-medium">No templates saved yet</p>
                        <p class="text-[11px] mt-1 text-surface-500 max-w-[200px]">Save your current report configuration to access it here.</p>
                    </div>
                `;
                return;
            }
            
            listEl.innerHTML = templates.map(t => `
                <div class="group px-4 py-3 border-b border-surface-100 hover:bg-brand-50 transition-colors last:border-b-0 relative">
                    <div class="cursor-pointer pr-8" onclick="loadTemplate(${t.id})">
                        <h5 class="text-label-md text-surface-800 font-semibold group-hover:text-brand-700 transition-colors line-clamp-1">${t.name}</h5>
                        <p class="text-xs text-surface-500 mt-0.5 line-clamp-1"><span class="font-medium text-surface-600">${t.connection_key}</span> &bull; ${t.table_name}</p>
                    </div>
                    <button onclick="deleteTemplate(${t.id})" class="absolute right-3 top-1/2 -translate-y-1/2 w-7 h-7 rounded-lg text-surface-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all focus:opacity-100" title="Delete Template">
                        <span class="material-symbols-outlined text-[16px]">delete</span>
                    </button>
                </div>
            `).join('');
        }

        function showSaveReportModal() {
            if (!document.getElementById('rbConnection').value || !document.getElementById('rbTableSearch').value) {
                showToast('Please select a connection and a table first.', 'warning');
                return;
            }
            
            // Pre-fill name if a template is loaded
            const nameInput = document.getElementById('saveReportName');
            if (currentLoadedTemplateId) {
                const template = templates.find(t => t.id === currentLoadedTemplateId);
                if (template) {
                    nameInput.value = template.name;
                }
            } else {
                nameInput.value = '';
            }
            
            document.getElementById('saveReportModal').classList.remove('hidden');
            setTimeout(() => nameInput.focus(), 100);
        }

        function hideSaveReportModal() {
            document.getElementById('saveReportModal').classList.add('hidden');
        }

        async function saveReportTemplate() {
            const name = document.getElementById('saveReportName').value.trim();
            if (!name) {
                showToast('Please enter a name for the report template.', 'warning');
                return;
            }
            
            const columns = getSelectedColumns();
            if (columns.length === 0) {
                showToast('Please select at least one column before saving.', 'warning');
                hideSaveReportModal();
                return;
            }

            const connKey = document.getElementById('rbConnection').value;
            const table = document.getElementById('rbTableSearch').value;
            const filters = getFilters();
            
            const btn = document.getElementById('btnSaveReportConfirm');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-[16px] spin">sync</span> Saving...';
            const url = currentLoadedTemplateId ? `/report-templates/${currentLoadedTemplateId}` : '/report-templates';
            const method = currentLoadedTemplateId ? 'PUT' : 'POST';
            
            try {
                const res = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        name: name,
                        connection_key: connKey,
                        table_name: table,
                        configuration: {
                            columns: columns,
                            filters: filters
                        }
                    })
                });
                
                const data = await res.json();
                if (data.success) {
                    showToast(`Report template ${currentLoadedTemplateId ? 'updated' : 'saved'} successfully.`, 'success');
                    hideSaveReportModal();
                    
                    if (currentLoadedTemplateId) {
                        // Update existing template in list
                        const index = templates.findIndex(t => t.id === currentLoadedTemplateId);
                        if (index !== -1) templates[index] = data.template;
                    } else {
                        // Append new template to list
                        templates.unshift(data.template);
                        currentLoadedTemplateId = data.template.id; // Mark as loaded so subsequent saves update it
                    }
                    renderTemplateList();
                } else {
                    showToast(data.message || 'Failed to save template.', 'error');
                }
            } catch (e) {
                showToast('Error saving template.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        async function loadTemplate(id) {
            const template = templates.find(t => t.id === id);
            if (!template) return;
            
            currentLoadedTemplateId = id;
            
            document.getElementById('templateDropdown').classList.add('hidden');
            document.getElementById('templateDropdownIcon').style.transform = 'rotate(0deg)';
            
            // Set connection
            const connSelect = document.getElementById('rbConnection');
            connSelect.value = template.connection_key;
            
            // We don't trigger onConnectionChange() because it resets everything, instead we manually select table
            document.getElementById('rbTableSearch').disabled = false;
            document.getElementById('rbTableSearch').value = template.table_name;
            
            // Load columns
            showToast('Loading template...', 'info');
            
            const tableChangeSuccess = await loadTemplateColumns(template.connection_key, template.table_name);
            if (!tableChangeSuccess) return;
            
            // Select columns
            let config = template.configuration;
            if (typeof config === 'string') {
                try {
                    config = JSON.parse(config);
                } catch (e) {
                    console.error('Failed to parse template configuration', e);
                    config = {};
                }
            }
            
            const savedCols = config.columns || [];
            
            // Re-assign available and selected columns based on template
            selectedColumns = savedCols;
            availableColumns = allColumns.filter(c => !savedCols.includes(c)).sort();
            updateColumnUI();
            
            // Load filters
            const container = document.getElementById('rbFiltersContainer');
            container.innerHTML = ''; // clear existing
            
            const filters = config.filters || [];
            filters.forEach(f => {
                addReportFilterRow(f.field, f.operator, f.value);
            });
            
            checkEmptyReportFilters();
            
            showToast('Template loaded successfully!', 'success');
            
            // Switch to columns step
            switchTab('columns');
        }
        
        async function loadTemplateColumns(connKey, table) {
            try {
                const baseUrl = "/api/report-builder";
                const res = await fetch(`${baseUrl}/columns?connection=${encodeURIComponent(connKey)}&table=${encodeURIComponent(table)}`);
                const data = await res.json();
                if (data.success) {
                    allColumns = data.columns;
                    renderColumns(allColumns);
                    populateFilterDataList(allColumns);
                    showSteps();
                    return true;
                } else {
                    showToast(data.message || 'Failed to load columns for template', 'error');
                    return false;
                }
            } catch (e) {
                console.error("Error loading template columns:", e);
                showToast('Error loading columns', 'error');
                return false;
            }
        }
        
        async function deleteTemplate(id) {
            if (!confirm('Are you sure you want to delete this saved report template?')) return;
            
            try {
                const res = await fetch(`/report-templates/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                const data = await res.json();
                if (data.success) {
                    showToast('Template deleted.', 'success');
                    templates = templates.filter(t => t.id !== id);
                    renderTemplateList();
                } else {
                    showToast(data.message || 'Failed to delete template.', 'error');
                }
            } catch (e) {
                showToast('Error deleting template.', 'error');
            }
        }
    </script>
@endsection