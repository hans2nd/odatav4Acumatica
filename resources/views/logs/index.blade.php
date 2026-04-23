@extends('layouts.app')

@section('title', 'Sync Logs — Enterprise Sync Engine')
@section('page-title', 'Sync Logs')

@section('content')
<div class="bg-surface-container-lowest border border-outline-variant rounded flex flex-col fade-in">
    <div class="p-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low/50">
        <h2 class="font-h2 text-h2 text-on-surface">All Sync Activity</h2>
        <span class="font-label-md text-label-md text-on-surface-variant">{{ $logs->total() }} total entries</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-surface-container-low border-b border-outline-variant">
                    <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">ID</th>
                    <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Entity</th>
                    <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Type</th>
                    <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Status</th>
                    <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Fetched</th>
                    <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Inserted</th>
                    <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Duration</th>
                    <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Started</th>
                    <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Error</th>
                </tr>
            </thead>
            <tbody class="font-body-md text-body-md text-on-surface divide-y divide-outline-variant/50">
                @forelse($logs as $log)
                <tr class="hover:bg-surface-container-low/50 transition-colors">
                    <td class="py-3 px-md font-code text-code text-on-surface-variant">#{{ $log->id }}</td>
                    <td class="py-3 px-md font-medium">{{ $log->entity_description ?? $log->entity_key }}</td>
                    <td class="py-3 px-md">
                        <span class="inline-flex items-center px-2 py-0.5 rounded font-label-sm text-label-sm
                            {{ $log->type === 'master' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-purple-50 text-purple-700 border border-purple-200' }}">
                            {{ ucfirst($log->type) }}
                        </span>
                    </td>
                    <td class="py-3 px-md">
                        @if($log->status === 'success')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-green-50 text-green-700 font-label-sm text-label-sm border border-green-200">
                                <div class="w-1.5 h-1.5 rounded-full bg-green-600"></div> Success
                            </span>
                        @elseif($log->status === 'running')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-yellow-50 text-yellow-700 font-label-sm text-label-sm border border-yellow-200">
                                <div class="w-1.5 h-1.5 rounded-full bg-yellow-600"></div> Running
                            </span>
                        @elseif($log->status === 'failed')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-red-50 text-red-700 font-label-sm text-label-sm border border-red-200">
                                <div class="w-1.5 h-1.5 rounded-full bg-red-600"></div> Failed
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-gray-50 text-gray-700 font-label-sm text-label-sm border border-gray-200">
                                <div class="w-1.5 h-1.5 rounded-full bg-gray-400"></div> {{ ucfirst($log->status) }}
                            </span>
                        @endif
                    </td>
                    <td class="py-3 px-md font-code text-code">{{ number_format($log->records_fetched) }}</td>
                    <td class="py-3 px-md font-code text-code">{{ number_format($log->records_inserted) }}</td>
                    <td class="py-3 px-md text-on-surface-variant">{{ $log->duration_seconds ?? '—' }}s</td>
                    <td class="py-3 px-md text-on-surface-variant text-sm">{{ $log->started_at?->format('d M Y H:i:s') ?? '—' }}</td>
                    <td class="py-3 px-md text-sm max-w-xs">
                        @if($log->error_message)
                            <span class="text-red-600 truncate block max-w-[200px]" title="{{ $log->error_message }}">
                                {{ Str::limit($log->error_message, 50) }}
                            </span>
                        @else
                            <span class="text-on-surface-variant">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="py-8 px-md text-center text-on-surface-variant">
                        <span class="material-symbols-outlined text-3xl mb-2 block opacity-40">receipt_long</span>
                        No sync logs yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="p-md border-t border-outline-variant flex justify-center">
            {{ $logs->links('pagination::simple-tailwind') }}
        </div>
    @endif
</div>
@endsection
