@extends('layouts.app')
@section('title', $config['description'] . ' Report — PL Sync')
@section('page-title', $config['description'])

@section('content')
<div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm overflow-hidden flex flex-col">

    {{-- Header --}}
    <div class="p-6 border-b border-outline-variant bg-surface-container-low flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
        <div>
            <h2 class="font-h2 text-h2 text-on-surface">{{ $config['description'] }}</h2>
            <p class="text-body-md text-on-surface-variant mt-0.5">Table: <code class="text-xs bg-surface-container px-1.5 py-0.5 rounded">{{ $config['table'] }}</code></p>
        </div>
        @if($tableExists && $data instanceof \Illuminate\Pagination\LengthAwarePaginator && $data->total() > 0)
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('report.dynamic.export', $entityKey) }}?{{ http_build_query(array_merge($filters, ['format' => 'xlsx'])) }}"
               class="flex items-center gap-2 bg-green-700 text-white px-4 py-2 rounded hover:bg-green-800 transition-colors text-label-md font-label-md active:scale-95">
                <span class="material-symbols-outlined text-sm">table_view</span> Export Excel
            </a>
            <a href="{{ route('report.dynamic.export', $entityKey) }}?{{ http_build_query(array_merge($filters, ['format' => 'csv'])) }}"
               class="flex items-center gap-2 bg-surface-container-high text-on-surface px-4 py-2 rounded border border-outline-variant hover:bg-surface-variant transition-colors text-label-md font-label-md active:scale-95">
                <span class="material-symbols-outlined text-sm">download</span> CSV
            </a>
        </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="p-6 border-b border-outline-variant">
        <form method="GET" action="{{ route('report.dynamic.show', $entityKey) }}" class="flex flex-wrap gap-4 items-end">

            {{-- Date Column Selector --}}
            @if(count($dateColumns) > 0)
            <div class="flex flex-col gap-1">
                <label class="font-label-md text-label-md text-on-surface-variant">Date Column</label>
                <select name="date_column" class="rounded border-outline-variant bg-surface text-on-surface font-body-md text-sm focus:ring-primary focus:border-primary min-w-[160px]">
                    @foreach($dateColumns as $dc)
                    <option value="{{ $dc }}" {{ $filters['date_column'] === $dc ? 'selected' : '' }}>{{ $dc }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Date Range --}}
            <div class="flex flex-col gap-1">
                <label class="font-label-md text-label-md text-on-surface-variant">Date Range</label>
                <div class="flex items-center gap-2">
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                        class="rounded border-outline-variant bg-surface text-on-surface font-body-md text-sm focus:ring-primary focus:border-primary">
                    <span class="text-on-surface-variant text-sm">to</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                        class="rounded border-outline-variant bg-surface text-on-surface font-body-md text-sm focus:ring-primary focus:border-primary">
                </div>
            </div>
            @endif

            {{-- Search Column --}}
            <div class="flex flex-col gap-1">
                <label class="font-label-md text-label-md text-on-surface-variant">Search Column</label>
                <select name="search_column" class="rounded border-outline-variant bg-surface text-on-surface font-body-md text-sm focus:ring-primary focus:border-primary min-w-[160px]">
                    <option value="">All columns</option>
                    @foreach($columns as $col)
                    <option value="{{ $col }}" {{ $filters['search_column'] === $col ? 'selected' : '' }}>{{ $col }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Search Value --}}
            <div class="flex flex-col gap-1">
                <label class="font-label-md text-label-md text-on-surface-variant">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Type to search..."
                    class="rounded border-outline-variant bg-surface text-on-surface font-body-md text-sm focus:ring-primary focus:border-primary min-w-[200px]">
            </div>

            {{-- Buttons --}}
            <div class="flex gap-2 mt-4 md:mt-0">
                <button type="submit"
                    class="bg-primary text-on-primary font-label-md text-label-md px-5 py-2 rounded shadow-sm hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-95 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">filter_alt</span> Filter
                </button>
                <a href="{{ route('report.dynamic.show', $entityKey) }}"
                    class="bg-surface-container-high text-on-surface font-label-md text-label-md px-4 py-2 rounded shadow-sm hover:bg-surface-variant transition-colors active:scale-95 flex items-center">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Data Table --}}
    @if (!$tableExists)
        <div class="p-12 text-center flex flex-col items-center justify-center">
            <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-4">database_off</span>
            <p class="font-h2 text-h2 text-on-surface">Data Belum Tersedia</p>
            <p class="font-body-md text-body-md text-on-surface-variant mt-2 max-w-md">
                Tabel <code>{{ $config['table'] }}</code> belum disinkronisasi.
                Silakan lakukan sinkronisasi dari menu Transactions terlebih dahulu.
            </p>
        </div>
    @elseif($data instanceof \Illuminate\Pagination\LengthAwarePaginator && $data->total() === 0)
        <div class="p-12 text-center flex flex-col items-center justify-center">
            <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-4">search_off</span>
            <p class="font-h2 text-h2 text-on-surface">Tidak Ada Data</p>
            <p class="font-body-md text-body-md text-on-surface-variant mt-2 max-w-md">
                Tidak ada data yang sesuai dengan filter yang diterapkan.
            </p>
        </div>
    @else
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant font-label-md text-label-md text-on-surface-variant">
                        <th class="py-3 px-4 font-semibold text-center w-12">#</th>
                        @foreach($columns as $col)
                        <th class="py-3 px-4 font-semibold">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant font-body-md text-body-md text-on-surface bg-surface-container-lowest">
                    @foreach($data as $idx => $record)
                    <tr class="hover:bg-surface-container-low/50 transition-colors">
                        <td class="py-2.5 px-4 text-center text-on-surface-variant text-xs">{{ $data->firstItem() + $idx }}</td>
                        @foreach($columns as $col)
                        <td class="py-2.5 px-4 max-w-[300px] truncate" title="{{ $record->{$col} ?? '' }}">
                            {{ $record->{$col} ?? '-' }}
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="p-4 border-t border-outline-variant bg-surface-container-low flex justify-between items-center">
            <span class="font-body-md text-body-md text-on-surface-variant">
                Menampilkan {{ $data->firstItem() ?? 0 }} - {{ $data->lastItem() ?? 0 }} dari {{ $data->total() }} data
            </span>
            <div class="flex gap-2">
                {{ $data->links('pagination::tailwind') }}
            </div>
        </div>
    @endif
</div>
@endsection
