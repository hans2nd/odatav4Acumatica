<nav
    class="fixed left-0 top-0 h-full flex flex-col overflow-y-auto bg-surface-container-lowest border-r border-outline-variant w-64 z-20">
    <div class="p-6 border-b border-outline-variant">
        <h1 class="text-xl font-black text-on-surface tracking-tighter">PL Sync</h1>
        <p class="font-label-sm text-label-sm text-on-surface-variant mt-1">Acumatica to SQL Server</p>
    </div>
    <div class="flex flex-col py-4 flex-grow">
        @php
            $tabs = [
                ['route' => 'dashboard', 'icon' => 'dashboard', 'label' => 'Overview', 'key' => 'overview'],
                [
                    'route' => 'master-data.index',
                    'icon' => 'database',
                    'label' => 'Master Data',
                    'key' => 'master-data',
                ],
                [
                    'route' => 'transactions.index',
                    'icon' => 'swap_horiz',
                    'label' => 'Transactions',
                    'key' => 'transactions',
                ],
                ['route' => 'logs.index', 'icon' => 'receipt_long', 'label' => 'Logs', 'key' => 'logs'],
                ['route' => 'settings.index', 'icon' => 'settings', 'label' => 'Settings', 'key' => 'settings'],
            ];
        @endphp

        @foreach ($tabs as $tab)
            @php $isActive = ($activeTab ?? '') === $tab['key']; @endphp
            <a href="{{ route($tab['route']) }}"
                class="{{ $isActive
                    ? 'bg-surface-container-lowest text-on-surface border-r-4 border-on-surface font-bold'
                    : 'text-on-surface-variant hover:bg-surface-container-low font-medium' }}
                   px-6 py-3 flex items-center gap-3 transition-all active:scale-[0.98]">
                <span class="material-symbols-outlined"
                    @if ($isActive) style="font-variation-settings: 'FILL' 1;" @endif>{{ $tab['icon'] }}</span>
                <span class="font-body-md text-body-md">{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>
    <div class="p-6 border-t border-outline-variant">
        <button onclick="syncAll(this)"
            class="w-full bg-primary text-on-primary font-label-md text-label-md py-2 px-4 rounded hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-[0.98]">
            Force Sync All
        </button>
    </div>
</nav>
