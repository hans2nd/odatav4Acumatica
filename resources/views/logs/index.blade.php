@extends('layouts.app')

@section('title', 'Enterprise Synchronization Monitoring — PL Sync Data Warehouse')
@section('page-title', 'Enterprise Monitoring Console')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex flex-col lg:flex-row gap-6 animate-fade-up">
    <!-- Advanced Filter Sidebar -->
    <aside class="w-full lg:w-72 flex flex-col gap-6 shrink-0">
        <!-- Global Search -->
        <div class="bg-white rounded-2xl border border-surface-200/60 p-5 shadow-card">
            <h3 class="text-label-lg font-semibold text-surface-800 mb-3">Global Search</h3>
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-[18px]">search</span>
                <input type="text" id="searchInput" placeholder="Search by ID, Entity, Error..." 
                    class="w-full pl-9 pr-4 py-2 bg-surface-50 border border-surface-200 rounded-xl text-body-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all outline-none">
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl border border-surface-200/60 p-5 shadow-card flex-1">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-label-lg font-semibold text-surface-800">Advanced Filters</h3>
                <button onclick="resetFilters()" class="text-label-sm text-indigo-600 hover:text-indigo-800 font-medium">Reset</button>
            </div>
            
            <form id="filterForm" class="space-y-5">
                <!-- Status -->
                <div>
                    <label class="block text-label-sm font-medium text-surface-700 mb-2">Execution Status</label>
                    <select name="status[]" multiple class="w-full bg-surface-50 border border-surface-200 rounded-xl text-body-sm p-2 outline-none h-24">
                        <option value="running">Running</option>
                        <option value="paused">Paused</option>
                        <option value="success">Success / Completed</option>
                        <option value="failed">Failed</option>
                        <option value="stopped">Stopped</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Type -->
                <div>
                    <label class="block text-label-sm font-medium text-surface-700 mb-2">Execution Source</label>
                    <select name="source[]" multiple class="w-full bg-surface-50 border border-surface-200 rounded-xl text-body-sm p-2 outline-none h-16">
                        <option value="manual">Manual</option>
                        <option value="schedule">Schedule</option>
                    </select>
                </div>

                <!-- Date Range -->
                <div>
                    <label class="block text-label-sm font-medium text-surface-700 mb-2">Date Range</label>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" name="date_from" class="w-full bg-surface-50 border border-surface-200 rounded-lg text-body-sm px-2 py-1.5 outline-none">
                        <input type="date" name="date_to" class="w-full bg-surface-50 border border-surface-200 rounded-lg text-body-sm px-2 py-1.5 outline-none">
                    </div>
                </div>

                <!-- Duration -->
                <div>
                    <label class="block text-label-sm font-medium text-surface-700 mb-2">Duration</label>
                    <select name="duration" class="w-full bg-surface-50 border border-surface-200 rounded-xl text-body-sm px-3 py-2 outline-none">
                        <option value="">All Durations</option>
                        <option value="<1m">&lt; 1 Minute</option>
                        <option value="1-5m">1 - 5 Minutes</option>
                        <option value="5-15m">5 - 15 Minutes</option>
                        <option value="15-30m">15 - 30 Minutes</option>
                        <option value="30-60m">30 - 60 Minutes</option>
                        <option value=">60m">&gt; 60 Minutes</option>
                    </select>
                </div>

                <!-- Sort -->
                <div>
                    <label class="block text-label-sm font-medium text-surface-700 mb-2">Sort By</label>
                    <div class="flex gap-2">
                        <select name="sort_by" class="flex-1 bg-surface-50 border border-surface-200 rounded-l-xl text-body-sm px-2 py-2 outline-none">
                            <option value="id">Execution ID</option>
                            <option value="started_at">Started Time</option>
                            <option value="duration_seconds">Duration</option>
                            <option value="rows_per_second">Rows/sec</option>
                            <option value="records_inserted">Rows Processed</option>
                        </select>
                        <select name="sort_order" class="w-20 bg-surface-50 border border-surface-200 rounded-r-xl border-l-0 text-body-sm px-1 py-2 outline-none">
                            <option value="desc">DESC</option>
                            <option value="asc">ASC</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="w-full py-2.5 bg-surface-800 text-white rounded-xl font-label-md hover:bg-surface-700 transition-colors shadow-sm">
                    Apply Filters
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col gap-6 min-w-0">
        
        <!-- Top Toolbar & KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm flex flex-col">
                <span class="text-label-sm text-surface-500 font-medium">Total Executions</span>
                <div class="flex items-end justify-between mt-1">
                    <span class="text-h3 text-surface-800" id="kpi-total">0</span>
                    <span class="text-label-xs bg-surface-100 text-surface-600 px-2 py-0.5 rounded" id="kpi-today-total">0 today</span>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm flex flex-col">
                <span class="text-label-sm text-surface-500 font-medium">Running / Paused</span>
                <div class="flex items-end justify-between mt-1">
                    <span class="text-h3 text-amber-600" id="kpi-running">0</span>
                    <div class="w-2 h-2 rounded-full bg-amber-500 animate-pulse-dot"></div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm flex flex-col">
                <span class="text-label-sm text-surface-500 font-medium">Failed</span>
                <div class="flex items-end justify-between mt-1">
                    <span class="text-h3 text-red-600" id="kpi-failed">0</span>
                    <span class="text-label-xs bg-red-50 text-red-600 px-2 py-0.5 rounded border border-red-100" id="kpi-today-failed">0 today</span>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm flex flex-col">
                <span class="text-label-sm text-surface-500 font-medium">Peak Performance</span>
                <div class="flex items-end justify-between mt-1">
                    <span class="text-h3 text-emerald-600" id="kpi-peak-speed">0 <span class="text-body-xs font-normal text-surface-500">rows/s</span></span>
                    <span class="text-label-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded border border-blue-100" id="kpi-peak-mem">0 MB</span>
                </div>
            </div>
        </div>

        <!-- Analytics Chart -->
        <div class="bg-white rounded-2xl border border-surface-200/60 p-5 shadow-card h-64">
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-label-md font-semibold text-surface-800">Execution Trend (Last 7 Days)</h3>
            </div>
            <div class="relative h-48 w-full">
                <canvas id="trendChart"></canvas>
            </div>
        </div>

        <!-- Data Table Container -->
        <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100 flex flex-wrap justify-between items-center gap-4">
                <div class="flex items-center gap-4">
                    <h2 class="text-h4 text-surface-800">Log Entries</h2>
                    
                    <div class="flex items-center gap-2 bg-surface-50 border border-surface-200 rounded-lg px-2 py-1">
                        <span class="text-label-xs font-medium text-surface-500 uppercase tracking-wider">Live View</span>
                        <select id="autoRefresh" class="bg-transparent text-label-sm font-semibold text-surface-800 outline-none cursor-pointer">
                            <option value="0">Off (Manual)</option>
                            <option value="5000">Auto (5s)</option>
                            <option value="10000">Auto (10s)</option>
                            <option value="30000">Auto (30s)</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <button onclick="fetchData()" class="btn-icon bg-surface-100 hover:bg-surface-200 text-surface-600 rounded-lg p-2 transition-colors" title="Refresh Now">
                        <span class="material-symbols-outlined text-[18px]">refresh</span>
                    </button>
                    <div class="h-6 w-px bg-surface-200 mx-1"></div>
                    <button onclick="openClearByDateModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 font-semibold text-label-sm transition-colors border border-red-200/50 shadow-sm">
                        <span class="material-symbols-outlined text-[16px]">calendar_today</span> Clear by Date
                    </button>
                    <div class="h-6 w-px bg-surface-200 mx-1"></div>
                    <button onclick="exportData('csv')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-semibold text-label-sm transition-colors border border-emerald-200/50 shadow-sm">
                        <span class="material-symbols-outlined text-[16px]">csv</span> Export CSV
                    </button>
                    <button onclick="exportData('excel')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 font-semibold text-label-sm transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-[16px]">table</span> Export Excel
                    </button>
                </div>
            </div>
            
            <!-- Bulk Actions Toolbar -->
            <div id="bulkActionsToolbar" class="hidden px-5 py-3 bg-indigo-50 border-b border-surface-200 flex flex-wrap items-center justify-between gap-4 transition-all">
                <span class="text-label-md font-semibold text-indigo-900"><span id="selectedCount">0</span> log(s) selected</span>
                <div class="flex items-center gap-2">
                    <button onclick="sendBulkAction('resume')" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-label-sm font-medium transition-colors shadow-sm"><span class="material-symbols-outlined text-[16px]">play_arrow</span> Resume</button>
                    <button onclick="sendBulkAction('pause')" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-label-sm font-medium transition-colors shadow-sm"><span class="material-symbols-outlined text-[16px]">pause</span> Pause</button>
                    <button onclick="sendBulkAction('stop')" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-label-sm font-medium transition-colors shadow-sm"><span class="material-symbols-outlined text-[16px]">stop</span> Stop</button>
                    <div class="w-px h-5 bg-indigo-200 mx-1"></div>
                    <button onclick="sendBulkAction('delete')" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-label-sm font-medium transition-colors shadow-sm"><span class="material-symbols-outlined text-[16px]">delete</span> Delete Selected</button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-surface-100 bg-surface-50/50">
                            <th class="py-3 px-4 w-12"><input type="checkbox" id="selectAllCheckbox" onchange="toggleAllCheckboxes(this)" class="rounded text-indigo-600 focus:ring-indigo-500 border-surface-300 w-4 h-4 cursor-pointer"></th>
                            <th class="py-3 px-4 text-label-xs text-surface-500 font-bold uppercase tracking-wider w-16">ID</th>
                            <th class="py-3 px-4 text-label-xs text-surface-500 font-bold uppercase tracking-wider">Entity</th>
                            <th class="py-3 px-4 text-label-xs text-surface-500 font-bold uppercase tracking-wider">Metrics</th>
                            <th class="py-3 px-4 text-label-xs text-surface-500 font-bold uppercase tracking-wider">Performance</th>
                            <th class="py-3 px-4 text-label-xs text-surface-500 font-bold uppercase tracking-wider">Status</th>
                            <th class="py-3 px-4 text-label-xs text-surface-500 font-bold uppercase tracking-wider text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="text-body-md text-surface-700 divide-y divide-surface-100">
                        <!-- JS renders rows here -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-5 py-3 border-t border-surface-100 flex items-center justify-between bg-surface-50" id="pagination">
                <span class="text-label-sm text-surface-500" id="pageInfo">Showing 0 to 0 of 0</span>
                <div class="flex gap-1" id="pageButtons">
                    <!-- JS renders buttons here -->
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Detailed View Modal -->
<div id="detailModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-surface-900/60 backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl mx-4 transform transition-transform scale-95" id="detailModalContent">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-surface-100 flex justify-between items-center bg-surface-50 rounded-t-2xl">
            <div>
                <h3 class="text-h3 text-surface-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-indigo-600">monitor_heart</span>
                    Execution Details <span id="modalLogId" class="text-surface-400 font-mono ml-2">#0</span>
                </h3>
            </div>
            <button type="button" onclick="closeDetailModal()" class="text-surface-400 hover:text-surface-600 transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <!-- Content -->
        <div class="px-6 py-5 overflow-y-auto flex-1 custom-scrollbar">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Info Card -->
                <div class="col-span-1 border border-surface-100 rounded-xl p-4 bg-white shadow-sm">
                    <h4 class="text-label-sm font-bold text-surface-500 uppercase tracking-wider mb-4 border-b border-surface-100 pb-2">General Info</h4>
                    <div class="space-y-3 text-body-sm">
                        <div class="flex justify-between"><span class="text-surface-500">Target Entity</span> <span class="font-semibold text-surface-800" id="modEntity"></span></div>
                        <div class="flex justify-between"><span class="text-surface-500">OData Entity</span> <span class="font-mono text-surface-600 bg-surface-50 px-1 rounded" id="modOdata"></span></div>
                        <div class="flex justify-between"><span class="text-surface-500">Type</span> <span class="font-semibold text-surface-800" id="modType"></span></div>
                        <div class="flex justify-between"><span class="text-surface-500">Source</span> <span class="font-semibold text-surface-800" id="modSource"></span></div>
                        <div class="flex justify-between"><span class="text-surface-500">Status</span> <span class="font-bold px-2 py-0.5 rounded text-[11px] uppercase tracking-wider" id="modStatus"></span></div>
                    </div>
                </div>

                <!-- Timeline Card -->
                <div class="col-span-1 border border-surface-100 rounded-xl p-4 bg-white shadow-sm">
                    <h4 class="text-label-sm font-bold text-surface-500 uppercase tracking-wider mb-4 border-b border-surface-100 pb-2">Timeline</h4>
                    <div class="space-y-3 text-body-sm">
                        <div class="flex justify-between"><span class="text-surface-500">Started At</span> <span class="font-mono text-surface-800" id="modStart"></span></div>
                        <div class="flex justify-between"><span class="text-surface-500">Completed At</span> <span class="font-mono text-surface-800" id="modEnd"></span></div>
                        <div class="flex justify-between"><span class="text-surface-500">Duration</span> <span class="font-mono text-surface-800 bg-surface-50 px-1 rounded" id="modDuration"></span></div>
                        <div class="flex justify-between"><span class="text-surface-500">Current Stage</span> <span class="font-semibold text-surface-800" id="modStage"></span></div>
                    </div>
                </div>

                <!-- Performance Card -->
                <div class="col-span-1 border border-surface-100 rounded-xl p-4 bg-white shadow-sm">
                    <h4 class="text-label-sm font-bold text-surface-500 uppercase tracking-wider mb-4 border-b border-surface-100 pb-2">Performance Metrics</h4>
                    <div class="space-y-3 text-body-sm">
                        <div class="flex justify-between"><span class="text-surface-500">Rows Fetched</span> <span class="font-mono text-blue-600 font-semibold" id="modFetched"></span></div>
                        <div class="flex justify-between"><span class="text-surface-500">Rows Inserted</span> <span class="font-mono text-emerald-600 font-semibold" id="modInserted"></span></div>
                        <div class="flex justify-between"><span class="text-surface-500">New Data</span> <span class="font-mono text-amber-600 font-semibold" id="modUpdated"></span></div>
                        <div class="flex justify-between"><span class="text-surface-500">Speed (Rows/sec)</span> <span class="font-mono text-surface-800" id="modSpeed"></span></div>
                        <div class="flex justify-between"><span class="text-surface-500">Peak Memory</span> <span class="font-mono text-surface-800" id="modMem"></span></div>
                    </div>
                </div>
            </div>

            <!-- Bottlenecks Section -->
            <div id="bottlenecksSection" class="hidden mb-8">
                <h4 class="text-h4 text-surface-800 mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-500">warning</span>
                    Detected Bottlenecks
                </h4>
                <div class="bg-amber-50 border border-amber-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-body-sm">
                        <thead class="bg-amber-100/50 text-amber-800 text-label-xs uppercase tracking-wider">
                            <tr>
                                <th class="py-2 px-4">Pipeline</th>
                                <th class="py-2 px-4">Stage</th>
                                <th class="py-2 px-4">Duration</th>
                                <th class="py-2 px-4">Threshold</th>
                                <th class="py-2 px-4">Recommendation</th>
                            </tr>
                        </thead>
                        <tbody id="bottlenecksBody" class="divide-y divide-amber-100">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Error Section -->
            <div id="errorSection" class="hidden">
                <h4 class="text-h4 text-red-800 mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    Error Details
                </h4>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                    <p id="modErrorMsg" class="text-body-sm text-red-700 font-semibold mb-3"></p>
                    <pre id="modStackTrace" class="text-code text-[11px] text-red-900 bg-red-100/50 p-3 rounded-lg overflow-x-auto whitespace-pre-wrap max-h-64"></pre>
                </div>
            </div>

        </div>
        
        <!-- Footer -->
        <div class="px-6 py-4 border-t border-surface-100 flex justify-end rounded-b-2xl bg-white gap-3">
            <button type="button" onclick="closeDetailModal()" class="px-5 py-2 border border-surface-200 bg-white text-surface-700 font-label-md rounded-xl hover:bg-surface-50 transition-all focus:ring-2 focus:ring-surface-400 outline-none">Close Panel</button>
        </div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
