@extends('layouts.app')

@section('title', 'Master Data — PL Sync Data Warehouse')
@section('page-title', 'Master Data')

@section('content')
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach ($entities as $key => $info)
            <div
                class="bg-white rounded-2xl border border-surface-200/60 p-5 flex flex-col shadow-card hover:shadow-card-hover transition-all duration-300 animate-fade-up group">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="p-2.5 bg-gradient-to-br from-sky-50 to-blue-100 rounded-xl group-hover:shadow-md transition-all duration-300">
                            <span
                                class="material-symbols-outlined text-sky-600 text-[20px]">{{ $info['config']['icon'] ?? 'database' }}</span>
                        </div>
                        <div>
                            <h3 class="text-body-md font-semibold text-surface-800">{{ $info['config']['description'] }}</h3>
                            <div class="flex flex-col gap-0.5 mt-1">
                                <p class="text-label-sm text-surface-400 font-mono">{{ $info['config']['odata_entity'] }}</p>
                                @php
                                    $sourceConnKey = $info['config']['source_connection'] ?? 'acumatica_default';
                                    $sourceConnName = $connections[$sourceConnKey]['name'] ?? $sourceConnKey;

                                    $destConnKey = $info['config']['destination_connection'] ?? 'database_default';
                                    $destConnName = $connections[$destConnKey]['name'] ?? $destConnKey;
                                @endphp
                                <div class="flex items-center gap-1 mt-1 flex-wrap">
                                    <span class="material-symbols-outlined text-[14px] text-surface-400"
                                        title="Source">database</span>
                                    <span
                                        class="text-[11px] font-medium px-1.5 py-0.5 rounded-md bg-surface-100 text-surface-600 border border-surface-200/50"
                                        title="Source">
                                        {{ $sourceConnName }}
                                    </span>

                                    <span
                                        class="material-symbols-outlined text-[14px] text-surface-400 mx-0.5">arrow_right_alt</span>

                                    <span class="material-symbols-outlined text-[14px] text-surface-400"
                                        title="Destination">storage</span>
                                    <span
                                        class="text-[11px] font-medium px-1.5 py-0.5 rounded-md bg-surface-100 text-surface-600 border border-surface-200/50"
                                        title="Destination">
                                        {{ $destConnName }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-end justify-between mt-auto">
                    <div>
                        <span
                            class="text-[28px] font-extrabold text-surface-900 leading-none tracking-tight">{{ number_format($info['records']) }}</span>
                        <p class="text-label-sm text-surface-400 mt-1">records</p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($info['table_exists'])
                            <a href="{{ route('master-data.show', $key) }}"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 hover:text-surface-800 text-label-md transition-all duration-200">
                                <span class="material-symbols-outlined text-[14px]">visibility</span> View
                            </a>
                        @endif
                        <button onclick="openScheduleModal('{{ $key }}', '{{ $info['config']['description'] }}', '{{ $info['schedule']->frequency ?? '' }}', '{{ $info['schedule']->interval ?? '' }}', '{{ $info['schedule']->time ?? '' }}', '{{ $info['schedule']->day_of_week ?? '' }}', '{{ $info['schedule']->day_of_month ?? '' }}')"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg {{ !empty($info['schedule']->frequency) ? 'bg-amber-100 text-amber-700 hover:bg-amber-200 border border-amber-300' : 'bg-surface-100 text-surface-600 hover:bg-surface-200' }} text-label-md transition-all duration-200"
                            title="Auto Sync Schedule">
                            <span class="material-symbols-outlined text-[14px]">schedule</span>
                            @php
                                $freqText = 'Off';
                                if (!empty($info['schedule']->frequency)) {
                                    if ($info['schedule']->frequency === 'minute') {
                                        $freqText = 'Every ' . ($info['schedule']->interval ?? 1) . 'm';
                                    } elseif ($info['schedule']->frequency === 'hour') {
                                        $freqText = 'Every ' . ($info['schedule']->interval ?? 1) . 'h';
                                    } else {
                                        $freqText = ucfirst($info['schedule']->frequency) . ($info['schedule']->time ? ' ' . \Carbon\Carbon::parse($info['schedule']->time)->format('H:i') : '');
                                    }
                                }
                            @endphp
                            {{ $freqText }}
                        </button>
                        <button
                            data-key="{{ $key }}"
                            data-name="{{ $info['config']['description'] }}"
                            data-filters="{{ json_encode($info['custom_filters'] ?? []) }}"
                            onclick="openSyncModal(this.dataset.key, this.dataset.name, JSON.parse(this.dataset.filters))"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 active:scale-[0.97] shadow-sm">
                            <span class="material-symbols-outlined text-[14px]">sync</span> Sync
                        </button>
                    </div>
                </div>

                @if (!$info['table_exists'])
                    <div class="mt-3 px-3 py-2 bg-amber-50 border border-amber-200/60 rounded-xl">
                        <span class="text-label-sm text-amber-700 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[14px]">info</span>
                            Table not created yet — run sync to create
                        </span>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Sync Filter Modal --}}
    <div id="syncModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm transition-all duration-200 opacity-0">
        <div
            class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col transform transition-all duration-200 scale-95 border border-surface-200/60">
            <div class="px-6 py-4 border-b border-surface-100 flex justify-between items-center">
                <div>
                    <h2 class="text-h2 text-surface-800">Sync <span id="syncModalEntityName" class="text-brand-600"></span>
                    </h2>
                    <p class="text-body-sm text-surface-500 mt-1">Configure optional filters for this synchronization.</p>
                </div>
                <button onclick="closeSyncModal()"
                    class="text-surface-400 hover:text-surface-700 p-1.5 rounded-xl hover:bg-surface-100 transition-all duration-200">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <div class="p-6 overflow-y-auto max-h-[60vh]">
                <div id="syncModalContent" class="space-y-4">
                    <div class="flex justify-between items-end">
                        <label class="text-label-lg text-surface-700">Custom Filters (Optional)</label>
                        <div class="flex gap-2">
                            <button onclick="refreshEntityFields()"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-100 text-surface-700 text-label-sm hover:bg-surface-200 transition-all duration-200"
                                title="Refresh available fields from Acumatica">
                                <span class="material-symbols-outlined text-[14px]">refresh</span> Refresh Fields
                            </button>
                            <button onclick="addFilterRow()"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-100 text-surface-700 text-label-sm hover:bg-surface-200 transition-all duration-200">
                                <span class="material-symbols-outlined text-[14px]">add</span> Add Filter
                            </button>
                        </div>
                    </div>

                    <div id="syncModalLoading" class="hidden flex items-center justify-center py-4 bg-surface-50 rounded-xl border border-surface-100">
                        <span class="material-symbols-outlined text-[20px] text-brand-500 spin mr-2">sync</span>
                        <p class="text-label-sm text-surface-500">Fetching available fields from server...</p>
                    </div>

                    <div id="filtersContainer" class="space-y-2">
                        <!-- Filter rows will be inserted here -->
                    </div>

                    <datalist id="acumaticaFieldsDataList"></datalist>

                    <div id="emptyFiltersMsg"
                        class="py-6 text-center border-2 border-dashed border-surface-200 rounded-xl bg-surface-50">
                        <span class="material-symbols-outlined text-surface-300 text-3xl mb-2 block">filter_alt_off</span>
                        <p class="text-body-md text-surface-400">No filters added. This will sync all data.</p>
                    </div>

                    <div class="flex items-center gap-2 mt-4 pt-4 border-t border-surface-100">
                        <input type="checkbox" id="saveFiltersCb"
                            class="w-4 h-4 text-brand-600 rounded border-surface-300 focus:ring-brand-500" checked>
                        <label for="saveFiltersCb" class="text-label-md text-surface-700 cursor-pointer">Save these filters
                            as default for scheduled and future syncs</label>
                    </div>

                    <!-- Modern Sync Dashboard -->
                    <div id="syncProgressContainer" class="hidden mt-6 bg-surface-50 rounded-xl border border-surface-200 overflow-hidden shadow-sm transition-all duration-300">
                        <div class="p-4 border-b border-surface-200 bg-white flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div id="syncStatusIcon" class="w-8 h-8 rounded-full bg-brand-50 flex items-center justify-center text-brand-600">
                                    <span class="material-symbols-outlined text-[18px] spin">sync</span>
                                </div>
                                <div>
                                    <h4 id="syncStageLabel" class="text-label-lg font-semibold text-surface-900">Initializing...</h4>
                                    <p id="syncProgressLabel" class="text-label-sm text-surface-500">Preparing synchronization pipeline</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div id="syncProgressPercent" class="text-h3 font-bold text-brand-600 font-mono">0%</div>
                                <div id="syncElapsedTime" class="text-label-sm text-surface-400 font-mono">00:00</div>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <div class="w-full bg-surface-200 rounded-full h-2 overflow-hidden mb-5">
                                <div id="syncProgressBar" class="bg-gradient-to-r from-brand-500 to-brand-400 h-2 rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-4">
                                <div class="bg-white p-3 rounded-lg border border-surface-100 shadow-sm flex flex-col justify-center items-center text-center">
                                    <span class="material-symbols-outlined text-[18px] text-surface-400 mb-1">database</span>
                                    <span class="text-label-sm text-surface-500 mb-0.5">Processed</span>
                                    <span id="syncStatProcessed" class="text-body-lg font-bold text-surface-800 font-mono">0</span>
                                </div>
                                <div class="bg-white p-3 rounded-lg border border-surface-100 shadow-sm flex flex-col justify-center items-center text-center">
                                    <span class="material-symbols-outlined text-[18px] text-surface-400 mb-1">speed</span>
                                    <span class="text-label-sm text-surface-500 mb-0.5">Speed</span>
                                    <span id="syncStatSpeed" class="text-body-lg font-bold text-surface-800 font-mono flex items-baseline gap-1">0 <span class="text-[10px] text-surface-400 font-normal">/s</span></span>
                                </div>
                                <div class="bg-white p-3 rounded-lg border border-surface-100 shadow-sm flex flex-col justify-center items-center text-center">
                                    <span class="material-symbols-outlined text-[18px] text-surface-400 mb-1">timer</span>
                                    <span class="text-label-sm text-surface-500 mb-0.5">ETA</span>
                                    <span id="syncStatETA" class="text-body-lg font-bold text-surface-800 font-mono">--:--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-surface-100 flex justify-end gap-3">
                <button onclick="closeSyncModal()"
                    class="px-4 py-2 rounded-xl text-surface-600 text-label-md hover:bg-surface-100 transition-all duration-200">
                    Cancel
                </button>
                <button id="btnSaveFiltersOnly" onclick="saveOnlyFilters()"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-surface-100 text-brand-600 text-label-md hover:bg-brand-50 transition-all duration-200 shadow-sm border border-brand-200">
                    <span class="material-symbols-outlined text-[16px]">save</span> Save Filters
                </button>
                <button id="btnStartSync" onclick="executeFilteredSync()"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">sync</span> Start Sync
                </button>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-surface-900/40 backdrop-blur-sm transition-opacity opacity-0"
            id="scheduleModalBackdrop"></div>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="relative w-full max-w-md p-6 overflow-hidden text-left transition-all transform bg-white shadow-2xl rounded-2xl opacity-0 scale-95"
                id="scheduleModalPanel">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-xl font-bold text-surface-900">Auto Schedule</h3>
                        <p class="text-surface-500 text-sm mt-0.5">Set automatic sync interval for <span
                                id="scheduleEntityName" class="font-semibold text-brand-600"></span></p>
                    </div>
                    <button type="button" onclick="closeScheduleModal()"
                        class="p-2 transition-colors rounded-xl text-surface-400 hover:text-surface-600 hover:bg-surface-100">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="space-y-4 text-left">
                    <div>
                        <label class="block text-sm font-medium text-surface-700 mb-1">Frequency</label>
                        <select id="scheduleFrequency" onchange="toggleScheduleFields()" class="w-full px-4 py-2.5 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition-all">
                            <option value="">Disabled (Manual Only)</option>
                            <option value="minute">Minutes</option>
                            <option value="hour">Hours</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    
                    <div id="scheduleIntervalGroup" class="hidden">
                        <label class="block text-sm font-medium text-surface-700 mb-1">Interval (e.g. Every X)</label>
                        <input type="number" id="scheduleInterval" min="1" class="w-full px-4 py-2.5 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition-all" placeholder="1">
                    </div>
                    
                    <div id="scheduleTimeGroup" class="hidden">
                        <label class="block text-sm font-medium text-surface-700 mb-1">Time</label>
                        <input type="time" id="scheduleTime" class="w-full px-4 py-2.5 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition-all">
                    </div>
                    
                    <div id="scheduleDayOfWeekGroup" class="hidden">
                        <label class="block text-sm font-medium text-surface-700 mb-1">Day of Week</label>
                        <select id="scheduleDayOfWeek" class="w-full px-4 py-2.5 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition-all">
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                        </select>
                    </div>
                    
                    <div id="scheduleDayOfMonthGroup" class="hidden">
                        <label class="block text-sm font-medium text-surface-700 mb-1">Date of Month</label>
                        <input type="number" id="scheduleDayOfMonth" min="1" max="31" class="w-full px-4 py-2.5 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition-all" placeholder="e.g. 15">
                    </div>
                </div>

                <div class="flex gap-3 mt-8">
                    <button type="button" onclick="closeScheduleModal()"
                        class="flex-1 px-4 py-2.5 text-sm font-semibold transition-colors bg-white border rounded-xl text-surface-700 border-surface-200 hover:bg-surface-50 hover:text-surface-900">
                        Cancel
                    </button>
                    <button type="button" id="btnSaveSchedule" onclick="saveSchedule()"
                        class="flex-1 px-4 py-2.5 text-sm font-semibold text-white transition-all bg-brand-600 rounded-xl hover:bg-brand-700 active:scale-[0.98] shadow-sm flex justify-center items-center gap-2">
                        <span>Save Schedule</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
    <script>
        let currentSyncEntity = null;
        let availableFields = [];

        function openSyncModal(entityKey, entityName, defaultFilters = []) {
            currentSyncEntity = entityKey;
            document.getElementById('syncModalEntityName').textContent = entityName;

            const modal = document.getElementById('syncModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.querySelector('.transform').classList.remove('scale-95');
                modal.querySelector('.transform').classList.add('scale-100');
            }, 10);

            loadEntityFields(entityKey, defaultFilters);
        }

        function closeSyncModal() {
            const modal = document.getElementById('syncModal');
            modal.classList.add('opacity-0');
            modal.querySelector('.transform').classList.add('scale-95');
            modal.querySelector('.transform').classList.remove('scale-100');

            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.getElementById('filtersContainer').innerHTML = '';
                checkEmptyFilters();
            }, 200);
        }

        async function loadEntityFields(entityKey, defaultFilters = [], refresh = false) {
            const loadingEl = document.getElementById('syncModalLoading');
            const contentEl = document.getElementById('syncModalContent');
            const btnSync = document.getElementById('btnStartSync');

            loadingEl.classList.remove('hidden');

            // Preserve current filters if refreshing
            let currentFiltersToRestore = defaultFilters;
            if (typeof currentFiltersToRestore === 'string') {
                try {
                    currentFiltersToRestore = JSON.parse(currentFiltersToRestore);
                    if (typeof currentFiltersToRestore === 'string') {
                        currentFiltersToRestore = JSON.parse(currentFiltersToRestore);
                    }
                } catch(e) {
                    console.warn("Failed to parse default filters:", e);
                }
            }

            if (refresh) {
                const rows = document.querySelectorAll('.filter-row');
                currentFiltersToRestore = [];
                rows.forEach(row => {
                    const field = row.querySelector('.filter-field').value;
                    const op = row.querySelector('.filter-operator').value;
                    const val = row.querySelector('.filter-value').value;
                    if (field || val) {
                        currentFiltersToRestore.push({
                            field: field,
                            operator: op,
                            value: val
                        });
                    }
                });
            }

            document.getElementById('filtersContainer').innerHTML = '';
            checkEmptyFilters();

            try {
                if (!refresh && window.entityMetadataCache && window.entityMetadataCache[entityKey]) {
                    // Gunakan local JS memory cache
                    const fields = window.entityMetadataCache[entityKey];
                    populateDatalist(fields, currentFiltersToRestore);
                    loadingEl.classList.add('hidden');
                    return;
                }

                const url = refresh ? `/api/entities/${entityKey}/fields?refresh=1` :
                    `/api/entities/${entityKey}/fields`;
                const res = await fetch(url);
                const data = await res.json();

                if (data.success && data.fields) {
                    availableFields = data.fields;
                    
                    // Simpan ke memory cache browser
                    if (!window.entityMetadataCache) window.entityMetadataCache = {};
                    window.entityMetadataCache[entityKey] = availableFields;

                    populateDatalist(availableFields, currentFiltersToRestore);
                } else {
                    availableFields = [];
                    showToast(data.message || 'Failed to load fields. You can still sync without filters.', 'warning');
                }
            } catch (e) {
                console.error(e);
                availableFields = [];
                showToast('Error loading fields for filtering.', 'error');
            } finally {
                loadingEl.classList.add('hidden');
            }
        }

        function populateDatalist(fields, currentFiltersToRestore) {
            const datalist = document.getElementById('acumaticaFieldsDataList');
            datalist.innerHTML = '';
            fields.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f;
                datalist.appendChild(opt);
            });

            if (currentFiltersToRestore && currentFiltersToRestore.length > 0) {
                currentFiltersToRestore.forEach(f => {
                    addFilterRow(f.field, f.operator, f.value);
                });
            }
        }

        function refreshEntityFields() {
            if (!currentSyncEntity) return;
            loadEntityFields(currentSyncEntity, [], true);
        }

        function addFilterRow(field = '', op = 'eq', val = '') {
            const container = document.getElementById('filtersContainer');

            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 filter-row animate-fade-up';

            row.innerHTML = `
            <input type="text" list="acumaticaFieldsDataList" class="filter-field flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2" placeholder="Search or select field..." onchange="handleFieldChange(this)">
            <select class="filter-operator w-36 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2">
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
            <input type="text" class="filter-value flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2" placeholder="Value...">
            <button onclick="removeFilterRow(this)" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all duration-200" title="Remove filter">
                <span class="material-symbols-outlined text-[16px]">delete</span>
            </button>
        `;
        
            row.querySelector('.filter-field').value = field;
            row.querySelector('.filter-value').value = val;

            container.appendChild(row);
            checkEmptyFilters();
        }

        function removeFilterRow(btn) {
            const row = btn.closest('.filter-row');
            row.remove();
            checkEmptyFilters();
        }

        function checkEmptyFilters() {
            const container = document.getElementById('filtersContainer');
            const emptyMsg = document.getElementById('emptyFiltersMsg');

            if (container.children.length === 0) {
                emptyMsg.classList.remove('hidden');
            } else {
                emptyMsg.classList.add('hidden');
            }
        }

        function handleFieldChange(selectEl) {
            const row = selectEl.closest('.filter-row');
            const valueInput = row.querySelector('.filter-value');
            const fieldName = selectEl.value.toLowerCase();

            if (fieldName.includes('date') || fieldName.includes('time')) {
                valueInput.type = 'date';
            } else {
                valueInput.type = 'text';
                valueInput.placeholder = 'Value...';
            }
        }

        async function executeFilteredSync() {
            const rows = document.querySelectorAll('.filter-row');
            const custom_filters = [];

            let hasError = false;
            rows.forEach(row => {
                const field = row.querySelector('.filter-field').value;
                const op = row.querySelector('.filter-operator').value;
                const val = row.querySelector('.filter-value').value;

                if (field && val) {
                    custom_filters.push({
                        field: field,
                        operator: op,
                        value: val
                    });
                } else if (field || val) {
                    hasError = true;
                }
            });

            if (hasError) {
                showToast('Please complete all filter fields or remove incomplete filters.', 'warning');
                return;
            }

            const btnEl = document.getElementById('btnStartSync');
            btnEl.disabled = true;
            const originalText = btnEl.innerHTML;
            btnEl.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Syncing...';

            document.getElementById('syncProgressContainer').classList.remove('hidden');
            document.getElementById('syncProgressBar').style.width = '0%';
            document.getElementById('syncProgressPercent').innerText = '0%';
            document.getElementById('syncStageLabel').innerText = 'Initializing...';
            document.getElementById('syncProgressLabel').innerText = 'Connecting to service...';
            document.getElementById('syncStatProcessed').innerText = '0';
            document.getElementById('syncStatSpeed').innerHTML = '0 <span class="text-[10px] text-surface-400 font-normal">/s</span>';
            document.getElementById('syncStatETA').innerText = '--:--';
            document.getElementById('syncElapsedTime').innerText = '00:00';
            
            const iconEl = document.getElementById('syncStatusIcon');
            iconEl.className = 'w-8 h-8 rounded-full bg-brand-50 flex items-center justify-center text-brand-600';
            iconEl.innerHTML = '<span class="material-symbols-outlined text-[18px] spin">sync</span>';

            const payload = {
                custom_filters: custom_filters,
                save_filters: document.getElementById('saveFiltersCb').checked
            };

            try {
                const res = await fetch(`/sync/${currentSyncEntity}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (data.success && data.job_id) {
                    showToast(`${currentSyncEntity}: Synchronization has been queued.`, 'info');
                    
                    // Instantly kickstart the queue worker via Web Cron (max 50s) to avoid waiting for VPS cron
                    fetch('/sys-cron?token=deploy123').catch(() => {});
                    
                    window.pollSyncJob(data.job_id, currentSyncEntity, {
                        onProgress: (job) => {
                            document.getElementById('syncProgressBar').style.width = `${job.progress}%`;
                            document.getElementById('syncProgressPercent').innerText = `${job.progress}%`;
                            
                            const pRows = job.processed_rows || 0;
                            const tRows = job.total_rows || 0;
                            
                            document.getElementById('syncStatProcessed').innerText = pRows.toLocaleString();
                            document.getElementById('syncStageLabel').innerText = job.status === 'pending' ? 'Queued' : 'Syncing';
                            document.getElementById('syncProgressLabel').innerText = `Processing... (${pRows.toLocaleString()} / ${tRows.toLocaleString()})`;
                            
                            if (job.duration) {
                                document.getElementById('syncElapsedTime').innerText = job.duration;
                            }
                        },
                        onSuccess: (job) => {
                            document.getElementById('syncProgressBar').style.width = '100%';
                            document.getElementById('syncProgressPercent').innerText = '100%';
                            document.getElementById('syncStageLabel').innerText = 'Completed!';
                            document.getElementById('syncProgressLabel').innerText = 'Synchronization finished successfully';
                            iconEl.className = 'w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600';
                            iconEl.innerHTML = '<span class="material-symbols-outlined text-[18px]">check_circle</span>';
                            
                            setTimeout(() => location.reload(), 1500);
                        },
                        onError: (job) => {
                            document.getElementById('syncStageLabel').innerText = 'Failed';
                            document.getElementById('syncProgressLabel').innerText = 'Synchronization stopped with error';
                            iconEl.className = 'w-8 h-8 rounded-full bg-red-50 flex items-center justify-center text-red-600';
                            iconEl.innerHTML = '<span class="material-symbols-outlined text-[18px]">error</span>';
                            
                            btnEl.disabled = false;
                            btnEl.innerHTML = originalText;
                        }
                    });
                } else {
                    if (data.error_type === 'connection_missing') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Koneksi Terputus!',
                                text: data.message,
                                confirmButtonText: 'Periksa Pengaturan',
                                confirmButtonColor: '#f59e0b'
                            }).then(() => window.location.href = '/settings');
                        } else {
                            alert(data.message);
                        }
                    } else {
                        showToast(`${currentSyncEntity}: ${data.error || data.message}`, 'error');
                    }
                    btnEl.disabled = false;
                    btnEl.innerHTML = originalText;
                    document.getElementById('syncProgressContainer').classList.add('hidden');
                }
            } catch (e) {
                showToast(`${currentSyncEntity}: ${e.message}`, 'error');
                btnEl.disabled = false;
                btnEl.innerHTML = originalText;
                document.getElementById('syncProgressContainer').classList.add('hidden');
            }
        }

        async function saveOnlyFilters() {
            const rows = document.querySelectorAll('.filter-row');
            const custom_filters = [];

            let hasError = false;
            rows.forEach(row => {
                const field = row.querySelector('.filter-field').value;
                const op = row.querySelector('.filter-operator').value;
                const val = row.querySelector('.filter-value').value;

                if (field && val) {
                    custom_filters.push({
                        field: field,
                        operator: op,
                        value: val
                    });
                } else if (field || val) {
                    hasError = true;
                }
            });

            if (hasError) {
                showToast('Please complete all filter fields or remove incomplete filters.', 'warning');
                return;
            }

            const btnSave = document.getElementById('btnSaveFiltersOnly');
            const originalText = btnSave.innerHTML;
            btnSave.disabled = true;
            btnSave.innerHTML = '<span class="material-symbols-outlined text-[16px] spin">sync</span> Saving...';

            try {
                const res = await fetch(`/api/entities/${currentSyncEntity}/filters`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        custom_filters: custom_filters
                    })
                });

                const data = await res.json();

                if (data.success) {
                    showToast(data.message || 'Filters saved successfully', 'success');
                    
                    // Update the data-filters attribute on the original button so reopening modal uses the new data
                    const triggerBtn = document.querySelector(`button[data-key="${currentSyncEntity}"]`);
                    if (triggerBtn) {
                        triggerBtn.dataset.filters = JSON.stringify(custom_filters);
                    }
                } else {
                    showToast(data.message || 'Failed to save filters', 'error');
                }
            } catch (e) {
                console.error(e);
                showToast('An error occurred while saving filters', 'error');
            } finally {
                btnSave.disabled = false;
                btnSave.innerHTML = originalText;
            }
        }

        /* Schedule Modal Logic */
        let currentScheduleEntity = '';

        function toggleScheduleFields() {
            const freq = document.getElementById('scheduleFrequency').value;
            const intervalGroup = document.getElementById('scheduleIntervalGroup');
            const timeGroup = document.getElementById('scheduleTimeGroup');
            const dowGroup = document.getElementById('scheduleDayOfWeekGroup');
            const domGroup = document.getElementById('scheduleDayOfMonthGroup');
            
            intervalGroup.classList.add('hidden');
            timeGroup.classList.add('hidden');
            dowGroup.classList.add('hidden');
            domGroup.classList.add('hidden');
            
            if (freq === 'minute' || freq === 'hour') {
                intervalGroup.classList.remove('hidden');
            } else if (freq) {
                timeGroup.classList.remove('hidden');
                if (freq === 'weekly') {
                    dowGroup.classList.remove('hidden');
                } else if (freq === 'monthly') {
                    domGroup.classList.remove('hidden');
                }
            }
        }

        function openScheduleModal(key, name, frequency, interval, time, dayOfWeek, dayOfMonth) {
            currentScheduleEntity = key;
            document.getElementById('scheduleEntityName').innerText = name;
            
            document.getElementById('scheduleFrequency').value = frequency || '';
            document.getElementById('scheduleInterval').value = interval || '1';
            if (time) {
                // Remove seconds if present (HH:mm:ss -> HH:mm)
                document.getElementById('scheduleTime').value = time.substring(0, 5);
            } else {
                document.getElementById('scheduleTime').value = '00:00';
            }
            document.getElementById('scheduleDayOfWeek').value = dayOfWeek || '1';
            document.getElementById('scheduleDayOfMonth').value = dayOfMonth || '1';
            
            toggleScheduleFields();
            
            const modal = document.getElementById('scheduleModal');
            const backdrop = document.getElementById('scheduleModalBackdrop');
            const panel = document.getElementById('scheduleModalPanel');
            
            modal.classList.remove('hidden');
            
            requestAnimationFrame(() => {
                backdrop.classList.remove('opacity-0');
                backdrop.classList.add('opacity-100');
                panel.classList.remove('opacity-0', 'scale-95');
                panel.classList.add('opacity-100', 'scale-100');
            });
        }

        function closeScheduleModal() {
            const modal = document.getElementById('scheduleModal');
            const backdrop = document.getElementById('scheduleModalBackdrop');
            const panel = document.getElementById('scheduleModalPanel');
            
            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
            panel.classList.remove('opacity-100', 'scale-100');
            panel.classList.add('opacity-0', 'scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        async function saveSchedule() {
            const btn = document.getElementById('btnSaveSchedule');
            const originalText = btn.innerHTML;
            btn.innerHTML = `<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving...`;
            btn.disabled = true;

            const payload = {
                frequency: document.getElementById('scheduleFrequency').value || null,
                interval: document.getElementById('scheduleInterval').value || null,
                time: document.getElementById('scheduleTime').value || null,
                day_of_week: document.getElementById('scheduleDayOfWeek').value || null,
                day_of_month: document.getElementById('scheduleDayOfMonth').value || null
            };

            try {
                const res = await fetch(`/api/scheduler/${currentScheduleEntity}`, {
                    method: 'PUT',
                    headers: { 
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                
                const data = await res.json();
                
                if (res.ok && data.success) {
                    showToast(`Schedule saved for ${currentScheduleEntity}`, 'success');
                    closeScheduleModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Failed to save schedule', 'error');
                }
            } catch (e) {
                showToast('Network error while saving schedule', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>
@endsection
