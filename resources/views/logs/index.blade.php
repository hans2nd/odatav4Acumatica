@extends('layouts.app')

@section('title', 'Sync Logs — PL Sync Data Warehouse')
@section('page-title', 'Sync Logs')

@section('content')
<div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden animate-fade-up">
    <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center">
        <div class="flex items-center gap-4">
            <h2 class="text-h3 text-surface-800">All Sync Activity</h2>
            <span class="text-label-md text-surface-500 bg-surface-100 px-3 py-1 rounded-lg">{{ $logs->total() }} total entries</span>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('logs.index') }}" class="px-3 py-1.5 rounded-lg text-label-sm font-semibold transition-colors {{ !request('source') ? 'bg-surface-800 text-white' : 'bg-surface-100 text-surface-600 hover:bg-surface-200' }}">All</a>
            <a href="{{ route('logs.index', ['source' => 'manual']) }}" class="px-3 py-1.5 rounded-lg text-label-sm font-semibold transition-colors {{ request('source') === 'manual' ? 'bg-surface-800 text-white' : 'bg-surface-100 text-surface-600 hover:bg-surface-200' }}">Manual</a>
            <a href="{{ route('logs.index', ['source' => 'schedule']) }}" class="px-3 py-1.5 rounded-lg text-label-sm font-semibold transition-colors {{ request('source') === 'schedule' ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100' }}">Schedule</a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-surface-100">
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">ID</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Entity</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Type</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Source</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Status</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Fetched</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Inserted</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Duration</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Started</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Error</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider text-right">Action</th>
                </tr>
            </thead>
            <tbody class="text-body-md text-surface-700 divide-y divide-surface-100">
                @forelse($logs as $log)
                <tr class="hover:bg-surface-50/50 transition-colors">
                    <td class="py-3 px-5 font-mono text-body-sm text-surface-400">#{{ $log->id }}</td>
                    <td class="py-3 px-5 font-medium text-surface-800">{{ $log->entity_description ?? $log->entity_key }}</td>
                    <td class="py-3 px-5">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-label-sm font-semibold
                            {{ $log->type === 'master' ? 'bg-sky-50 text-sky-700' : 'bg-violet-50 text-violet-700' }}">
                            {{ ucfirst($log->type) }}
                        </span>
                    </td>
                    <td class="py-3 px-5">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-label-sm font-semibold
                            {{ $log->source === 'schedule' ? 'bg-indigo-50 text-indigo-700' : 'bg-surface-100 text-surface-700' }}">
                            <span class="material-symbols-outlined text-[14px] mr-1">{{ $log->source === 'schedule' ? 'schedule' : 'ads_click' }}</span>
                            {{ ucfirst($log->source ?? 'manual') }}
                        </span>
                    </td>
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
                    <td class="py-3 px-5 font-mono text-body-sm">{{ number_format($log->records_fetched) }}</td>
                    <td class="py-3 px-5 font-mono text-body-sm">{{ number_format($log->records_inserted) }}</td>
                    <td class="py-3 px-5 text-surface-500 text-body-sm">{{ $log->duration_seconds ?? '—' }}s</td>
                    <td class="py-3 px-5 text-surface-500 text-body-sm">{{ $log->started_at?->format('d M Y H:i:s') ?? '—' }}</td>
                    <td class="py-3 px-5">
                        @if($log->error_message)
                            <button onclick="showErrorModal(this)" data-error="{{ htmlspecialchars($log->error_message, ENT_QUOTES, 'UTF-8') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors text-label-sm">
                                <span class="material-symbols-outlined text-[16px]">visibility</span> View Error
                            </button>
                        @else
                            <span class="text-surface-400">—</span>
                        @endif
                    </td>
                    <td class="py-3 px-5 text-right">
                        @if($log->status === 'running')
                            <button onclick="stopSyncQueue({{ $log->id }}, this)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition-colors text-label-sm focus:outline-none focus:ring-2 focus:ring-red-500/20" title="Stop this sync task">
                                <span class="material-symbols-outlined text-[16px]">stop_circle</span> Stop
                            </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="py-12 px-5 text-center text-surface-400">
                        <span class="material-symbols-outlined text-4xl mb-3 block">receipt_long</span>
                        <p class="text-body-md">No sync logs yet.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="px-5 py-4 border-t border-surface-100 flex justify-center">
            {{ $logs->links('pagination::simple-tailwind') }}
        </div>
    @endif
</div>

<!-- Error Detail Modal -->
<div id="errorModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-surface-900/40 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[80vh] flex flex-col shadow-card-hover mx-4">
        <div class="px-6 py-4 border-b border-surface-100 flex justify-between items-center bg-red-50/50 rounded-t-2xl">
            <h3 class="text-h3 text-red-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-red-600">error</span>
                Error Details
            </h3>
            <button type="button" onclick="closeErrorModal()" class="text-surface-400 hover:text-surface-600 transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="px-6 py-4 overflow-y-auto bg-surface-50 flex-1">
            <pre id="errorModalContent" class="text-code font-mono text-surface-800 whitespace-pre-wrap break-words leading-relaxed"></pre>
        </div>
        <div class="px-6 py-4 border-t border-surface-100 flex justify-end rounded-b-2xl bg-white">
            <button type="button" onclick="closeErrorModal()" class="px-5 py-2.5 bg-surface-200 text-surface-700 font-label-lg rounded-xl hover:bg-surface-300 transition-all focus:ring-2 focus:ring-surface-400 outline-none">Close</button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    function showErrorModal(btn) {
        const modal = document.getElementById('errorModal');
        const content = document.getElementById('errorModalContent');
        content.textContent = btn.getAttribute('data-error');
        modal.classList.remove('hidden');
    }
    
    function closeErrorModal() {
        document.getElementById('errorModal').classList.add('hidden');
    }

    // Close on click outside
    document.getElementById('errorModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeErrorModal();
        }
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !document.getElementById('errorModal').classList.contains('hidden')) {
            closeErrorModal();
        }
    });

    async function stopSyncQueue(id, btn) {
        if (!confirm("Are you sure you want to stop this specific sync task?")) return;
        
        btn.disabled = true;
        btn.innerHTML = `<span class="material-symbols-outlined text-[16px] animate-spin">sync</span> Stopping...`;
        
        try {
            const res = await fetch(`/sync/stop/${id}`, {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json' 
                }
            });
            const data = await res.json();
            if (res.ok && data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert(data.message || 'Failed to stop queue');
            }
        } catch (e) {
            alert('Error: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = `<span class="material-symbols-outlined text-[16px]">stop_circle</span> Stop`;
        }
    }
</script>
@endsection
