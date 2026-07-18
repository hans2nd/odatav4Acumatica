@extends('layouts.app')

@section('title', $config['description'] . ' — PL Sync Data Warehouse')
@section('page-title', $config['description'])

@section('content')
{{-- Header --}}
<div class="flex items-center justify-between animate-fade-up">
    <div class="flex items-center gap-3">
        <a href="{{ route('transactions.index') }}" class="p-2 rounded-xl hover:bg-surface-100 transition-all duration-200 active:scale-95">
            <span class="material-symbols-outlined text-surface-500 text-[20px]">arrow_back</span>
        </a>
        <div>
            <h2 class="text-h1 text-surface-800">{{ $config['description'] }}</h2>
            <p class="text-label-sm text-surface-400 mt-0.5 font-mono">
                OData: {{ $config['odata_entity'] }} → Table: {{ $config['table'] }}
            </p>
        </div>
    </div>
    <button onclick="openShowSyncModal()"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 active:scale-[0.97] shadow-sm">
        <span class="material-symbols-outlined text-[16px]">sync</span> Sync Now
    </button>
</div>

@if(!$tableExists)
    <div class="bg-amber-50 border border-amber-200/60 rounded-2xl p-8 text-center animate-fade-up mt-5">
        <span class="material-symbols-outlined text-4xl text-amber-500 mb-3 block">info</span>
        <p class="text-body-lg text-amber-800 font-medium">
            Table <code class="bg-amber-100 px-2 py-0.5 rounded-lg font-mono text-body-sm">{{ $config['table'] }}</code> does not exist yet.
        </p>
        <p class="text-body-md text-amber-700 mt-1">Click <strong>Sync Now</strong> to fetch data from Acumatica and create the table automatically.</p>
    </div>
