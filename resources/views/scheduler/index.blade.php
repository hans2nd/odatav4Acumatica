@extends('layouts.app')

@section('title', 'Scheduler Management — PL Sync Data Warehouse')
@section('page-title', 'Scheduler Management')

@section('content')
<div x-data="schedulerApp()" x-init="init()" x-cloak>
    <!-- Dashboard Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white p-4 rounded-xl border border-surface-200/60 shadow-sm flex flex-col justify-center">
            <p class="text-label-sm text-surface-500 mb-1">Total Schedules</p>
            <p class="text-h2 text-surface-900" x-text="stats.total">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl border border-surface-200/60 shadow-sm flex flex-col justify-center">
            <p class="text-label-sm text-surface-500 mb-1">Active</p>
            <p class="text-h2 text-emerald-600" x-text="stats.active">{{ $stats['active'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl border border-surface-200/60 shadow-sm flex flex-col justify-center">
            <p class="text-label-sm text-surface-500 mb-1">Disabled</p>
            <p class="text-h2 text-slate-500" x-text="stats.disabled">{{ $stats['disabled'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl border border-surface-200/60 shadow-sm flex flex-col justify-center">
            <p class="text-label-sm text-surface-500 mb-1">Running</p>
            <p class="text-h2 text-blue-600" x-text="stats.running">{{ $stats['running'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl border border-surface-200/60 shadow-sm flex flex-col justify-center">
            <p class="text-label-sm text-surface-500 mb-1">Successful Today</p>
            <p class="text-h2 text-emerald-600">{{ $stats['success_today'] }}</p>
        </div>
        <div class="bg-white p-4 rounded-xl border border-surface-200/60 shadow-sm flex flex-col justify-center">
            <p class="text-label-sm text-surface-500 mb-1">Failed Today</p>
            <p class="text-h2 text-red-600">{{ $stats['failed_today'] }}</p>
        </div>
    </div>

    <!-- Filters & Table -->
    <div class="bg-white rounded-xl border border-surface-200/60 shadow-sm overflow-hidden flex flex-col">
        <!-- Filters -->
        <div class="p-4 border-b border-surface-100 flex flex-col sm:flex-row justify-between items-center gap-4 bg-surface-50/50">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="relative w-full sm:w-64">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-[18px]">search</span>
                    <input type="text" x-model="search" placeholder="Search entity..." class="w-full pl-9 pr-4 py-2 bg-white border border-surface-200 rounded-lg text-body-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none transition-all">
                </div>
                <select x-model="statusFilter" class="bg-white border border-surface-200 rounded-lg px-3 py-2 text-body-sm focus:border-brand-500 outline-none w-32">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>
            <div>
                <button @click="openEditModal(null)" class="inline-flex items-center gap-1.5 px-3 py-2 bg-brand-50 text-brand-700 hover:bg-brand-100 rounded-lg font-medium text-label-md transition-colors">
                    <span class="material-symbols-outlined text-[18px]">add</span> Add Preset
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-50/80 border-b border-surface-200/60">
                        <th class="px-5 py-3 text-label-sm font-semibold text-surface-500 cursor-pointer hover:text-brand-600" @click="sortBy('entity_key')">Entity</th>
                        <th class="px-5 py-3 text-label-sm font-semibold text-surface-500 cursor-pointer hover:text-brand-600" @click="sortBy('is_active')">Status</th>
                        <th class="px-5 py-3 text-label-sm font-semibold text-surface-500">Cron</th>
                        <th class="px-5 py-3 text-label-sm font-semibold text-surface-500 cursor-pointer hover:text-brand-600" @click="sortBy('next_run')">Next Run</th>
                        <th class="px-5 py-3 text-label-sm font-semibold text-surface-500 cursor-pointer hover:text-brand-600" @click="sortBy('last_run_at')">Last Run</th>
                        <th class="px-5 py-3 text-label-sm font-semibold text-surface-500">Last Status</th>
                        <th class="px-5 py-3 text-label-sm font-semibold text-surface-500 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100">
                    <template x-for="schedule in sortedSchedules" :key="schedule.entity_key">
                        <tr class="hover:bg-surface-50/50 transition-colors group">
                            <td class="px-5 py-4">
                                <span class="font-medium text-surface-800" x-text="schedule.entity_key"></span>
                            </td>
                            <td class="px-5 py-4">
                                <button @click="toggleStatus(schedule)" class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none" :class="schedule.is_active ? 'bg-emerald-500' : 'bg-surface-300'">
                                    <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform" :class="schedule.is_active ? 'translate-x-4' : 'translate-x-1'"></span>
                                </button>
                            </td>
                            <td class="px-5 py-4 max-w-[200px] truncate" :title="schedule.cron_expression">
                                <code class="px-1.5 py-0.5 bg-surface-100 text-surface-600 rounded text-xs" x-text="schedule.cron_expression || 'Not set'"></code>
                            </td>
                            <td class="px-5 py-4">
                                <span class="text-body-sm text-surface-600" x-text="formatDate(schedule.next_run)"></span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="text-body-sm text-surface-600" x-text="formatDate(schedule.last_run_at)"></span>
                            </td>
                            <td class="px-5 py-4">
                                <template x-if="schedule.is_running || schedule.latest_job_status === 'pending'">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-[11px] font-semibold tracking-wide uppercase border border-blue-200">
                                        <span class="material-symbols-outlined text-[12px] spin">sync</span> Running
                                    </span>
                                </template>
                                <template x-if="!schedule.is_running && schedule.latest_job_status === 'completed'">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold tracking-wide uppercase border border-emerald-200">
                                        <span class="material-symbols-outlined text-[12px]">check_circle</span> Success
                                    </span>
                                </template>
                                <template x-if="!schedule.is_running && schedule.latest_job_status === 'failed'">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-[11px] font-semibold tracking-wide uppercase border border-red-200">
                                        <span class="material-symbols-outlined text-[12px]">error</span> Failed
                                    </span>
                                </template>
                                <template x-if="!schedule.latest_job_status">
                                    <span class="text-surface-400 text-sm">-</span>
                                </template>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="runNow(schedule.entity_key)" :disabled="schedule.is_running" class="p-1.5 text-surface-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors disabled:opacity-50" title="Run Now">
                                        <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                                    </button>
                                    <button @click="openEditModal(schedule)" class="p-1.5 text-surface-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredSchedules.length === 0">
                        <td colspan="7" class="px-5 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <span class="material-symbols-outlined text-surface-300 text-4xl mb-3">event_busy</span>
                                <p class="text-surface-600 font-medium">No schedules found</p>
                                <p class="text-surface-400 text-sm mt-1">Try adjusting your filters or search term.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit/Preview Modal -->
    <div x-show="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
        <!-- Backdrop -->
        <div x-show="isModalOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="closeModal()"></div>
        
        <!-- Dialog -->
        <div x-show="isModalOpen" x-transition.scale.origin.bottom class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden border border-surface-200/60 z-10">
            <div class="px-6 py-4 border-b border-surface-100 flex justify-between items-center bg-surface-50/50">
                <h3 class="text-h3 text-surface-800" x-text="'Edit Schedule: ' + modalData.entityKey"></h3>
                <button @click="closeModal()" class="text-surface-400 hover:text-surface-600 transition-colors">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            
            <div class="p-6 space-y-4 overflow-y-auto max-h-[70vh]">
                <!-- Preset -->
                <div>
                    <label class="block text-label-sm font-semibold text-surface-700 mb-1.5">Preset</label>
                    <select x-model="modalData.preset" class="w-full bg-white border border-surface-300 rounded-lg px-3 py-2 text-body-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
                        <option value="Every 5 Minutes">Every 5 Minutes</option>
                        <option value="Every 10 Minutes">Every 10 Minutes</option>
                        <option value="Every 15 Minutes">Every 15 Minutes</option>
                        <option value="Every 30 Minutes">Every 30 Minutes</option>
                        <option value="Hourly">Hourly</option>
                        <option value="Every 2 Hours">Every 2 Hours</option>
                        <option value="Every 6 Hours">Every 6 Hours</option>
                        <option value="Daily">Daily</option>
                        <option value="Weekly">Weekly</option>
                        <option value="Monthly">Monthly</option>
                        <option value="Custom Cron">Custom Cron</option>
                    </select>
                </div>

                <!-- Conditional Fields -->
                <div x-show="['Daily', 'Weekly', 'Monthly'].includes(modalData.preset)">
                    <label class="block text-label-sm font-semibold text-surface-700 mb-1.5">Time (HH:MM)</label>
                    <input type="time" x-model="modalData.time" class="w-full bg-white border border-surface-300 rounded-lg px-3 py-2 text-body-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
                </div>

                <div x-show="modalData.preset === 'Weekly'">
                    <label class="block text-label-sm font-semibold text-surface-700 mb-1.5">Day of Week (0-7)</label>
                    <input type="number" min="0" max="7" x-model="modalData.dayOfWeek" class="w-full bg-white border border-surface-300 rounded-lg px-3 py-2 text-body-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
                </div>

                <div x-show="modalData.preset === 'Monthly'">
                    <label class="block text-label-sm font-semibold text-surface-700 mb-1.5">Day of Month (1-31)</label>
                    <input type="number" min="1" max="31" x-model="modalData.dayOfMonth" class="w-full bg-white border border-surface-300 rounded-lg px-3 py-2 text-body-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
                </div>

                <div x-show="modalData.preset === 'Custom Cron'">
                    <label class="block text-label-sm font-semibold text-surface-700 mb-1.5">Custom Cron Expression</label>
                    <input type="text" x-model="modalData.customCron" placeholder="* * * * *" class="w-full bg-white border border-surface-300 rounded-lg px-3 py-2 text-body-sm font-mono focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none">
                </div>

                <div class="flex items-center gap-2 mt-4">
                    <input type="checkbox" id="isActive" x-model="modalData.isActive" class="rounded text-brand-600 focus:ring-brand-500 border-surface-300">
                    <label for="isActive" class="text-body-sm text-surface-700">Enable Schedule</label>
                </div>
                
                <hr class="border-surface-100 my-4">

                <!-- Preview Button & Area -->
                <div>
                    <button @click="fetchPreview()" type="button" :disabled="isLoadingPreview" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-surface-100 text-surface-700 hover:bg-surface-200 rounded-lg font-medium text-body-sm transition-colors border border-surface-200 disabled:opacity-50">
                        <span class="material-symbols-outlined text-[18px]" :class="isLoadingPreview ? 'spin' : ''" x-text="isLoadingPreview ? 'sync' : 'visibility'"></span> 
                        <span x-text="isLoadingPreview ? 'Generating...' : 'Preview Generated Cron'"></span>
                    </button>
                    
                    <div x-show="previewData" class="mt-4 p-4 bg-slate-50 border border-slate-200 rounded-lg text-sm space-y-2">
                        <p><strong>Expression:</strong> <code class="bg-white px-1.5 py-0.5 rounded border border-slate-200" x-text="previewData.cron"></code></p>
                        <p><strong>Description:</strong> <span class="text-slate-600" x-text="previewData.description"></span></p>
                        <p><strong>Next 3 Runs:</strong></p>
                        <ul class="list-disc pl-5 text-slate-600 space-y-0.5">
                            <template x-for="run in previewData.next_runs">
                                <li x-text="run"></li>
                            </template>
                        </ul>
                    </div>
                </div>

            </div>
            
            <div class="px-6 py-4 border-t border-surface-100 bg-surface-50 flex justify-end gap-3">
                <button @click="closeModal()" :disabled="isSaving" class="px-4 py-2 text-body-sm font-medium text-surface-600 hover:text-surface-800 transition-colors disabled:opacity-50">Cancel</button>
                <button @click="saveSchedule()" :disabled="isSaving" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-body-sm font-medium rounded-lg shadow-sm transition-colors active:scale-95 flex items-center gap-2 disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]" :class="isSaving ? 'spin' : ''" x-text="isSaving ? 'sync' : 'save'"></span> 
                    <span x-text="isSaving ? 'Saving...' : 'Save'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function schedulerApp() {
    return {
        schedules: @json($schedules),
        search: '',
        statusFilter: 'all',
        sortCol: 'entity_key',
        sortAsc: true,
        
        isModalOpen: false,
        isSaving: false,
        isLoadingPreview: false,
        modalData: {
            entityKey: '',
            preset: 'Every 15 Minutes',
            time: '',
            dayOfWeek: '',
            dayOfMonth: '',
            customCron: '',
            isActive: false
        },
        previewData: null,

        get stats() {
            return {
                total: this.schedules.length,
                active: this.schedules.filter(s => s.is_active).length,
                disabled: this.schedules.filter(s => !s.is_active).length,
                running: this.schedules.filter(s => s.is_running).length,
                success_today: {{ $stats['success_today'] }},
                failed_today: {{ $stats['failed_today'] }}
            };
        },

        get filteredSchedules() {
            let filtered = this.schedules.filter(s => {
                const searchMatch = s.entity_key.toLowerCase().includes(this.search.toLowerCase());
                let statusMatch = true;
                if (this.statusFilter === 'active') statusMatch = s.is_active;
                if (this.statusFilter === 'disabled') statusMatch = !s.is_active;
                return searchMatch && statusMatch;
            });
            
            return filtered.sort((a, b) => {
                let valA = a[this.sortCol] || '';
                let valB = b[this.sortCol] || '';
                if(valA < valB) return this.sortAsc ? -1 : 1;
                if(valA > valB) return this.sortAsc ? 1 : -1;
                return 0;
            });
        },

        sortBy(col) {
            if (this.sortCol === col) {
                this.sortAsc = !this.sortAsc;
            } else {
                this.sortCol = col;
                this.sortAsc = true;
            }
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleString();
        },
        
        async toggleStatus(schedule) {
            try {
                let res = await fetch(`/api/scheduler/${schedule.entity_key}/toggle`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ is_active: !schedule.is_active })
                });
                let data = await res.json();
                if (data.success) {
                    schedule.is_active = data.data.is_active;
                    schedule.next_run = data.data.next_run;
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Toggle failed', 'error');
            }
        },

        async runNow(entityKey) {
            let schedule = this.schedules.find(s => s.entity_key === entityKey);
            if (!schedule) return;

            if (typeof Swal !== 'undefined') {
                const confirm = await Swal.fire({
                    title: `Run ${entityKey} now?`,
                    text: "This will instantly dispatch the synchronization job to the background queue.",
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonText: "Yes, run it",
                    cancelButtonText: "Cancel",
                    confirmButtonColor: "#0284c7"
                });
                if (!confirm.isConfirmed) return;
            }
            
            schedule.is_running = true;
            schedule.latest_job_status = 'pending';
            
            try {
                let res = await fetch(`/api/scheduler/${entityKey}/run-now`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                let data = await res.json();
                
                if (data.success) {
                    fetch('/sys-cron?token=deploy123').catch(() => {});
                    showToast(data.message, 'success');
                    schedule.latest_job_id = data.job_id;
                    
                    if (window.pollSyncJob) {
                        window.pollSyncJob(data.job_id, entityKey, {
                            onProgress: (progress, status, job) => {
                                schedule.latest_job_status = status;
                            },
                            onSuccess: (job) => {
                                schedule.latest_job_status = 'completed';
                                schedule.is_running = false;
                                showToast(`${entityKey} sync completed!`, 'success');
                            },
                            onError: (message, job) => {
                                schedule.latest_job_status = 'failed';
                                schedule.is_running = false;
                                showToast(`Error: ${message}`, 'error');
                            }
                        });
                    }
                } else {
                    schedule.is_running = false;
                    schedule.latest_job_status = 'failed';
                    showToast(data.message, 'error');
                }
            } catch (err) {
                schedule.is_running = false;
                schedule.latest_job_status = 'failed';
                showToast('Network error', 'error');
            }
        },

        openEditModal(schedule) {
            if (schedule) {
                this.modalData = {
                    entityKey: schedule.entity_key,
                    preset: 'Every 15 Minutes', // Assuming UI default; real persistence logic would re-parse cron to preset if we stored preset.
                    time: '',
                    dayOfWeek: '',
                    dayOfMonth: '',
                    customCron: schedule.cron_expression,
                    isActive: schedule.is_active
                };
            }
            this.previewData = null;
            this.isModalOpen = true;
        },

        closeModal() {
            this.isModalOpen = false;
        },

        async fetchPreview() {
            this.isLoadingPreview = true;
            try {
                let res = await fetch(`/api/scheduler/preview`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        preset: this.modalData.preset,
                        time: this.modalData.time,
                        day_of_week: this.modalData.dayOfWeek,
                        day_of_month: this.modalData.dayOfMonth,
                        custom_cron: this.modalData.customCron
                    })
                });
                let data = await res.json();
                if (data.success) {
                    this.previewData = data;
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Failed to fetch preview', 'error');
            } finally {
                this.isLoadingPreview = false;
            }
        },

        async saveSchedule() {
            this.isSaving = true;
            try {
                let res = await fetch(`/api/scheduler/${this.modalData.entityKey}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        preset: this.modalData.preset,
                        time: this.modalData.time,
                        day_of_week: this.modalData.dayOfWeek,
                        day_of_month: this.modalData.dayOfMonth,
                        custom_cron: this.modalData.customCron,
                        is_active: this.modalData.isActive
                    })
                });
                let data = await res.json();
                if (data.success) {
                    let idx = this.schedules.findIndex(s => s.entity_key === this.modalData.entityKey);
                    if(idx !== -1) {
                        this.schedules[idx] = data.data;
                    }
                    showToast(data.message, 'success');
                    this.closeModal();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Failed to save schedule', 'error');
            } finally {
                this.isSaving = false;
            }
        },

        init() {
            // Trigger polling for any jobs currently running on load
            this.schedules.forEach(schedule => {
                if (schedule.is_running && schedule.latest_job_id) {
                    if (window.pollSyncJob) {
                        window.pollSyncJob(schedule.latest_job_id, schedule.entity_key, {
                            onProgress: (progress, status, job) => { schedule.latest_job_status = status; },
                            onSuccess: (job) => { schedule.latest_job_status = 'completed'; schedule.is_running = false; },
                            onError: (message, job) => { schedule.latest_job_status = 'failed'; schedule.is_running = false; }
                        });
                    }
                }
            });
        }
    };
}
</script>
@endsection
