@extends('layouts.app')

@section('title', $config['description'] . ' — PL Sync Data Warehouse')
@section('page-title', $config['description'])

@section('content')
{{-- Header --}}
<div class="flex items-center justify-between animate-fade-up">
    <div class="flex items-center gap-3">
        <a href="{{ route('master-data.index') }}" class="p-2 rounded-xl hover:bg-surface-100 transition-all duration-200 active:scale-95">
            <span class="material-symbols-outlined text-surface-500 text-[20px]">arrow_back</span>
        </a>
        <div>
            <h2 class="text-h1 text-surface-800">{{ $config['description'] }}</h2>
            <p class="text-label-sm text-surface-400 mt-0.5 font-mono">
                OData: {{ $config['odata_entity'] }} → Table: {{ $config['table'] }}
            </p>
        </div>
    </div>
    <button onclick="syncEntity('{{ $entityKey }}', this)"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 active:scale-[0.97] shadow-sm">
        <span class="material-symbols-outlined text-[16px]">sync</span> Sync Now
    </button>
</div>

@if(!$tableExists)
    <div class="bg-amber-50 border border-amber-200/60 rounded-2xl p-8 text-center animate-fade-up mt-5">
        <span class="material-symbols-outlined text-4xl text-amber-500 mb-3 block">info</span>
        <p class="text-body-lg text-amber-800 font-medium">
            Table <code class="bg-amber-100 px-2 py-0.5 rounded-lg font-mono text-body-sm">{{ $config['table'] }}</code> does not exist yet.
        </p>
        <p class="text-body-md text-amber-700 mt-1">Click <strong>Sync Now</strong> to fetch data from Acumatica and create the table automatically.</p>
    </div>
@else
    {{-- Data Table --}}
    <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden animate-fade-up mt-5">
        <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center flex-wrap gap-4">
            <span class="text-label-md text-surface-500 whitespace-nowrap">
                Showing {{ $data->firstItem() ?? 0 }}–{{ $data->lastItem() ?? 0 }} of {{ number_format($data->total()) }} records
            </span>
            <form action="{{ url()->current() }}" method="GET" class="flex items-center w-full max-w-sm">
                <div class="relative w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-[16px]">search</span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search records..." 
                           class="w-full pl-9 pr-3 py-2 bg-surface-50 border border-surface-200 rounded-xl text-body-sm text-surface-800 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all duration-200">
                </div>
                @if(request('search'))
                    <a href="{{ url()->current() }}" class="ml-2 p-2 flex items-center justify-center bg-surface-100 hover:bg-surface-200 border border-surface-200 rounded-xl text-surface-500 transition-all duration-200" title="Clear Search">
                        <span class="material-symbols-outlined text-[16px]">close</span>
                    </a>
                @endif
                <button type="submit" class="ml-2 px-3 py-2 bg-surface-100 hover:bg-surface-200 text-surface-600 rounded-xl text-label-sm transition-all duration-200 border border-surface-200">
                    Search
                </button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-max">
                <thead>
                    <tr class="border-b border-surface-100">
                        @foreach($columns as $col)
                            @if(!in_array($col, ['_sync_id', '_synced_at', '_row_hash']))
                                <th class="py-3 px-4 text-label-sm text-surface-500 font-semibold uppercase tracking-wider whitespace-nowrap">{{ $col }}</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="font-mono text-body-sm text-surface-700 divide-y divide-surface-100">
                    @foreach($data as $row)
                        <tr class="hover:bg-surface-50/50 transition-colors">
                            @foreach($columns as $col)
                                @if(!in_array($col, ['_sync_id', '_synced_at', '_row_hash']))
                                    <td class="py-2.5 px-4 whitespace-nowrap max-w-xs truncate">{{ $row->{$col} ?? '' }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($data->hasPages())
            <div class="px-5 py-4 border-t border-surface-100 flex justify-center">
                {{ $data->links('pagination::simple-tailwind') }}
            </div>
        @endif
    </div>
@endif
@endsection
