@extends('layouts.app')
@section('title', 'Security & Access Monitor — PL Sync Data Warehouse')
@section('page-title', 'Security & Access Monitor')

@section('content')
    <div class="space-y-6">
        {{-- Top Header --}}
        <div class="flex flex-wrap justify-between items-center gap-4 animate-fade-up">
            <div>
                <h2 class="text-h1 text-surface-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand-600 text-[26px]">shield_lock</span>
                    Security &amp; Access Monitor
                </h2>
                <p class="text-body-md text-surface-500 mt-1">
                    Pantau user yang sedang login, sesi anonymous/guest, serta penggunaan Endpoint API secara real-time (Session Lifetime: {{ $sessionLifetime }} menit).
                </p>
            </div>
            <div class="flex items-center gap-2">
                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white border border-surface-200 text-body-sm text-surface-700 cursor-pointer shadow-sm">
                    <input type="checkbox" id="auto-refresh-toggle" checked onchange="toggleAutoRefresh(this.checked)" class="ui-checkbox">
                    <span>Auto-Refresh (15s)</span>
                </label>
                <button type="button" id="btn-refresh-monitor" onclick="refreshMonitorData()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand-600 text-white hover:bg-brand-700 transition-all text-label-md shadow-sm">
                    <span class="material-symbols-outlined text-[18px]" id="refresh-icon">refresh</span>
                    <span>Refresh</span>
                </button>
            </div>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 animate-fade-up">
            {{-- Active Logged-In Sessions --}}
            <div class="bg-white rounded-2xl border border-surface-200/60 p-5 shadow-card">
                <div class="flex items-center justify-between">
                    <span class="text-label-md text-surface-500 font-semibold">User Sedang Login</span>
                    <div class="p-2 rounded-xl bg-emerald-50 text-emerald-600">
                        <span class="material-symbols-outlined text-[20px]">person_check</span>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-surface-900" id="stat-active-sessions">{{ $stats['active_user_sessions'] }}</span>
                    <span class="text-xs text-surface-500">sesi (<span id="stat-unique-users">{{ $stats['unique_online_users'] }}</span> akun unik)</span>
                </div>
            </div>

            {{-- Anonymous / Guest Sessions --}}
            <div class="bg-white rounded-2xl border border-surface-200/60 p-5 shadow-card">
                <div class="flex items-center justify-between">
                    <span class="text-label-md text-surface-500 font-semibold">Sesi Anonymous / Guest</span>
                    <div class="p-2 rounded-xl bg-amber-50 text-amber-600">
                        <span class="material-symbols-outlined text-[20px]">person_alert</span>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <div>
                        <span class="text-2xl font-bold {{ $stats['anonymous_sessions'] > 0 ? 'text-amber-600' : 'text-surface-900' }}" id="stat-anon-sessions">{{ $stats['anonymous_sessions'] }}</span>
                        <span class="text-xs text-surface-500 ml-1">sesi tanpa login</span>
                    </div>
                    <button type="button" onclick="purgeGuestSessions()" class="text-xs font-semibold text-amber-700 hover:text-amber-900 underline">
                        Bersihkan
                    </button>
                </div>
            </div>

            {{-- Authorized API Calls (24h) --}}
            <div class="bg-white rounded-2xl border border-surface-200/60 p-5 shadow-card">
                <div class="flex items-center justify-between">
                    <span class="text-label-md text-surface-500 font-semibold">Akses API (24 Jam)</span>
                    <div class="p-2 rounded-xl bg-brand-50 text-brand-600">
                        <span class="material-symbols-outlined text-[20px]">api</span>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-surface-900" id="stat-api-24h">{{ $stats['api_requests_24h'] }}</span>
                    <span class="text-xs text-surface-500">request terotorisasi</span>
                </div>
            </div>

            {{-- Suspicious / Unauthorized Attempts (24h) --}}
            <div class="bg-white rounded-2xl border border-surface-200/60 p-5 shadow-card">
                <div class="flex items-center justify-between">
                    <span class="text-label-md text-surface-500 font-semibold">Akses Ditolak / Anonim (24J)</span>
                    <div class="p-2 rounded-xl bg-red-50 text-red-600">
                        <span class="material-symbols-outlined text-[20px]">gpp_bad</span>
                    </div>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl font-bold {{ $stats['suspicious_24h'] > 0 ? 'text-red-600' : 'text-surface-900' }}" id="stat-suspicious-24h">{{ $stats['suspicious_24h'] }}</span>
                    <span class="text-xs text-surface-500">percobaan gagal / 401 / 403</span>
                </div>
            </div>
        </div>

        {{-- Section 1: Active Logged-in Users --}}
        <div class="bg-white rounded-2xl border border-surface-200/60 overflow-hidden shadow-card animate-fade-up">
            <div class="px-5 py-4 border-b border-surface-100 flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-600 text-[20px]">verified_user</span>
                    <h3 class="text-h3 text-surface-800">User yang Sedang Login (Active Sessions)</h3>
                </div>
                <span class="text-xs text-surface-400">Menampilkan semua perangkat/browser yang sedang login ke aplikasi</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-surface-50 border-b border-surface-100">
                        <tr>
                            <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">User</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Role</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">IP Address</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Browser / Device</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Aktivitas Terakhir</th>
                            <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="authenticated-sessions-tbody" class="divide-y divide-surface-100 text-body-sm">
                        {{-- Populated by JS --}}
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Section 2: Anonymous / Guest Sessions in Sessions Table --}}
        <div class="bg-white rounded-2xl border border-surface-200/60 overflow-hidden shadow-card animate-fade-up">
            <div class="px-5 py-4 border-b border-surface-100 flex flex-wrap justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-500 text-[20px]">visibility</span>
                    <div>
                        <h3 class="text-h3 text-surface-800">Sesi Anonymous / Guest (Belum Login)</h3>
                        <p class="text-xs text-surface-400">Pengunjung atau bot yang membuka halaman aplikasi namun belum terautentikasi</p>
                    </div>
                </div>
                <button type="button" onclick="purgeGuestSessions()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 text-xs font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
                    Bersihkan Semua Sesi Guest
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-surface-50 border-b border-surface-100">
                        <tr>
                            <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Session ID</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">IP Address</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Client / User-Agent</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Aktivitas Terakhir</th>
                            <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="anonymous-sessions-tbody" class="divide-y divide-surface-100 text-body-sm">
                        {{-- Populated by JS --}}
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Section 3: API Usage & Security Access Log --}}
        <div class="bg-white rounded-2xl border border-surface-200/60 overflow-hidden shadow-card animate-fade-up">
            <div class="p-5 border-b border-surface-100 space-y-3">
                <div class="flex flex-wrap justify-between items-center gap-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-brand-600 text-[20px]">policy</span>
                        <div>
                            <h3 class="text-h3 text-surface-800">Log Penggunaan API &amp; Aktivitas Login</h3>
                            <p class="text-xs text-surface-400">Mencatat setiap akses ke Endpoint API (<code class="font-mono">/data/{slug}</code>), pengguna Power BI/Excel, serta percobaan masuk tanpa izin (Anonymous 401/403)</p>
                        </div>
                    </div>
                    <button type="button" onclick="clearSecurityLogs()"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-surface-100 text-surface-600 hover:bg-red-50 hover:text-red-600 text-xs font-medium transition-colors">
                        <span class="material-symbols-outlined text-[15px]">delete</span>
                        Clear Log
                    </button>
                </div>

                {{-- Filter Bar --}}
                <div class="flex flex-wrap justify-between items-center gap-3 pt-1">
                    <div class="inline-flex items-center bg-surface-100 p-1 rounded-xl border border-surface-200 gap-1">
                        <button type="button" id="log-filter-all" onclick="setLogFilter('all')"
                            class="log-filter-pill px-3 py-1 rounded-lg text-xs font-semibold bg-white text-surface-900 shadow-sm transition-all">
                            Semua Aktivitas
                        </button>
                        <button type="button" id="log-filter-api" onclick="setLogFilter('api')"
                            class="log-filter-pill px-3 py-1 rounded-lg text-xs font-medium text-surface-600 hover:text-surface-900 transition-all inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px] text-brand-600">api</span>
                            Akses API
                        </button>
                        <button type="button" id="log-filter-suspicious" onclick="setLogFilter('suspicious')"
                            class="log-filter-pill px-3 py-1 rounded-lg text-xs font-medium text-surface-600 hover:text-surface-900 transition-all inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px] text-red-500">warning</span>
                            Anonymous / Ditolak (401/403)
                        </button>
                        <button type="button" id="log-filter-login" onclick="setLogFilter('login')"
                            class="log-filter-pill px-3 py-1 rounded-lg text-xs font-medium text-surface-600 hover:text-surface-900 transition-all inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px] text-emerald-600">login</span>
                            Login &amp; Sesi
                        </button>
                    </div>

                    <div class="relative w-72">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-[18px]">search</span>
                        <input type="text" id="log-search-input" oninput="debounceRefreshLogs()" placeholder="Cari IP, user, endpoint, Power BI..."
                            class="ui-input pl-9 pr-3 py-1.5 text-sm" style="background:#fff;">
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-surface-50 border-b border-surface-100">
                        <tr>
                            <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Waktu</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Tipe Event</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">User / Identitas</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Endpoint</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">IP Address</th>
                            <th class="px-4 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Aplikasi / Client</th>
                            <th class="px-5 py-3 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody id="access-logs-tbody" class="divide-y divide-surface-100 text-body-sm">
                        {{-- Populated by JS --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        let authenticatedSessions = @json($authenticatedSessions);
        let anonymousSessions = @json($anonymousSessions);
        let accessLogs = @json($accessLogs);
        let currentLogFilter = 'all';
        let autoRefreshTimer = null;
        let searchDebounceTimer = null;

        document.addEventListener('DOMContentLoaded', () => {
            renderAuthenticatedSessions();
            renderAnonymousSessions();
            renderAccessLogs();
            toggleAutoRefresh(true);
        });

        function toggleAutoRefresh(enabled) {
            if (autoRefreshTimer) {
                clearInterval(autoRefreshTimer);
                autoRefreshTimer = null;
            }
            if (enabled) {
                autoRefreshTimer = setInterval(() => refreshMonitorData(true), 15000);
            }
        }

        function setLogFilter(filter) {
            currentLogFilter = filter;
            ['all', 'api', 'suspicious', 'login'].forEach(f => {
                const btn = document.getElementById('log-filter-' + f);
                if (!btn) return;
                if (f === filter) {
                    btn.classList.add('bg-white', 'text-surface-900', 'shadow-sm', 'font-semibold');
                    btn.classList.remove('text-surface-600');
                } else {
                    btn.classList.remove('bg-white', 'text-surface-900', 'shadow-sm', 'font-semibold');
                    btn.classList.add('text-surface-600');
                }
            });
            refreshMonitorData(true);
        }

        function debounceRefreshLogs() {
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => refreshMonitorData(true), 300);
        }

        async function refreshMonitorData(silent = false) {
            const icon = document.getElementById('refresh-icon');
            if (icon) icon.classList.add('spin');

            try {
                const search = document.getElementById('log-search-input')?.value || '';
                const url = `/api/security-monitor/data?filter=${encodeURIComponent(currentLogFilter)}&search=${encodeURIComponent(search)}&_t=${Date.now()}`;
                const res = await fetch(url, {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                if (data.success) {
                    authenticatedSessions = data.authenticatedSessions || [];
                    anonymousSessions = data.anonymousSessions || [];
                    accessLogs = data.accessLogs || [];

                    if (data.stats) {
                        document.getElementById('stat-active-sessions').textContent = data.stats.active_user_sessions;
                        document.getElementById('stat-unique-users').textContent = data.stats.unique_online_users;
                        document.getElementById('stat-anon-sessions').textContent = data.stats.anonymous_sessions;
                        document.getElementById('stat-api-24h').textContent = data.stats.api_requests_24h;
                        document.getElementById('stat-suspicious-24h').textContent = data.stats.suspicious_24h;
                    }

                    renderAuthenticatedSessions();
                    renderAnonymousSessions();
                    renderAccessLogs();

                    if (!silent) showToast('Data monitoring berhasil diperbarui.', 'success');
                }
            } catch (e) {
                if (!silent) showToast('Gagal memuat data monitoring.', 'error');
            } finally {
                if (icon) icon.classList.remove('spin');
            }
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderAuthenticatedSessions() {
            const tbody = document.getElementById('authenticated-sessions-tbody');
            if (!authenticatedSessions || authenticatedSessions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-5 py-8 text-center text-surface-400">
                            Tidak ada sesi user aktif saat ini.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = authenticatedSessions.map(s => {
                const rolesHtml = (s.roles || []).map(r => `
                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-semibold ${r === 'Super Administrator' || r === 'Administrator' ? 'bg-red-50 text-red-700' : 'bg-brand-50 text-brand-700'}">
                        ${escapeHtml(r)}
                    </span>
                `).join(' ');

                const statusBadge = s.is_online
                    ? `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600"><span class="status-dot status-dot-online"></span> Online</span>`
                    : `<span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-600"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Idle</span>`;

                const actionHtml = s.is_current
                    ? `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">Sesi Anda</span>`
                    : `<button type="button" onclick="revokeSession('${escapeHtml(s.session_id)}', '${escapeHtml(s.user_name)}')"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 text-xs font-semibold transition-colors">
                        <span class="material-symbols-outlined text-[15px]">logout</span> Force Logout
                       </button>`;

                return `
                    <tr class="hover:bg-surface-50/60 transition-colors">
                        <td class="px-5 py-3.5">
                            <div class="font-semibold text-surface-900">${escapeHtml(s.user_name)}</div>
                            <div class="text-xs text-surface-500">${escapeHtml(s.user_email)}</div>
                        </td>
                        <td class="px-4 py-3.5">${rolesHtml || '<span class="text-xs text-surface-400">-</span>'}</td>
                        <td class="px-4 py-3.5">${statusBadge}</td>
                        <td class="px-4 py-3.5 font-mono text-xs text-surface-800">${escapeHtml(s.ip_address)}</td>
                        <td class="px-4 py-3.5" title="${escapeHtml(s.user_agent)}">
                            <div class="font-medium text-surface-800 text-xs">${escapeHtml(s.client_type)}</div>
                            <div class="text-[11px] text-surface-400">${escapeHtml(s.platform)} &bull; ID: ${escapeHtml(s.short_id)}</div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-xs font-medium text-surface-800">${escapeHtml(s.last_activity)}</div>
                            <div class="text-[11px] text-surface-400">${escapeHtml(s.last_activity_at)}</div>
                        </td>
                        <td class="px-5 py-3.5 text-right">${actionHtml}</td>
                    </tr>
                `;
            }).join('');
        }

        function renderAnonymousSessions() {
            const tbody = document.getElementById('anonymous-sessions-tbody');
            if (!anonymousSessions || anonymousSessions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="px-5 py-6 text-center text-surface-400">
                            <span class="material-symbols-outlined text-emerald-500 text-[22px] align-middle mr-1">check_circle</span>
                            Aman — Tidak ada sesi anonymous/guest yang aktif.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = anonymousSessions.map(s => `
                <tr class="bg-amber-50/20 hover:bg-amber-50/50 transition-colors">
                    <td class="px-5 py-3 font-mono text-xs text-amber-900 font-semibold">${escapeHtml(s.short_id)}</td>
                    <td class="px-4 py-3 font-mono text-xs text-surface-900 font-semibold">${escapeHtml(s.ip_address)}</td>
                    <td class="px-4 py-3" title="${escapeHtml(s.user_agent)}">
                        <div class="text-xs font-medium text-surface-800">${escapeHtml(s.client_type)} (${escapeHtml(s.platform)})</div>
                        <div class="text-[11px] text-surface-400 truncate max-w-md">${escapeHtml(s.user_agent)}</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-xs font-medium text-surface-800">${escapeHtml(s.last_activity)}</div>
                        <div class="text-[11px] text-surface-400">${escapeHtml(s.last_activity_at)}</div>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <button type="button" onclick="revokeSession('${escapeHtml(s.session_id)}', 'Guest (${escapeHtml(s.ip_address)})')"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 text-xs font-semibold transition-colors">
                            <span class="material-symbols-outlined text-[14px]">block</span> Hapus Sesi
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function renderAccessLogs() {
            const tbody = document.getElementById('access-logs-tbody');
            if (!accessLogs || accessLogs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="px-5 py-8 text-center text-surface-400">
                            Belum ada log aktivitas yang tercatat untuk filter ini.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = accessLogs.map(log => {
                let eventBadge = '';
                if (log.event_type === 'api_access') {
                    eventBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-brand-50 text-brand-700 text-xs font-semibold"><span class="material-symbols-outlined text-[13px]">api</span> API Access</span>`;
                } else if (log.event_type === 'api_unauthorized') {
                    eventBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-red-50 text-red-700 text-xs font-semibold"><span class="material-symbols-outlined text-[13px]">gpp_bad</span> API Blocked</span>`;
                } else if (log.event_type === 'login_success') {
                    eventBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold"><span class="material-symbols-outlined text-[13px]">login</span> Login OK</span>`;
                } else if (log.event_type === 'login_failed') {
                    eventBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-red-50 text-red-700 text-xs font-semibold"><span class="material-symbols-outlined text-[13px]">warning</span> Login Failed</span>`;
                } else {
                    eventBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-50 text-amber-800 text-xs font-semibold">${escapeHtml(log.event_type)}</span>`;
                }

                const statusBadge = log.status_code >= 400
                    ? `<span class="px-2 py-0.5 rounded font-mono text-xs font-bold bg-red-100 text-red-700">${log.status_code}</span>`
                    : `<span class="px-2 py-0.5 rounded font-mono text-xs font-bold bg-emerald-100 text-emerald-700">${log.status_code}</span>`;

                const identityHtml = log.is_anonymous
                    ? `<div class="inline-flex items-center gap-1 text-red-600 font-semibold text-xs">
                        <span class="material-symbols-outlined text-[14px]">person_off</span>
                        <span>${escapeHtml(log.user_identifier)}</span>
                       </div>
                       <div class="text-[10px] text-red-500 uppercase font-bold tracking-wider">Anonymous / Unverified</div>`
                    : `<div class="font-semibold text-surface-900 text-xs">${escapeHtml(log.user_name || log.user_identifier)}</div>
                       <div class="text-[11px] text-surface-500">${escapeHtml(log.user_identifier)}</div>`;

                const rowClass = log.is_anonymous || log.status_code >= 400
                    ? 'bg-red-50/20 hover:bg-red-50/40'
                    : 'hover:bg-surface-50/60';

                return `
                    <tr class="${rowClass} transition-colors">
                        <td class="px-5 py-3 whitespace-nowrap">
                            <div class="text-xs font-medium text-surface-800">${escapeHtml(log.time_ago)}</div>
                            <div class="text-[11px] text-surface-400">${escapeHtml(log.created_at)}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">${eventBadge}</td>
                        <td class="px-4 py-3">${identityHtml}</td>
                        <td class="px-4 py-3 font-mono text-xs text-surface-800">
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-surface-100 text-surface-600 mr-1">${escapeHtml(log.method)}</span>
                            ${escapeHtml(log.endpoint)}
                        </td>
                        <td class="px-4 py-3">${statusBadge}</td>
                        <td class="px-4 py-3 font-mono text-xs text-surface-800 font-medium">${escapeHtml(log.ip_address)}</td>
                        <td class="px-4 py-3" title="${escapeHtml(log.user_agent)}">
                            <span class="text-xs font-medium text-surface-800">${escapeHtml(log.client_type)}</span>
                        </td>
                        <td class="px-5 py-3 text-xs text-surface-600">${escapeHtml(log.details || '-')}</td>
                    </tr>
                `;
            }).join('');
        }

        async function revokeSession(sessionId, label) {
            if (!confirm(`Apakah Anda yakin ingin memutus/mengeluarkan sesi "${label}"?`)) return;

            try {
                const res = await fetch(`/api/security-monitor/sessions/${encodeURIComponent(sessionId)}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    showToast(data.message, 'success');
                    refreshMonitorData(true);
                } else {
                    showToast(data.message || 'Gagal menghentikan sesi.', 'error');
                }
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
            }
        }

        async function purgeGuestSessions() {
            try {
                const res = await fetch('/api/security-monitor/sessions/purge-guests', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    showToast(data.message, 'success');
                    refreshMonitorData(true);
                } else {
                    showToast(data.message || 'Gagal membersihkan sesi guest.', 'error');
                }
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
            }
        }

        async function clearSecurityLogs() {
            if (!confirm('Bersihkan seluruh riwayat log akses API & Login?')) return;

            try {
                const res = await fetch('/api/security-monitor/logs', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    showToast(data.message, 'success');
                    refreshMonitorData(true);
                } else {
                    showToast(data.message || 'Gagal membersihkan log.', 'error');
                }
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
            }
        }
    </script>
@endsection

