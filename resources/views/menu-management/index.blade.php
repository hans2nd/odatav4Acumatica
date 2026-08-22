@extends('layouts.app')

@section('title', 'Menu Management — PL Sync Data Warehouse')
@section('page-title', 'Menu Management')

@section('content')
    {{-- SortableJS CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

    <div class="flex flex-col xl:flex-row gap-6 h-auto xl:h-[calc(100vh-140px)] animate-fade-up">

        {{-- LEFT: Tree View --}}
        <div class="w-full xl:w-[50%] flex flex-col gap-4 h-full">

            {{-- Toolbar --}}
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="p-2 bg-gradient-to-br from-indigo-50 to-blue-100 rounded-lg shrink-0">
                        <span class="material-symbols-outlined text-indigo-600 text-[18px]">account_tree</span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2 class="text-h3 text-surface-800 truncate">Menu Tree</h2>
                            <div class="flex items-center gap-0.5 ml-1">
                                <button onclick="expandAllNodes()" title="Expand All" class="p-1 rounded-lg text-surface-400 hover:text-surface-700 hover:bg-surface-100 transition-colors">
                                    <span class="material-symbols-outlined text-[16px]">unfold_more</span>
                                </button>
                                <button onclick="collapseAllNodes()" title="Collapse All" class="p-1 rounded-lg text-surface-400 hover:text-surface-700 hover:bg-surface-100 transition-colors">
                                    <span class="material-symbols-outlined text-[16px]">unfold_less</span>
                                </button>
                            </div>
                        </div>
                        <p class="text-label-sm text-surface-400 mt-0.5 truncate">Drag & drop to reorder</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    {{-- Quick Add Input --}}
                    <div class="relative hidden md:block w-44 lg:w-52">
                        <input type="text" id="quickAddTitle" placeholder="⚡ Quick Add Menu..." 
                            onkeydown="if(event.key==='Enter') quickAddMenu()"
                            class="w-full px-3 py-1.5 text-xs bg-surface-50 border border-surface-200 rounded-xl focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none pr-8 transition-all">
                        <button onclick="quickAddMenu()" id="btnQuickAdd" title="Quick Add (Press Enter)" class="absolute right-1 top-1/2 -translate-y-1/2 p-1 text-brand-600 hover:bg-brand-50 rounded-lg transition-colors">
                            <span class="material-symbols-outlined text-[16px]">bolt</span>
                        </button>
                    </div>
                    <button onclick="openPreviewSidebar()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-surface-100 text-surface-700 text-label-md hover:bg-surface-200 transition-all duration-200">
                        <span class="material-symbols-outlined text-[16px]">preview</span> <span class="hidden sm:inline">Preview</span>
                    </button>
                    <button onclick="addRootMenu()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-brand-600 text-white text-label-md hover:bg-brand-700 transition-all duration-200 shadow-sm shrink-0">
                        <span class="material-symbols-outlined text-[16px]">add</span> Add Menu
                    </button>
                </div>
            </div>

            {{-- Tree Container --}}
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-card overflow-hidden flex-grow flex flex-col min-h-[400px]">
                <div id="menuTreeContainer" class="p-4 flex-grow overflow-y-auto">
                    <div id="menuTreeLoading" class="flex items-center justify-center py-16">
                        <span class="material-symbols-outlined text-brand-500 text-[24px] spin mr-2">sync</span>
                        <span class="text-surface-500 text-label-md">Loading menu tree...</span>
                    </div>
                    <div id="menuTree" class="space-y-1"></div>
                </div>
            </div>

            {{-- Publish Bar --}}
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-card p-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-500 text-[18px]">info</span>
                    <span class="text-label-sm text-surface-600">Changes are saved automatically. Click <strong>Publish</strong> to clear cache and apply to all users.</span>
                </div>
                <button onclick="publishMenus()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 text-white text-label-md hover:bg-emerald-700 transition-all duration-200 shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">publish</span> Publish
                </button>
            </div>
        </div>

        {{-- RIGHT: Permission Matrix Card (Full Height) --}}
        <div class="w-full xl:w-[50%] flex flex-col gap-4 h-full overflow-hidden">
            <div class="bg-white rounded-2xl border border-surface-200/60 shadow-card flex flex-col h-full overflow-hidden">
                <div class="px-5 py-4 border-b border-surface-100 flex items-center justify-between bg-surface-50/50">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-gradient-to-br from-amber-50 to-orange-100 rounded-lg">
                            <span class="material-symbols-outlined text-amber-600 text-[18px]">security</span>
                        </div>
                        <div>
                            <h2 class="text-h3 text-surface-800">Role & Permission Matrix</h2>
                            <p class="text-label-sm text-surface-400 mt-0.5">Menu visibility per role</p>
                        </div>
                    </div>
                    <button onclick="loadPermissionMatrix()" class="px-3 py-1.5 rounded-lg bg-surface-100 text-surface-600 text-label-sm hover:bg-surface-200 transition-all">
                        <span class="material-symbols-outlined text-[14px] align-middle">refresh</span> Refresh
                    </button>
                </div>
                <div id="permissionMatrixContainer" class="p-4 overflow-x-auto overflow-y-auto flex-grow">
                    <p class="text-center text-surface-400 text-label-sm py-8">Click "Refresh" to load the permission matrix.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- MENU EDITOR MODAL (POP-UP) --}}
    <div id="editorModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-surface-900/40 backdrop-blur-sm transition-opacity opacity-0" id="editorModalBackdrop" onclick="closeEditorModal()"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="relative w-full max-w-md bg-white shadow-2xl rounded-2xl opacity-0 scale-95 transition-all overflow-hidden flex flex-col max-h-[90vh]" id="editorModalPanel">
                
                {{-- Modal Header --}}
                <div class="px-5 py-4 border-b border-surface-100 flex items-center justify-between bg-surface-50/50">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-brand-50 rounded-xl text-brand-600 shrink-0">
                            <span class="material-symbols-outlined text-[20px]">add_box</span>
                        </div>
                        <div class="min-w-0">
                            <h3 id="editorTitle" class="text-base font-semibold text-surface-900 truncate">Menu Editor</h3>
                            <p id="editorSubtitle" class="text-xs text-surface-400 mt-0.5 truncate">Isi nama menu untuk memulai</p>
                        </div>
                    </div>
                    <button onclick="closeEditorModal()" class="p-1.5 rounded-xl text-surface-400 hover:text-surface-600 hover:bg-surface-100 transition-colors">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {{-- Modal Body (Ultra Simple) --}}
                <div id="editorForm" class="p-5 space-y-4 overflow-y-auto flex-grow">
                    <input type="hidden" id="editMenuId">
                    <input type="hidden" id="editRouteName">
                    <input type="checkbox" id="editGenerateFiles" class="hidden" checked>

                    {{-- 1. Title --}}
                    <div>
                        <label class="block text-xs font-semibold text-surface-700 uppercase tracking-wider mb-1.5">Nama Menu <span class="text-red-500">*</span></label>
                        <input type="text" id="editTitle" oninput="onInputTitle()" class="w-full px-3.5 py-2.5 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 text-sm focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition-all" placeholder="Contoh: Laporan Penjualan" autofocus>
                    </div>

                    {{-- 2. Icon Picker --}}
                    <div id="iconPickerGroup">
                        <label class="block text-xs font-semibold text-surface-700 uppercase tracking-wider mb-1.5">Pilih Ikon</label>
                        <div class="flex gap-2">
                            <div class="flex-1 relative">
                                <input type="text" id="editIcon" class="w-full px-3.5 py-2.5 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 text-sm focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition-all pl-9" placeholder="link" readonly>
                                <span id="iconPreview" class="material-symbols-outlined text-brand-600 text-[18px] absolute left-3 top-1/2 -translate-y-1/2" style="font-variation-settings: 'FILL' 1;">link</span>
                            </div>
                            <button onclick="openIconPicker()" class="px-3 py-2 rounded-xl bg-surface-100 text-surface-700 hover:bg-surface-200 text-xs font-medium transition-all" title="Browse Icons">
                                <span class="material-symbols-outlined text-[18px]">apps</span>
                            </button>
                        </div>
                        {{-- Quick Icon Chips --}}
                        <div id="quickIconChips" class="flex flex-wrap gap-1.5 mt-2"></div>
                    </div>

                    {{-- Info Box --}}
                    <div class="p-3 bg-indigo-50/60 border border-indigo-100/80 rounded-xl flex items-start gap-2.5">
                        <span class="material-symbols-outlined text-indigo-600 text-[18px] mt-0.5 shrink-0">auto_awesome</span>
                        <div class="text-xs text-indigo-950">
                            <strong>Otomatisasi Aktif:</strong> Route, Controller, dan View Blade akan otomatis dibuatkan oleh sistem berdasarkan Nama Menu yang Anda ketik.
                        </div>
                    </div>

                    {{-- Collapsible Advanced Options --}}
                    <div class="border-t border-surface-100 pt-3">
                        <button type="button" onclick="toggleAdvancedOptions()" class="flex items-center justify-between w-full text-xs font-medium text-surface-500 hover:text-surface-800 py-1 transition-colors">
                            <span class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">tune</span> Opsi Lanjutan (Custom Route, Tipe, Permission)
                            </span>
                            <span id="advArrow" class="material-symbols-outlined text-[18px] transition-transform duration-200">expand_more</span>
                        </button>
                        
                        <div id="advancedOptions" class="hidden space-y-3.5 pt-3">
                            {{-- Type --}}
                            <div>
                                <label class="block text-xs font-medium text-surface-700 mb-1">Tipe Menu</label>
                                <select id="editType" onchange="toggleEditorFields()" class="w-full px-3.5 py-2 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 text-xs outline-none">
                                    <option value="link">Link (Halaman Standard)</option>
                                    <option value="header">Header / Separator Kategori</option>
                                    <option value="divider">Garis Pemisah (Divider)</option>
                                </select>
                            </div>

                            {{-- Route Name Manual Override --}}
                            <div id="routePickerGroup">
                                <label class="block text-xs font-medium text-surface-700 mb-1">Custom Route Name</label>
                                <input type="text" id="editRouteNameManual" list="routeDataList" oninput="document.getElementById('editRouteName').value = this.value" class="w-full px-3.5 py-2 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 text-xs outline-none" placeholder="laporan-penjualan.index">
                                <datalist id="routeDataList"></datalist>
                                <p id="routePreviewUri" class="text-[11px] text-surface-400 mt-1 hidden"></p>
                            </div>

                            {{-- Query Repository Selection --}}
                            <div id="queryRepoGroup">
                                <label class="block text-xs font-medium text-surface-700 mb-1">Link to Saved Query (Auto UI)</label>
                                <select id="editQueryRepositoryId" onchange="loadQueryColumns(this.value)" class="w-full px-3.5 py-2 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 text-xs outline-none">
                                    <option value="">-- No Query (Blank Page) --</option>
                                    @foreach($queryRepositories as $query)
                                        <option value="{{ $query->id }}">{{ $query->name }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[11px] text-surface-400 mt-1">If selected, the system will auto-generate a data table and filter UI.</p>
                            </div>

                            {{-- Filter Configuration --}}
                            <div id="filterConfigGroup" class="hidden border border-surface-200 rounded-xl bg-surface-50/50 overflow-hidden mt-3">
                                <div class="px-3.5 py-2 bg-surface-100/50 border-b border-surface-200 flex justify-between items-center cursor-pointer" onclick="document.getElementById('filterConfigContent').classList.toggle('hidden')">
                                    <span class="text-xs font-semibold text-surface-700 flex items-center gap-1.5"><span class="material-symbols-outlined text-[16px]">filter_list</span> UI Filters Configuration</span>
                                    <span class="text-[10px] text-surface-500">Click to expand</span>
                                </div>
                                <div id="filterConfigContent" class="hidden p-3 space-y-3">
                                    <div class="flex gap-2">
                                        <select id="availableColumnsSelect" class="flex-1 px-3 py-1.5 bg-white border border-surface-200 rounded-lg text-xs outline-none">
                                            <option value="">-- Select Column --</option>
                                        </select>
                                        <button type="button" onclick="addFilterConfig()" class="px-3 py-1.5 bg-brand-600 text-white text-xs font-medium rounded-lg hover:bg-brand-700 transition-colors">Add Filter</button>
                                    </div>
                                    <div id="activeFiltersList" class="space-y-2">
                                        <!-- Filter Items will be rendered here -->
                                    </div>
                                    <p class="text-[10px] text-surface-400 mt-1">Leave empty to use all columns (legacy behavior).</p>
                                </div>
                            </div>

                            {{-- Permission Key --}}
                            <div>
                                <label class="block text-xs font-medium text-surface-700 mb-1">Permission Key</label>
                                <select id="editPermissionKey" class="w-full px-3.5 py-2 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 text-xs outline-none">
                                    <option value="">No restriction (visible to all)</option>
                                    @foreach($permissions as $perm)
                                        <option value="{{ $perm->permission_key }}">{{ $perm->permission_key }} — {{ $perm->description }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Badge --}}
                            <div>
                                <label class="block text-xs font-medium text-surface-700 mb-1">Badge (Label Tambahan)</label>
                                <input type="text" id="editBadge" class="w-full px-3.5 py-2 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 text-xs outline-none" placeholder="Contoh: New, Beta">
                            </div>

                            {{-- Toggles --}}
                            <div class="grid grid-cols-3 gap-2 pt-1">
                                <label class="flex items-center gap-2 px-3 py-2 bg-surface-50 rounded-xl border border-surface-200 cursor-pointer">
                                    <input type="checkbox" id="editIsActive" class="w-4 h-4 text-brand-600 rounded border-surface-300" checked>
                                    <span class="text-xs text-surface-700 font-medium">Active</span>
                                </label>
                                <label class="flex items-center gap-2 px-3 py-2 bg-surface-50 rounded-xl border border-surface-200 cursor-pointer">
                                    <input type="checkbox" id="editIsVisible" class="w-4 h-4 text-brand-600 rounded border-surface-300" checked>
                                    <span class="text-xs text-surface-700 font-medium">Visible</span>
                                </label>
                                <label class="flex items-center gap-2 px-3 py-2 bg-surface-50 rounded-xl border border-surface-200 cursor-pointer">
                                    <input type="checkbox" id="editOpenNewTab" class="w-4 h-4 text-brand-600 rounded border-surface-300">
                                    <span class="text-xs text-surface-700 font-medium">New Tab</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Modal Footer / Action buttons --}}
                <div class="px-5 py-3.5 border-t border-surface-100 bg-surface-50/50 flex items-center justify-between gap-2">
                    <button onclick="deleteMenuItem()" id="btnDeleteMenu" class="inline-flex items-center gap-1 px-3 py-2 rounded-xl bg-red-50 text-red-600 text-xs font-medium hover:bg-red-100 transition-all">
                        <span class="material-symbols-outlined text-[16px]">delete</span> Hapus
                    </button>
                    <div class="flex items-center gap-2 ml-auto">
                        <button onclick="closeEditorModal()" class="px-4 py-2 rounded-xl text-surface-600 hover:bg-surface-100 text-xs font-medium transition-all">
                            Batal
                        </button>
                        <button onclick="saveMenuItem()" id="btnSaveMenu" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-brand-600 text-white text-xs font-semibold hover:bg-brand-700 transition-all shadow-sm">
                            <span class="material-symbols-outlined text-[16px]">save</span> Simpan Menu
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Icon Picker Modal (Ultra Simple) --}}
    <div id="iconPickerModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-surface-900/40 backdrop-blur-sm transition-opacity opacity-0" id="iconPickerBackdrop" onclick="closeIconPicker()"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="relative w-full max-w-md bg-white shadow-2xl rounded-2xl opacity-0 scale-95 transition-all overflow-hidden flex flex-col" id="iconPickerPanel">
                
                {{-- Modal Header --}}
                <div class="px-5 py-3.5 border-b border-surface-100 flex items-center justify-between bg-surface-50/50">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-brand-600 text-[20px]">apps</span>
                        <h3 class="text-sm font-semibold text-surface-900">Pilih Ikon</h3>
                    </div>
                    <button onclick="closeIconPicker()" class="p-1 rounded-lg text-surface-400 hover:text-surface-600 hover:bg-surface-100 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-5">
                    {{-- Search Input --}}
                    <div class="relative mb-3.5">
                        <input type="text" id="iconSearchInput" oninput="debounceFilterIcons()" class="w-full px-3.5 py-2.5 bg-surface-50 border border-surface-200 rounded-xl text-surface-900 text-xs focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition-all pl-9" placeholder="Cari nama ikon...">
                        <span class="material-symbols-outlined text-surface-400 text-[18px] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">search</span>
                    </div>

                    {{-- Category Pills --}}
                    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-3.5 text-xs">
                        <button onclick="filterIconCategory('all', this)" class="icon-cat-btn px-3 py-1.5 rounded-lg bg-brand-600 text-white font-medium shadow-2xs transition-all shrink-0">Semua</button>
                        <button onclick="filterIconCategory('data', this)" class="icon-cat-btn px-3 py-1.5 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 font-medium transition-all shrink-0">Data</button>
                        <button onclick="filterIconCategory('folder', this)" class="icon-cat-btn px-3 py-1.5 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 font-medium transition-all shrink-0">File</button>
                        <button onclick="filterIconCategory('settings', this)" class="icon-cat-btn px-3 py-1.5 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 font-medium transition-all shrink-0">Sistem</button>
                        <button onclick="filterIconCategory('user', this)" class="icon-cat-btn px-3 py-1.5 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 font-medium transition-all shrink-0">User</button>
                    </div>

                    {{-- Pure Icon 6-Grid --}}
                    <div id="iconGrid" class="grid grid-cols-6 gap-2.5 max-h-[260px] overflow-y-auto p-2 border border-surface-200/60 rounded-xl bg-surface-50/40"></div>
                </div>

                {{-- Hover Icon Name Footer --}}
                <div class="px-4 py-2.5 border-t border-surface-100 bg-surface-50/50 flex items-center justify-between text-xs text-surface-500">
                    <div class="flex items-center gap-1.5">
                        <span id="hoverIconPreview" class="material-symbols-outlined text-[16px] text-brand-600">help</span>
                        <span id="hoverIconName" class="font-mono text-surface-700 font-medium">Hover ikon untuk melihat nama</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Preview Sidebar Modal --}}
    <div id="previewSidebarModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-surface-900/40 backdrop-blur-sm transition-opacity opacity-0" id="previewBackdrop" onclick="closePreviewSidebar()"></div>
        <div class="fixed left-0 top-0 h-full w-[280px] transition-transform duration-300 -translate-x-full z-[101]" id="previewPanel" style="background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);">
            <div class="px-5 py-4 border-b border-white/[0.06] flex items-center justify-between">
                <h3 class="text-white text-label-lg font-bold">Sidebar Preview</h3>
                <button onclick="closePreviewSidebar()" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
            <div id="previewSidebarContent" class="py-3 px-3 overflow-y-auto" style="max-height: calc(100vh - 64px);"></div>
        </div>
    </div>

