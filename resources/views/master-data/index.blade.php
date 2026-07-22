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
                            onclick="openSyncModal('{{ $key }}', '{{ $info['config']['description'] }}', {{ json_encode($info['custom_filters']) }})"
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

                    <div id="syncProgressContainer" class="hidden mt-4">
                        <div class="flex justify-between items-center mb-2">
                            <span id="syncProgressLabel"
                                class="text-label-sm text-surface-700 font-medium">Syncing...</span>
                            <span id="syncProgressPercent" class="text-label-sm text-surface-500 font-mono">0%</span>
                        </div>
                        <div class="w-full bg-surface-100 rounded-full h-2 overflow-hidden">
                            <div id="syncProgressBar"
                                class="bg-gradient-to-r from-brand-600 to-brand-400 h-2 rounded-full transition-all duration-300"
                                style="width: 0%"></div>
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
                const url = refresh ? `/api/entities/${entityKey}/fields?refresh=1` :
                    `/api/entities/${entityKey}/fields`;
                const res = await fetch(url);
                const data = await res.json();

                if (data.success && data.fields) {
                    availableFields = data.fields;

                    // Populate datalist
                    const datalist = document.getElementById('acumaticaFieldsDataList');
                    datalist.innerHTML = '';
                    availableFields.forEach(f => {
                        const opt = document.createElement('option');
                        opt.value = f;
                        datalist.appendChild(opt);
                    });

                    if (currentFiltersToRestore && currentFiltersToRestore.length > 0) {
                        currentFiltersToRestore.forEach(f => {
                            addFilterRow(f.field, f.operator, f.value);
                        });
                    }
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

        function refreshEntityFields() {
            if (!currentSyncEntity) return;
            loadEntityFields(currentSyncEntity, [], true);
        }

        function addFilterRow(field = '', op = 'eq', val = '') {
            const container = document.getElementById('filtersContainer');

            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 filter-row animate-fade-up';

            row.innerHTML = `
            <input type="text" list="acumaticaFieldsDataList" class="filter-field flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2" placeholder="Search or select field..." value="${field}" onchange="handleFieldChange(this)">
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
            <input type="text" class="filter-value flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2" placeholder="Value..." value="${val}">
            <button onclick="removeFilterRow(this)" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all duration-200" title="Remove filter">
                <span class="material-symbols-outlined text-[16px]">delete</span>
            </button>
        `;

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
            document.getElementById('syncProgressLabel').innerText = 'Initializing...';

            let isAsync = false;
            let noProgressCount = 0;

            let syncInterval = setInterval(async () => {
                try {
                    const progRes = await fetch(`/sync/${currentSyncEntity}/progress`);
                    const progData = await progRes.json();
                    if (progData.success && progData.progress) {
                        noProgressCount = 0;
                        const prog = progData.progress;
                        let percent = 0;

                        if (prog.status === 'queued') {
                            document.getElementById('syncProgressBar').classList.add('animate-pulse');
                            document.getElementById('syncProgressBar').style.width = '5%';
                            document.getElementById('syncProgressLabel').innerText =
                                'Queued — waiting for worker to start...';
                            document.getElementById('syncProgressPercent').innerText = '...';
                            return;
                        }

                        if (prog.total) {
                            percent = Math.round((prog.current / prog.total) * 100);
                            document.getElementById('syncProgressBar').classList.remove('animate-pulse');
                        } else if (prog.current > 0) {
                            percent = 100;
                            document.getElementById('syncProgressBar').classList.add('animate-pulse');
                        }

                        document.getElementById('syncProgressBar').style.width = `${percent}%`;
                        if (prog.total) {
                            document.getElementById('syncProgressPercent').innerText = `${percent}%`;
                        } else {
                            document.getElementById('syncProgressPercent').innerText = `...`;
                        }

                        let label = 'Syncing...';
                        if (prog.status === 'fetching') label =
                            `Fetching data from Acumatica... (${prog.current.toLocaleString()} records)`;
                        else if (prog.status === 'inserting') label =
                            `Saving to database... (${prog.current.toLocaleString()}/${prog.total ? prog.total.toLocaleString() : '?'})`;
                        document.getElementById('syncProgressLabel').innerText = label;
                    } else if (isAsync || (btnEl.disabled && btnEl.innerHTML.includes('Syncing'))) {
                        noProgressCount++;
                        if (noProgressCount >= 2) {
                            clearInterval(syncInterval);
                            try {
                                const logRes = await fetch(
                                `/sync/logs?entity=${currentSyncEntity}&limit=1`);
                                const logData = await logRes.json();
                                const latest = logData.logs && logData.logs[0];
                                if (latest && latest.status === 'success') {
                                    document.getElementById('syncProgressBar').style.width = '100%';
                                    document.getElementById('syncProgressPercent').innerText = '100%';
                                    document.getElementById('syncProgressLabel').innerText = 'Completed!';
                                    showToast(
                                        `${currentSyncEntity}: ${(latest.records_inserted || 0).toLocaleString()} records synced in ${latest.duration_seconds}s`,
                                        'success');
                                } else if (latest && latest.status === 'failed') {
                                    showToast(
                                        `${currentSyncEntity}: ${latest.error_message || 'Sync failed'}`,
                                        'error');
                                } else {
                                    showToast(`${currentSyncEntity}: Sync process finished.`, 'success');
                                }
                            } catch (e) {
                                showToast(`${currentSyncEntity}: Sync process finished.`, 'success');
                            }
                            setTimeout(() => location.reload(), 1500);
                        }
                    }
                } catch (e) {
                    // Ignore poll errors
                }
            }, 2000);

            const payload = {
                custom_filters: custom_filters,
                save_filters: document.getElementById('saveFiltersCb').checked
            };

            try {
                const res = await fetch(`/sync/${currentSyncEntity}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (data.async) {
                    isAsync = true;
                    showToast(`${currentSyncEntity}: Sync dispatched to background queue.`, 'info');
                    return;
                }

                clearInterval(syncInterval);
                document.getElementById('syncProgressBar').style.width = '100%';
                document.getElementById('syncProgressPercent').innerText = '100%';
                document.getElementById('syncProgressLabel').innerText = 'Completed!';

                if (data.success) {
                    showToast(`${currentSyncEntity}: ${data.records} records synced in ${data.duration}`, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(`${currentSyncEntity}: ${data.error || data.message}`, 'error');
                    btnEl.disabled = false;
                    btnEl.innerHTML = originalText;
                    document.getElementById('syncProgressContainer').classList.add('hidden');
                }
            } catch (e) {
                console.warn("Sync HTTP connection closed, but background process may still be running.", e);
                showToast('Connection timeout. The sync process will continue in the background.', 'info');
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
                        'X-CSRF-TOKEN': csrfToken,
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
                    setTimeout(() => location.reload(), 1000);
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
                const res = await fetch(`/api/entities/${currentScheduleEntity}/schedule`, {
                    method: 'POST',
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
