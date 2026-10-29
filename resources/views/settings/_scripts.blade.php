{{-- Settings page JavaScript — extracted for maintainability --}}
<script>
    // csrfToken is already declared globally in app.blade.php
    const entityData = @json($entities);
    const connectionData = @json($connections);
    const providerSchemas = @json($schemas);
    let deleteTargetKey = null;

    function renderCustomSelect(id, value, options, onchange) {
        const selectOptionsHtml = options.map(opt => `<option value="${opt.value}" ${opt.value === value ? 'selected' : ''}>${opt.label}</option>`).join('');
        return `
        <div x-data="customSelect('${id}')" class="relative w-full">
            <select id="${id}" class="hidden" x-ref="select" onchange="${onchange}">
                ${selectOptionsHtml}
            </select>
            
            <button type="button" @click="toggle" @click.away="close" class="w-full bg-surface-50 border border-surface-200 px-3 py-2 text-sm rounded-xl focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all font-mono shadow-sm flex justify-between items-center text-left">
                <span x-text="selectedLabel" class="truncate" :class="!selectedValue ? 'text-surface-400' : 'text-surface-900'"></span>
                <span class="material-symbols-outlined text-[18px] text-surface-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''">expand_more</span>
            </button>
            
            <div x-show="open" x-transition.opacity.duration.200ms class="absolute z-30 w-full mt-1 bg-white border border-surface-200 rounded-xl shadow-lg max-h-60 overflow-y-auto" style="display: none;">
                <ul class="py-1">
                    <template x-for="option in options" :key="option.value">
                        <li @click="selectOption(option.value)" 
                            class="px-3.5 py-2.5 hover:bg-surface-50 cursor-pointer text-sm transition-colors flex justify-between items-center"
                            :class="option.value === selectedValue ? 'bg-brand-50 text-brand-700 font-medium' : 'text-surface-700'"
                        >
                            <span x-text="option.label"></span>
                            <span x-show="option.value === selectedValue" class="material-symbols-outlined text-[16px] text-brand-600">check</span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
        `;
    }

    // ── Global Modal Helpers ──────────────────────────────────────────────
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.remove('closing');
        }
    }
    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('closing');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('closing');
        }, 200);
    }

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
            html += `<button onclick="entityGoPage(${p})" class="px-2.5 py-0.5 rounded-lg text-label-sm ${p === entityCurrentPage ? 'ui-btn-primary' : 'text-surface-500 hover:bg-surface-100'}">${p}</button>`;
        }
        html += `<button onclick="entityGoPage(${entityCurrentPage + 1})" ${entityCurrentPage >= totalPages ? 'disabled' : ''} class="p-1 rounded-lg hover:bg-surface-100 text-surface-400 disabled:opacity-30"><span class="material-symbols-outlined text-[14px]">chevron_right</span></button>`;
        html += '</div>';
        container.innerHTML = html;
        if (typeof updateBatchDeleteBtn === 'function') updateBatchDeleteBtn();
    }
    function entityGoPage(p) { entityCurrentPage = p; paginateEntities(); }
    document.addEventListener('DOMContentLoaded', paginateEntities);

    // Aliases for HTML buttons
    function editEntity(key) { openEntityModal(key); }
    function editConnection(key) { openConnectionModal(key); }

    // ── Entity Modal ───────────────────────────────────────────────────────
    function openEntityModal(editKey = null) {
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
            if (e.odata_entity && e.odata_entity.includes('::')) {
                const parts = e.odata_entity.split('::');
                document.getElementById('form-odata').value = parts[0];
                document.getElementById('form-sheet').value = parts[1] || '';
            } else {
                document.getElementById('form-odata').value = e.odata_entity || '';
                document.getElementById('form-sheet').value = '';
            }
            document.getElementById('form-table').value = e.table;
            document.getElementById('form-icon').value = e.icon || '';
            document.getElementById('form-desc').value = e.description;
            if (e.source_connection) {
                document.getElementById('form-source-conn').value = e.source_connection;
                document.getElementById('form-source-conn').dispatchEvent(new Event('change'));
            }
            if (e.destination_connection) {
                document.getElementById('form-dest-conn').value = e.destination_connection;
                document.getElementById('form-dest-conn').dispatchEvent(new Event('change'));
            }

            document.getElementById('form-sync-mode').value = e.sync_mode || 'full';
            document.getElementById('form-sync-mode').dispatchEvent(new Event('change'));
            document.getElementById('form-batch-size').value = e.batch_size || 2000;
            document.getElementById('form-watermark-type').value = e.watermark_type || '';
            document.getElementById('form-watermark-type').dispatchEvent(new Event('change'));
            document.getElementById('form-watermark-column').value = e.watermark_column || '';
            document.getElementById('form-is-active').checked = e.is_active ?? true;

            document.getElementById('modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">edit</span> Edit Entity';
            document.getElementById('form-submit-text').textContent = 'Save Changes';
            if (e.source_connection && e.odata_entity) {
                updateWatermarkOptions(e.odata_entity, false);
            }
            fetchSourceObjects();
        } else {
            const syncModeEl = document.getElementById('form-sync-mode');
            syncModeEl.value = 'full';
            syncModeEl.dispatchEvent(new Event('change'));

            document.getElementById('form-batch-size').value = 2000;

            const watermarkTypeEl = document.getElementById('form-watermark-type');
            watermarkTypeEl.value = '';
            watermarkTypeEl.dispatchEvent(new Event('change'));

            document.getElementById('form-watermark-column').value = '';
            document.getElementById('form-is-active').checked = true;
            document.getElementById('form-sheet').value = '';

            const typeEl = document.getElementById('form-type');
            if (typeEl) typeEl.dispatchEvent(new Event('change'));

            const sourceConnEl = document.getElementById('form-source-conn');
            if (sourceConnEl) sourceConnEl.dispatchEvent(new Event('change'));

            const destConnEl = document.getElementById('form-dest-conn');
            if (destConnEl) destConnEl.dispatchEvent(new Event('change'));

            document.getElementById('modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">add_circle</span> Add Entity';
            document.getElementById('form-submit-text').textContent = 'Create Entity';
            fetchSourceObjects();
        }
        toggleWatermarkFields();
        openModal('entity-modal');
    }

    function toggleWatermarkFields() {
        const mode = document.getElementById('form-sync-mode').value;
        const container = document.getElementById('watermark-container');
        if (mode === 'incremental' || mode === 'backdate') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
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
    } catch (e) { }

    function renderSourceObjects(objects) {
        requestAnimationFrame(() => {
            const list = document.getElementById('form-odata-list');
            const select = document.getElementById('form-odata-select');
            const inputContainer = document.getElementById('form-odata-container');
            const odataInput = document.getElementById('form-odata');

            const connKey = document.getElementById('form-source-conn').value;
            const connectionType = connKey && connectionData[connKey] ? connectionData[connKey].type : '';

            if (connectionType === 'sharepoint') {
                inputContainer.classList.add('hidden');
                odataInput.required = false;
                select.classList.remove('hidden');
                select.required = true;

                const isEmptyOrError = !objects || objects.length === 0 ||
                    (objects.length === 1 && (objects[0] === 'No files found' || objects[0].startsWith('Error:')));

                if (isEmptyOrError) {
                    const msg = objects && objects.length === 1 ? objects[0] : 'No objects found';
                    let html = `<option value="" disabled selected>${msg}</option>`;
                    if (odataInput.value) {
                        html += `<option value="${odataInput.value}">📄 ${odataInput.value} (Current)</option>`;
                    }
                    select.innerHTML = html;
                    if (odataInput.value) select.value = odataInput.value;
                } else {
                    let html = '<option value="" disabled selected>Select an object</option>';
                    const groups = {};
                    objects.forEach(obj => {
                        const parts = obj.split('/');
                        const drive = parts.length > 1 ? parts[0] : '/';
                        const file = parts.length > 1 ? parts.slice(1).join('/') : obj;
                        if (!groups[drive]) groups[drive] = [];
                        groups[drive].push({ full: obj, file: file });
                    });

                    for (const drive in groups) {
                        html += `<optgroup label="📁 ${drive}">`;
                        groups[drive].forEach(item => {
                            html += `<option value="${item.full}">📄 ${item.file}</option>`;
                        });
                        html += `</optgroup>`;
                    }

                    select.innerHTML = html;

                    if (odataInput.value) {
                        if (!Array.from(select.options).some(opt => opt.value === odataInput.value)) {
                            select.innerHTML += `<optgroup label="Current Value"><option value="${odataInput.value}">📄 ${odataInput.value}</option></optgroup>`;
                        }
                        select.value = odataInput.value;
                    }
                }
            } else {
                inputContainer.classList.remove('hidden');
                odataInput.required = true;
                select.classList.add('hidden');
                select.required = false;

                const isEmptyOrError = !objects || objects.length === 0 ||
                    (objects.length === 1 && (objects[0] === 'No files found' || objects[0].startsWith('Error:')));

                if (isEmptyOrError) {
                    const msg = objects && objects.length === 1 ? objects[0] : 'No objects found';
                    list.innerHTML = `<li class="px-3.5 py-2 text-sm text-surface-400 italic">${msg}</li>`;
                } else {
                    let html = '';
                    objects.forEach(obj => {
                        html += `<li onclick="document.getElementById('form-odata').value = '${obj}'; document.getElementById('form-odata-dropdown').classList.add('hidden'); updateWatermarkOptions('${obj}', true)" class="px-3.5 py-2 hover:bg-surface-100 cursor-pointer text-sm font-mono text-surface-800 transition-colors">${obj}</li>`;
                    });
                    list.innerHTML = html;
                }
            }
        });
    }

    async function fetchSourceObjects(force = false) {
        const connEl = document.getElementById('form-source-conn');
        const connKey = connEl.value;
        const sheetContainer = document.getElementById('form-sheet-container');

        const btnDownload = document.getElementById('btn-download-source');
        if (btnDownload) btnDownload.classList.add('hidden');

        if (connEl.options[connEl.selectedIndex] && connEl.options[connEl.selectedIndex].text.includes('(SHAREPOINT)')) {
            if (sheetContainer) sheetContainer.classList.remove('hidden');
        } else {
            if (sheetContainer) {
                sheetContainer.classList.add('hidden');
                document.getElementById('form-sheet').value = '';
            }
        }

        if (!connKey) {
            renderSourceObjects([]);
            return;
        }

        if (!force && cachedSourceObjects[connKey]) {
            renderSourceObjects(cachedSourceObjects[connKey]);
            return;
        }

        const loading = document.getElementById('form-odata-loading');
        loading.classList.remove('hidden');

        try {
            const url = force ? `/api/connections/${connKey}/objects?force=true` : `/api/connections/${connKey}/objects`;
            const res = await fetch(url);
            const data = await res.json();
            if (res.ok && data.success && data.objects) {
                data.objects.sort((a, b) => a.localeCompare(b));
                cachedSourceObjects[connKey] = data.objects;
                try {
                    localStorage.setItem('cached_source_objects', JSON.stringify(cachedSourceObjects));
                } catch (e) { }

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

    let cachedWatermarkColumns = [];

    function downloadSourceObject() {
        const connKey = document.getElementById('form-source-conn').value;
        const objectName = document.getElementById('form-odata').value;
        if (!connKey || !objectName) return;

        window.location.href = `/api/connections/${connKey}/objects/${encodeURIComponent(objectName)}/download`;
    }

    async function fetchExcelSheets(objectName) {
        if (!objectName) return;
        const connEl = document.getElementById('form-source-conn');
        const connKey = connEl.value;
        if (!connKey) return;
        if (!connEl.options[connEl.selectedIndex] || !connEl.options[connEl.selectedIndex].text.includes('(SHAREPOINT)')) return;
        if (!objectName.toLowerCase().endsWith('.xlsx')) return;

        const loading = document.getElementById('form-sheet-loading');
        if (loading) loading.classList.remove('hidden');

        try {
            const res = await fetch(`/api/connections/${connKey}/objects/${encodeURIComponent(objectName)}/sheets`);
            const data = await res.json();
            const list = document.getElementById('form-sheet-list');
            if (res.ok && data.success && data.sheets && data.sheets.length > 0) {
                let html = '';
                data.sheets.forEach(sheet => {
                    html += `<li onclick="document.getElementById('form-sheet').value = '${sheet}'; document.getElementById('form-sheet-dropdown').classList.add('hidden');" class="px-3.5 py-2 hover:bg-surface-100 cursor-pointer text-sm font-mono text-surface-800 transition-colors">${sheet}</li>`;
                });
                list.innerHTML = html;
            } else {
                list.innerHTML = `<li class="px-3.5 py-2 text-sm text-surface-400 italic">No sheets found</li>`;
            }
        } catch (e) {
            console.error('Failed to fetch sheets', e);
        } finally {
            if (loading) loading.classList.add('hidden');
        }
    }

    function autoSuggestEntityFields(objectName) {
        const mode = document.getElementById('form-mode')?.value;
        if (mode !== 'create') return;

        const keyEl = document.getElementById('form-key');
        const tableEl = document.getElementById('form-table');
        const descEl = document.getElementById('form-desc');
        const connEl = document.getElementById('form-source-conn');

        if (!keyEl || !tableEl || !descEl || !connEl) return;

        // Extract base name: remove file extensions and directory paths
        let baseName = objectName.replace(/\.[^/.]+$/, "").split('/').pop();

        // 1. Entity Key: PascalCase, alphanumeric only
        let pascalCase = baseName
            .replace(/[^a-zA-Z0-9]/g, ' ')
            .split(' ')
            .filter(word => word.length > 0)
            .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join('');

        // 2. Display Description: Title Case, spaced
        let titleCase = baseName
            .replace(/([a-z])([A-Z])/g, '$1 $2') // Add space between camelCase
            .replace(/[^a-zA-Z0-9]/g, ' ')
            .split(' ')
            .filter(word => word.length > 0)
            .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join(' ');

        // 3. Target SQL Table: snake_case
        let snakeCase = baseName
            .replace(/([a-z])([A-Z])/g, '$1_$2')
            .replace(/[^a-zA-Z0-9]/g, '_')
            .replace(/_+/g, '_')
            .toLowerCase()
            .replace(/^_|_$/g, '');

        // Add prefix based on connection name (e.g. "Acumatica ERP" -> "acu_")
        let prefix = 'tbl_';
        if (connEl.options && connEl.selectedIndex >= 0) {
            const connText = connEl.options[connEl.selectedIndex].text.replace(/\([^)]+\)/g, '').trim();
            if (connText.length >= 3) {
                prefix = connText.substring(0, 3).toLowerCase() + '_';
            }
        }

        let targetTable = prefix + snakeCase;

        // Auto-fill only if fields are empty
        if (!keyEl.value) keyEl.value = pascalCase;
        if (!descEl.value) descEl.value = titleCase;
        if (!tableEl.value) tableEl.value = targetTable;
    }

    async function updateWatermarkOptions(objectName, autoSet = true) {
        if (!objectName) return;

        // Trigger auto-suggest logic when an object is selected
        autoSuggestEntityFields(objectName);

        const connEl = document.getElementById('form-source-conn');
        const connKey = connEl.value;
        if (!connKey) return;

        const btnDownload = document.getElementById('btn-download-source');
        if (btnDownload) {
            if (connEl.options[connEl.selectedIndex] && connEl.options[connEl.selectedIndex].text.includes('(SHAREPOINT)')) {
                btnDownload.classList.remove('hidden');
            } else {
                btnDownload.classList.add('hidden');
            }
        }

        fetchExcelSheets(objectName);

        const loading = document.getElementById('watermark-loading');
        if (loading) loading.classList.remove('hidden');

        try {
            const res = await fetch(`/api/connections/${connKey}/objects/${encodeURIComponent(objectName)}/columns`);
            const data = await res.json();
            if (res.ok && data.success && data.columns) {
                cachedWatermarkColumns = data.columns;
                renderWatermarkOptions(autoSet);
            }
        } catch (e) {
            console.error('Failed to fetch columns for watermark', e);
        } finally {
            if (loading) loading.classList.add('hidden');
        }
    }

    function renderWatermarkOptions(autoSet = false) {
        const list = document.getElementById('watermark-columns-list');
        if (!list) return;

        list.innerHTML = '';
        const watermarkType = document.getElementById('form-watermark-type').value;
        let availableColumns = cachedWatermarkColumns;

        if (watermarkType === 'datetime') {
            const dateColumns = availableColumns.filter(c => {
                const type = (c.type || '').toLowerCase();
                return type.includes('date') || type.includes('time') || type.includes('timestamp');
            });
            if (dateColumns.length > 0) {
                availableColumns = dateColumns;
            }
        }

        let bestMatch = '';
        let html = '';

        if (availableColumns.length === 0) {
            html = `<li class="px-3.5 py-2 text-sm text-surface-400 italic">No columns found</li>`;
        } else {
            availableColumns.forEach(colObj => {
                const colName = colObj.name;
                html += `<li onclick="document.getElementById('form-watermark-column').value = '${colName}'; document.getElementById('form-watermark-dropdown').classList.add('hidden')" class="px-3.5 py-2 hover:bg-surface-100 cursor-pointer text-sm font-mono text-surface-800 transition-colors">${colName}</li>`;

                if (!bestMatch) {
                    const lower = colName.toLowerCase();
                    if (lower.includes('lastmodified') || lower.includes('lastupdate') || lower.includes('timestamp') || lower === 'modifiedat' || lower === 'updatedat') {
                        bestMatch = colName;
                    }
                }
            });
        }

        list.innerHTML = html;

        const wmInput = document.getElementById('form-watermark-column');
        if (wmInput && autoSet && !wmInput.value && bestMatch) {
            wmInput.value = bestMatch;
        }
    }

    function closeEntityModal() { closeModal('entity-modal'); }

    const providerRegistry = @json($providers);

    async function submitEntityForm(event) {
        if (event) event.preventDefault();
        if (!document.getElementById('entity-form').checkValidity()) {
            document.getElementById('entity-form').reportValidity();
            return;
        }

        const mode = document.getElementById('form-mode').value;
        const originalKey = document.getElementById('form-original-key').value;
        const btn = document.getElementById('form-submit-btn');
        const payload = {
            key: document.getElementById('form-key').value.trim(),
            odata_entity: (() => {
                let odata = document.getElementById('form-odata').value.trim();
                const sheet = document.getElementById('form-sheet').value.trim();
                if (sheet) odata += '::' + sheet;
                return odata;
            })(),
            table: document.getElementById('form-table').value.trim(),
            type: document.getElementById('form-type').value,
            icon: document.getElementById('form-icon').value.trim() || 'database',
            description: document.getElementById('form-desc').value.trim(),
            source_connection: document.getElementById('form-source-conn').value,
            destination_connection: document.getElementById('form-dest-conn').value,
            sync_mode: document.getElementById('form-sync-mode').value,
            batch_size: parseInt(document.getElementById('form-batch-size').value, 10),
            watermark_type: document.getElementById('form-watermark-type').value,
            watermark_column: document.getElementById('form-watermark-column').value.trim(),
            is_active: document.getElementById('form-is-active').checked,
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

    // ── Field Management Modal ─────────────────────────────────────────────
    let currentFieldsEntity = null;
    let currentFieldsData = [];
    let fieldsFilterMode = 'all'; // 'all' | 'keys' | 'candidates' | 'enabled'
    let sortKeysFirst = false;
    let lastClickedKeyRowPos = null;
    let lastClickedEnableRowPos = null;
    let currentVisibleFieldIndices = [];

    const AUDIT_EXCLUDE_KEY_NAMES = new Set([
        'createdbyid', 'createdbyscreenid', 'createddatetime',
        'lastmodifiedbyid', 'lastmodifiedbyscreenid', 'lastmodifieddatetime',
        'tstamp', 'noteid', '_sync_id', '_synced_at', '_row_hash'
    ]);

    function isKeyCandidateField(fieldName, fieldObj = null) {
        if (!fieldName) return false;
        const lower = String(fieldName).trim().toLowerCase();
        if (AUDIT_EXCLUDE_KEY_NAMES.has(lower)) return false;
        if (fieldObj && fieldObj.semantic_type === 'identifier') return true;
        if (lower === 'id' || lower === 'key' || lower === 'code' || lower === 'uuid' || lower === 'guid') return true;
        return /(id|cd|key|code|nbr|no|uuid|guid)$/i.test(fieldName) || /^(id_|pk_|key_|kode_|no_)/i.test(fieldName);
    }

    function matchesFieldsFilter(field, searchFilter, mode = fieldsFilterMode) {
        if (searchFilter) {
            const sf = (field.source_field || '').toLowerCase();
            const df = (field.destination_field || '').toLowerCase();
            const lbl = (field.label || '').toLowerCase();
            const desc = (field.description || '').toLowerCase();
            if (!sf.includes(searchFilter) && !df.includes(searchFilter) && !lbl.includes(searchFilter) && !desc.includes(searchFilter)) {
                return false;
            }
        }
        if (mode === 'keys') return !!field.is_key;
        if (mode === 'candidates') return isKeyCandidateField(field.source_field, field) || !!field.is_key;
        if (mode === 'enabled') return field.is_enabled !== false;
        return true;
    }

    async function manageEntityFields(key) {
        currentFieldsEntity = key;
        fieldsFilterMode = 'all';
        sortKeysFirst = false;
        lastClickedKeyRowPos = null;
        lastClickedEnableRowPos = null;
        document.getElementById('fields-modal-entity-name').textContent = key;
        document.getElementById('fields-search').value = '';
        updateFieldsFilterPillsUI();
        updateSortKeysButtonUI();
        openModal('fields-modal');
        await loadFields(key);
    }

    function closeFieldsModal() {
        closeModal('fields-modal');
        currentFieldsEntity = null;
        currentFieldsData = [];
        lastClickedKeyRowPos = null;
        lastClickedEnableRowPos = null;
    }

    function clearFieldsSearch() {
        const input = document.getElementById('fields-search');
        if (input) {
            input.value = '';
            filterFieldsTable();
            input.focus();
        }
    }

    function setFieldsFilterMode(mode) {
        fieldsFilterMode = mode;
        lastClickedKeyRowPos = null;
        lastClickedEnableRowPos = null;
        updateFieldsFilterPillsUI();
        renderFieldsTable();
    }

    function updateFieldsFilterPillsUI() {
        const modes = ['all', 'keys', 'candidates', 'enabled'];
        modes.forEach(m => {
            const btn = document.getElementById(`fields-filter-${m}`);
            if (!btn) return;
            if (m === fieldsFilterMode) {
                btn.classList.add('bg-white', 'text-surface-900', 'shadow-sm', 'font-semibold');
                btn.classList.remove('text-surface-600');
            } else {
                btn.classList.remove('bg-white', 'text-surface-900', 'shadow-sm', 'font-semibold');
                btn.classList.add('text-surface-600');
            }
        });
    }

    function toggleSortKeysFirst() {
        sortKeysFirst = !sortKeysFirst;
        updateSortKeysButtonUI();
        renderFieldsTable();
    }

    function updateSortKeysButtonUI() {
        const btn = document.getElementById('btn-sort-keys-first');
        if (!btn) return;
        if (sortKeysFirst) {
            btn.className = 'px-2.5 py-1.5 rounded-xl border border-amber-300 bg-amber-100 text-xs font-semibold text-amber-900 transition-colors inline-flex items-center gap-1';
        } else {
            btn.className = 'px-2.5 py-1.5 rounded-xl border border-surface-200 bg-white text-xs font-medium text-surface-700 hover:bg-surface-50 transition-colors inline-flex items-center gap-1';
        }
    }

    async function loadFields(key) {
        const loading = document.getElementById('fields-loading');
        loading.classList.remove('hidden');
        document.getElementById('fields-loading-text').textContent = 'Loading fields...';

        try {
            const res = await fetch(`/api/entities/${key}/mappings`);
            const data = await res.json();

            if (res.ok) {
                currentFieldsData = data.fields || (Array.isArray(data) ? data : Object.values(data));
                if (!Array.isArray(currentFieldsData) && typeof currentFieldsData === 'object') {
                    currentFieldsData = Object.keys(currentFieldsData).map(k => ({ source_field: k, ...currentFieldsData[k] }));
                }

                currentFieldsData.sort((a, b) => a.source_field.localeCompare(b.source_field));

                renderFieldsTable();
            } else {
                showToast(data.message || 'Failed to load fields', 'error');
            }
        } catch (e) {
            showToast('Error: ' + e.message, 'error');
        } finally {
            loading.classList.add('hidden');
        }
    }

    function updateActiveKeysBarAndBadges() {
        const totalAll = currentFieldsData.length;
        const keyFields = [];
        let candidatesCount = 0;
        let enabledCount = 0;

        currentFieldsData.forEach((f, idx) => {
            if (f.is_key) keyFields.push({ field: f, index: idx });
            if (isKeyCandidateField(f.source_field, f) || f.is_key) candidatesCount++;
            if (f.is_enabled !== false) enabledCount++;
        });

        const elAll = document.getElementById('badge-count-all');
        const elKeys = document.getElementById('badge-count-keys');
        const elCand = document.getElementById('badge-count-candidates');
        const elEn = document.getElementById('badge-count-enabled');
        if (elAll) elAll.textContent = totalAll;
        if (elKeys) elKeys.textContent = keyFields.length;
        if (elCand) elCand.textContent = candidatesCount;
        if (elEn) elEn.textContent = enabledCount;

        const keysSummary = document.getElementById('fields-keys-summary');
        if (keysSummary) {
            keysSummary.textContent = `${keyFields.length} unique key${keyFields.length === 1 ? '' : 's'} selected`;
        }

        const chipsContainer = document.getElementById('active-keys-chips');
        if (!chipsContainer) return;

        if (keyFields.length === 0) {
            chipsContainer.innerHTML = `<span class="text-amber-700/80 italic">Belum ada kolom Unique Key dipilih — klik tombol <strong>Key</strong> pada baris, atau klik <strong>Auto-Detect Key</strong>.</span>`;
            return;
        }

        chipsContainer.innerHTML = keyFields.map(({ field, index }) => `
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-white border border-amber-300 text-amber-900 font-mono text-xs font-semibold shadow-sm">
                <span class="material-symbols-outlined text-[13px] text-amber-500">vpn_key</span>
                <button type="button" onclick="scrollToFieldRow(${index})" class="hover:underline" title="Klik untuk menuju baris ${field.source_field}">${field.source_field}</button>
                <button type="button" onclick="removeKeyField(${index})" class="ml-0.5 text-amber-500 hover:text-red-600 inline-flex items-center" title="Hapus dari Unique Key">
                    <span class="material-symbols-outlined text-[14px]">close</span>
                </button>
            </span>
        `).join('');
    }

    function scrollToFieldRow(index) {
        const row = document.querySelector(`#fields-tbody tr[data-index="${index}"]`);
        if (row) {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.classList.add('ring-2', 'ring-amber-400');
            setTimeout(() => row.classList.remove('ring-2', 'ring-amber-400'), 1400);
        } else if (fieldsFilterMode !== 'all' || document.getElementById('fields-search').value) {
            fieldsFilterMode = 'all';
            document.getElementById('fields-search').value = '';
            updateFieldsFilterPillsUI();
            renderFieldsTable();
            setTimeout(() => scrollToFieldRow(index), 50);
        }
    }

    function removeKeyField(index) {
        if (currentFieldsData[index]) {
            currentFieldsData[index].is_key = false;
            renderFieldsTable();
        }
    }

    function renderFieldsTable() {
        const tbody = document.getElementById('fields-tbody');
        const filter = document.getElementById('fields-search').value.toLowerCase();
        const searchInput = document.getElementById('fields-search');
        const filter = (searchInput ? searchInput.value : '').trim().toLowerCase();
        const clearBtn = document.getElementById('fields-search-clear');
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', filter.length === 0);
        }

        updateActiveKeysBarAndBadges();

        // Build list of indices to display
        let indexedItems = currentFieldsData.map((field, index) => ({ field, index }))
            .filter(({ field }) => matchesFieldsFilter(field, filter, fieldsFilterMode));

        if (sortKeysFirst) {
            indexedItems.sort((a, b) => {
                const aKey = a.field.is_key ? 1 : 0;
                const bKey = b.field.is_key ? 1 : 0;
                if (aKey !== bKey) return bKey - aKey;
                return a.field.source_field.localeCompare(b.field.source_field);
            });
        }

        currentVisibleFieldIndices = indexedItems.map(item => item.index);

        let html = '';
        let count = 0;
        let allEnabled = true;
        let count = indexedItems.length;
        let visibleEnabledCount = 0;
        let visibleKeyCount = 0;
        let visibleNullableCount = 0;

        currentFieldsData.forEach((field, index) => {
            if (filter && !field.source_field.toLowerCase().includes(filter) && !(field.destination_field || '').toLowerCase().includes(filter)) {
                return;
            }
            count++;

            if (field.is_enabled === false) {
                allEnabled = false;
            }
            indexedItems.forEach(({ field, index }, visiblePos) => {
                const isEnabled = field.is_enabled !== false;
                const isKey = !!field.is_key;
                const isNullable = field.is_nullable !== false;
                const isCandidate = !isKey && isKeyCandidateField(field.source_field, field);

                if (isEnabled) visibleEnabledCount++;
                if (isKey) visibleKeyCount++;
                if (isNullable) visibleNullableCount++;

                const rowBgClass = isKey
                    ? 'bg-amber-50/40 hover:bg-amber-50/70'
                    : 'hover:bg-surface-50/50';

                const keyBtnClass = isKey
                    ? 'bg-amber-500 text-white border-amber-500 shadow-sm hover:bg-amber-600'
                    : (isCandidate
                        ? 'bg-amber-50/80 text-amber-700 border-amber-200 hover:bg-amber-100 hover:border-amber-300'
                        : 'bg-white text-surface-400 border-surface-200 hover:text-amber-600 hover:border-amber-300 hover:bg-amber-50/40');

                html += `
                <tr class="hover:bg-surface-50/50 transition-colors" data-index="${index}">
                    <td class="py-2.5 px-4 text-center border-b border-surface-100">
                        <input type="checkbox" class="field-enabled ui-checkbox" ${field.is_enabled !== false ? 'checked' : ''} onchange="updateFieldData(${index}, 'is_enabled', this.checked)">
                <tr class="${rowBgClass} transition-colors" data-index="${index}" data-visible-pos="${visiblePos}">
                    <td class="py-2.5 px-3 text-center border-b border-surface-100">
                        <input type="checkbox" class="field-enabled ui-checkbox cursor-pointer" ${isEnabled ? 'checked' : ''}
                            onclick="handleEnableClick(event, ${index}, ${visiblePos}, this.checked)">
                    </td>
                    <td class="py-2.5 px-2 text-center border-b border-surface-100 border-x border-amber-100/50">
                        <button type="button"
                            onclick="handleKeyClick(event, ${index}, ${visiblePos})"
                            class="inline-flex items-center justify-center gap-1 px-2.5 py-1 rounded-lg border text-xs font-semibold transition-all cursor-pointer select-none ${keyBtnClass}"
                            title="${isKey ? 'Unique Key aktif (Klik untuk hapus, Shift+Klik untuk pilih rentang)' : 'Jadikan Unique Key (Shift+Klik untuk pilih rentang)'}">
                            <span class="material-symbols-outlined text-[15px]">${isKey ? 'vpn_key' : 'key'}</span>
                            <span>${isKey ? 'KEY' : 'Set'}</span>
                        </button>
                    </td>
                    <td class="py-2.5 px-4 border-b border-surface-100 font-mono text-surface-900 font-medium">
                        ${field.source_field}
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span>${field.source_field}</span>
                            ${isKey ? '<span class="px-1.5 py-0.5 text-[10px] font-sans font-bold uppercase tracking-wider rounded bg-amber-100 text-amber-800 border border-amber-200">Unique Key</span>' : ''}
                            ${isCandidate ? `<button type="button" onclick="handleKeyClick(event, ${index}, ${visiblePos})" class="px-1.5 py-0.5 text-[10px] font-sans font-medium rounded bg-surface-100 text-surface-600 hover:bg-amber-100 hover:text-amber-800 transition-colors" title="Terdeteksi kandidat ID/Key — klik untuk jadikan Key">+ ID Candidate</button>` : ''}
                        </div>
                        <input type="hidden" class="field-source" value="${field.source_field}">
                    </td>
                    <td class="py-2.5 px-3 border-b border-surface-100">
                        <input type="text" class="field-dest w-full bg-surface-50 border border-surface-200 px-3 py-2 text-sm rounded-xl focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all font-mono shadow-sm" value="${field.destination_field || ''}" onchange="updateFieldData(${index}, 'destination_field', this.value)">
                    </td>
                    <td class="py-2.5 px-3 border-b border-surface-100">
                        ${renderCustomSelect(
                    'data_type_' + index,
                    field.data_type || '',
                    [
                        { value: '', label: 'Auto (Text)' },
                        { value: 'string', label: 'String (VARCHAR)' },
                        { value: 'text', label: 'Text (TEXT)' },
                        { value: 'integer', label: 'Integer' },
                        { value: 'number', label: 'Number (Decimal)' },
                        { value: 'boolean', label: 'Boolean' },
                        { value: 'datetime', label: 'DateTime' },
                        { value: 'date', label: 'Date' }
                    ],
                    `updateFieldData(${index}, 'data_type', this.value)`
                )}
                    </td>
                    <td class="py-2.5 px-3 border-b border-surface-100">
                        ${renderCustomSelect(
                    'semantic_type_' + index,
                    field.semantic_type || '',
                    [
                        { value: '', label: 'None' },
                        { value: 'identifier', label: 'Identifier' },
                        { value: 'metric', label: 'Metric' },
                        { value: 'amount', label: 'Amount' },
                        { value: 'currency', label: 'Currency' },
                        { value: 'dimension', label: 'Dimension' },
                        { value: 'categorical', label: 'Categorical' },
                        { value: 'date', label: 'Date' },
                        { value: 'datetime', label: 'Datetime' },
                        { value: 'boolean', label: 'Boolean' },
                        { value: 'status', label: 'Status' }
                    ],
                    `updateFieldData(${index}, 'semantic_type', this.value)`
                )}
                    </td>
                    <td class="py-2.5 px-3 text-center border-b border-surface-100">
                        <input type="checkbox" class="field-nullable ui-checkbox cursor-pointer" ${isNullable ? 'checked' : ''} onchange="updateFieldData(${index}, 'is_nullable', this.checked); syncHeaderCheckboxes();">
                    </td>
                    <td class="py-2.5 px-3 border-b border-surface-100">
                        <textarea class="field-label w-full bg-surface-50 border border-surface-200 px-3 py-2 text-sm rounded-xl focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all shadow-sm resize-y" rows="1" placeholder="Label" onchange="updateFieldData(${index}, 'label', this.value)">${field.label || ''}</textarea>
                    </td>
                    <td class="py-2.5 px-3 border-b border-surface-100">
                        <textarea class="field-description w-full bg-surface-50 border border-surface-200 px-3 py-2 text-sm rounded-xl focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all shadow-sm resize-y" rows="1" placeholder="Description" onchange="updateFieldData(${index}, 'description', this.value)">${field.description || ''}</textarea>
                    </td>
                    <td class="py-2.5 px-3 border-b border-surface-100">
                        <textarea class="field-aliases w-full bg-surface-50 border border-surface-200 px-3 py-2 text-sm rounded-xl focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all shadow-sm resize-y" rows="1" placeholder="tag1, tag2" onchange="updateFieldAliases(${index}, this.value)">${(field.aliases || []).join(', ')}</textarea>
                    </td>
                    <td class="py-2.5 px-3 text-center border-b border-surface-100">
                        <input type="checkbox" class="field-key w-4 h-4 text-amber-500 rounded border-surface-300 focus:ring-amber-500" ${field.is_key ? 'checked' : ''} onchange="updateFieldData(${index}, 'is_key', this.checked)">
                    </td>
                    <td class="py-2.5 px-3 text-center border-b border-surface-100">
                        <input type="checkbox" class="field-nullable ui-checkbox" ${field.is_nullable !== false ? 'checked' : ''} onchange="updateFieldData(${index}, 'is_nullable', this.checked)">
                    </td>
                    <td class="py-2.5 px-3 border-b border-surface-100">
                        <textarea class="field-transform w-full bg-surface-50 border border-surface-200 px-3 py-2 text-sm rounded-xl focus:bg-white focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all shadow-sm resize-y" rows="1" placeholder="e.g. trim" onchange="updateFieldData(${index}, 'transform_rule', this.value)">${field.transform_rule || ''}</textarea>
                    </td>
                </tr>
            `;
            });

            if (count === 0) {
                html = `
                <tr>
                    <td colspan="11" class="py-10 text-center text-surface-400">
                        <span class="material-symbols-outlined text-[28px] block mb-1">filter_list_off</span>
                        Tidak ada field yang sesuai dengan filter saat ini.
                    </td>
                </tr>
            `;
            }

            tbody.innerHTML = html;
            document.getElementById('fields-count-summary').textContent = `${count} fields visible`;
            document.getElementById('fields-toggle-all').checked = count > 0 && allEnabled;
            document.getElementById('fields-count-summary').textContent = `${count} of ${currentFieldsData.length} fields visible`;

            const toggleAllEl = document.getElementById('fields-toggle-all');
            if (toggleAllEl) {
                toggleAllEl.checked = count > 0 && visibleEnabledCount === count;
                toggleAllEl.indeterminate = visibleEnabledCount > 0 && visibleEnabledCount < count;
            }

            const toggleAllKeysEl = document.getElementById('fields-toggle-all-keys');
            if (toggleAllKeysEl) {
                toggleAllKeysEl.checked = count > 0 && visibleKeyCount === count;
                toggleAllKeysEl.indeterminate = visibleKeyCount > 0 && visibleKeyCount < count;
            }

            const toggleAllNullEl = document.getElementById('fields-toggle-all-nullable');
            if (toggleAllNullEl) {
                toggleAllNullEl.checked = count > 0 && visibleNullableCount === count;
                toggleAllNullEl.indeterminate = visibleNullableCount > 0 && visibleNullableCount < count;
            }
        }

    function syncHeaderCheckboxes() {
                updateActiveKeysBarAndBadges();
            }

    function handleKeyClick(event, dataIndex, visiblePos) {
                if (!currentFieldsData[dataIndex]) return;
                const targetState = !currentFieldsData[dataIndex].is_key;

                if (event && event.shiftKey && lastClickedKeyRowPos !== null && currentVisibleFieldIndices.length > 0) {
                    const start = Math.min(lastClickedKeyRowPos, visiblePos);
                    const end = Math.max(lastClickedKeyRowPos, visiblePos);
                    for (let pos = start; pos <= end; pos++) {
                        const idx = currentVisibleFieldIndices[pos];
                        if (idx !== undefined && currentFieldsData[idx]) {
                            currentFieldsData[idx].is_key = targetState;
                            if (targetState) {
                                currentFieldsData[idx].is_enabled = true;
                                currentFieldsData[idx].is_nullable = false;
                            }
                        }
                    }
                } else {
                    currentFieldsData[dataIndex].is_key = targetState;
                    if (targetState) {
                        currentFieldsData[dataIndex].is_enabled = true;
                        currentFieldsData[dataIndex].is_nullable = false;
                    }
                }

                lastClickedKeyRowPos = visiblePos;
                renderFieldsTable();
            }

    function handleEnableClick(event, dataIndex, visiblePos, checked) {
                if (!currentFieldsData[dataIndex]) return;

                if (event && event.shiftKey && lastClickedEnableRowPos !== null && currentVisibleFieldIndices.length > 0) {
                    const start = Math.min(lastClickedEnableRowPos, visiblePos);
                    const end = Math.max(lastClickedEnableRowPos, visiblePos);
                    for (let pos = start; pos <= end; pos++) {
                        const idx = currentVisibleFieldIndices[pos];
                        if (idx !== undefined && currentFieldsData[idx]) {
                            currentFieldsData[idx].is_enabled = checked;
                        }
                    }
                    lastClickedEnableRowPos = visiblePos;
                    renderFieldsTable();
                } else {
                    currentFieldsData[dataIndex].is_enabled = checked;
                    lastClickedEnableRowPos = visiblePos;
                    updateActiveKeysBarAndBadges();
                }
            }

    function toggleAllVisibleKeys(checkbox) {
                const checked = checkbox.checked;
                setVisibleFieldsKey(checked);
            }

    function setVisibleFieldsKey(checked) {
                if (currentVisibleFieldIndices.length === 0) {
                    showToast('Tidak ada field yang tampil untuk diubah.', 'warning');
                    return;
                }
                currentVisibleFieldIndices.forEach(idx => {
                    if (currentFieldsData[idx]) {
                        currentFieldsData[idx].is_key = checked;
                        if (checked) {
                            currentFieldsData[idx].is_enabled = true;
                            currentFieldsData[idx].is_nullable = false;
                        }
                    }
                });
                renderFieldsTable();
                showToast(
                    checked
                        ? `${currentVisibleFieldIndices.length} field yang tampil dijadikan Unique Key.`
                        : `Unique Key dihapus dari ${currentVisibleFieldIndices.length} field yang tampil.`,
                    'info'
                );
            }

    function autoDetectKeyFields() {
                let detected = [];
                currentFieldsData.forEach(field => {
                    if (isKeyCandidateField(field.source_field, field)) {
                        field.is_key = true;
                        field.is_enabled = true;
                        field.is_nullable = false;
                        detected.push(field.source_field);
                    }
                });

                renderFieldsTable();

                if (detected.length > 0) {
                    showToast(`Auto-Detect memilih ${detected.length} Unique Key: ${detected.slice(0, 5).join(', ')}${detected.length > 5 ? '...' : ''}`, 'success');
                } else {
                    showToast('Tidak ditemukan kolom berpola ID/Key/Code secara otomatis. Silakan pilih manual atau gunakan filter.', 'warning');
                }
            }

    function clearAllKeyFields() {
                let cleared = 0;
                currentFieldsData.forEach(field => {
                    if (field.is_key) {
                        field.is_key = false;
                        cleared++;
                    }
                });
                renderFieldsTable();
                if (cleared > 0) {
                    showToast(`${cleared} Unique Key telah di-reset.`, 'info');
                }
            }

    function toggleAllVisibleNullable(checkbox) {
                const checked = checkbox.checked;
                currentVisibleFieldIndices.forEach(idx => {
                    if (currentFieldsData[idx]) {
                        currentFieldsData[idx].is_nullable = checked;
                    }
                });
                renderFieldsTable();
            }

    function updateFieldData(index, prop, value) {
                if (currentFieldsData[index]) {
                    currentFieldsData[index][prop] = value === '' ? null : value;
                }
            }

    function updateFieldAliases(index, value) {
                if (currentFieldsData[index]) {
                    if (!value || value.trim() === '') {
                        currentFieldsData[index].aliases = null;
                    } else {
                        currentFieldsData[index].aliases = value.split(',').map(s => s.trim()).filter(s => s.length > 0);
                    }
                }
            }

    function filterFieldsTable() {
                lastClickedKeyRowPos = null;
                lastClickedEnableRowPos = null;
                renderFieldsTable();
            }

    function toggleAllFields(checkbox) {
                const checked = checkbox.checked;
                const filter = document.getElementById('fields-search').value.toLowerCase();

                currentFieldsData.forEach((field) => {
                    if (filter && !field.source_field.toLowerCase().includes(filter) && !(field.destination_field || '').toLowerCase().includes(filter)) {
                        return;
                        currentVisibleFieldIndices.forEach(idx => {
                            if (currentFieldsData[idx]) {
                                currentFieldsData[idx].is_enabled = checked;
                            }
                            field.is_enabled = checked;
                        });

                        renderFieldsTable();
                    }

                    async function submitFieldsForm() {
                        if (!currentFieldsEntity) return;

                        const btn = document.getElementById('fields-submit-btn');
                        btn.disabled = true;

                        try {
                            const payload = { fields: currentFieldsData };
                            const res = await fetch(`/api/entities/${currentFieldsEntity}/mappings`, {
                                method: 'PUT',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                body: JSON.stringify(payload)
                            });
                            const data = await res.json();

                            if (res.ok && data.success) {
                                showToast(data.message, 'success');
                                closeFieldsModal();
                            } else {
                                showToast(data.message || 'Operation failed', 'error');
                            }
                        } catch (e) {
                            showToast('Error: ' + e.message, 'error');
                        } finally {
                            btn.disabled = false;
                        }
                    }

                    async function discoverFields() {
                        if (!currentFieldsEntity) return;

                        const btn = document.getElementById('btn-discover-fields');
                        btn.disabled = true;
                        document.getElementById('fields-loading').classList.remove('hidden');
                        document.getElementById('fields-loading-text').textContent = 'Discovering metadata (may take a minute)...';

                        try {
                            const res = await fetch(`/api/entities/${currentFieldsEntity}/discover`, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                            });
                            const data = await res.json();

                            if (res.ok) {
                                showToast('Discovery completed', 'success');
                                await loadFields(currentFieldsEntity);
                            } else {
                                showToast(data.message || 'Discovery failed', 'error');
                            }
                        } catch (e) {
                            showToast('Error: ' + e.message, 'error');
                        } finally {
                            btn.disabled = false;
                            document.getElementById('fields-loading').classList.add('hidden');
                        }
                    }

                    async function suggestFieldsAI() {
                        if (!currentFieldsEntity) return;

                        const btn = document.getElementById('btn-suggest-fields-ai');
                        btn.disabled = true;
                        document.getElementById('fields-loading').classList.remove('hidden');
                        document.getElementById('fields-loading-text').textContent = 'AI is analyzing and suggesting fields...';

                        try {
                            const res = await fetch(`/api/entities/${currentFieldsEntity}/suggest-fields`, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                            });
                            const data = await res.json();

                            if (res.ok && data.success && data.suggestions) {
                                showToast('AI suggestions generated successfully', 'success');

                                currentFieldsData.forEach((field, index) => {
                                    const suggestion = data.suggestions[field.source_field];
                                    if (suggestion) {
                                        if (suggestion.data_type) field.data_type = suggestion.data_type;
                                        if (suggestion.semantic_type) field.semantic_type = suggestion.semantic_type;
                                        if (suggestion.label) field.label = suggestion.label;
                                        if (suggestion.description) field.description = suggestion.description;
                                        if (suggestion.aliases) {
                                            if (typeof suggestion.aliases === 'string') {
                                                field.aliases = suggestion.aliases.split(',').map(s => s.trim()).filter(s => s.length > 0);
                                            } else if (Array.isArray(suggestion.aliases)) {
                                                field.aliases = suggestion.aliases;
                                            }
                                        }
                                    }
                                });

                                renderFieldsTable();
                            } else {
                                showToast(data.message || 'AI Suggestion failed', 'error');
                            }
                        } catch (e) {
                            showToast('Error: ' + e.message, 'error');
                        } finally {
                            btn.disabled = false;
                            document.getElementById('fields-loading').classList.add('hidden');
                        }
                    }

                    // ── Connection Modal ───────────────────────────────────────────────────
                    function toggleConnectionFields() {
                        const type = document.getElementById('conn-type').value;
                        const container = document.getElementById('conn-dynamic-fields');
                        container.innerHTML = '';

                        const schema = providerSchemas[type] || [];

                        schema.forEach(field => {
                            const wrapper = document.createElement('div');
                            wrapper.className = 'mb-4';

                            const label = document.createElement('label');
                            label.className = 'ui-label';
                            label.innerHTML = `${field.label} ${field.required ? '<span class="text-red-500">*</span>' : ''}`;
                            wrapper.appendChild(label);

                            if (field.type === 'select') {
                                const select = document.createElement('select');
                                select.id = `dyn_${field.field}`;
                                select.className = 'ui-select';
                                if (field.required) select.required = true;

                                let optionsData = field.options;
                                if ((!optionsData || (Array.isArray(optionsData) && optionsData.length === 0)) && field.default && typeof field.default === 'object') {
                                    optionsData = field.default;
                                }

                                for (const [val, text] of Object.entries(optionsData || {})) {
                                    const opt = document.createElement('option');
                                    opt.value = val;
                                    if (typeof text === 'object' && text !== null) {
                                        opt.value = text.value || val;
                                        opt.textContent = text.label || text.text || val;
                                    } else {
                                        opt.textContent = text;
                                    }
                                    if (field.default === val || field.placeholder === val) opt.selected = true;
                                    select.appendChild(opt);
                                }
                                wrapper.appendChild(select);
                            } else if (field.type === 'boolean') {
                                const checkbox = document.createElement('input');
                                checkbox.type = 'checkbox';
                                checkbox.id = `dyn_${field.field}`;
                                checkbox.className = 'ui-checkbox';
                                if (field.default === true || field.default === 'true' || field.default === 1) checkbox.checked = true;

                                const flex = document.createElement('div');
                                flex.className = 'flex items-center gap-2';
                                label.className = 'ui-label cursor-pointer mb-0';
                                label.setAttribute('for', checkbox.id);

                                flex.appendChild(checkbox);
                                flex.appendChild(label);

                                wrapper.innerHTML = '';
                                wrapper.appendChild(flex);
                            } else if (field.type === 'textarea') {
                                const textarea = document.createElement('textarea');
                                textarea.id = `dyn_${field.field}`;
                                textarea.className = 'ui-input';
                                textarea.rows = 3;
                                if (field.required) textarea.required = true;
                                if (field.placeholder) textarea.placeholder = field.placeholder;
                                if (field.default !== null && typeof field.default !== 'object') textarea.value = field.default;

                                wrapper.appendChild(textarea);
                            } else {
                                const input = document.createElement('input');
                                input.type = field.type;
                                input.id = `dyn_${field.field}`;
                                input.className = 'ui-input ui-input-mono';
                                if (field.required) input.required = true;
                                if (field.placeholder) input.placeholder = field.placeholder;
                                if (field.default !== null && typeof field.default !== 'object') input.value = field.default;

                                if (type === 'sharepoint' && field.field === 'site_path') {
                                    const group = document.createElement('div');
                                    group.className = 'flex gap-2';

                                    input.className = 'flex-1 ui-input ui-input-mono';
                                    group.appendChild(input);

                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'ui-btn ui-btn-sm ui-btn-secondary flex-shrink-0';
                                    btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">sync</span> Load Sites';
                                    btn.onclick = async () => {
                                        const tId = document.getElementById('dyn_tenant_id')?.value;
                                        const cId = document.getElementById('dyn_client_id')?.value;
                                        const cSec = document.getElementById('dyn_client_secret')?.value;
                                        const hName = document.getElementById('dyn_hostname')?.value;

                                        if (!tId || !cId || !cSec || !hName) {
                                            showToast('Please fill Tenant ID, Client ID, Secret, and Hostname first', 'warning');
                                            return;
                                        }

                                        btn.disabled = true;
                                        btn.innerHTML = '<span class="material-symbols-outlined text-[16px] spin">sync</span> Loading...';

                                        try {
                                            const res = await fetch('/api/connections/sharepoint/sites', {
                                                method: 'POST',
                                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                                body: JSON.stringify({ tenant_id: tId, client_id: cId, client_secret: cSec, hostname: hName })
                                            });
                                            const data = await res.json();

                                            if (data.success) {
                                                const sel = document.createElement('select');
                                                sel.id = `dyn_${field.field}`;
                                                sel.className = input.className;
                                                if (field.required) sel.required = true;

                                                let html = '<option value="">Select a site...</option>';
                                                data.sites.forEach(s => {
                                                    html += `<option value="${s.path}">${s.displayName} (${s.path})</option>`;
                                                });
                                                sel.innerHTML = html;

                                                group.replaceChild(sel, input);
                                                btn.remove();
                                                showToast('Sites loaded successfully', 'success');

                                                sel.addEventListener('change', () => {
                                                    if (typeof updateSpPreview === 'function') updateSpPreview();
                                                });
                                            } else {
                                                showToast(data.message, 'error');
                                            }
                                        } catch (e) {
                                            showToast(e.message, 'error');
                                        } finally {
                                            if (btn.parentNode) {
                                                btn.disabled = false;
                                                btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">sync</span> Load Sites';
                                            }
                                        }
                                    };
                                    group.appendChild(btn);
                                    wrapper.appendChild(group);
                                } else {
                                    wrapper.appendChild(input);
                                }
                            }

                            if (field.helpText) {
                                const help = document.createElement('p');
                                help.className = 'ui-hint';
                                help.textContent = field.helpText;
                                wrapper.appendChild(help);
                            }

                            container.appendChild(wrapper);
                        });

                        // Add Live URL Preview for Acumatica Connections
                        if (type === 'acumatica') {
                            const previewWrapper = document.createElement('div');
                            previewWrapper.className = 'mt-5 p-4 bg-brand-50 rounded-xl border border-brand-200 shadow-inner';

                            const title = document.createElement('div');
                            title.className = 'text-label-md font-semibold text-brand-800 mb-1 flex items-center gap-1.5';
                            title.innerHTML = '<span class="material-symbols-outlined text-[16px]">link</span> Computed OData URL Preview';

                            const urlText = document.createElement('div');
                            urlText.id = 'acm-url-preview';
                            urlText.className = 'text-body-sm text-brand-700 font-mono break-all font-semibold';

                            const hintText = document.createElement('div');
                            hintText.id = 'acm-entity-hint';
                            hintText.className = 'text-[11px] text-brand-600/80 mt-1.5 italic';

                            previewWrapper.appendChild(title);
                            previewWrapper.appendChild(urlText);
                            previewWrapper.appendChild(hintText);
                            container.appendChild(previewWrapper);

                            const updatePreview = () => {
                                const baseUrlInput = document.getElementById('dyn_base_url');
                                const tenantInput = document.getElementById('dyn_tenant');
                                const protocolInput = document.getElementById('dyn_protocol');
                                const overrideInput = document.getElementById('dyn_odata_url');

                                if (!baseUrlInput || !tenantInput || !protocolInput) return;

                                let base = baseUrlInput.value.trim().replace(/\/$/, '');
                                let tenant = tenantInput.value.trim().replace(/^\/|\/$/g, '');
                                let protocol = protocolInput.value;
                                let endpoint = (protocol === 'odata_v3' || protocol === 'v3') ? 'odata' : 'odatav4';

                                let finalUrl = '';
                                if (overrideInput && overrideInput.value.trim() !== '') {
                                    finalUrl = overrideInput.value.trim().replace(/\/$/, '');
                                } else {
                                    if (tenant !== '') {
                                        if (base.toLowerCase().endsWith('/' + tenant.toLowerCase())) {
                                            finalUrl = `${base}/${endpoint}`;
                                        } else {
                                            finalUrl = `${base}/${tenant}/${endpoint}`;
                                        }
                                    } else {
                                        finalUrl = `${base}/${endpoint}`;
                                    }
                                }

                                document.getElementById('acm-url-preview').textContent = finalUrl || 'https://...';
                                document.getElementById('acm-entity-hint').textContent = `* Jika memanggil entity baru contoh INSite, pemanggilan sync nya menjadi: ${finalUrl}/INSite`;
                            };

                            setTimeout(() => {
                                ['dyn_base_url', 'dyn_tenant', 'dyn_odata_url'].forEach(id => {
                                    const el = document.getElementById(id);
                                    if (el) el.addEventListener('input', updatePreview);
                                });
                                const protoEl = document.getElementById('dyn_protocol');
                                if (protoEl) protoEl.addEventListener('change', updatePreview);
                                updatePreview();
                            }, 50);
                        }

                        // Add Live Preview for SharePoint Connections
                        if (type === 'sharepoint') {
                            const previewWrapper = document.createElement('div');
                            previewWrapper.className = 'mt-5 p-4 bg-teal-50 rounded-xl border border-teal-200 shadow-inner';

                            const title = document.createElement('div');
                            title.className = 'text-label-md font-semibold text-teal-800 mb-1 flex items-center gap-1.5';
                            title.innerHTML = '<span class="material-symbols-outlined text-[16px]">cloud</span> SharePoint Site Preview';

                            const urlText = document.createElement('div');
                            urlText.id = 'sp-url-preview';
                            urlText.className = 'text-body-sm text-teal-700 font-mono break-all font-semibold';

                            const hintText = document.createElement('div');
                            hintText.id = 'sp-hint';
                            hintText.className = 'text-[11px] text-teal-600/80 mt-1.5 italic';
                            hintText.textContent = '* Ensure the Azure AD App Registration has Sites.Read.All or Sites.FullControl.All permission on Microsoft Graph.';

                            previewWrapper.appendChild(title);
                            previewWrapper.appendChild(urlText);
                            previewWrapper.appendChild(hintText);
                            container.appendChild(previewWrapper);

                            window.updateSpPreview = () => {
                                const hostnameInput = document.getElementById('dyn_hostname');
                                const sitePathInput = document.getElementById('dyn_site_path');

                                if (!hostnameInput || !sitePathInput) return;

                                let hostname = hostnameInput.value.trim();
                                let sitePath = sitePathInput.value.trim().replace(/^\//, '');

                                let siteUrl = hostname ? `https://${hostname}/${sitePath}` : 'https://...';
                                document.getElementById('sp-url-preview').textContent = siteUrl;
                            };

                            setTimeout(() => {
                                ['dyn_hostname', 'dyn_site_path'].forEach(id => {
                                    const el = document.getElementById(id);
                                    if (el) el.addEventListener('input', window.updateSpPreview);
                                });
                                window.updateSpPreview();
                            }, 50);
                        }
                    }

                    function closeConnectionModal() {
                        closeModal('connection-modal');
                    }

                    function openConnectionModal(editKey = null) {
                        const form = document.getElementById('connection-form');
                        form.reset();
                        document.getElementById('conn-form-mode').value = 'create';
                        document.getElementById('conn-form-original-key').value = '';

                        if (editKey && connectionData[editKey]) {
                            const c = connectionData[editKey];
                            document.getElementById('conn-form-mode').value = 'edit';
                            document.getElementById('conn-form-original-key').value = editKey;

                            document.getElementById('conn-key').value = editKey;
                            document.getElementById('conn-name').value = c.name;
                            document.getElementById('conn-type').value = c.type;
                            document.getElementById('conn-type').dispatchEvent(new Event('change'));

                            toggleConnectionFields();

                            const schema = providerSchemas[c.type] || [];
                            schema.forEach(field => {
                                const el = document.getElementById(`dyn_${field.field}`);
                                if (el && c.credentials && c.credentials[field.field] !== undefined) {
                                    if (field.type === 'boolean') {
                                        el.checked = c.credentials[field.field];
                                    } else {
                                        el.value = c.credentials[field.field];
                                    }
                                }
                            });

                            document.getElementById('conn-modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">edit</span> Edit Connection';
                            document.getElementById('conn-submit-text').textContent = 'Save Changes';
                        } else {
                            toggleConnectionFields();
                            document.getElementById('conn-modal-title').innerHTML = '<span class="material-symbols-outlined text-brand-500 text-[20px]">add_circle</span> Add Connection';
                            document.getElementById('conn-submit-text').textContent = 'Create Connection';
                        }
                        openModal('connection-modal');
                    }

                    function getCredentialsFromSchema(type) {
                        const schema = providerSchemas[type] || [];
                        const credentials = {};
                        schema.forEach(field => {
                            const el = document.getElementById(`dyn_${field.field}`);
                            if (el) {
                                credentials[field.field] = field.type === 'boolean' ? el.checked : el.value;
                            }
                        });
                        return credentials;
                    }

                    async function testConnectionFromForm() {
                        if (!document.getElementById('connection-form').reportValidity()) return;
                        const type = document.getElementById('conn-type').value;
                        const credentials = getCredentialsFromSchema(type);

                        const btn = document.getElementById('conn-test-btn');
                        btn.disabled = true;
                        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Testing...';

                        try {
                            const res = await fetch('/api/connections/test', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                body: JSON.stringify({ type, credentials })
                            });
                            const data = await res.json();
                            if (res.ok && data.success) { showToast(data.message, 'success'); }
                            else { showToast(data.message || 'Connection failed', 'error'); }
                        } catch (e) { showToast('Error: ' + e.message, 'error'); }
                        finally {
                            btn.disabled = false;
                            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">science</span> Test';
                        }
                    }

                    async function runDiagnosticsFromForm() {
                        if (!document.getElementById('connection-form').reportValidity()) return;
                        const type = document.getElementById('conn-type').value;
                        const credentials = getCredentialsFromSchema(type);

                        const btn = document.getElementById('conn-diag-btn');
                        const container = document.getElementById('conn-diagnostics-container');
                        const content = document.getElementById('conn-diagnostics-content');

                        btn.disabled = true;
                        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Running...';
                        container.classList.remove('hidden');
                        content.innerHTML = '<div class="text-surface-500 animate-pulse">Running full production diagnostics...</div>';

                        try {
                            const res = await fetch('/api/connections/diagnostics', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                body: JSON.stringify({ type, credentials })
                            });
                            const data = await res.json();

                            let html = '<div class="grid grid-cols-2 gap-4">';

                            if (data.checks && Object.keys(data.checks).length > 0) {
                                html += '<div class="space-y-1.5"><h5 class="font-medium text-surface-700 text-xs uppercase tracking-wider mb-2">Checks</h5>';
                                for (const [key, passed] of Object.entries(data.checks)) {
                                    const icon = passed ? '<span class="text-emerald-500 material-symbols-outlined text-[16px] align-middle">check_circle</span>' : '<span class="text-red-500 material-symbols-outlined text-[16px] align-middle">cancel</span>';
                                    html += `<div class="flex items-center justify-between py-1 border-b border-surface-100 last:border-0"><span class="text-surface-600">${key}</span> ${icon}</div>`;
                                }
                                html += '</div>';
                            }

                            if (data.summary && Object.keys(data.summary).length > 0) {
                                html += '<div class="space-y-1.5"><h5 class="font-medium text-surface-700 text-xs uppercase tracking-wider mb-2">Summary</h5>';
                                for (const [key, val] of Object.entries(data.summary)) {
                                    html += `<div class="flex items-center justify-between py-1 border-b border-surface-100 last:border-0"><span class="text-surface-500">${key}</span> <span class="font-medium text-surface-800 text-right truncate pl-2" title="${val}">${val}</span></div>`;
                                }
                                if (data.metrics && data.metrics.latency_ms) {
                                    html += `<div class="flex items-center justify-between py-1"><span class="text-surface-500">Latency</span> <span class="font-medium text-surface-800">${data.metrics.latency_ms} ms</span></div>`;
                                }
                                html += '</div>';
                            }

                            html += '</div>';

                            if (data.error) {
                                html += `<div class="mt-4 p-3 rounded-lg bg-red-50 border border-red-200">
                    <div class="font-semibold text-red-700 flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span> ${data.error.title} (${data.error.code})</div>
                    <div class="text-red-600 mt-1">${data.error.message}</div>
                    ${data.error.sql_state ? `<div class="text-xs font-mono text-red-500 mt-2 p-1.5 bg-red-100/50 rounded">SQLSTATE: ${data.error.sql_state}</div>` : ''}
                    <div class="text-xs font-mono text-red-400 mt-1 truncate" title="${data.error.technical_message}">${data.error.technical_message}</div>
                </div>`;
                            }

                            content.innerHTML = html;

                            if (res.ok && data.success) { showToast('Diagnostics passed.', 'success'); }
                            else { showToast('Diagnostics failed or produced warnings.', 'error'); }
                        } catch (e) {
                            content.innerHTML = `<div class="text-red-500 p-3 bg-red-50 rounded-lg border border-red-200">Error: ${e.message}</div>`;
                        } finally {
                            btn.disabled = false;
                            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">troubleshoot</span> Diagnostics';
                        }
                    }

                    async function submitConnectionForm(e) {
                        e.preventDefault();
                        if (!document.getElementById('connection-form').checkValidity()) {
                            document.getElementById('connection-form').reportValidity();
                            return;
                        }

                        const mode = document.getElementById('conn-form-mode').value;
                        const originalKey = document.getElementById('conn-form-original-key').value;
                        const type = document.getElementById('conn-type').value;
                        const credentials = getCredentialsFromSchema(type);

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
                            if (res.ok && data.success) {
                                // Clear cached source objects for this connection so Add Entity fetches fresh data
                                delete cachedSourceObjects[originalKey];
                                delete cachedSourceObjects[payload.key];
                                try {
                                    localStorage.setItem('cached_source_objects', JSON.stringify(cachedSourceObjects));
                                } catch (e) { }

                                showToast(data.message, 'success');
                                closeConnectionModal();
                                setTimeout(() => location.reload(), 800);
                            }
                            else { showToast(data.message || 'Operation failed', 'error'); }
                        } catch (e) { showToast('Error: ' + e.message, 'error'); }
                        finally { btn.disabled = false; }
                    }

                    function updateEntityModalConnectionDropdowns() {
                        const sourceSelect = document.getElementById('form-source-conn');
                        const destSelect = document.getElementById('form-dest-conn');
                        if (!sourceSelect || !destSelect) return;

                        const currentSource = sourceSelect.value;
                        const currentDest = destSelect.value;

                        sourceSelect.innerHTML = '<option value="" disabled selected>Select Source Connection</option>';
                        destSelect.innerHTML = '<option value="" disabled selected>Select Destination Connection</option>';

                        for (const [k, c] of Object.entries(connectionData)) {
                            if (c.is_connected) {
                                const optionText = `${c.name} (${c.type.toUpperCase()})`;

                                const optSource = document.createElement('option');
                                optSource.value = k;
                                optSource.textContent = optionText;
                                sourceSelect.appendChild(optSource);

                                if (providerRegistry[c.type] && providerRegistry[c.type].kind === 'database') {
                                    const optDest = document.createElement('option');
                                    optDest.value = k;
                                    optDest.textContent = optionText;
                                    destSelect.appendChild(optDest);
                                }
                            }
                        }

                        if (currentSource && sourceSelect.querySelector(`option[value="${currentSource}"]`)) {
                            sourceSelect.value = currentSource;
                        } else {
                            sourceSelect.value = "";
                        }

                        if (currentDest && destSelect.querySelector(`option[value="${currentDest}"]`)) {
                            destSelect.value = currentDest;
                        } else {
                            destSelect.value = "";
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
                                statusEl.innerHTML = '<span class="status-dot status-dot-online"></span> ' + (data.ping_ms ? data.ping_ms + 'ms' : 'Connected');
                                statusEl.className = "text-label-sm text-emerald-600 font-medium flex items-center gap-1.5";

                                btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link_off</span> Disconnect';
                                btn.className = "ui-btn ui-btn-sm bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border border-emerald-200";
                                btn.setAttribute('onclick', `disconnectConnection('${key}')`);

                                connectionData[key].is_connected = true;
                                updateEntityModalConnectionDropdowns();

                                if (!silent) showToast(data.message, 'success');
                            } else {
                                throw new Error(data.message || 'Connection failed');
                            }
                        } catch (e) {
                            statusEl.innerHTML = '<span class="status-dot status-dot-error"></span> Failed';
                            statusEl.className = "text-label-sm text-red-600 font-medium flex items-center gap-1.5";

                            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link</span> Connect';
                            btn.className = "ui-btn ui-btn-sm bg-red-50 text-red-600 hover:bg-red-100 border border-red-200";
                            btn.setAttribute('onclick', `testConnection('${key}')`);
                            if (!silent) showToast('Error: ' + e.message, 'error');
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
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                }
                            });
                            const data = await res.json();

                            if (data.success) {
                                const statusEl = document.getElementById('status-' + key);
                                const btn = document.getElementById('btn-conn-' + key);

                                statusEl.innerHTML = '<span class="status-dot status-dot-offline"></span> Disconnected';
                                statusEl.className = "text-label-sm text-surface-400 font-medium flex items-center gap-1.5";

                                btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link</span> Connect';
                                btn.className = "ui-btn ui-btn-sm bg-surface-50 text-surface-600 hover:bg-surface-100 border border-surface-200";
                                btn.setAttribute('onclick', `testConnection('${key}', true)`);
                                btn.disabled = false;

                                connectionData[key].is_connected = false;
                                updateEntityModalConnectionDropdowns();

                                showToast('Disconnected successfully', 'success');
                            } else {
                                throw new Error(data.message || 'Failed to disconnect');
                            }
                        } catch (err) {
                            console.error(err);
                            const btn = document.getElementById('btn-conn-' + key);
                            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">link_off</span> Disconnect';
                            btn.disabled = false;
                            showToast(err.message, 'error');
                        }
                    }

                    // ── Delete & Batch Delete ──────────────────────────────────────────────
                    let deleteTargetBatchKeys = [];

                    function deleteEntity(key) {
                        document.getElementById('delete-type').value = 'entity';
                        deleteTargetKey = key;
                        deleteTargetBatchKeys = [];
                        document.getElementById('delete-name').textContent = `entity "${key}"`;
                        openModal('delete-modal');
                    }

                    function deleteConnection(key) {
                        document.getElementById('delete-type').value = 'connection';
                        deleteTargetKey = key;
                        deleteTargetBatchKeys = [];
                        document.getElementById('delete-name').textContent = `connection "${connectionData[key] ? connectionData[key].name : key}"`;
                        openModal('delete-modal');
                    }

                    // ── Batch Delete Connections ───────────────────────────────────────────
                    function toggleAllConnections(source) {
                        const isChecked = source.checked;
                        const checkboxes = document.querySelectorAll('.conn-checkbox');
                        checkboxes.forEach(cb => {
                            const tr = cb.closest('tr');
                            if (!tr || tr.style.display !== 'none') {
                                cb.checked = isChecked;
                            }
                        });
                        updateBatchDeleteConnBtn();
                    }

                    function updateBatchDeleteConnBtn() {
                        const checked = document.querySelectorAll('.conn-checkbox:checked');
                        const selectedCount = checked.length;
                        const btn = document.getElementById('btn-batch-delete-conn');
                        const countSpan = document.getElementById('batch-delete-conn-count');

                        if (countSpan) countSpan.textContent = selectedCount;

                        if (btn) {
                            if (selectedCount > 0) {
                                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                            } else {
                                btn.classList.add('opacity-50', 'cursor-not-allowed');
                            }
                        }

                        const selectAll = document.getElementById('selectAllConnections');
                        if (selectAll) {
                            const visibleCheckboxes = Array.from(document.querySelectorAll('.conn-checkbox')).filter(cb => {
                                const tr = cb.closest('tr');
                                return !tr || tr.style.display !== 'none';
                            });
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

                    function batchDeleteConnections() {
                        const selected = Array.from(document.querySelectorAll('.conn-checkbox:checked')).map(cb => cb.value);
                        if (selected.length === 0) {
                            showToast('Please select at least one connection to delete.', 'error');
                            return;
                        }

                        document.getElementById('delete-type').value = 'batch_connection';
                        deleteTargetKey = null;
                        deleteTargetBatchKeys = selected;
                        document.getElementById('delete-name').textContent = `${selected.length} selected connection(s) (${selected.join(', ')})`;
                        openModal('delete-modal');
                    }

                    // ── Batch Delete Entities ──────────────────────────────────────────────
                    function toggleAllEntities(source) {
                        const isChecked = source.checked;
                        const checkboxes = document.querySelectorAll('.entity-checkbox');
                        checkboxes.forEach(cb => {
                            const tr = cb.closest('tr');
                            if (!tr || tr.style.display !== 'none') {
                                cb.checked = isChecked;
                            }
                        });
                        updateBatchDeleteBtn();
                    }

                    function updateBatchDeleteBtn() {
                        const checked = document.querySelectorAll('.entity-checkbox:checked');
                        const selectedCount = checked.length;
                        const btn = document.getElementById('btn-batch-delete');
                        const countSpan = document.getElementById('batch-delete-count');

                        if (countSpan) countSpan.textContent = selectedCount;

                        if (btn) {
                            if (selectedCount > 0) {
                                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                            } else {
                                btn.classList.add('opacity-50', 'cursor-not-allowed');
                            }
                        }

                        const selectAll = document.getElementById('selectAllEntities');
                        if (selectAll) {
                            const visibleCheckboxes = Array.from(document.querySelectorAll('.entity-checkbox')).filter(cb => {
                                const tr = cb.closest('tr');
                                return !tr || tr.style.display !== 'none';
                            });
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

                    function batchDeleteEntities() {
                        const selected = Array.from(document.querySelectorAll('.entity-checkbox:checked')).map(cb => cb.value);
                        if (selected.length === 0) {
                            showToast('Please select at least one entity to delete.', 'error');
                            return;
                        }

                        document.getElementById('delete-type').value = 'batch_entity';
                        deleteTargetKey = null;
                        deleteTargetBatchKeys = selected;
                        document.getElementById('delete-name').textContent = `${selected.length} selected entity(s) (${selected.join(', ')})`;
                        openModal('delete-modal');
                    }

                    function closeDeleteModal() {
                        closeModal('delete-modal');
                        deleteTargetKey = null;
                        deleteTargetBatchKeys = [];
                    }

                    async function confirmDelete() {
                        const type = document.getElementById('delete-type').value;
                        const btn = document.getElementById('delete-confirm-btn');
                        btn.disabled = true;
                        btn.innerHTML = '<span class="material-symbols-outlined text-[14px] spin">sync</span> Deleting…';

                        try {
                            let res, data;
                            if (type === 'batch_connection') {
                                res = await fetch('/api/connections/batch-delete', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                    body: JSON.stringify({ keys: deleteTargetBatchKeys })
                                });
                                data = await res.json();
                            } else if (type === 'batch_entity') {
                                res = await fetch('/api/entities/batch-delete', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                    body: JSON.stringify({ keys: deleteTargetBatchKeys })
                                });
                                data = await res.json();
                            } else if (type === 'entity') {
                                res = await fetch(`/api/entities/${deleteTargetKey}`, {
                                    method: 'DELETE',
                                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                                });
                                data = await res.json();
                            } else if (type === 'connection') {
                                res = await fetch(`/api/connections/${deleteTargetKey}`, {
                                    method: 'DELETE',
                                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                                });
                                data = await res.json();
                            }

                            if (res && res.ok && data && data.success) {
                                showToast(data.message || 'Deleted successfully', 'success');
                                closeDeleteModal();
                                setTimeout(() => location.reload(), 600);
                            } else {
                                showToast(data?.message || 'Delete operation failed', 'error');
                            }
                        } catch (e) {
                            showToast('Error: ' + e.message, 'error');
                        } finally {
                            btn.disabled = false;
                            btn.innerHTML = '<span class="material-symbols-outlined text-[14px]">delete</span> Delete';
                        }
                    }


                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape') {
                            closeEntityModal();
                            closeConnectionModal();
                            closeDeleteModal();
                            closeFieldsModal();
                        }
                    });
</script>