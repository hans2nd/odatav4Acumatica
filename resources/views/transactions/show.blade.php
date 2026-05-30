@extends('layouts.app')

@section('title', $config['description'] . ' — Enterprise Sync Engine')
@section('page-title', $config['description'])

@section('content')
{{-- Header --}}
<div class="flex items-center justify-between fade-in">
    <div class="flex items-center gap-md">
        <a href="{{ route('transactions.index') }}" class="p-2 rounded hover:bg-surface-container-low transition-colors">
            <span class="material-symbols-outlined text-on-surface-variant">arrow_back</span>
        </a>
        <div>
            <h2 class="font-h1 text-h1 text-on-surface">{{ $config['description'] }}</h2>
            <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">
                OData: {{ $config['odata_entity'] }} → Table: {{ $config['table'] }}
            </p>
        </div>
    </div>
    <button onclick="openShowSyncModal()"
            class="inline-flex items-center gap-1 px-4 py-2 rounded bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-[0.97]">
        <span class="material-symbols-outlined text-sm">sync</span> Sync Now
    </button>
</div>

@if(!$tableExists)
    <div class="bg-yellow-50 border border-yellow-200 rounded p-md text-center fade-in">
        <span class="material-symbols-outlined text-3xl text-yellow-600 mb-2">info</span>
        <p class="font-body-md text-body-md text-yellow-800">
            Table <code class="bg-yellow-100 px-1 py-0.5 rounded">{{ $config['table'] }}</code> does not exist yet.
            <br>Click <strong>Sync Now</strong> to fetch data from Acumatica and create the table automatically.
        </p>
    </div>
