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
    <div class="flex-1 flex flex-col ml-64 min-w-0">
        {{-- Top Bar --}}
        <header class="flex justify-between items-center h-16 w-full px-8 bg-surface-container-lowest border-b border-outline-variant z-10 sticky top-0">
            <div class="flex items-center">
                <span class="font-h1 text-h1 text-on-surface">@yield('page-title', 'Dashboard')</span>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    {{-- Acumatica status indicator --}}
                    <div id="acumatica-status" class="flex items-center gap-xs px-2 py-1 bg-gray-50 rounded">
                        <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                        <span class="font-label-sm text-label-sm text-gray-500">Checking…</span>
                    </div>
                    {{-- SQL status indicator --}}
                    <div id="sql-status" class="flex items-center gap-xs px-2 py-1 bg-gray-50 rounded">
                        <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                        <span class="font-label-sm text-label-sm text-gray-500">Checking…</span>
                    </div>
                </div>
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

        // Check connection status
        async function checkConnections() {
            try {
                const res = await fetch('/api/connection-status');
                const data = await res.json();

                // Acumatica
                const acmEl = document.getElementById('acumatica-status');
                if (data.acumatica.connected) {
                    acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-green-600"></div>
                        <span class="font-label-sm text-label-sm text-green-700">Acumatica ${data.acumatica.ping_ms}ms</span>`;
                    acmEl.className = 'flex items-center gap-xs px-2 py-1 bg-green-50 rounded';
                } else {
                    acmEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-600"></div>
                        <span class="font-label-sm text-label-sm text-red-700">Acumatica Offline</span>`;
                    acmEl.className = 'flex items-center gap-xs px-2 py-1 bg-red-50 rounded';
                }

                // SQL Server
                const sqlEl = document.getElementById('sql-status');
                if (data.sql_server.connected) {
                    sqlEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-green-600"></div>
                        <span class="font-label-sm text-label-sm text-green-700">SQL ${data.sql_server.ping_ms}ms</span>`;
                    sqlEl.className = 'flex items-center gap-xs px-2 py-1 bg-green-50 rounded';
                } else {
                    sqlEl.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-600"></div>
                        <span class="font-label-sm text-label-sm text-red-700">SQL Offline</span>`;
                    sqlEl.className = 'flex items-center gap-xs px-2 py-1 bg-red-50 rounded';
                }
            } catch (e) {
                console.error('Connection check failed:', e);
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

        // Init
        document.addEventListener('DOMContentLoaded', () => {
            checkConnections();
        });
    </script>

    @yield('scripts')
</body>
</html>
