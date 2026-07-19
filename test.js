
    const entityData = {};
    const connectionData = {};
    let deleteTargetKey = null;

    // ── Entity Pagination ──────────────────────────────────────────────────
    const ENTITY_PAGE_SIZE = 25;
    let entityCurrentPage = 1;
    function paginateEntities() {
        const rows = document.querySelectorAll('#entity-table tbody tr');
        const total = rows.length;
        const totalPages = Math.max(1, Math.ceil(total / ENTITY_PAGE_SIZE));
        if (entityCurrentPage > totalPages) entityCurrentPage = totalPages;
        const start = (entityCurrentPage - 1) * ENTITY_PAGE_SIZE;
        const end = start + ENTITY_PAGE_SIZE;
        rows.forEach((row, i) => { row.style.display = (i >= start && i < end) ? '' : 'none'; });
        const container = document.getElementById('entity-pagination');
        if (!container) return;
        if (totalPages <= 1) { container.innerHTML = ''; return; }
        let html = `<span class="text-label-sm text-surface-500">Page ${entityCurrentPage} of ${totalPages} (${total} entities)</span><div class="flex items-center gap-1">`;
        html += `<button onclick="entityGoPage(${entityCurrentPage - 1})" ${entityCurrentPage <= 1 ? 'disabled' : ''} class="p-1 rounded-lg hover:bg-surface-100 text-surface-400 disabled:opacity-30"><span class="material-symbols-outlined text-[14px]">chevron_left</span></button>`;
        for (let p = 1; p <= totalPages; p++) {
            html += `<button onclick="entityGoPage(${p})" class="px-2.5 py-0.5 rounded-lg text-label-sm ${p === entityCurrentPage ? 'bg-brand-600 text-white' : 'text-surface-500 hover:bg-surface-100'}">${p}</button>`;
        }
        html += `<button onclick="entityGoPage(${entityCurrentPage + 1})" ${entityCurrentPage >= totalPages ? 'disabled' : ''} class="p-1 rounded-lg hover:bg-surface-100 text-surface-400 disabled:opacity-30"><span class="material-symbols-outlined text-[14px]">chevron_right</span></button>`;
        html += '</div>';
        container.innerHTML = html;
        if(typeof updateBatchDeleteBtn === 'function') updateBatchDeleteBtn();
    }
    function entityGoPage(p) { entityCurrentPage = p; paginateEntities(); }
    document.addEventListener('DOMContentLoaded', paginateEntities);

    // Aliases for HTML buttons
    function editEntity(key) { openEntityModal(key); }
    function editConnection(key) { openConnectionModal(key); }

    // ── Entity Modal ───────────────────────────────────────────────────────
    function openEntityModal(editKey = null) {
        const modal = document.getElementById('entity-modal');
        const form = document.getElementById('entity-form');
        form.reset();
        document.getElementById('form-mode').value = 'create';
        document.getElementById('form-original-key').value = '';

        if (editKey && entityData[editKey]) {
            const e = entityData[editKey];
            document.getElementById('form-mode').value = 'edit';
            document.getElementById('form-original-key').value = editKey;
            document.getElementById('form-key').value = editKey;
            document.getElementById('form-type').value = e.type;
            document.getElementById('form-odata').value = e.odata_entity;
            document.getElementById('form-table').value = e.table;
            document.getElementById('form-icon').value = e.icon || '';
            document.getElementById('form-desc').value = e.description;
            if(e.source_connection) {
                document.getElementById('form-source-conn').value = e.source_connection;
            }
            if(e.destination_connection) {
                document.getElementById('form-dest-conn').value = e.destination_connection;
            }
            document.getElementById('modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">edit</span> Edit Entity';
            document.getElementById('form-submit-text').textContent = 'Save Changes';
            fetchSourceObjects();
        } else {
            document.getElementById('modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">add_circle</span> Add Entity';
            document.getElementById('form-submit-text').textContent = 'Create Entity';
            fetchSourceObjects();
        }
        modal.classList.remove('hidden');
    }

    function filterList(input, listId) {
        const filter = input.value.toLowerCase();
        const lis = document.getElementById(listId).getElementsByTagName('li');
        for (let i = 0; i < lis.length; i++) {
            const txtValue = lis[i].textContent || lis[i].innerText;
            if (txtValue.toLowerCase().indexOf(filter) > -1) {
                lis[i].style.display = "";
            } else {
                lis[i].style.display = "none";
            }
        }
    }

    let cachedSourceObjects = {};
    try {
        const stored = localStorage.getItem('cached_source_objects');
        if (stored) cachedSourceObjects = JSON.parse(stored);
    } catch(e) {}

    function renderSourceObjects(objects) {
        requestAnimationFrame(() => {
            const list = document.getElementById('form-odata-list');
            
            if (objects.length === 0) {
                list.innerHTML = '<li class="px-3.5 py-2 text-sm text-surface-400 italic">No objects found</li>';
            } else {
                let html = '';
                objects.forEach(obj => {
                    html += `<li onclick="document.getElementById('form-odata').value = '${obj}'" class="px-3.5 py-2 hover:bg-surface-100 cursor-pointer text-sm font-mono text-surface-800 transition-colors">${obj}</li>`;
                });
                list.innerHTML = html;
            }
        });
    }

    async function fetchSourceObjects(force = false) {
        const connKey = document.getElementById('form-source-conn').value;
        if (!connKey) return;
        
        if (!force && cachedSourceObjects[connKey]) {
            renderSourceObjects(cachedSourceObjects[connKey]);
            return;
        }
        
        const loading = document.getElementById('form-odata-loading');
        loading.classList.remove('hidden');
        
        try {
            const res = await fetch(`/api/connections/${connKey}/objects`);
            const data = await res.json();
            if (res.ok && data.success && data.objects) {
                // Sort objects alphabetically
                data.objects.sort((a, b) => a.localeCompare(b));
                cachedSourceObjects[connKey] = data.objects;
                try {
                    localStorage.setItem('cached_source_objects', JSON.stringify(cachedSourceObjects));
                } catch(e) {}
                
                renderSourceObjects(data.objects);

                if (document.activeElement === document.getElementById('form-odata')) {
                    document.getElementById('form-odata-dropdown').classList.remove('hidden');
                }
            }
        } catch (e) {
            console.error('Failed to fetch source objects', e);
        } finally {
            loading.classList.add('hidden');
        }
    }

    function closeEntityModal() { document.getElementById('entity-modal').classList.add('hidden'); }

    async function submitEntityForm(event) {
        if(event) event.preventDefault();
        if(!document.getElementById('entity-form').checkValidity()) {
            document.getElementById('entity-form').reportValidity();
            return;
        }

        const mode = document.getElementById('form-mode').value;
        const originalKey = document.getElementById('form-original-key').value;
        const btn = document.getElementById('form-submit-btn');
        const payload = {
            key: document.getElementById('form-key').value.trim(),
            odata_entity: document.getElementById('form-odata').value.trim(),
            table: document.getElementById('form-table').value.trim(),
            type: document.getElementById('form-type').value,
            icon: document.getElementById('form-icon').value.trim() || 'database',
            description: document.getElementById('form-desc').value.trim(),
            source_connection: document.getElementById('form-source-conn').value,
            destination_connection: document.getElementById('form-dest-conn').value,
        };
        btn.disabled = true;
        try {
            const url = mode === 'edit' ? `/api/entities/${originalKey}` : '/api/entities';
            const method = mode === 'edit' ? 'PUT' : 'POST';
            const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (res.ok && data.success) { showToast(data.message, 'success'); closeEntityModal(); setTimeout(() => location.reload(), 800); }
            else { showToast(data.message || 'Operation failed', 'error'); }
        } catch (e) { showToast('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; }
    }

    // ── Connection Modal ───────────────────────────────────────────────────
    function toggleConnectionFields() {
        const type = document.getElementById('conn-type').value;
        document.getElementById('conn-fields-db').classList.add('hidden');
        document.getElementById('conn-fields-odata').classList.add('hidden');
        document.getElementById('conn-fields-rest').classList.add('hidden');
        
        if (type === 'mysql' || type === 'sqlsrv') {
            document.getElementById('conn-fields-db').classList.remove('hidden');
        } else if (type === 'odata') {
            document.getElementById('conn-fields-odata').classList.remove('hidden');
        } else if (type === 'rest_api') {
            document.getElementById('conn-fields-rest').classList.remove('hidden');
        }
    }

    function openConnectionModal(editKey = null) {
        const modal = document.getElementById('connection-modal');
        document.getElementById('connection-form').reset();
        document.getElementById('conn-form-mode').value = 'create';
        document.getElementById('conn-form-original-key').value = '';

        if (editKey && connectionData[editKey]) {
            const c = connectionData[editKey];
            document.getElementById('conn-form-mode').value = 'edit';
            document.getElementById('conn-form-original-key').value = editKey;
            document.getElementById('conn-key').value = editKey;
            document.getElementById('conn-name').value = c.name;
            document.getElementById('conn-type').value = c.type;
            
            toggleConnectionFields();
            if (c.type === 'mysql' || c.type === 'sqlsrv') {
                document.getElementById('conn-db-host').value = c.credentials.host || '';
                document.getElementById('conn-db-port').value = c.credentials.port || '';
                document.getElementById('conn-db-name').value = c.credentials.database || '';
                document.getElementById('conn-db-user').value = c.credentials.username || '';
                document.getElementById('conn-db-pass').value = c.credentials.password || '';
            } else if (c.type === 'odata') {
                document.getElementById('conn-odata-url').value = c.credentials.url || '';
                document.getElementById('conn-odata-tenant').value = c.credentials.tenant || '';
                document.getElementById('conn-odata-user').value = c.credentials.username || '';
                document.getElementById('conn-odata-pass').value = c.credentials.password || '';
                document.getElementById('conn-odata-path').value = c.credentials.odata_path || 'odatav4';
            } else if (c.type === 'rest_api') {
                document.getElementById('conn-rest-url').value = c.credentials.url || '';
                document.getElementById('conn-rest-token').value = c.credentials.token || '';
            }

            document.getElementById('conn-modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">edit</span> Edit Connection';
            document.getElementById('conn-submit-text').textContent = 'Save Changes';
        } else {
            toggleConnectionFields();
            document.getElementById('conn-modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">add_circle</span> Add Connection';
            document.getElementById('conn-submit-text').textContent = 'Create Connection';
        }
        modal.classList.remove('hidden');
    }

    function closeConnectionModal() { document.getElementById('connection-modal').classList.add('hidden'); }

    async function testConnectionFromForm() {
        if(!document.getElementById('connection-form').checkValidity()) {
            document.getElementById('connection-form').reportValidity();
            return;
        }

        const type = document.getElementById('conn-type').value;
        let credentials = {};

        if (type === 'mysql' || type === 'sqlsrv') {
            credentials = {
                host: document.getElementById('conn-db-host').value,
                port: document.getElementById('conn-db-port').value,
                database: document.getElementById('conn-db-name').value,
                username: document.getElementById('conn-db-user').value,
                password: document.getElementById('conn-db-pass').value,
            };
        } else if (type === 'odata') {
            credentials = {
                url: document.getElementById('conn-odata-url').value,
                tenant: document.getElementById('conn-odata-tenant').value,
                username: document.getElementById('conn-odata-user').value,
                password: document.getElementById('conn-odata-pass').value,
                odata_path: document.getElementById('conn-odata-path').value,
            };
        } else if (type === 'rest_api') {
            credentials = {
                url: document.getElementById('conn-rest-url').value,
                token: document.getElementById('conn-rest-token').value,
            };
        }

        const btn = document.getElementById('conn-test-btn');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Testing...';

        try {
            const res = await fetch('/api/connections/test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ type, credentials })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Connection failed', 'error');
            }
        } catch (e) {
            showToast('Error: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    async function submitConnectionForm(event) {
        if(event) event.preventDefault();
        if(!document.getElementById('connection-form').checkValidity()) {
            document.getElementById('connection-form').reportValidity();
            return;
        }

        const mode = document.getElementById('conn-form-mode').value;
        const originalKey = document.getElementById('conn-form-original-key').value;
        const type = document.getElementById('conn-type').value;
        let credentials = {};

        if (type === 'mysql' || type === 'sqlsrv') {
            credentials = {
                host: document.getElementById('conn-db-host').value,
                port: document.getElementById('conn-db-port').value,
                database: document.getElementById('conn-db-name').value,
                username: document.getElementById('conn-db-user').value,
                password: document.getElementById('conn-db-pass').value,
            };
        } else if (type === 'odata') {
            credentials = {
                url: document.getElementById('conn-odata-url').value,
                tenant: document.getElementById('conn-odata-tenant').value,
                username: document.getElementById('conn-odata-user').value,
                password: document.getElementById('conn-odata-pass').value,
                odata_path: document.getElementById('conn-odata-path').value,
            };
        } else if (type === 'rest_api') {
            credentials = {
                url: document.getElementById('conn-rest-url').value,
                token: document.getElementById('conn-rest-token').value,
            };
        }

        const payload = {
            key: document.getElementById('conn-key').value.trim(),
            name: document.getElementById('conn-name').value.trim(),
            type: type,
            credentials: credentials
        };

        const btn = document.getElementById('conn-submit-btn');
        btn.disabled = true;
        try {
            const url = mode === 'edit' ? `/api/connections/${originalKey}` : '/api/connections';
            const method = mode === 'edit' ? 'PUT' : 'POST';
            const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (res.ok && data.success) { showToast(data.message, 'success'); closeConnectionModal(); setTimeout(() => location.reload(), 800); }
            else { showToast(data.message || 'Operation failed', 'error'); }
        } catch (e) { showToast('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; }
    }

    let lastDbFetchKey = '';
    async function fetchServerDatabases() {
        const type = document.getElementById('conn-type').value;
        if (type !== 'mysql' && type !== 'sqlsrv') return;

        const host = document.getElementById('conn-db-host').value;
        const port = document.getElementById('conn-db-port').value;
        const user = document.getElementById('conn-db-user').value;
        const pass = document.getElementById('conn-db-pass').value;

        if (!host || !user) {
            showToast('Please fill Host and Username first', 'error');
            return;
        }

        const currentKey = `${type}-${host}-${port}-${user}-${pass}`;
        if (lastDbFetchKey === currentKey) {
            // Already fetched, just show dropdown if there are items
            document.getElementById('conn-db-dropdown').classList.remove('hidden');
            return; 
        }
        
        const loading = document.getElementById('conn-db-loading');
        loading.classList.remove('hidden');
        
        try {
            const res = await fetch('/api/connections/databases', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ type, credentials: { host, port, username: user, password: pass } })
            });
            const data = await res.json();
            if (res.ok && data.success && data.databases) {
                // Sort databases alphabetically
                data.databases.sort((a, b) => a.localeCompare(b));
                
                const list = document.getElementById('conn-db-list');
                
                if (data.databases.length === 0) {
                    list.innerHTML = '<li class="px-3.5 py-2 text-sm text-surface-400 italic">No databases found</li>';
                } else {
                    let html = '';
                    data.databases.forEach(db => {
                        html += `<li onclick="document.getElementById('conn-db-name').value = '${db}'" class="px-3.5 py-2 hover:bg-surface-100 cursor-pointer text-sm font-mono text-surface-800 transition-colors">${db}</li>`;
                    });
                    list.innerHTML = html;
                }
                
                if (document.activeElement === document.getElementById('conn-db-name')) {
                    document.getElementById('conn-db-dropdown').classList.remove('hidden');
                }
                
                lastDbFetchKey = currentKey;
            } else {
                showToast(data.message || 'Failed to fetch databases', 'error');
            }
        } catch (e) {
            console.error('Failed to fetch databases', e);
            showToast('Error fetching databases', 'error');
        } finally {
            loading.classList.add('hidden');
        }
    }

    async function testConnection(key, silent = false) {
        if (!connectionData[key]) return;
        const c = connectionData[key];
        const payload = { type: c.type, credentials: c.credentials, key: key };

        const statusEl = document.getElementById('status-' + key);
        const btn = document.getElementById('btn-conn-' + key);
        
        btn.disabled = true;
        statusEl.innerHTML = '<span class="material-symbols-outlined text-[12px] spin align-middle">sync</span> Ping...';
        statusEl.className = "text-label-sm text-amber-500 font-medium flex items-center gap-1";

        try {
            const res = await fetch('/api/connections/test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (res.ok && data.success) {
                statusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block shadow-[0_0_8px_rgba(16,185,129,0.4)]"></span> ' + (data.ping_ms ? data.ping_ms + 'ms' : 'Connected');
                statusEl.className = "text-label-sm text-emerald-600 font-medium flex items-center gap-1.5";
                
                btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link_off</span> Disconnect';
                btn.className = "px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-600 transition-all duration-200 text-label-sm font-semibold flex items-center gap-1";
                btn.setAttribute('onclick', `disconnectConnection('${key}')`);
                
                if(!silent) showToast(data.message, 'success');
            } else {
                throw new Error(data.message || 'Connection failed');
            }
        } catch (e) {
            statusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block shadow-[0_0_8px_rgba(239,68,68,0.4)]"></span> Failed';
            statusEl.className = "text-label-sm text-red-600 font-medium flex items-center gap-1.5";
            
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link</span> Connect';
            btn.className = "px-2.5 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 transition-all duration-200 text-label-sm font-semibold flex items-center gap-1";
            btn.setAttribute('onclick', `testConnection('${key}')`);
            if(!silent) showToast('Error: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
        }
    }

    async function disconnectConnection(key) {
        try {
            const btn = document.getElementById('btn-conn-' + key);
            const originalBtnHtml = btn.innerHTML;
            btn.innerHTML = '<span class="material-symbols-outlined animate-spin text-[14px]">progress_activity</span> Disconnecting...';
            btn.disabled = true;

            const res = await fetch(`/api/connections/${key}/disconnect`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '"token"'
                }
            });
            const data = await res.json();
            
            if (data.success) {
                const statusEl = document.getElementById('status-' + key);
                const btn = document.getElementById('btn-conn-' + key);
                
                statusEl.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-surface-300"></span> Disconnected';
                statusEl.className = "text-label-sm text-surface-400 font-medium flex items-center gap-1.5";
                
                btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link</span> Connect';
                btn.className = "px-2.5 py-1.5 rounded-lg bg-surface-50 hover:bg-surface-100 text-surface-600 transition-all duration-200 text-label-sm font-semibold flex items-center gap-1";
                btn.setAttribute('onclick', `testConnection('${key}', true)`);
                btn.disabled = false;
                showToast('Disconnected successfully', 'success');
            } else {
                throw new Error(data.message || 'Failed to disconnect');
            }
        } catch(err) {
            console.error(err);
            const btn = document.getElementById('btn-conn-' + key);
            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link_off</span> Disconnect';
            btn.disabled = false;
            showToast(err.message, 'error');
        }
    }

    // ── Delete ─────────────────────────────────────────────────────────────
    function deleteEntity(key) { 
        document.getElementById('delete-type').value = 'entity';
        deleteTargetKey = key; 
        document.getElementById('delete-name').textContent = key; 
        document.getElementById('delete-modal').classList.remove('hidden'); 
    }
    function deleteConnection(key) { 
        document.getElementById('delete-type').value = 'connection';
        deleteTargetKey = key; 
        document.getElementById('delete-name').textContent = connectionData[key] ? connectionData[key].name : key; 
        document.getElementById('delete-modal').classList.remove('hidden'); 
    }
    
    // ── Batch Delete Entities ──────────────────────────────────────────────
    function toggleAllEntities(source) {
        const checkboxes = document.querySelectorAll('.entity-checkbox');
        checkboxes.forEach(cb => {
            if (cb.closest('tr').style.display !== 'none') {
                cb.checked = source.checked;
            }
        });
        updateBatchDeleteBtn();
    }

    function updateBatchDeleteBtn() {
        const selectedCount = document.querySelectorAll('.entity-checkbox:checked').length;
        const btn = document.getElementById('btn-batch-delete');
        const countSpan = document.getElementById('batch-delete-count');
        
        if (selectedCount > 0) {
            countSpan.textContent = selectedCount;
            btn.classList.remove('hidden');
            btn.classList.add('inline-flex');
        } else {
            btn.classList.add('hidden');
            btn.classList.remove('inline-flex');
        }
        
        const selectAll = document.getElementById('selectAllEntities');
        if(selectAll) {
            const visibleCheckboxes = Array.from(document.querySelectorAll('.entity-checkbox')).filter(cb => cb.closest('tr').style.display !== 'none');
            if (visibleCheckboxes.length > 0) {
                const allChecked = visibleCheckboxes.every(cb => cb.checked);
                const someChecked = visibleCheckboxes.some(cb => cb.checked);
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
        }
    }

    async function batchDeleteEntities() {
        const selected = Array.from(document.querySelectorAll('.entity-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) return;
        
        if(!confirm(`Are you sure you want to delete ${selected.length} entities?`)) return;
        
        const btn = document.getElementById('btn-batch-delete');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Deleting...';
        
        let successCount = 0;
        for (const key of selected) {
            try {
                const res = await fetch(`/api/entities/${key}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                });
                if(res.ok) successCount++;
            } catch(e) {
                console.error('Failed to delete ' + key);
            }
        }
        
        if (successCount > 0) {
            showToast(`Successfully deleted ${successCount} entities`, 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast('Failed to delete entities', 'error');
            btn.disabled = false;
            btn.innerHTML = `<span class="material-symbols-outlined text-[14px]">delete</span> Delete Selected (<span id="batch-delete-count">${selected.length}</span>)`;
            updateBatchDeleteBtn();
        }
    }

    function closeDeleteModal() { document.getElementById('delete-modal').classList.add('hidden'); deleteTargetKey = null; }
    
    async function confirmDelete() {
        if (!deleteTargetKey) return;
        const type = document.getElementById('delete-type').value;
        const btn = document.getElementById('delete-confirm-btn');
        btn.disabled = true; 
        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Deleting…';
        try {
            const url = type === 'entity' ? `/api/entities/${deleteTargetKey}` : `/api/connections/${deleteTargetKey}`;
            const res = await fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } });
            const data = await res.json();
            if (res.ok && data.success) { showToast(data.message, 'success'); closeDeleteModal(); setTimeout(() => location.reload(), 800); }
            else { showToast(data.message || 'Delete failed', 'error'); }
        } catch (e) { showToast('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">delete</span> Delete'; }
    }


    document.addEventListener('keydown', (e) => { 
        if (e.key === 'Escape') { 
            closeEntityModal(); 
            closeConnectionModal();
            closeDeleteModal(); 
        } 
    });
