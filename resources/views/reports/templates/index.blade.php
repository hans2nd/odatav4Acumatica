@extends('layouts.app')

@section('title', 'Manage Report Templates')
@section('page-title', 'Report Templates')

@section('content')
<div class="max-w-7xl mx-auto space-y-8 animate-fade-up">

    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 bg-white p-6 rounded-2xl border border-surface-200/80 shadow-sm relative overflow-hidden">
        <div class="absolute right-0 top-0 w-64 h-64 bg-gradient-to-br from-brand-100 to-transparent rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 opacity-60"></div>
        
        <div class="flex items-center gap-5 relative z-10">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center shadow-lg shadow-brand-500/20 shrink-0">
                <span class="material-symbols-outlined text-white text-[28px]" style="font-variation-settings: 'FILL' 1;">bookmarks</span>
            </div>
            <div>
                <h2 class="text-h2 text-surface-900 tracking-tight">Report Templates</h2>
                <p class="text-body-md text-surface-500 mt-1 max-w-xl">Manage your saved configurations. Access them quickly from the Report Builder to skip the setup process.</p>
            </div>
        </div>
        
        <div class="relative z-10 shrink-0">
            <a href="{{ route('report.builder') }}" class="group relative inline-flex items-center gap-2 px-6 py-3 bg-brand-600 text-white rounded-xl overflow-hidden transition-all hover:bg-brand-700 shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:shadow-sm">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-out"></div>
                <span class="material-symbols-outlined text-[20px] relative z-10 transition-transform duration-300 group-hover:rotate-90">add</span>
                <span class="font-medium text-label-md relative z-10">New Template</span>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-xl bg-green-50 border border-green-200 flex items-start gap-3 animate-fade-in shadow-sm">
            <span class="material-symbols-outlined text-green-600 mt-0.5" style="font-variation-settings: 'FILL' 1;">check_circle</span>
            <div>
                <p class="text-label-md font-semibold text-green-800">Success</p>
                <p class="text-body-sm text-green-700 mt-0.5">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    {{-- Templates Grid / List --}}
    <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm overflow-hidden">
        @if($templates->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6 bg-surface-50/50">
                @foreach($templates as $template)
                    <div class="group relative flex flex-col bg-white border border-surface-200 rounded-2xl p-5 transition-all duration-300 hover:shadow-xl hover:border-brand-300 hover:-translate-y-1">
                        
                        {{-- Top Section: Name & Actions --}}
                        <div class="flex items-start justify-between mb-4 gap-4">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-h4 text-surface-900 font-semibold truncate group-hover:text-brand-700 transition-colors" title="{{ $template->name }}">{{ $template->name }}</h3>
                                <p class="text-[11px] font-medium text-surface-400 mt-1 uppercase tracking-wider flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">schedule</span>
                                    {{ $template->created_at->diffForHumans() }}
                                </p>
                            </div>
                            
                            <div class="flex items-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 absolute top-4 right-4 bg-white/90 backdrop-blur-sm rounded-lg shadow-sm border border-surface-100 p-1">
                                <a href="{{ route('report.builder', ['template' => $template->id]) }}" class="w-8 h-8 flex items-center justify-center text-surface-500 hover:text-amber-600 hover:bg-amber-50 rounded-md transition-colors" title="Edit Template Configuration">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <div class="w-px h-4 bg-surface-200 mx-1"></div>
                                <form action="{{ route('report-templates.destroy', $template->id) }}" method="POST" onsubmit="return confirm('Delete this template permanently?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-8 h-8 flex items-center justify-center text-surface-500 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors" title="Delete Template">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
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
                @endforeach
            </div>
        @else
            <div class="py-24 flex flex-col items-center justify-center text-center px-4 bg-surface-50/50">
                <div class="w-24 h-24 bg-brand-50 rounded-full flex items-center justify-center mb-6 shadow-inner relative">
                    <div class="absolute inset-0 bg-brand-100 rounded-full animate-ping opacity-20"></div>
                    <span class="material-symbols-outlined text-[48px] text-brand-500 relative z-10" style="font-variation-settings: 'FILL' 1;">inventory_2</span>
                </div>
                <h3 class="text-h3 text-surface-900 mb-2">No Templates Found</h3>
                <p class="text-body-md text-surface-500 max-w-md mx-auto mb-8">You haven't saved any report templates yet. Go to the Report Builder to create your first configuration.</p>
                <a href="{{ route('report.builder') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all shadow-md hover:shadow-lg font-medium">
                    <span class="material-symbols-outlined text-[20px]">play_arrow</span>
                    Start Building
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