@else
    {{-- Data Table --}}
    <div class="bg-surface-container-lowest border border-outline-variant rounded flex flex-col fade-in">
        <div class="p-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low/50 flex-wrap gap-4">
            <span class="font-label-md text-label-md text-on-surface-variant whitespace-nowrap">
                Showing {{ $data->firstItem() ?? 0 }}–{{ $data->lastItem() ?? 0 }} of {{ number_format($data->total()) }} records
            </span>
            <form action="{{ url()->current() }}" method="GET" class="flex items-center w-full max-w-sm">
                <div class="relative w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search records..." 
                           class="w-full pl-9 pr-3 py-1.5 bg-surface border border-outline-variant rounded font-body-sm text-body-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                </div>
                @if(request('search'))
                    <a href="{{ url()->current() }}" class="ml-2 p-1.5 flex items-center justify-center bg-surface hover:bg-surface-container-high border border-outline-variant rounded text-on-surface-variant transition-colors" title="Clear Search">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </a>
                @endif
                <button type="submit" class="ml-2 px-3 py-1.5 bg-surface-variant hover:bg-surface-container-highest text-on-surface-variant rounded font-label-sm text-label-sm transition-colors border border-outline-variant">
                    Search
                </button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-max">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        @foreach($columns as $col)
                            @if(!in_array($col, ['_sync_id', '_synced_at', '_row_hash']))
                                <th class="py-2 px-3 font-label-sm text-label-sm text-on-surface-variant font-medium whitespace-nowrap">{{ $col }}</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="font-code text-code text-on-surface divide-y divide-outline-variant/50">
                    @foreach($data as $row)
                        <tr class="hover:bg-surface-container-low/50 transition-colors">
                            @foreach($columns as $col)
                                @if(!in_array($col, ['_sync_id', '_synced_at', '_row_hash']))
                                    <td class="py-2 px-3 whitespace-nowrap max-w-xs truncate">{{ $row->{$col} ?? '' }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($data->hasPages())
            <div class="p-md border-t border-outline-variant flex justify-center">
                {{ $data->links('pagination::simple-tailwind') }}
            </div>
        @endif
    </div>
@endif

{{-- Sync Filter Modal --}}
<div id="showSyncModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-surface rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col transform transition-transform scale-95 duration-200">
        <div class="px-lg py-md border-b border-outline-variant flex justify-between items-center bg-surface-container-lowest">
            <div>
                <h2 class="font-h2 text-h2 text-on-surface">Sync <span class="text-primary">{{ $config['description'] }}</span></h2>
                <p class="font-body-md text-body-md text-on-surface-variant mt-1">Configure optional filters for this synchronization.</p>
            </div>
            <button onclick="closeShowSyncModal()" class="text-on-surface-variant hover:text-on-surface p-1 rounded hover:bg-surface-variant transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <div class="p-lg overflow-y-auto max-h-[60vh]">
            <div id="showSyncLoading" class="hidden flex flex-col items-center justify-center py-xl">
                <span class="material-symbols-outlined text-4xl text-primary spin mb-4">sync</span>
                <p class="font-body-md text-body-md text-on-surface-variant">Fetching available fields...</p>
            </div>
            
            <div id="showSyncContent" class="hidden space-y-md">
                <div class="flex justify-between items-end">
                    <label class="font-label-md text-label-md text-on-surface">Custom Filters (Optional)</label>
                    <button onclick="addShowFilterRow()" class="inline-flex items-center gap-1 px-2 py-1 rounded bg-secondary-container text-on-secondary-container font-label-sm text-label-sm hover:bg-secondary transition-colors hover:text-on-secondary">
                        <span class="material-symbols-outlined text-[16px]">add</span> Add Filter
                    </button>
                </div>
                
                <div id="showFiltersContainer" class="space-y-sm">
                    <!-- Filter rows will be inserted here -->
                </div>
                
                <div id="showEmptyFiltersMsg" class="py-md text-center border border-dashed border-outline-variant rounded bg-surface-container-lowest">
                    <p class="font-body-md text-body-md text-on-surface-variant">No filters added. This will sync all data.</p>
                </div>
                
                <div id="showSyncProgressContainer" class="hidden mt-4">
                    <div class="flex justify-between items-center mb-1">
                        <span id="showSyncProgressLabel" class="font-label-sm text-label-sm text-on-surface">Syncing...</span>
                        <span id="showSyncProgressPercent" class="font-label-sm text-label-sm text-on-surface-variant">0%</span>
                    </div>
                    <div class="w-full bg-surface-variant rounded-full h-2">
                        <div id="showSyncProgressBar" class="bg-primary h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="px-lg py-md border-t border-outline-variant flex justify-end gap-md bg-surface-container-lowest">
            <button onclick="closeShowSyncModal()" class="px-4 py-2 rounded text-on-surface-variant font-label-md text-label-md hover:bg-surface-variant transition-colors">
                Cancel
            </button>
            <button id="btnShowStartSync" onclick="executeShowSync()" class="inline-flex items-center gap-2 px-4 py-2 rounded bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container hover:text-on-primary-container transition-colors">
                <span class="material-symbols-outlined text-[18px]">sync</span> Start Sync
            </button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const showEntityKey = @json($entityKey);
    let showAvailableFields = [];
    
    function openShowSyncModal() {
        const modal = document.getElementById('showSyncModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
        }, 10);
        
        loadShowEntityFields();
    }
    
    function closeShowSyncModal() {
        const modal = document.getElementById('showSyncModal');
        modal.classList.add('opacity-0');
        modal.querySelector('.transform').classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.getElementById('showFiltersContainer').innerHTML = '';
            checkShowEmptyFilters();
        }, 200);
    }
    
    async function loadShowEntityFields() {
        const loadingEl = document.getElementById('showSyncLoading');
        const contentEl = document.getElementById('showSyncContent');
        const btnSync = document.getElementById('btnShowStartSync');
        
        loadingEl.classList.remove('hidden');
        contentEl.classList.add('hidden');
        btnSync.disabled = true;
        
        document.getElementById('showFiltersContainer').innerHTML = '';
        checkShowEmptyFilters();
        
        try {
            const res = await fetch(`/api/entities/${showEntityKey}/fields`);
            const data = await res.json();
            
            if (data.success && data.fields) {
                showAvailableFields = data.fields;
            } else {
                showAvailableFields = [];
                showToast(data.message || 'Failed to load fields.', 'warning');
            }
        } catch (e) {
            console.error(e);
            showAvailableFields = [];
            showToast('Error loading fields for filtering.', 'error');
        } finally {
            loadingEl.classList.add('hidden');
            contentEl.classList.remove('hidden');
            btnSync.disabled = false;
        }
    }
    
    function addShowFilterRow() {
        const container = document.getElementById('showFiltersContainer');
        
        const row = document.createElement('div');
        row.className = 'flex items-center gap-sm show-filter-row fade-in';
        
        let optionsHtml = '<option value="">-- Select Field --</option>';
        showAvailableFields.forEach(f => {
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
                <option value="lt">Less (<)</option>
                <option value="ge">Greater or Eq (>=)</option>
                <option value="le">Less or Eq (<=)</option>
            </select>
            <input type="text" class="filter-value flex-1 rounded border-outline-variant bg-surface-container-lowest text-on-surface text-body-md focus:border-primary focus:ring focus:ring-primary/20" placeholder="Value...">
            <button onclick="removeShowFilterRow(this)" class="p-2 text-error hover:bg-error-container rounded transition-colors" title="Remove filter">
                <span class="material-symbols-outlined text-sm">delete</span>
            </button>
        `;
        
        container.appendChild(row);
        checkShowEmptyFilters();
    }
    
    function removeShowFilterRow(btn) {
        const row = btn.closest('.show-filter-row');
        row.remove();
        checkShowEmptyFilters();
    }
    
    function checkShowEmptyFilters() {
        const container = document.getElementById('showFiltersContainer');
        const emptyMsg = document.getElementById('showEmptyFiltersMsg');
        
        if (container.children.length === 0) {
            emptyMsg.classList.remove('hidden');
        } else {
            emptyMsg.classList.add('hidden');
        }
    }
    
    async function executeShowSync() {
        const rows = document.querySelectorAll('.show-filter-row');
        const custom_filters = [];
        
        let hasError = false;
        rows.forEach(row => {
            const field = row.querySelector('.filter-field').value;
            const op = row.querySelector('.filter-operator').value;
            const val = row.querySelector('.filter-value').value;
            
            if (field && val) {
                custom_filters.push({ field: field, operator: op, value: val });
            } else if (field || val) {
                hasError = true;
            }
        });
        
        if (hasError) {
            showToast('Please complete all filter fields or remove incomplete filters.', 'warning');
            return;
        }
        
        const btnEl = document.getElementById('btnShowStartSync');
        btnEl.disabled = true;
        const originalText = btnEl.innerHTML;
        btnEl.innerHTML = '<span class="material-symbols-outlined text-sm spin">sync</span> Syncing...';
        
        document.getElementById('showSyncProgressContainer').classList.remove('hidden');
        document.getElementById('showSyncProgressBar').style.width = '0%';
        document.getElementById('showSyncProgressPercent').innerText = '0%';
        document.getElementById('showSyncProgressLabel').innerText = 'Initializing...';
        
        let syncInterval = setInterval(async () => {
            try {
                const progRes = await fetch(`/sync/${showEntityKey}/progress`);
                const progData = await progRes.json();
                if (progData.success && progData.progress) {
                    const prog = progData.progress;
                    let percent = 0;
                    if (prog.total) {
                        percent = Math.round((prog.current / prog.total) * 100);
                    }
                    
                    document.getElementById('showSyncProgressBar').style.width = `${percent}%`;
                    document.getElementById('showSyncProgressPercent').innerText = `${percent}%`;
                    
                    let label = 'Syncing...';
                    if (prog.status === 'fetching') label = `Fetching data from Acumatica... (${prog.current} records)`;
                    else if (prog.status === 'inserting') label = `Saving to database... (${prog.current}/${prog.total || '?'})`;
                    document.getElementById('showSyncProgressLabel').innerText = label;
                } else if (btnEl.disabled && btnEl.innerHTML.includes('Syncing')) {
                    clearInterval(syncInterval);
                    document.getElementById('showSyncProgressBar').style.width = '100%';
                    document.getElementById('showSyncProgressPercent').innerText = '100%';
                    document.getElementById('showSyncProgressLabel').innerText = 'Completed!';
                    showToast(`${showEntityKey}: Sync process finished.`, 'success');
                    closeShowSyncModal();
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (e) {
                // Ignore poll errors
            }
        }, 1000);
        
        try {
            const res = await fetch(`/sync/${showEntityKey}`, {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': csrfToken, 
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ custom_filters: custom_filters })
            });
            
            const data = await res.json();
            
            clearInterval(syncInterval);
            document.getElementById('showSyncProgressBar').style.width = '100%';
            document.getElementById('showSyncProgressPercent').innerText = '100%';
            document.getElementById('showSyncProgressLabel').innerText = 'Completed!';
            
            if (data.success) {
                showToast(`${showEntityKey}: ${data.records} records synced in ${data.duration}`, 'success');
                closeShowSyncModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(`${showEntityKey}: ${data.error || data.message}`, 'error');
                btnEl.disabled = false;
                btnEl.innerHTML = originalText;
                document.getElementById('showSyncProgressContainer').classList.add('hidden');
            }
        } catch (e) {
            // Ignore HTTP timeout if it drops, backend will continue due to ignore_user_abort
            console.warn("Sync HTTP connection closed, but background process may still be running.", e);
        }
    }
</script>
@endsection
