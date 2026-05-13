const app = {
    currentProjectId: null,
    currentFilePreview: null,

    init: function() {
        document.getElementById('auth-form').addEventListener('submit', (e) => { e.preventDefault(); this.handleAuth(); });
        this.checkSession();
        
        const searchInputTable = document.getElementById('search-projects-table');
        if(searchInputTable) searchInputTable.addEventListener('input', () => this.filterProjectsTable());
    
        const filterSelect = document.getElementById('filter-projects');
        if(filterSelect) filterSelect.addEventListener('change', () => this.filterProjectsTable());

        this.populateForemanDropdown();
    },

    request: async function(action, data = {}, isFormData = false) {
        let bodyData;
        if (isFormData) {
            bodyData = data; bodyData.append('action', action);
        } else {
            bodyData = new URLSearchParams(); bodyData.append('action', action);
            for (let key in data) bodyData.append(key, data[key]);
        }
        const res = await fetch('backend/api.php', { method: 'POST', body: bodyData });
        return await res.json();
    },

    checkSession: async function() { const res = await this.request('check_session'); if (res.logged_in) { document.getElementById('auth-screen').style.display = 'none'; document.getElementById('app-layout').style.display = 'flex'; this.showModule('dashboard'); } },
    handleAuth: async function() {
        const res = await this.request('login', { email: document.getElementById('auth-email').value, password: document.getElementById('auth-pass').value });
        if (res.status === 'success') { document.getElementById('auth-screen').style.display = 'none'; document.getElementById('app-layout').style.display = 'flex'; this.showModule('dashboard'); } else { this.showToast(res.message, 'error'); }
    },
    logout: async function() { await this.request('logout'); location.reload(); },

    showToast: function(message, type = 'success') {
        const existingToast = document.querySelector('.toast-notification');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        toast.className = `toast-notification ${type === 'error' ? 'toast-error' : ''}`;
        const icon = type === 'error' ? '<i class="fa-solid fa-circle-exclamation" style="color:var(--danger); font-size:1.2rem;"></i>' : '<i class="fa-solid fa-circle-check" style="color:var(--success); font-size:1.2rem;"></i>';
        toast.innerHTML = `${icon} <span>${message}</span>`;
        document.body.appendChild(toast);

        setTimeout(() => { toast.style.animation = 'slideInRight 0.3s reverse forwards'; setTimeout(() => toast.remove(), 300); }, 3000);
    },

    openModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            if (modalId === 'modal-add-stock') {
                this.populateInventorySupplierDropdown();
                this.populateCategoryDropdown(); 
                const newCatInput = document.getElementById('stock-category-new');
                if(newCatInput) { newCatInput.style.display = 'none'; newCatInput.value = ''; }
            }
        }
    },
    closeModal: function(modalId) { const modal = document.getElementById(modalId); if (modal) modal.style.display = 'none'; },
    closeModalOnBackdrop: function(event, modalId) { if (event.target.id === modalId) this.closeModal(modalId); },

    // ==========================================
    // MODULE ROUTING
    // ==========================================
    showModule: function(id) {
        document.querySelectorAll('.module').forEach(m => m.classList.remove('active'));
        document.getElementById('mod-' + id).classList.add('active');
        
        document.querySelectorAll('.nav-links li').forEach(li => li.classList.remove('active'));
        let activeLi = document.querySelector(`.nav-links li[data-module='${id}']`);
        let cleanTitle = activeLi ? activeLi.textContent.trim() : 'Dashboard';
        
        document.getElementById('dynamic-breadcrumbs').innerHTML = `<span class="breadcrumb-link" onclick="app.showModule('dashboard')"><i class="fa-solid fa-house"></i> Home</span><i class="fa-solid fa-chevron-right separator"></i><b id="breadcrumb-current" class="active-crumb">${cleanTitle}</b>`;
        if(activeLi) activeLi.classList.add('active');

        if(id === 'dashboard') this.loadDashboard();
        if(id === 'projects') { 
            this.closeProjectDetails(); document.getElementById('breadcrumb-current').innerText = "Projects (Sites)";
            if(document.getElementById('search-projects-table')) document.getElementById('search-projects-table').value = '';
            if(document.getElementById('filter-projects')) document.getElementById('filter-projects').value = 'all';
            this.loadProjects(); 
        }
        if(id === 'materials') this.loadSuppliersDashboard();
        if(id === 'users') { this.loadProjectOptionsForManpower(); this.loadManpowerFolders(); }
        if(id === 'award_costs') this.loadAwardCosts();
        if(id === 'payroll') { this.backToActivePayroll(); this.loadPayroll(); this.loadManpowerNames(); }
        if(id === 'global_ntp') this.loadGlobalNTP();
    },

    loadDashboard: async function() {
        const stats = await this.request('get_stats');
        document.getElementById('stat-projects').innerText = stats.projects || 0;
        document.getElementById('stat-users').innerText = stats.users || 0;
        this.loadUpcomingDeadlines(); 
    },

    loadUpcomingDeadlines: async function() {
        const tbody = document.getElementById('deadlines-content'); if(!tbody) return;
        const projects = await this.request('get_projects');
        const today = new Date(); today.setHours(0,0,0,0);
        let deadlines = [];
        projects.forEach(p => {
            let sDate = new Date(p.start_date); let dOffset = Math.floor((sDate - today) / (1000 * 60 * 60 * 24));
            deadlines.push({ type: 'project', icon: 'fa-city', site: p.location, action: p.name, daysOffset: dOffset, actualDate: sDate });
        });

        deadlines = deadlines.filter(t => t.daysOffset <= 30).sort((a, b) => a.daysOffset - b.daysOffset);
        tbody.innerHTML = '';
        if (deadlines.length === 0) { tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 20px; color: var(--text-muted);">No upcoming deadlines.</td></tr>`; return; }

        deadlines.forEach(task => {
            let statusBadge = ''; let countdownClass = ''; let countdownStr = '';
            let dateText = task.actualDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

            if (task.daysOffset < 0) { statusBadge = `<span class="badge badge-urgent"><i class="fa-solid fa-triangle-exclamation"></i> OVERDUE</span>`; countdownClass = 'countdown-urgent'; countdownStr = `${Math.abs(task.daysOffset)} Day(s) Late`; } 
            else if (task.daysOffset <= 7) { statusBadge = `<span class="badge badge-urgent"><i class="fa-solid fa-bell"></i> URGENT</span>`; countdownClass = 'countdown-urgent'; countdownStr = `In ${task.daysOffset} Day(s)`; } 
            else { statusBadge = `<span class="badge badge-upcoming"><i class="fa-solid fa-clock"></i> UPCOMING</span>`; countdownClass = 'countdown-upcoming'; countdownStr = `In ${task.daysOffset} Day(s)`; }

            tbody.innerHTML += `<tr><td style="text-align: center;"><div class="type-icon-box" style="margin: 0 auto;"><i class="fa-solid ${task.icon}"></i></div></td><td><b style="color:var(--text-dark);">${task.site}</b></td><td style="font-weight: 600; color: var(--text-main);">${task.action}</td><td><span style="font-weight: 600; color: var(--text-dark);">${dateText}</span><span class="countdown-text ${countdownClass}">${countdownStr}</span></td><td>${statusBadge}</td></tr>`;
        });
    },

    handleGlobalSearch: async function(query) {
        const searchContainer = document.getElementById('global-search-results'); const content = document.getElementById('search-results-content'); const clearBtn = document.getElementById('clear-search-btn'); const deadlinesContainer = document.getElementById('upcoming-deadlines-container');
        if (!query || query.trim() === '') { searchContainer.style.display = 'none'; clearBtn.style.display = 'none'; if (deadlinesContainer) deadlinesContainer.style.display = 'block'; return; }
        if (deadlinesContainer) deadlinesContainer.style.display = 'none'; clearBtn.style.display = 'block'; searchContainer.style.display = 'block';
        document.getElementById('search-query-display').innerText = query;
        
        let resultsHTML = ''; const projects = window.allProjectsData || await this.request('get_projects'); const q = query.toLowerCase();
        const matched = projects.filter(p => p.name.toLowerCase().includes(q) || p.location.toLowerCase().includes(q));
        
        if(matched.length === 0) { resultsHTML = `<p style="padding: 10px; color: var(--text-muted);">No results found.</p>`; } 
        else { matched.forEach(item => { resultsHTML += `<div class="search-result-item" onclick="app.showModule('projects'); app.openProjectDetails(${item.id}, '${item.name}', '${item.location}')"><div class="search-icon-box"><i class="fa-solid fa-city"></i></div><div class="search-content"><h4>${item.name}</h4><p>${item.location}</p><span class="search-category-badge">Projects</span></div></div>`; }); }
        content.innerHTML = resultsHTML;
    },
    clearGlobalSearch: function() { document.getElementById('global-search-input').value = ''; this.handleGlobalSearch(''); },

    // ==========================================
    // PROJECTS & WORKSPACE (DB DRIVEN)
    // ==========================================
    populateForemanDropdown: async function() {
        const users = await this.request('get_active_manpower');
        const select = document.getElementById('proj-foreman'); if(!select) return;
        select.innerHTML = '<option value="">Select Site Foreman / In-Charge</option>';
        if(users && Array.isArray(users)) {
            const foremen = users.filter(m => m.position && (m.position.toLowerCase().includes('foreman') || m.position.toLowerCase().includes('lead') || m.position.toLowerCase().includes('engineer') || m.position.toLowerCase().includes('in-charge')));
            foremen.forEach(f => { select.innerHTML += `<option value="${f.name}">${f.name} (${f.position})</option>`; });
        }
    },

    handleFileSelect: function(input) {
        const display = document.getElementById('file-name-display'); const label = document.getElementById('file-dropzone-label');
        if (input.files && input.files.length > 0) { 
            this.currentFilePreview = URL.createObjectURL(input.files[0]);
            display.innerHTML = `📄 ${input.files[0].name} <span style="margin-left:10px; color:var(--primary-hover); text-decoration:underline;" onclick="event.preventDefault(); app.viewAttachedFile('${this.currentFilePreview}')">View</span>`; 
            label.style.borderColor = "var(--success)"; label.style.color = "var(--success)";
        } else { 
            this.currentFilePreview = null; display.innerText = "Attach Initial NTP Document (Optional)"; label.style.borderColor = "#D1D5DB"; label.style.color = "var(--text-muted)"; 
        }
    },

    viewAttachedFile: function(url) {
        if(url) { document.getElementById('resume-img').src = url; document.getElementById('resume-modal').style.display = 'block'; }
    },

    submitProjectForm: async function() {
        const name = document.getElementById('proj-name').value; const client = document.getElementById('proj-client').value || '-';
        const location = document.getElementById('proj-loc').value; const desc = document.getElementById('proj-desc').value; 
        const foremanRaw = document.getElementById('proj-foreman').value; const start_date = document.getElementById('proj-start').value;
        const fileInput = document.getElementById('proj-ntp-init');
        
        if (!name || !location || !start_date || !foremanRaw) { this.showToast('Project Name, Location, Foreman, and Date are required!', 'error'); return; }
        const foreman = foremanRaw.split(' (')[0];

        const res = await this.request('add_project', { name, client, location, desc, foreman, start_date });
        if(res.status === 'error') return this.showToast(res.message, 'error');

        document.getElementById('proj-name').value = ''; document.getElementById('proj-loc').value = ''; document.getElementById('proj-desc').value = ''; document.getElementById('proj-start').value = ''; document.getElementById('proj-client').value = ''; document.getElementById('proj-foreman').value = ''; fileInput.value = ''; this.handleFileSelect(fileInput);
        this.loadProjects(); this.showToast("Project successfully created.");
    },

    loadProjects: async function() {
        this.closeProjectDetails(); window.allProjectsData = await this.request('get_projects'); this.filterProjectsTable(); this.populateForemanDropdown();
    },

    filterProjectsTable: function() {
        const tbody = document.querySelector('#table-projects tbody'); if(!tbody || !window.allProjectsData) return; 
        const search = (document.getElementById('search-projects-table')?.value || '').toLowerCase(); const filter = document.getElementById('filter-projects')?.value || 'all';

        let filtered = window.allProjectsData;
        if (search) filtered = filtered.filter(p => p.name.toLowerCase().includes(search) || p.location.toLowerCase().includes(search) || (p.foreman && p.foreman.toLowerCase().includes(search)));
        if (filter !== 'all') filtered = filtered.filter(p => p.status === filter);
        filtered.sort((a, b) => new Date(b.start_date) - new Date(a.start_date));

        tbody.innerHTML = '';
        if (filtered.length === 0) { tbody.innerHTML = `<tr><td colspan="6" class="empty-state-wrapper"><i class="fa-solid fa-folder-open"></i><p>No projects found.</p></td></tr>`; return; }

        filtered.forEach(p => {
            let statusUI = ''; let actionBtn = '';
            
            if (p.status === 'pending') { 
                statusUI = `<span class="badge pending">Pending (NTP)</span>`;
                actionBtn = `<button class="btn-outline" style="color:var(--warning); border-color:var(--warning); height: 26px; padding: 0 8px; font-size: 0.75rem;" onclick="app.showModule('global_ntp')"><i class="fa-solid fa-file-circle-check"></i> Verify NTP</button>`;
            } else { 
                statusUI = `<select onchange="app.updateProjectStatus(${p.id}, this.value)" class="table-status-select" style="height:24px; padding: 0 4px; width:auto; font-size:0.75rem; background: ${p.status === 'completed' ? '#D1FAE5' : '#FEFCE8'}; color: ${p.status === 'completed' ? 'var(--success)' : '#854D0E'};"><option value="ongoing" ${p.status === 'ongoing' ? 'selected' : ''}>Ongoing</option><option value="completed" ${p.status === 'completed' ? 'selected' : ''}>Completed</option></select>`; 
                actionBtn = '';
            }
            
            let projNameClickable = `<span style="cursor:pointer; color:var(--primary-hover); text-decoration:underline;" onclick="app.openProjectDetails(${p.id}, '${p.name.replace(/'/g, "\\'")}', '${p.location.replace(/'/g, "\\'")}')">${p.name}</span>`;

            tbody.innerHTML += `<tr><td><b style="color:var(--text-dark);">${projNameClickable}</b><br><small style="color:var(--text-muted); font-size:0.75rem;">${p.description || ''}</small></td><td><b style="color:var(--text-main); font-size:0.8rem;"><i class="fa-solid fa-user-helmet"></i> ${p.foreman || '-'}</b></td><td>${p.location}</td><td style="font-weight: 600;">${p.start_date}</td><td>${statusUI}</td><td><div style="display: flex; gap: 4px;">${actionBtn}<button class="btn-danger" style="height: 26px; padding: 0 8px; border-radius: 4px;" onclick="app.deleteProject(${p.id})"><i class="fa-solid fa-trash" style="font-size: 0.8rem;"></i></button></div></td></tr>`;
        });
    },

    updateProjectStatus: async function(id, status) { await this.request('update_project_status', { id, status }); this.loadProjects(); },
    deleteProject: async function(id) { if(confirm("DANGER ZONE! Deleting this project will wipe all tracking data. Are you sure?")) { await this.request('delete_project', { id }); this.loadProjects(); } },

    openProjectDetails: function(id, name, location) {
        this.currentProjectId = id;
        document.getElementById('projects-list-view').style.display = 'none'; document.getElementById('project-details-view').style.display = 'block'; document.getElementById('pd-name').innerText = name; document.getElementById('pd-loc-display').innerText = location ? location : "Location not specified";
        document.getElementById('dynamic-breadcrumbs').innerHTML = `<span class="breadcrumb-link" onclick="app.showModule('dashboard')"><i class="fa-solid fa-house"></i> Home</span><i class="fa-solid fa-chevron-right separator"></i><span class="breadcrumb-link" onclick="app.showModule('projects')">Projects (Sites)</span><i class="fa-solid fa-chevron-right separator"></i><b id="breadcrumb-current" class="active-crumb">Workspace</b>`;
        this.switchProjectTab('progress');
    },

    closeProjectDetails: function() {
        this.currentProjectId = null;
        document.getElementById('projects-list-view').style.display = 'block'; document.getElementById('project-details-view').style.display = 'none';
        document.getElementById('dynamic-breadcrumbs').innerHTML = `<span class="breadcrumb-link" onclick="app.showModule('dashboard')"><i class="fa-solid fa-house"></i> Home</span><i class="fa-solid fa-chevron-right separator"></i><b id="breadcrumb-current" class="active-crumb">Projects (Sites)</b>`;
    },

    switchProjectTab: function(tabId) {
        document.getElementById('tab-progress').classList.remove('active'); document.getElementById('tab-materials').classList.remove('active'); document.getElementById('tab-' + tabId).classList.add('active');
        document.getElementById('ptab-progress').classList.remove('active'); document.getElementById('ptab-materials').classList.remove('active'); document.getElementById('ptab-' + tabId).classList.add('active');
        if(tabId === 'materials') this.renderProjectWorkspaceMaterials(); if(tabId === 'progress') this.renderProjectChecklist();
    },

    // --- CHECKLIST CRUD FROM DB ---
    renderProjectChecklist: async function() {
        const data = await this.request('get_project_data', { project_id: this.currentProjectId });
        const grid = document.getElementById('checklist-grid'); grid.innerHTML = '';
        if(!data.checklist || data.checklist.length === 0) { grid.innerHTML = `<p style="grid-column: 1/-1; text-align:center; padding: 20px; color:var(--text-muted);">No checklist generated.</p>`; return; }

        let grouped = {};
        data.checklist.forEach(item => { const cat = item.category || 'Uncategorized'; if(!grouped[cat]) grouped[cat] = []; if(item.task_name !== '') grouped[cat].push(item); });

        let totalItems = 0; let completedItems = 0;
        Object.keys(grouped).forEach((cat, cIdx) => {
            let itemsHtml = '';
            grouped[cat].forEach((item, iIdx) => {
                totalItems++; let isComp = item.status === 'Completed'; if(isComp) completedItems++;
                const completedClass = isComp ? 'completed' : ''; const checkedAttr = isComp ? 'checked' : '';
                itemsHtml += `<div class="checklist-item ${completedClass}" id="item-row-${item.id}"><div class="checklist-item-left" onclick="app.toggleChecklistItemDB(${item.id}, '${isComp ? 'Not Started' : 'Completed'}')"><input type="checkbox" ${checkedAttr} onclick="event.stopPropagation(); app.toggleChecklistItemDB(${item.id}, '${isComp ? 'Not Started' : 'Completed'}')"><label>${item.task_name}</label></div><div class="checklist-item-actions"><button class="checklist-action-btn edit" onclick="app.editChecklistItemDB(${item.id}, '${item.task_name.replace(/'/g, "\\'")}')"><i class="fa-solid fa-pencil"></i></button><button class="checklist-action-btn delete" onclick="app.deleteChecklistItemDB(${item.id})"><i class="fa-solid fa-trash"></i></button></div></div>`;
            });
            grid.innerHTML += `<div class="checklist-category"><h4>${cat} <button class="checklist-action-btn delete" style="float:right;" onclick="app.deleteCategoryDB('${cat}')"><i class="fa-solid fa-xmark"></i></button></h4><div id="cat-items-${cIdx}">${itemsHtml}</div><div id="add-task-container-${cIdx}" style="display:none; margin-top:8px;"><input type="text" class="inline-input" id="add-task-input-${cIdx}" placeholder="Type task and press Enter..." onkeydown="if(event.key==='Enter') app.saveNewTaskDB('${cat}', this.value, ${cIdx})" onblur="this.parentElement.style.display='none'; document.getElementById('btn-show-add-${cIdx}').style.display='block';"></div><button id="btn-show-add-${cIdx}" class="add-task-btn" onclick="this.style.display='none'; document.getElementById('add-task-container-${cIdx}').style.display='block'; document.getElementById('add-task-input-${cIdx}').focus();"><i class="fa-solid fa-plus"></i> Add Task</button></div>`;
        });
        
        const pct = totalItems === 0 ? 0 : Math.round((completedItems / totalItems) * 100);
        document.getElementById('proj-progress-bar').style.width = pct + '%'; document.getElementById('proj-progress-text').innerText = pct + '%';
    },

    toggleChecklistItemDB: async function(taskId, newStatus) { await this.request('update_checklist_status', { task_id: taskId, status: newStatus }); if(newStatus === 'Completed') this.showToast('Task completed.'); this.renderProjectChecklist(); },
    saveNewTaskDB: async function(category, taskName, cIdx) { if(taskName.trim() !== '') { await this.request('add_checklist_task', { project_id: this.currentProjectId, category: category, task_name: taskName.trim() }); this.showToast('Task added.'); } document.getElementById('add-task-container-'+cIdx).style.display='none'; this.renderProjectChecklist(); },
    editChecklistItemDB: function(taskId, currentName) { const row = document.getElementById(`item-row-${taskId}`); row.innerHTML = `<div style="width:100%;"><input type="text" class="inline-input" value="${currentName}" onblur="app.saveEditedTaskDB(${taskId}, this.value)" onkeydown="if(event.key==='Enter') this.blur()" autofocus></div>`; row.querySelector('input').focus(); },
    saveEditedTaskDB: async function(taskId, newName) { if(newName.trim() !== '') { await this.request('edit_checklist_task', { task_id: taskId, task_name: newName.trim() }); } this.renderProjectChecklist(); },
    deleteChecklistItemDB: async function(taskId) { await this.request('delete_checklist_task', { task_id: taskId }); this.renderProjectChecklist(); },
    showAddCategoryInput: function() { document.getElementById('btn-add-cat').style.display = 'none'; const input = document.getElementById('input-add-cat'); input.style.display = 'block'; input.focus(); },
    saveNewCategoryDB: async function(val) { const input = document.getElementById('input-add-cat'); input.style.display = 'none'; input.value = ''; document.getElementById('btn-add-cat').style.display = 'inline-flex'; if(val.trim() !== '') { await this.request('add_checklist_task', { project_id: this.currentProjectId, category: val.trim(), task_name: '' }); this.showToast('New Phase/Category added.'); this.renderProjectChecklist(); } },
    deleteCategoryDB: async function(category) { if(confirm(`Delete category "${category}" and all its tasks?`)) { await this.request('delete_checklist_category', { project_id: this.currentProjectId, category: category }); this.renderProjectChecklist(); } },

    // --- MATERIAL ISSUANCES FROM DB ---
    renderProjectWorkspaceMaterials: async function() {
        const inventory = await this.request('get_inventory'); const projData = await this.request('get_project_data', { project_id: this.currentProjectId });
        const select = document.getElementById('issue-item'); select.innerHTML = '<option value="">Select Inventory Item</option>';
        inventory.forEach(inv => { select.innerHTML += `<option value="${inv.id}">${inv.name} (Stock: ${inv.stock} ${inv.unit})</option>`; });

        const allProjs = window.allProjectsData || await this.request('get_projects'); const currentP = allProjs.find(p => p.id == this.currentProjectId);
        if(currentP) document.getElementById('issue-receiver').value = currentP.foreman || '';

        const issuances = projData.issuances || []; const tbody = document.getElementById('issuance-history-content'); tbody.innerHTML = ''; let totalItems = 0; let totalCost = 0;

        if(issuances.length === 0) { tbody.innerHTML = `<tr><td colspan="5" class="empty-state-wrapper"><i class="fa-solid fa-clipboard"></i><p>No materials issued to this site yet.</p></td></tr>`;
        } else { issuances.forEach(i => { const rowCost = i.qty * i.unit_cost; totalItems += parseInt(i.qty); totalCost += parseFloat(rowCost); let fmtCost = parseFloat(rowCost).toLocaleString('en-US', {minimumFractionDigits: 2}); tbody.innerHTML += `<tr><td style="color:var(--text-muted); font-weight:600;">${i.issue_date.split(' ')[0]}</td><td><b style="color:var(--text-dark);">${i.item_name}</b></td><td style="font-weight:700;">${i.qty} <span style="color:var(--text-muted); font-weight:500;">${i.unit}</span></td><td style="color:var(--success); font-weight:700;">₱${fmtCost}</td><td>${i.receiver}</td></tr>`; }); }
        document.getElementById('proj-summary-qty').innerText = `${totalItems} Total Qty`; document.getElementById('proj-summary-cost').innerText = `₱${parseFloat(totalCost).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
    },

    issueMaterial: async function() {
        const itemId = document.getElementById('issue-item').value; const qty = parseInt(document.getElementById('issue-qty').value); const receiver = document.getElementById('issue-receiver').value;
        if (!itemId || !qty || qty <= 0 || !receiver) { this.showToast('Item, Valid Quantity, and Receiver are required!', 'error'); return; }
        const res = await this.request('issue_material', { project_id: this.currentProjectId, item_id: itemId, qty: qty, receiver: receiver });
        if(res.status === 'error') { this.showToast(res.message, 'error'); return; }
        document.getElementById('issue-item').value = ''; document.getElementById('issue-qty').value = '';
        this.renderProjectWorkspaceMaterials(); this.showToast(`Material successfully issued to site.`);
    },

    // ==========================================
    // MODULE: MATERIAL SUPPLIERS & INVENTORY (DB)
    // ==========================================
    switchMatTab: function(tabId) {
        document.getElementById('tab-mat-suppliers').classList.remove('active'); document.getElementById('tab-mat-inventory').classList.remove('active'); document.getElementById('tab-mat-' + tabId).classList.add('active');
        document.getElementById('mtab-suppliers').classList.remove('active'); document.getElementById('mtab-inventory').classList.remove('active'); document.getElementById('mtab-' + tabId).classList.add('active');
    },
    loadSuppliersDashboard: async function() { this.renderSuppliersTable(); this.renderInventoryTable(); },

    renderSuppliersTable: async function() {
        const suppliers = await this.request('get_suppliers'); const tbody = document.getElementById('suppliers-content'); if (!tbody) return; tbody.innerHTML = '';
        if (suppliers.length === 0) { tbody.innerHTML = `<tr><td colspan="5" class="empty-state-wrapper"><i class="fa-solid fa-truck-field"></i><p>No suppliers available.</p></td></tr>`; }
        suppliers.forEach(s => { let statusBadge = s.status === 'Active' ? `<span class="badge completed">Active</span>` : `<span class="badge" style="background:#E5E7EB; color:#6B7280; border: 1px solid #D1D5DB;">Inactive</span>`; tbody.innerHTML += `<tr><td><b style="color:var(--text-dark); font-size:0.9rem;">${s.name}</b></td><td style="color:var(--text-main); font-weight:600;">${s.materials}</td><td style="color:var(--text-muted);"><i class="fa-solid fa-phone" style="margin-right:4px;"></i> ${s.contact}</td><td style="color:var(--text-muted);"><i class="fa-solid fa-envelope" style="margin-right:4px;"></i> ${s.email || 'N/A'}</td><td>${statusBadge}</td></tr>`; });
        document.getElementById('stat-active-suppliers').innerText = suppliers.filter(s => s.status === 'Active').length;
    },

    renderInventoryTable: async function() {
        const inventory = await this.request('get_inventory'); const suppliers = await this.request('get_suppliers'); const tbody = document.getElementById('inventory-content'); if (!tbody) return; tbody.innerHTML = ''; let lowStockCount = 0;
        if (inventory.length === 0) { tbody.innerHTML = `<tr><td colspan="5" class="empty-state-wrapper"><i class="fa-solid fa-box-open"></i><p>No inventory items available.</p></td></tr>`; }
        inventory.forEach(inv => {
            let stockStyle = "color: var(--text-dark);"; if (inv.stock <= 5) { stockStyle = "color: var(--danger); font-weight: 800;"; lowStockCount++; }
            let supMatch = suppliers.find(s => s.name === inv.supplier); let supplierUI = supMatch ? `<b>${supMatch.name}</b><br><small style="color:var(--text-muted);">${supMatch.contact}</small>` : `<b style="color:var(--text-muted);">Unassigned</b>`;
            let fmtCost = parseFloat(inv.unit_cost).toLocaleString('en-US', {minimumFractionDigits: 2});
            tbody.innerHTML += `<tr><td><b style="color:var(--text-dark); font-size:0.9rem;">${inv.name}</b><br><small style="color:var(--text-muted);">${inv.unit}</small></td><td style="color:var(--text-muted); font-weight:600;">${inv.category}</td><td><span style="${stockStyle} font-size:1rem;">${inv.stock}</span></td><td style="color:var(--success); font-weight:700;">₱${fmtCost}</td><td>${supplierUI}</td></tr>`;
        });
        document.getElementById('stat-low-stock').innerText = `${lowStockCount} Items`;
    },

    populateInventorySupplierDropdown: async function() {
        const suppliers = await this.request('get_suppliers'); const select = document.getElementById('stock-supplier'); if(!select) return; select.innerHTML = '<option value="">Select Supplier</option>';
        suppliers.filter(s => s.status === 'Active').forEach(sup => { select.innerHTML += `<option value="${sup.name}">${sup.name}</option>`; });
    },

    populateCategoryDropdown: async function() {
        const categories = await this.request('get_inventory_categories'); const select = document.getElementById('stock-category'); if(!select) return; select.innerHTML = '<option value="">Select Category</option>';
        categories.forEach(cat => { select.innerHTML += `<option value="${cat}">${cat}</option>`; }); select.innerHTML += `<option value="ADD_NEW" style="font-weight: 800; color: var(--primary-hover);">+ Add New Category</option>`;
    },

    handleCategoryChange: function(val) { const newCatInput = document.getElementById('stock-category-new'); if(!newCatInput) return; if(val === 'ADD_NEW') { newCatInput.style.display = 'block'; newCatInput.focus(); } else { newCatInput.style.display = 'none'; newCatInput.value = ''; } },

    submitNewSupplier: async function() {
        const name = document.getElementById('new-sup-name').value; const mats = document.getElementById('new-sup-materials').value; const contact = document.getElementById('new-sup-contact').value; const email = document.getElementById('new-sup-email').value;
        if (!name || !mats || !contact) { this.showToast('Name, Materials, and Contact are required!', 'error'); return; }
        await this.request('add_supplier', { name, materials: mats, contact, email });
        document.getElementById('new-sup-name').value = ''; document.getElementById('new-sup-materials').value = ''; document.getElementById('new-sup-contact').value = ''; document.getElementById('new-sup-email').value = '';
        this.closeModal('modal-add-supplier'); this.renderSuppliersTable(); this.showToast('New supplier successfully added.');
    },

    submitNewStock: async function() {
        const name = document.getElementById('stock-name').value; let cat = document.getElementById('stock-category').value;
        const qty = document.getElementById('stock-qty').value; const unit = document.getElementById('stock-unit').value; const cost = document.getElementById('stock-cost').value; const supplier = document.getElementById('stock-supplier').value;
        if (cat === 'ADD_NEW') { cat = document.getElementById('stock-category-new').value.trim(); if (cat) await this.request('add_inventory_category', { name: cat }); }
        if (!name || !qty || !unit || !cost || !cat) { this.showToast('Name, Category, Stock, Unit, and Cost are required!', 'error'); return; }
        await this.request('add_inventory', { name, category: cat, qty, unit, cost, supplier });
        document.getElementById('stock-name').value = ''; document.getElementById('stock-category').value = ''; document.getElementById('stock-category-new').value = ''; document.getElementById('stock-category-new').style.display = 'none'; document.getElementById('stock-qty').value = ''; document.getElementById('stock-unit').value = ''; document.getElementById('stock-cost').value = ''; document.getElementById('stock-supplier').value = '';
        this.closeModal('modal-add-stock'); this.renderInventoryTable(); this.showToast(`Inventory item added successfully.`);
    },

    // ==========================================
    // MANPOWER (RECORD LIST) 
    // ==========================================
    loadProjectOptionsForManpower: async function() {
        const proj = await this.request('get_projects'); const select = document.getElementById('man-project'); if (!select) return; select.innerHTML = '<option value="">Select Project</option>';
        if(proj) { const activeProjects = proj.filter(p => p.status === 'ongoing' || p.status === 'pending'); activeProjects.forEach(p => select.innerHTML += `<option value="${p.id}">${p.name} - ${p.location}</option>`); }
    },
    
    addManpower: async function() {
        const name = document.getElementById('man-name').value; const skills = document.getElementById('man-skills').value; const position = document.getElementById('man-pos').value; const salary = document.getElementById('man-salary').value; const project_id = document.getElementById('man-project').value; const photoInput = document.getElementById('man-photo');
        if (!name || !position || !salary) { this.showToast('Fill in Name, Position, and Salary Rate!', 'error'); return; }
        
        const fd = new FormData(); fd.append('name', name); fd.append('skills', skills); fd.append('position', position); fd.append('salary', salary); if (project_id) fd.append('project_id', project_id); if(photoInput.files.length > 0) fd.append('photo', photoInput.files[0]);
        const res = await this.request('add_manpower', fd, true);
        
        if (res.status === 'success') {
            document.getElementById('man-name').value = ''; document.getElementById('man-skills').value = ''; document.getElementById('man-pos').value = ''; document.getElementById('man-salary').value = ''; document.getElementById('man-project').value = ''; document.getElementById('man-photo').value = '';
            this.loadManpowerFolders(); this.showToast("Record successfully added.");
        } else { this.showToast("Warning: " + res.message, 'error'); }
    },

    loadManpowerFolders: async function() {
        document.getElementById('manpower-folders-view').style.display = 'block'; document.getElementById('manpower-table-view').style.display = 'none';
        const skills = await this.request('get_manpower_skills'); const grid = document.getElementById('skill-folders-grid'); grid.innerHTML = '';
        if (!skills || skills.length === 0) { grid.innerHTML = '<p style="color: var(--text-muted);">No records found.</p>'; return; }
        skills.forEach(s => { const skillName = s.skill_name || 'Uncategorized'; grid.innerHTML += `<div class="stat-card" style="cursor:pointer;" onclick="app.openSkillFolder('${skillName}')"><div class="stat-details"><h3 style="font-size: 1rem; color:var(--text-dark); text-transform: capitalize; font-weight:600;">${skillName}</h3><span style="color:var(--text-muted); font-size:0.85rem;">${s.worker_count} Record(s)</span></div><div class="stat-icon" style="background:var(--bg-main); color:var(--text-muted);"><i class="fa-solid fa-folder"></i></div></div>`; });
    },

    openSkillFolder: async function(skillName) {
        document.getElementById('manpower-folders-view').style.display = 'none'; document.getElementById('manpower-table-view').style.display = 'block'; document.getElementById('current-skill-title').innerHTML = `<i class="fa-solid fa-folder-open"></i> ${skillName} Records`;
        const workerList = await this.request('get_manpower_by_skill', { skill: skillName }); const tbody = document.querySelector('#table-users tbody'); tbody.innerHTML = '';
        if (!workerList || workerList.length === 0) { tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No records found.</td></tr>`; return; }
        workerList.forEach(w => { let resumeButton = `<span style="color:var(--text-muted); font-size: 0.85rem;">No Bio Data</span>`; if (w.photo) resumeButton = `<button class="btn-outline btn-sm" onclick="app.viewAttachedFile('${w.photo}')"><i class="fa-solid fa-image"></i> View</button>`; tbody.innerHTML += `<tr><td><b style="color:var(--text-dark);">${w.name}</b> <br> <small style="color:var(--text-muted);">ID: ${w.id}</small></td><td>${w.project_name || '<small style="color:var(--text-muted);">Unassigned</small>'}</td><td>${w.skills || 'Uncategorized'}</td><td>${w.position || 'N/A'}</td><td style="font-weight:600;">₱${w.salary || 0}</td><td>${resumeButton}</td></tr>`; });
    },
    backToSkills: function() { this.loadManpowerFolders(); },

    // ==========================================
    // AWARD COST
    // ==========================================
    addAwardCost: async function() {
        const desc = document.getElementById('awd-desc').value; const amount = document.getElementById('awd-amount').value;
        if (!desc || !amount) { this.showToast("Fill in Job Description and Amount!", 'error'); return; }
        await this.request('add_award_cost', { desc, amount });
        document.getElementById('awd-desc').value = ''; document.getElementById('awd-amount').value = ''; this.loadAwardCosts(); this.showToast('Award Cost added.');
    },
    loadAwardCosts: async function() {
        const data = await this.request('get_award_costs'); const tbody = document.querySelector('#table-award-costs tbody'); if(tbody) { tbody.innerHTML = ''; if(data) { data.forEach(d => { let fmtAmt = parseFloat(d.amount).toLocaleString('en-US', {minimumFractionDigits: 2}); tbody.innerHTML += `<tr><td><b style="color:var(--text-dark);">${d.scope_of_work}</b></td><td style="font-weight:700; color:var(--text-dark);">₱${fmtAmt}</td><td><button class="btn-danger" style="height:26px; padding:0 8px; border-radius:4px;" onclick="app.deleteAwardCost(${d.id})"><i class="fa-solid fa-trash"></i></button></td></tr>`; }); } }
    },
    deleteAwardCost: async function(id) { if(confirm("Delete this Job Description?")) { await this.request('delete_award_cost', { id }); this.loadAwardCosts(); } },

    // ==========================================
    // PAYROLL 
    // ==========================================
    loadManpowerNames: async function() {
        const users = await this.request('get_active_manpower'); const datalist = document.getElementById('pay-name-list');
        if (datalist) { datalist.innerHTML = ''; if (Array.isArray(users)) users.forEach(u => datalist.innerHTML += `<option value="${u.name}">`); }
    },
    clearPayrollForm: function() { ['pay-date', 'pay-name', 'pay-job', 'pay-days', 'pay-deduc'].forEach(id => { if(document.getElementById(id)) document.getElementById(id).value = ''; }); },
    addPayroll: async function() {
        const date = document.getElementById('pay-date').value; const name = document.getElementById('pay-name').value; const job_desc = document.getElementById('pay-job').value; const days = document.getElementById('pay-days').value; const deduc = document.getElementById('pay-deduc').value || 0; 
        if (!name || !days || !date || !job_desc) { this.showToast('Date, Name, Job Description, and Days are required!', 'error'); return; }
        const res = await this.request('add_payroll', { date: date, name: name, job_desc: job_desc, days: days, deductions: deduc });
        if(res.status === 'success') { this.clearPayrollForm(); this.loadPayroll(); this.showToast('Added to payslip.'); } else { this.showToast(res.message, 'error'); }
    },
    loadPayroll: async function() {
        const pay = await this.request('get_payroll'); const tbody = document.querySelector('#table-payroll tbody'); if(tbody) tbody.innerHTML = '';
        if(pay) { pay.forEach(p => { tbody.innerHTML += `<tr><td>${p.pay_date}</td><td><b style="color:var(--text-dark);">${p.name}</b></td><td><span class="badge ongoing">${p.job_description || 'N/A'}</span></td><td style="font-weight:600;">₱${p.rate}</td><td>${p.days_worked}</td><td style="font-weight:700;">₱${p.gross_pay}</td><td style="color:var(--danger); font-weight:600;">-₱${p.deductions}</td><td style="font-weight:800; color:var(--text-dark);">₱${p.net_pay}</td></tr>`; }); }
    },
    resetDatabasePayroll: async function() { if(confirm("Move all current records to History and start a new Payroll Cycle?")) { await this.request('archive_and_reset_payroll'); this.loadPayroll(); this.showToast("Cycle Closed. Records moved to History."); } },
    viewPayrollHistory: async function() {
        document.getElementById('payroll-active-view').style.display = 'none'; document.getElementById('payroll-history-view').style.display = 'block';
        const history = await this.request('get_payroll_history'); const tbody = document.querySelector('#table-payroll-history tbody');
        if(tbody) { tbody.innerHTML = ''; if(history.length === 0) { tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No history found.</td></tr>`; return; } history.forEach(h => { tbody.innerHTML += `<tr><td><small style="color:var(--text-muted); font-weight:700;">${h.cycle_id}</small></td><td>${h.pay_date}</td><td><b style="color:var(--text-dark);">${h.name}</b></td><td>${h.job_description || 'N/A'}</td><td style="font-weight:600;">₱${h.rate}</td><td style="font-weight:800; color:var(--text-dark);">₱${h.net_pay}</td></tr>`; }); }
    },
    backToActivePayroll: function() { if(document.getElementById('payroll-history-view')) document.getElementById('payroll-history-view').style.display = 'none'; if(document.getElementById('payroll-active-view')) document.getElementById('payroll-active-view').style.display = 'block'; },

    // ==========================================
    // NTP GLOBAL
    // ==========================================
    loadGlobalNTP: async function() {
        const proj = await this.request('get_projects'); const select = document.getElementById('g-ntp-project');
        if (select) { select.innerHTML = '<option value="">Select Pending Project</option>'; proj.filter(p => p.status === 'pending').forEach(p => select.innerHTML += `<option value="${p.id}">${p.name} - ${p.location}</option>`); }
        const ntps = await this.request('get_all_ntps'); const tbody = document.querySelector('#table-global-ntp tbody'); if(tbody) tbody.innerHTML = '';
        if(ntps) { ntps.forEach(n => { let fmtCost = n.award_cost ? '₱' + parseFloat(n.award_cost).toLocaleString('en-US') : 'N/A'; tbody.innerHTML += `<tr><td><b style="color:var(--text-dark);">${n.project_name}</b></td><td>${n.ntp_ticket || 'N/A'}</td><td>${n.date_received}</td><td style="font-weight:700;">${fmtCost}</td><td><b style="color:var(--danger);">${n.due_date || 'N/A'}</b></td><td>${n.acceptance_date || 'N/A'}</td><td><span style="cursor:pointer; color:var(--primary-hover); text-decoration:underline;" onclick="app.viewAttachedFile('${n.file_path}')">View PDF</span></td></tr>`; }); }
    },
    uploadGlobalNTP: async function() {
        const project_id = document.getElementById('g-ntp-project').value; const ticket = document.getElementById('g-ntp-ticket').value; const date = document.getElementById('g-ntp-date').value; const award_cost = document.getElementById('g-ntp-cost').value; const due_date = document.getElementById('g-ntp-due').value; const accept_date = document.getElementById('g-ntp-accept').value; const fileInput = document.getElementById('g-ntp-file');
        if (!project_id || !date || !due_date || fileInput.files.length === 0) { this.showToast('Project, NTP Date, Due Date, and File are required!', 'error'); return; }
        
        const fd = new FormData(); fd.append('project_id', project_id); fd.append('ticket', ticket); fd.append('date', date); fd.append('award_cost', award_cost); fd.append('due_date', due_date); fd.append('accept_date', accept_date); fd.append('file', fileInput.files[0]);
        const res = await this.request('upload_ntp_file', fd, true);
        if (res.status === 'success') { document.getElementById('g-ntp-ticket').value = ''; document.getElementById('g-ntp-cost').value = ''; document.getElementById('g-ntp-accept').value = ''; document.getElementById('g-ntp-file').value = ''; this.loadGlobalNTP(); this.showToast("NTP Successfully uploaded!"); } else { this.showToast(res.message, 'error'); }
    }
};

window.onload = () => app.init();