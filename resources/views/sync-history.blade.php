@extends('layouts.app')

@section('title', 'Sync History')
@section('page-title', 'Synchronization History')

@section('content')
<div class="space-y-6 animate-fade-up">
    <!-- Worker & Queue Live Monitor -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Running Workers Card -->
        <div class="bg-white rounded-2xl border border-surface-200/60 p-5 shadow-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-label-sm font-semibold text-surface-500 uppercase tracking-wider">Worker Sedang Berjalan</span>
                    <span class="flex h-2.5 w-2.5 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span id="queue-running-count" class="text-h2 font-bold text-amber-600 font-mono">{{ $queueStatus['running_count'] ?? 0 }}</span>
                    <span class="text-body-sm text-surface-500">worker aktif</span>
                </div>
            </div>
            <div id="queue-running-details" class="mt-4 pt-3 border-t border-surface-100 space-y-2 text-body-sm max-h-36 overflow-y-auto">
                @forelse($queueStatus['running_jobs'] ?? [] as $rJob)
                    <div class="flex items-center justify-between text-surface-700 bg-amber-50/70 px-3 py-1.5 rounded-lg border border-amber-200/60">
                        <div class="truncate mr-2">
                            <span class="font-medium text-amber-900">{{ $rJob['entity'] }}</span>
                            <span class="text-[10px] text-amber-600 block">{{ $rJob['started_at'] ?? 'Baru saja' }}</span>
                        </div>
                        <span class="font-mono text-xs font-semibold text-amber-800">{{ $rJob['progress'] }}%</span>
                    </div>
                @empty
                    <p class="text-xs text-surface-400 italic">Tidak ada worker yang sedang berjalan saat ini.</p>
                @endforelse
            </div>
        </div>

        <!-- Queued Workers Card -->
        <div class="bg-white rounded-2xl border border-surface-200/60 p-5 shadow-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-label-sm font-semibold text-surface-500 uppercase tracking-wider">Antrean Worker (Pending)</span>
                    <span class="material-symbols-outlined text-[20px] text-indigo-500">hourglass_top</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span id="queue-pending-count" class="text-h2 font-bold text-indigo-600 font-mono">{{ $queueStatus['pending_count'] ?? 0 }}</span>
                    <span class="text-body-sm text-surface-500">task mengantre</span>
                </div>
            </div>
            <div id="queue-pending-details" class="mt-4 pt-3 border-t border-surface-100 space-y-2 text-body-sm max-h-36 overflow-y-auto">
                @forelse($queueStatus['pending_jobs'] ?? [] as $pJob)
                    <div class="flex items-center justify-between text-surface-700 bg-indigo-50/70 px-3 py-1.5 rounded-lg border border-indigo-200/60">
                        <span class="font-medium text-indigo-900">{{ $pJob['entity'] }}</span>
                        <span class="text-xs text-indigo-600 font-mono">{{ $pJob['queued_at'] ?? 'Menunggu giliran' }}</span>
                    </div>
                @empty
                    <p class="text-xs text-surface-400 italic">Antrean kosong. Semua task telah selesai diproses.</p>
                @endforelse
            </div>
        </div>

        <!-- Locks & Engine Status Card -->
        <div class="bg-white rounded-2xl border border-surface-200/60 p-5 shadow-card flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-label-sm font-semibold text-surface-500 uppercase tracking-wider">Distributed Locks Held</span>
                    <span class="material-symbols-outlined text-[20px] text-surface-400">lock</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span id="queue-locks-count" class="text-h2 font-bold text-surface-800 font-mono">{{ count($queueStatus['active_locks'] ?? []) }}</span>
                    <span class="text-body-sm text-surface-500">entitas terkunci</span>
                </div>
            </div>
            <div id="queue-locks-details" class="mt-4 pt-3 border-t border-surface-100 space-y-2 text-body-sm max-h-36 overflow-y-auto">
                @forelse($queueStatus['active_locks'] ?? [] as $lock)
                    <div class="flex items-center justify-between text-surface-700 bg-surface-50 px-3 py-1.5 rounded-lg border border-surface-200">
                        <div class="truncate mr-2">
                            <span class="font-medium text-surface-800">{{ $lock['entity'] }}</span>
                            <span class="text-[10px] text-surface-400 block truncate">{{ $lock['owner'] }}</span>
                        </div>
                        <button type="button" onclick="unlockEntity('{{ $lock['entity'] }}')" class="shrink-0 px-2 py-0.5 rounded text-xs bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 transition font-medium">
                            Buka Kunci
                        </button>
                    </div>
                @empty
                    <p class="text-xs text-emerald-600 font-medium flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">check_circle</span> Semua entitas bebas kunci (Idle).
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Job History Card -->
    <div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center bg-surface-50">
            <div>
                <h2 class="text-h3 text-surface-800">Job History</h2>
                <p class="text-label-sm text-surface-500 mt-0.5">Riwayat eksekusi sinkronisasi data beserta status dan durasi.</p>
            </div>
            <button onclick="refreshPageData()" class="px-3.5 py-1.5 bg-white border border-surface-200 rounded-lg text-label-sm font-medium text-surface-600 hover:bg-surface-50 hover:text-surface-900 transition-colors flex items-center gap-1.5 shadow-sm">
                <span class="material-symbols-outlined text-[16px]">refresh</span> Refresh
            </button>
        </div>
    <div class="overflow-x-auto min-h-[400px]">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-surface-100 bg-white">
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Job ID</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Entity</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Status</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider min-w-[200px]">Progress</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider">Duration</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider text-right">Started At</th>
                    <th class="py-3 px-5 text-label-sm text-surface-500 font-semibold uppercase tracking-wider text-right">Action</th>
                </tr>
            </thead>
            <tbody class="text-body-md text-surface-700 divide-y divide-surface-100" id="sync-jobs-tbody">
                @forelse($jobs as $job)
                <tr id="job-row-{{ $job['job_id'] }}" class="hover:bg-surface-50/50 transition-colors group">
                    <td class="py-4 px-5 font-mono text-body-sm text-surface-500">
                        {{ substr($job['job_id'], 0, 8) }}...
                    </td>
                    <td class="py-4 px-5 font-medium text-surface-800">
                        {{ $job['entity'] }}
                        @if($job['status'] === 'failed' && !empty($job['error_message']))
                        <div class="mt-2 text-body-sm text-red-600 bg-red-50 p-2.5 rounded-lg border border-red-100 error-container">
                            {{ $job['error_message'] }}
                        </div>
                        @else
                        <div class="error-container"></div>
                        @endif
                    </td>
                    <td class="py-4 px-5 status-container">
                        @if($job['status'] === 'completed')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-label-sm font-semibold">
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div> Completed
                            </span>
                        @elseif($job['status'] === 'running')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-label-sm font-semibold">
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse-dot"></div> Running
                            </span>
                        @elseif($job['status'] === 'failed')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-50 text-red-700 text-label-sm font-semibold">
                                <div class="w-1.5 h-1.5 rounded-full bg-red-500"></div> Failed
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-surface-100 text-surface-600 text-label-sm font-semibold capitalize">
                                <div class="w-1.5 h-1.5 rounded-full bg-surface-400"></div> {{ $job['status'] }}
                            </span>
                        @endif
                    </td>
                    <td class="py-4 px-5">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-label-sm font-medium text-surface-700">{{ $job['progress'] }}%</span>
                            <span class="text-label-sm text-surface-500 progress-text">{{ number_format($job['processed_rows']) }} / {{ number_format($job['total_rows']) }}</span>
                        </div>
                        <div class="w-full bg-surface-100 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-brand-500 h-1.5 rounded-full transition-all duration-500 progress-bar-fill" style="width: {{ $job['progress'] }}%"></div>
                        </div>
                    </td>
                    <td class="py-4 px-5 text-surface-600 text-body-sm duration-text">
                        {{ $job['duration'] ?? '—' }}
                    </td>
                    <td class="py-4 px-5 text-surface-500 text-body-sm text-right whitespace-nowrap">
                        {{ $job['started_at'] ? \Carbon\Carbon::parse($job['started_at'])->format('M d, H:i:s') : '—' }}
                    </td>
                    <td class="py-4 px-5 text-right whitespace-nowrap">
                        <button 
                            onclick="abortJob('{{ $job['job_id'] }}')" 
                            class="abort-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 text-label-sm font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed {{ in_array($job['status'], ['pending', 'running']) ? '' : 'hidden' }}">
                            <span class="material-symbols-outlined text-[16px]">cancel</span> Abort
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="py-12 px-5 text-center text-surface-400">
                        <span class="material-symbols-outlined text-4xl mb-3 block">history</span>
                        <p class="text-body-md">No synchronization history found.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($paginator->hasPages())
    <div class="px-5 py-4 border-t border-surface-100 bg-surface-50">
        {{ $paginator->links() }}
    </div>
    @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    let pollingInterval = null;

    async function pollQueueStatus() {
        try {
            const res = await fetch('/api/sync-jobs/queue-status', {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            const resJson = await res.json();
            const data = resJson.data;
            if (!data) return;

            // 1. Running count & details
            const elRunning = document.getElementById('queue-running-count');
            if (elRunning) elRunning.textContent = data.running_count || 0;

            const elRunningDetails = document.getElementById('queue-running-details');
            if (elRunningDetails) {
                if (data.running_jobs && data.running_jobs.length > 0) {
                    elRunningDetails.innerHTML = data.running_jobs.map(j => `
                        <div class="flex items-center justify-between text-surface-700 bg-amber-50/70 px-3 py-1.5 rounded-lg border border-amber-200/60">
                            <div class="truncate mr-2">
                                <span class="font-medium text-amber-900">${j.entity}</span>
                                <span class="text-[10px] text-amber-600 block">${j.started_at || 'Baru saja'}</span>
                            </div>
                            <span class="font-mono text-xs font-semibold text-amber-800">${j.progress}%</span>
                        </div>
                    `).join('');
                } else {
                    elRunningDetails.innerHTML = `<p class="text-xs text-surface-400 italic">Tidak ada worker yang sedang berjalan saat ini.</p>`;
                }
            }

            // 2. Pending count & details
            const elPending = document.getElementById('queue-pending-count');
            if (elPending) elPending.textContent = data.pending_count || 0;

            const elPendingDetails = document.getElementById('queue-pending-details');
            if (elPendingDetails) {
                if (data.pending_jobs && data.pending_jobs.length > 0) {
                    elPendingDetails.innerHTML = data.pending_jobs.map(j => `
                        <div class="flex items-center justify-between text-surface-700 bg-indigo-50/70 px-3 py-1.5 rounded-lg border border-indigo-200/60">
                            <span class="font-medium text-indigo-900">${j.entity}</span>
                            <span class="text-xs text-indigo-600 font-mono">${j.queued_at || 'Menunggu giliran'}</span>
                        </div>
                    `).join('');
                } else {
                    elPendingDetails.innerHTML = `<p class="text-xs text-surface-400 italic">Antrean kosong. Semua task telah selesai diproses.</p>`;
                }
            }

            // 3. Locks count & details
            const elLocks = document.getElementById('queue-locks-count');
            if (elLocks) elLocks.textContent = (data.active_locks || []).length;

            const elLocksDetails = document.getElementById('queue-locks-details');
            if (elLocksDetails) {
                if (data.active_locks && data.active_locks.length > 0) {
                    elLocksDetails.innerHTML = data.active_locks.map(l => `
                        <div class="flex items-center justify-between text-surface-700 bg-surface-50 px-3 py-1.5 rounded-lg border border-surface-200">
                            <div class="truncate mr-2">
                                <span class="font-medium text-surface-800">${l.entity}</span>
                                <span class="text-[10px] text-surface-400 block truncate">${l.owner}</span>
                            </div>
                            <button type="button" onclick="unlockEntity('${l.entity}')" class="shrink-0 px-2 py-0.5 rounded text-xs bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 transition font-medium">
                                Buka Kunci
                            </button>
                        </div>
                    `).join('');
                } else {
                    elLocksDetails.innerHTML = `<p class="text-xs text-emerald-600 font-medium flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">check_circle</span> Semua entitas bebas kunci (Idle).</p>`;
                }
            }
        } catch (e) {
            console.error('Queue status polling error:', e);
        }
    }

    async function pollActiveJobs() {
        try {
            const res = await fetch('/api/sync-jobs/active', {
                headers: { 'Accept': 'application/json' }
            });
            
            if (!res.ok) return;
            
            const data = await res.json();
            const activeJobs = data.data;

            if (activeJobs && activeJobs.length > 0) {
                activeJobs.forEach(job => {
                    updateRow(job);
                });
            }
        } catch (e) {
            console.error('Active jobs polling failed:', e);
        }
    }

    function updateRow(job) {
        const row = document.getElementById(`job-row-${job.job_id}`);
        if (!row) return; // Job not visible on current page

        // Progress Bar & Text
        const progressBar = row.querySelector('.progress-bar-fill');
        if (progressBar) progressBar.style.width = `${job.progress}%`;

        const progressText = row.querySelector('.progress-text');
        if (progressText) {
            const pRows = (job.processed_rows || 0).toLocaleString();
            const tRows = (job.total_rows || 0).toLocaleString();
            progressText.textContent = `${pRows} / ${tRows}`;
            const pct = row.querySelector('.text-label-sm.font-medium');
            if (pct) pct.textContent = `${job.progress}%`;
        }

        // Status Badge
        const statusContainer = row.querySelector('.status-container');
        if (statusContainer) {
            statusContainer.innerHTML = getStatusBadgeHTML(job.status);
        }

        // Duration
        const durationText = row.querySelector('.duration-text');
        if (durationText && job.duration) {
            durationText.textContent = job.duration;
        }

        // Error message if failed
        if (job.status === 'failed' && job.error_message) {
            let errorContainer = row.querySelector('.error-container');
            if (errorContainer && errorContainer.innerHTML.trim() === '') {
                errorContainer.innerHTML = `<div class="mt-2 text-body-sm text-red-600 bg-red-50 p-2.5 rounded-lg border border-red-100">${job.error_message}</div>`;
            }
        }
        
        // Hide Abort Button if not running/pending
        const abortBtn = row.querySelector('.abort-btn');
        if (abortBtn) {
            if (job.status === 'running' || job.status === 'pending') {
                abortBtn.classList.remove('hidden');
            } else {
                abortBtn.classList.add('hidden');
            }
        }
    }

    function getStatusBadgeHTML(status) {
        if (status === 'completed') {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 text-label-sm font-semibold"><div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div> Completed</span>`;
        } else if (status === 'running') {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-label-sm font-semibold"><div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse-dot"></div> Running</span>`;
        } else if (status === 'failed') {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-red-50 text-red-700 text-label-sm font-semibold"><div class="w-1.5 h-1.5 rounded-full bg-red-500"></div> Failed</span>`;
        } else {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-surface-100 text-surface-600 text-label-sm font-semibold capitalize"><div class="w-1.5 h-1.5 rounded-full bg-surface-400"></div> ${status}</span>`;
        }
    }

    window.refreshPageData = async function() {
        await Promise.all([pollQueueStatus(), pollActiveJobs()]);
        window.location.reload();
    };

    window.unlockEntity = async function(entityKey) {
        const confirmed = typeof Swal !== 'undefined' ? await Swal.fire({
            title: `Buka Kunci ${entityKey}?`,
            text: `Kunci distributed dan status job untuk ${entityKey} akan di-reset agar dapat diproses kembali.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Buka Kunci',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc2626'
        }).then(r => r.isConfirmed) : confirm(`Reset kunci untuk ${entityKey}?`);

        if (!confirmed) return;

        try {
            const res = await fetch(`/sync/${entityKey}/reset-stuck`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });
            const data = await res.json();
            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Kunci berhasil dibuka.', 'success');
                } else {
                    alert(data.message || 'Kunci berhasil dibuka.');
                }
                pollQueueStatus();
                pollActiveJobs();
            } else {
                alert(data.message || 'Gagal membuka kunci.');
            }
        } catch (e) {
            console.error(e);
            alert('Terjadi kesalahan: ' + e.message);
        }
    };

    window.abortJob = async function(jobId) {
        if (!confirm('Are you sure you want to abort this synchronization job?')) return;
        
        const row = document.getElementById(`job-row-${jobId}`);
        const btn = row ? row.querySelector('.abort-btn') : null;
        if (btn) btn.disabled = true;

        try {
            const res = await fetch(`/api/sync-jobs/${jobId}/abort`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });

            const data = await res.json();
            if (data.success) {
                // Force an immediate poll to update the UI
                pollActiveJobs();
                pollQueueStatus();
            } else {
                alert(data.message || 'Failed to abort job.');
                if (btn) btn.disabled = false;
            }
        } catch (e) {
            console.error(e);
            alert('An error occurred while aborting the job.');
            if (btn) btn.disabled = false;
        }
    };

    // Polling active jobs and queue status every 4 seconds
    pollingInterval = setInterval(() => {
        pollActiveJobs();
        pollQueueStatus();
    }, 4000);
});
</script>
@endsection