.btn-action { @apply inline-flex items-center justify-center w-8 h-8 rounded-lg transition-all outline-none focus:ring-2 focus:ring-surface-400/20 shadow-sm border; }
</style>

<!-- Clear By Date Modal -->
<div id="clearDateModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-surface-900/60 backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl mx-4 transform transition-transform scale-95" id="clearDateModalContent">
        <div class="px-6 py-4 border-b border-surface-100 flex justify-between items-center bg-surface-50 rounded-t-2xl">
            <h3 class="text-h4 text-surface-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-red-600">delete_sweep</span> Clear Logs by Date
            </h3>
            <button type="button" onclick="closeClearByDateModal()" class="text-surface-400 hover:text-surface-600 transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="px-6 py-5">
            <p class="text-body-sm text-surface-600 mb-4">Select a date range to permanently delete sync logs. This action cannot be undone.</p>
            <form id="clearDateForm" class="space-y-4">
                <div>
                    <label class="block text-label-sm font-medium text-surface-700 mb-1">From Date</label>
                    <input type="date" id="clearDateFrom" required class="w-full bg-surface-50 border border-surface-200 rounded-xl text-body-sm px-3 py-2 outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-label-sm font-medium text-surface-700 mb-1">To Date</label>
                    <input type="date" id="clearDateTo" required class="w-full bg-surface-50 border border-surface-200 rounded-xl text-body-sm px-3 py-2 outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500">
                </div>
            </form>
        </div>
        <div class="px-6 py-4 border-t border-surface-100 flex justify-end gap-3 bg-surface-50 rounded-b-2xl">
            <button type="button" onclick="closeClearByDateModal()" class="px-4 py-2 rounded-xl text-label-sm font-semibold text-surface-600 hover:bg-surface-200 transition-colors">Cancel</button>
            <button type="button" onclick="submitClearByDate()" class="px-4 py-2 rounded-xl bg-red-600 text-white text-label-sm font-semibold hover:bg-red-700 transition-colors shadow-sm">Delete Logs</button>
        </div>
    </div>
