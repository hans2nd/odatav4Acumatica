<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PL Sync — Data Warehouse')</title>

    <!-- Google Fonts & Material Symbols (Loaded via CDN to prevent path issues on VPS) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL,GRAD,opsz@100..700,0..1,0,24&display=swap"
        rel="stylesheet" />

    <!-- Load compiled CSS and JS containing fonts and Tailwind -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.13.3/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Sidebar scrollbar */
        #sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
        }

        #sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* ── Sidebar Mini/Expand ────────────────────────── */
        #sidebar {
            width: 260px;
            transition: width 0.3s ease-in-out;
            overflow-x: hidden;
        }
        #sidebar.sidebar-mini {
            width: 64px;
        }
        #main-content {
            transition: margin-left 0.3s ease-in-out;
        }

        /* Hide text elements when mini */
        #sidebar.sidebar-mini .sidebar-text,
        #sidebar.sidebar-mini .sidebar-submenu,
        #sidebar.sidebar-mini .sidebar-chevron,
        #sidebar.sidebar-mini .sidebar-user-info {
            display: none;
        }

        /* Center icons when mini */
        #sidebar.sidebar-mini .sidebar-nav-btn,
        #sidebar.sidebar-mini .sidebar-nav-link {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }
        #sidebar.sidebar-mini .sidebar-icon-wrap {
            margin: 0;
            gap: 0;
        }

        /* Center logo when mini */
        #sidebar.sidebar-mini .sidebar-logo-area {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }

        /* Center user area when mini */
        #sidebar.sidebar-mini .sidebar-user-btn {
            justify-content: center;
            padding: 0.5rem;
        }

        /* Tooltip for mini sidebar */
        #sidebar.sidebar-mini .nav-group,
        #sidebar.sidebar-mini li {
            position: relative;
        }
        #sidebar.sidebar-mini .sidebar-tooltip {
            display: none;
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            margin-left: 8px;
            padding: 6px 12px;
            background: #1e293b;
            color: #fff;
            font-size: 13px;
            font-weight: 500;
            border-radius: 8px;
            white-space: nowrap;
            z-index: 100;
            pointer-events: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        #sidebar.sidebar-mini .sidebar-nav-btn:hover .sidebar-tooltip,
        #sidebar.sidebar-mini .sidebar-nav-link:hover .sidebar-tooltip {
            display: block;
        }
    </style>
</head>

