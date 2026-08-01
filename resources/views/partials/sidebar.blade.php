<nav id="sidebar"
    class="fixed left-0 top-0 h-full flex flex-col overflow-y-auto w-[260px] z-20 transition-transform duration-300"
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
            $menuService = new \App\Services\MenuService();
            $menus = $menuService->getUserMenus($user);
        @endphp

        <ul class="flex flex-col px-3 space-y-1 mt-2">
            @foreach ($menus as $menu)
                @if (isset($menu['submenus']))
                    @php
                        $hasActiveSubmenu = false;
                        foreach ($menu['submenus'] as $submenu) {
                            if (isset($submenu['submenus'])) {
                                foreach ($submenu['submenus'] as $nested) {
                                    $routeUrl = isset($nested['route_param']) ? route($nested['route'], $nested['route_param']) : route($nested['route']);
                                    if (request()->url() === $routeUrl) { $hasActiveSubmenu = true; break 2; }
                                }
                            } else {
                                $routeUrl = isset($submenu['route_param']) ? route($submenu['route'], $submenu['route_param']) : route($submenu['route']);
                                if (request()->url() === $routeUrl) { $hasActiveSubmenu = true; break; }
                            }
                        }
                    @endphp
                    <li class="nav-group" x-data="{ expanded: {{ $hasActiveSubmenu ? 'true' : 'false' }} }">
                        <button @click="expanded = !expanded"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all group focus:outline-none {{ $hasActiveSubmenu ? 'text-white' : 'text-slate-300 hover:text-white hover:bg-white/[0.04]' }}">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[20px] {{ $hasActiveSubmenu ? 'text-brand-400' : 'text-slate-400 group-hover:text-brand-400' }} transition-colors"
                                    style="font-variation-settings: 'FILL' 1;">{{ $menu['icon'] }}</span>
                                <span class="text-[14px] font-medium tracking-wide">{{ $menu['label'] }}</span>
                            </div>
                            <span class="material-symbols-outlined text-[18px] transition-transform duration-300"
                                :class="expanded ? 'rotate-90 text-brand-400' : 'text-slate-500'">chevron_right</span>
                        </button>
                        
                        <ul x-show="expanded" x-collapse x-cloak class="pl-[42px] pr-3 py-1 space-y-1 overflow-hidden">
                            @foreach ($menu['submenus'] as $submenu)
                                @if(isset($submenu['submenus']))
                                    {{-- Nested Menu --}}
                                    @php
                                        $hasActiveNested = false;
                                        foreach ($submenu['submenus'] as $nested) {
                                            $routeUrl = isset($nested['route_param']) 
                                                        ? route($nested['route'], $nested['route_param']) 
                                                        : route($nested['route']);
                                            if (request()->url() === $routeUrl) {
                                                $hasActiveNested = true;
                                                break;
                                            }
                                        }
                                    @endphp
                                    <li class="nav-group" x-data="{ nestedExpanded: {{ $hasActiveNested ? 'true' : 'false' }} }">
                                        <button @click="nestedExpanded = !nestedExpanded"
                                            class="w-full flex items-center justify-between px-1 py-1.5 mt-1 rounded-lg transition-all focus:outline-none {{ $hasActiveNested ? 'text-white font-medium' : 'text-slate-400 hover:text-white hover:bg-white/[0.04]' }}">
                                            <div class="flex items-center gap-2">
                                                @if(isset($submenu['icon']))
                                                <span class="material-symbols-outlined text-[16px]">{{ $submenu['icon'] }}</span>
                                                @endif
                                                <span class="text-[13px] tracking-wide">{{ $submenu['label'] }}</span>
                                            </div>
                                            <span class="material-symbols-outlined text-[16px] transition-transform duration-300"
                                                :class="nestedExpanded ? 'rotate-90 text-brand-400' : 'text-slate-500'">chevron_right</span>
                                        </button>
                                        <ul x-show="nestedExpanded" x-collapse x-cloak class="pl-[24px] pr-2 py-1 space-y-1 overflow-hidden border-l border-white/[0.05] ml-2 mt-1 mb-2">
                                            @foreach ($submenu['submenus'] as $nested)
                                                @php
                                                    $routeUrl = isset($nested['route_param']) 
                                                                ? route($nested['route'], $nested['route_param']) 
                                                                : route($nested['route']);
                                                    $isActive = request()->url() === $routeUrl;
                                                @endphp
                                                <li>
                                                    <a href="{{ $routeUrl }}"
                                                        class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-[12.5px] transition-all {{ $isActive ? 'text-white bg-brand-500/10 font-semibold' : 'text-slate-500 hover:text-white hover:bg-white/[0.04]' }}">
                                                        @if($isActive)
                                                            <div class="w-1 h-1 rounded-full bg-brand-400 absolute left-[30px]"></div>
                                                        @endif
                                                        <span class="tracking-wide">{{ $nested['label'] }}</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @else
                                    {{-- Standard Submenu --}}
                                    @php
                                        $routeUrl = isset($submenu['route_param']) 
                                                    ? route($submenu['route'], $submenu['route_param']) 
                                                    : route($submenu['route']);
                                        $isActive = request()->url() === $routeUrl;
                                    @endphp
                                    <li>
                                        <a href="{{ $routeUrl }}"
                                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-all {{ $isActive ? 'text-white bg-brand-500/10 font-semibold shadow-inner' : 'text-slate-400 hover:text-white hover:bg-white/[0.04]' }}">
                                            @if($isActive)
                                                <div class="w-1.5 h-1.5 rounded-full bg-brand-400 absolute left-[30px]"></div>
                                            @endif
                                            <span class="tracking-wide">{{ $submenu['label'] }}</span>
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </li>
                @else
                    @php
                        $routeUrl = route($menu['route']);
                        $routePath = parse_url($routeUrl, PHP_URL_PATH);
                        $isActive = request()->url() === $routeUrl || (strlen($routePath) > 1 && request()->is(ltrim($routePath, '/') . '*'));
                    @endphp
                    <li>
                        <a href="{{ $routeUrl }}"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all {{ $isActive ? 'text-white bg-gradient-to-r from-brand-600/20 to-transparent font-semibold border-l-2 border-brand-500' : 'text-slate-300 hover:text-white hover:bg-white/[0.04]' }}">
                            <span class="material-symbols-outlined text-[20px] {{ $isActive ? 'text-brand-400' : 'text-slate-400 group-hover:text-brand-400' }} transition-colors"
                                style="font-variation-settings: 'FILL' 1;">{{ $menu['icon'] }}</span>
                            <span class="text-[14px] font-medium tracking-wide">{{ $menu['label'] }}</span>
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>

    {{-- User Profile Area --}}
    <div class="p-4 mt-auto border-t border-white/[0.06] bg-slate-900/50">
        <form method="POST" action="{{ route('logout') }}" id="logout-form" onsubmit="localStorage.clear(); sessionStorage.clear();">
            @csrf
            <button type="submit"
                class="w-full flex items-center justify-between px-4 py-3 rounded-xl bg-slate-800/50 border border-white/[0.05] hover:bg-slate-800 hover:border-white/[0.1] transition-all group">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-brand-600/20 flex items-center justify-center border border-brand-500/30">
                        <span class="material-symbols-outlined text-[16px] text-brand-400">person</span>
                    </div>
                    <div class="text-left flex flex-col justify-center">
                        <span class="text-[13px] font-semibold text-white leading-tight block w-24 truncate">{{ auth()->user()->name }}</span>
                        <span class="text-[11px] text-brand-400 mt-0.5">{{ auth()->user()->primary_role }}</span>
                    </div>
                </div>
                <span class="material-symbols-outlined text-[18px] text-slate-500 group-hover:text-red-400 transition-colors">logout</span>
            </button>
        </form>
    </div>
</nav>