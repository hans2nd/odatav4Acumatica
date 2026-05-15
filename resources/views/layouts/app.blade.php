<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Enterprise Sync Engine')</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "on-surface-variant": "#45464d",
                        "on-error": "#ffffff",
                        "tertiary-container": "#271901",
                        "surface-tint": "#565e74",
                        "on-primary-container": "#7c839b",
                        "primary": "#000000",
                        "on-tertiary-fixed": "#271901",
                        "surface-container": "#f0edef",
                        "surface": "#fcf8fa",
                        "primary-container": "#131b2e",
                        "surface-variant": "#e4e2e4",
                        "on-secondary-fixed-variant": "#3a485c",
                        "on-background": "#1b1b1d",
                        "background": "#fcf8fa",
                        "secondary-fixed-dim": "#b9c7e0",
                        "on-surface": "#1b1b1d",
                        "surface-bright": "#fcf8fa",
                        "on-primary": "#ffffff",
                        "surface-container-lowest": "#ffffff",
                        "tertiary-fixed": "#fcdeb5",
                        "outline-variant": "#c6c6cd",
                        "on-error-container": "#93000a",
                        "error-container": "#ffdad6",
                        "inverse-surface": "#303032",
                        "on-secondary": "#ffffff",
                        "surface-dim": "#dcd9db",
                        "on-secondary-fixed": "#0d1c2f",
                        "secondary": "#515f74",
                        "on-tertiary-fixed-variant": "#574425",
                        "outline": "#76777d",
                        "secondary-fixed": "#d5e3fd",
                        "on-primary-fixed-variant": "#3f465c",
                        "on-primary-fixed": "#131b2e",
                        "tertiary": "#000000",
                        "surface-container-low": "#f6f3f5",
                        "on-tertiary": "#ffffff",
                        "primary-fixed": "#dae2fd",
                        "on-tertiary-container": "#98805d",
                        "surface-container-highest": "#e4e2e4",
                        "primary-fixed-dim": "#bec6e0",
                        "inverse-on-surface": "#f3f0f2",
                        "on-secondary-container": "#57657b",
                        "secondary-container": "#d5e3fd",
                        "inverse-primary": "#bec6e0",
                        "tertiary-fixed-dim": "#dec29a",
                        "surface-container-high": "#eae7e9",
                        "error": "#ba1a1a"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.125rem",
                        "lg": "0.25rem",
                        "xl": "0.5rem",
                        "full": "0.75rem"
                    },
                    "spacing": {
                        "md": "16px",
                        "lg": "24px",
                        "unit": "4px",
                        "container-margin": "32px",
                        "xs": "4px",
                        "column-gutter": "20px",
                        "sm": "8px",
                        "xl": "32px"
                    },
                    "fontFamily": {
                        "h2": ["Inter"],
                        "label-sm": ["Inter"],
                        "display": ["Inter"],
                        "body-md": ["Inter"],
                        "h1": ["Inter"],
                        "body-lg": ["Inter"],
                        "label-md": ["Inter"],
                        "code": ["Inter"]
                    },
                    "fontSize": {
                        "h2": ["20px", {"lineHeight": "28px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                        "label-sm": ["11px", {"lineHeight": "14px", "letterSpacing": "0.03em", "fontWeight": "500"}],
                        "display": ["30px", {"lineHeight": "38px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-md": ["14px", {"lineHeight": "20px", "letterSpacing": "0", "fontWeight": "400"}],
                        "h1": ["24px", {"lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                        "body-lg": ["16px", {"lineHeight": "24px", "letterSpacing": "0", "fontWeight": "400"}],
                        "label-md": ["12px", {"lineHeight": "16px", "letterSpacing": "0.02em", "fontWeight": "600"}],
                        "code": ["13px", {"lineHeight": "18px", "letterSpacing": "0", "fontWeight": "400"}]
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: theme('colors.background');
            color: theme('colors.on-background');
        }
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="flex min-h-screen">

    {{-- Sidebar --}}
    @include('partials.sidebar')

    {{-- Main Content --}}
    <div id="main-content" class="flex-1 flex flex-col ml-64 min-w-0 transition-all duration-300">
        {{-- Top Bar --}}
        <header class="flex justify-between items-center h-16 w-full px-8 bg-surface-container-lowest border-b border-outline-variant z-10 sticky top-0">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="p-2 rounded text-on-surface-variant hover:bg-surface-container-low transition-colors active:scale-95">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <span class="font-h1 text-h1 text-on-surface">@yield('page-title', 'Dashboard')</span>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    {{-- Acumatica status indicator --}}
                    <button id="acumatica-status" onclick="toggleAcumaticaConnection()" class="flex items-center gap-xs px-2 py-1 bg-gray-50 rounded cursor-pointer hover:bg-gray-100 transition-colors" title="Click to connect">
                        <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                        <span class="font-label-sm text-label-sm text-gray-500">Acumatica</span>
                    </button>
                    {{-- SQL status indicator --}}
                    <button id="sql-status" onclick="toggleDatabaseConnection()" class="flex items-center gap-xs px-2 py-1 bg-gray-50 rounded cursor-pointer hover:bg-gray-100 transition-colors" title="Click to connect">
                        <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                        <span class="font-label-sm text-label-sm text-gray-500">Database</span>
                    </button>
                </div>
                @auth
                <div class="h-6 w-px bg-outline-variant"></div>
                <div class="relative" id="user-dropdown-container">
                    <button onclick="document.getElementById('user-dropdown').classList.toggle('hidden')" class="flex items-center gap-2 px-2 py-1 rounded hover:bg-surface-container-low transition-colors">
                        <div class="w-7 h-7 rounded-full bg-primary-container flex items-center justify-center text-on-primary-container font-bold text-xs">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <span class="font-label-md text-label-md text-on-surface hidden sm:inline">{{ auth()->user()->name }}</span>
                        <span class="material-symbols-outlined text-on-surface-variant text-sm">expand_more</span>
                    </button>
                    <div id="user-dropdown" class="hidden absolute right-0 top-full mt-1 w-56 bg-surface-container-lowest border border-outline-variant rounded-xl shadow-lg py-2 z-50">
                        <div class="px-4 py-2 border-b border-outline-variant">
                            <p class="text-body-md font-medium text-on-surface">{{ auth()->user()->name }}</p>
                            <p class="text-label-sm text-on-surface-variant">{{ auth()->user()->email }}</p>
                            <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full bg-primary-container text-on-primary-container font-medium">{{ auth()->user()->primary_role }}</span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-body-md text-on-surface-variant hover:bg-surface-container-low transition-colors">
                                <span class="material-symbols-outlined text-sm">logout</span>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
                @endauth
            </div>
        </header>

        {{-- Page Content --}}
        <main class="flex-1 overflow-y-auto p-container-margin space-y-lg bg-surface">
            @yield('content')
        </main>
    </div>

    {{-- Toast notifications container --}}
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 space-y-2"></div>

    <script>
        // CSRF token for AJAX
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // ── Persistent Connection State (localStorage) ───────────────────────
        const STORAGE_KEY_ACM = 'conn_acumatica_enabled';
        const STORAGE_KEY_DB  = 'conn_database_enabled';

        let acumaticaConnected = false;
        let databaseConnected = false;

        // ── Toggle: Acumatica ────────────────────────────────────────────────
        function toggleAcumaticaConnection() {
            const isEnabled = localStorage.getItem(STORAGE_KEY_ACM) === '1';
            if (isEnabled && acumaticaConnected) {
                // Disconnect
                localStorage.removeItem(STORAGE_KEY_ACM);
                acumaticaConnected = false;
                renderAcumaticaDisconnected();
                showToast('Acumatica disconnected', 'info');
            } else {
                // Connect
                localStorage.setItem(STORAGE_KEY_ACM, '1');
                checkAcumaticaConnection();
            }
        }

        // ── Toggle: Database ─────────────────────────────────────────────────
        function toggleDatabaseConnection() {
            const isEnabled = localStorage.getItem(STORAGE_KEY_DB) === '1';
            if (isEnabled && databaseConnected) {
                // Disconnect
                localStorage.removeItem(STORAGE_KEY_DB);
                databaseConnected = false;
                renderDatabaseDisconnected();
                showToast('Database disconnected', 'info');
            } else {
                // Connect
                localStorage.setItem(STORAGE_KEY_DB, '1');
                checkDatabaseConnection();
            }
        }

        // ── Check Acumatica (ping server) ────────────────────────────────────
        async function checkAcumaticaConnection(silent = false) {
            const acmEl = document.getElementById('acumatica-status');
            acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-yellow-500 spin"></div>
                <span class="font-label-sm text-label-sm text-yellow-600">Connecting…</span>`;
            acmEl.className = 'flex items-center gap-xs px-2 py-1 bg-yellow-50 rounded cursor-pointer hover:bg-yellow-100 transition-colors';

            try {
                const res = await fetch('/api/connection-status');
                const data = await res.json();

                if (data.acumatica && data.acumatica.connected) {
                    acumaticaConnected = true;
                    acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-green-600"></div>
                        <span class="font-label-sm text-label-sm text-green-700">Acumatica ${data.acumatica.ping_ms}ms</span>`;
                    acmEl.className = 'flex items-center gap-xs px-2 py-1 bg-green-50 rounded cursor-pointer hover:bg-green-100 transition-colors';
                    acmEl.title = 'Connected — Click to disconnect';
                    if (!silent) showToast('Acumatica connected successfully', 'success');
                } else {
                    acumaticaConnected = false;
                    acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-600"></div>
                        <span class="font-label-sm text-label-sm text-red-700">Acumatica Offline</span>`;
                    acmEl.className = 'flex items-center gap-xs px-2 py-1 bg-red-50 rounded cursor-pointer hover:bg-red-100 transition-colors';
                    acmEl.title = 'Connection failed — Click to retry';
                    if (!silent) showToast('Acumatica connection failed', 'error');
                }

                // Also update database if it's enabled
                if (localStorage.getItem(STORAGE_KEY_DB) === '1') {
                    renderDatabaseFromData(data);
                }
            } catch (e) {
                acumaticaConnected = false;
                acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-600"></div>
                    <span class="font-label-sm text-label-sm text-red-700">Error</span>`;
                acmEl.className = 'flex items-center gap-xs px-2 py-1 bg-red-50 rounded cursor-pointer hover:bg-red-100 transition-colors';
                if (!silent) showToast('Connection check failed: ' + e.message, 'error');
            }
        }

        // ── Check Database (ping server) ─────────────────────────────────────
        async function checkDatabaseConnection(silent = false) {
            const sqlEl = document.getElementById('sql-status');
            sqlEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-yellow-500 spin"></div>
                <span class="font-label-sm text-label-sm text-yellow-600">Connecting…</span>`;
            sqlEl.className = 'flex items-center gap-xs px-2 py-1 bg-yellow-50 rounded cursor-pointer hover:bg-yellow-100 transition-colors';

            try {
                const res = await fetch('/api/connection-status');
                const data = await res.json();

                renderDatabaseFromData(data);

                if (data.database && data.database.connected) {
                    if (!silent) showToast(`${data.database.type} connected successfully`, 'success');
                } else {
                    if (!silent) showToast('Database connection failed', 'error');
                }

                // Also update acumatica if it's enabled
                if (localStorage.getItem(STORAGE_KEY_ACM) === '1') {
                    renderAcumaticaFromData(data);
                }
            } catch (e) {
                databaseConnected = false;
                sqlEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-600"></div>
                    <span class="font-label-sm text-label-sm text-red-700">Error</span>`;
                sqlEl.className = 'flex items-center gap-xs px-2 py-1 bg-red-50 rounded cursor-pointer hover:bg-red-100 transition-colors';
                if (!silent) showToast('Connection check failed: ' + e.message, 'error');
            }
        }

        // ── Render helpers ───────────────────────────────────────────────────
        function renderAcumaticaDisconnected() {
            const el = document.getElementById('acumatica-status');
            el.innerHTML = `<div class="w-2 h-2 rounded-full bg-gray-400"></div>
                <span class="font-label-sm text-label-sm text-gray-500">Acumatica</span>`;
            el.className = 'flex items-center gap-xs px-2 py-1 bg-gray-50 rounded cursor-pointer hover:bg-gray-100 transition-colors';
            el.title = 'Click to connect';
        }

        function renderDatabaseDisconnected() {
            const el = document.getElementById('sql-status');
            el.innerHTML = `<div class="w-2 h-2 rounded-full bg-gray-400"></div>
                <span class="font-label-sm text-label-sm text-gray-500">Database</span>`;
            el.className = 'flex items-center gap-xs px-2 py-1 bg-gray-50 rounded cursor-pointer hover:bg-gray-100 transition-colors';
            el.title = 'Click to connect';
        }

        function renderAcumaticaFromData(data) {
            const acmEl = document.getElementById('acumatica-status');
            if (data.acumatica && data.acumatica.connected) {
                acumaticaConnected = true;
                acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-green-600"></div>
                    <span class="font-label-sm text-label-sm text-green-700">Acumatica ${data.acumatica.ping_ms}ms</span>`;
                acmEl.className = 'flex items-center gap-xs px-2 py-1 bg-green-50 rounded cursor-pointer hover:bg-green-100 transition-colors';
                acmEl.title = 'Connected — Click to disconnect';
            } else if (data.acumatica) {
                acumaticaConnected = false;
                acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-600"></div>
                    <span class="font-label-sm text-label-sm text-red-700">Acumatica Offline</span>`;
                acmEl.className = 'flex items-center gap-xs px-2 py-1 bg-red-50 rounded cursor-pointer hover:bg-red-100 transition-colors';
                acmEl.title = 'Connection failed — Click to retry';
            }
        }

        function renderDatabaseFromData(data) {
            const sqlEl = document.getElementById('sql-status');
            if (data.database && data.database.connected) {
                databaseConnected = true;
                sqlEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-green-600"></div>
                    <span class="font-label-sm text-label-sm text-green-700">${data.database.type} ${data.database.ping_ms}ms</span>`;
                sqlEl.className = 'flex items-center gap-xs px-2 py-1 bg-green-50 rounded cursor-pointer hover:bg-green-100 transition-colors';
                sqlEl.title = 'Connected — Click to disconnect';
            } else if (data.database) {
                databaseConnected = false;
                sqlEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-600"></div>
                    <span class="font-label-sm text-label-sm text-red-700">${data.database.type} Offline</span>`;
                sqlEl.className = 'flex items-center gap-xs px-2 py-1 bg-red-50 rounded cursor-pointer hover:bg-red-100 transition-colors';
                sqlEl.title = 'Connection failed — Click to retry';
            }
        }

        // Toast notification
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const colors = {
                success: 'bg-green-600',
                error:   'bg-red-600',
                info:    'bg-primary-container',
                warning: 'bg-yellow-600',
            };
            const toast = document.createElement('div');
            toast.className = `${colors[type] || colors.info} text-white px-4 py-3 rounded shadow-lg font-body-md text-body-md fade-in max-w-sm`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 6000);
        }

        // Sync entity
        async function syncEntity(entityKey, btnEl) {
            if (btnEl) {
                btnEl.disabled = true;
                btnEl.innerHTML = '<span class="material-symbols-outlined text-sm spin">sync</span> Syncing…';
            }

            try {
                const res = await fetch(`/sync/${entityKey}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });
                const data = await res.json();

                if (data.success) {
                    showToast(`${entityKey}: ${data.records} records synced in ${data.duration}`, 'success');
                } else {
                    showToast(`${entityKey}: ${data.error || data.message}`, 'error');
                }
            } catch (e) {
                showToast(`${entityKey}: ${e.message}`, 'error');
            } finally {
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = '<span class="material-symbols-outlined text-sm">sync</span> Sync';
                }
                setTimeout(() => location.reload(), 1500);
            }
        }

        // Sync all
        async function syncAll(btnEl) {
            if (btnEl) {
                btnEl.disabled = true;
                btnEl.innerHTML = '<span class="material-symbols-outlined text-sm spin">sync</span> Syncing All…';
            }
            showToast('Starting full sync… This may take several minutes.', 'info');

            try {
                const res = await fetch('/sync/all', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            } catch (e) {
                showToast('Sync failed: ' + e.message, 'error');
            } finally {
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = 'Force Sync All';
                }
                setTimeout(() => location.reload(), 2000);
            }
        }

        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                mainContent.classList.add('ml-64');
                mainContent.classList.remove('ml-0');
            } else {
                sidebar.classList.add('-translate-x-full');
                mainContent.classList.remove('ml-64');
                mainContent.classList.add('ml-0');
            }
        }

        // ── Init: auto-connect if previously enabled ─────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            const acmEnabled = localStorage.getItem(STORAGE_KEY_ACM) === '1';
            const dbEnabled  = localStorage.getItem(STORAGE_KEY_DB) === '1';

            if (acmEnabled && dbEnabled) {
                // Both enabled — single API call updates both
                checkAcumaticaConnection(true);
            } else if (acmEnabled) {
                checkAcumaticaConnection(true);
            } else if (dbEnabled) {
                checkDatabaseConnection(true);
            }
            // If neither enabled → stay gray "Not Connected"

            // Close user dropdown on outside click
            document.addEventListener('click', (e) => {
                const dd = document.getElementById('user-dropdown');
                const container = document.getElementById('user-dropdown-container');
                if (dd && container && !container.contains(e.target)) {
                    dd.classList.add('hidden');
                }
            });
        });
    </script>

    @yield('scripts')
</body>
</html>