<body class="flex min-h-screen bg-surface-100">

    {{-- Sidebar --}}
    @include('partials.sidebar')

    {{-- Main Content --}}
    <div id="main-content" class="flex-1 flex flex-col min-w-0 transition-all duration-300 ease-in-out" style="margin-left: 260px;">
        {{-- Top Bar --}}
        <header
            class="flex justify-between items-center h-14 sm:h-16 w-full px-3 sm:px-6 bg-white/80 backdrop-blur-xl border-b border-surface-200/80 z-10 sticky top-0">
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <button onclick="toggleSidebar()"
                    class="p-1.5 sm:p-2 rounded-xl text-surface-500 hover:text-surface-800 hover:bg-surface-100 transition-all duration-200 active:scale-95 shrink-0">
                    <span class="material-symbols-outlined text-[20px]">menu</span>
                </button>
                <div class="h-5 w-px bg-surface-200 shrink-0 hidden sm:block"></div>
                <span class="text-h3 text-surface-800 truncate">@yield('page-title', 'Dashboard')</span>
            </div>
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <div class="flex items-center gap-1.5 sm:gap-2">
                    {{-- Acumatica status indicator --}}
                    <button id="acumatica-status" onclick="toggleAcumaticaConnection()"
                        class="flex items-center gap-1.5 px-2 sm:px-2.5 py-1.5 bg-surface-100 rounded-lg cursor-pointer hover:bg-surface-200 transition-all duration-200 border border-surface-200/60"
                        title="Click to connect">
                        <div class="w-2 h-2 rounded-full bg-surface-400"></div>
                        <span class="text-label-sm text-surface-500 hidden sm:inline">Acumatica</span>
                    </button>
                    {{-- SQL status indicator --}}
                    <button id="sql-status" onclick="toggleDatabaseConnection()"
                        class="flex items-center gap-1.5 px-2 sm:px-2.5 py-1.5 bg-surface-100 rounded-lg cursor-pointer hover:bg-surface-200 transition-all duration-200 border border-surface-200/60"
                        title="Click to connect">
                        <div class="w-2 h-2 rounded-full bg-surface-400"></div>
                        <span class="text-label-sm text-surface-500 hidden sm:inline">Database</span>
                    </button>
                </div>
                @auth
                    <div class="h-5 w-px bg-surface-200"></div>
                    <div class="relative" id="user-dropdown-container">
                        <button onclick="document.getElementById('user-dropdown').classList.toggle('hidden')"
                            class="flex items-center gap-2 px-2 py-1.5 rounded-xl hover:bg-surface-100 transition-all duration-200">
                            <div
                                class="w-8 h-8 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold text-xs shadow-sm">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </div>
                            <span
                                class="text-body-sm font-medium text-surface-700 hidden sm:inline">{{ auth()->user()->name }}</span>
                            <span class="material-symbols-outlined text-surface-400 text-[16px]">expand_more</span>
                        </button>
                        <div id="user-dropdown"
                            class="hidden absolute right-0 top-full mt-2 w-60 bg-white border border-surface-200 rounded-2xl shadow-lg py-2 z-50 animate-fade-up">
                            <div class="px-4 py-3 border-b border-surface-100">
                                <p class="text-body-md font-semibold text-surface-800">{{ auth()->user()->name }}</p>
                                <p class="text-label-sm text-surface-500 mt-0.5">{{ auth()->user()->email }}</p>
                                <span
                                    class="inline-block mt-1.5 text-[10px] px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 font-semibold tracking-wide uppercase">{{ auth()->user()->primary_role }}</span>
                            </div>
                            <form method="POST" action="{{ route('logout') }}"
                                onsubmit="localStorage.clear(); sessionStorage.clear();">
                                @csrf
                                <button type="submit"
                                    class="w-full flex items-center gap-2.5 px-4 py-2.5 text-body-md text-surface-600 hover:bg-surface-50 hover:text-red-600 transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">logout</span>
                                    Sign Out
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth
            </div>
        </header>

        {{-- Page Content --}}
        <main class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
            @yield('content')
        </main>
    </div>

    {{-- Toast notifications container --}}
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 space-y-2 pointer-events-none"></div>

    <script>
        // CSRF token for AJAX
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // ── Persistent Connection State (localStorage) ───────────────────────
        const STORAGE_KEY_ACM = 'conn_acumatica_enabled';
        const STORAGE_KEY_DB = 'conn_database_enabled';

        let acumaticaConnected = false;
        let databaseConnected = false;

        // ── Toggle: Acumatica ────────────────────────────────────────────────
        function toggleAcumaticaConnection() {
            const isEnabled = localStorage.getItem(STORAGE_KEY_ACM) === '1';
            if (isEnabled && acumaticaConnected) {
                localStorage.removeItem(STORAGE_KEY_ACM);
                acumaticaConnected = false;
                renderAcumaticaDisconnected();
                showToast('Acumatica disconnected', 'info');
            } else {
                localStorage.setItem(STORAGE_KEY_ACM, '1');
                checkAcumaticaConnection();
            }
        }

        // ── Toggle: Database ─────────────────────────────────────────────────
        function toggleDatabaseConnection() {
            const isEnabled = localStorage.getItem(STORAGE_KEY_DB) === '1';
            if (isEnabled && databaseConnected) {
                localStorage.removeItem(STORAGE_KEY_DB);
                databaseConnected = false;
                renderDatabaseDisconnected();
                showToast('Database disconnected', 'info');
            } else {
                localStorage.setItem(STORAGE_KEY_DB, '1');
                checkDatabaseConnection();
            }
        }

        // ── Check Acumatica (ping server) ────────────────────────────────────
        async function checkAcumaticaConnection(silent = false) {
            const acmEl = document.getElementById('acumatica-status');
            acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-amber-500 animate-pulse-dot"></div>
                <span class="text-label-sm text-amber-600">Connecting…</span>`;
            acmEl.className =
                'flex items-center gap-1.5 px-2.5 py-1.5 bg-amber-50 rounded-lg cursor-pointer hover:bg-amber-100 transition-all duration-200 border border-amber-200/60';

            try {
                const res = await fetch('/api/connection-status');
                const data = await res.json();

                if (data.acumatica && data.acumatica.connected) {
                    acumaticaConnected = true;
                    acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                        <span class="text-label-sm text-emerald-700">Acumatica ${data.acumatica.ping_ms}ms</span>`;
                    acmEl.className =
                        'flex items-center gap-1.5 px-2.5 py-1.5 bg-emerald-50 rounded-lg cursor-pointer hover:bg-emerald-100 transition-all duration-200 border border-emerald-200/60';
                    acmEl.title = 'Connected — Click to disconnect';
                    if (!silent) showToast('Acumatica connected successfully', 'success');
                } else {
                    acumaticaConnected = false;
                    acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-500"></div>
                        <span class="text-label-sm text-red-700">Acumatica Offline</span>`;
                    acmEl.className =
                        'flex items-center gap-1.5 px-2.5 py-1.5 bg-red-50 rounded-lg cursor-pointer hover:bg-red-100 transition-all duration-200 border border-red-200/60';
                    acmEl.title = 'Connection failed — Click to retry';
                    if (!silent) showToast('Acumatica connection failed', 'error');
                }

                if (localStorage.getItem(STORAGE_KEY_DB) === '1') {
                    renderDatabaseFromData(data);
                }
            } catch (e) {
                acumaticaConnected = false;
                acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-500"></div>
                    <span class="text-label-sm text-red-700">Error</span>`;
                acmEl.className =
                    'flex items-center gap-1.5 px-2.5 py-1.5 bg-red-50 rounded-lg cursor-pointer hover:bg-red-100 transition-all duration-200 border border-red-200/60';
                if (!silent) showToast('Connection check failed: ' + e.message, 'error');
            }
        }

        // ── Check Database (ping server) ─────────────────────────────────────
        async function checkDatabaseConnection(silent = false) {
            const sqlEl = document.getElementById('sql-status');
            sqlEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-amber-500 animate-pulse-dot"></div>
                <span class="text-label-sm text-amber-600">Connecting…</span>`;
            sqlEl.className =
                'flex items-center gap-1.5 px-2.5 py-1.5 bg-amber-50 rounded-lg cursor-pointer hover:bg-amber-100 transition-all duration-200 border border-amber-200/60';

            try {
                const res = await fetch('/api/connection-status');
                const data = await res.json();

                renderDatabaseFromData(data);

                if (data.database && data.database.connected) {
                    if (!silent) showToast(`${data.database.type} connected successfully`, 'success');
                } else {
                    if (!silent) showToast('Database connection failed', 'error');
                }

                if (localStorage.getItem(STORAGE_KEY_ACM) === '1') {
                    renderAcumaticaFromData(data);
                }
            } catch (e) {
                databaseConnected = false;
                sqlEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-500"></div>
                    <span class="text-label-sm text-red-700">Error</span>`;
                sqlEl.className =
                    'flex items-center gap-1.5 px-2.5 py-1.5 bg-red-50 rounded-lg cursor-pointer hover:bg-red-100 transition-all duration-200 border border-red-200/60';
                if (!silent) showToast('Connection check failed: ' + e.message, 'error');
            }
        }

        // ── Render helpers ───────────────────────────────────────────────────
        function renderAcumaticaDisconnected() {
            const el = document.getElementById('acumatica-status');
            el.innerHTML = `<div class="w-2 h-2 rounded-full bg-surface-400"></div>
                <span class="text-label-sm text-surface-500">Acumatica</span>`;
            el.className =
                'flex items-center gap-1.5 px-2.5 py-1.5 bg-surface-100 rounded-lg cursor-pointer hover:bg-surface-200 transition-all duration-200 border border-surface-200/60';
            el.title = 'Click to connect';
        }

        function renderDatabaseDisconnected() {
            const el = document.getElementById('sql-status');
            el.innerHTML = `<div class="w-2 h-2 rounded-full bg-surface-400"></div>
                <span class="text-label-sm text-surface-500">Database</span>`;
            el.className =
                'flex items-center gap-1.5 px-2.5 py-1.5 bg-surface-100 rounded-lg cursor-pointer hover:bg-surface-200 transition-all duration-200 border border-surface-200/60';
            el.title = 'Click to connect';
        }

        function renderAcumaticaFromData(data) {
            const acmEl = document.getElementById('acumatica-status');
            if (data.acumatica && data.acumatica.connected) {
                acumaticaConnected = true;
                acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                    <span class="text-label-sm text-emerald-700">Acumatica ${data.acumatica.ping_ms}ms</span>`;
                acmEl.className =
                    'flex items-center gap-1.5 px-2.5 py-1.5 bg-emerald-50 rounded-lg cursor-pointer hover:bg-emerald-100 transition-all duration-200 border border-emerald-200/60';
                acmEl.title = 'Connected — Click to disconnect';
            } else if (data.acumatica) {
                acumaticaConnected = false;
                acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-500"></div>
                    <span class="text-label-sm text-red-700">Acumatica Offline</span>`;
                acmEl.className =
                    'flex items-center gap-1.5 px-2.5 py-1.5 bg-red-50 rounded-lg cursor-pointer hover:bg-red-100 transition-all duration-200 border border-red-200/60';
                acmEl.title = 'Connection failed — Click to retry';
            }
        }

        function renderDatabaseFromData(data) {
            const sqlEl = document.getElementById('sql-status');
            if (data.database && data.database.connected) {
                databaseConnected = true;
                sqlEl.innerHTML =
                    `<div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                    <span class="text-label-sm text-emerald-700">${data.database.type} ${data.database.ping_ms}ms</span>`;
                sqlEl.className =
                    'flex items-center gap-1.5 px-2.5 py-1.5 bg-emerald-50 rounded-lg cursor-pointer hover:bg-emerald-100 transition-all duration-200 border border-emerald-200/60';
                sqlEl.title = 'Connected — Click to disconnect';
            } else if (data.database) {
                databaseConnected = false;
                sqlEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-500"></div>
                    <span class="text-label-sm text-red-700">${data.database.type} Offline</span>`;
                sqlEl.className =
                    'flex items-center gap-1.5 px-2.5 py-1.5 bg-red-50 rounded-lg cursor-pointer hover:bg-red-100 transition-all duration-200 border border-red-200/60';
                sqlEl.title = 'Connection failed — Click to retry';
            }
        }

        // Toast notification
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const configs = {
                success: {
                    bg: 'bg-emerald-600',
                    icon: 'check_circle'
                },
                error: {
                    bg: 'bg-red-600',
                    icon: 'error'
                },
                info: {
                    bg: 'bg-surface-700',
                    icon: 'info'
                },
                warning: {
                    bg: 'bg-amber-600',
                    icon: 'warning'
                },
            };
            const cfg = configs[type] || configs.info;
            const toast = document.createElement('div');
            toast.className =
                `${cfg.bg} text-white px-4 py-3 rounded-xl shadow-toast text-body-md flex items-center gap-2.5 animate-slide-in pointer-events-auto min-w-[280px] max-w-sm`;
            toast.innerHTML =
                `<span class="material-symbols-outlined text-[18px]">${cfg.icon}</span><span class="flex-1">${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('animate-slide-in');
                toast.classList.add('animate-slide-out');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        // Global Async Polling Helpers
        window.pollSyncJob = async function(jobId, entityKey, callbacks = {}) {
            try {
                const res = await fetch(`/api/sync-jobs/${jobId}`);
                const data = await res.json();
                const job = data.data;

                if (callbacks.onProgress) callbacks.onProgress(job);

                if (job.status === 'completed') {
                    if (callbacks.onSuccess) callbacks.onSuccess(job);
                    showToast(
                        `${entityKey}: ${job.processed_rows.toLocaleString()} records synced in ${job.duration}`,
                        'success');
                    return;
                } else if (job.status === 'failed') {
                    if (callbacks.onError) callbacks.onError(job);
                    showToast(`${entityKey}: ${job.error_message || 'Sync failed'}`, 'error');
                    return;
                }

                // Keep polling if still running or pending
                setTimeout(() => window.pollSyncJob(jobId, entityKey, callbacks), 3000);
            } catch (e) {
                console.error('Polling error', e);
                // Retry in 5s if network error
                setTimeout(() => window.pollSyncJob(jobId, entityKey, callbacks), 5000);
            }
        };

        // Sync entity
        async function syncEntity(entityKey, btnEl) {
            if (btnEl) {
                btnEl.disabled = true;
                btnEl.innerHTML = '<span class="material-symbols-outlined text-sm spin">sync</span> Syncing…';
            }

            try {
                const res = await fetch(`/sync/${entityKey}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                });
                const data = await res.json();

                if (data.success && data.job_id) {
                    showToast(`${entityKey}: Synchronization has been queued.`, 'info');
                    window.pollSyncJob(data.job_id, entityKey, {
                        onSuccess: () => {
                            if (btnEl) {
                                btnEl.disabled = false;
                                btnEl.innerHTML =
                                    '<span class="material-symbols-outlined text-sm">sync</span> Sync';
                            }
                            setTimeout(() => location.reload(), 1500);
                        },
                        onError: () => {
                            if (btnEl) {
                                btnEl.disabled = false;
                                btnEl.innerHTML =
                                    '<span class="material-symbols-outlined text-sm">sync</span> Sync';
                            }
                        }
                    });
                } else {
                    showToast(`${entityKey}: ${data.error || data.message}`, 'error');
                    if (btnEl) {
                        btnEl.disabled = false;
                        btnEl.innerHTML = '<span class="material-symbols-outlined text-sm">sync</span> Sync';
                    }
                }
            } catch (e) {
                showToast(`${entityKey}: ${e.message}`, 'error');
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = '<span class="material-symbols-outlined text-sm">sync</span> Sync';
                }
            }
        }

        // Sync all
        async function syncAll(btnEl) {
            if (btnEl) {
                btnEl.disabled = true;
                btnEl.innerHTML = '<span class="material-symbols-outlined text-sm spin">sync</span> Syncing All…';
            }
            showToast('Starting full sync… Jobs have been queued.', 'info');

            try {
                const res = await fetch('/sync/all', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.href = '/sync-history', 1500);
                } else {
                    showToast(data.message, 'error');
                    if (btnEl) {
                        btnEl.disabled = false;
                        btnEl.innerHTML =
                            '<span class="material-symbols-outlined text-[16px]">sync</span> Force Sync All';
                    }
                }
            } catch (e) {
                showToast('Sync failed: ' + e.message, 'error');
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = '<span class="material-symbols-outlined text-[16px]">sync</span> Force Sync All';
                }
            }
        }

        // Sidebar toggle — icon-only minimize
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const isMini = sidebar.classList.toggle('sidebar-mini');

            mainContent.style.marginLeft = isMini ? '64px' : '260px';
            localStorage.setItem('sidebar_mini', isMini ? '1' : '0');
        }

        // Restore sidebar state from localStorage & set initial margin
        (function initSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const savedState = localStorage.getItem('sidebar_mini');

            if (savedState === '1') {
                sidebar.classList.add('sidebar-mini');
                mainContent.style.marginLeft = '64px';
            } else {
                sidebar.classList.remove('sidebar-mini');
                mainContent.style.marginLeft = '260px';
            }
        })();

        // ── Init: auto-connect if previously enabled ─────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            const acmEnabled = localStorage.getItem(STORAGE_KEY_ACM) === '1';
            const dbEnabled = localStorage.getItem(STORAGE_KEY_DB) === '1';

            if (acmEnabled && dbEnabled) {
                checkAcumaticaConnection(true);
            } else if (acmEnabled) {
                checkAcumaticaConnection(true);
            } else if (dbEnabled) {
                checkDatabaseConnection(true);
            }

            // Close user dropdown on outside click
            document.addEventListener('click', (e) => {
                const dd = document.getElementById('user-dropdown');
                const container = document.getElementById('user-dropdown-container');
                if (dd && container && !container.contains(e.target)) {
                    dd.classList.add('hidden');
                }
            });

            // ── Background Web Cron (Local Development Helper) ───────────────────
            // This eliminates the need to run schedule:work or queue:work manually in Laragon.
            // Checks every 10 seconds, but only fires the HTTP request every 55 seconds across all tabs.
            setInterval(() => {
                const webCronEnabled = localStorage.getItem('enable_web_cron') === '1';
                if (!webCronEnabled) return;

                const lastRun = localStorage.getItem('last_web_cron');
                const now = new Date().getTime();
                if (!lastRun || now - parseInt(lastRun) > 300000) {
                    localStorage.setItem('last_web_cron', now.toString());
                    fetch('/sys-cron?token=deploy123').catch(() => {});
                }
            }, 10000);
        });
    </script>

    @yield('scripts')
</body>

</html>
