@extends('layouts.app')

@section('title', 'Manage Report Templates')
@section('page-title', 'Report Templates')

@section('content')
<div class="max-w-7xl mx-auto space-y-8 animate-fade-up">

    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 bg-white p-6 rounded-2xl border border-surface-200/80 shadow-sm relative overflow-hidden">
        <div class="absolute right-0 top-0 w-64 h-64 bg-gradient-to-br from-brand-100 to-transparent rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 opacity-60"></div>
        
        <div class="flex items-center gap-5 relative z-10">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center shadow-lg shadow-brand-500/20 shrink-0">
                <span class="material-symbols-outlined text-white text-[28px]" style="font-variation-settings: 'FILL' 1;">bookmarks</span>
            </div>
            <div>
                <h2 class="text-h2 text-surface-900 tracking-tight">Report Templates</h2>
                <p class="text-body-md text-surface-500 mt-1 max-w-xl">Manage your saved configurations. Access them quickly from the Report Builder to skip the setup process.</p>
            </div>
        </div>
        
        <div class="relative z-10 shrink-0">
            <a href="{{ route('report.builder') }}" class="group relative inline-flex items-center gap-2 px-6 py-3 bg-brand-600 text-white rounded-xl overflow-hidden transition-all hover:bg-brand-700 shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:shadow-sm">
                <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300 ease-out"></div>
                <span class="material-symbols-outlined text-[20px] relative z-10 transition-transform duration-300 group-hover:rotate-90">add</span>
                <span class="font-medium text-label-md relative z-10">New Template</span>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-xl bg-green-50 border border-green-200 flex items-start gap-3 animate-fade-in shadow-sm">
            <span class="material-symbols-outlined text-green-600 mt-0.5" style="font-variation-settings: 'FILL' 1;">check_circle</span>
            <div>
                <p class="text-label-md font-semibold text-green-800">Success</p>
                <p class="text-body-sm text-green-700 mt-0.5">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex border-b border-surface-200 gap-6">
        <button onclick="switchTab('my-templates')" id="tab-my-templates" class="pb-3 border-b-2 text-label-md transition-colors font-semibold border-brand-600 text-brand-700">
            My Templates
            <span class="ml-1.5 px-2 py-0.5 rounded-full text-[10px] bg-surface-100 text-surface-600">{{ $myTemplates->count() }}</span>
        </button>
        <button onclick="switchTab('shared-templates')" id="tab-shared-templates" class="pb-3 border-b-2 border-transparent text-label-md text-surface-500 hover:text-surface-700 transition-colors font-medium">
            Shared with Me
            <span class="ml-1.5 px-2 py-0.5 rounded-full text-[10px] bg-surface-100 text-surface-600">{{ $sharedTemplates->count() }}</span>
        </button>
    </div>

    {{-- Content: My Templates --}}
    <div id="content-my-templates" class="bg-white rounded-2xl border border-surface-200/80 shadow-sm overflow-hidden block">
        @if($myTemplates->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6 bg-surface-50/50">
                @foreach($myTemplates as $template)
                    @include('reports.templates.partials.template-card', ['template' => $template, 'isOwner' => true])
                @endforeach
            </div>
        @else
            @include('reports.templates.partials.empty-state', ['message' => 'You haven\'t saved any report templates yet.'])
        @endif
    </div>

    {{-- Content: Shared with Me --}}
    <div id="content-shared-templates" class="bg-white rounded-2xl border border-surface-200/80 shadow-sm overflow-hidden hidden">
        @if($sharedTemplates->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6 bg-surface-50/50">
                @foreach($sharedTemplates as $template)
                    @include('reports.templates.partials.template-card', ['template' => $template, 'isOwner' => false])
                @endforeach
            </div>
        @else
            @include('reports.templates.partials.empty-state', ['message' => 'No templates have been shared with you yet.'])
        @endif
    </div>

</div>

{{-- Share Modal --}}
<div id="share-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeShareModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-surface-200/60 shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col animate-fade-up overflow-hidden">
            <div class="px-5 py-4 border-b border-surface-100">
                <h3 class="text-h3 text-surface-800">Share Template</h3>
                <p class="text-body-sm text-surface-500 mt-1">Select roles that can access this template</p>
            </div>
            <div class="p-5 overflow-y-auto flex-1">
                <input type="hidden" id="share-template-id">
                
                <div id="share-loading" class="text-center py-8 hidden">
                    <span class="material-symbols-outlined animate-spin text-brand-600 text-[32px]">sync</span>
                    <p class="text-surface-500 text-sm mt-2">Loading roles...</p>
                </div>
                
                <div id="share-roles-list" class="space-y-3">
                    @foreach($allRoles as $role)
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-surface-200 hover:bg-surface-50 hover:border-brand-300 cursor-pointer transition-colors group">
                            <div class="mt-0.5">
                                <input type="checkbox" value="{{ $role->id }}" class="role-checkbox w-4 h-4 text-brand-600 bg-surface-100 border-surface-300 rounded focus:ring-brand-500 focus:ring-2">
                            </div>
                            <div class="flex-1">
                                <p class="text-label-md text-surface-900 group-hover:text-brand-700">{{ $role->name }}</p>
                                @if($role->description)
                                    <p class="text-body-sm text-surface-500 mt-0.5">{{ $role->description }}</p>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="px-5 py-4 border-t border-surface-100 flex justify-end gap-2 bg-surface-50">
                <button type="button" onclick="closeShareModal()" class="px-4 py-2 rounded-xl text-label-md text-surface-600 hover:bg-surface-200 transition-all duration-200">Cancel</button>
                <button type="button" id="share-save-btn" onclick="saveShareSettings()" class="px-4 py-2 bg-brand-600 text-white rounded-xl hover:bg-brand-700 transition-all duration-200 text-label-md shadow-sm active:scale-[0.97]">
                    Save Sharing
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        // Reset Tabs
        document.getElementById('tab-my-templates').className = 'pb-3 border-b-2 border-transparent text-label-md text-surface-500 hover:text-surface-700 transition-colors font-medium';
        document.getElementById('tab-shared-templates').className = 'pb-3 border-b-2 border-transparent text-label-md text-surface-500 hover:text-surface-700 transition-colors font-medium';
        
        // Hide content
        document.getElementById('content-my-templates').classList.add('hidden');
        document.getElementById('content-my-templates').classList.remove('block');
        document.getElementById('content-shared-templates').classList.add('hidden');
        document.getElementById('content-shared-templates').classList.remove('block');
        
        // Show active tab
        const activeTabBtn = document.getElementById('tab-' + tab);
        activeTabBtn.className = 'pb-3 border-b-2 text-label-md transition-colors font-semibold border-brand-600 text-brand-700';
        
        const activeContent = document.getElementById('content-' + tab);
        activeContent.classList.remove('hidden');
        activeContent.classList.add('block');
    }

    async function openShareModal(templateId) {
        document.getElementById('share-modal').classList.remove('hidden');
        document.getElementById('share-template-id').value = templateId;
        
        // Reset checkboxes
        document.querySelectorAll('.role-checkbox').forEach(cb => cb.checked = false);
        
        // Show loading
        document.getElementById('share-loading').classList.remove('hidden');
        document.getElementById('share-roles-list').classList.add('hidden');
        
        try {
            const response = await fetch(`/api/report-templates/${templateId}/shared-roles`);
            const data = await response.json();
            
            if(data.roles) {
                document.querySelectorAll('.role-checkbox').forEach(cb => {
                    if (data.roles.includes(parseInt(cb.value))) {
                        cb.checked = true;
                    }
                });
            }
        } catch (error) {
            console.error('Error fetching shared roles:', error);
            alert('Failed to load sharing settings.');
        } finally {
            document.getElementById('share-loading').classList.add('hidden');
            document.getElementById('share-roles-list').classList.remove('hidden');
        }
    }

    function closeShareModal() {
        document.getElementById('share-modal').classList.add('hidden');
    }

    async function saveShareSettings() {
        const templateId = document.getElementById('share-template-id').value;
        const btn = document.getElementById('share-save-btn');
        const roles = Array.from(document.querySelectorAll('.role-checkbox:checked')).map(cb => cb.value);
        
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-[18px]">sync</span> Saving...';
        
        try {
            const response = await fetch(`/api/report-templates/${templateId}/share`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ roles })
            });
            
            const data = await response.json();
            if(data.success) {
                closeShareModal();
                // Optionally show a toast notification here
            } else {
                alert(data.message || 'Error saving settings');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to save sharing settings.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Save Sharing';
        }
    }
</script>
@endsection
