<nav id="sidebar"
    class="fixed left-0 top-0 h-full flex flex-col overflow-y-auto bg-surface-container-lowest border-r border-outline-variant w-64 z-20 transition-transform duration-300 transform translate-x-0">
    <div class="p-6 border-b border-outline-variant">
        <h1 class="text-xl font-black text-on-surface tracking-tighter">PL Sync</h1>
        <p class="font-label-sm text-label-sm text-on-surface-variant mt-1">Acumatica to SQL Server</p>
    </div>
    <div class="flex flex-col py-4 flex-grow">
        @php
            $user = auth()->user();

            // Build dynamic report sub-menus from synced transaction entities
            $reportSubmenus = [
                ['route' => 'report.sales-order', 'label' => 'Sales Order', 'key' => 'report.sales-order'],
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
                    'submenus' => [
                        ['route' => 'master-data.index', 'label' => 'Master Data', 'key' => 'master-data'],
                        ['route' => 'transactions.index', 'label' => 'Transactions', 'key' => 'transactions'],
                        ['route' => 'logs.index', 'label' => 'Logs', 'key' => 'logs'],
                        [
                            'route' => 'settings.index',
                            'label' => 'Settings',
                            'key' => 'settings',
                            'permission' => 'settings.manage',
                        ],
                    ],
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

        @foreach ($menus as $menu)
            {{-- Check top-level permission --}}
            @if (isset($menu['permission']) && $user && !$user->hasPermission($menu['permission']))
                @continue
            @endif

            @if (isset($menu['submenus']))
                @php
                    // Filter submenus by permission
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
                            class="{{ $isActiveGroup ? 'text-primary' : 'text-on-surface-variant' }} hover:bg-surface-container-low px-6 py-3 flex items-center justify-between transition-all w-full text-left font-medium">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined"
                                    @if ($isActiveGroup) style="font-variation-settings: 'FILL' 1;" @endif>{{ $menu['icon'] }}</span>
                                <span class="font-body-md text-body-md">{{ $menu['label'] }}</span>
                            </div>
                            <span
                                class="material-symbols-outlined transition-transform duration-200 {{ $isActiveGroup ? 'rotate-180' : '' }}">expand_more</span>
                        </button>
                        <div id="submenu-{{ $menu['key'] }}"
                            class="overflow-hidden transition-all duration-300 {{ $isActiveGroup ? 'max-h-[600px]' : 'max-h-0' }}">
                            <div class="flex flex-col bg-surface-container-low py-2">
                                @foreach ($visibleSubmenus as $sub)
                                    @php
                                        $isActive = $currentTab === $sub['key'];
                                        $href = isset($sub['route_param'])
                                            ? route($sub['route'], $sub['route_param'])
                                            : route($sub['route']);
                                    @endphp
                                    <a href="{{ $href }}"
                                        class="{{ $isActive ? 'text-primary font-bold border-r-4 border-primary bg-surface' : 'text-on-surface-variant hover:text-on-surface' }}
                                    pl-14 pr-6 py-2.5 flex items-center gap-3 transition-colors text-body-md">
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
                        ? 'bg-surface-container-lowest text-on-surface border-r-4 border-on-surface font-bold'
                        : 'text-on-surface-variant hover:bg-surface-container-low font-medium' }}
                       px-6 py-3 flex items-center gap-3 transition-all active:scale-[0.98]">
                    <span class="material-symbols-outlined"
                        @if ($isActive) style="font-variation-settings: 'FILL' 1;" @endif>{{ $menu['icon'] }}</span>
                    <span class="font-body-md text-body-md">{{ $menu['label'] }}</span>
                </a>
            @endif
        @endforeach
    </div>

    {{-- Sync All Button --}}
    <div class="px-6 py-3">
        <button onclick="syncAll(this)"
            class="w-full bg-primary text-on-primary font-label-md text-label-md py-2 px-4 rounded hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-[0.98]">
            Force Sync All
        </button>
    </div>

    {{-- User Info & Logout --}}
    @auth
        <div class="p-4 border-t border-outline-variant">
            <div class="flex items-center gap-3">
                <div
                    class="w-9 h-9 rounded-full bg-primary-container flex items-center justify-center text-on-primary-container font-bold text-sm shrink-0">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-on-surface truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-on-surface-variant truncate">{{ auth()->user()->primary_role }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit"
                        class="p-1.5 rounded hover:bg-surface-container-low text-on-surface-variant transition-colors"
                        title="Logout">
                        <span class="material-symbols-outlined text-lg">logout</span>
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
