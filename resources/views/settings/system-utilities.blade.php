@extends('layouts.app')

@section('title', 'System Utilities — PL Sync Data Warehouse')
@section('page-title', 'System Utilities')

@section('content')
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 animate-fade-up">

        {{-- Migrate Component --}}
        <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100 flex items-center bg-surface-50/50 gap-3">
                <div class="p-2 bg-gradient-to-br from-indigo-50 to-blue-100 rounded-lg">
                    <span class="material-symbols-outlined text-indigo-600 text-[18px]">database</span>
                </div>
                <div>
                    <h2 class="text-h3 text-surface-800">Database Migration</h2>
                    <p class="text-label-sm text-surface-400 mt-1">Run pending migrations to update database schema</p>
                </div>
            </div>
            <div class="p-5 flex flex-col gap-4">
                <p class="text-body-sm text-surface-600">This will execute `php artisan migrate` on the server. Only new
                    migrations will be run. Existing data is preserved.</p>
                <div class="mt-auto pt-2">
                    <button onclick="runCommand('{{ route('sys.migrate') }}')"
                        class="w-full flex justify-center items-center gap-2 px-4 py-2.5 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 active:scale-[0.98] shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">play_circle</span> Run Migration
                    </button>
                </div>
            </div>
        </div>

        {{-- Optimize Component --}}
        <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100 flex items-center bg-surface-50/50 gap-3">
                <div class="p-2 bg-gradient-to-br from-emerald-50 to-teal-100 rounded-lg">
                    <span class="material-symbols-outlined text-emerald-600 text-[18px]">bolt</span>
                </div>
                <div>
                    <h2 class="text-h3 text-surface-800">Optimize / Clear Cache</h2>
                    <p class="text-label-sm text-surface-400 mt-1">Clear all cached views, routes, and config</p>
                </div>
            </div>
            <div class="p-5 flex flex-col gap-4">
                <p class="text-body-sm text-surface-600">This will execute `php artisan optimize:clear` on the server.
                    Useful if you recently deployed new changes or updated `.env`.</p>
                <div class="mt-auto pt-2">
                    <button onclick="runCommand('{{ route('sys.optimize') }}')"
                        class="w-full flex justify-center items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-label-md hover:bg-emerald-700 transition-all duration-200 active:scale-[0.98] shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">mop</span> Clear Cache
                    </button>
                </div>
            </div>
        </div>

        {{-- Clear Logs Component --}}
        <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100 flex items-center bg-surface-50/50 gap-3">
                <div class="p-2 bg-gradient-to-br from-gray-50 to-slate-100 rounded-lg">
                    <span class="material-symbols-outlined text-slate-600 text-[18px]">delete_sweep</span>
                </div>
                <div>
                    <h2 class="text-h3 text-surface-800">Clear Laravel Logs</h2>
                    <p class="text-label-sm text-surface-400 mt-1">Empty all .log files in storage/logs</p>
                </div>
            </div>
            <div class="p-5 flex flex-col gap-4">
                <p class="text-body-sm text-surface-600">This will empty the contents of all log files to free up disk space. Use this if your logs are piling up.</p>
                <div class="mt-auto pt-2">
                    <button onclick="runCommand('{{ route('sys.clear-logs') }}')"
                        class="w-full flex justify-center items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-600 text-white text-label-md hover:bg-slate-700 transition-all duration-200 active:scale-[0.98] shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">delete_sweep</span> Clear Logs
                    </button>
                </div>
            </div>
        </div>

        {{-- Storage Link Component --}}
        <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100 flex items-center bg-surface-50/50 gap-3">
                <div class="p-2 bg-gradient-to-br from-amber-50 to-orange-100 rounded-lg">
                    <span class="material-symbols-outlined text-amber-600 text-[18px]">folder_special</span>
                </div>
                <div>
                    <h2 class="text-h3 text-surface-800">Storage Link</h2>
                    <p class="text-label-sm text-surface-400 mt-1">Create a symbolic link for public storage</p>
                </div>
            </div>
            <div class="p-5 flex flex-col gap-4">
                <p class="text-body-sm text-surface-600">This will execute `php artisan storage:link` on the server.
                    Required for uploaded files to be publicly accessible.</p>
                <div class="mt-auto pt-2">
                    <button onclick="runCommand('{{ route('sys.artisan', ['command' => 'storage-link']) }}')"
                        class="w-full flex justify-center items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 text-white text-label-md hover:bg-amber-600 transition-all duration-200 active:scale-[0.98] shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">link</span> Link Storage
                    </button>
                </div>
            </div>
        </div>

        {{-- Migrate Fresh Component (DANGER) --}}
        <div class="bg-white rounded-2xl border border-red-200 flex flex-col shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-red-100 flex items-center bg-red-50/50 gap-3">
                <div class="p-2 bg-gradient-to-br from-red-50 to-rose-100 rounded-lg">
                    <span class="material-symbols-outlined text-red-600 text-[18px]">warning</span>
                </div>
                <div>
                    <h2 class="text-h3 text-red-700">Migrate Fresh (Danger)</h2>
                    <p class="text-label-sm text-red-400 mt-1">Drop all tables and re-run all migrations</p>
                </div>
            </div>
            <div class="p-5 flex flex-col gap-4">
                <p class="text-body-sm text-red-600 font-medium">WARNING: This will DESTROY ALL DATA in the local database.
                    Only use this during initial setup or development.</p>
                <div class="mt-auto pt-2">
                    <button onclick="confirmMigrateFresh()"
                        class="w-full flex justify-center items-center gap-2 px-4 py-2.5 rounded-xl bg-red-600 text-white text-label-md hover:bg-red-700 transition-all duration-200 active:scale-[0.98] shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">delete_forever</span> Migrate Fresh
                    </button>
                </div>
            </div>
        </div>

        {{-- Background Web Cron Component --}}
        <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100 flex items-center bg-surface-50/50 gap-3">
                <div class="p-2 bg-gradient-to-br from-purple-50 to-fuchsia-100 rounded-lg">
                    <span class="material-symbols-outlined text-purple-600 text-[18px]">language</span>
                </div>
                <div>
                    <h2 class="text-h3 text-surface-800">Background Web Cron</h2>
                    <p class="text-label-sm text-surface-400 mt-1">Run scheduled tasks via browser</p>
                </div>
            </div>
            <div class="p-5 flex flex-col gap-4">
                <p class="text-body-sm text-surface-600">If you cannot configure a native VPS Cronjob, enable this feature to allow your browser to automatically trigger scheduled syncs while this app is open.</p>
                <div class="mt-auto pt-2">
                    <button id="btnToggleWebCron" onclick="toggleWebCron()"
                        class="w-full flex justify-center items-center gap-2 px-4 py-2.5 rounded-xl bg-purple-600 text-white text-label-md hover:bg-purple-700 transition-all duration-200 active:scale-[0.98] shadow-sm">
                        <span class="material-symbols-outlined text-[18px]" id="webCronIcon">toggle_off</span> 
                        <span id="webCronText">Enable Web Cron</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Cronjob Setup Info Component --}}
        <div
            class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden md:col-span-2">
            <div class="px-5 py-4 border-b border-surface-100 flex items-center bg-surface-50/50 gap-3">
                <div class="p-2 bg-gradient-to-br from-blue-50 to-cyan-100 rounded-lg">
                    <span class="material-symbols-outlined text-cyan-600 text-[18px]">schedule</span>
                </div>
                <div>
                    <h2 class="text-h3 text-surface-800">VPS Cronjob Setup</h2>
                    <p class="text-label-sm text-surface-400 mt-1">Information for setting up the background scheduler on
                        your server</p>
                </div>
            </div>
            <div class="p-5 flex flex-col gap-4">
                <p class="text-body-sm text-surface-600">To ensure the **Automated Sync Scheduler** runs properly, you must
                    add the following Cronjob entry on your VPS or hosting panel (e.g. cPanel, aaPanel, or via `crontab
                    -e`). This allows Laravel to process scheduled tasks every minute.</p>

                <div class="bg-slate-600 rounded-xl p-4 flex items-center justify-between gap-4">
                    <code class="text-emerald-400 text-sm font-mono break-all" id="cronCommand">* * * * * cd
                        {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</code>
                    <button onclick="copyCronCommand('cronCommand')"
                        class="shrink-0 flex justify-center items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-700 text-blue text-label-sm hover:bg-slate-700 transition-all duration-200"
                        title="Copy to clipboard">
                        <span class="material-symbols-outlined text-[16px]">content_copy</span> Copy
                    </button>
                </div>

                <p class="text-body-sm text-surface-600 mt-4">If your VPS disables background processes (`exec`), you must also add this second command so the server automatically runs the queue worker every minute:</p>
                <div class="bg-slate-600 rounded-xl p-4 flex items-center justify-between gap-4">
                    <code class="text-emerald-400 text-sm font-mono break-all" id="workerCronCommand">* * * * * cd
                        {{ base_path() }} && php artisan queue:work --stop-when-empty --timeout=1800 --memory=1024 >> /dev/null 2>&1</code>
                    <button onclick="copyCronCommand('workerCronCommand')"
                        class="shrink-0 flex justify-center items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-700 text-blue text-label-sm hover:bg-slate-700 transition-all duration-200"
                        title="Copy to clipboard">
                        <span class="material-symbols-outlined text-[16px]">content_copy</span> Copy
                    </button>
                </div>

                <p class="text-xs text-surface-500 mt-1"><i
                        class="material-symbols-outlined text-[14px] align-middle mr-1">info</i> Note: Depending on your
                    server configuration, you may need to specify the full path to PHP (e.g., <code>/usr/bin/php</code> or
                    <code>/opt/alt/php82/usr/bin/php</code>).
                </p>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
    <script>
        const token = 'deploy123'; // Matches backend security token

        document.addEventListener('DOMContentLoaded', () => {
            updateWebCronBtn();
        });

        function updateWebCronBtn() {
            const btn = document.getElementById('btnToggleWebCron');
            const icon = document.getElementById('webCronIcon');
            const text = document.getElementById('webCronText');
            
            if (localStorage.getItem('enable_web_cron') === '1') {
                btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                btn.classList.add('bg-red-600', 'hover:bg-red-700');
                icon.innerText = 'toggle_on';
                text.innerText = 'Disable Web Cron';
            } else {
                btn.classList.add('bg-purple-600', 'hover:bg-purple-700');
                btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                icon.innerText = 'toggle_off';
                text.innerText = 'Enable Web Cron';
            }
        }

        function toggleWebCron() {
            if (localStorage.getItem('enable_web_cron') === '1') {
                localStorage.setItem('enable_web_cron', '0');
                showToast('Background Web Cron disabled.', 'info');
            } else {
                localStorage.setItem('enable_web_cron', '1');
                showToast('Background Web Cron enabled. Your browser will now process background syncs automatically.', 'success');
            }
            updateWebCronBtn();
        }

        async function runCommand(route) {
            const url = `${route}?token=${token}`;

            Swal.fire({
                title: 'Executing...',
                text: 'Please wait while the server processes the command.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: data.title,
                        html: `<pre class="text-left bg-surface-100 p-4 rounded-lg text-xs font-mono overflow-y-auto max-h-60 mt-3 border border-surface-200 text-surface-800 whitespace-pre-wrap break-words max-w-full">${data.message}</pre>`,
                        confirmButtonColor: '#4f46e5'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error Executing Command',
                        text: data.message || 'An unknown error occurred.',
                        confirmButtonColor: '#ef4444'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to communicate with the server. ' + error.message,
                    confirmButtonColor: '#ef4444'
                });
            }
        }

        function confirmMigrateFresh() {
            Swal.fire({
                title: 'DANGER: Are you absolutely sure?',
                text: "You are about to drop all tables and wipe all data! This cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, Destroy All Data'
            }).then((result) => {
                if (result.isConfirmed) {
                    runCommand('{{ route('sys.migrate-fresh') }}');
                }
            });
        }

        function copyCronCommand(elementId) {
            const cmd = document.getElementById(elementId).innerText;
            navigator.clipboard.writeText(cmd).then(() => {
                showToast('Cron command copied to clipboard!', 'success');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                showToast('Failed to copy command.', 'error');
            });
        }
    </script>
@endsection
