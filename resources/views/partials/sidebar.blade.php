<nav id="sidebar"
    class="fixed left-0 top-0 h-full flex flex-col overflow-y-auto w-[260px] z-20 transition-transform duration-300 transform translate-x-0"
    style="background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);">

    {{-- Logo Area --}}
    <div class="px-6 py-5 border-b border-white/[0.06]">
        <div class="flex items-center gap-3">
            <div
                class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center shadow-lg shadow-brand-500/20">
                <span class="material-symbols-outlined text-white text-[20px]"
                    style="font-variation-settings: 'FILL' 1;">sync</span>
            </div>
            <div>
                <h1 class="text-[15px] font-bold text-white tracking-tight">PL Sync</h1>
                <p class="text-[10px] text-slate-400 font-medium tracking-wider uppercase">Data Warehouse</p>
            </div>
        </div>
    </div>

    {{-- Navigation --}}
    <div class="flex flex-col py-3 flex-grow">
        @php
            $user = auth()->user();

            $hasMasterDataView = collect(config('acumatica.entities', []))->contains(function ($e, $key) use ($user) {
                return $e['type'] === 'master' && $user && $user->hasPermission("{$key}.view");
            });
            $hasTransactionsView = collect(config('acumatica.entities', []))->contains(function ($e, $key) use ($user) {
                return $e['type'] === 'transaction' && $user && $user->hasPermission("{$key}.view");
            });

            // Build dynamic report sub-menus from synced transaction entities
            $reportSubmenus = [
                ['route' => 'report.builder', 'label' => 'Report Builder', 'key' => 'report.builder', 'permission' => 'report_builder.manage'],
                ['route' => 'endpoint.api.index', 'label' => 'Endpoint API', 'key' => 'endpoint.api', 'permission' => 'endpoint_api.manage'],
                // ['route' => 'report.sales-order', 'label' => 'Sales Order', 'key' => 'report.sales-order'],
            ];
            $syncedTransactions = \App\Http\Controllers\DynamicReportController::getSyncedTransactionEntities();
            foreach ($syncedTransactions as $eKey => $eConfig) {
                $reportSubmenus[] = [
                    'route' => 'report.dynamic.show',
                    'route_param' => $eKey,
                    'label' => $eConfig['description'],
                    'key' => 'report.' . $eKey,
                ];
            }

            $menus = [
                [
                    'route' => 'dashboard',
                    'icon' => 'dashboard',
                    'label' => 'Overview',
                    'key' => 'overview',
                ],
                [
                    'label' => 'Report',
                    'icon' => 'analytics',
                    'key' => 'report',
                    'permission' => 'reports.view',
                    'submenus' => $reportSubmenus,
                ],
                [
                    'label' => 'ODataV4 Sync',
                    'icon' => 'sync_alt',
                    'key' => 'odata',
                    'submenus' => array_filter([
                        $hasMasterDataView ? ['route' => 'master-data.index', 'label' => 'Master Data', 'key' => 'master-data'] : null,
                        $hasTransactionsView ? ['route' => 'transactions.index', 'label' => 'Transactions', 'key' => 'transactions'] : null,
                        ['route' => 'logs.index', 'label' => 'Logs', 'key' => 'logs', 'permission' => 'logs.view'],
                        [
                            'route' => 'settings.index',
                            'label' => 'Settings',
                            'key' => 'settings',
                            'permission' => 'settings.manage',
                        ],
                    ]),
                ],
                [
                    'route' => 'users.index',
                    'icon' => 'manage_accounts',
                    'label' => 'User Management',
                    'key' => 'users',
                    'permission' => 'users.manage',
                ],
            ];
            $currentTab = $activeTab ?? '';
        @endphp

        {{-- Section label --}}
        <div class="px-6 pt-3 pb-2">
            <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-[0.08em]">Navigation</span>
        </div>

        @foreach ($menus as $menu)
            {{-- Check top-level permission --}}
            @if (isset($menu['permission']) && $user && !$user->hasPermission($menu['permission']))
                @continue
            @endif

            @if (isset($menu['submenus']))
                @php
                    $visibleSubmenus = collect($menu['submenus'])
                        ->filter(function ($sub) use ($user) {
                            if (!isset($sub['permission'])) {
                                return true;
                            }
                            return $user && $user->hasPermission($sub['permission']);
                        })
                        ->values()
                        ->all();

                    $isActiveGroup = collect($visibleSubmenus)->contains('key', $currentTab);
                @endphp

                @if (count($visibleSubmenus) > 0)
                    <div class="flex flex-col">
                        <button onclick="toggleSubmenu('submenu-{{ $menu['key'] }}', this)"
                            class="{{ $isActiveGroup ? 'text-white' : 'text-slate-400 hover:text-slate-200' }} hover:bg-white/[0.04] px-6 py-2.5 flex items-center justify-between transition-all duration-200 w-full text-left group">
                            <div class="flex items-center gap-3">
                                <span
                                    class="material-symbols-outlined text-[20px] {{ $isActiveGroup ? 'text-brand-400' : 'text-slate-500 group-hover:text-slate-400' }}"
                                    @if ($isActiveGroup) style="font-variation-settings: 'FILL' 1;" @endif>{{ $menu['icon'] }}</span>
                                <span class="text-body-sm font-medium">{{ $menu['label'] }}</span>
                            </div>
                            <span
                                class="material-symbols-outlined text-[16px] transition-transform duration-200 text-slate-500 {{ $isActiveGroup ? 'rotate-180' : '' }}">expand_more</span>
                        </button>
                        <div id="submenu-{{ $menu['key'] }}"
                            class="overflow-hidden transition-all duration-300 {{ $isActiveGroup ? 'max-h-[600px]' : 'max-h-0' }}">
                            <div class="flex flex-col py-1">
                                @foreach ($visibleSubmenus as $sub)
                                    @php
                                        $isActive = $currentTab === $sub['key'];
                                        $href = isset($sub['route_param'])
                                            ? route($sub['route'], $sub['route_param'])
                                            : route($sub['route']);
                                    @endphp
                                    <a href="{{ $href }}"
                                        class="{{ $isActive
                                            ? 'text-white bg-white/[0.08] border-r-2 border-brand-400'
                                            : 'text-slate-400 hover:text-slate-200 hover:bg-white/[0.04]' }}
                                        pl-[52px] pr-6 py-2 flex items-center gap-2.5 transition-all duration-200 text-body-sm relative">
                                        @if ($isActive)
                                            <div
                                                class="absolute left-[40px] top-1/2 -translate-y-1/2 w-1 h-1 rounded-full bg-brand-400">
                                            </div>
                                        @endif
                                        {{ $sub['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            @else
                @php $isActive = $currentTab === $menu['key']; @endphp
                <a href="{{ route($menu['route']) }}"
                    class="{{ $isActive
                        ? 'bg-white/[0.08] text-white border-r-2 border-brand-400'
                        : 'text-slate-400 hover:text-slate-200 hover:bg-white/[0.04]' }}
                       px-6 py-2.5 flex items-center gap-3 transition-all duration-200 active:scale-[0.98] group">
                    <span
                        class="material-symbols-outlined text-[20px] {{ $isActive ? 'text-brand-400' : 'text-slate-500 group-hover:text-slate-400' }}"
                        @if ($isActive) style="font-variation-settings: 'FILL' 1;" @endif>{{ $menu['icon'] }}</span>
                    <span class="text-body-sm font-medium">{{ $menu['label'] }}</span>
                </a>
            @endif
        @endforeach
    </div>

    {{-- Sync All Button --}}
    @if(auth()->check() && auth()->user()->hasPermission('sync.manage'))
    <div class="px-5 py-3">
        <button onclick="syncAll(this)"
            class="w-full bg-gradient-to-r from-brand-600 to-brand-500 text-white text-label-md py-2.5 px-4 rounded-xl hover:from-brand-500 hover:to-brand-400 transition-all duration-200 active:scale-[0.97] flex items-center justify-center gap-2 shadow-lg shadow-brand-500/20">
            <span class="material-symbols-outlined text-[16px]">sync</span>
            Force Sync All
        </button>
    </div>
    @endif

    {{-- User Info & Logout --}}
    @auth
        <div class="p-4 border-t border-white/[0.06] mx-3 mb-2">
            <div class="flex items-center gap-3">
                <div
                    class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-bold text-sm shrink-0 shadow-sm">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-200 truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[10px] text-slate-500 truncate uppercase tracking-wider font-medium">
                        {{ auth()->user()->primary_role }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit"
                        class="p-1.5 rounded-lg hover:bg-white/[0.06] text-slate-500 hover:text-slate-300 transition-all duration-200"
                        title="Logout">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                    </button>
                </form>
            </div>
        </div>
    @endauth
</nav>

<script>
    function toggleSubmenu(id, btn) {
        const el = document.getElementById(id);
        const icon = btn.querySelector('.material-symbols-outlined:last-child');

        if (el.classList.contains('max-h-0')) {
            el.classList.remove('max-h-0');
            el.classList.add('max-h-[600px]');
            icon.classList.add('rotate-180');
        } else {
            el.classList.add('max-h-0');
            el.classList.remove('max-h-[600px]');
            icon.classList.remove('rotate-180');
        }
    }
</script>