</div>

<script>
    let currentPage = 1;
    let refreshInterval = null;
    let trendChartObj = null;

    // Elements
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');
    const autoRefresh = document.getElementById('autoRefresh');
    const tableBody = document.getElementById('tableBody');
    const pagination = document.getElementById('pagination');
    const pageInfo = document.getElementById('pageInfo');
    const pageButtons = document.getElementById('pageButtons');

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        initChart();
        fetchAnalytics();
        fetchData();
        
        // Debounce search
        let timeout = null;
        searchInput.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                currentPage = 1;
                fetchData();
            }, 500);
        });

        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            currentPage = 1;
            fetchData();
        });

        autoRefresh.addEventListener('change', (e) => {
            clearInterval(refreshInterval);
            const val = parseInt(e.target.value);
            if (val > 0) {
                refreshInterval = setInterval(() => {
                    fetchData(true); // silent refresh
                    fetchAnalytics();
                }, val);
            }
        });
    });

    function getFilterParams() {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams();
        
        if (searchInput.value) params.append('search', searchInput.value);
        
        for (const [key, value] of formData.entries()) {
            if (value) params.append(key, value);
        }
        
        params.append('page', currentPage);
        return params;
    }

    function resetFilters() {
        filterForm.reset();
        searchInput.value = '';
        // reset multiselects explicitly
        Array.from(filterForm.querySelectorAll('select[multiple]')).forEach(sel => {
            Array.from(sel.options).forEach(opt => opt.selected = false);
        });
        currentPage = 1;
        fetchData();
    }

    async function fetchData(silent = false) {
        if (!silent) tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-10"><span class="material-symbols-outlined animate-spin text-surface-400 text-3xl">sync</span></td></tr>';
        
        const params = getFilterParams();
        try {
            const res = await fetch(`/api/sync/logs?${params.toString()}`);
            const data = await res.json();
            renderTable(data);
        } catch (e) {
            console.error(e);
            if (!silent) tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-10 text-red-500">Failed to load data.</td></tr>';
        }
    }

    async function fetchAnalytics() {
        try {
            const res = await fetch(`/api/sync/analytics`);
            const data = await res.json();
            
            // Update KPIs
            document.getElementById('kpi-total').textContent = new Intl.NumberFormat().format(data.kpis.total_executions);
            document.getElementById('kpi-today-total').textContent = new Intl.NumberFormat().format(data.kpis.today_executions) + ' today';
            document.getElementById('kpi-running').textContent = new Intl.NumberFormat().format(data.kpis.running);
            document.getElementById('kpi-failed').textContent = new Intl.NumberFormat().format(data.kpis.failed);
            document.getElementById('kpi-today-failed').textContent = new Intl.NumberFormat().format(data.kpis.today_failed) + ' today';
            document.getElementById('kpi-peak-speed').innerHTML = new Intl.NumberFormat().format(data.kpis.peak_rows_sec) + ' <span class="text-body-xs font-normal text-surface-500">rows/s</span>';
            document.getElementById('kpi-peak-mem').textContent = new Intl.NumberFormat().format(data.kpis.peak_memory_mb) + ' MB';
            
            // Update Chart
            updateChart(data.trends);
        } catch (e) {
            console.error('Failed to load analytics', e);
        }
    }

    function renderTable(data) {
        if (!data.data || data.data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-10 text-surface-500">No logs found matching your criteria.</td></tr>';
            pageInfo.textContent = 'Showing 0 to 0 of 0';
            pageButtons.innerHTML = '';
            return;
        }

        tableBody.innerHTML = data.data.map(log => `
            <tr class="hover:bg-surface-50/50 transition-colors">
                <td class="py-3 px-4 align-top">
                    <input type="checkbox" value="${log.id}" class="row-checkbox rounded text-indigo-600 focus:ring-indigo-500 border-surface-300 w-4 h-4 cursor-pointer mt-0.5" onchange="updateBulkToolbar()">
                </td>
                <td class="py-3 px-4 align-top">
                    <span class="font-mono text-label-md text-surface-600 block mb-1">#${log.id}</span>
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold uppercase text-surface-400">
                        <span class="material-symbols-outlined text-[14px]">${log.source === 'schedule' ? 'schedule' : 'bolt'}</span>
                        ${log.source ?? 'manual'}
                    </span>
                </td>
                
                <td class="py-3 px-4 align-top">
                    <div class="font-semibold text-surface-800">${log.entity_description ?? log.entity_key}</div>
                    <div class="text-[11px] font-mono text-surface-400 mt-1">${log.odata_entity || '-'}</div>
                    <div class="mt-2 text-label-xs text-surface-500 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">schedule</span> 
                        ${formatDate(log.started_at)}
                    </div>
                </td>
                
                <td class="py-3 px-4 align-top">
                    <div class="flex items-center gap-3 text-label-sm mb-1">
                        <div class="flex items-center gap-1 text-blue-600"><span class="material-symbols-outlined text-[14px]">download</span> ${new Intl.NumberFormat().format(log.records_fetched)}</div>
                        <div class="flex items-center gap-1 text-emerald-600"><span class="material-symbols-outlined text-[14px]">save</span> ${new Intl.NumberFormat().format(log.records_inserted)}</div>
                    </div>
                    <div class="text-[11px] text-surface-400 font-mono">
                        Dur: ${log.duration_seconds ? log.duration_seconds+'s' : '-'}
                    </div>
                </td>
                
                <td class="py-3 px-4 align-top">
                    <div class="flex items-center gap-1.5 mb-1">
                        <span class="material-symbols-outlined text-[14px] text-surface-400">speed</span>
                        <span class="font-mono text-label-sm">${new Intl.NumberFormat().format(log.rows_per_second ?? 0)} r/s</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[14px] text-surface-400">memory</span>
                        <span class="font-mono text-label-sm">${new Intl.NumberFormat().format(log.peak_memory_mb ?? 0)} MB</span>
                    </div>
                </td>
                
                <td class="py-3 px-4 align-top">
                    <div class="mb-1">${getStatusBadge(log.status)}</div>
                    <div class="text-label-xs text-surface-400 truncate max-w-[120px]" title="${log.current_stage || ''}">
                        ${formatStage(log.current_stage)}
                    </div>
                </td>
                
                <td class="py-3 px-4 align-top text-right space-y-1">
                    <div class="flex items-center justify-end gap-1">
                        <button onclick="viewDetails(${log.id})" class="btn-action bg-surface-50 text-surface-600 border-surface-200 hover:bg-surface-100" title="View Details">
                            <span class="material-symbols-outlined text-[16px]">visibility</span>
                        </button>
                        ${getControlButtons(log)}
                    </div>
                </td>
            </tr>
        `).join('');

        // Pagination info
        pageInfo.textContent = `Showing ${data.from || 0} to ${data.to || 0} of ${data.total}`;
        
        // Pagination buttons
        let btns = '';
        data.links.forEach(link => {
            if (!link.url) {
                btns += `<button disabled class="px-2 py-1 text-label-sm text-surface-400 bg-surface-50 border border-surface-200 rounded opacity-50 cursor-not-allowed">${link.label}</button>`;
            } else {
                const pageNum = new URL(link.url).searchParams.get('page');
                const activeClass = link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-surface-700 border-surface-200 hover:bg-surface-50';
                btns += `<button onclick="goToPage(${pageNum})" class="px-3 py-1 text-label-sm rounded border ${activeClass} transition-colors">${link.label}</button>`;
            }
        });
        pageButtons.innerHTML = btns;
        
        // Reset select all checkbox
        document.getElementById('selectAllCheckbox').checked = false;
        updateBulkToolbar();
    }

    function toggleAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => cb.checked = source.checked);
        updateBulkToolbar();
    }

    function updateBulkToolbar() {
        const checked = document.querySelectorAll('.row-checkbox:checked').length;
        const toolbar = document.getElementById('bulkActionsToolbar');
        const countSpan = document.getElementById('selectedCount');
        
        countSpan.textContent = checked;
        if (checked > 0) {
            toolbar.classList.remove('hidden');
        } else {
            toolbar.classList.add('hidden');
        }
    }

    async function sendBulkAction(action) {
        const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
        const ids = Array.from(checkedBoxes).map(cb => cb.value);
        
        if (ids.length === 0) return;
        
        if (action === 'delete' && !confirm(`Are you sure you want to permanently delete ${ids.length} selected logs?`)) return;
        if (action === 'stop' && !confirm(`Graceful stop will finish the current batch safely for ${ids.length} logs. Proceed?`)) return;

        try {
            const res = await fetch('/api/sync/logs/bulk', {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json' 
                },
                body: JSON.stringify({ action: action, target_type: 'ids', ids: ids })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                document.getElementById('selectAllCheckbox').checked = false;
                updateBulkToolbar();
                fetchData(true);
                fetchAnalytics();
            } else {
                alert(data.message || 'Failed to process bulk action');
            }
        } catch (e) {
            alert('Error: ' + e.message);
        }
    }

    function openClearByDateModal() {
        const modal = document.getElementById('clearDateModal');
        const content = document.getElementById('clearDateModalContent');
        
        // Default to last 7 days
        const today = new Date();
        const lastWeek = new Date(today);
        lastWeek.setDate(lastWeek.getDate() - 7);
        
        document.getElementById('clearDateTo').value = today.toISOString().split('T')[0];
        document.getElementById('clearDateFrom').value = lastWeek.toISOString().split('T')[0];

        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            content.classList.remove('scale-95');
        }, 10);
    }

    function closeClearByDateModal() {
        const modal = document.getElementById('clearDateModal');
        const content = document.getElementById('clearDateModalContent');
        modal.classList.add('opacity-0');
        content.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    async function submitClearByDate() {
        const dateFrom = document.getElementById('clearDateFrom').value;
        const dateTo = document.getElementById('clearDateTo').value;
        
        if (!dateFrom || !dateTo) {
            alert('Please select both dates');
            return;
        }

        if (!confirm(`Are you sure you want to permanently delete all logs between ${dateFrom} and ${dateTo}?`)) return;

        try {
            const res = await fetch('/api/sync/logs/bulk', {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json' 
                },
                body: JSON.stringify({ 
                    action: 'delete', 
                    target_type: 'date_range', 
                    date_from: dateFrom,
                    date_to: dateTo
                })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                alert(data.message);
                closeClearByDateModal();
                fetchData(true);
                fetchAnalytics();
            } else {
                alert(data.message || 'Failed to delete logs');
            }
        } catch (e) {
            alert('Error: ' + e.message);
        }
    }

    function goToPage(page) {
        currentPage = page;
        fetchData();
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

    function formatStage(stage) {
        if (!stage) return 'Initializing';
        return stage.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    function getStatusBadge(status) {
        if (status === 'success' || status === 'completed') {
            return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[11px] font-bold uppercase border border-emerald-200/50"><div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div> Done</span>`;
        } else if (status === 'running') {
            return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 text-amber-700 text-[11px] font-bold uppercase border border-amber-200/50"><div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse-dot"></div> Run</span>`;
        } else if (status === 'paused') {
            return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-blue-50 text-blue-700 text-[11px] font-bold uppercase border border-blue-200/50"><div class="w-1.5 h-1.5 rounded-full bg-blue-500"></div> Pause</span>`;
        } else if (status === 'failed' || status === 'cancelled' || status === 'stopped') {
            return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-red-50 text-red-700 text-[11px] font-bold uppercase border border-red-200/50"><div class="w-1.5 h-1.5 rounded-full bg-red-500"></div> ${status}</span>`;
        }
        return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-surface-100 text-surface-600 text-[11px] font-bold uppercase"><div class="w-1.5 h-1.5 rounded-full bg-surface-400"></div> ${status}</span>`;
    }

    function getControlButtons(log) {
        if (log.status === 'running') {
            return `
                <button onclick="sendControl(${log.id}, 'pause')" class="btn-action bg-blue-50 text-blue-600 border-blue-200 hover:bg-blue-100" title="Pause"><span class="material-symbols-outlined text-[16px]">pause</span></button>
                <button onclick="sendControl(${log.id}, 'stop')" class="btn-action bg-red-50 text-red-600 border-red-200 hover:bg-red-100" title="Stop"><span class="material-symbols-outlined text-[16px]">stop</span></button>
            `;
        } else if (log.status === 'paused') {
            return `
                <button onclick="sendControl(${log.id}, 'resume')" class="btn-action bg-emerald-50 text-emerald-600 border-emerald-200 hover:bg-emerald-100" title="Resume"><span class="material-symbols-outlined text-[16px]">play_arrow</span></button>
                <button onclick="sendControl(${log.id}, 'stop')" class="btn-action bg-red-50 text-red-600 border-red-200 hover:bg-red-100" title="Stop"><span class="material-symbols-outlined text-[16px]">stop</span></button>
            `;
        }
        return '';
    }

    async function sendControl(id, action) {
        if (action === 'stop' && !confirm("Graceful stop will finish the current batch safely. Proceed?")) return;
        try {
            const res = await fetch(`/sync/control/${id}`, {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json' 
                },
                body: JSON.stringify({ action: action })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                fetchData(true);
                fetchAnalytics();
            } else {
                alert(data.message || 'Failed to send signal');
            }
        } catch (e) {
            alert('Error: ' + e.message);
        }
    }

    function exportData(format) {
        const params = getFilterParams();
        params.delete('page'); // export all matching pages
        params.append('format', format);
        window.location.href = `/api/sync/export?${params.toString()}`;
    }

    // Modal Logic
    async function viewDetails(id) {
        try {
            const res = await fetch(`/api/sync/logs/${id}/details`);
            const data = await res.json();
            if (data.success) {
                populateModal(data.log);
                const modal = document.getElementById('detailModal');
                const content = document.getElementById('detailModalContent');
                modal.classList.remove('hidden');
                // Animate in
                setTimeout(() => {
                    modal.classList.remove('opacity-0');
                    content.classList.remove('scale-95');
                }, 10);
            }
        } catch (e) {
            alert('Failed to load details: ' + e.message);
        }
    }

    function populateModal(log) {
        document.getElementById('modalLogId').textContent = '#' + log.id;
        document.getElementById('modEntity').textContent = log.entity_description || log.entity_key;
        document.getElementById('modOdata').textContent = log.odata_entity || '-';
        document.getElementById('modType').textContent = log.type;
        document.getElementById('modSource').textContent = log.source;
        
        const statusEl = document.getElementById('modStatus');
        statusEl.textContent = log.status;
        statusEl.className = 'font-bold px-2 py-0.5 rounded text-[11px] uppercase tracking-wider ' + 
            (log.status === 'success' || log.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
             log.status === 'running' ? 'bg-amber-100 text-amber-800' : 
             log.status === 'failed' || log.status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-surface-200 text-surface-700');

        document.getElementById('modStart').textContent = formatDate(log.started_at);
        document.getElementById('modEnd').textContent = formatDate(log.completed_at);
        document.getElementById('modDuration').textContent = log.duration_seconds ? log.duration_seconds + 's' : '-';
        document.getElementById('modStage').textContent = formatStage(log.current_stage);

        document.getElementById('modFetched').textContent = new Intl.NumberFormat().format(log.records_fetched);
        document.getElementById('modInserted').textContent = new Intl.NumberFormat().format(log.records_inserted);
        document.getElementById('modUpdated').textContent = new Intl.NumberFormat().format(log.records_updated || 0);
        document.getElementById('modSpeed').textContent = new Intl.NumberFormat().format(log.rows_per_second || 0);
        document.getElementById('modMem').textContent = new Intl.NumberFormat().format(log.peak_memory_mb || 0) + ' MB';

        // Bottlenecks
        const bSection = document.getElementById('bottlenecksSection');
        const bBody = document.getElementById('bottlenecksBody');
        if (log.bottlenecks && log.bottlenecks.length > 0) {
            bBody.innerHTML = log.bottlenecks.map(b => `
                <tr class="hover:bg-amber-100/50">
                    <td class="py-2 px-4 font-semibold">${b.pipeline}</td>
                    <td class="py-2 px-4 font-mono">${b.stage}</td>
                    <td class="py-2 px-4 font-mono">${b.duration_ms}ms</td>
                    <td class="py-2 px-4 font-mono text-surface-500">${b.threshold_ms}ms</td>
                    <td class="py-2 px-4">${b.recommendation || '-'}</td>
                </tr>
            `).join('');
            bSection.classList.remove('hidden');
        } else {
            bSection.classList.add('hidden');
        }

        // Errors
        const errSection = document.getElementById('errorSection');
        if (log.error_message) {
            document.getElementById('modErrorMsg').textContent = log.error_message;
            document.getElementById('modStackTrace').textContent = log.stack_trace || 'No stack trace available.';
            errSection.classList.remove('hidden');
        } else {
            errSection.classList.add('hidden');
        }
    }

    function closeDetailModal() {
        const modal = document.getElementById('detailModal');
        const content = document.getElementById('detailModalContent');
        modal.classList.add('opacity-0');
        content.classList.add('scale-95');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    // Chart.js Setup
    function initChart() {
        const ctx = document.getElementById('trendChart').getContext('2d');
        Chart.defaults.font.family = 'Inter, sans-serif';
        Chart.defaults.color = '#64748b';
        
        trendChartObj = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Success',
                        data: [],
                        backgroundColor: '#10b981',
                        borderRadius: 4,
                        barPercentage: 0.6
                    },
                    {
                        label: 'Failed/Stopped',
                        data: [],
                        backgroundColor: '#ef4444',
                        borderRadius: 4,
                        barPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, border: { display: false }, ticks: { precision: 0 } }
                },
                plugins: {
                    legend: { position: 'top', align: 'end', labels: { boxWidth: 10, usePointStyle: true } }
                }
            }
        });
    }

    function updateChart(trends) {
        if (!trendChartObj || !trends) return;
        
        const labels = [];
        const successData = [];
        const failedData = [];
        
        trends.forEach(t => {
            const date = new Date(t.date).toLocaleDateString([], { month: 'short', day: 'numeric' });
            labels.push(date);
            successData.push(t.success_count);
            failedData.push(t.failed_count);
        });
        
        trendChartObj.data.labels = labels;
        trendChartObj.data.datasets[0].data = successData;
        trendChartObj.data.datasets[1].data = failedData;
        trendChartObj.update();
    }
</script>
@endsection
