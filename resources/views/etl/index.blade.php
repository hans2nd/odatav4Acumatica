@extends('layouts.app')

@section('title', 'ETL Syncron — PL Sync Data Warehouse')
@section('page-title', 'ETL Syncron')

@section('content')
    <div class="space-y-6">
        @if (empty($pipelines))
            <div class="bg-white rounded-2xl border border-surface-200 p-10 text-center shadow-card">
                <span class="material-symbols-outlined text-4xl text-surface-300 mb-2 block">database</span>
                <h3 class="text-lg font-bold text-surface-800">Belum Ada Entitas SQL Server</h3>
                <p class="text-sm text-surface-500 mt-1">Tambahkan entitas dengan koneksi SQL Server di menu General Settings terlebih dahulu.</p>
                <a href="{{ route('settings.index') }}" class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition-all">
                    <span class="material-symbols-outlined text-[16px]">settings</span> Buka General Settings
                </a>
            </div>
        @else
            {{-- Pipeline Selector --}}
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-surface-500">Pilih data yang ingin disinkronkan lalu klik <strong>Mulai Sinkronisasi</strong>. Pengaturan default berjalan otomatis.</p>
                </div>
                <a href="{{ route('settings.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-surface-200 text-xs font-semibold text-surface-600 hover:bg-surface-50 transition-all">
                    <span class="material-symbols-outlined text-[15px]">tune</span> Kelola Entitas
                </a>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                @foreach ($pipelines as $key => $pipe)
                    <button type="button"
                        id="pipe-tab-{{ $key }}"
                        onclick="selectPipeline('{{ $key }}')"
                        class="pipeline-tab-btn inline-flex items-center gap-2.5 px-4 py-2.5 rounded-xl border text-left transition-all duration-200 {{ $loop->first ? 'bg-brand-600 text-white border-brand-600 shadow-sm' : 'bg-white text-surface-700 border-surface-200 hover:bg-surface-50' }}">
                        <span class="material-symbols-outlined text-[18px]">{{ $pipe['is_sp'] ? 'sync_alt' : 'table_view' }}</span>
                        <div>
                            <div class="text-xs font-bold leading-tight">{{ $pipe['name'] }}</div>
                            <div class="text-[11px] opacity-80 mt-0.5">{{ $pipe['source_conn_name'] }} → {{ $pipe['table_name'] }}</div>
                        </div>
                    </button>
                @endforeach
            </div>

            {{-- Active Pipeline Detail Panels --}}
            @foreach ($pipelines as $key => $pipe)
                <div id="pipe-panel-{{ $key }}" class="pipeline-panel {{ $loop->first ? '' : 'hidden' }} space-y-5"
                    data-key="{{ $key }}"
                    data-is-sp="{{ $pipe['is_sp'] ? '1' : '0' }}">

                    {{-- 3 Clean Summary Cards --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Card 1: Sumber --}}
                        <div class="bg-white rounded-2xl border border-surface-200/70 p-5 shadow-card">
                            <div class="flex items-center justify-between text-xs text-surface-400 mb-1.5">
                                <span class="font-semibold uppercase tracking-wider">Sumber SQL Server</span>
                                <span class="material-symbols-outlined text-[18px] text-indigo-500">database</span>
                            </div>
                            <div class="text-sm font-bold text-surface-900 truncate" title="{{ $pipe['source_conn_name'] }}">{{ $pipe['source_conn_name'] }}</div>
                            <div class="text-xs text-surface-500 font-mono mt-1 truncate" title="{{ $pipe['is_sp'] ? $pipe['get_proc'] : $pipe['source_object'] }}">
                                {{ $pipe['is_sp'] ? $pipe['get_proc'] : $pipe['source_object'] }}
                            </div>
                        </div>

                        {{-- Card 2: Tujuan MySQL --}}
                        <div class="bg-white rounded-2xl border border-surface-200/70 p-5 shadow-card">
                            <div class="flex items-center justify-between text-xs text-surface-400 mb-1.5">
                                <span class="font-semibold uppercase tracking-wider">Data di MySQL</span>
                                <a href="{{ $pipe['type'] === 'master' ? route('master-data.show', $key) : route('transactions.show', $key) }}"
                                    class="inline-flex items-center gap-1 font-semibold text-brand-600 hover:text-brand-700 normal-case">
                                    Lihat Data <span class="material-symbols-outlined text-[14px]">open_in_new</span>
                                </a>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span id="mysql-rows-{{ $key }}" class="text-2xl font-extrabold text-surface-900 font-mono">{{ number_format($pipe['mysql_rows']) }}</span>
                                <span class="text-xs text-surface-500">baris</span>
                            </div>
                            <div class="text-xs text-surface-500 font-mono mt-1 truncate">{{ $pipe['table_name'] }}</div>
                        </div>

                        {{-- Card 3: Status --}}
                        <div class="bg-white rounded-2xl border border-surface-200/70 p-5 shadow-card">
                            <div class="flex items-center justify-between text-xs text-surface-400 mb-1.5">
                                <span class="font-semibold uppercase tracking-wider">Status</span>
                                <span id="sp-lock-badge-{{ $key }}" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-surface-100 text-surface-600">
                                    Memuat...
                                </span>
                            </div>
                            <div class="text-xs text-surface-500 mt-1">Selesai Terakhir</div>
                            <div id="wm-last-sync-{{ $key }}" class="text-sm font-bold text-surface-900 font-mono mt-0.5">-</div>
                            <div id="wm-sub-watermark-{{ $key }}" class="text-[11px] text-surface-400 font-mono mt-0.5 truncate hidden"></div>
                        </div>
                    </div>

                    {{-- Main Control Box (Simple & Zero-Config by Default) --}}
                    <div class="bg-white rounded-2xl border border-surface-200/70 p-6 shadow-card space-y-5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h3 class="text-base font-bold text-surface-900">Jalankan Sinkronisasi</h3>
                                <p class="text-xs text-surface-500 mt-0.5">Mode otomatis melanjutkan dari titik sinkronisasi terakhir agar cepat dan ringan.</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" onclick="refreshPipelineState('{{ $key }}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-surface-100 text-surface-700 text-xs font-semibold hover:bg-surface-200 transition-all"
                                    title="Muat ulang status terbaru">
                                    <span class="material-symbols-outlined text-[16px]">refresh</span> Refresh
                                </button>
                                <button type="button" onclick="unlockPipeline('{{ $key }}')"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-surface-100 text-surface-700 text-xs font-semibold hover:bg-amber-50 hover:text-amber-800 transition-all"
                                    title="Buka kunci jika proses sebelumnya terhenti">
                                    <span class="material-symbols-outlined text-[16px]">lock_open</span> Reset Status
                                </button>
                                <button type="button" id="btn-run-etl-{{ $key }}" onclick="runEtlPipeline('{{ $key }}')"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-bold hover:bg-brand-700 active:scale-[0.98] transition-all shadow-sm">
                                    <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                                    Mulai Sinkronisasi
                                </button>
                            </div>
                        </div>

                        {{-- Simple Mode Options --}}
                        <div class="flex flex-wrap items-center gap-3 pt-1">
                            <label class="cursor-pointer">
                                <input type="radio" name="run_mode_{{ $key }}" value="auto_watermark" class="peer sr-only" checked onchange="toggleCustomDateInput('{{ $key }}')">
                                <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-surface-200 text-xs font-semibold text-surface-700 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700 hover:bg-surface-50 transition-all">
                                    <span class="material-symbols-outlined text-[15px]">bolt</span>
                                    Otomatis (Lanjutkan Terakhir)
                                </span>
                            </label>

                            @if ($pipe['is_sp'] || !empty($pipe['watermark_col']))
                                <label class="cursor-pointer">
                                    <input type="radio" name="run_mode_{{ $key }}" value="custom_date" class="peer sr-only" onchange="toggleCustomDateInput('{{ $key }}')">
                                    <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-surface-200 text-xs font-semibold text-surface-700 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700 hover:bg-surface-50 transition-all">
                                        <span class="material-symbols-outlined text-[15px]">calendar_month</span>
                                        Tarik Mulai Tanggal...
                                    </span>
                                </label>
                            @endif

                            @if ($pipe['is_sp'])
                                <label class="cursor-pointer">
                                    <input type="radio" name="run_mode_{{ $key }}" value="order_date_range" class="peer sr-only" onchange="toggleCustomDateInput('{{ $key }}')">
                                    <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-surface-200 text-xs font-semibold text-surface-700 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700 hover:bg-surface-50 transition-all">
                                        <span class="material-symbols-outlined text-[15px]">date_range</span>
                                        Rentang OrderDate
                                    </span>
                                </label>
                            @endif

                            <label class="cursor-pointer">
                                <input type="radio" name="run_mode_{{ $key }}" value="full" class="peer sr-only" onchange="toggleCustomDateInput('{{ $key }}')">
                                <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-surface-200 text-xs font-semibold text-surface-700 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700 hover:bg-surface-50 transition-all">
                                    <span class="material-symbols-outlined text-[15px]">sync_saved_locally</span>
                                    Muat Ulang Penuh
                                </span>
                            </label>

                            {{-- Conditional Date Picker (hidden until "Tarik Mulai Tanggal..." is chosen) --}}
                            <div id="etl-custom-date-wrap-{{ $key }}" class="hidden items-center gap-2 pl-2">
                                <span class="text-xs font-medium text-surface-600">Dari tanggal:</span>
                                <input type="date" id="etl-custom-date-{{ $key }}" value="{{ $pipe['custom_start_date'] ?: now()->startOfMonth()->format('Y-m-d') }}"
                                    class="rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-xs font-mono py-1.5 px-3 focus:border-brand-500 focus:ring focus:ring-brand-500/20">
                            </div>

                            {{-- Conditional OrderDate Range Picker (hidden until "Rentang OrderDate" is chosen) --}}
                            @if ($pipe['is_sp'])
                                <div id="etl-order-date-wrap-{{ $key }}" class="hidden flex-wrap items-center gap-3 w-full pt-2 border-t border-surface-100">
                                    <div class="inline-flex items-center gap-2">
                                        <span class="text-xs font-medium text-surface-600">OrderDate Dari:</span>
                                        <input type="date" id="etl-order-date-from-{{ $key }}" value="{{ now()->startOfMonth()->format('Y-m-d') }}"
                                            class="rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-xs font-mono py-1.5 px-3 focus:border-brand-500 focus:ring focus:ring-brand-500/20">
                                    </div>
                                    <div class="inline-flex items-center gap-2">
                                        <span class="text-xs font-medium text-surface-600">Sampai:</span>
                                        <input type="date" id="etl-order-date-to-{{ $key }}" value="{{ now()->format('Y-m-d') }}"
                                            class="rounded-xl border-surface-200 bg-surface-50 text-surface-800 text-xs font-mono py-1.5 px-3 focus:border-brand-500 focus:ring focus:ring-brand-500/20">
                                    </div>
                                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-surface-700 select-none">
                                        <input type="checkbox" id="etl-keep-watermark-{{ $key }}" checked class="ui-checkbox">
                                        <span>Pertahankan Watermark Harian (tidak mengubah jam sync rutin)</span>
                                    </label>
                                </div>
                            @endif
                        </div>

                        {{-- Live Progress Box --}}
                        <div id="etl-progress-box-{{ $key }}" class="hidden rounded-xl border border-surface-200 bg-surface-50/70 p-4 space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div id="etl-status-icon-{{ $key }}" class="w-8 h-8 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center shrink-0">
                                        <span class="material-symbols-outlined text-[18px] spin">sync</span>
                                    </div>
                                    <div>
                                        <h4 id="etl-stage-title-{{ $key }}" class="text-sm font-bold text-surface-900">Memulai sinkronisasi...</h4>
                                        <p id="etl-stage-subtitle-{{ $key }}" class="text-xs text-surface-500">Menghubungkan ke SQL Server &amp; MySQL</p>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div id="etl-progress-pct-{{ $key }}" class="text-lg font-extrabold text-brand-600 font-mono">0%</div>
                                    <div class="text-[11px] text-surface-500 font-mono">
                                        <span id="etl-stat-rows-{{ $key }}">0</span> baris · <span id="etl-speed-{{ $key }}">0 rows/s</span>
                                    </div>
                                </div>
                            </div>
                            <div class="w-full bg-surface-200 rounded-full h-2 overflow-hidden">
                                <div id="etl-progress-bar-{{ $key }}" class="bg-brand-600 h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                            </div>
                        </div>

                        {{-- Collapsible Advanced Settings (Hidden by Default) --}}
                        @if ($pipe['is_sp'])
                            <details class="border-t border-surface-100 pt-3 group">
                                <summary class="inline-flex items-center gap-1.5 text-xs font-semibold text-surface-500 hover:text-surface-800 cursor-pointer select-none list-none">
                                    <span class="material-symbols-outlined text-[16px] transition-transform group-open:rotate-90">chevron_right</span>
                                    Pengaturan Lanjutan (Parameter &amp; Titik Watermark)
                                </summary>

                                <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-5 pt-2">
                                    {{-- SP Parameters --}}
                                    <div class="p-4 rounded-xl bg-surface-50 border border-surface-200/70 space-y-3">
                                        <h4 class="text-xs font-bold text-surface-800">Parameter Stored Procedure</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="text-[11px] font-semibold text-surface-600 block mb-1">Nama Proses (@SyncProcessName)</label>
                                                <input type="text" id="etl-proc-name-{{ $key }}" value="{{ $pipe['sync_process_name'] }}"
                                                    class="w-full rounded-lg border-surface-200 bg-white text-surface-800 text-xs font-mono py-2 px-3 focus:border-brand-500 focus:ring focus:ring-brand-500/20">
                                            </div>
                                            <div>
                                                <label class="text-[11px] font-semibold text-surface-600 block mb-1">Company ID (@CompanyID)</label>
                                                <input type="number" id="etl-company-id-{{ $key }}" value="{{ $pipe['company_id'] }}"
                                                    class="w-full rounded-lg border-surface-200 bg-white text-surface-800 text-xs font-mono py-2 px-3 focus:border-brand-500 focus:ring focus:ring-brand-500/20">
                                            </div>
                                        </div>
                                        <p class="text-[11px] text-surface-500">Parameter disimpan otomatis setiap kali menjalankan sinkronisasi.</p>
                                    </div>

                                    {{-- Watermark Control --}}
                                    <div id="sp-watermark-settings-{{ $key }}" class="p-4 rounded-xl bg-surface-50 border border-surface-200/70 space-y-3">
                                        <h4 class="text-xs font-bold text-surface-800">Atur Titik Awal Watermark SQL Server</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            <div class="sm:col-span-2">
                                                <label class="text-[11px] font-semibold text-surface-600 block mb-1">Tanggal &amp; Waktu Terakhir</label>
                                                <input type="datetime-local" step="1" id="wm-input-time-{{ $key }}"
                                                    value="{{ now()->startOfMonth()->format('Y-m-d\TH:i:s') }}"
                                                    oninput="this.dataset.userEdited = '1'"
                                                    class="w-full rounded-lg border-surface-200 bg-white text-surface-800 text-xs font-mono py-2 px-3 focus:border-brand-500 focus:ring focus:ring-brand-500/20">
                                            </div>
                                            <div>
                                                <label class="text-[11px] font-semibold text-surface-600 block mb-1">Overlap (Menit)</label>
                                                <input type="number" id="wm-input-overlap-{{ $key }}" value="5" min="0" max="1440"
                                                    oninput="this.dataset.userEdited = '1'"
                                                    class="w-full rounded-lg border-surface-200 bg-white text-surface-800 text-xs font-mono py-2 px-3 focus:border-brand-500 focus:ring focus:ring-brand-500/20">
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                                            <div class="flex flex-wrap gap-1">
                                                <button type="button" onclick="setQuickWatermarkDate('{{ $key }}', '{{ now()->subDay()->format('Y-m-d\T00:00:00') }}')"
                                                    class="px-2 py-1 rounded-md bg-white border border-surface-200 hover:bg-surface-100 text-[11px] font-medium text-surface-700">Kemarin</button>
                                                <button type="button" onclick="setQuickWatermarkDate('{{ $key }}', '{{ now()->startOfMonth()->format('Y-m-d\T00:00:00') }}')"
                                                    class="px-2 py-1 rounded-md bg-white border border-surface-200 hover:bg-surface-100 text-[11px] font-medium text-surface-700">Awal Bulan</button>
                                                <button type="button" onclick="setQuickWatermarkDate('{{ $key }}', '{{ now()->startOfYear()->format('Y-m-d\T00:00:00') }}')"
                                                    class="px-2 py-1 rounded-md bg-white border border-surface-200 hover:bg-surface-100 text-[11px] font-medium text-surface-700">Awal Tahun</button>
                                            </div>
                                            <button type="button" onclick="saveSqlServerWatermark('{{ $key }}')"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-800 text-white text-xs font-semibold hover:bg-surface-900 transition-all">
                                                <span class="material-symbols-outlined text-[14px]">save</span> Simpan Watermark
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        @endif
                    </div>

                    {{-- Clean Execution History --}}
                    <div class="bg-white rounded-2xl border border-surface-200/70 p-6 shadow-card">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-bold text-surface-900">Riwayat Sinkronisasi</h3>
                            <span id="logs-source-hint-{{ $key }}" class="text-[11px] text-surface-400"></span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-surface-200 text-surface-500 uppercase text-[10px] font-bold tracking-wider bg-surface-50">
                                        <th class="py-2.5 px-3">Waktu Mulai</th>
                                        <th class="py-2.5 px-3">Selesai</th>
                                        <th class="py-2.5 px-3">Rentang / Info</th>
                                        <th class="py-2.5 px-3 text-right">Baris</th>
                                        <th class="py-2.5 px-3">Status</th>
                                        <th class="py-2.5 px-3">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody id="sp-exec-logs-tbody-{{ $key }}" class="divide-y divide-surface-100 font-mono">
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-surface-400 font-sans">Memuat riwayat...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
@endsection

@section('scripts')
    <script>
        let activePipelineKey = @json(array_key_first($pipelines) ?? null);
        let pipelinePollIntervals = {};

        document.addEventListener('DOMContentLoaded', () => {
            if (activePipelineKey) {
                refreshPipelineState(activePipelineKey);
            }
        });

        function selectPipeline(key) {
            activePipelineKey = key;
            document.querySelectorAll('.pipeline-panel').forEach(el => el.classList.add('hidden'));
            const panel = document.getElementById(`pipe-panel-${key}`);
            if (panel) panel.classList.remove('hidden');

            document.querySelectorAll('.pipeline-tab-btn').forEach(btn => {
                btn.className = 'pipeline-tab-btn inline-flex items-center gap-2.5 px-4 py-2.5 rounded-xl border text-left transition-all duration-200 bg-white text-surface-700 border-surface-200 hover:bg-surface-50';
            });
            const activeBtn = document.getElementById(`pipe-tab-${key}`);
            if (activeBtn) {
                activeBtn.className = 'pipeline-tab-btn inline-flex items-center gap-2.5 px-4 py-2.5 rounded-xl border text-left transition-all duration-200 bg-brand-600 text-white border-brand-600 shadow-sm';
            }

            refreshPipelineState(key);
        }

        function toggleCustomDateInput(key) {
            const mode = document.querySelector(`input[name="run_mode_${key}"]:checked`)?.value || 'auto_watermark';
            const wrap = document.getElementById(`etl-custom-date-wrap-${key}`);
            if (wrap) {
                if (mode === 'custom_date') {
                    wrap.classList.remove('hidden');
                    wrap.classList.add('inline-flex');
                } else {
                    wrap.classList.add('hidden');
                    wrap.classList.remove('inline-flex');
                }
            }
            const orderWrap = document.getElementById(`etl-order-date-wrap-${key}`);
            if (orderWrap) {
                if (mode === 'order_date_range') {
                    orderWrap.classList.remove('hidden');
                    orderWrap.classList.add('flex');
                } else {
                    orderWrap.classList.add('hidden');
                    orderWrap.classList.remove('flex');
                }
            }
        }

        function setQuickWatermarkDate(key, isoLocal) {
            const input = document.getElementById(`wm-input-time-${key}`);
            if (input) {
                input.value = isoLocal;
                input.dataset.userEdited = '1';
            }
        }

        function renderStatusBadge(statusRaw) {
            const status = String(statusRaw || '').toUpperCase();
            if (status === 'SUCCESS') {
                return `<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">SUCCESS</span>`;
            }
            if (status === 'RUNNING' || status === 'PENDING' || status === 'IN_PROGRESS') {
                return `<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">${status}</span>`;
            }
            if (status === 'FAILED' || status === 'ABORTED' || status === 'STOPPED') {
                return `<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-red-100 text-red-800 border border-red-200">${status}</span>`;
            }
            return `<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-surface-100 text-surface-700">${status || '-'}</span>`;
        }

        function escapeHtml(text) {
            return String(text ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        async function refreshPipelineState(key) {
            try {
                const res = await fetch(`/api/etl-pipeline/${key}/state`);
                const data = await res.json();
                if (!data.success) return;

                // Update MySQL row count
                const mysqlRowsEl = document.getElementById(`mysql-rows-${key}`);
                if (mysqlRowsEl && data.mysql_rows !== undefined) {
                    mysqlRowsEl.textContent = Number(data.mysql_rows).toLocaleString();
                }

                // Update Status & Last Sync card
                const wm = data.watermark_control;
                const activeJob = data.active_job;
                const latestLog = data.latest_log;
                const lockBadge = document.getElementById(`sp-lock-badge-${key}`);
                const lastSyncEl = document.getElementById(`wm-last-sync-${key}`);

                const isSpRunning = wm && String(wm.IsRunning) === '1';
                const isRunning = Boolean(activeJob) || isSpRunning;

                if (lockBadge) {
                    if (isRunning) {
                        lockBadge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300';
                        lockBadge.innerHTML = '<span class="material-symbols-outlined text-[13px] spin">sync</span> Sedang Berjalan';
                    } else {
                        lockBadge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200';
                        lockBadge.innerHTML = '<span class="material-symbols-outlined text-[13px]">check_circle</span> Siap';
                    }
                }

                if (lastSyncEl) {
                    const actualLastSync = data.last_completed_at
                        || (latestLog && latestLog.completed_at ? latestLog.completed_at : null)
                        || (wm && wm.LastSuccessSyncTime ? wm.LastSuccessSyncTime : 'Belum pernah sync');
                    lastSyncEl.textContent = actualLastSync;
                }

                const subWmEl = document.getElementById(`wm-sub-watermark-${key}`);
                if (subWmEl) {
                    if (wm && wm.LastSuccessSyncTime) {
                        subWmEl.textContent = `Watermark DB: ${wm.LastSuccessSyncTime}`;
                        subWmEl.classList.remove('hidden');
                    } else {
                        subWmEl.classList.add('hidden');
                    }
                }

                // Populate advanced watermark inputs if SP has SyncWatermarkControl
                const wmBox = document.getElementById(`sp-watermark-settings-${key}`);
                if (wmBox) {
                    if (wm) {
                        wmBox.classList.remove('hidden');
                        const wmInput = document.getElementById(`wm-input-time-${key}`);
                        if (wmInput && wm.LastSuccessSyncTime && !wmInput.dataset.userEdited) {
                            wmInput.value = wm.LastSuccessSyncTime.replace(' ', 'T');
                        }
                        const overlapInput = document.getElementById(`wm-input-overlap-${key}`);
                        if (overlapInput && wm.OverlapMinutes !== undefined && !overlapInput.dataset.userEdited) {
                            overlapInput.value = wm.OverlapMinutes;
                        }
                    } else if (!data.has_sp_watermark_table) {
                        wmBox.classList.add('hidden');
                    }
                }

                // Render execution history (prefer SQL Server SyncExecutionLog for 2-phase SPs, fallback to MySQL sync_logs)
                const tbody = document.getElementById(`sp-exec-logs-tbody-${key}`);
                const hintEl = document.getElementById(`logs-source-hint-${key}`);
                if (tbody) {
                    const spLogs = data.execution_logs || [];
                    const localLogs = data.local_logs || [];

                    if (spLogs.length > 0) {
                        if (hintEl) hintEl.textContent = '10 eksekusi terakhir';
                        tbody.innerHTML = spLogs.map(row => `
                            <tr class="hover:bg-surface-50/80 transition-colors">
                                <td class="py-2.5 px-3 text-surface-700">${escapeHtml(row.StartTime || '-')}</td>
                                <td class="py-2.5 px-3 text-surface-600">${escapeHtml(row.EndTime || '-')}</td>
                                <td class="py-2.5 px-3 text-surface-700">${escapeHtml(row.WatermarkFrom || '-')} → ${escapeHtml(row.WatermarkTo || '-')}</td>
                                <td class="py-2.5 px-3 text-right font-bold text-surface-900">${row.ExtractedRowsCount !== null ? Number(row.ExtractedRowsCount).toLocaleString() : '-'}</td>
                                <td class="py-2.5 px-3">${renderStatusBadge(row.Status)}</td>
                                <td class="py-2.5 px-3 text-red-600 font-sans max-w-xs truncate" title="${escapeHtml(row.ErrorMessage || '')}">${escapeHtml(row.ErrorMessage || '-')}</td>
                            </tr>
                        `).join('');
                    } else if (localLogs.length > 0) {
                        if (hintEl) hintEl.textContent = '10 eksekusi terakhir';
                        tbody.innerHTML = localLogs.map(row => {
                            const rows = row.records_inserted || row.records_updated || row.records_fetched || 0;
                            const duration = row.duration_seconds ? `${row.duration_seconds}s` : '-';
                            return `
                                <tr class="hover:bg-surface-50/80 transition-colors">
                                    <td class="py-2.5 px-3 text-surface-700">${escapeHtml(row.started_at || '-')}</td>
                                    <td class="py-2.5 px-3 text-surface-600">${escapeHtml(row.completed_at || '-')}</td>
                                    <td class="py-2.5 px-3 text-surface-600 font-sans">${escapeHtml(row.current_stage || '-')} (${duration})</td>
                                    <td class="py-2.5 px-3 text-right font-bold text-surface-900">${Number(rows).toLocaleString()}</td>
                                    <td class="py-2.5 px-3">${renderStatusBadge(row.status)}</td>
                                    <td class="py-2.5 px-3 text-red-600 font-sans max-w-xs truncate" title="${escapeHtml(row.error_message || '')}">${escapeHtml(row.error_message || '-')}</td>
                                </tr>
                            `;
                        }).join('');
                    } else {
                        if (hintEl) hintEl.textContent = '';
                        tbody.innerHTML = `<tr><td colspan="6" class="py-6 text-center text-surface-400 font-sans">Belum ada riwayat sinkronisasi.</td></tr>`;
                    }
                }

                // Attach polling if a job is currently running
                if (activeJob && !pipelinePollIntervals[key]) {
                    startPipelinePolling(key);
                } else if (!activeJob && pipelinePollIntervals[key]) {
                    clearInterval(pipelinePollIntervals[key]);
                    delete pipelinePollIntervals[key];
                    resetRunButton(key);
                }
            } catch (e) {
                console.error('Failed to refresh ETL state:', e);
            }
        }

        function resetRunButton(key) {
            const btn = document.getElementById(`btn-run-etl-${key}`);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">play_arrow</span> Mulai Sinkronisasi';
            }
        }

        async function runEtlPipeline(key) {
            const runMode = document.querySelector(`input[name="run_mode_${key}"]:checked`)?.value || 'auto_watermark';
            const syncProcessName = document.getElementById(`etl-proc-name-${key}`)?.value || '';
            const companyId = document.getElementById(`etl-company-id-${key}`)?.value || '';
            const customStartDate = document.getElementById(`etl-custom-date-${key}`)?.value || '';
            const orderDateFrom = document.getElementById(`etl-order-date-from-${key}`)?.value || '';
            const orderDateTo = document.getElementById(`etl-order-date-to-${key}`)?.value || '';
            const keepWatermark = document.getElementById(`etl-keep-watermark-${key}`)?.checked ?? true;

            const btn = document.getElementById(`btn-run-etl-${key}`);
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="material-symbols-outlined text-[18px] spin">sync</span> Memproses...';
            }

            const progressBox = document.getElementById(`etl-progress-box-${key}`);
            if (progressBox) progressBox.classList.remove('hidden');

            const iconWrap = document.getElementById(`etl-status-icon-${key}`);
            if (iconWrap) {
                iconWrap.className = 'w-8 h-8 rounded-lg bg-brand-50 text-brand-600 flex items-center justify-center shrink-0';
                iconWrap.innerHTML = '<span class="material-symbols-outlined text-[18px] spin">sync</span>';
            }

            document.getElementById(`etl-stage-title-${key}`).textContent = 'Memulai sinkronisasi...';
            document.getElementById(`etl-stage-subtitle-${key}`).textContent = 'Menyiapkan data dari SQL Server';
            document.getElementById(`etl-progress-bar-${key}`).style.width = '8%';
            document.getElementById(`etl-progress-pct-${key}`).textContent = '8%';

            try {
                const payload = {
                    run_mode: runMode,
                    custom_start_date: customStartDate,
                    order_date_from: orderDateFrom,
                    order_date_to: orderDateTo,
                    keep_watermark: keepWatermark
                };
                if (syncProcessName) payload.sync_process_name = syncProcessName;
                if (companyId) payload.company_id = companyId;

                const res = await fetch(`/api/etl-pipeline/${key}/run`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                if (data.success || data.status === 'already_running') {
                    showToast(data.message || 'Sinkronisasi sedang berjalan.', 'info');
                    startPipelinePolling(key);
                } else {
                    showToast(data.message || 'Gagal memulai sinkronisasi.', 'error');
                    resetRunButton(key);
                }
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
                resetRunButton(key);
            }
        }

        function startPipelinePolling(key) {
            if (pipelinePollIntervals[key]) {
                clearInterval(pipelinePollIntervals[key]);
            }

            const progressBox = document.getElementById(`etl-progress-box-${key}`);
            if (progressBox) progressBox.classList.remove('hidden');

            pipelinePollIntervals[key] = setInterval(async () => {
                try {
                    const res = await fetch(`/api/etl-pipeline/${key}/state`);
                    const data = await res.json();
                    if (!data.success) return;

                    await refreshPipelineState(key);

                    const activeJob = data.active_job;
                    const latestLog = data.latest_log;
                    const iconWrap = document.getElementById(`etl-status-icon-${key}`);

                    if (activeJob) {
                        const pct = Math.max(5, activeJob.progress || 5);
                        const rows = activeJob.processed_rows || (latestLog ? latestLog.records_updated : 0) || 0;
                        const speed = latestLog ? (latestLog.rows_per_second || 0) : 0;

                        document.getElementById(`etl-progress-bar-${key}`).style.width = `${pct}%`;
                        document.getElementById(`etl-progress-pct-${key}`).textContent = `${pct}%`;
                        document.getElementById(`etl-stat-rows-${key}`).textContent = Number(rows).toLocaleString();
                        document.getElementById(`etl-speed-${key}`).textContent = `${Number(speed).toLocaleString()} rows/s`;

                        if (rows > 0) {
                            document.getElementById(`etl-stage-title-${key}`).textContent = 'Memuat data ke MySQL...';
                            document.getElementById(`etl-stage-subtitle-${key}`).textContent = `${Number(rows).toLocaleString()} baris telah dimuat ke tabel ${data.mysql_table}`;
                        } else {
                            document.getElementById(`etl-stage-title-${key}`).textContent = 'Mengambil data dari SQL Server...';
                            document.getElementById(`etl-stage-subtitle-${key}`).textContent = 'Menyiapkan perubahan data terbaru';
                        }
                    } else {
                        clearInterval(pipelinePollIntervals[key]);
                        delete pipelinePollIntervals[key];

                        resetRunButton(key);

                        if (latestLog && latestLog.status === 'success') {
                            const finalRows = latestLog.records_inserted || latestLog.records_updated || 0;
                            document.getElementById(`etl-progress-bar-${key}`).style.width = '100%';
                            document.getElementById(`etl-progress-pct-${key}`).textContent = '100%';
                            document.getElementById(`etl-stat-rows-${key}`).textContent = Number(finalRows).toLocaleString();
                            document.getElementById(`etl-stage-title-${key}`).textContent = 'Sinkronisasi Selesai';
                            document.getElementById(`etl-stage-subtitle-${key}`).textContent = `${Number(finalRows).toLocaleString()} baris tersinkronisasi dalam ${latestLog.duration_seconds || 0}s.`;
                            if (iconWrap) {
                                iconWrap.className = 'w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0';
                                iconWrap.innerHTML = '<span class="material-symbols-outlined text-[18px]">check_circle</span>';
                            }
                            showToast('Sinkronisasi selesai dengan sukses!', 'success');
                        } else if (latestLog && (latestLog.status === 'failed' || latestLog.status === 'stopped')) {
                            document.getElementById(`etl-stage-title-${key}`).textContent = 'Sinkronisasi Berhenti / Gagal';
                            document.getElementById(`etl-stage-subtitle-${key}`).textContent = latestLog.error_message || 'Proses dihentikan.';
                            if (iconWrap) {
                                iconWrap.className = 'w-8 h-8 rounded-lg bg-red-100 text-red-700 flex items-center justify-center shrink-0';
                                iconWrap.innerHTML = '<span class="material-symbols-outlined text-[18px]">error</span>';
                            }
                        }
                    }
                } catch (e) {
                    console.error('Polling error:', e);
                }
            }, 2000);
        }

        async function saveSqlServerWatermark(key) {
            const syncProcessName = document.getElementById(`etl-proc-name-${key}`)?.value || 'SO_TO_MYSQL_SYNC';
            const companyId = document.getElementById(`etl-company-id-${key}`)?.value || 2;
            const lastTime = document.getElementById(`wm-input-time-${key}`)?.value || '';
            const overlap = document.getElementById(`wm-input-overlap-${key}`)?.value || 5;

            if (!lastTime) {
                showToast('Pilih tanggal & waktu Watermark terlebih dahulu.', 'warning');
                return;
            }

            try {
                const res = await fetch(`/api/etl-pipeline/${key}/update-watermark`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        sync_process_name: syncProcessName,
                        company_id: companyId,
                        last_success_sync_time: lastTime,
                        overlap_minutes: overlap
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    const wmInput = document.getElementById(`wm-input-time-${key}`);
                    const overlapInput = document.getElementById(`wm-input-overlap-${key}`);
                    if (wmInput) delete wmInput.dataset.userEdited;
                    if (overlapInput) delete overlapInput.dataset.userEdited;
                    refreshPipelineState(key);
                } else {
                    showToast(data.message || 'Gagal menyimpan watermark.', 'error');
                }
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
            }
        }

        async function unlockPipeline(key) {
            try {
                const res = await fetch(`/api/etl-pipeline/${key}/unlock`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    refreshPipelineState(key);
                } else {
                    showToast(data.message || 'Gagal mereset status.', 'error');
                }
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
            }
        }
    </script>
@endsection
