@extends('layouts.app')
@section('title', $config['description'] . ' Report — PL Sync')
@section('page-title', $config['description'])

@section('content')
<div class="bg-white rounded-2xl border border-surface-200/60 shadow-card overflow-hidden flex flex-col animate-fade-up">

    {{-- Header --}}
    <div class="px-5 py-4 border-b border-surface-100 flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
        <div>
            <h2 class="text-h2 text-surface-800">{{ $config['description'] }}</h2>
            <p class="text-body-sm text-surface-500 mt-0.5">Table: <code class="text-label-sm bg-surface-100 px-2 py-0.5 rounded-lg font-mono">{{ $config['table'] }}</code></p>
        </div>
        @if($tableExists && $data instanceof \Illuminate\Pagination\LengthAwarePaginator && $data->total() > 0)
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('report.dynamic.export', $entityKey) }}?{{ http_build_query(array_merge($filters, ['format' => 'xlsx'])) }}"
               class="flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-xl hover:bg-emerald-700 transition-all duration-200 text-label-md active:scale-95 shadow-sm">
                <span class="material-symbols-outlined text-[16px]">table_view</span> Export Excel
            </a>
            <a href="{{ route('report.dynamic.export', $entityKey) }}?{{ http_build_query(array_merge($filters, ['format' => 'csv'])) }}"
               class="flex items-center gap-2 bg-surface-100 text-surface-700 px-4 py-2 rounded-xl border border-surface-200 hover:bg-surface-200 transition-all duration-200 text-label-md active:scale-95">
                <span class="material-symbols-outlined text-[16px]">download</span> CSV
            </a>
        </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="px-5 py-4 border-b border-surface-100 bg-surface-50/50">
        <form method="GET" action="{{ route('report.dynamic.show', $entityKey) }}" class="flex flex-wrap gap-4 items-end">

            @if(count($dateColumns) > 0)
            <div class="flex flex-col gap-1.5">
                <label class="text-label-md text-surface-600">Date Column</label>
                <select name="date_column" class="rounded-xl border-surface-200 bg-white text-surface-800 text-body-sm focus:ring-brand-500 focus:border-brand-500 min-w-[160px] py-2">
                    @foreach($dateColumns as $dc)
                    <option value="{{ $dc }}" {{ $filters['date_column'] === $dc ? 'selected' : '' }}>{{ $dc }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="text-label-md text-surface-600">Date Range</label>
                <div class="flex items-center gap-2">
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                        class="rounded-xl border-surface-200 bg-white text-surface-800 text-body-sm focus:ring-brand-500 focus:border-brand-500 py-2">
                    <span class="text-surface-400 text-body-sm">to</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                        class="rounded-xl border-surface-200 bg-white text-surface-800 text-body-sm focus:ring-brand-500 focus:border-brand-500 py-2">
                </div>
            </div>
            @endif

            <div class="flex flex-col gap-1.5">
                <label class="text-label-md text-surface-600">Search Column</label>
                <select name="search_column" class="rounded-xl border-surface-200 bg-white text-surface-800 text-body-sm focus:ring-brand-500 focus:border-brand-500 min-w-[160px] py-2">
                    <option value="">All columns</option>
                    @foreach($columns as $col)
                    <option value="{{ $col }}" {{ $filters['search_column'] === $col ? 'selected' : '' }}>{{ $col }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="text-label-md text-surface-600">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Type to search..."
                    class="rounded-xl border-surface-200 bg-white text-surface-800 text-body-sm focus:ring-brand-500 focus:border-brand-500 min-w-[200px] py-2">
            </div>

            <div class="flex gap-2 mt-4 md:mt-0">
                <button type="submit"
                    class="bg-brand-600 text-white text-label-md px-5 py-2 rounded-xl shadow-sm hover:bg-brand-700 transition-all duration-200 active:scale-95 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">filter_alt</span> Filter
                </button>
                <a href="{{ route('report.dynamic.show', $entityKey) }}"
                    class="bg-surface-100 text-surface-600 text-label-md px-4 py-2 rounded-xl hover:bg-surface-200 transition-all duration-200 active:scale-95 flex items-center border border-surface-200">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Data Table --}}
    @if (!$tableExists)
        <div class="p-12 text-center flex flex-col items-center justify-center">
            <span class="material-symbols-outlined text-4xl text-surface-300 mb-4">database_off</span>
            <p class="text-h3 text-surface-800">Data Belum Tersedia</p>
            <p class="text-body-md text-surface-500 mt-2 max-w-md">
                Tabel <code class="font-mono">{{ $config['table'] }}</code> belum disinkronisasi.
                Silakan lakukan sinkronisasi dari menu Transactions terlebih dahulu.
            </p>
        </div>
    @elseif($data instanceof \Illuminate\Pagination\LengthAwarePaginator && $data->total() === 0)
        <div class="p-12 text-center flex flex-col items-center justify-center">
            <span class="material-symbols-outlined text-4xl text-surface-300 mb-4">search_off</span>
            <p class="text-h3 text-surface-800">Tidak Ada Data</p>
            <p class="text-body-md text-surface-500 mt-2 max-w-md">
                Tidak ada data yang sesuai dengan filter yang diterapkan.
            </p>
        </div>
    @else
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead>
                    <tr class="border-b border-surface-100">
                        <th class="py-3 px-4 text-label-sm text-surface-500 font-semibold text-center w-12 uppercase tracking-wider">#</th>
                        @foreach($columns as $col)
                        <th class="py-3 px-4 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100 text-body-md text-surface-700">
                    @foreach($data as $idx => $record)
                    <tr class="hover:bg-surface-50/50 transition-colors">
                        <td class="py-2.5 px-4 text-center text-surface-400 text-label-sm">{{ $data->firstItem() + $idx }}</td>
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
        <div class="px-5 py-4 border-t border-surface-100 flex justify-between items-center">
            <span class="text-body-sm text-surface-500">
                Menampilkan {{ $data->firstItem() ?? 0 }} - {{ $data->lastItem() ?? 0 }} dari {{ $data->total() }} data
            </span>
            <div class="flex gap-2">
                {{ $data->links('pagination::tailwind') }}
            </div>
        </div>
    @endif
</div>
@endsection
