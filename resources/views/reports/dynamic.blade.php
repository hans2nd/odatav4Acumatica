@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between animate-fade-up">
        <div>
            <h2 class="text-h2 text-surface-900">{{ $title }}</h2>
            <p class="text-body-md text-surface-500 mt-0.5">Data generated from Query Repository</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ url()->current() }}?export=csv&{{ http_build_query(request()->except('export')) }}" onclick="showExportProgress()" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-surface-200 text-surface-700 hover:bg-surface-50 hover:text-surface-900 rounded-xl text-label-md transition-all shadow-sm">
                <span class="material-symbols-outlined text-[18px]">csv</span> Export CSV
            </a>
            <a href="{{ url()->current() }}?export=xlsx&{{ http_build_query(request()->except('export')) }}" onclick="showExportProgress()" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-surface-200 text-surface-700 hover:bg-surface-50 hover:text-surface-900 rounded-xl text-label-md transition-all shadow-sm">
                <span class="material-symbols-outlined text-[18px]">table_chart</span> Export Excel
            </a>
        </div>
    </div>

    @if(isset($error))
        <div class="p-4 bg-red-50 text-red-600 rounded-xl border border-red-200 animate-fade-up">
            {{ $error }}
        </div>
    @endif
    
    @if(!isset($error) || collect($results)->isNotEmpty() || isset($filterConfig))
        @php
            $hasFilters = false;
            $renderFilters = [];
            
            if (!isset($filterConfig)) {
                // Legacy
                $hasFilters = true;
                foreach($columns as $col) {
                    $field = is_array($col) ? ($col['field'] ?? null) : $col;
                    if($field) {
                        $renderFilters[] = [
                            'field' => $field,
                            'label' => Str::title(str_replace('_', ' ', $field)),
                            'type' => 'text'
                        ];
                    }
                }
            } elseif (is_array($filterConfig) && count($filterConfig) > 0) {
                $hasFilters = true;
                $renderFilters = $filterConfig;
            }
        @endphp

        @if($hasFilters)
        <!-- Filter Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-surface-200/80 p-5 animate-fade-up" style="animation-delay: 0.1s">
            <form method="GET" action="{{ url()->current() }}" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <input type="hidden" name="apply_filters" value="1">
                @foreach($renderFilters as $filter)
                    @php 
                        $field = $filter['field'];
                        $type = $filter['type'] ?? 'text';
                        $label = $filter['label'] ?? Str::title(str_replace('_', ' ', $field));
                        $placeholder = $filter['placeholder'] ?? 'Search...';
                        $required = $filter['required'] ?? false;
                    @endphp
                    @if($type === 'date_range')
                        <div class="col-span-1 md:col-span-2">
                            <label class="block text-label-sm text-surface-700 mb-1.5">{{ $label }} {!! $required ? '<span class="text-red-500">*</span>' : '' !!}</label>
                            <div class="flex items-center gap-2">
                                <input type="date" name="{{ $field }}_from" value="{{ request($field . '_from') }}" class="w-full bg-white border border-surface-300 rounded-xl px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                                <span>-</span>
                                <input type="date" name="{{ $field }}_to" value="{{ request($field . '_to') }}" class="w-full bg-white border border-surface-300 rounded-xl px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                            </div>
                        </div>
                    @else
                        <div>
                            <label class="block text-label-sm text-surface-700 mb-1.5">{{ $label }} {!! $required ? '<span class="text-red-500">*</span>' : '' !!}</label>
                            @if($type === 'date')
                                <input type="date" name="{{ $field }}" value="{{ request($field) }}" class="w-full bg-white border border-surface-300 rounded-xl px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                            @elseif($type === 'number')
                                <input type="number" step="any" name="{{ $field }}" value="{{ request($field) }}" placeholder="{{ $placeholder }}" class="w-full bg-white border border-surface-300 rounded-xl px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                            @else
                                <input type="text" name="{{ $field }}" value="{{ request($field) }}" placeholder="{{ $placeholder }}" class="w-full bg-white border border-surface-300 rounded-xl px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                            @endif
                        </div>
                    @endif
                @endforeach
                <div class="md:col-span-3 lg:col-span-4 flex justify-end gap-3 mt-2">
                    <a href="{{ url()->current() }}" class="px-5 py-2.5 bg-white border border-surface-200 text-surface-700 hover:bg-surface-50 rounded-xl text-label-md transition-all">Clear</a>
                    <button type="submit" class="px-5 py-2.5 bg-brand-600 text-white hover:bg-brand-700 rounded-xl text-label-md transition-all shadow-sm shadow-brand-500/20">Apply Filters</button>
                </div>
            </form>
        </div>
        @endif
        
        <!-- Data Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-surface-200/80 overflow-hidden animate-fade-up" style="animation-delay: 0.2s">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-50/50 border-b border-surface-200">
                            @foreach($columns as $col)
                                @php $field = is_array($col) ? ($col['field'] ?? null) : $col; @endphp
                                @if($field)
                                <th class="px-6 py-4 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">{{ Str::title(str_replace('_', ' ', $field)) }}</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 text-body-sm text-surface-700">
                        @if(isset($results) && collect($results)->isNotEmpty())
                            @foreach($results as $row)
                                <tr class="hover:bg-surface-50/80 transition-colors">
                                    @foreach($columns as $col)
                                        @php $field = is_array($col) ? ($col['field'] ?? null) : $col; @endphp
                                        @if($field)
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $row->{$field} ?? '-' }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="{{ count($columns) }}" class="px-6 py-12 text-center text-surface-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="material-symbols-outlined text-[48px] text-surface-300 mb-2">database</span>
                                        <p class="text-label-md text-surface-600">No data found matching your filters.</p>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            
            @if(isset($results) && method_exists($results, 'links'))
                <div class="p-4 border-t border-surface-200/80 bg-surface-50/30">
                    {{ $results->links() }}
                </div>
            @endif
        </div>
    @endif
</div>

@section('scripts')
<div id="exportProgressModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="absolute inset-0 bg-surface-900/50 backdrop-blur-sm"></div>
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-sm w-full mx-4 relative z-10 text-center animate-fade-up">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-brand-50 text-brand-600 mb-4">
            <span class="material-symbols-outlined text-[32px] spin">sync</span>
        </div>
        <h3 class="text-h3 text-surface-900 mb-2">Preparing Export...</h3>
        <p class="text-body-md text-surface-500">Your download will start shortly. Please wait and do not close this window.</p>
    </div>
</div>
<script>
    function showExportProgress() {
        document.getElementById('exportProgressModal').classList.remove('hidden');
        // Hide modal after 5 seconds assuming browser download started
        setTimeout(() => {
            document.getElementById('exportProgressModal').classList.add('hidden');
        }, 5000);
    }
</script>
@endsection
@endsection
