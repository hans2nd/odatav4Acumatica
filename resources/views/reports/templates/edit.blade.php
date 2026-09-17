@extends('layouts.app')

@section('title', 'Edit Report Template')
@section('page-title', 'Edit Report Template')

@section('content')
<div class="max-w-5xl mx-auto space-y-8 animate-fade-up">

    {{-- Header Section --}}
    <div class="flex items-center justify-between bg-white p-6 rounded-2xl border border-surface-200/80 shadow-sm relative overflow-hidden">
        <div class="absolute left-0 top-0 w-64 h-64 bg-gradient-to-br from-brand-100 to-transparent rounded-full blur-3xl -translate-y-1/2 -translate-x-1/2 opacity-60"></div>
        
        <div class="flex items-center gap-4 relative z-10">
            <a href="{{ route('report-templates.index') }}" class="w-12 h-12 flex items-center justify-center text-surface-500 hover:text-brand-600 bg-surface-50 hover:bg-brand-50 rounded-xl transition-colors border border-surface-200" title="Back to Templates">
                <span class="material-symbols-outlined text-[24px]">arrow_back</span>
            </a>
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2 py-0.5 rounded-md bg-brand-100 text-brand-700 text-[10px] font-bold uppercase tracking-wider">Template</span>
                    <h2 class="text-h3 text-surface-900 tracking-tight leading-none">{{ $template->name }}</h2>
                </div>
                <p class="text-body-sm text-surface-500">View and update your saved report configuration.</p>
            </div>
        </div>
    </div>

    <form action="{{ route('report-templates.update', $template->id) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            {{-- Left Column: Form & Basics --}}
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm p-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-bl from-amber-100 to-transparent rounded-full blur-2xl opacity-50"></div>
                    
                    <h3 class="text-label-lg font-semibold text-surface-900 mb-5 flex items-center gap-2 relative z-10">
                        <span class="material-symbols-outlined text-amber-500" style="font-variation-settings: 'FILL' 1;">edit_note</span>
                        General Settings
                    </h3>
                    
                    <div class="space-y-5 relative z-10">
                        <div>
                            <label class="block text-label-sm font-medium text-surface-700 mb-1.5">Template Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name', $template->name) }}" required
                                class="w-full bg-surface-50/50 border border-surface-300 rounded-xl px-4 py-2.5 text-body-md text-surface-900 focus:outline-none focus:ring-4 focus:ring-brand-500/10 focus:border-brand-500 transition-all hover:border-surface-400">
                            @error('name')
                                <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-surface-400 mt-2">Give this template a descriptive name to easily identify it later.</p>
                        </div>
                    </div>
                </div>
                
                {{-- Source Overview --}}
                <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm p-6">
                    <h3 class="text-label-lg font-semibold text-surface-900 mb-5 flex items-center gap-2">
                        <span class="material-symbols-outlined text-brand-500" style="font-variation-settings: 'FILL' 1;">database</span>
                        Data Source
                    </h3>
                    
                    <div class="space-y-4">
                        <div class="p-4 bg-surface-50 rounded-xl border border-surface-100 flex items-start gap-3">
                            <div class="mt-0.5"><span class="material-symbols-outlined text-[20px] text-surface-400">cable</span></div>
                            <div>
                                <span class="text-[10px] text-surface-500 font-bold uppercase tracking-wider block mb-0.5">Connection</span>
                                <span class="text-body-md font-medium text-surface-900 font-mono">{{ $template->connection_key }}</span>
                            </div>
                        </div>
                        <div class="p-4 bg-surface-50 rounded-xl border border-surface-100 flex items-start gap-3">
                            <div class="mt-0.5"><span class="material-symbols-outlined text-[20px] text-surface-400">table_chart</span></div>
                            <div>
                                <span class="text-[10px] text-surface-500 font-bold uppercase tracking-wider block mb-0.5">Table</span>
                                <span class="text-body-md font-medium text-surface-900 font-mono">{{ $template->table_name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Config Summary --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-surface-200/80 shadow-sm h-full flex flex-col">
                    <div class="p-6 border-b border-surface-100 bg-surface-50/50 rounded-t-2xl flex items-center justify-between">
                        <h3 class="text-label-lg font-semibold text-surface-900 flex items-center gap-2">
                            <span class="material-symbols-outlined text-blue-500" style="font-variation-settings: 'FILL' 1;">tune</span>
                            Configuration Summary
                        </h3>
                        <span class="px-3 py-1 bg-surface-200 text-surface-600 rounded-full text-xs font-medium border border-surface-300 shadow-sm">Read Only</span>
                    </div>
                    
                    @php
                        $config = $template->configuration;
                        if (is_string($config)) {
                            $config = json_decode($config, true) ?: [];
                        }
                        $columns = $config['columns'] ?? [];
                        $filters = $config['filters'] ?? [];
                    @endphp
                    
                    <div class="p-6 space-y-8 flex-1">
                        {{-- Columns --}}
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-label-md font-semibold text-surface-800 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[18px] text-surface-400">view_column</span>
                                    Selected Columns
                                </span>
                                <span class="text-xs font-bold text-brand-600 bg-brand-50 px-2 py-0.5 rounded-md">{{ count($columns) }}</span>
                            </div>
                            
                            <div class="p-5 rounded-xl border border-surface-200 bg-white shadow-sm">
                                <div class="flex flex-wrap gap-2.5">
                                    @forelse($columns as $col)
                                        <div class="group flex items-center gap-1.5 px-3 py-1.5 bg-surface-50 hover:bg-brand-50 text-surface-700 hover:text-brand-700 border border-surface-200 hover:border-brand-200 rounded-lg transition-colors cursor-default">
                                            <span class="material-symbols-outlined text-[14px] text-surface-400 group-hover:text-brand-400">data_object</span>
                                            <span class="text-sm font-medium font-mono">{{ $col }}</span>
                                        </div>
                                    @empty
                                        <div class="w-full py-4 text-center">
                                            <span class="text-sm text-surface-400 italic">No columns were selected in this template.</span>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        
                        {{-- Filters --}}
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-label-md font-semibold text-surface-800 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[18px] text-surface-400">filter_alt</span>
                                    Applied Filters
                                </span>
                                <span class="text-xs font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md">{{ count($filters) }}</span>
                            </div>
                            
                            @if(count($filters) > 0)
                                <div class="rounded-xl border border-surface-200 bg-white shadow-sm overflow-hidden">
                                    <table class="w-full text-left text-sm">
                                        <thead class="bg-surface-50 border-b border-surface-200">
                                            <tr>
                                                <th class="px-4 py-3 font-semibold text-surface-600">Field</th>
                                                <th class="px-4 py-3 font-semibold text-surface-600">Operator</th>
                                                <th class="px-4 py-3 font-semibold text-surface-600">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-surface-100">
                                            @foreach($filters as $index => $f)
                                                <tr class="hover:bg-surface-50/50 transition-colors">
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center gap-2">
                                                            <span class="w-5 h-5 rounded bg-surface-100 text-surface-500 flex items-center justify-center text-[10px] font-bold">{{ $index + 1 }}</span>
                                                            <span class="font-mono font-medium text-brand-700">{{ $f['field'] ?? '' }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <span class="px-2 py-1 rounded bg-surface-100 text-surface-600 text-xs font-bold uppercase tracking-wider">{{ $f['operator'] ?? '' }}</span>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        @if(isset($f['value']) && $f['value'] !== '')
                                                            <span class="font-medium text-surface-900 bg-surface-50 px-2 py-1 rounded border border-surface-200">{{ $f['value'] }}</span>
                                                        @else
                                                            <span class="text-surface-300 italic text-xs">Empty</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="p-8 rounded-xl border border-surface-200 border-dashed bg-surface-50/50 flex flex-col items-center justify-center text-center">
                                    <span class="material-symbols-outlined text-[32px] text-surface-300 mb-2">filter_alt_off</span>
                                    <span class="text-sm text-surface-500 font-medium">No filters were applied in this template.</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Actions Footer --}}
                    <div class="p-6 border-t border-surface-100 bg-surface-50/80 rounded-b-2xl flex items-center justify-end gap-3">
                        <a href="{{ route('report-templates.index') }}" class="px-5 py-2.5 bg-white border border-surface-200 rounded-xl text-body-sm font-medium text-surface-700 hover:bg-surface-50 hover:border-surface-300 transition-all shadow-sm">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-brand-600 rounded-xl text-body-sm font-medium text-white hover:bg-brand-700 hover:shadow-md transition-all active:translate-y-0.5">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
