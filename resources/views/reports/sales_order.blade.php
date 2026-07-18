@extends('layouts.app')

@section('title', 'Data Mentah Rinci Sales Order - PL Sync')
@section('page-title', 'Data Mentah Rinci Sales Order')

@section('content')
    <div class="bg-white rounded-2xl border border-surface-200/60 shadow-card overflow-hidden flex flex-col animate-fade-up">
        <!-- Header -->
        <div class="px-5 py-4 border-b border-surface-100 flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
            <div>
                <h2 class="text-h2 text-surface-800">Data Sales Order</h2>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="px-5 py-4 border-b border-surface-100 bg-surface-50/50">
            <form method="GET" action="{{ route('report.sales-order') }}" class="flex flex-wrap gap-4 items-end">
                <!-- Order Date Filter -->
                <div class="flex flex-col gap-1.5 w-full md:w-auto">
                    <label class="text-label-md text-surface-600">Order Date Range</label>
                    <div class="flex items-center gap-2">
                        <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}"
                            class="rounded-xl border-surface-200 bg-white text-surface-800 text-body-sm focus:ring-brand-500 focus:border-brand-500 py-2">
                        <span class="text-surface-400 text-body-sm">to</span>
                        <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}"
                            class="rounded-xl border-surface-200 bg-white text-surface-800 text-body-sm focus:ring-brand-500 focus:border-brand-500 py-2">
                    </div>
                </div>

                <!-- Customer ID Filter -->
                <div class="flex flex-col gap-1.5 w-full md:w-auto min-w-[250px]">
                    <label for="customer_id" class="text-label-md text-surface-600">Customer (Searchable)</label>
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
                        class="bg-brand-600 text-white text-label-md px-5 py-2 rounded-xl shadow-sm hover:bg-brand-700 transition-all duration-200 active:scale-95 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px]">search</span> Filter
                    </button>
                    <a href="{{ route('report.sales-order') }}"
                        class="bg-surface-100 text-surface-600 text-label-md px-4 py-2 rounded-xl hover:bg-surface-200 transition-all duration-200 active:scale-95 flex items-center border border-surface-200">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Data Table -->
        @if (!$hasData)
            <div class="p-12 text-center flex flex-col items-center justify-center">
                <span class="material-symbols-outlined text-4xl text-surface-300 mb-4">database_off</span>
                <p class="text-h3 text-surface-800">Data Belum Tersedia</p>
                <p class="text-body-md text-surface-500 mt-2 max-w-md">Tabel acm_sales_orders belum
                    dibuat atau belum disinkronisasi. Silakan lakukan sinkronisasi Sales Order dari menu Transactions
                    terlebih dahulu.</p>
            </div>
        @elseif(count($records) === 0)
            <div class="p-12 text-center flex flex-col items-center justify-center">
                <span class="material-symbols-outlined text-4xl text-surface-300 mb-4">search_off</span>
                <p class="text-h3 text-surface-800">Pencarian Tidak Ditemukan</p>
                <p class="text-body-md text-surface-500 mt-2 max-w-md">Tidak ada data Sales Order yang
                    sesuai dengan kriteria filter yang Anda terapkan.</p>
            </div>
        @else
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="border-b border-surface-100">
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Order Nbr</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Order Type</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Status</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Order Date</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Customer ID</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Customer Class</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider text-right">Order Total</th>
                            <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Currency</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 text-body-md text-surface-700">
                        @foreach ($records as $record)
                            <tr class="hover:bg-surface-50/50 transition-colors">
                                <td class="py-3 px-5 font-medium text-surface-800">{{ $record->OrderNbr ?? '-' }}</td>
                                <td class="py-3 px-5">{{ $record->OrderType ?? '-' }}</td>
                                <td class="py-3 px-5">
                                    <span class="px-2.5 py-1 bg-surface-100 rounded-lg text-label-sm font-semibold text-surface-700">{{ $record->Status ?? '-' }}</span>
                                </td>
                                <td class="py-3 px-5">
                                    {{ isset($record->OrderDate) ? \Carbon\Carbon::parse($record->OrderDate)->format('d/m/Y') : '-' }}
                                </td>
                                <td class="py-3 px-5">{{ $record->CustomerID ?? '-' }}</td>
                                <td class="py-3 px-5">{{ $record->CustomerClassID ?? '-' }}</td>
                                <td class="py-3 px-5 text-right font-medium font-mono">
                                    {{ isset($record->OrderTotal) ? number_format((float) $record->OrderTotal, 2) : '-' }}
                                </td>
                                <td class="py-3 px-5 text-surface-500">{{ $record->CuryID ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-5 py-4 border-t border-surface-100 flex justify-between items-center">
                <span class="text-body-sm text-surface-500">
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
            border-color: #e2e8f0 !important;
            border-radius: 0.75rem !important;
            padding: 0.5rem 0.875rem !important;
            background-color: #ffffff !important;
            color: #1e293b !important;
            box-shadow: none !important;
            font-family: 'Inter', sans-serif !important;
            min-height: 42px;
            font-size: 13px;
        }

        .ts-wrapper.focus .ts-control {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
        }

        .ts-dropdown {
            border-radius: 0.75rem !important;
            border-color: #e2e8f0 !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06), 0 4px 10px rgba(0,0,0,0.04) !important;
            font-family: 'Inter', sans-serif !important;
            font-size: 13px;
        }

        .ts-dropdown .option.active {
            background-color: #eef2ff !important;
            color: #4f46e5 !important;
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
