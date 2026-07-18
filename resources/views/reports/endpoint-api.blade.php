@extends('layouts.app')

@section('title', 'Endpoint API')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center shadow-lg shadow-brand-500/20">
                <span class="material-symbols-outlined text-white text-[24px]" style="font-variation-settings: 'FILL' 1;">api</span>
            </div>
            <div>
                <h2 class="text-h2 text-surface-900">Endpoint API</h2>
                <p class="text-body-md text-surface-500 mt-0.5">Expose database tables as API endpoints for PowerBI and Excel.</p>
            </div>
        </div>
        <a href="{{ route('endpoint.api.create') }}" class="px-4 py-2 bg-brand-500 text-white text-label-sm rounded-xl hover:bg-brand-600 focus:ring-4 focus:ring-brand-500/20 transition-all font-semibold flex items-center gap-2 shadow-sm">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Add Endpoint
        </a>
    </div>

@if (session('success'))
<div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 flex items-start gap-3 text-green-700">
    <span class="material-symbols-outlined text-[20px] text-green-500">check_circle</span>
    <div>
        <p class="text-body-sm font-medium">{{ session('success') }}</p>
    </div>
</div>
@endif

    {{-- List Section --}}
    <div class="bg-white rounded-2xl border border-surface-200 shadow-sm overflow-hidden flex flex-col h-full">
        <div class="p-5 border-b border-surface-100 flex items-center justify-between">
            <h2 class="text-h3 text-surface-800">Active Endpoints</h2>
        </div>
        
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-50/50">
                        <th class="px-5 py-3 text-label-sm text-surface-500 whitespace-nowrap">Endpoint Name</th>
                        <th class="px-5 py-3 text-label-sm text-surface-500 whitespace-nowrap">URL (Slug)</th>
                        <th class="px-5 py-3 text-label-sm text-surface-500 whitespace-nowrap">Connection / Table</th>
                        <th class="px-5 py-3 text-label-sm text-surface-500 whitespace-nowrap text-center">Status</th>
                        <th class="px-5 py-3 text-label-sm text-surface-500 whitespace-nowrap text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100">
                    @forelse ($endpoints as $endpoint)
                    <tr class="hover:bg-surface-50/50 transition-colors">
                        <td class="px-5 py-3 align-middle">
                            <p class="text-body-sm font-medium text-surface-800">{{ $endpoint->name }}</p>
                            <p class="text-[11px] text-surface-400 mt-0.5">Created {{ $endpoint->created_at->format('M d, Y') }}</p>
                        </td>
                        <td class="px-5 py-3 align-middle">
                            <div class="flex items-center gap-2">
                                <code class="px-2 py-1 bg-surface-100 text-surface-600 rounded-md text-[11px] font-mono select-all">/data/{{ $endpoint->endpoint_slug }}</code>
                                <button type="button" onclick="copyApiUrl('{{ route('api.data.endpoint', $endpoint->endpoint_slug) }}', this)" class="text-surface-400 hover:text-brand-500 transition-colors" title="Copy URL">
                                    <span class="material-symbols-outlined text-[16px]">content_copy</span>
                                </button>
                                <a href="{{ route('api.data.endpoint', $endpoint->endpoint_slug) }}" target="_blank" class="text-brand-500 hover:text-brand-600" title="Open in new tab">
                                    <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                                </a>
                            </div>
                        </td>
                        <td class="px-5 py-3 align-middle">
                            <div class="flex items-center gap-1.5 text-body-sm text-surface-700">
                                <span class="material-symbols-outlined text-[16px] text-surface-400">database</span>
                                {{ $endpoint->connection_key }}
                            </div>
                            <div class="flex items-center gap-1.5 text-[11px] text-surface-500 mt-0.5">
                                <span class="material-symbols-outlined text-[14px] text-surface-400">table_rows</span>
                                {{ $endpoint->table_name }}
                            </div>
                        </td>
                        <td class="px-5 py-3 align-middle text-center">
                            <form action="{{ route('endpoint.api.toggle', $endpoint->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $endpoint->is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-surface-100 text-surface-500 hover:bg-surface-200' }} transition-colors">
                                    {{ $endpoint->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-5 py-3 align-middle text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('endpoint.api.edit', $endpoint->id) }}" class="p-1.5 text-surface-400 hover:text-brand-500 hover:bg-brand-50 rounded-lg transition-colors" title="Edit Endpoint">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form action="{{ route('endpoint.api.destroy', $endpoint->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this endpoint?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-surface-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Delete Endpoint">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-surface-500">
                            <span class="material-symbols-outlined text-[48px] text-surface-300 mb-3 block">api</span>
                            <p class="text-body-md font-medium text-surface-700">No Endpoints Found</p>
                            <p class="text-body-sm mt-1">Create your first endpoint to start exposing data.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function copyApiUrl(url, btn) {
        navigator.clipboard.writeText(url).then(() => {
            const icon = btn.querySelector('span');
            const originalText = icon.innerText;
            icon.innerText = 'check';
            icon.classList.add('text-green-500');
            setTimeout(() => {
                icon.innerText = originalText;
                icon.classList.remove('text-green-500');
            }, 1500);
        }).catch(err => {
            console.error('Failed to copy text: ', err);
        });
    }
</script>
@endsection