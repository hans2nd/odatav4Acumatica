@extends('layouts.app')

@section('title', 'Dashboard — PL Sync Data Warehouse')
@section('page-title', 'Sync Dashboard')

@section('content')
{{-- Health Stats Row --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 animate-fade-up">
    @foreach($activeConnections as $key => $conn)
    <div class="bg-white rounded-2xl border border-surface-200/60 p-5 flex items-center justify-between shadow-card hover:shadow-card-hover transition-all duration-300">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-gradient-to-br {{ in_array($conn['type'], ['mysql', 'sqlsrv', 'firebird', 'local_env']) ? 'from-violet-50 to-purple-100 text-violet-600' : 'from-sky-50 to-blue-100 text-sky-600' }} rounded-xl">
                <span class="material-symbols-outlined text-[22px]">{{ in_array($conn['type'], ['mysql', 'sqlsrv', 'firebird', 'local_env']) ? 'database' : 'cloud_done' }}</span>
            </div>
            <div>
                <h3 class="text-label-md text-surface-500 uppercase tracking-wider">{{ $conn['name'] ?? $key }}</h3>
                <p class="text-body-lg font-semibold text-surface-800 mt-0.5" id="conn-label-{{ $key }}">Checking…</p>
            </div>
        </div>
        <div id="conn-badge-{{ $key }}" class="flex items-center gap-1.5 px-3 py-1.5 bg-surface-100 rounded-lg border border-surface-200/60">
            <div class="w-2 h-2 rounded-full bg-surface-400 animate-pulse"></div>
            <span class="text-label-sm text-surface-500">…</span>
        </div>
    </div>
    @endforeach
</div>

{{-- Complex Layout Area --}}
<div class="mt-5 animate-fade-up" style="animation-delay:.1s">
    {{-- Recent Activity Table --}}
    <div class="w-full bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden">
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
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await fetch('/api/connection-status');
        const data = await res.json();
        
        for (const [key, status] of Object.entries(data)) {
            const label = document.getElementById('conn-label-' + key);
            const badge = document.getElementById('conn-badge-' + key);
            
            if (label && badge) {
                if (status.connected) {
                    label.textContent = status.ping_ms ? `Connected (${status.ping_ms}ms)` : 'Connected';
                    badge.innerHTML = `<div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                        <span class="text-label-sm text-emerald-700">Online</span>`;
                    badge.className = 'flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 rounded-lg border border-emerald-200/60';
                } else {
                    label.textContent = 'Disconnected';
                    badge.innerHTML = `<div class="w-2 h-2 rounded-full bg-red-500"></div>
                        <span class="text-label-sm text-red-700">Offline</span>`;
                    badge.className = 'flex items-center gap-1.5 px-3 py-1.5 bg-red-50 rounded-lg border border-red-200/60';
                }
            }
        }
    } catch(e) {
        console.error('Failed to load connection statuses', e);
    }
});
</script>
@endsection
