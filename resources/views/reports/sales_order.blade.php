@extends('layouts.app')

@section('title', 'Data Mentah Rinci Sales Order - PL Sync')
@section('page-title', 'Data Mentah Rinci Sales Order')

@section('content')
    <div
        class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm overflow-hidden flex flex-col">
        <!-- Filters Header -->
        <div
            class="p-6 border-b border-outline-variant bg-surface-container-low flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
            <div>
                <h2 class="font-h2 text-h2 text-on-surface">Data Sales Order</h2>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="p-6 border-b border-outline-variant">
            <form method="GET" action="{{ route('report.sales-order') }}" class="flex flex-wrap gap-4 items-end">
                <!-- Order Date Filter -->
                <div class="flex flex-col gap-1 w-full md:w-auto">
                    <label class="font-label-md text-label-md text-on-surface-variant">Order Date Range</label>
                    <div class="flex items-center gap-2">
                        <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}"
                            class="rounded border-outline-variant bg-surface text-on-surface font-body-md focus:ring-primary focus:border-primary">
                        <span class="text-on-surface-variant">to</span>
                        <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}"
                            class="rounded border-outline-variant bg-surface text-on-surface font-body-md focus:ring-primary focus:border-primary">
                    </div>
                </div>

                <!-- Customer ID Filter -->
                <div class="flex flex-col gap-1 w-full md:w-auto min-w-[250px]">
                    <label for="customer_id" class="font-label-md text-label-md text-on-surface-variant">Customer
                        (Searchable)</label>
                    <select name="customer_id" id="customer_id" placeholder="Cari nama/ID customer..." autocomplete="off">
                        <option value="">Semua Customer</option>
                        @foreach ($customerIds as $id)
                            <option value="{{ $id }}" {{ $filters['customer_id'] == $id ? 'selected' : '' }}>
                                {{ $id }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2 w-full md:w-auto mt-4 md:mt-0">
                    <button type="submit"
                        class="bg-primary text-on-primary font-label-md text-label-md px-6 py-2 rounded shadow-sm hover:bg-primary-container hover:text-on-primary-container transition-colors active:scale-95 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">search</span> Filter
                    </button>
                    <a href="{{ route('report.sales-order') }}"
                        class="bg-surface-container-high text-on-surface font-label-md text-label-md px-4 py-2 rounded shadow-sm hover:bg-surface-variant transition-colors active:scale-95 flex items-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Data Table -->
        @if (!$hasData)
            <div class="p-12 text-center flex flex-col items-center justify-center">
                <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-4">database_off</span>
                <p class="font-h2 text-h2 text-on-surface">Data Belum Tersedia</p>
                <p class="font-body-md text-body-md text-on-surface-variant mt-2 max-w-md">Tabel acm_sales_orders belum
                    dibuat atau belum disinkronisasi. Silakan lakukan sinkronisasi Sales Order dari menu Transactions
                    terlebih dahulu.</p>
            </div>
        @elseif(count($records) === 0)
            <div class="p-12 text-center flex flex-col items-center justify-center">
                <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-4">search_off</span>
                <p class="font-h2 text-h2 text-on-surface">Pencarian Tidak Ditemukan</p>
                <p class="font-body-md text-body-md text-on-surface-variant mt-2 max-w-md">Tidak ada data Sales Order yang
                    sesuai dengan kriteria filter yang Anda terapkan.</p>
            </div>
        @else
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr
                            class="bg-surface-container-low border-b border-outline-variant font-label-md text-label-md text-on-surface-variant">
                            <th class="py-3 px-6 font-semibold">Order Nbr</th>
                            <th class="py-3 px-6 font-semibold">Order Type</th>
                            <th class="py-3 px-6 font-semibold">Status</th>
                            <th class="py-3 px-6 font-semibold">Order Date</th>
                            <th class="py-3 px-6 font-semibold">Customer ID</th>
                            <th class="py-3 px-6 font-semibold">Customer Class</th>
                            <th class="py-3 px-6 font-semibold text-right">Order Total</th>
                            <th class="py-3 px-6 font-semibold">Currency</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-outline-variant font-body-md text-body-md text-on-surface bg-surface-container-lowest">
                        @foreach ($records as $record)
                            <tr class="hover:bg-surface-container-lowest/50 transition-colors">
                                <td class="py-3 px-6 font-medium">{{ $record->OrderNbr ?? '-' }}</td>
                                <td class="py-3 px-6">{{ $record->OrderType ?? '-' }}</td>
                                <td class="py-3 px-6">
                                    <span
                                        class="px-2 py-1 bg-surface-container-highest rounded text-xs">{{ $record->Status ?? '-' }}</span>
                                </td>
                                <td class="py-3 px-6">
                                    {{ isset($record->OrderDate) ? \Carbon\Carbon::parse($record->OrderDate)->format('d/m/Y') : '-' }}
                                </td>
                                <td class="py-3 px-6">{{ $record->CustomerID ?? '-' }}</td>
                                <td class="py-3 px-6">{{ $record->CustomerClassID ?? '-' }}</td>
                                <td class="py-3 px-6 text-right font-medium">
                                    {{ isset($record->OrderTotal) ? number_format((float) $record->OrderTotal, 2) : '-' }}
                                </td>
                                <td class="py-3 px-6 text-on-surface-variant">{{ $record->CuryID ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4 border-t border-outline-variant bg-surface-container-low flex justify-between items-center">
                <span class="font-body-md text-body-md text-on-surface-variant">
                    Menampilkan {{ $records->firstItem() ?? 0 }} - {{ $records->lastItem() ?? 0 }} dari
                    {{ $records->total() }} data
                </span>
                <div class="flex gap-2">
                    {{ $records->links('pagination::tailwind') }}
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    <!-- Include TomSelect for searchable dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
        .ts-wrapper .ts-control {
            border-color: #c6c6cd !important;
            border-radius: 0.25rem !important;
            padding: 0.5rem 0.75rem !important;
            background-color: #fcf8fa !important;
            color: #1b1b1d !important;
            box-shadow: none !important;
            font-family: 'Inter', sans-serif !important;
            min-height: 42px;
        }

        .ts-wrapper.focus .ts-control {
            border-color: #000000 !important;
            box-shadow: 0 0 0 1px #000000 !important;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new TomSelect('#customer_id', {
                create: false,
                sortField: {
                    field: 'text',
                    direction: 'asc'
                },
                placeholder: 'Ketik nama / ID customer...',
            });
        });
    </script>
@endsection
