@extends('layouts.app')

@section('title', 'Dashboard — Enterprise Sync Engine')
@section('page-title', 'Sync Dashboard')

@section('content')
{{-- Health Stats Row --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-lg fade-in">
    <div class="bg-surface-container-lowest border border-outline-variant rounded p-md flex items-center justify-between">
        <div class="flex items-center gap-md">
            <div class="p-2 bg-surface-container-low rounded">
                <span class="material-symbols-outlined text-secondary">cloud_done</span>
            </div>
            <div>
                <h3 class="font-label-md text-label-md text-on-surface-variant">Acumatica OData v4</h3>
                <p class="font-body-md text-body-md font-medium text-on-surface mt-xs" id="acm-conn-label">Checking…</p>
            </div>
        </div>
        <div id="acm-conn-badge" class="flex items-center gap-xs px-2 py-1 bg-gray-50 rounded">
            <div class="w-2 h-2 rounded-full bg-gray-400"></div>
            <span class="font-label-sm text-label-sm text-gray-500">…</span>
        </div>
    </div>
    <div class="bg-surface-container-lowest border border-outline-variant rounded p-md flex items-center justify-between">
        <div class="flex items-center gap-md">
            <div class="p-2 bg-surface-container-low rounded">
                <span class="material-symbols-outlined text-secondary">database</span>
            </div>
            <div>
                <h3 class="font-label-md text-label-md text-on-surface-variant" id="db-type-label">Database Server</h3>
                <p class="font-body-md text-body-md font-medium text-on-surface mt-xs" id="sql-conn-label">Checking…</p>
            </div>
        </div>
        <div id="sql-conn-badge" class="flex items-center gap-xs px-2 py-1 bg-gray-50 rounded">
            <div class="w-2 h-2 rounded-full bg-gray-400"></div>
            <span class="font-label-sm text-label-sm text-gray-500">…</span>
        </div>
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-lg fade-in" style="animation-delay:.05s">
    <div class="bg-surface-container-lowest border border-outline-variant rounded p-md flex flex-col">
        <span class="font-label-md text-label-md text-on-surface-variant mb-sm uppercase tracking-wider">Total Records Synced</span>
        <span class="font-display text-display text-on-surface">{{ number_format($stats['total_records']) }}</span>
        <div class="mt-auto pt-md flex items-center gap-sm text-on-surface-variant">
            <span class="material-symbols-outlined text-sm">database</span>
            <span class="font-label-sm text-label-sm">{{ $stats['total_entities'] }} entities configured</span>
        </div>
    </div>
    <div class="bg-surface-container-lowest border border-outline-variant rounded p-md flex flex-col">
        <span class="font-label-md text-label-md text-on-surface-variant mb-sm uppercase tracking-wider">Master Data</span>
        <span class="font-display text-display text-on-surface">{{ number_format($masterRecords) }}</span>
        <div class="mt-auto pt-md flex items-center gap-sm text-on-surface-variant">
            <span class="material-symbols-outlined text-sm">category</span>
            <span class="font-label-sm text-label-sm">{{ $stats['master_entities'] }} entities</span>
        </div>
    </div>
    <div class="bg-surface-container-lowest border border-outline-variant rounded p-md flex flex-col relative overflow-hidden">
        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full -mr-16 -mt-16"></div>
        <span class="font-label-md text-label-md text-on-surface-variant mb-sm uppercase tracking-wider">Transaction Data</span>
        <span class="font-display text-display text-on-surface">{{ number_format($transactionRecords) }}</span>
        <div class="mt-auto pt-md flex items-center gap-sm text-on-surface-variant">
            <span class="material-symbols-outlined text-sm">swap_horiz</span>
            <span class="font-label-sm text-label-sm">{{ $stats['transaction_entities'] }} entities</span>
        </div>
    </div>
</div>

{{-- Complex Layout Area --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-lg fade-in" style="animation-delay:.1s">
    {{-- Recent Activity Table --}}
    <div class="lg:col-span-2 bg-surface-container-lowest border border-outline-variant rounded flex flex-col">
        <div class="p-md border-b border-outline-variant flex justify-between items-center bg-surface-container-low/50">
            <h2 class="font-h2 text-h2 text-on-surface">Recent Activity</h2>
            <a href="{{ route('logs.index') }}" class="font-label-md text-label-md text-on-surface-variant hover:text-on-surface flex items-center gap-1">
                View All <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant">
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Entity</th>
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Status</th>
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Records</th>
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Duration</th>
                        <th class="py-2 px-md font-label-md text-label-md text-on-surface-variant font-medium">Time</th>
                    </tr>
                </thead>
                <tbody class="font-body-md text-body-md text-on-surface divide-y divide-outline-variant/50">
                    @forelse($recentLogs as $log)
                    <tr class="hover:bg-surface-container-low/50 transition-colors">
                        <td class="py-3 px-md font-medium">{{ $log->entity_description ?? $log->entity_key }}</td>
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
                        <td class="py-3 px-md font-code text-code">{{ number_format($log->records_inserted) }}</td>
                        <td class="py-3 px-md text-on-surface-variant">{{ $log->duration_seconds }}s</td>
                        <td class="py-3 px-md text-on-surface-variant text-sm">{{ $log->created_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-8 px-md text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-3xl mb-2 block opacity-40">sync_disabled</span>
                            No sync activity yet. Click <strong>Force Sync All</strong> to start.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Data Volume Split --}}
    <div class="bg-surface-container-lowest border border-outline-variant rounded flex flex-col p-md">
        <h2 class="font-h2 text-h2 text-on-surface mb-lg">Data Volume Split</h2>
        <div class="flex-1 flex flex-col justify-center gap-lg">
            @php
                $totalData = max($masterRecords + $transactionRecords, 1);
                $masterPct = round(($masterRecords / $totalData) * 100);
                $txnPct    = round(($transactionRecords / $totalData) * 100);
            @endphp
            <div>
                <div class="flex justify-between items-end mb-sm">
                    <span class="font-body-md text-body-md font-medium text-on-surface flex items-center gap-2">
                        <span class="w-3 h-3 rounded-sm bg-primary"></span>
                        Master Data
                    </span>
                    <span class="font-code text-code text-on-surface-variant">{{ number_format($masterRecords) }}</span>
                </div>
                <div class="w-full bg-surface-container-high h-2 rounded-full overflow-hidden">
                    <div class="bg-primary h-full transition-all duration-500" style="width: {{ $masterPct }}%"></div>
                </div>
                <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">{{ collect($stats['entity_stats'])->where('type', 'master')->pluck('description')->implode(', ') }}</p>
            </div>
            <div>
                <div class="flex justify-between items-end mb-sm">
                    <span class="font-body-md text-body-md font-medium text-on-surface flex items-center gap-2">
                        <span class="w-3 h-3 rounded-sm bg-secondary"></span>
                        Transactional Data
                    </span>
                    <span class="font-code text-code text-on-surface-variant">{{ number_format($transactionRecords) }}</span>
                </div>
                <div class="w-full bg-surface-container-high h-2 rounded-full overflow-hidden">
                    <div class="bg-secondary h-full transition-all duration-500" style="width: {{ $txnPct }}%"></div>
                </div>
                <p class="font-label-sm text-label-sm text-on-surface-variant mt-xs">{{ collect($stats['entity_stats'])->where('type', 'transaction')->pluck('description')->implode(', ') }}</p>
            </div>
        </div>

        {{-- Per-entity breakdown --}}
        <div class="mt-lg pt-lg border-t border-outline-variant">
            <h3 class="font-label-md text-label-md text-on-surface-variant mb-sm uppercase tracking-wider">Entity Breakdown</h3>
            <div class="space-y-1 max-h-48 overflow-y-auto">
                @foreach($stats['entity_stats'] as $key => $es)
                    <div class="flex justify-between items-center py-1">
                        <span class="font-label-sm text-label-sm text-on-surface truncate mr-2">{{ $es['description'] }}</span>
                        <span class="font-code text-code text-on-surface-variant whitespace-nowrap">{{ number_format($es['records']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await fetch('/api/connection-status');
        const data = await res.json();

        // Acumatica card
        const acmLabel = document.getElementById('acm-conn-label');
        const acmBadge = document.getElementById('acm-conn-badge');
        if (data.acumatica.connected) {
            acmLabel.textContent = 'Connected';
            acmBadge.innerHTML = `<div class="w-2 h-2 rounded-full bg-green-600"></div>
                <span class="font-label-sm text-label-sm text-green-700">${data.acumatica.ping_ms}ms ping</span>`;
            acmBadge.className = 'flex items-center gap-xs px-2 py-1 bg-green-50 rounded';
        } else {
            acmLabel.textContent = 'Disconnected';
            acmBadge.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-600"></div>
                <span class="font-label-sm text-label-sm text-red-700">Offline</span>`;
            acmBadge.className = 'flex items-center gap-xs px-2 py-1 bg-red-50 rounded';
        }

        // Database card
        const dbStatus = data.database || data.sql_server;
        const sqlLabel = document.getElementById('sql-conn-label');
        const sqlBadge = document.getElementById('sql-conn-badge');
        const dbTypeLabel = document.getElementById('db-type-label');
        
        if (dbStatus && dbStatus.type) {
            dbTypeLabel.textContent = dbStatus.type;
        }

        if (dbStatus && dbStatus.connected) {
            sqlLabel.textContent = 'Connected';
            sqlBadge.innerHTML = `<div class="w-2 h-2 rounded-full bg-green-600"></div>
                <span class="font-label-sm text-label-sm text-green-700">${dbStatus.ping_ms}ms ping</span>`;
            sqlBadge.className = 'flex items-center gap-xs px-2 py-1 bg-green-50 rounded';
        } else {
            sqlLabel.textContent = 'Disconnected';
            sqlBadge.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-600"></div>
                <span class="font-label-sm text-label-sm text-red-700">Offline</span>`;
            sqlBadge.className = 'flex items-center gap-xs px-2 py-1 bg-red-50 rounded';
        }
    } catch(e) {
        console.error(e);
    }
});
</script>
@endsection