@endsection

@section('scripts')
<script>
    console.log('Menu Management Script is executing...');
    // csrfToken is already declared globally in app.blade.php
    let menuTreeData = [];
    let allRoutes = [];
    let allIcons = [];
    let currentEditId = null;
    let sortableInstances = [];

    // ========== INITIALIZATION ==========
    async function initializeMenuManagement() {
        try {
            await Promise.all([loadMenuTree(), loadRoutes(), loadIcons()]);
        } catch (e) {
            console.error('Initialization error:', e);
            alert('A JavaScript error occurred during initialization: ' + e.message);
        }
    }
    
    // Call immediately since the script is at the end of the body
    initializeMenuManagement();

    // ========== LOAD DATA ==========
    async function updateSidebarNav() {
        try {
            const res = await fetch(window.location.href, {
                cache: 'no-store',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache'
                }
            });
            const text = await res.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, 'text/html');
            const newSidebar = doc.getElementById('sidebar');
            const currentSidebar = document.getElementById('sidebar');
            if (newSidebar && currentSidebar) {
                currentSidebar.innerHTML = newSidebar.innerHTML;
            }
        } catch (e) {
            console.error('Failed to auto-refresh sidebar navigation:', e);
        }
    }

    async function refreshAllData() {
        await Promise.all([
            loadMenuTree(),
            loadPermissionMatrix(),
            loadRoutes(),
            updateSidebarNav()
        ]);
    }

    async function loadMenuTree() {
        try {
            const baseUrl = '{{ route("api.menus.tree", [], false) }}';
            const res = await fetch(`${baseUrl}?_t=${Date.now()}`, {
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache'
                }
            });
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.message || 'Unknown API error');
            }
            menuTreeData = data.tree || [];
            renderTree();
        } catch (e) {
            console.error('Failed to load menu tree:', e);
            const container = document.getElementById('menuTreeContainer');
            if (container) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <span class="material-symbols-outlined text-red-500 text-[48px] mb-3">error</span>
                        <p class="text-surface-800 text-label-lg font-bold">Failed to load data</p>
                        <p class="text-surface-500 text-label-sm mt-1 max-w-md">${e.message}</p>
                        <p class="text-surface-400 text-[11px] mt-4 font-mono">${window.location.origin}{{ route("api.menus.tree", [], false) }}</p>
                    </div>`;
            }
        }
    }

    async function loadRoutes() {
        try {
            const res = await fetch('{{ route("api.menus.routes", [], false) }}');
            const data = await res.json();
            allRoutes = data.routes || [];

            const dataList = document.getElementById('routeDataList');
            dataList.innerHTML = '';
            allRoutes.forEach(r => {
                const opt = document.createElement('option');
                opt.value = r.name;
                opt.label = `${r.name} → ${r.uri}`;
                dataList.appendChild(opt);
            });

            // Live preview of URI
            document.getElementById('editRouteName').addEventListener('input', function() {
                const found = allRoutes.find(r => r.name === this.value);
                const preview = document.getElementById('routePreviewUri');
                if (found) {
                    preview.textContent = `URI: ${found.uri} [${found.methods.join(', ')}]`;
                    preview.classList.remove('hidden');
                } else {
                    preview.classList.add('hidden');
                }
            });
        } catch (e) {
            console.error('Failed to load routes:', e);
        }
    }

    async function loadIcons() {
        try {
            const res = await fetch('{{ route("api.menus.icons", [], false) }}');
            const data = await res.json();
            allIcons = data.icons || [];
        } catch (e) {
            console.error('Failed to load icons:', e);
        }
    }

    // ========== TREE RENDERING ==========
    let collapsedState = {};

    function toggleNodeCollapse(id) {
        collapsedState[id] = !collapsedState[id];
        renderTree();
    }

    function expandAllNodes() {
        collapsedState = {};
        renderTree();
    }

    function collapseAllNodes() {
        function collectAllIds(items) {
            items.forEach(item => {
                if (item.children && item.children.length > 0) {
                    collapsedState[item.id] = true;
                    collectAllIds(item.children);
                }
            });
        }
        collectAllIds(menuTreeData);
        renderTree();
    }

    function renderTree() {
        document.getElementById('menuTreeLoading').classList.add('hidden');
        const container = document.getElementById('menuTree');
        container.innerHTML = '';

        if (menuTreeData.length === 0) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <span class="material-symbols-outlined text-surface-200 text-[48px] mb-3">menu_open</span>
                    <p class="text-surface-400 text-label-lg">No menus configured yet</p>
                    <p class="text-surface-300 text-label-sm mt-1">Click "Add Menu" to get started</p>
                </div>`;
            return;
        }

        const list = buildTreeDom(menuTreeData, 0);
        container.appendChild(list);
        initSortable();
    }

    function buildTreeDom(items, depth) {
        const ul = document.createElement('div');
        ul.className = 'menu-tree-list space-y-1';
        ul.dataset.depth = depth;

        items.forEach(item => {
            const li = document.createElement('div');
            li.className = 'menu-tree-node';
            li.dataset.id = item.id;
            li.dataset.parentId = item.parent_id || '';

            const statusClasses = !item.is_active
                ? 'opacity-50 border-red-200 bg-red-50/50'
                : !item.is_visible
                    ? 'opacity-60 border-amber-200 bg-amber-50/30'
                    : currentEditId === item.id
                        ? 'border-brand-300 bg-brand-50/50 ring-2 ring-brand-500/20'
                        : 'border-surface-200 bg-white hover:border-brand-200 hover:bg-brand-50/20';

            const itemDiv = document.createElement('div');
            itemDiv.className = `flex items-center gap-2 px-3 py-2 rounded-xl border cursor-pointer transition-all duration-200 group ${statusClasses}`;
            itemDiv.onclick = () => selectMenuItem(item.id);
            itemDiv.style.marginLeft = `${depth * 20}px`;

            const hasChildren = item.children && item.children.length > 0;
            const isCollapsed = !!collapsedState[item.id];

            itemDiv.innerHTML = `
                <span class="material-symbols-outlined text-surface-300 text-[16px] cursor-grab drag-handle">drag_indicator</span>
                ${hasChildren ? `
                    <button onclick="event.stopPropagation(); toggleNodeCollapse(${item.id})" class="p-0.5 hover:bg-surface-100 rounded text-surface-500 hover:text-surface-800 transition-transform ${isCollapsed ? '-rotate-90' : ''}" title="${isCollapsed ? 'Expand' : 'Collapse'}">
                        <span class="material-symbols-outlined text-[16px]">expand_more</span>
                    </button>
                ` : `
                    <span class="w-5 inline-block"></span>
                `}
                <span class="material-symbols-outlined text-[18px] ${item.is_active ? 'text-brand-500' : 'text-surface-300'}" style="font-variation-settings: 'FILL' 1;">${item.icon || 'circle'}</span>
                <span class="text-[13px] font-medium ${item.is_active ? 'text-surface-800' : 'text-surface-400'} flex-1 truncate">${item.title}</span>
                ${item.route_name ? `<span class="text-[10px] text-surface-400 bg-surface-100 px-1.5 py-0.5 rounded font-mono hidden group-hover:inline">${item.route_name}</span>` : ''}
                ${item.badge ? `<span class="text-[10px] text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded font-medium">${item.badge}</span>` : ''}
                ${!item.is_active ? '<span class="material-symbols-outlined text-[14px] text-red-400">block</span>' : ''}
                ${!item.is_visible ? '<span class="material-symbols-outlined text-[14px] text-amber-400">visibility_off</span>' : ''}
                ${hasChildren ? `<span class="text-[10px] text-surface-400 bg-surface-100 px-1.5 py-0.5 rounded font-medium">${item.children.length}</span>` : ''}
                
                {{-- Hover Actions (Edit, Add Sub & Delete) --}}
                <div class="opacity-0 group-hover:opacity-100 flex items-center gap-1 transition-opacity">
                    <button onclick="event.stopPropagation(); openEditMenuModal(${item.id})" 
                        title="Edit '${item.title}'" 
                        class="p-1 rounded-lg text-surface-500 hover:text-brand-600 hover:bg-surface-100 transition-colors">
                        <span class="material-symbols-outlined text-[15px]">edit</span>
                    </button>
                    <button onclick="event.stopPropagation(); triggerAddChildMenu(${item.id})" 
                        title="Tambah Sub-Menu di bawah '${item.title}'" 
                        class="flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-semibold bg-brand-600 text-white hover:bg-brand-700 transition-all shadow-xs">
                        <span class="material-symbols-outlined text-[13px]">add</span>
                        <span>Sub</span>
                    </button>
                    <button onclick="event.stopPropagation(); triggerDeleteDirect(${item.id}, '${item.title.replace(/'/g, "\\'")}')" 
                        title="Hapus '${item.title}'" 
                        class="p-1 rounded-lg text-surface-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                        <span class="material-symbols-outlined text-[15px]">delete</span>
                    </button>
                </div>
            `;
            
            li.appendChild(itemDiv);

            if (hasChildren) {
                const childList = buildTreeDom(item.children, depth + 1);
                if (isCollapsed) {
                    childList.classList.add('hidden');
                }
                li.appendChild(childList);
            }
            ul.appendChild(li);
        });

        return ul;
    }

    function initSortable() {
        // Destroy old instances safely
        sortableInstances.forEach(s => {
            try { s.destroy(); } catch (e) {}
        });
        sortableInstances = [];

        document.querySelectorAll('.menu-tree-list').forEach(el => {
            const s = new Sortable(el, {
                group: 'menu-tree',
                animation: 200,
                handle: '.drag-handle',
                ghostClass: 'opacity-30',
                fallbackOnBody: true,
                swapThreshold: 0.65,
                onEnd: saveTreeOrder,
            });
            sortableInstances.push(s);
        });
    }

    // ========== SAVE TREE ORDER ==========
    function saveTreeOrder() {
        const allItems = [];
        let orderCounter = 0;

        function walkDom(container, parentId) {
            Array.from(container.children).forEach(el => {
                if (el.classList.contains('menu-tree-node')) {
                    allItems.push({
                        id: parseInt(el.dataset.id),
                        parent_id: parentId,
                        sort_order: orderCounter * 10,
                    });
                    orderCounter++;

                    // Find child list (if any)
                    const childList = el.querySelector(':scope > .menu-tree-list');
                    if (childList) {
                        walkDom(childList, parseInt(el.dataset.id));
                    }
                }
            });
        }

        const rootList = document.querySelector('#menuTree > .menu-tree-list');
        if (rootList) walkDom(rootList, null);

        fetch('{{ route("api.menus.reorder", [], false) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ items: allItems }),
        }).then(res => res.json()).then(async data => {
            if (data.success) {
                showToast('Menu order saved!', 'success');
                await refreshAllData();
            }
        });
    }

    // ========== EDITOR MODAL LOGIC ==========
    function openEditorModal() {
        const modal = document.getElementById('editorModal');
        const backdrop = document.getElementById('editorModalBackdrop');
        const panel = document.getElementById('editorModalPanel');

        modal.classList.remove('hidden');
        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            panel.classList.remove('opacity-0', 'scale-95');
            panel.classList.add('opacity-100', 'scale-100');
        });
    }

    function closeEditorModal() {
        const modal = document.getElementById('editorModal');
        const backdrop = document.getElementById('editorModalBackdrop');
        const panel = document.getElementById('editorModalPanel');

        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
        panel.classList.remove('opacity-100', 'scale-100');
        panel.classList.add('opacity-0', 'scale-95');

        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    // ========== SELECT / EDIT MENU ITEM ==========
    function selectMenuItem(id) {
        currentEditId = id;
        const item = findMenuById(menuTreeData, id);
        if (!item) return;

        document.getElementById('editorTitle').textContent = `✏️ Edit: ${item.title}`;
        document.getElementById('editorSubtitle').textContent = item.parent_id ? `Sub-menu di bawah ID #${item.parent_id}` : 'Menu Utama (Root Menu)';

        document.getElementById('editMenuId').value = item.id;
        document.getElementById('editTitle').value = item.title || '';
        document.getElementById('editType').value = item.type || 'link';
        document.getElementById('editRouteName').value = item.route_name || '';
        const manualRoute = document.getElementById('editRouteNameManual');
        if (manualRoute) manualRoute.value = item.route_name || '';
        document.getElementById('editIcon').value = item.icon || '';
        document.getElementById('editPermissionKey').value = item.permission_key || '';
        document.getElementById('editBadge').value = item.badge || '';
        document.getElementById('editIsActive').checked = item.is_active !== false;
        document.getElementById('editIsVisible').checked = item.is_visible !== false;
        document.getElementById('editOpenNewTab').checked = item.open_in_new_tab === true;
        document.getElementById('editQueryRepositoryId').value = item.query_repository_id || '';

        // Reset and Load Filter Config
        activeFilterConfig = Array.isArray(item.filter_config) ? item.filter_config : (item.filter_config === null ? null : []);
        if (item.query_repository_id) {
            loadQueryColumns(item.query_repository_id);
        } else {
            document.getElementById('filterConfigGroup').classList.add('hidden');
        }

        // Update icon preview
        const iconPreview = document.getElementById('iconPreview');
        iconPreview.textContent = item.icon || 'help';

        // Show/hide route URI preview
        const found = allRoutes.find(r => r.name === item.route_name);
        const preview = document.getElementById('routePreviewUri');
        if (found) {
            preview.textContent = `URI: ${found.uri} [${found.methods.join(', ')}]`;
            preview.classList.remove('hidden');
        } else {
            preview.classList.add('hidden');
        }

        const iconInfo = smartDetectIcon(item.title);
        renderQuickIconChips(iconInfo.chips);

        const btnDelete = document.getElementById('btnDeleteMenu');
        if (btnDelete) btnDelete.classList.remove('hidden');

        toggleEditorFields();
        renderTree(); // Re-render to highlight selected
    }

    function openEditMenuModal(id) {
        selectMenuItem(id);
        openEditorModal();
    }

    function findMenuById(items, id) {
        for (const item of items) {
            if (item.id === id) return item;
            if (item.children) {
                const found = findMenuById(item.children, id);
                if (found) return found;
            }
        }
        return null;
    }

    function toggleEditorFields() {
        const type = document.getElementById('editType').value;
        const isLink = type === 'link';
        document.getElementById('routePickerGroup').classList.toggle('hidden', !isLink);
        document.getElementById('queryRepoGroup').classList.toggle('hidden', !isLink);
        document.getElementById('iconPickerGroup').classList.toggle('hidden', type === 'divider');
    }

    // ========== SAVE MENU ITEM ==========
    async function saveMenuItem() {
        const id = document.getElementById('editMenuId').value;
        const editEl = document.getElementById('editMenuId');
        const parentId = editEl.dataset.parentId || null;

        const payload = {
            title: document.getElementById('editTitle').value,
            type: document.getElementById('editType').value,
            route_name: document.getElementById('editRouteName').value || null,
            icon: document.getElementById('editIcon').value || null,
            permission_key: document.getElementById('editPermissionKey').value || null,
            badge: document.getElementById('editBadge').value || null,
            is_active: document.getElementById('editIsActive').checked,
            is_visible: document.getElementById('editIsVisible').checked,
            open_in_new_tab: document.getElementById('editOpenNewTab').checked,
            generate_files: document.getElementById('editGenerateFiles')?.checked || false,
            query_repository_id: document.getElementById('editQueryRepositoryId').value || null,
            filter_config: activeFilterConfig, // Can be null, [], or array of filters
        };

        // Include parent_id when creating a new child menu
        if (!id && parentId) {
            payload.parent_id = parseInt(parentId);
        }

        const btn = document.getElementById('btnSaveMenu');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-outlined text-[16px] spin">sync</span> Saving...';
        btn.disabled = true;

        try {
            const url = id ? `/api/menus/${id}` : '/api/menus';
            const method = id ? 'PUT' : 'POST';
            const res = await fetch(url, {
                method,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                closeEditorModal();
                await refreshAllData();
                if (data.menu) selectMenuItem(data.menu.id);
            } else {
                showToast(data.message || 'Failed to save', 'error');
            }
        } catch (e) {
            showToast('Network error', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    // ========== ADD MENU ==========
    function addRootMenu() {
        currentEditId = null;
        document.getElementById('editorTitle').textContent = '➕ Add Root Menu';
        document.getElementById('editorSubtitle').textContent = 'Menambahkan menu utama pada tingkat paling atas (Root)';

        document.getElementById('editMenuId').value = '';
        document.getElementById('editTitle').value = '';
        document.getElementById('editType').value = 'link';
        document.getElementById('editRouteName').value = '';
        document.getElementById('editIcon').value = 'link';
        document.getElementById('editPermissionKey').value = '';
        document.getElementById('editBadge').value = '';
        document.getElementById('editIsActive').checked = true;
        document.getElementById('editIsVisible').checked = true;
        document.getElementById('editOpenNewTab').checked = false;
        document.getElementById('editQueryRepositoryId').value = '';
        document.getElementById('iconPreview').textContent = 'link';
        document.getElementById('routePreviewUri').classList.add('hidden');
        document.getElementById('editMenuId').dataset.parentId = '';
        document.getElementById('filterConfigGroup').classList.add('hidden');
        activeFilterConfig = null;

        const btnDelete = document.getElementById('btnDeleteMenu');
        if (btnDelete) btnDelete.classList.add('hidden');
        document.getElementById('editIsVisible').checked = true;
        document.getElementById('editOpenNewTab').checked = false;
        document.getElementById('iconPreview').textContent = 'link';
        document.getElementById('routePreviewUri').classList.add('hidden');
        document.getElementById('editMenuId').dataset.parentId = '';

        renderQuickIconChips(['link', 'grid_view', 'star', 'folder', 'apps']);
        toggleEditorFields();
        openEditorModal();
        setTimeout(() => document.getElementById('editTitle').focus(), 100);
    }

    // ========== QUICK ADD MENU ==========
    async function quickAddMenu() {
        const input = document.getElementById('quickAddTitle');
        const title = input.value.trim();
        if (!title) {
            input.focus();
            return;
        }

        const btn = document.getElementById('btnQuickAdd');
        const origIcon = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-outlined text-[16px] spin">sync</span>';
        btn.disabled = true;

        const slug = slugifyRoute(title);
        const iconInfo = smartDetectIcon(title);

        const payload = {
            title: title,
            type: 'link',
            route_name: slug,
            icon: iconInfo.defaultIcon,
            is_active: true,
            is_visible: true,
            generate_files: true
        };

        try {
            const res = await fetch('{{ route("api.menus.store", [], false) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (data.success) {
                showToast(`Menu '${title}' created successfully!`, 'success');
                input.value = '';
                await refreshAllData();
                if (data.menu) selectMenuItem(data.menu.id);
            } else {
                showToast(data.message || 'Failed to add menu', 'error');
            }
        } catch (e) {
            showToast('Network error during quick add', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = origIcon;
        }
    }

    // ========== AUTOMATION & SMART AUTO-FILL ==========
    function slugifyRoute(text) {
        if (!text) return '';
        const slug = text.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');
        return slug ? `${slug}.index` : '';
    }

    function onInputTitle() {
        const title = document.getElementById('editTitle').value;

        // Only auto-fill if we are creating a new menu item
        if (!currentEditId) {
            const slug = slugifyRoute(title);
            document.getElementById('editRouteName').value = slug;
            const manual = document.getElementById('editRouteNameManual');
            if (manual) manual.value = slug;

            const iconInfo = smartDetectIcon(title);
            if (iconInfo.defaultIcon) {
                document.getElementById('editIcon').value = iconInfo.defaultIcon;
                document.getElementById('iconPreview').textContent = iconInfo.defaultIcon;
            }
            renderQuickIconChips(iconInfo.chips);
        }
    }

    function smartDetectIcon(titleStr) {
        if (!titleStr) {
            return {
                defaultIcon: 'link',
                chips: ['link', 'circle', 'dashboard', 'analytics', 'settings']
            };
        }

        const t = titleStr.toLowerCase();

        const rules = [
            { keywords: ['user', 'karyawan', 'pegawai', 'member', 'pengguna', 'role', 'permission', 'profil'], defaultIcon: 'group', chips: ['group', 'person', 'manage_accounts', 'badge', 'security'] },
            { keywords: ['laporan', 'report', 'stat', 'analytic', 'grafik', 'chart', 'ringkasan'], defaultIcon: 'analytics', chips: ['analytics', 'bar_chart', 'pie_chart', 'monitoring', 'trending_up'] },
            { keywords: ['barang', 'produk', 'product', 'item', 'inventory', 'stok', 'stock', 'gudang'], defaultIcon: 'inventory_2', chips: ['inventory_2', 'category', 'warehouse', 'local_shipping', 'box'] },
            { keywords: ['jual', 'sales', 'order', 'transaksi', 'invoice', 'kasir', 'pos', 'nota'], defaultIcon: 'shopping_cart', chips: ['shopping_cart', 'receipt_long', 'payments', 'point_of_sale', 'sell'] },
            { keywords: ['beli', 'purchase', 'po', 'vendor', 'supplier', 'pembelian'], defaultIcon: 'shopping_bag', chips: ['shopping_bag', 'store', 'local_shipping', 'inventory', 'fact_check'] },
            { keywords: ['keuangan', 'bayar', 'finance', 'kas', 'bank', 'akuntansi', 'pajak', 'biaya'], defaultIcon: 'payments', chips: ['payments', 'account_balance', 'account_balance_wallet', 'receipt', 'savings'] },
            { keywords: ['setting', 'pengaturan', 'config', 'konfigurasi', 'system', 'sistem'], defaultIcon: 'settings', chips: ['settings', 'tune', 'display_settings', 'admin_panel_settings', 'construction'] },
            { keywords: ['sync', 'sinkron', 'acumatica', 'api', 'connect', 'integrasi'], defaultIcon: 'sync', chips: ['sync', 'cloud_sync', 'sync_alt', 'hub', 'api'] },
            { keywords: ['log', 'history', 'riwayat', 'audit', 'aktivitas'], defaultIcon: 'history', chips: ['history', 'list_alt', 'view_list', 'receipt_long', 'schedule'] },
            { keywords: ['dashboard', 'home', 'beranda', 'utama'], defaultIcon: 'dashboard', chips: ['dashboard', 'home', 'grid_view', 'speed', 'space_dashboard'] },
        ];

        for (const r of rules) {
            if (r.keywords.some(k => t.includes(k))) {
                return { defaultIcon: r.defaultIcon, chips: r.chips };
            }
        }

        return {
            defaultIcon: 'link',
            chips: ['link', 'grid_view', 'star', 'folder', 'apps']
        };
    }

    function renderQuickIconChips(chips) {
        const container = document.getElementById('quickIconChips');
        if (!container) return;

        const currentIcon = document.getElementById('editIcon').value;
        container.innerHTML = chips.map(icon => `
            <button type="button" onclick="selectQuickChip('${icon}')" 
                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs border transition-all ${currentIcon === icon ? 'bg-brand-50 border-brand-300 text-brand-700 font-medium' : 'bg-surface-50 border-surface-200 text-surface-600 hover:bg-surface-100'}">
                <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1;">${icon}</span>
                <span>${icon}</span>
            </button>
        `).join('');
    }

    function selectQuickChip(icon) {
        document.getElementById('editIcon').value = icon;
        document.getElementById('iconPreview').textContent = icon;
        const currentChips = Array.from(document.querySelectorAll('#quickIconChips button span:last-child')).map(el => el.textContent);
        renderQuickIconChips(currentChips.length ? currentChips : ['link', 'circle']);
    }

    function toggleAdvancedOptions() {
        const adv = document.getElementById('advancedOptions');
        const arrow = document.getElementById('advArrow');
        if (adv.classList.contains('hidden')) {
            adv.classList.remove('hidden');
            arrow.classList.add('rotate-180');
        } else {
            adv.classList.add('hidden');
            arrow.classList.remove('rotate-180');
        }
    }

    function addChildMenu() {
        const parentId = document.getElementById('editMenuId').value;
        if (!parentId) return;

        const parentTitle = document.getElementById('editTitle').value;
        currentEditId = null;

        document.getElementById('editorTitle').textContent = `New child of: ${parentTitle}`;
        document.getElementById('editMenuId').value = '';
        document.getElementById('editTitle').value = '';
        document.getElementById('editType').value = 'link';
        document.getElementById('editRouteName').value = '';
        document.getElementById('editIcon').value = '';
        document.getElementById('editPermissionKey').value = '';
        document.getElementById('editBadge').value = '';
        document.getElementById('editIsActive').checked = true;
        document.getElementById('editIsVisible').checked = true;
        document.getElementById('editOpenNewTab').checked = false;
        document.getElementById('iconPreview').textContent = 'help';

        // Store parent_id in a hidden field temporarily
        document.getElementById('editMenuId').dataset.parentId = parentId;

        toggleEditorFields();
    }

    function triggerAddChildMenu(parentId) {
        const parentItem = findMenuById(menuTreeData, parentId);
        if (!parentItem) return;

        // Auto-expand parent if collapsed
        if (collapsedState[parentId]) {
            collapsedState[parentId] = false;
        }

        currentEditId = null;
        document.getElementById('editorTitle').textContent = `➕ Add Sub-Menu`;
        document.getElementById('editorSubtitle').innerHTML = `Membuat sub-menu baru di bawah: <strong class="text-brand-600">${parentItem.title}</strong>`;
        document.getElementById('editMenuId').value = '';
        document.getElementById('editTitle').value = '';
        document.getElementById('editType').value = 'link';
        document.getElementById('editRouteName').value = '';
        document.getElementById('editIcon').value = 'link';
        document.getElementById('editPermissionKey').value = '';
        document.getElementById('editBadge').value = '';
        document.getElementById('editIsActive').checked = true;
        document.getElementById('editOpenNewTab').checked = false;
        document.getElementById('editQueryRepositoryId').value = '';
        document.getElementById('iconPreview').textContent = 'link';
        document.getElementById('editMenuId').dataset.parentId = parentId;
        document.getElementById('filterConfigGroup').classList.add('hidden');
        activeFilterConfig = null;

        const btnDelete = document.getElementById('btnDeleteMenu');
        if (btnDelete) btnDelete.classList.add('hidden');

        renderQuickIconChips(['link', 'grid_view', 'star', 'folder', 'apps']);
        toggleEditorFields();
        renderTree();
        openEditorModal();
        setTimeout(() => document.getElementById('editTitle').focus(), 100);
    }

    // ========== DELETE MENU ITEM ==========
    async function triggerDeleteDirect(id, title) {
        const result = await Swal.fire({
            title: 'Hapus Menu?',
            html: `Apakah Anda yakin ingin menghapus menu <strong>"${title}"</strong>?<br><small class="text-surface-500 mt-1 block">Permission dan perannya akan otomatis dibersihkan dari sistem.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        });

        if (result.isConfirmed) {
            try {
                const res = await fetch(`/api/menus/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    if (currentEditId == id) {
                        currentEditId = null;
                        closeEditorModal();
                    }
                    await loadMenuTree();
                    loadPermissionMatrix();
                } else {
                    showToast(data.message || 'Gagal menghapus menu', 'error');
                }
            } catch (e) {
                showToast('Terjadi kesalahan jaringan', 'error');
            }
        }
    }

    async function deleteMenuItem() {
        const id = document.getElementById('editMenuId').value;
        if (!id) return;

        const item = findMenuById(menuTreeData, parseInt(id));
        await triggerDeleteDirect(parseInt(id), item?.title || 'menu ini');
    }

    // ========== ICON PICKER ==========
    function openIconPicker() {
        const modal = document.getElementById('iconPickerModal');
        const backdrop = document.getElementById('iconPickerBackdrop');
        const panel = document.getElementById('iconPickerPanel');

        modal.classList.remove('hidden');
        document.getElementById('iconSearchInput').value = '';

        renderIconGrid(allIcons);

        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            panel.classList.remove('opacity-0', 'scale-95');
            panel.classList.add('opacity-100', 'scale-100');
        });
    }

    function closeIconPicker() {
        const modal = document.getElementById('iconPickerModal');
        const backdrop = document.getElementById('iconPickerBackdrop');
        const panel = document.getElementById('iconPickerPanel');

        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
        panel.classList.remove('opacity-100', 'scale-100');
        panel.classList.add('opacity-0', 'scale-95');

        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    function renderIconGrid(icons) {
        const grid = document.getElementById('iconGrid');
        const limitedIcons = icons.slice(0, 120); // Limit to 120 for optimal grid
        grid.innerHTML = limitedIcons.map(icon => `
            <button onclick="pickIcon('${icon}')" 
                onmouseenter="onHoverIcon('${icon}')" 
                class="w-full aspect-square flex items-center justify-center rounded-xl bg-white hover:bg-brand-600 hover:text-white text-surface-600 border border-surface-200/60 shadow-2xs hover:shadow-md hover:scale-105 transition-all duration-150 group" title="${icon}">
                <span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">${icon}</span>
            </button>
        `).join('');
    }

    function onHoverIcon(icon) {
        const nameEl = document.getElementById('hoverIconName');
        const prevEl = document.getElementById('hoverIconPreview');
        if (nameEl) nameEl.textContent = icon;
        if (prevEl) prevEl.textContent = icon;
    }

    function filterIconCategory(category, btn) {
        // Highlight active tab
        document.querySelectorAll('.icon-cat-btn').forEach(b => {
            b.classList.remove('bg-brand-600', 'text-white', 'shadow-2xs');
            b.classList.add('bg-surface-100', 'text-surface-600', 'hover:bg-surface-200');
        });
        if (btn) {
            btn.classList.remove('bg-surface-100', 'text-surface-600', 'hover:bg-surface-200');
            btn.classList.add('bg-brand-600', 'text-white', 'shadow-2xs');
        }

        if (category === 'all') {
            renderIconGrid(allIcons);
            return;
        }

        const keywords = {
            data: ['analytics', 'bar_chart', 'show_chart', 'pie_chart', 'table', 'database', 'grid', 'list', 'poll', 'query', 'insights'],
            folder: ['folder', 'file', 'description', 'inventory', 'receipt', 'folder_open', 'draft', 'attach_file', 'request_quote'],
            settings: ['settings', 'tune', 'admin_panel_settings', 'key', 'lock', 'build', 'shield', 'security', 'terminal', 'manage_accounts'],
            user: ['person', 'group', 'account_circle', 'badge', 'people', 'contacts', 'face', 'diversity_3', 'supervisor_account']
        };

        const list = keywords[category] || [];
        const filtered = allIcons.filter(i => list.some(k => i.includes(k)));
        renderIconGrid(filtered.length > 0 ? filtered : allIcons);
    }

    let iconSearchTimeout;
    function debounceFilterIcons() {
        clearTimeout(iconSearchTimeout);
        iconSearchTimeout = setTimeout(() => {
            filterIcons();
        }, 300);
    }

    function filterIcons() {
        const search = document.getElementById('iconSearchInput').value.toLowerCase();
        const filtered = allIcons.filter(i => i.includes(search));
        renderIconGrid(filtered);
    }

    function pickIcon(icon) {
        document.getElementById('editIcon').value = icon;
        document.getElementById('iconPreview').textContent = icon;
        closeIconPicker();
    }

    // ========== PREVIEW SIDEBAR ==========
    function openPreviewSidebar() {
        const modal = document.getElementById('previewSidebarModal');
        const backdrop = document.getElementById('previewBackdrop');
        const panel = document.getElementById('previewPanel');

        modal.classList.remove('hidden');
        renderPreviewSidebar();

        requestAnimationFrame(() => {
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            panel.classList.remove('-translate-x-full');
            panel.classList.add('translate-x-0');
        });
    }

    function closePreviewSidebar() {
        const modal = document.getElementById('previewSidebarModal');
        const backdrop = document.getElementById('previewBackdrop');
        const panel = document.getElementById('previewPanel');

        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
        panel.classList.remove('translate-x-0');
        panel.classList.add('-translate-x-full');

        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    function renderPreviewSidebar() {
        const container = document.getElementById('previewSidebarContent');
        container.innerHTML = buildPreviewHtml(menuTreeData);
    }

    function buildPreviewHtml(items) {
        let html = '<ul class="space-y-1">';
        items.forEach(item => {
            if (!item.is_active || !item.is_visible) return;

            const hasChildren = item.children && item.children.filter(c => c.is_active && c.is_visible).length > 0;

            if (item.type === 'divider') {
                html += '<li class="py-2"><div class="border-t border-white/[0.06]"></div></li>';
                return;
            }

            if (item.type === 'header') {
                html += `<li class="px-3 pt-4 pb-1"><span class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold">${item.title}</span></li>`;
                return;
            }

            html += `<li>
                <div class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/[0.04] transition-all cursor-default">
                    <span class="material-symbols-outlined text-[18px] text-slate-400" style="font-variation-settings: 'FILL' 1;">${item.icon || 'circle'}</span>
                    <span class="text-[13px] font-medium flex-1">${item.title}</span>
                    ${item.badge ? `<span class="text-[9px] bg-brand-500/20 text-brand-400 px-1.5 py-0.5 rounded font-medium">${item.badge}</span>` : ''}
                    ${hasChildren ? '<span class="material-symbols-outlined text-[16px] text-slate-500">chevron_right</span>' : ''}
                </div>`;

            if (hasChildren) {
                html += '<ul class="pl-[36px] space-y-0.5 mt-0.5">';
                item.children.forEach(child => {
                    if (!child.is_active || !child.is_visible) return;
                    html += `<li>
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/[0.04] transition-all cursor-default">
                            <span class="text-[12px]">${child.title}</span>
                            ${child.badge ? `<span class="text-[9px] bg-brand-500/20 text-brand-400 px-1.5 py-0.5 rounded">${child.badge}</span>` : ''}
                        </div>
                    </li>`;
                });
                html += '</ul>';
            }

            html += '</li>';
        });
        html += '</ul>';
        return html;
    }

    // ========== PERMISSION MATRIX ==========
    async function loadPermissionMatrix() {
        const container = document.getElementById('permissionMatrixContainer');
        container.innerHTML = '<div class="flex items-center justify-center py-8"><span class="material-symbols-outlined text-brand-500 spin mr-2">sync</span> Loading...</div>';

        try {
            const baseUrl = '{{ route("api.menus.permission-matrix", [], false) }}';
            const res = await fetch(`${baseUrl}?_t=${Date.now()}`, {
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache'
                }
            });
            const data = await res.json();

            if (!data.success) {
                container.innerHTML = '<p class="text-red-500 text-center py-4">Failed to load matrix.</p>';
                return;
            }

            const roles = data.roles;
            const matrix = data.matrix;

            let html = '<table class="w-full text-xs border-collapse">';
            html += '<thead><tr><th class="text-left px-2 py-2 border-b border-surface-200 text-surface-600 font-medium sticky left-0 bg-white z-10 min-w-[140px]">Menu</th>';
            roles.forEach(r => {
                html += `<th class="px-2 py-2 border-b border-surface-200 text-surface-600 font-medium text-center whitespace-nowrap">${r.name}</th>`;
            });
            html += '</tr></thead><tbody>';

            matrix.forEach(row => {
                const indent = row.parent_id ? 'pl-6' : '';
                html += `<tr class="hover:bg-surface-50 transition-colors">`;
                html += `<td class="px-2 py-1.5 border-b border-surface-100 text-surface-700 font-medium sticky left-0 bg-white z-10 ${indent}">${row.title}</td>`;
                roles.forEach(r => {
                    const hasAccess = row.roles[r.id];
                    const permKey = row.permission_key;
                    html += `<td class="px-2 py-1.5 border-b border-surface-100 text-center">`;
                    if (!permKey) {
                        html += `<span class="text-emerald-500" title="No restriction">✓</span>`;
                    } else {
                        html += `<input type="checkbox" 
                                    class="w-4 h-4 text-brand-600 rounded border-surface-300 focus:ring-brand-500 cursor-pointer"
                                    ${hasAccess ? 'checked' : ''}
                                    onchange="toggleRoleMenuPermission(${row.menu_id}, ${r.id}, this)">`;
                    }
                    html += '</td>';
                });
                html += '</tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        } catch (e) {
            container.innerHTML = '<p class="text-red-500 text-center py-4">Network error.</p>';
        }
    }

    // ========== PUBLISH ==========
    async function publishMenus() {
        try {
            const res = await fetch('{{ route("api.menus.publish", [], false) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
            }
        } catch (e) {
            showToast('Failed to publish', 'error');
        }
    }
    // ========== TOGGLE ROLE PERMISSION ==========
    async function toggleRoleMenuPermission(menuId, roleId, checkbox) {
        const isGranted = checkbox.checked;
        checkbox.disabled = true;

        try {
            const res = await fetch('{{ route("api.menus.toggle-role-permission", [], false) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    menu_id: menuId,
                    role_id: roleId,
                    is_granted: isGranted
                })
            });
            const data = await res.json();
            
            if (data.success) {
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Failed to update permission', 'error');
                checkbox.checked = !isGranted; // Revert checkbox
            }
        } catch (e) {
            showToast('Network error', 'error');
            checkbox.checked = !isGranted; // Revert checkbox
        } finally {
            checkbox.disabled = false;
        }
    }
    
    // ========== FILTER CONFIG LOGIC ==========
    let availableQueryColumns = [];
    let activeFilterConfig = null; // null = legacy (all columns), [] = empty, [...] = explicit

    async function loadQueryColumns(queryId) {
        const group = document.getElementById('filterConfigGroup');
        const select = document.getElementById('availableColumnsSelect');
        
        if (!queryId) {
            group.classList.add('hidden');
            availableQueryColumns = [];
            return;
        }
        
        group.classList.remove('hidden');
        select.innerHTML = '<option value="">Loading...</option>';
        select.disabled = true;

        try {
            const res = await fetch(`/api/report/query-repository/${queryId}/columns`);
            const data = await res.json();
            
            if (data.success) {
                availableQueryColumns = data.columns;
                populateColumnsSelect();
                renderActiveFilters();
            } else {
                select.innerHTML = '<option value="">Error loading columns</option>';
            }
        } catch (e) {
            console.error(e);
            select.innerHTML = '<option value="">Error loading columns</option>';
        } finally {
            select.disabled = false;
        }
    }

    function populateColumnsSelect() {
        const select = document.getElementById('availableColumnsSelect');
        select.innerHTML = '<option value="">-- Select Column --</option>';
        
        availableQueryColumns.forEach(col => {
            const opt = document.createElement('option');
            opt.value = col.field;
            opt.textContent = col.field;
            select.appendChild(opt);
        });
    }

    function renderActiveFilters() {
        const container = document.getElementById('activeFiltersList');
        container.innerHTML = '';
        
        if (!activeFilterConfig || activeFilterConfig.length === 0) {
            container.innerHTML = '<p class="text-[10px] text-surface-400 italic">No explicit filters added.</p>';
            return;
        }

        activeFilterConfig.forEach((filter, index) => {
            const operators = [
                {val: 'contains', label: 'Contains'},
                {val: 'starts_with', label: 'Starts With'},
                {val: 'ends_with', label: 'Ends With'},
                {val: 'equals', label: 'Equals'},
                {val: 'greater_than', label: '>'},
                {val: 'greater_than_or_equal', label: '>='},
                {val: 'less_than', label: '<'},
                {val: 'less_than_or_equal', label: '<='},
                {val: 'before', label: 'Before'},
                {val: 'after', label: 'After'},
                {val: 'between', label: 'Between'}
            ];
            
            const types = [
                {val: 'text', label: 'Text'},
                {val: 'number', label: 'Number'},
                {val: 'date', label: 'Date'},
                {val: 'date_range', label: 'Date Range'}
            ];

            const html = `
                <div class="p-2 border border-surface-200 bg-white rounded-lg flex flex-col gap-2">
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-semibold text-brand-600">${filter.field}</span>
                        <button type="button" onclick="removeFilterConfig(${index})" class="text-red-500 hover:text-red-700 transition-colors"><span class="material-symbols-outlined text-[16px]">close</span></button>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[9px] text-surface-500 uppercase">Label</label>
                            <input type="text" value="${filter.label || ''}" onchange="updateFilterConfig(${index}, 'label', this.value)" class="w-full px-2 py-1 text-[11px] border border-surface-200 rounded">
                        </div>
                        <div>
                            <label class="text-[9px] text-surface-500 uppercase">Placeholder</label>
                            <input type="text" value="${filter.placeholder || ''}" onchange="updateFilterConfig(${index}, 'placeholder', this.value)" class="w-full px-2 py-1 text-[11px] border border-surface-200 rounded">
                        </div>
                        <div>
                            <label class="text-[9px] text-surface-500 uppercase">Type</label>
                            <select onchange="updateFilterConfig(${index}, 'type', this.value)" class="w-full px-2 py-1 text-[11px] border border-surface-200 rounded">
                                ${types.map(t => `<option value="${t.val}" ${t.val === filter.type ? 'selected' : ''}>${t.label}</option>`).join('')}
                            </select>
                        </div>
                        <div>
                            <label class="text-[9px] text-surface-500 uppercase">Operator</label>
                            <select onchange="updateFilterConfig(${index}, 'operator', this.value)" class="w-full px-2 py-1 text-[11px] border border-surface-200 rounded">
                                ${operators.map(t => `<option value="${t.val}" ${t.val === filter.operator ? 'selected' : ''}>${t.label}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                    <label class="flex items-center gap-1.5 mt-1 cursor-pointer">
                        <input type="checkbox" onchange="updateFilterConfig(${index}, 'required', this.checked)" ${filter.required ? 'checked' : ''} class="w-3 h-3 text-brand-600 rounded">
                        <span class="text-[10px] font-medium text-surface-600">Required</span>
                    </label>
                </div>
            `;
            container.innerHTML += html;
        });
    }

    function addFilterConfig() {
        const select = document.getElementById('availableColumnsSelect');
        const field = select.value;
        
        if (!field) return;

        if (activeFilterConfig === null) {
            activeFilterConfig = [];
        }

        if (activeFilterConfig.some(f => f.field === field)) {
            showToast('Filter for this column already exists', 'error');
            return;
        }

        activeFilterConfig.push({
            field: field,
            label: field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
            type: 'text',
            operator: 'contains',
            placeholder: '',
            required: false
        });

        select.value = ''; // reset
        renderActiveFilters();
    }

    function updateFilterConfig(index, key, value) {
        if (activeFilterConfig && activeFilterConfig[index]) {
            activeFilterConfig[index][key] = value;
        }
    }

    function removeFilterConfig(index) {
        if (activeFilterConfig) {
            activeFilterConfig.splice(index, 1);
            if (activeFilterConfig.length === 0) {
                // If the user explicitly removed all filters, it stays as [] to denote "no filters".
                // We do not revert it to null (legacy).
            }
            renderActiveFilters();
        }
    }

</script>
@endsection
