<nav id="sidebar"
    class="fixed left-0 top-0 h-full flex flex-col overflow-y-auto bg-surface-container-lowest border-r border-outline-variant w-64 z-20 transition-transform duration-300 transform translate-x-0">
    <div class="p-6 border-b border-outline-variant">
        <h1 class="text-xl font-black text-on-surface tracking-tighter">PL Sync</h1>
        <p class="font-label-sm text-label-sm text-on-surface-variant mt-1">Acumatica to SQL Server</p>
    </div>
    <div class="flex flex-col py-4 flex-grow">
        @php
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
                    'submenus' => [
                        ['route' => 'report.sales-order', 'label' => 'Sales Order', 'key' => 'report.sales-order'],
                    ],
                ],
                [
                    'label' => 'ODataV4 Sync',
                    'icon' => 'sync_alt',
                    'key' => 'odata',
                    'submenus' => [
                        ['route' => 'master-data.index', 'label' => 'Master Data', 'key' => 'master-data'],
                        ['route' => 'transactions.index', 'label' => 'Transactions', 'key' => 'transactions'],
                        ['route' => 'logs.index', 'label' => 'Logs', 'key' => 'logs'],
                        ['route' => 'settings.index', 'label' => 'Settings', 'key' => 'settings'],
                    ],
                ],
            ];
            $currentTab = $activeTab ?? '';
        @endphp

        @foreach ($menus as $menu)
            @if (isset($menu['submenus']))
                @php
                    $isActiveGroup = collect($menu['submenus'])->contains('key', $currentTab);
                @endphp
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
                        class="overflow-hidden transition-all duration-300 {{ $isActiveGroup ? 'max-h-96' : 'max-h-0' }}">
                        <div class="flex flex-col bg-surface-container-low py-2">
                            @foreach ($menu['submenus'] as $sub)
                                @php $isActive = $currentTab === $sub['key']; @endphp
                                <a href="{{ route($sub['route']) }}"
                                    class="{{ $isActive ? 'text-primary font-bold border-r-4 border-primary bg-surface' : 'text-on-surface-variant hover:text-on-surface' }}
                                    pl-14 pr-6 py-2.5 flex items-center gap-3 transition-colors text-body-md">
                                    {{ $sub['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
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
    <div class="p-6 border-t border-outline-variant">
        <button onclick="syncAll(this)"
            class="w-full bg-primary text-on-primary font-label-md text-label-md py-2 px-4 rounded hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-[0.98]">
            Force Sync All
        </button>
    </div>
</nav>

<script>
    function toggleSubmenu(id, btn) {
        const el = document.getElementById(id);
        const icon = btn.querySelector('.material-symbols-outlined:last-child');

        if (el.classList.contains('max-h-0')) {
            el.classList.remove('max-h-0');
            el.classList.add('max-h-96');
            icon.classList.add('rotate-180');
        } else {
            el.classList.add('max-h-0');
            el.classList.remove('max-h-96');
            icon.classList.remove('rotate-180');
        }
    }
</script>
