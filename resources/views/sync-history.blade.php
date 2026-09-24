@extends('layouts.app')

@section('title', 'Sync History')
@section('page-title', 'Synchronization History')

@section('content')
<div class="bg-white rounded-2xl border border-surface-200/60 flex flex-col shadow-card overflow-hidden animate-fade-up">
    <div class="px-5 py-4 border-b border-surface-100 flex justify-between items-center bg-surface-50">
        <h2 class="text-h3 text-surface-800">Job History</h2>
        <button onclick="window.location.reload()" class="px-3 py-1.5 bg-white border border-surface-200 rounded-lg text-label-sm text-surface-600 hover:bg-surface-50 transition-colors flex items-center gap-1.5 shadow-sm">
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
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    let pollingInterval = null;

    async function pollActiveJobs() {
        try {
            const res = await fetch('/api/sync-jobs/active', {
                headers: { 'Accept': 'application/json' }
            });
            
            if (!res.ok) return;
            
            const data = await res.json();
            const activeJobs = data.data;

            if (activeJobs.length === 0) {
                // No more active jobs, we can stop polling.
                clearInterval(pollingInterval);
                return;
            }

            activeJobs.forEach(job => {
                updateRow(job);
            });
        } catch (e) {
            console.error('Polling failed:', e);
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
            row.querySelector('.text-label-sm.font-medium').textContent = `${job.progress}%`;
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

    // Start polling every 3 seconds
    pollingInterval = setInterval(pollActiveJobs, 3000);
});
</script>
@endsection
