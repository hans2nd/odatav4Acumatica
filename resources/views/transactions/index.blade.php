@extends('layouts.app')

@section('title', 'Transactions — Enterprise Sync Engine')
@section('page-title', 'Transactions')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-lg">
    @foreach($entities as $key => $info)
        <div class="bg-surface-container-lowest border border-outline-variant rounded p-md flex flex-col fade-in">
            <div class="flex items-center justify-between mb-md">
                <div class="flex items-center gap-sm">
                    <div class="p-2 bg-surface-container-low rounded">
                        <span class="material-symbols-outlined text-secondary">{{ $info['config']['icon'] ?? 'swap_horiz' }}</span>
                    </div>
                    <div>
                        <h3 class="font-body-md text-body-md font-semibold text-on-surface">{{ $info['config']['description'] }}</h3>
                        <p class="font-label-sm text-label-sm text-on-surface-variant">{{ $info['config']['odata_entity'] }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-end justify-between mt-auto">
                <div>
                    <span class="font-display text-[24px] font-bold text-on-surface leading-none">{{ number_format($info['records']) }}</span>
                    <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">records</p>
                </div>

                <div class="flex items-center gap-2">
                    @if($info['table_exists'])
                        <a href="{{ route('transactions.show', $key) }}"
                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-surface-container-low text-on-surface-variant hover:text-on-surface font-label-md text-label-md transition-colors">
                            <span class="material-symbols-outlined text-sm">visibility</span> View
                        </a>
                    @endif
                    <button onclick="openSyncModal('{{ $key }}', '{{ $info['config']['description'] }}')"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-[0.97]">
                        <span class="material-symbols-outlined text-sm">sync</span> Sync
                    </button>
                </div>
            </div>

            @if(!$info['table_exists'])
                <div class="mt-sm px-2 py-1 bg-yellow-50 border border-yellow-200 rounded">
                    <span class="font-label-sm text-label-sm text-yellow-700">Table not created yet — run sync to create</span>
                </div>
            @endif
        </div>
    @endforeach
</div>

{{-- Sync Filter Modal --}}
<div id="syncModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-surface rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col transform transition-transform scale-95 duration-200">
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center bg-surface-container-lowest">
            <div>
                <h2 class="font-h2 text-h2 text-on-surface">Sync <span id="syncModalEntityName" class="text-primary"></span></h2>
                <p class="font-body-md text-body-md text-on-surface-variant mt-1">Configure optional filters for this synchronization.</p>
            </div>
            <button onclick="closeSyncModal()" class="text-on-surface-variant hover:text-on-surface p-1 rounded hover:bg-surface-variant transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <div class="p-lg overflow-y-auto max-h-[60vh]">
            <div id="syncModalLoading" class="hidden flex flex-col items-center justify-center py-xl">
                <span class="material-symbols-outlined text-4xl text-primary spin mb-4">sync</span>
                <p class="font-body-md text-body-md text-on-surface-variant">Fetching available fields...</p>
            </div>
            
            <div id="syncModalContent" class="hidden space-y-md">
                <div class="flex justify-between items-end">
                    <label class="font-label-md text-label-md text-on-surface">Custom Filters (Optional)</label>
                    <button onclick="addFilterRow()" class="inline-flex items-center gap-1 px-2 py-1 rounded bg-secondary-container text-on-secondary-container font-label-sm text-label-sm hover:bg-secondary transition-colors hover:text-on-secondary">
                        <span class="material-symbols-outlined text-[16px]">add</span> Add Filter
                    </button>
                </div>
                
                <div id="filtersContainer" class="space-y-sm">
                    <!-- Filter rows will be inserted here -->
                </div>
                
                <div id="emptyFiltersMsg" class="py-md text-center border border-dashed border-outline-variant rounded bg-surface-container-lowest">
                    <p class="font-body-md text-body-md text-on-surface-variant">No filters added. This will sync all data.</p>
                </div>
                
                <div id="syncProgressContainer" class="hidden mt-4">
                    <div class="flex justify-between items-center mb-1">
                        <span id="syncProgressLabel" class="font-label-sm text-label-sm text-on-surface">Syncing...</span>
                        <span id="syncProgressPercent" class="font-label-sm text-label-sm text-on-surface-variant">0%</span>
                    </div>
                    <div class="w-full bg-surface-variant rounded-full h-2">
                        <div id="syncProgressBar" class="bg-primary h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="px-lg py-md border-t border-outline-variant flex justify-end gap-md bg-surface-container-lowest">
            <button onclick="closeSyncModal()" class="px-4 py-2 rounded text-on-surface-variant font-label-md text-label-md hover:bg-surface-variant transition-colors">
                Cancel
            </button>
            <button id="btnStartSync" onclick="executeFilteredSync()" class="inline-flex items-center gap-2 px-4 py-2 rounded bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container hover:text-on-primary-container transition-colors">
                <span class="material-symbols-outlined text-[18px]">sync</span> Start Sync
            </button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    let currentSyncEntity = null;
    let availableFields = [];
    
    function openSyncModal(entityKey, entityName) {
        currentSyncEntity = entityKey;
        document.getElementById('syncModalEntityName').textContent = entityName;
        
        const modal = document.getElementById('syncModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        // Small delay to allow display:block to apply before animating opacity/transform
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
        }, 10);
        
        loadEntityFields(entityKey);
    }
    
    function closeSyncModal() {
        const modal = document.getElementById('syncModal');
        modal.classList.add('opacity-0');
        modal.querySelector('.transform').classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.getElementById('filtersContainer').innerHTML = '';
            checkEmptyFilters();
        }, 200);
    }
    
    async function loadEntityFields(entityKey) {
        const loadingEl = document.getElementById('syncModalLoading');
        const contentEl = document.getElementById('syncModalContent');
        const btnSync = document.getElementById('btnStartSync');
        
        loadingEl.classList.remove('hidden');
        contentEl.classList.add('hidden');
        btnSync.disabled = true;
        
        document.getElementById('filtersContainer').innerHTML = '';
        checkEmptyFilters();
        
        try {
            const res = await fetch(`/api/entities/${entityKey}/fields`);
            const data = await res.json();
            
            if (data.success && data.fields) {
                availableFields = data.fields;
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
            contentEl.classList.remove('hidden');
            btnSync.disabled = false;
        }
    }
    
    function addFilterRow() {
        const container = document.getElementById('filtersContainer');
        
        const row = document.createElement('div');
        row.className = 'flex items-center gap-sm filter-row fade-in';
        
        // Field Select
        let optionsHtml = '<option value="">-- Select Field --</option>';
        availableFields.forEach(f => {
            optionsHtml += `<option value="${f}">${f}</option>`;
        });
        
        row.innerHTML = `
            <select class="filter-field flex-1 rounded border-outline-variant bg-surface-container-lowest text-on-surface text-body-md focus:border-primary focus:ring focus:ring-primary/20">
                ${optionsHtml}
            </select>
            <select class="filter-operator w-32 rounded border-outline-variant bg-surface-container-lowest text-on-surface text-body-md focus:border-primary focus:ring focus:ring-primary/20">
                <option value="eq">Equals (=)</option>
                <option value="ne">Not Eq (!=)</option>
                <option value="contains">Contains</option>
                <option value="startswith">Starts With</option>
                <option value="endswith">Ends With</option>
                <option value="gt">Greater (>)</option>
                <option value="ge">Greater Eq (>=)</option>
                <option value="lt">Less (<)</option>
                <option value="le">Less Eq (<=)</option>
            </select>
            <input type="text" class="filter-value flex-1 rounded border-outline-variant bg-surface-container-lowest text-on-surface text-body-md focus:border-primary focus:ring focus:ring-primary/20" placeholder="Value...">
            <button onclick="removeFilterRow(this)" class="p-2 text-error hover:bg-error-container rounded transition-colors" title="Remove filter">
                <span class="material-symbols-outlined text-sm">delete</span>
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
    
    async function executeFilteredSync() {
        const rows = document.querySelectorAll('.filter-row');
        const custom_filters = [];
        
        let hasError = false;
        rows.forEach(row => {
            const field = row.querySelector('.filter-field').value;
            const op = row.querySelector('.filter-operator').value;
            const val = row.querySelector('.filter-value').value;
            
            if (field && val) {
                custom_filters.push({ field: field, operator: op, value: val });
            } else if (field || val) {
                // partial row filled
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
        btnEl.innerHTML = '<span class="material-symbols-outlined text-sm spin">sync</span> Syncing...';
        
        document.getElementById('syncProgressContainer').classList.remove('hidden');
        document.getElementById('syncProgressBar').style.width = '0%';
        document.getElementById('syncProgressPercent').innerText = '0%';
        document.getElementById('syncProgressLabel').innerText = 'Initializing...';
        
        let syncInterval = setInterval(async () => {
            try {
                const progRes = await fetch(`/sync/${currentSyncEntity}/progress`);
                const progData = await progRes.json();
                if (progData.success && progData.progress) {
                    const prog = progData.progress;
                    let percent = 0;
                    if (prog.total) {
                        percent = Math.round((prog.current / prog.total) * 100);
                    }
                    
                    document.getElementById('syncProgressBar').style.width = `${percent}%`;
                    document.getElementById('syncProgressPercent').innerText = `${percent}%`;
                    
                    let label = 'Syncing...';
                    if (prog.status === 'fetching') label = `Fetching data from Acumatica... (${prog.current} records)`;
                    else if (prog.status === 'inserting') label = `Saving to database... (${prog.current}/${prog.total || '?'})`;
                    document.getElementById('syncProgressLabel').innerText = label;
                } else if (btnEl.disabled && btnEl.innerHTML.includes('Syncing')) {
                    // If progress is missing but button is still disabled, it means the background job finished!
                    clearInterval(syncInterval);
                    btnEl.innerHTML = 'Completed'; // Prevent overlapping toast
                    document.getElementById('syncProgressBar').style.width = '100%';
                    document.getElementById('syncProgressPercent').innerText = '100%';
                    document.getElementById('syncProgressLabel').innerText = 'Completed!';
                    showToast(`${currentSyncEntity}: Sync process finished.`, 'success');
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (e) {
                // Ignore poll errors
            }
        }, 1000);
        
        const payload = {
            custom_filters: custom_filters
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
            // Ignore HTTP timeout if it drops, backend will continue due to ignore_user_abort
            console.warn("Sync HTTP connection closed, but background process may still be running.", e);
        }
    }
</script>
@endsection
