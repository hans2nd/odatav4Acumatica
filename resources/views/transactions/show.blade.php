@extends('layouts.app')

@section('title', $config['description'] . ' — Enterprise Sync Engine')
@section('page-title', $config['description'])

@section('content')
{{-- Header --}}
<div class="flex items-center justify-between fade-in">
    <div class="flex items-center gap-md">
        <a href="{{ route('transactions.index') }}" class="p-2 rounded hover:bg-surface-container-low transition-colors">
            <span class="material-symbols-outlined text-on-surface-variant">arrow_back</span>
        </a>
        <div>
            <h2 class="font-h1 text-h1 text-on-surface">{{ $config['description'] }}</h2>
            <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">
                OData: {{ $config['odata_entity'] }} → Table: {{ $config['table'] }}
            </p>
        </div>
    </div>
    <button onclick="syncEntity('{{ $entityKey }}', this)"
            class="inline-flex items-center gap-1 px-4 py-2 rounded bg-primary text-on-primary font-label-md text-label-md hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-[0.97]">
        <span class="material-symbols-outlined text-sm">sync</span> Sync Now
    </button>
</div>

@if(!$tableExists)
    <div class="bg-yellow-50 border border-yellow-200 rounded p-md text-center fade-in">
        <span class="material-symbols-outlined text-3xl text-yellow-600 mb-2">info</span>
        <p class="font-body-md text-body-md text-yellow-800">
            Table <code class="bg-yellow-100 px-1 py-0.5 rounded">{{ $config['table'] }}</code> does not exist yet.
            <br>Click <strong>Sync Now</strong> to fetch data from Acumatica and create the table automatically.
        </p>
    </div>
@else
    {{-- Data Table --}}
    <div class="bg-surface-container-lowest border border-outline-variant rounded flex flex-col fade-in">
        <div class="p-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low/50 flex-wrap gap-4">
            <span class="font-label-md text-label-md text-on-surface-variant whitespace-nowrap">
                Showing {{ $data->firstItem() ?? 0 }}–{{ $data->lastItem() ?? 0 }} of {{ number_format($data->total()) }} records
            </span>
            <form action="{{ url()->current() }}" method="GET" class="flex items-center w-full max-w-sm">
                <div class="relative w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search records..." 
                           class="w-full pl-9 pr-3 py-1.5 bg-surface border border-outline-variant rounded font-body-sm text-body-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                </div>
                @if(request('search'))
                    <a href="{{ url()->current() }}" class="ml-2 p-1.5 flex items-center justify-center bg-surface hover:bg-surface-container-high border border-outline-variant rounded text-on-surface-variant transition-colors" title="Clear Search">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </a>
                @endif
                <button type="submit" class="ml-2 px-3 py-1.5 bg-surface-variant hover:bg-surface-container-highest text-on-surface-variant rounded font-label-sm text-label-sm transition-colors border border-outline-variant">
                    Search
                </button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-max">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        @foreach($columns as $col)
                            @if(!in_array($col, ['_sync_id', '_synced_at']))
                                <th class="py-2 px-3 font-label-sm text-label-sm text-on-surface-variant font-medium whitespace-nowrap">{{ $col }}</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="font-code text-code text-on-surface divide-y divide-outline-variant/50">
                    @foreach($data as $row)
                        <tr class="hover:bg-surface-container-low/50 transition-colors">
                            @foreach($columns as $col)
                                @if(!in_array($col, ['_sync_id', '_synced_at']))
                                    <td class="py-2 px-3 whitespace-nowrap max-w-xs truncate">{{ $row->{$col} ?? '' }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($data->hasPages())
            <div class="p-md border-t border-outline-variant flex justify-center">
                {{ $data->links('pagination::simple-tailwind') }}
            </div>
        @endif
    </div>
@endif
@endsection