@else
    {{-- Data Table --}}
    <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden animate-fade-up mt-5">
        <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center flex-wrap gap-4">
            <span class="text-label-md text-surface-500 whitespace-nowrap">
                Showing {{ $data->firstItem() ?? 0 }}–{{ $data->lastItem() ?? 0 }} of {{ number_format($data->total()) }} records
            </span>
            <form action="{{ url()->current() }}" method="GET" class="flex items-center w-full max-w-sm">
                <div class="relative w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-[16px]">search</span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search records..." 
                           class="w-full pl-9 pr-3 py-2 bg-surface-50 border border-surface-200 rounded-xl text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                </div>
                @if(request('search'))
                    <a href="{{ url()->current() }}" class="ml-2 p-2 flex items-center justify-center bg-surface-100 hover:bg-surface-200 border border-surface-200 rounded-xl text-surface-500 transition-all duration-200" title="Clear Search">
                        <span class="material-symbols-outlined text-[16px]">close</span>
                    </a>
                @endif
                <button type="submit" class="ml-2 px-3 py-2 bg-surface-100 hover:bg-surface-200 text-surface-600 rounded-xl text-label-sm transition-all duration-200 border border-surface-200">
                    Search
                </button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-max">
                <thead>
                    <tr class="border-b border-surface-100">
                        @foreach($columns as $col)
                            @if(!in_array($col, ['_sync_id', '_synced_at', '_row_hash']))
                                <th class="py-3 px-4 text-label-sm text-surface-500 font-semibold uppercase tracking-wider whitespace-nowrap">{{ $col }}</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="font-mono text-body-sm text-surface-700 divide-y divide-surface-100">
                    @foreach($data as $row)
                        <tr class="hover:bg-surface-50/50 transition-colors">
                            @foreach($columns as $col)
                                @if(!in_array($col, ['_sync_id', '_synced_at', '_row_hash']))
                                    <td class="py-2.5 px-4 whitespace-nowrap max-w-xs truncate">{{ $row->{$col} ?? '' }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($data->hasPages())
            <div class="px-5 py-4 border-t border-surface-100 flex justify-center">
                {{ $data->links('pagination::simple-tailwind') }}
            </div>
        @endif
    </div>
@endif

{{-- Sync Filter Modal --}}
<div id="showSyncModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm transition-all duration-200 opacity-0">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col transform transition-all duration-200 scale-95 border border-surface-200/60">
        <div class="px-6 py-4 border-b border-surface-100 flex justify-between items-center">
            <div>
                <h2 class="text-h2 text-surface-800">Sync <span class="text-brand-600">{{ $config['description'] }}</span></h2>
                <p class="text-body-sm text-surface-500 mt-1">Configure optional filters for this synchronization.</p>
            </div>
            <button onclick="closeShowSyncModal()" class="text-surface-400 hover:text-surface-700 p-1.5 rounded-xl hover:bg-surface-100 transition-all duration-200">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[60vh]">
            <div id="showSyncLoading" class="hidden flex flex-col items-center justify-center py-12">
                <span class="material-symbols-outlined text-4xl text-brand-500 spin mb-4">sync</span>
                <p class="text-body-md text-surface-500">Fetching available fields...</p>
            </div>
            
            <div id="showSyncContent" class="hidden space-y-4">
                <div class="flex justify-between items-end">
                    <label class="text-label-lg text-surface-700">Custom Filters (Optional)</label>
                    <button onclick="addShowFilterRow()" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-100 text-surface-700 text-label-sm hover:bg-surface-200 transition-all duration-200">
                        <span class="material-symbols-outlined text-[14px]">add</span> Add Filter
                    </button>
                </div>
                
                <div id="showFiltersContainer" class="space-y-2"></div>
                
                <div id="showEmptyFiltersMsg" class="py-6 text-center border-2 border-dashed border-surface-200 rounded-xl bg-surface-50">
                    <span class="material-symbols-outlined text-surface-300 text-3xl mb-2 block">filter_alt_off</span>
                    <p class="text-body-md text-surface-400">No filters added. This will sync all data.</p>
                </div>
                
                <div id="showSyncProgressContainer" class="hidden mt-4">
                    <div class="flex justify-between items-center mb-2">
                        <span id="showSyncProgressLabel" class="text-label-sm text-surface-700 font-medium">Syncing...</span>
                        <span id="showSyncProgressPercent" class="text-label-sm text-surface-500 font-mono">0%</span>
                    </div>
                    <div class="w-full bg-surface-100 rounded-full h-2 overflow-hidden">
                        <div id="showSyncProgressBar" class="bg-gradient-to-r from-brand-600 to-brand-400 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="px-6 py-4 border-t border-surface-100 flex justify-end gap-3">
            <button onclick="closeShowSyncModal()" class="px-4 py-2 rounded-xl text-surface-600 text-label-md hover:bg-surface-100 transition-all duration-200">
                Cancel
            </button>
            <button id="btnShowStartSync" onclick="executeShowSync()" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 shadow-sm">
                <span class="material-symbols-outlined text-[16px]">sync</span> Start Sync
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
            modal.querySelector('.transform').classList.add('scale-100');
        }, 10);
        loadShowEntityFields();
    }
    
    function closeShowSyncModal() {
        const modal = document.getElementById('showSyncModal');
        modal.classList.add('opacity-0');
        modal.querySelector('.transform').classList.add('scale-95');
        modal.querySelector('.transform').classList.remove('scale-100');
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
        row.className = 'flex items-center gap-2 show-filter-row animate-fade-up';
        
        let optionsHtml = '<option value="">-- Select Field --</option>';
        showAvailableFields.forEach(f => { optionsHtml += `<option value="${f}">${f}</option>`; });
        
        row.innerHTML = `
            <select class="filter-field flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2">
                ${optionsHtml}
            </select>
            <select class="filter-operator w-36 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2">
                <option value="eq">Equals (=)</option>
                <option value="ne">Not Eq (!=)</option>
                <option value="contains">Contains</option>
                <option value="startswith">Starts With</option>
                <option value="endswith">Ends With</option>
                <option value="gt">Greater (>)</option>
                <option value="lt">Less (<)</option>
                <option value="ge">Greater Eq (>=)</option>
                <option value="le">Less Eq (<=)</option>
            </select>
            <input type="text" class="filter-value flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2" placeholder="Value...">
            <button onclick="removeShowFilterRow(this)" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all duration-200" title="Remove filter">
                <span class="material-symbols-outlined text-[16px]">delete</span>
            </button>
        `;
        
        container.appendChild(row);
        checkShowEmptyFilters();
    }
    
    function removeShowFilterRow(btn) { btn.closest('.show-filter-row').remove(); checkShowEmptyFilters(); }
    
    function checkShowEmptyFilters() {
        const container = document.getElementById('showFiltersContainer');
        const emptyMsg = document.getElementById('showEmptyFiltersMsg');
        emptyMsg.classList.toggle('hidden', container.children.length > 0);
    }
    
    async function executeShowSync() {
        const rows = document.querySelectorAll('.show-filter-row');
        const custom_filters = [];
        let hasError = false;
        rows.forEach(row => {
            const field = row.querySelector('.filter-field').value;
            const op = row.querySelector('.filter-operator').value;
            const val = row.querySelector('.filter-value').value;
            if (field && val) { custom_filters.push({ field, operator: op, value: val }); }
            else if (field || val) { hasError = true; }
        });
        
        if (hasError) { showToast('Please complete all filter fields or remove incomplete filters.', 'warning'); return; }
        
        const btnEl = document.getElementById('btnShowStartSync');
        btnEl.disabled = true;
        const originalText = btnEl.innerHTML;
        btnEl.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Syncing...';
        
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
                    if (prog.total) { percent = Math.round((prog.current / prog.total) * 100); }
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
            } catch (e) {}
        }, 1000);
        
        try {
            const res = await fetch(`/sync/${showEntityKey}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ custom_filters })
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
            console.warn("Sync HTTP connection closed, but background process may still be running.", e);
        }
    }
</script>
@endsection
