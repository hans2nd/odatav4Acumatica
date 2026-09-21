<div class="group relative flex flex-col bg-white border border-surface-200 rounded-2xl p-5 transition-all duration-300 hover:shadow-xl hover:border-brand-300 hover:-translate-y-1">
    
    {{-- Top Section: Name & Actions --}}
    <div class="flex items-start justify-between mb-4 gap-4">
        <div class="flex-1 min-w-0">
            <h3 class="text-h4 text-surface-900 font-semibold truncate group-hover:text-brand-700 transition-colors" title="{{ $template->name }}">{{ $template->name }}</h3>
            <p class="text-[11px] font-medium text-surface-400 mt-1 uppercase tracking-wider flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">schedule</span>
                {{ $template->created_at->diffForHumans() }}
                @if(!$isOwner)
                <span class="ml-2 inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-[9px] font-bold">
                    <span class="material-symbols-outlined text-[10px]">group</span> SHARED
                </span>
                @endif
            </p>
        </div>
        
        <div class="flex items-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 absolute top-4 right-4 bg-white/90 backdrop-blur-sm rounded-lg shadow-sm border border-surface-100 p-1">
            <a href="{{ route('report.builder', ['template' => $template->id]) }}" class="w-8 h-8 flex items-center justify-center text-surface-500 hover:text-amber-600 hover:bg-amber-50 rounded-md transition-colors" title="Use Template Configuration">
                <span class="material-symbols-outlined text-[18px]">edit</span>
            </a>
            
            @if($isOwner)
            <div class="w-px h-4 bg-surface-200 mx-1"></div>
            <button type="button" onclick="openShareModal({{ $template->id }})" class="w-8 h-8 flex items-center justify-center text-surface-500 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors" title="Share Template">
                <span class="material-symbols-outlined text-[18px]">share</span>
            </button>
            <div class="w-px h-4 bg-surface-200 mx-1"></div>
            <form action="{{ route('report-templates.destroy', $template->id) }}" method="POST" onsubmit="return confirm('Delete this template permanently?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-8 h-8 flex items-center justify-center text-surface-500 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors" title="Delete Template">
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Details Section --}}
    <div class="space-y-3 mt-auto">
        <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 border border-surface-100 group-hover:bg-brand-50/30 group-hover:border-brand-100 transition-colors">
            <div class="w-8 h-8 rounded-lg bg-white border border-surface-200 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[16px] text-surface-600">database</span>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] uppercase font-bold tracking-wider text-surface-400">Connection</p>
                <p class="text-body-sm font-medium text-surface-800 truncate font-mono mt-0.5">{{ $template->connection_key }}</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 border border-surface-100 group-hover:bg-brand-50/30 group-hover:border-brand-100 transition-colors">
            <div class="w-8 h-8 rounded-lg bg-white border border-surface-200 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[16px] text-surface-600">table_rows</span>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] uppercase font-bold tracking-wider text-surface-400">Table</p>
                <p class="text-body-sm font-medium text-surface-800 truncate font-mono mt-0.5">{{ $template->table_name }}</p>
            </div>
        </div>
        
        @php
            $config = $template->configuration;
            if (is_string($config)) { $config = json_decode($config, true) ?: []; }
            $colCount = count($config['columns'] ?? []);
            $filterCount = count($config['filters'] ?? []);
        @endphp
        
        <div class="flex items-center justify-between pt-4 mt-2 border-t border-surface-100">
            <div class="flex items-center gap-1.5 text-surface-500" title="{{ $colCount }} Columns Selected">
                <span class="material-symbols-outlined text-[16px]">view_column</span>
                <span class="text-xs font-bold">{{ $colCount }}</span> <span class="text-xs">Cols</span>
            </div>
            <div class="flex items-center gap-1.5 text-surface-500" title="{{ $filterCount }} Filters Applied">
                <span class="material-symbols-outlined text-[16px]">filter_alt</span>
                <span class="text-xs font-bold">{{ $filterCount }}</span> <span class="text-xs">Filters</span>
            </div>
        </div>
    </div>
</div>
