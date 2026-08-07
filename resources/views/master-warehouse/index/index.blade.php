@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between animate-fade-up">
        <div>
            <h2 class="text-h2 text-surface-900">{$title}</h2>
            <p class="text-body-md text-surface-500 mt-0.5">Data generated from Query Builder</p>
        </div>
        <form method="POST" action="{{ route('menu-management.refresh-files', ['route_name' => '{$routeName}']) }}" onsubmit="return confirm('Regenerate files to pull latest columns from Query Builder?');">
            @csrf
            <button type="submit" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-surface-200 text-surface-700 hover:bg-surface-50 hover:text-surface-900 rounded-xl text-label-md transition-all shadow-sm">
                <span class="material-symbols-outlined text-[18px]">refresh</span> Refresh Columns
            </button>
        </form>
    </div>

    @if(isset($error))
        <div class="p-4 bg-red-50 text-red-600 rounded-xl border border-red-200 animate-fade-up">
            {{ $error }}
        </div>
    @else
        <!-- Filter Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-surface-200/80 p-5 animate-fade-up" style="animation-delay: 0.1s">
            <form method="GET" action="{{ url()->current() }}" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($columns as $col)
                    @php $field = is_array($col) ? ($col['field'] ?? null) : $col; @endphp
                    @if($field)
                    <div>
                        <label class="block text-label-sm text-surface-700 mb-1.5">{{ Str::title(str_replace('_', ' ', $field)) }}</label>
                        <input type="text" name="{{ $field }}" value="{{ request($field) }}" placeholder="Search..." class="w-full bg-white border border-surface-300 rounded-xl px-3 py-2 text-body-sm text-surface-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all">
                    </div>
                    @endif
                @endforeach
                <div class="md:col-span-3 lg:col-span-4 flex justify-end gap-3 mt-2">
                    <a href="{{ url()->current() }}" class="px-5 py-2.5 bg-white border border-surface-200 text-surface-700 hover:bg-surface-50 rounded-xl text-label-md transition-all">Clear</a>
                    <button type="submit" class="px-5 py-2.5 bg-brand-600 text-white hover:bg-brand-700 rounded-xl text-label-md transition-all shadow-sm shadow-brand-500/20">Apply Filters</button>
                </div>
            </form>
        </div>

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
                        @forelse($results as $row)
                            <tr class="hover:bg-surface-50/80 transition-colors">
                                @foreach($columns as $col)
                                    @php $field = is_array($col) ? ($col['field'] ?? null) : $col; @endphp
                                    @if($field)
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $row->{$field} ?? '-' }}</td>
                                    @endif
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) }}" class="px-6 py-12 text-center text-surface-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="material-symbols-outlined text-[48px] text-surface-300 mb-2">database</span>
                                        <p class="text-label-md text-surface-600">No data found matching your filters.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if(method_exists($results, 'links'))
                <div class="p-4 border-t border-surface-200/80 bg-surface-50/30">
                    {{ $results->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection