
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let menuTreeData = [];
    let allRoutes = [];
    let allIcons = [];
    let currentEditId = null;
    let sortableInstances = [];

    // ========== INITIALIZATION ==========
    document.addEventListener('DOMContentLoaded', async () => {
        await Promise.all([loadMenuTree(), loadRoutes(), loadIcons()]);
    });

    // ========== LOAD DATA ==========
    async function loadMenuTree() {
        try {
            const res = await fetch('"BLADE_REPLACED"');
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
                        <p class="text-surface-400 text-[11px] mt-4 font-mono">${window.location.origin}"BLADE_REPLACED"</p>
                    </div>`;
            }
        }
    }

    async function loadRoutes() {
        try {
            const res = await fetch('"BLADE_REPLACED"');
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
            const res = await fetch('"BLADE_REPLACED"');
            const data = await res.json();
            allIcons = data.icons || [];
        } catch (e) {
            console.error('Failed to load icons:', e);
        }
    }

    // ========== TREE RENDERING ==========
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
            li.className = 'menu-tree-item';
            li.dataset.id = item.id;
            li.dataset.parentId = item.parent_id || '';

            const statusClasses = !item.is_active
                ? 'opacity-50 border-red-200 bg-red-50/50'
                : !item.is_visible
                    ? 'opacity-60 border-amber-200 bg-amber-50/30'
                    : currentEditId === item.id
                        ? 'border-brand-300 bg-brand-50/50 ring-2 ring-brand-500/20'
                        : 'border-surface-200 bg-white hover:border-brand-200 hover:bg-brand-50/20';

            li.innerHTML = `
                <div class="flex items-center gap-2 px-3 py-2.5 rounded-xl border cursor-pointer transition-all duration-200 group ${statusClasses}" onclick="selectMenuItem(${item.id})" style="margin-left: ${depth * 24}px;">
                    <span class="material-symbols-outlined text-surface-300 text-[16px] cursor-grab drag-handle">drag_indicator</span>
                    <span class="material-symbols-outlined text-[18px] ${item.is_active ? 'text-brand-500' : 'text-surface-300'}" style="font-variation-settings: 'FILL' 1;">${item.icon || 'circle'}</span>
                    <span class="text-[13px] font-medium ${item.is_active ? 'text-surface-800' : 'text-surface-400'} flex-1 truncate">${item.title}</span>
                    ${item.route_name ? `<span class="text-[10px] text-surface-400 bg-surface-100 px-1.5 py-0.5 rounded font-mono hidden group-hover:inline">${item.route_name}</span>` : ''}
                    ${item.badge ? `<span class="text-[10px] text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded font-medium">${item.badge}</span>` : ''}
                    ${!item.is_active ? '<span class="material-symbols-outlined text-[14px] text-red-400">block</span>' : ''}
                    ${!item.is_visible ? '<span class="material-symbols-outlined text-[14px] text-amber-400">visibility_off</span>' : ''}
                    ${(item.children && item.children.length > 0) ? `<span class="text-[10px] text-surface-400 bg-surface-100 px-1.5 py-0.5 rounded">${item.children.length}</span>` : ''}
                </div>`;
            ul.appendChild(li);

            if (item.children && item.children.length > 0) {
                const childList = buildTreeDom(item.children, depth + 1);
                ul.appendChild(childList);
            }
        });

        return ul;
    }

    function initSortable() {
        // Destroy old instances
        sortableInstances.forEach(s => s.destroy());
        sortableInstances = [];

        document.querySelectorAll('.menu-tree-list').forEach(el => {
            const s = new Sortable(el, {
                group: 'menu-tree',
                animation: 200,
                handle: '.drag-handle',
                ghostClass: 'opacity-30',
                chosenClass: 'ring-2 ring-brand-500',
                onEnd: saveTreeOrder,
            });
            sortableInstances.push(s);
        });
    }

    // ========== SAVE TREE ORDER ==========
    function saveTreeOrder() {
        const items = [];
        let order = 0;

        function walkDom(container, parentId) {
            container.querySelectorAll(':scope > .menu-tree-item').forEach(el => {
                items.push({
                    id: parseInt(el.dataset.id),
                    parent_id: parentId,
                    sort_order: order++,
                });
                // Find child list
                const nextSibling = el.nextElementSibling;
                if (nextSibling && nextSibling.classList.contains('menu-tree-list')) {
                    walkDom(nextSibling, parseInt(el.dataset.id));
                }
            });
        }

        const rootList = document.querySelector('#menuTree > .menu-tree-list');
        if (rootList) walkDom(rootList, null);

        // Quick flat approach: just re-read all items in DOM order
        const allItems = [];
        document.querySelectorAll('.menu-tree-item').forEach((el, idx) => {
            const parentList = el.closest('.menu-tree-list');
            const depth = parseInt(parentList?.dataset?.depth || 0);
            // Find parent by looking at the previous item list's depth
            let parentId = null;
            if (depth > 0) {
                // Walk up to find parent item
                let prev = parentList.previousElementSibling;
                if (prev && prev.classList.contains('menu-tree-item')) {
                    parentId = parseInt(prev.dataset.id);
                }
            }
            allItems.push({
                id: parseInt(el.dataset.id),
                parent_id: parentId,
                sort_order: idx * 10,
            });
        });

        fetch('"BLADE_REPLACED"', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ items: allItems }),
        }).then(res => res.json()).then(data => {
            if (data.success) {
                showToast('Menu order saved!', 'success');
                loadMenuTree();
            }
        });
    }

    // ========== SELECT / EDIT MENU ITEM ==========
    function selectMenuItem(id) {
        currentEditId = id;
        const item = findMenuById(menuTreeData, id);
        if (!item) return;

        document.getElementById('editorEmpty').classList.add('hidden');
        document.getElementById('editorForm').classList.remove('hidden');
        document.getElementById('editorTitle').textContent = `Editing: ${item.title}`;

        document.getElementById('editMenuId').value = item.id;
        document.getElementById('editTitle').value = item.title || '';
        document.getElementById('editType').value = item.type || 'link';
        document.getElementById('editRouteName').value = item.route_name || '';
        document.getElementById('editIcon').value = item.icon || '';
        document.getElementById('editPermissionKey').value = item.permission_key || '';
        document.getElementById('editBadge').value = item.badge || '';
        document.getElementById('editIsActive').checked = item.is_active !== false;
        document.getElementById('editIsVisible').checked = item.is_visible !== false;
        document.getElementById('editOpenNewTab').checked = item.open_in_new_tab === true;

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

        toggleEditorFields();
        renderTree(); // Re-render to highlight selected
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
                await loadMenuTree();
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
        document.getElementById('editorEmpty').classList.add('hidden');
        document.getElementById('editorForm').classList.remove('hidden');
        document.getElementById('editorTitle').textContent = 'New Menu Item';

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
        document.getElementById('routePreviewUri').classList.add('hidden');

        toggleEditorFields();
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

    // ========== DELETE MENU ITEM ==========
    async function deleteMenuItem() {
        const id = document.getElementById('editMenuId').value;
        if (!id) return;

        const item = findMenuById(menuTreeData, parseInt(id));
        const result = await Swal.fire({
            title: 'Delete Menu?',
            html: `Are you sure you want to delete <strong>${item?.title || 'this menu'}</strong>?<br><small class="text-gray-500">Children will be moved to the parent level.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete',
        });

        if (!result.isConfirmed) return;

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
                currentEditId = null;
                document.getElementById('editorForm').classList.add('hidden');
                document.getElementById('editorEmpty').classList.remove('hidden');
                await loadMenuTree();
            } else {
                showToast(data.message || 'Failed to delete', 'error');
            }
        } catch (e) {
            showToast('Network error', 'error');
        }
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
        grid.innerHTML = icons.map(icon => `
            <button onclick="pickIcon('${icon}')" class="flex flex-col items-center justify-center p-2 rounded-xl hover:bg-brand-50 hover:text-brand-600 transition-all group border border-transparent hover:border-brand-200" title="${icon}">
                <span class="material-symbols-outlined text-[24px] text-surface-500 group-hover:text-brand-600 transition-colors" style="font-variation-settings: 'FILL' 1;">${icon}</span>
                <span class="text-[9px] text-surface-400 mt-1 truncate w-full text-center">${icon}</span>
            </button>
        `).join('');
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
            const res = await fetch('"BLADE_REPLACED"');
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
                        html += `<span class="${hasAccess ? 'text-emerald-500' : 'text-surface-300'}">${hasAccess ? '✓' : '—'}</span>`;
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
            const res = await fetch('"BLADE_REPLACED"', {
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
