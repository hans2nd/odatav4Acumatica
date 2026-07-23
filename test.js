        try {
            const [permRes, roleRes] = await Promise.all([
                fetch('/api/permissions', { headers: { 'Accept': 'application/json' } }),
                fetch(`/api/roles/${roleId}`, { headers: { 'Accept': 'application/json' } }),
            ]);
            const permData = await permRes.json();
            const roleData = await roleRes.json();
            const assignedIds = roleData.permissions.map(p => p.id);
            
            const actionsList = ['view', 'create', 'update', 'delete', 'sync', 'export', 'import', 'manage', 'execute'];
            
            let html = `
            <div class="overflow-x-auto border border-surface-200/80 rounded-xl bg-white shadow-xs">
                <table class="w-full text-left border-collapse min-w-max">
                    <thead>
                        <tr class="bg-surface-50 border-b border-surface-200/80">
                            <th class="px-4 py-3 text-xs font-semibold text-surface-700 w-[200px] sm:w-[250px] md:w-[300px] sticky left-0 bg-surface-50 z-10 border-r border-surface-200/80 shadow-[1px_0_0_0_rgba(0,0,0,0.05)]">Menu / Module</th>
                            <th class="px-3 py-3 text-center border-r border-surface-200/80 bg-surface-50/50">
                                <div class="flex flex-col items-center gap-1.5">
                                    <span class="text-[10px] font-semibold text-surface-500 uppercase tracking-wide">All</span>
                                    <input type="checkbox" class="rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4 cursor-pointer" onchange="toggleMatrixAll(this, 'row-all')">
                                </div>
                            </th>
            `;
            
            actionsList.forEach(action => {
                html += `
                            <th class="px-2 py-3 text-center">
                                <div class="flex flex-col items-center gap-1.5">
                                    <span class="text-[10px] font-semibold text-surface-500 uppercase tracking-wide">${action}</span>
                                    <input type="checkbox" class="rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4 cursor-pointer" onchange="toggleMatrixAll(this, 'col-${action}')">
                                </div>
                            </th>
                `;
            });
            
            html += `
                            <th class="px-4 py-3 text-xs font-semibold text-surface-700">Other</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
            `;
            
            let rows = [];

            function flattenMenu(menu, depth = 0) {
                rows.push({
                    type: 'menu',
                    title: menu.title,
                    icon: menu.icon,
                    depth: depth,
                    permissions: menu.permissions || []
                });
                if (menu.children && menu.children.length > 0) {
                    menu.children.forEach(child => flattenMenu(child, depth + 1));
                }
            }

            permData.menus.forEach(menu => flattenMenu(menu));

            if (permData.otherPermissions && permData.otherPermissions.length > 0) {
                rows.push({
                    type: 'header',
                    title: 'Lainnya (Sistem)',
                    icon: 'database',
                    depth: 0,
                    permissions: []
                });

                const groupedOthers = {};
                permData.otherPermissions.forEach(p => {
                    const parts = p.permission_key.split('.');
                    let group = parts[0];
                    if (parts.length === 1) group = 'General';
                    else group = group.replace(/_/g, ' ').replace(/([A-Z])/g, ' $1').trim();
                    group = group.charAt(0).toUpperCase() + group.slice(1);
                    
                    if (!groupedOthers[group]) groupedOthers[group] = [];
                    groupedOthers[group].push(p);
                });

                for (const [groupName, perms] of Object.entries(groupedOthers)) {
                    rows.push({
                        type: 'menu',
                        title: groupName,
                        icon: 'subdirectory_arrow_right',
                        depth: 1,
                        permissions: perms
                    });
                }
            }
            
            rows.forEach((row, i) => {
                const indent = row.depth * 20;
                const isHeaderOnly = row.type === 'header' || (row.type === 'menu' && row.permissions.length === 0);
                const rowId = `r${i}`;
            
                let rowHtml = `<tr class="hover:bg-surface-50/50 transition-colors group">`;
                
                rowHtml += `
                    <td class="px-4 py-2.5 sticky left-0 bg-white group-hover:bg-surface-50/50 z-10 border-r border-surface-200/80 shadow-[1px_0_0_0_rgba(0,0,0,0.05)]">
                        <div class="flex items-center gap-2.5" style="padding-left: ${indent}px">
                            <span class="material-symbols-outlined text-[18px] text-brand-600/70 shrink-0">${row.depth === 0 ? (row.icon || 'folder') : 'subdirectory_arrow_right'}</span>
                            <span class="${row.depth === 0 ? 'font-semibold text-surface-800' : 'font-medium text-surface-600'} text-xs truncate" title="${row.title}">${row.title}</span>
                        </div>
                    </td>
                `;
            
                if (isHeaderOnly) {
                    rowHtml += `<td colspan="${actionsList.length + 2}" class="bg-surface-50/30"></td>`;
                } else {
                    const permsByAction = {};
                    const otherPerms = [];
                    
                    row.permissions.forEach(p => {
                        let actionCode = null;
                        if (p.action && p.action.code) {
                            actionCode = p.action.code;
                        } else {
                            const parts = p.permission_key.split('.');
                            if (parts.length > 1) {
                                actionCode = parts[1].toLowerCase();
                            }
                        }
            
                        if (actionCode && actionsList.includes(actionCode)) {
                            permsByAction[actionCode] = p;
                        } else {
                            otherPerms.push(p);
                        }
                    });
            
                    rowHtml += `
                        <td class="px-3 py-2.5 text-center border-r border-surface-200/80 bg-surface-50/50">
                            <input type="checkbox" class="rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4 cursor-pointer" onchange="toggleMatrixAll(this, 'row-all-${rowId}')">
                        </td>
                    `;
            
                    actionsList.forEach(action => {
                        const p = permsByAction[action];
                        rowHtml += `<td class="px-2 py-2.5 text-center">`;
                        if (p) {
                            const checked = assignedIds.includes(p.id) ? 'checked' : '';
                            rowHtml += `<input type="checkbox" name="permissions[]" value="${p.id}" class="perm-checkbox row-all row-all-${rowId} col-${action} rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-4 h-4 cursor-pointer" ${checked}>`;
                        } else {
                            rowHtml += `<span class="text-surface-200 text-[10px] select-none">-</span>`;
                        }
                        rowHtml += `</td>`;
                    });
            
                    rowHtml += `<td class="px-4 py-2.5">`;
                    if (otherPerms.length > 0) {
                        rowHtml += `<div class="flex flex-wrap gap-1.5">`;
                        otherPerms.forEach(p => {
                            const checked = assignedIds.includes(p.id) ? 'checked' : '';
                            const name = p.action ? p.action.name : p.permission_key.split('.').pop();
                            rowHtml += `
                                <label class="flex items-center gap-1.5 px-2 py-1 rounded bg-surface-100 border border-surface-200 cursor-pointer hover:bg-surface-200 transition-colors">
                                    <input type="checkbox" name="permissions[]" value="${p.id}" class="perm-checkbox row-all row-all-${rowId} rounded border-surface-300 text-brand-600 focus:ring-brand-500/20 w-3 h-3" ${checked}>
                                    <span class="text-[10px] text-surface-700 font-medium capitalize whitespace-nowrap">${name}</span>
                                </label>
                            `;
                        });
                        rowHtml += `</div>`;
                    }
                    rowHtml += `</td>`;
                }
            
                rowHtml += `</tr>`;
                html += rowHtml;
            });
            
            html += `
                    </tbody>
                </table>
            </div>
            `;
            
            document.getElementById('perm-content').innerHTML = html || '<p class="text-surface-500 text-center py-4">Tidak ada hak akses yang tersedia.</p>';
        } catch (err) {
            document.getElementById('perm-content').innerHTML = `<p class="text-red-600">${err.message}</p>`;
        }
