@extends('layouts.app')

@section('title', 'Dashboard — PL Sync Data Warehouse')
@section('page-title', 'Sync Dashboard')

@section('content')
{{-- Health Stats Row --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-5 animate-fade-up">
    {{-- Acumatica Card --}}
    <div class="bg-white rounded-2xl border border-surface-200/60 p-5 flex items-center justify-between shadow-card hover:shadow-card-hover transition-all duration-300">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-gradient-to-br from-sky-50 to-blue-100 rounded-xl">
                <span class="material-symbols-outlined text-sky-600 text-[22px]">cloud_done</span>
            </div>
            <div>
                <h3 class="text-label-md text-surface-500 uppercase tracking-wider">Acumatica OData v4</h3>
                <p class="text-body-lg font-semibold text-surface-800 mt-0.5" id="acm-conn-label">Checking…</p>
            </div>
        </div>
        <div id="acm-conn-badge" class="flex items-center gap-1.5 px-3 py-1.5 bg-surface-100 rounded-lg border border-surface-200/60">
            <div class="w-2 h-2 rounded-full bg-surface-400"></div>
            <span class="text-label-sm text-surface-500">…</span>
        </div>
    </div>
    {{-- Database Card --}}
    <div class="bg-white rounded-2xl border border-surface-200/60 p-5 flex items-center justify-between shadow-card hover:shadow-card-hover transition-all duration-300">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-gradient-to-br from-violet-50 to-purple-100 rounded-xl">
                <span class="material-symbols-outlined text-violet-600 text-[22px]">database</span>
            </div>
            <div>
                <h3 class="text-label-md text-surface-500 uppercase tracking-wider" id="db-type-label">Database Server</h3>
                <p class="text-body-lg font-semibold text-surface-800 mt-0.5" id="sql-conn-label">Checking…</p>
            </div>
        </div>
        <div id="sql-conn-badge" class="flex items-center gap-1.5 px-3 py-1.5 bg-surface-100 rounded-lg border border-surface-200/60">
            <div class="w-2 h-2 rounded-full bg-surface-400"></div>
            <span class="text-label-sm text-surface-500">…</span>
        </div>
    </div>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 animate-fade-up" style="animation-delay:.05s">
    <div class="bg-white rounded-2xl border border-surface-200/60 p-5 flex flex-col shadow-card hover:shadow-card-hover transition-all duration-300 relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-brand-50 to-transparent rounded-bl-[3rem] -mr-2 -mt-2 opacity-60 group-hover:opacity-100 transition-opacity"></div>
        <span class="text-label-md text-surface-500 mb-2 uppercase tracking-wider">Total Records Synced</span>
        <span class="text-display text-surface-900 relative">{{ number_format($stats['total_records']) }}</span>
        <div class="mt-auto pt-4 flex items-center gap-2 text-surface-500">
            <span class="material-symbols-outlined text-[16px]">database</span>
            <span class="text-label-sm">{{ $stats['total_entities'] }} entities configured</span>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-surface-200/60 p-5 flex flex-col shadow-card hover:shadow-card-hover transition-all duration-300 relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-sky-50 to-transparent rounded-bl-[3rem] -mr-2 -mt-2 opacity-60 group-hover:opacity-100 transition-opacity"></div>
        <span class="text-label-md text-surface-500 mb-2 uppercase tracking-wider">Master Data</span>
        <span class="text-display text-surface-900 relative">{{ number_format($masterRecords) }}</span>
        <div class="mt-auto pt-4 flex items-center gap-2 text-surface-500">
            <span class="material-symbols-outlined text-[16px]">category</span>
            <span class="text-label-sm">{{ $stats['master_entities'] }} entities</span>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-surface-200/60 p-5 flex flex-col shadow-card hover:shadow-card-hover transition-all duration-300 relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-bl from-violet-50 to-transparent rounded-bl-[3rem] -mr-2 -mt-2 opacity-60 group-hover:opacity-100 transition-opacity"></div>
        <span class="text-label-md text-surface-500 mb-2 uppercase tracking-wider">Transaction Data</span>
        <span class="text-display text-surface-900 relative">{{ number_format($transactionRecords) }}</span>
        <div class="mt-auto pt-4 flex items-center gap-2 text-surface-500">
            <span class="material-symbols-outlined text-[16px]">swap_horiz</span>
            <span class="text-label-sm">{{ $stats['transaction_entities'] }} entities</span>
        </div>
    </div>
</div>

{{-- Complex Layout Area --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 animate-fade-up" style="animation-delay:.1s">
    {{-- Recent Activity Table --}}
    <div class="lg:col-span-2 bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center">
            <h2 class="text-h3 text-surface-800">Recent Activity</h2>
            <a href="{{ route('logs.index') }}" class="text-label-md text-surface-500 hover:text-brand-600 flex items-center gap-1 transition-colors">
                View All <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-surface-100">
                        <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Entity</th>
                        <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Status</th>
                        <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Records</th>
                        <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Duration</th>
                        <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Time</th>
                    </tr>
                </thead>
                <tbody class="text-body-md text-surface-700 divide-y divide-surface-100">
                    @forelse($recentLogs as $log)
                    <tr class="hover:bg-surface-50/50 transition-colors">
                        <td class="py-3 px-5 font-medium text-surface-800">{{ $log->entity_description ?? $log->entity_key }}</td>
                        <td class="py-3 px-5">
                            @if($log->status === 'success')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-label-sm font-semibold">
                                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div> Success
                                </span>
                            @elseif($log->status === 'running')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-label-sm font-semibold">
                                    <div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse-dot"></div> Running
                                </span>
                            @elseif($log->status === 'failed')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-50 text-red-700 text-label-sm font-semibold">
                                    <div class="w-1.5 h-1.5 rounded-full bg-red-500"></div> Failed
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-surface-100 text-surface-600 text-label-sm font-semibold">
                                    <div class="w-1.5 h-1.5 rounded-full bg-surface-400"></div> {{ ucfirst($log->status) }}
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-5 font-mono text-body-sm">{{ number_format($log->records_inserted) }}</td>
                        <td class="py-3 px-5 text-surface-500 text-body-sm">{{ $log->duration_seconds }}s</td>
                        <td class="py-3 px-5 text-surface-500 text-body-sm">{{ $log->created_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 px-5 text-center text-surface-400">
                            <span class="material-symbols-outlined text-4xl mb-3 block">sync_disabled</span>
                            <p class="text-body-md">No sync activity yet. Click <strong class="text-surface-600">Force Sync All</strong> to start.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Data Volume Split --}}
    <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col p-5 shadow-card">
        <h2 class="text-h3 text-surface-800 mb-5">Data Volume Split</h2>
        <div class="flex-1 flex flex-col justify-center gap-5">
            @php
                $totalData = max($masterRecords + $transactionRecords, 1);
                $masterPct = round(($masterRecords / $totalData) * 100);
                $txnPct    = round(($transactionRecords / $totalData) * 100);
            @endphp
            <div>
                <div class="flex justify-between items-end mb-2">
                    <span class="text-body-md font-medium text-surface-700 flex items-center gap-2">
                        <span class="w-3 h-3 rounded bg-brand-500"></span>
                        Master Data
                    </span>
                    <span class="font-mono text-body-sm text-surface-500">{{ number_format($masterRecords) }}</span>
                </div>
                <div class="w-full bg-surface-100 h-2 rounded-full overflow-hidden">
                    <div class="bg-gradient-to-r from-brand-500 to-brand-400 h-full rounded-full transition-all duration-700" style="width: {{ $masterPct }}%"></div>
                </div>
                <p class="text-label-sm text-surface-400 mt-1.5">{{ collect($stats['entity_stats'])->where('type', 'master')->pluck('description')->implode(', ') }}</p>
            </div>
            <div>
                <div class="flex justify-between items-end mb-2">
                    <span class="text-body-md font-medium text-surface-700 flex items-center gap-2">
                        <span class="w-3 h-3 rounded bg-violet-500"></span>
                        Transactional Data
                    </span>
                    <span class="font-mono text-body-sm text-surface-500">{{ number_format($transactionRecords) }}</span>
                </div>
                <div class="w-full bg-surface-100 h-2 rounded-full overflow-hidden">
                    <div class="bg-gradient-to-r from-violet-500 to-violet-400 h-full rounded-full transition-all duration-700" style="width: {{ $txnPct }}%"></div>
                </div>
                <p class="text-label-sm text-surface-400 mt-1.5">{{ collect($stats['entity_stats'])->where('type', 'transaction')->pluck('description')->implode(', ') }}</p>
            </div>
        </div>

        {{-- Per-entity breakdown --}}
        <div class="mt-5 pt-5 border-t border-surface-100">
            <h3 class="text-label-md text-surface-500 mb-3 uppercase tracking-wider">Entity Breakdown</h3>
            <div class="space-y-1.5 max-h-48 overflow-y-auto">
                @foreach($stats['entity_stats'] as $key => $es)
                    <div class="flex justify-between items-center py-1.5 px-2 rounded-lg hover:bg-surface-50 transition-colors">
                        <span class="text-label-sm text-surface-700 truncate mr-2">{{ $es['description'] }}</span>
                        <span class="font-mono text-code text-surface-500 whitespace-nowrap">{{ number_format($es['records']) }}</span>
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
            acmBadge.innerHTML = `<div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                <span class="text-label-sm text-emerald-700">${data.acumatica.ping_ms}ms ping</span>`;
            acmBadge.className = 'flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 rounded-lg border border-emerald-200/60';
        } else {
            acmLabel.textContent = 'Disconnected';
            acmBadge.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-500"></div>
                <span class="text-label-sm text-red-700">Offline</span>`;
            acmBadge.className = 'flex items-center gap-1.5 px-3 py-1.5 bg-red-50 rounded-lg border border-red-200/60';
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
            sqlBadge.innerHTML = `<div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                <span class="text-label-sm text-emerald-700">${dbStatus.ping_ms}ms ping</span>`;
            sqlBadge.className = 'flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 rounded-lg border border-emerald-200/60';
        } else {
            sqlLabel.textContent = 'Disconnected';
            sqlBadge.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-500"></div>
                <span class="text-label-sm text-red-700">Offline</span>`;
            sqlBadge.className = 'flex items-center gap-1.5 px-3 py-1.5 bg-red-50 rounded-lg border border-red-200/60';
        }
    } catch(e) {
        console.error(e);
    }
});
</script>
@endsection
