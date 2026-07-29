@extends('layouts.app')

@section('title', isset($query) ? 'Edit Query' : 'Create New Query')
@section('page-title', isset($query) ? 'Edit Query' : 'Create New Query')

@section('content')
    <div class="space-y-5">

        {{-- Header --}}
        <div class="flex items-center gap-4 animate-fade-up">
            <div
                class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center shadow-lg shadow-brand-500/20">
                <span class="material-symbols-outlined text-white text-[24px]"
                    style="font-variation-settings: 'FILL' 1;">code</span>
            </div>
            <div class="flex-1">
                <h2 class="text-h2 text-surface-900">{{ isset($query) ? 'Edit Query' : 'Create New Query' }}</h2>
                <p class="text-body-md text-surface-500 mt-0.5">Write SQL queries, test them live, and save for reuse in
                    reports.</p>
            </div>
            <a href="{{ route('query-repository.index') }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white border border-surface-200 text-surface-600 text-label-md font-medium hover:bg-surface-50 hover:border-surface-300 transition-all">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Back
            </a>
        </div>

        @if ($errors->any())
            <div class="p-4 text-sm text-red-800 rounded-xl bg-red-50 border border-red-200 animate-fade-up">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="queryForm"
            action="{{ isset($query) ? route('query-repository.update', $query->id) : route('query-repository.store') }}"
            method="POST">
            @csrf
            @if (isset($query))
                @method('PUT')
            @endif

            {{-- Connection Bar --}}
            <div
                class="bg-white rounded-2xl border border-surface-200/80 shadow-sm p-4 flex flex-wrap items-end gap-4 animate-fade-up">
                <div class="flex-1 min-w-[200px]">
                    <label class="text-label-md text-surface-700 mb-1.5 block">Database Connection <span
                            class="text-red-500">*</span></label>
                    <select name="database_connection_id" id="connection_id"
                        class="w-full bg-white border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all cursor-pointer"
                        required>
                        <option value="">-- Select Connection --</option>
                        @foreach ($connections as $conn)
                            <option value="{{ $conn->id }}" data-type="{{ $conn->type }}"
                                {{ old('database_connection_id', $query->database_connection_id ?? '') == $conn->id ? 'selected' : '' }}>
                                {{ $conn->name }} ({{ strtoupper($conn->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div id="connStatus" class="text-label-sm text-surface-400 flex items-center gap-1.5 pb-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-surface-300"></span> Select a connection
                </div>
            </div>

            {{-- Query Info --}}
            <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm p-4 mt-4 animate-fade-up"
                style="animation-delay: 0.05s;">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-label-md text-surface-700 mb-1.5 block">Query Name <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $query->name ?? '') }}"
                            class="w-full bg-white border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all"
                            required placeholder="e.g. Daily Sales Report">
                    </div>
                    <div>
                        <label class="text-label-md text-surface-700 mb-1.5 block">Category</label>
                        <input type="text" name="category" value="{{ old('category', $query->category ?? '') }}"
                            class="w-full bg-white border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all"
                            placeholder="e.g. Sales, Finance">
                    </div>
                    <div>
                        <label class="text-label-md text-surface-700 mb-1.5 block">Description</label>
                        <input type="text" name="description"
                            value="{{ old('description', $query->description ?? '') }}"
                            class="w-full bg-white border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all"
                            placeholder="Brief description...">
                    </div>
                </div>
                @if (isset($query))
                    <div class="flex items-center gap-2 mt-3">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                            {{ old('is_active', $query->is_active ?? true) ? 'checked' : '' }}
                            class="rounded border-surface-300 text-brand-600 focus:ring-brand-500">
                        <label for="is_active" class="text-label-sm text-surface-700">Active Query</label>
                        <span class="text-label-sm text-surface-400 ml-4">Version: {{ $query->version ?? 1 }}</span>
                    </div>
                @endif
            </div>

            {{-- SQL Editor --}}
            <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm mt-4 animate-fade-up"
                style="animation-delay: 0.1s;">
                <div
                    class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-brand-600">code</span>
                        SQL Query Editor
                    </h3>

                    {{-- Snippet Helper Buttons --}}
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <span class="text-surface-400 font-medium mr-1">Insert Snippet:</span>
                        <button type="button" onclick="insertSnippet('LIKE')"
                            class="px-2.5 py-1 rounded-lg bg-white border border-surface-200 hover:bg-brand-50 hover:border-brand-300 hover:text-brand-700 transition-all font-mono text-surface-700 shadow-sm"
                            title="Insert LIKE / Contains snippet">
                            <span class="text-brand-600 font-bold">+</span> LIKE (Contains)
                        </button>
                        <button type="button" onclick="insertSnippet('BETWEEN')"
                            class="px-2.5 py-1 rounded-lg bg-white border border-surface-200 hover:bg-brand-50 hover:border-brand-300 hover:text-brand-700 transition-all font-mono text-surface-700 shadow-sm"
                            title="Insert BETWEEN date range snippet">
                            <span class="text-brand-600 font-bold">+</span> BETWEEN (Date Range)
                        </button>
                        <button type="button" onclick="insertSnippet('EQUAL')"
                            class="px-2.5 py-1 rounded-lg bg-white border border-surface-200 hover:bg-brand-50 hover:border-brand-300 hover:text-brand-700 transition-all font-mono text-surface-700 shadow-sm"
                            title="Insert Equal (=) snippet">
                            <span class="text-brand-600 font-bold">+</span> Equal (=)
                        </button>

                        {{-- Sample Query Selector --}}
                        <select onchange="loadSampleQuery(this.value); this.value='';"
                            class="px-2.5 py-1 rounded-lg bg-white border border-brand-200 text-brand-700 font-medium text-xs focus:outline-none cursor-pointer shadow-sm hover:bg-brand-50">
                            <option value="">⚡ Load Sample Query...</option>
                            <option value="sales_date_range">📊 Sales Report (BETWEEN Date & LIKE Customer)</option>
                            <option value="product_search">🔍 Product Search (LIKE Name & Equal Category)</option>
                            <option value="customer_orders">🛒 Customer Orders (Equal ID & Status)</option>
                        </select>
                    </div>
                </div>

                {{-- Guide & Syntax Banner --}}
                <div
                    class="px-4 py-2.5 bg-slate-800 border-b border-slate-700 text-xs text-slate-300 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px] text-amber-400">lightbulb</span>
                        <span>Use <code
                                class="px-1.5 py-0.5 bg-slate-900 rounded text-amber-300 font-mono">@{{ parameter_name }}</code>
                            for dynamic inputs:</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 font-mono text-[11px]">
                        <span class="cursor-pointer hover:text-amber-300 transition-colors" onclick="insertSnippet('LIKE')"
                            title="Click to insert">LIKE: <code class="text-amber-300">LIKE CONCAT('%',
                                @{{ search }}, '%')</code></span>
                        <span class="cursor-pointer hover:text-emerald-300 transition-colors"
                            onclick="insertSnippet('BETWEEN')" title="Click to insert">BETWEEN: <code
                                class="text-emerald-300">BETWEEN @{{ start_date }} AND
                                @{{ end_date }}</code></span>
                        <span class="cursor-pointer hover:text-sky-300 transition-colors" onclick="insertSnippet('EQUAL')"
                            title="Click to insert">EQUAL: <code class="text-sky-300">status =
                                @{{ status }}</code></span>
                    </div>
                </div>

                <div class="p-4 relative">
                    <textarea name="sql_text" id="sql_text" spellcheck="false"
                        class="w-full bg-slate-900 text-slate-100 rounded-xl p-4 text-body-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500 min-h-[280px] resize-y"
                        required placeholder="SELECT * FROM table_name WHERE column = @{{ param }}">{{ old('sql_text', $query->sql_text ?? '') }}</textarea>

                    {{-- Quick Suggestions Bar below editor --}}
                    <div id="suggestionsBar"
                        class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-400 font-mono">
                        <span class="text-slate-500">Quick Insert:</span>
                        <button type="button"
                            onclick="insertRawText('WHERE customer_name LIKE CONCAT(\'%\', @{{ customer }}, \'%\')')"
                            class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700">@{{ customer }}
                            (LIKE)</button>
                        <button type="button"
                            onclick="insertRawText('WHERE order_date BETWEEN @{{ start_date }} AND @{{ end_date }}')"
                            class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700">@{{ start_date }}
                            AND @{{ end_date }}</button>
                        <button type="button" onclick="insertRawText('WHERE status = @{{ status }}')"
                            class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700">@{{ status }}
                            (Equal)</button>
                    </div>
                </div>
            </div>

            {{-- Dynamic Parameters --}}
            <div id="dynamicParameters"
                class="bg-white rounded-2xl border border-surface-200/80 shadow-sm mt-4 hidden animate-fade-up">
                <div class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl">
                    <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px] text-amber-500">tune</span>
                        Query Parameters
                        <span id="paramCount"
                            class="text-label-sm px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 font-medium">0</span>
                    </h3>
                </div>
                <div id="parametersContainer" class="p-4 grid grid-cols-2 md:grid-cols-4 gap-3">
                    <!-- Populated via JS -->
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-wrap items-center gap-4 mt-4 animate-fade-up" style="animation-delay: 0.15s;">
                <button type="button" id="btnPreview" onclick="executePreview()"
                    class="inline-flex items-center gap-2 px-7 py-2.5 rounded-xl bg-gradient-to-r from-brand-600 to-brand-500 text-white text-label-md font-semibold hover:from-brand-500 hover:to-brand-400 hover:shadow-lg hover:shadow-brand-500/25 hover:-translate-y-0.5 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 disabled:hover:-translate-y-0 disabled:hover:shadow-none disabled:cursor-not-allowed border border-brand-500/50"
                    disabled>
                    <span class="material-symbols-outlined text-[20px]">play_arrow</span> Test / Preview
                </button>
                <button type="button" onclick="exportResults('xlsx')" id="btnExportXlsx"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white text-emerald-700 border border-emerald-200 text-label-md font-medium hover:bg-emerald-50 hover:border-emerald-300 hover:shadow-md hover:shadow-emerald-500/10 hover:-translate-y-0.5 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 disabled:hover:-translate-y-0 disabled:hover:shadow-none disabled:cursor-not-allowed"
                    disabled>
                    <span class="material-symbols-outlined text-[20px]">table_chart</span> Export XLSX
                </button>
                <button type="button" onclick="exportResults('csv')" id="btnExportCsv"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white text-sky-700 border border-sky-200 text-label-md font-medium hover:bg-sky-50 hover:border-sky-300 hover:shadow-md hover:shadow-sky-500/10 hover:-translate-y-0.5 transition-all duration-300 active:scale-[0.97] disabled:opacity-50 disabled:hover:-translate-y-0 disabled:hover:shadow-none disabled:cursor-not-allowed"
                    disabled>
                    <span class="material-symbols-outlined text-[20px]">csv</span> Export CSV
                </button>
                <div class="ml-auto flex items-center gap-3">
                    <a href="{{ route('query-repository.index') }}"
                        class="px-5 py-2.5 rounded-xl bg-white border border-surface-200 text-surface-600 text-label-md font-medium hover:bg-surface-50 hover:border-surface-300 transition-all">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-7 py-2.5 rounded-xl bg-gradient-to-r from-brand-400 to-brand-500 text-black text-label-md font-semibold hover:from-brand-500 hover:to-brand-400 hover:shadow-lg hover:shadow-brand-500/25 hover:-translate-y-0.5 transition-all duration-300 active:scale-[0.97] border border-brand-500/50">
                        <span class="material-symbols-outlined text-[20px]">save</span> Save Query
                    </button>
                </div>
            </div>
        </form>

        {{-- Results --}}
        <div id="resultsContainer" class="hidden animate-fade-up">
            <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm">
                <div
                    class="px-4 py-3 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl flex items-center justify-between">
                    <h3 class="text-label-md font-semibold text-surface-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px] text-emerald-500">table_rows</span>
                        Results
                        <span id="resultMeta" class="text-label-sm text-surface-400 font-normal"></span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-body-sm">
                        <thead id="resultHead"
                            class="bg-surface-50 text-surface-500 text-label-sm uppercase tracking-wider"></thead>
                        <tbody id="resultBody" class="divide-y divide-surface-100"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <script>
        // csrfToken is already declared globally in app.blade.php
        const activeCsrfToken = window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute(
            'content');
        const savedParams = @json(isset($query) && $query->parameter_json ? $query->parameter_json : []);
        const queryId = {{ isset($query) ? $query->id : 'null' }};

        let currentParams = [];
        let lastPreviewSql = '';

        function showToast(msg, type = 'success') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    icon: type,
                    title: msg,
                });
            } else {
                alert(msg);
            }
        }

        // ─── Connection Status ───────────────────────────────────
        const connSelect = document.getElementById('connection_id');
        const connStatus = document.getElementById('connStatus');

        function updateConnStatus() {
            if (!connSelect) return;
            const selected = connSelect.options[connSelect.selectedIndex];
            if (!connSelect.value) {
                if (connStatus) connStatus.innerHTML =
                    '<span class="w-1.5 h-1.5 rounded-full bg-surface-300"></span> Select a connection';
            } else {
                const connType = selected ? (selected.dataset.type || '') : '';
                if (connType === 'odata') {
                    if (connStatus) connStatus.innerHTML =
                        '<span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> <span class="text-amber-600">OData — SQL not supported</span>';
                } else {
                    if (connStatus) connStatus.innerHTML =
                        '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]"></span> <span class="text-emerald-600">' +
                        (selected ? selected.text.trim() : '') + '</span>';
                }
            }
            updateButtons();
        }

        // ─── Dynamic Parameters Detection ────────────────────────
        const sqlInput = document.getElementById('sql_text');
        const dynamicParamsEl = document.getElementById('dynamicParameters');
        const paramsContainer = document.getElementById('parametersContainer');
        const paramCountEl = document.getElementById('paramCount');

        // ─── Snippet & Helper Functions ─────────────────────────
        function insertRawText(text) {
            if (!sqlInput) return;
            const start = sqlInput.selectionStart;
            const end = sqlInput.selectionEnd;
            const current = sqlInput.value;

            if (start || start === 0) {
                sqlInput.value = current.substring(0, start) + text + current.substring(end, current.length);
                sqlInput.selectionStart = start + text.length;
                sqlInput.selectionEnd = start + text.length;
            } else {
                sqlInput.value += "\n" + text;
            }
            sqlInput.focus();
            handleSqlInput();
        }

        function insertSnippet(type) {
            let snippet = '';
            if (type === 'LIKE') {
                snippet = "column_name LIKE CONCAT('%', @{{ column_name }}, '%')";
            } else if (type === 'BETWEEN') {
                snippet = "date_column BETWEEN @{{ start_date }} AND @{{ end_date }}";
            } else if (type === 'EQUAL') {
                snippet = "column_name = @{{ column_name }}";
            } else if (type === 'IN') {
                snippet = "column_name IN (@{{ value_1 }}, @{{ value_2 }})";
            }
            insertRawText(snippet);
        }

        function loadSampleQuery(preset) {
            if (!preset || !sqlInput) return;

            const samples = {
                sales_date_range: `SELECT order_id, customer_name, total_amount, order_date, status\nFROM sales_orders\nWHERE order_date BETWEEN @{{ start_date }} AND @{{ end_date }}\n  AND customer_name LIKE CONCAT('%', @{{ customer_name }}, '%')\nORDER BY order_date DESC;`,
                product_search: `SELECT product_id, product_name, category, price, stock_qty\nFROM products\nWHERE product_name LIKE CONCAT('%', @{{ product_name }}, '%')\n  AND category = @{{ category }}\nORDER BY price ASC;`,
                customer_orders: `SELECT order_id, order_date, total_amount, status\nFROM orders\nWHERE customer_id = @{{ customer_id }}\n  AND status = @{{ status }}\nORDER BY order_date DESC;`
            };

            if (samples[preset]) {
                if (sqlInput.value.trim() !== '' && !confirm('Overwrite current SQL query with sample template?')) {
                    return;
                }
                sqlInput.value = samples[preset];
                sqlInput.focus();
                handleSqlInput();
            }
        }

        function handleSqlInput() {
            if (!sqlInput) return;
            const regex = /\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g;
            const matches = [...sqlInput.value.matchAll(regex)].map(m => m[1]);
            const unique = [...new Set(matches)];

            if (JSON.stringify(unique) !== JSON.stringify(currentParams)) {
                currentParams = unique;
                if (currentParams.length > 0 && dynamicParamsEl && paramsContainer) {
                    dynamicParamsEl.classList.remove('hidden');
                    if (paramCountEl) paramCountEl.textContent = currentParams.length;
                    let html = '';
                    
                    const formatLabel = (str) => str.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

                    currentParams.forEach(p => {
                        const saved = savedParams.find(sp => sp.name === p);
                        const paramType = saved ? saved.type : 'string';
                        const inputType = paramType === 'date' ? 'date' : (paramType === 'number' ? 'number' :
                            'text');

                        html += `
                        <div>
                            <label class="block text-label-sm font-medium text-surface-600 mb-1">${formatLabel(p)} <span class="text-xs text-surface-400">(${paramType})</span></label>
                            <input type="${inputType}" data-param="${p}" class="query-param-input w-full bg-white border border-surface-300 rounded-lg px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all" placeholder="Value...">
                        </div>
                    `;
                    });
                    paramsContainer.innerHTML = html;
                } else if (dynamicParamsEl && paramsContainer) {
                    dynamicParamsEl.classList.add('hidden');
                    paramsContainer.innerHTML = '';
                }
            }
            updateButtons();
        }

        // ─── Button State ────────────────────────────────────────
        function updateButtons() {
            if (!sqlInput || !connSelect) return;
            const hasSql = sqlInput.value.trim().length > 0;
            const hasConn = !!connSelect.value;
            const selected = connSelect.options[connSelect.selectedIndex];
            const connType = selected ? (selected.dataset.type || '') : '';
            const isOdata = connType === 'odata';
            const enabled = hasSql && hasConn && !isOdata;

            const btnPreview = document.getElementById('btnPreview');
            if (btnPreview) btnPreview.disabled = !enabled;
            const btnExportXlsx = document.getElementById('btnExportXlsx');
            if (btnExportXlsx) btnExportXlsx.disabled = !lastPreviewSql;
            const btnExportCsv = document.getElementById('btnExportCsv');
            if (btnExportCsv) btnExportCsv.disabled = !lastPreviewSql;
        }

        // ─── Execute Preview ─────────────────────────────────────
        async function executePreview() {
            if (!sqlInput || !connSelect) return;
            const sql = sqlInput.value.trim();
            const connId = connSelect.value;
            if (!sql || !connId) return;

            const btn = document.getElementById('btnPreview');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="material-symbols-outlined text-[18px] spin">sync</span> Executing...';
            }

            // Gather bindings
            const bindings = {};
            document.querySelectorAll('.query-param-input').forEach(input => {
                bindings[input.dataset.param] = input.value;
            });

            const requestBody = {
                database_connection_id: connId,
                sql_text: sql,
                bindings: bindings
            };
            if (queryId) {
                requestBody.query_repository_id = queryId;
            }

            try {
                const res = await fetch("{{ route('api.query-repository.preview') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': activeCsrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });

                const contentType = res.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    if (res.status === 419) throw new Error('Session expired. Please refresh the page.');
                    if (res.status === 403) throw new Error('Permission denied. Check your role permissions.');
                    throw new Error('Server error (' + res.status + ')');
                }

                const data = await res.json();

                if (data.success) {
                    lastPreviewSql = sql;
                    renderResults(data);
                    showToast(`Query executed: ${data.count} rows in ${parseFloat(data.duration).toFixed(3)}s`);
                } else {
                    throw new Error(data.message || 'Query failed.');
                }
            } catch (err) {
                showToast(err.message, 'error');
                // Show error in results area
                const container = document.getElementById('resultsContainer');
                if (container) {
                    container.classList.remove('hidden');
                    document.getElementById('resultMeta').textContent = 'Error';
                    document.getElementById('resultHead').innerHTML = '';
                    document.getElementById('resultBody').innerHTML =
                        `<tr><td class="py-8 text-center text-red-500 font-medium">${err.message}</td></tr>`;
                }
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML =
                        '<span class="material-symbols-outlined text-[20px]">play_arrow</span> Test / Preview';
                }
                updateButtons();
            }
        }

        // ─── Render Results ──────────────────────────────────────
        function renderResults(data) {
            const container = document.getElementById('resultsContainer');
            const thead = document.getElementById('resultHead');
            const tbody = document.getElementById('resultBody');
            const meta = document.getElementById('resultMeta');

            if (!container || !thead || !tbody || !meta) return;

            container.classList.remove('hidden');
            meta.textContent = `${data.count} rows — ${parseFloat(data.duration).toFixed(3)}s`;

            // Header
            if (!data.columns || data.columns.length === 0) {
                thead.innerHTML = '';
                tbody.innerHTML = '<tr><td class="py-8 text-center text-surface-400">No columns returned</td></tr>';
                return;
            }

            let headHtml = '<tr>';
            data.columns.forEach(col => {
                headHtml += `<th class="py-3 px-4 text-left font-semibold whitespace-nowrap">${col.name}</th>`;
            });
            headHtml += '</tr>';
            thead.innerHTML = headHtml;

            // Body
            if (!data.data || data.data.length === 0) {
                tbody.innerHTML =
                    `<tr><td colspan="${data.columns.length}" class="py-8 text-center text-surface-400">No results found</td></tr>`;
                return;
            }

            let bodyHtml = '';
            data.data.forEach((row, i) => {
                bodyHtml +=
                    `<tr class="${i % 2 === 0 ? 'bg-white' : 'bg-surface-50/50'} hover:bg-blue-50/30 transition-colors">`;
                data.columns.forEach(col => {
                    const val = row[col.name];
                    if (val === null || val === undefined) {
                        bodyHtml +=
                            `<td class="py-2.5 px-4 whitespace-nowrap max-w-[300px] truncate"><span class="text-surface-300 italic">NULL</span></td>`;
                    } else {
                        const escaped = String(val).replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        bodyHtml +=
                            `<td class="py-2.5 px-4 text-surface-700 whitespace-nowrap max-w-[300px] truncate">${escaped}</td>`;
                    }
                });
                bodyHtml += '</tr>';
            });
            tbody.innerHTML = bodyHtml;

            container.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        // ─── Export ───────────────────────────────────────────────
        async function exportResults(format) {
            if (!lastPreviewSql) {
                showToast('Execute the query first before exporting', 'warning');
                return;
            }

            const connId = connSelect ? connSelect.value : null;
            const btn = format === 'xlsx' ? document.getElementById('btnExportXlsx') : document.getElementById(
                'btnExportCsv');
            const origHtml = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="material-symbols-outlined text-[18px] spin">sync</span> Exporting...';
            }

            // Gather bindings
            const bindings = {};
            document.querySelectorAll('.query-param-input').forEach(input => {
                bindings[input.dataset.param] = input.value;
            });

            try {
                const res = await fetch("{{ route('api.query-repository.export') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': activeCsrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        database_connection_id: connId,
                        sql_text: lastPreviewSql,
                        bindings: bindings,
                        format: format
                    })
                });
                const data = await res.json();

                if (data.success) {
                    window.location.href = "{{ url('/api/report/query-repository/download') }}/" + data.token;
                    showToast(`Export ready: ${data.filename} (${data.size})`);
                } else {
                    throw new Error(data.message);
                }
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            }
        }

        // Initialize Event Listeners
        document.addEventListener('DOMContentLoaded', () => {
            if (connSelect) {
                connSelect.addEventListener('change', updateConnStatus);
                updateConnStatus();
            }
            if (sqlInput) {
                sqlInput.addEventListener('input', handleSqlInput);
                handleSqlInput();
            }
        });
    </script>
@endsection
