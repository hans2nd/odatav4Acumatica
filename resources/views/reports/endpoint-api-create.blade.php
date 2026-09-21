@extends('layouts.app')

@section('title', 'Create Endpoint API')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('endpoint.api.index') }}" class="w-10 h-10 rounded-xl bg-surface-100 flex items-center justify-center text-surface-500 hover:bg-surface-200 hover:text-surface-700 transition-colors">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div>
            <h2 class="text-h2 text-surface-900">Create New Endpoint</h2>
            <p class="text-body-md text-surface-500 mt-0.5">Select a connection and table to expose as an API endpoint.</p>
        </div>
    </div>

    @if ($errors->any())
    <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-start gap-3 text-red-700">
        <span class="material-symbols-outlined text-[20px] text-red-500">error</span>
        <div>
            <ul class="text-body-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-2xl border border-surface-200 shadow-sm p-6">
        @if(empty($dbConnections))
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 flex items-start gap-3">
                <span class="material-symbols-outlined shrink-0">warning</span>
                <div>
                    <p class="font-medium text-label-md">No connected database available.</p>
                    <p class="text-body-sm mt-1">You need to connect to at least one database in <a href="{{ route('settings.index') }}" class="underline font-semibold text-amber-800 hover:text-amber-900">Settings &gt; Connection Management</a> before creating an endpoint.</p>
                </div>
            </div>
        @else
            <form action="{{ route('endpoint.api.store') }}" method="POST" class="space-y-5" onsubmit="return onFormSubmit()">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="relative z-30">
                    <label class="text-label-md text-surface-700 mb-1.5 block">Database Connection</label>
                    <div class="flex gap-2">
                        <select name="connection_key" id="apiConnection" required
                            class="flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20">
                            <option value="" disabled {{ old('connection_key') ? '' : 'selected' }}>Select connection...</option>
                            @foreach ($dbConnections as $conn)
                                <option value="{{ $conn['key'] }}" {{ old('connection_key') == $conn['key'] ? 'selected' : '' }}>{{ $conn['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="button" onclick="validateConnection()" class="px-4 py-2 bg-surface-100 text-surface-700 text-label-sm rounded-xl hover:bg-surface-200 transition-colors font-semibold">
                            Validate
                        </button>
                    </div>
                </div>

                <div class="relative z-20">
                    <label class="text-label-md text-surface-700 mb-1.5 block">Source Table</label>
                    <div x-data="customSelect('apiTableInput')" class="relative">
                        <select name="table_name" id="apiTableInput" class="hidden" x-ref="select" required onchange="window.selectApiTable(this.value)">
                            <option value="">Select connection first...</option>
                        </select>
                        
                        <button type="button" @click="toggle" @click.away="close" class="w-full bg-surface-50 border border-surface-200 px-3 py-2 text-sm rounded-xl focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all font-mono shadow-sm flex justify-between items-center text-left" :disabled="options.length <= 1">
                            <span x-text="selectedLabel" class="truncate" :class="!selectedValue ? 'text-surface-400' : 'text-surface-900'"></span>
                            <span class="material-symbols-outlined text-[18px] text-surface-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''">expand_more</span>
                        </button>
                        
                        <div x-show="open" x-transition.opacity.duration.200ms class="absolute z-30 w-full mt-1 bg-white border border-surface-200 rounded-xl shadow-lg max-h-60 overflow-y-auto" style="display: none;">
                            <ul class="py-1">
                                <template x-for="option in options" :key="option.value">
                                    <li @click="selectOption(option.value)" 
                                        class="px-3.5 py-2.5 hover:bg-surface-50 cursor-pointer text-sm transition-colors flex justify-between items-center"
                                        :class="option.value === selectedValue ? 'bg-brand-50 text-brand-700 font-medium' : 'text-surface-700'"
                                        x-show="option.value !== ''"
                                    >
                                        <span x-text="option.label"></span>
                                        <span x-show="option.value === selectedValue" class="material-symbols-outlined text-[16px] text-brand-600">check</span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 relative z-10">
                <div>
                    <label class="text-label-md text-surface-700 mb-1.5 block">Endpoint Name</label>
                    <input type="text" name="name" id="apiName" required placeholder="e.g. Sales Data" value="{{ old('name') }}"
                        oninput="generateSlug()"
                        class="w-full rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20">
                </div>

                <div>
                    <label class="text-label-md text-surface-700 mb-1.5 block">Endpoint Slug (URL Path)</label>
                    <div class="flex items-center">
                        <span class="px-3 py-2 bg-surface-100 border border-r-0 border-surface-200 rounded-l-xl text-surface-500 text-body-sm">/data/</span>
                        <input type="text" name="endpoint_slug" id="apiSlug" required placeholder="sales_data" value="{{ old('endpoint_slug') }}"
                            class="w-full rounded-r-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 flex-1">
                    </div>
                    <p class="text-[11px] text-surface-400 mt-1">Only letters, numbers, dashes, and underscores.</p>
                </div>
            </div>

            <div class="pt-2 border-t border-surface-100 relative z-0">
                <div class="flex items-center justify-between mb-3">
                    <label class="text-label-md text-surface-700">Select Columns</label>
                    <div class="flex gap-2">
                        <button type="button" onclick="toggleAllColumns(true)" class="px-2 py-1 text-[11px] font-medium bg-surface-100 text-surface-600 rounded-md hover:bg-surface-200">Select All</button>
                        <button type="button" onclick="toggleAllColumns(false)" class="px-2 py-1 text-[11px] font-medium bg-surface-100 text-surface-600 rounded-md hover:bg-surface-200">Deselect All</button>
                    </div>
                </div>
                <p class="text-[12px] text-surface-500 mb-3">If no columns are selected, all columns will be exposed by default.</p>
                <div id="columnsContainer" class="bg-surface-50 border border-surface-200 rounded-xl p-4 min-h-[100px] max-h-60 overflow-y-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                    <p class="col-span-full text-center text-surface-400 text-body-sm mt-6">Select a table to load columns</p>
                </div>
            </div>
            
            <div class="pt-2 border-t border-surface-100 relative z-0">
                <div class="flex items-center justify-between mb-4">
                    <label class="text-label-md text-surface-700">Filters <span class="text-body-sm text-surface-400 font-normal">(Optional)</span></label>
                    <button type="button" onclick="addFilterRow()" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-100 text-surface-700 text-label-sm hover:bg-surface-200 transition-all duration-200">
                        <span class="material-symbols-outlined text-[14px]">add</span> Add Filter
                    </button>
                </div>
                
                <datalist id="apiFilterFieldDataList"></datalist>
                <div id="apiFiltersContainer" class="space-y-2">
                    {{-- Filter rows --}}
                </div>
                <div id="apiEmptyFiltersMsg" class="py-4 text-center border-2 border-dashed border-surface-200 rounded-xl bg-surface-50">
                    <span class="material-symbols-outlined text-surface-300 text-2xl mb-1 block">filter_alt_off</span>
                    <p class="text-body-sm text-surface-400">No filters added. All data will be returned.</p>
                </div>
                
                <input type="hidden" name="filters" id="apiFiltersHiddenInput" value="{{ old('filters') }}">
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-surface-100 mt-6 relative z-0">
                <a href="{{ route('endpoint.api.index') }}" class="px-4 py-2 bg-surface-100 text-surface-700 text-label-sm rounded-xl hover:bg-surface-200 transition-colors font-semibold">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2 bg-brand-500 text-white text-label-sm rounded-xl hover:bg-brand-600 focus:ring-4 focus:ring-brand-500/20 transition-all font-semibold flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Create Endpoint
                </button>
            </div>
        </form>
        @endif
    </div>
</div>

<script>
    // JS for dropdown
    const allTablesData = @json($allTables);
    let fetchingColumns = false;
    let allFetchedColumns = [];

    function validateConnection(forceFocus = true) {
        const conn = document.getElementById('apiConnection').value;
        const tableSelect = document.getElementById('apiTableInput');
        
        if (!conn) {
            alert('Please select a database connection first.');
            return;
        }

        // Save to localStorage so it remembers the connection
        localStorage.setItem('last_endpoint_connection', conn);
        
        tableSelect.value = '';
        
        document.getElementById('columnsContainer').innerHTML = '<p class="col-span-full text-center text-surface-400 text-body-sm mt-6">Select a table to load columns</p>';
        allFetchedColumns = [];
        populateFilterDataList();
        
        renderApiTableDropdown(allTablesData[conn] || []);
    }

    // Expose select table globally for inline onclick
    window.selectApiTable = function(table) {
        if (!table) return;
        
        // Fetch columns
        fetchColumns(document.getElementById('apiConnection').value, table);
        
        // Trigger auto name generation if name is empty
        const apiNameInput = document.getElementById('apiName');
        if(!apiNameInput.value) {
            apiNameInput.value = table;
            generateSlug();
        }
    };

    function renderApiTableDropdown(tables) {
        const select = document.getElementById('apiTableInput');
        
        if (tables.length === 0) {
            select.innerHTML = '<option value="">No tables available for this connection</option>';
            return;
        }

        let html = '<option value="">Select table...</option>';
        tables.forEach(table => {
            html += `<option value="${table}">${table}</option>`;
        });
        
        select.innerHTML = html;
        // Alpine's MutationObserver in customSelect will automatically pick up the DOM changes
    }

    function generateSlug() {
        const name = document.getElementById('apiName').value;
        const slugInput = document.getElementById('apiSlug');
        
        const slug = name
            .toLowerCase()
            .replace(/\s+/g, '_')
            .replace(/[^a-z0-9\-_]/g, '');
            
        slugInput.value = slug;
    }
    
    function fetchColumns(conn, table, preselected = []) {
        if (!conn || !table) return;
        
        const container = document.getElementById('columnsContainer');
        container.innerHTML = '<p class="col-span-full text-center text-surface-400 text-body-sm mt-6"><span class="material-symbols-outlined animate-spin text-[24px]">progress_activity</span><br>Loading columns...</p>';
        
        fetch(`/api/report-builder/columns?connection=${encodeURIComponent(conn)}&table=${encodeURIComponent(table)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allFetchedColumns = data.columns;
                    renderColumns(data.columns, preselected);
                    populateFilterDataList();
                } else {
                    container.innerHTML = '<p class="col-span-full text-center text-red-500 text-body-sm mt-6">Failed to load columns.</p>';
                    allFetchedColumns = [];
                    populateFilterDataList();
                }
            })
            .catch(err => {
                container.innerHTML = '<p class="col-span-full text-center text-red-500 text-body-sm mt-6">Error loading columns.</p>';
                allFetchedColumns = [];
                populateFilterDataList();
            });
    }
    
    function renderColumns(columns, preselected = []) {
        const container = document.getElementById('columnsContainer');
        container.innerHTML = '';
        
        if (columns.length === 0) {
            container.innerHTML = '<p class="col-span-full text-center text-surface-400 text-body-sm mt-6">No columns found.</p>';
            return;
        }
        
        columns.forEach(col => {
            const isChecked = preselected.length === 0 || preselected.includes(col);
            
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2 p-2 hover:bg-surface-100 rounded-lg transition-colors';
            
            div.innerHTML = `
                <input type="checkbox" id="col_${col}" name="columns[]" value="${col}" ${isChecked ? 'checked' : ''} class="rounded border-surface-300 text-brand-500 focus:ring-brand-500/20">
                <label for="col_${col}" class="text-body-sm text-surface-700 cursor-pointer select-none truncate flex-1" title="${col}">${col}</label>
            `;
            container.appendChild(div);
        });
    }
    
    function toggleAllColumns(state) {
        const checkboxes = document.querySelectorAll('#columnsContainer input[type="checkbox"]');
        checkboxes.forEach(cb => cb.checked = state);
    }
    
    // FILTERS
    function populateFilterDataList() {
        const dl = document.getElementById('apiFilterFieldDataList');
        dl.innerHTML = '';
        allFetchedColumns.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c;
            dl.appendChild(opt);
        });
    }

    function addFilterRow(field = '', op = 'eq', val = '') {
        const container = document.getElementById('apiFiltersContainer');
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 api-filter-row animate-fade-up';
        
        const uniqueOpId = 'filter-op-' + Math.random().toString(36).substr(2, 9);
        const opOptions = `
            <option value="eq" ${op === 'eq' ? 'selected' : ''}>Equals (=)</option>
            <option value="ne" ${op === 'ne' ? 'selected' : ''}>Not Eq (!=)</option>
            <option value="contains" ${op === 'contains' ? 'selected' : ''}>Contains</option>
            <option value="startswith" ${op === 'startswith' ? 'selected' : ''}>Starts With</option>
            <option value="endswith" ${op === 'endswith' ? 'selected' : ''}>Ends With</option>
            <option value="gt" ${op === 'gt' ? 'selected' : ''}>Greater (>)</option>
            <option value="ge" ${op === 'ge' ? 'selected' : ''}>Greater Eq (>=)</option>
            <option value="lt" ${op === 'lt' ? 'selected' : ''}>Less (<)</option>
            <option value="le" ${op === 'le' ? 'selected' : ''}>Less Eq (<=)</option>
        `;

        const uniqueFieldId = 'filter-field-' + Math.random().toString(36).substr(2, 9);
        let fieldOptions = '<option value="" disabled ' + (!field ? 'selected' : '') + '>Select Field...</option>';
        
        const dl = document.getElementById('apiFilterFieldDataList');
        if (dl && dl.options) {
            Array.from(dl.options).forEach(opt => {
                const c = opt.value;
                fieldOptions += `<option value="${c}" ${field === c ? 'selected' : ''}>${c}</option>`;
            });
        }

        row.innerHTML = `
                <div x-data="customSelect('${uniqueFieldId}')" class="relative flex-1">
                    <select id="${uniqueFieldId}" class="hidden api-filter-field" x-ref="select" onchange="updateApiFilterType(this)">
                        ${fieldOptions}
                    </select>
                    
                    <button type="button" @click="toggle" @click.away="close" class="w-full bg-surface-50 border border-surface-200 px-3 py-2 text-sm rounded-xl focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all font-mono shadow-sm flex justify-between items-center text-left">
                        <span x-text="selectedLabel" class="truncate" :class="!selectedValue ? 'text-surface-400' : 'text-surface-900'"></span>
                        <span class="material-symbols-outlined text-[18px] text-surface-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''">expand_more</span>
                    </button>
                    
                    <div x-show="open" x-transition.opacity.duration.200ms class="absolute z-30 w-full mt-1 bg-white border border-surface-200 rounded-xl shadow-lg max-h-60 overflow-y-auto" style="display: none;">
                        <ul class="py-1">
                            <template x-for="option in options" :key="option.value">
                                <li @click="selectOption(option.value)" 
                                    class="px-3.5 py-2.5 hover:bg-surface-50 cursor-pointer text-sm transition-colors flex justify-between items-center"
                                    :class="option.value === selectedValue ? 'bg-brand-50 text-brand-700 font-medium' : 'text-surface-700'"
                                >
                                    <span x-text="option.label"></span>
                                    <span x-show="option.value === selectedValue" class="material-symbols-outlined text-[16px] text-brand-600">check</span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
                
                <div x-data="customSelect('${uniqueOpId}')" class="relative w-40 shrink-0">
                    <select id="${uniqueOpId}" class="hidden api-filter-operator" x-ref="select">
                        ${opOptions}
                    </select>
                    
                    <button type="button" @click="toggle" @click.away="close" class="w-full bg-surface-50 border border-surface-200 px-3 py-2 text-sm rounded-xl focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all font-mono shadow-sm flex justify-between items-center text-left">
                        <span x-text="selectedLabel" class="truncate" :class="!selectedValue ? 'text-surface-400' : 'text-surface-900'"></span>
                        <span class="material-symbols-outlined text-[18px] text-surface-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''">expand_more</span>
                    </button>
                    
                    <div x-show="open" x-transition.opacity.duration.200ms class="absolute z-30 w-full mt-1 bg-white border border-surface-200 rounded-xl shadow-lg max-h-60 overflow-y-auto" style="display: none;">
                        <ul class="py-1">
                            <template x-for="option in options" :key="option.value">
                                <li @click="selectOption(option.value)" 
                                    class="px-3.5 py-2.5 hover:bg-surface-50 cursor-pointer text-sm transition-colors flex justify-between items-center"
                                    :class="option.value === selectedValue ? 'bg-brand-50 text-brand-700 font-medium' : 'text-surface-700'"
                                >
                                    <span x-text="option.label"></span>
                                    <span x-show="option.value === selectedValue" class="material-symbols-outlined text-[16px] text-brand-600">check</span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                <input type="text" class="api-filter-value flex-1 rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-body-sm focus:border-brand-500 focus:ring focus:ring-brand-500/20 py-2" placeholder="Value..." value="${val}">
                <button type="button" onclick="removeFilterRow(this)" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all duration-200 shrink-0" title="Remove filter">
                    <span class="material-symbols-outlined text-[16px]">delete</span>
                </button>
            `;

        container.appendChild(row);
        checkEmptyFilters();
        
        // Initialize the input type if field is pre-filled (e.g. from restored filters)
        if (field) {
            updateApiFilterType(row.querySelector('.api-filter-field'));
        }
    }

    function removeFilterRow(btn) {
        btn.closest('.api-filter-row').remove();
        checkEmptyFilters();
    }

    function checkEmptyFilters() {
        const rows = document.querySelectorAll('.api-filter-row');
        document.getElementById('apiEmptyFiltersMsg').classList.toggle('hidden', rows.length > 0);
    }

    function updateApiFilterType(fieldInput) {
        const valInput = fieldInput.closest('.api-filter-row').querySelector('.api-filter-value');
        const fieldName = fieldInput.value.toLowerCase();
        
        const dateKeywords = ['date', 'time', '_at', 'dt', 'tgl', 'tanggal'];
        if (dateKeywords.some(kw => fieldName.includes(kw))) {
            valInput.type = 'date';
        } else {
            valInput.type = 'text';
        }
    }

    function getFilters() {
        const filters = [];
        document.querySelectorAll('.api-filter-row').forEach(row => {
            const field = row.querySelector('.api-filter-field').value;
            const op = row.querySelector('.api-filter-operator').value;
            const val = row.querySelector('.api-filter-value').value;
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
    
    function onFormSubmit() {
        const filters = getFilters();
        if(filters.length > 0) {
            document.getElementById('apiFiltersHiddenInput').value = JSON.stringify(filters);
        } else {
            document.getElementById('apiFiltersHiddenInput').value = '';
        }
        return true;
    }
    
    function restoreOldFilters() {
        const oldFiltersRaw = document.getElementById('apiFiltersHiddenInput').value;
        if(oldFiltersRaw) {
            try {
                const filters = JSON.parse(oldFiltersRaw);
                filters.forEach(f => {
                    addFilterRow(f.field, f.operator, f.value);
                });
            } catch (e) {
                console.error("Failed to parse old filters");
            }
        }
    }
    
    // Setup initial state
    document.addEventListener('DOMContentLoaded', () => {
        const connSelect = document.getElementById('apiConnection');
        const oldConn = @json(old('connection_key'));
        
        // Check for old value from form validation fail OR from localStorage
        const storedConn = localStorage.getItem('last_endpoint_connection');
        
        if (oldConn) {
            connSelect.value = oldConn;
        } else if (storedConn && Array.from(connSelect.options).some(opt => opt.value === storedConn)) {
            // Restore from localStorage if no old value exists and the option is valid
            connSelect.value = storedConn;
        }
        
        if(connSelect.value) {
            // Soft validation (don't force focus on input)
            validateConnection(false);
            
            const tableInput = document.getElementById('apiTableInput');
            const oldTable = @json(old('table_name'));
            
            if(oldTable) {
                // If there's an old table value from validation
                tableInput.value = oldTable;
                const oldColumns = @json(old('columns', []));
                fetchColumns(connSelect.value, oldTable, oldColumns);
            }
        }
        
        restoreOldFilters();
    });
</script>
@endsection