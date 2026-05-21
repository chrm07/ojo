const app = {
    currentProjectId: null,
    currentFilePreview: null,
    searchTimeout: null,

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
        try {
            let bodyData;
            if (isFormData) {
                bodyData = data; bodyData.append('action', action);
            } else {
                bodyData = new URLSearchParams(); bodyData.append('action', action);
                for (let key in data) bodyData.append(key, data[key]);
            }
            const res = await fetch('backend/api.php', { method: 'POST', body: bodyData });
            const text = await res.text();
            try { return JSON.parse(text); } 
            catch(e) { console.error("Backend Error:", text); return { status: 'error', message: 'DB Error: Check Backend.' }; }
        } catch(e) { console.error("Network Error:", e); return { status: 'error', message: 'Failed to connect.' }; }
    },

    checkSession: async function() { const res = await this.request('check_session'); if (res.logged_in) { document.getElementById('auth-screen').style.display = 'none'; document.getElementById('app-layout').style.display = 'flex'; this.showModule('dashboard'); } },
    
    handleAuth: async function() {
        const emailInput = document.getElementById('auth-email');
        const passInput = document.getElementById('auth-pass');
        emailInput.classList.remove('input-error'); passInput.classList.remove('input-error');

        const res = await this.request('login', { email: emailInput.value, password: passInput.value });
        if (res.status === 'success') { 
            document.getElementById('auth-screen').style.display = 'none'; document.getElementById('app-layout').style.display = 'flex'; this.showModule('dashboard'); 
        } else { 
            this.showToast(res.message, 'error'); 
            if(res.field === 'email') { emailInput.classList.add('input-error'); emailInput.focus(); } 
            else if (res.field === 'password') { passInput.classList.add('input-error'); passInput.focus(); } 
            else { emailInput.classList.add('input-error'); passInput.classList.add('input-error'); }
        }
    },
    
    logout: async function() { await this.request('logout'); location.reload(); },

    togglePassword: function() {
        const passInput = document.getElementById('auth-pass'); const eyeIcon = document.getElementById('toggle-password');
        if(passInput.type === 'password') { passInput.type = 'text'; eyeIcon.classList.remove('fa-eye'); eyeIcon.classList.add('fa-eye-slash'); } 
        else { passInput.type = 'password'; eyeIcon.classList.remove('fa-eye-slash'); eyeIcon.classList.add('fa-eye'); }
    },

    // --- FIX: BINALIK ANG MISSING TOGGLE SIDEBAR FUNCTION PARA SA HAMBURGER MENU ---
    toggleSidebar: function() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if(sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        } else {
            sidebar.classList.add('active');
            overlay.classList.add('active');
        }
    },
    
    closeSidebarMobile: function() {
        if(window.innerWidth <= 900) {
            document.querySelector('.sidebar').classList.remove('active');
            document.getElementById('sidebar-overlay').classList.remove('active');
        }
    },

    showToast: function(message, type = 'success') {
        const existingToast = document.querySelector('.toast-notification');
        if (existingToast) existingToast.remove();
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type === 'error' ? 'toast-error' : ''}`;
        const icon = type === 'error' ? '<i class="fa-solid fa-circle-exclamation" style="color:var(--danger); font-size:1.2rem;"></i>' : '<i class="fa-solid fa-circle-check" style="color:var(--success); font-size:1.2rem;"></i>';
        toast.innerHTML = `${icon} <span>${message}</span>`;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.animation = 'slideInRight 0.3s reverse forwards'; setTimeout(() => toast.remove(), 4000); }, 4000);
    },

    openModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            if (modalId === 'modal-add-stock') { this.populateInventorySupplierDropdown(); this.populateCategoryDropdown(); const newCatInput = document.getElementById('stock-category-new'); if(newCatInput) { newCatInput.style.display = 'none'; newCatInput.value = ''; } }
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
        if(id === 'users') { this.loadProjectOptionsForManpower(); this.populateManpowerDropdowns(); this.loadManpowerFolders(); }
        if(id === 'award_costs') this.loadAwardCosts();
        if(id === 'payroll') { this.backToActivePayroll(); this.renderPayrollTab(); this.populatePayrollDatalists(); }
        if(id === 'cash_release') this.loadCashRelease(); 
        if(id === 'global_ntp') this.loadGlobalNTP();

        this.clearGlobalSearch();
        this.closeSidebarMobile();
    },

    // FIX: Dashboard numbers now sync exactly with the backend count.
    loadDashboard: async function() {
        const stats = await this.request('get_stats');
        const projects = await this.request('get_projects');
        window.allProjectsData = projects;
        
        document.getElementById('stat-projects').innerText = stats.projects || 0;
        document.getElementById('stat-users').innerText = stats.users || 0;
        document.getElementById('stat-cash-release').innerText = '₱' + parseFloat(stats.total_cash_release || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
        document.getElementById('stat-payroll-advance').innerText = '₱' + parseFloat(stats.total_payroll_advance || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
        
        this.loadUpcomingDeadlines(); 
    },

    loadUpcomingDeadlines: async function() {
        const tbody = document.getElementById('deadlines-content'); if(!tbody) return;
        const projects = window.allProjectsData || await this.request('get_projects');
        const today = new Date(); today.setHours(0,0,0,0);
        let deadlines = [];
        
        if (Array.isArray(projects)) {
            projects.forEach(p => {
                if ((p.status || '').toLowerCase().trim() === 'completed') return; 
                let sDate = new Date(p.start_date); 
                let dOffset = Math.floor((sDate - today) / (1000 * 60 * 60 * 24));
                deadlines.push({ type: 'project', icon: 'fa-city', site: p.location, action: p.name, daysOffset: dOffset, actualDate: sDate });
            });
        }

        deadlines = deadlines.filter(t => t.daysOffset <= 30).sort((a, b) => a.daysOffset - b.daysOffset);
        tbody.innerHTML = '';
        if (deadlines.length === 0) { tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 20px; color: var(--text-muted);">No upcoming deadlines.</td></tr>`; return; }

        deadlines.forEach(task => {
            let statusBadge = ''; 
            let dateText = task.actualDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

            if (task.daysOffset < 0) { 
                statusBadge = `<span class="badge" style="background: #FEE2E2; color: #EF4444; border: 1px solid #FCA5A5;">OVERDUE (${Math.abs(task.daysOffset)} DAYS LATE)</span>`; 
            } else if (task.daysOffset === 0) { 
                statusBadge = `<span class="badge" style="background: #FEE2E2; color: #EF4444; border: 1px solid #FCA5A5;">DUE TODAY</span>`; 
            } else if (task.daysOffset <= 7) { 
                statusBadge = `<span class="badge" style="background: #FEF3C7; color: #B45309; border: 1px solid #FCD34D;">URGENT (${task.daysOffset} DAYS LEFT)</span>`; 
            } else { 
                statusBadge = `<span class="badge" style="background: #E5E7EB; color: #374151; border: 1px solid #D1D5DB;">UPCOMING (${task.daysOffset} DAYS LEFT)</span>`; 
            }

            tbody.innerHTML += `<tr>
                <td style="text-align: center;"><div class="type-icon-box" style="margin: 0 auto; color: var(--text-muted);"><i class="fa-solid ${task.icon}"></i></div></td>
                <td><b style="color:var(--text-dark);">${task.site}</b></td>
                <td style="font-weight: 600; color: var(--text-main);">${task.action}</td>
                <td><span style="font-weight: 600; color: var(--text-dark);">${dateText}</span></td>
                <td>${statusBadge}</td>
            </tr>`;
        });
    },

    handleGlobalSearch: function(query) {
        const searchContainer = document.getElementById('global-search-results'); 
        const content = document.getElementById('search-results-content'); 
        const clearBtn = document.getElementById('clear-search-btn'); 
        const deadlinesContainer = document.getElementById('upcoming-deadlines-container');
        
        if (!query || query.trim() === '') { 
            searchContainer.style.display = 'none'; clearBtn.style.display = 'none'; 
            if(deadlinesContainer) deadlinesContainer.style.display = 'block'; 
            clearTimeout(this.searchTimeout); return; 
        }

        clearBtn.style.display = 'block'; searchContainer.style.display = 'block'; 
        if(deadlinesContainer) deadlinesContainer.style.display = 'none'; 
        
        const queryDisplay = document.getElementById('search-query-display');
        if(queryDisplay) queryDisplay.innerText = query;

        content.innerHTML = `<p style="padding: 10px; color: var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Searching across all modules...</p>`;

        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(async () => {
            if (!window.globalSearchData || !window.globalSearchData.timestamp || (Date.now() - window.globalSearchData.timestamp > 15000)) {
                const fetchedData = await this.request('get_global_search_data');
                if(fetchedData) { window.globalSearchData = { ...fetchedData, timestamp: Date.now() }; }
            }
            const db = window.globalSearchData || { projects:[], users:[], suppliers:[], inventory:[], award_costs:[], cash_releases:[] };
            const q = query.toLowerCase(); let resultsHTML = '';
            
            const matchedProjs = (db.projects || []).filter(p => p.name.toLowerCase().includes(q) || p.location.toLowerCase().includes(q));
            const matchedUsers = (db.users || []).filter(u => u.name.toLowerCase().includes(q) || (u.position && u.position.toLowerCase().includes(q)));
            const matchedSuppliers = (db.suppliers || []).filter(s => s.name.toLowerCase().includes(q) || s.materials.toLowerCase().includes(q));
            const matchedInventory = (db.inventory || []).filter(i => i.name.toLowerCase().includes(q) || i.category.toLowerCase().includes(q));
            const matchedAwards = (db.award_costs || []).filter(a => a.scope_of_work.toLowerCase().includes(q));
            const matchedCash = (db.cash_releases || []).filter(c => c.name.toLowerCase().includes(q) || (c.description && c.description.toLowerCase().includes(q)) || c.category.toLowerCase().includes(q));

            matchedProjs.forEach(item => { resultsHTML += `<div class="search-result-item" onclick="app.showModule('projects'); app.openProjectDetails(${item.id}, '${item.name.replace(/'/g, "\\'")}', '${item.location.replace(/'/g, "\\'")}')"><div class="search-icon-box"><i class="fa-solid fa-city"></i></div><div class="search-content"><h4>${item.name}</h4><p>${item.location} | Foreman: ${item.foreman || 'N/A'}</p><span class="search-category-badge">Projects</span></div></div>`; });
            matchedUsers.forEach(item => { 
                let safeName = (item.name||'').replace(/'/g, "\\'"); let safeId = (item.name||'').replace(/[^a-zA-Z0-9]/g, '-');
                resultsHTML += `<div class="search-result-item" onclick="app.showModule('users'); setTimeout(()=>app.openSkillFolder('${item.skills || 'Uncategorized'}'), 200)"><div class="search-icon-box" style="color:var(--success);"><i class="fa-solid fa-user-helmet"></i></div><div class="search-content"><h4>${item.name}</h4><p>${item.position || 'Worker'} | ${item.skills || 'N/A'}</p><span class="search-category-badge" style="color:var(--success);">Record List</span></div></div>`; 
                resultsHTML += `<div class="search-result-item" onclick="app.showModule('payroll'); setTimeout(() => { document.getElementById('pay-name').value = '${safeName}'; const r = document.getElementById('nested-${safeId}'); if(r){ r.classList.add('active'); r.scrollIntoView({behavior: 'smooth', block: 'center'}); } }, 300);"><div class="search-icon-box" style="color:var(--warning);"><i class="fa-solid fa-file-invoice-dollar"></i></div><div class="search-content"><h4>${item.name}</h4><p>Log Cash Advance / Compute Balance</p><span class="search-category-badge" style="color:var(--warning);">Payroll</span></div></div>`; 
            });
            matchedSuppliers.forEach(item => { resultsHTML += `<div class="search-result-item" onclick="app.showModule('materials'); app.switchMatTab('suppliers');"><div class="search-icon-box" style="color:#10B981;"><i class="fa-solid fa-truck-field"></i></div><div class="search-content"><h4>${item.name}</h4><p>Provides: ${item.materials}</p><span class="search-category-badge" style="color:#10B981;">Supplier</span></div></div>`; });
            matchedInventory.forEach(item => { resultsHTML += `<div class="search-result-item" onclick="app.showModule('materials'); app.switchMatTab('inventory');"><div class="search-icon-box" style="color:#3B82F6;"><i class="fa-solid fa-boxes-stacked"></i></div><div class="search-content"><h4>${item.name}</h4><p>Stock: ${item.stock} ${item.unit} | Category: ${item.category}</p><span class="search-category-badge" style="color:#3B82F6;">Inventory</span></div></div>`; });
            matchedAwards.forEach(item => { resultsHTML += `<div class="search-result-item" onclick="app.showModule('award_costs');"><div class="search-icon-box" style="color:#8B5CF6;"><i class="fa-solid fa-clipboard-list"></i></div><div class="search-content"><h4>${item.scope_of_work}</h4><p>Amount: ₱${parseFloat(item.amount).toLocaleString('en-US')}</p><span class="search-category-badge" style="color:#8B5CF6;">Award Cost</span></div></div>`; });
            matchedCash.forEach(item => { resultsHTML += `<div class="search-result-item" onclick="app.showModule('cash_release');"><div class="search-icon-box" style="color:#EF4444;"><i class="fa-solid fa-hand-holding-dollar"></i></div><div class="search-content"><h4>${item.name} - ${item.category}</h4><p>${item.description || 'No Description'} | Amount: ₱${parseFloat(item.amount).toLocaleString('en-US')}</p><span class="search-category-badge" style="color:#EF4444;">Cash Release</span></div></div>`; });

            if(resultsHTML === '') { resultsHTML = `<p style="padding: 10px; color: var(--text-muted);">No results found.</p>`; }
            content.innerHTML = resultsHTML;
        }, 400); 
    },

    clearGlobalSearch: function() { 
        document.getElementById('global-search-input').value = ''; 
        this.handleGlobalSearch(''); 
        const deadlinesContainer = document.getElementById('upcoming-deadlines-container');
        if(deadlinesContainer) deadlinesContainer.style.display = 'block'; 
    },

    // ==========================================
    // PROJECTS & WORKSPACE
    // ==========================================
    populateForemanDropdown: async function() {
        const users = await this.request('get_active_manpower'); const select = document.getElementById('proj-foreman'); if(!select) return; select.innerHTML = '<option value="">Select Site Foreman / In-Charge</option>';
        if(users && Array.isArray(users)) { const foremen = users.filter(m => m.position && (m.position.toLowerCase().includes('foreman') || m.position.toLowerCase().includes('lead') || m.position.toLowerCase().includes('engineer') || m.position.toLowerCase().includes('in-charge'))); foremen.forEach(f => { select.innerHTML += `<option value="${f.name}">${f.name} (${f.position})</option>`; }); }
    },

    handleFileSelect: function(input) {
        const display = document.getElementById('file-name-display'); const label = document.getElementById('file-dropzone-label');
        if (input.files && input.files.length > 0) { this.currentFilePreview = URL.createObjectURL(input.files[0]); display.innerHTML = `📄 ${input.files[0].name} <span style="margin-left:10px; color:var(--primary-hover); text-decoration:underline;" onclick="event.preventDefault(); app.viewAttachedFile('${this.currentFilePreview}')">View</span>`; label.style.borderColor = "var(--success)"; label.style.color = "var(--success)"; } 
        else { this.currentFilePreview = null; display.innerText = "Attach Initial NTP Document (Optional)"; label.style.borderColor = "#D1D5DB"; label.style.color = "var(--text-muted)"; }
    },

    viewAttachedFile: function(url) { if(url) { document.getElementById('resume-img').src = url; document.getElementById('resume-modal').style.display = 'block'; } },

    submitProjectForm: async function() {
        const name = document.getElementById('proj-name').value; const client = document.getElementById('proj-client').value || '-'; const location = document.getElementById('proj-loc').value; const desc = document.getElementById('proj-desc').value; const foremanRaw = document.getElementById('proj-foreman').value; const start_date = document.getElementById('proj-start').value; const fileInput = document.getElementById('proj-ntp-init');
        if (!name || !location || !start_date || !foremanRaw) { this.showToast('Project Name, Location, Foreman, and Date are required!', 'error'); return; }
        const foreman = foremanRaw.split(' (')[0];
        const res = await this.request('add_project', { name, client, location, desc, foreman, start_date });
        if(res.status === 'error') return this.showToast(res.message, 'error');
        document.getElementById('proj-name').value = ''; document.getElementById('proj-loc').value = ''; document.getElementById('proj-desc').value = ''; document.getElementById('proj-start').value = ''; document.getElementById('proj-client').value = ''; document.getElementById('proj-foreman').value = ''; fileInput.value = ''; this.handleFileSelect(fileInput); 
        
        await this.loadProjects(); 
        this.loadProjectOptionsForManpower(); 
        this.loadDashboard(); 
        this.showToast("Project successfully created.");
    },

    loadProjects: async function() { this.closeProjectDetails(); window.allProjectsData = await this.request('get_projects'); this.filterProjectsTable(); this.populateForemanDropdown(); },

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
            let stat = (p.status || '').toLowerCase().trim();
            let statusUI = ''; let actionBtn = ''; let viewNtpBtn = stat === 'pending' ? `<button class="btn-outline" style="height: 26px; padding: 0 8px; font-size: 0.75rem;" onclick="app.showModule('global_ntp')" title="View NTP"><i class="fa-solid fa-file-pdf"></i> Verify NTP</button>` : '';
            
            if (stat === 'pending') { 
                statusUI = `<span class="badge pending">Pending (NTP)</span>`; actionBtn = ``; 
            } else { 
                statusUI = `<select onchange="app.updateProjectStatus(${p.id}, this.value)" class="table-status-select" style="height:24px; padding: 0 4px; width:auto; font-size:0.75rem; background: ${stat === 'completed' ? '#D1FAE5' : '#FEFCE8'}; color: ${stat === 'completed' ? 'var(--success)' : '#854D0E'};"><option value="ongoing" ${stat === 'ongoing' ? 'selected' : ''}>Ongoing</option><option value="completed" ${stat === 'completed' ? 'selected' : ''}>Completed</option></select>`; 
                actionBtn = `<button class="btn" style="height: 26px; padding: 0 8px; font-size: 0.75rem;" onclick="app.openProjectDetails(${p.id}, '${p.name.replace(/'/g, "\\'")}', '${p.location.replace(/'/g, "\\'")}')">📂 Workspace</button>`; 
            }
            
            let projNameClickable = `<span style="cursor:pointer; color:var(--primary-hover); text-decoration:underline;" onclick="app.openProjectDetails(${p.id}, '${p.name.replace(/'/g, "\\'")}', '${p.location.replace(/'/g, "\\'")}')">${p.name}</span>`;
            tbody.innerHTML += `<tr><td><b style="color:var(--text-dark);">${projNameClickable}</b><br><small style="color:var(--text-muted); font-size:0.75rem;">${p.description || ''}</small></td><td><b style="color:var(--text-main); font-size:0.8rem;"><i class="fa-solid fa-user-helmet"></i> ${p.foreman || '-'}</b></td><td>${p.location}</td><td style="font-weight: 600;">${p.start_date}</td><td>${statusUI}</td><td><div style="display: flex; gap: 4px;">${viewNtpBtn}${actionBtn}<button class="btn-danger" style="height: 26px; padding: 0 8px; border-radius: 4px;" onclick="app.deleteProject(${p.id})"><i class="fa-solid fa-trash" style="font-size: 0.8rem;"></i></button></div></td></tr>`;
        });
    },

    updateProjectStatus: async function(id, status) { await this.request('update_project_status', { id, status }); await this.loadProjects(); this.loadDashboard(); },
    deleteProject: async function(id) { if(confirm("DANGER ZONE! Deleting this project will wipe all tracking data. Are you sure?")) { await this.request('delete_project', { id }); await this.loadProjects(); this.loadDashboard(); } },

    openProjectDetails: function(id, name, location) {
        this.currentProjectId = id;
        document.getElementById('projects-list-view').style.display = 'none'; document.getElementById('project-details-view').style.display = 'block';
        document.getElementById('pd-name').innerText = name; document.getElementById('pd-loc-display').innerText = location ? location : "Location not specified";
        document.getElementById('dynamic-breadcrumbs').innerHTML = `<span class="breadcrumb-link" onclick="app.showModule('dashboard')"><i class="fa-solid fa-house"></i> Home</span><i class="fa-solid fa-chevron-right separator"></i><span class="breadcrumb-link" onclick="app.showModule('projects')">Projects (Sites)</span><i class="fa-solid fa-chevron-right separator"></i><b id="breadcrumb-current" class="active-crumb">Workspace</b>`;
        this.switchProjectTab('progress');
    },

    closeProjectDetails: function() {
        this.currentProjectId = null;
        document.getElementById('projects-list-view').style.display = 'block'; document.getElementById('project-details-view').style.display = 'none';
        document.getElementById('dynamic-breadcrumbs').innerHTML = `<span class="breadcrumb-link" onclick="app.showModule('dashboard')"><i class="fa-solid fa-house"></i> Home</span><i class="fa-solid fa-chevron-right separator"></i><b id="breadcrumb-current" class="active-crumb">Projects (Sites)</b>`;
    },

    switchProjectTab: function(tabId) {
        document.getElementById('tab-progress').classList.remove('active'); document.getElementById('tab-materials').classList.remove('active'); document.getElementById('tab-manpower').classList.remove('active');
        document.getElementById('ptab-progress').classList.remove('active'); document.getElementById('ptab-materials').classList.remove('active'); document.getElementById('ptab-manpower').classList.remove('active');
        document.getElementById('tab-' + tabId).classList.add('active'); document.getElementById('ptab-' + tabId).classList.add('active');
        if(tabId === 'materials') this.renderProjectWorkspaceMaterials();
        if(tabId === 'progress') this.renderProjectChecklist();
        if(tabId === 'manpower') this.renderManpowerAssignments();
    },

    // --- CHECKLIST CRUD FROM DB ---
    renderProjectChecklist: async function() {
        const data = await this.request('get_project_data', { project_id: this.currentProjectId }); const grid = document.getElementById('checklist-grid'); grid.innerHTML = '';
        if(!data.checklist || data.checklist.length === 0) { grid.innerHTML = `<p style="grid-column: 1/-1; text-align:center; padding: 20px; color:var(--text-muted);">No checklist generated.</p>`; return; }
        let grouped = {};
        data.checklist.forEach(item => { const cat = item.category || 'Uncategorized'; if(!grouped[cat]) grouped[cat] = { items: [], assigned: item.assigned_worker }; if(item.task_name !== '') grouped[cat].items.push(item); if(item.assigned_worker) grouped[cat].assigned = item.assigned_worker; });

        let totalItems = 0; let completedItems = 0;
        Object.keys(grouped).forEach((cat, cIdx) => {
            let itemsHtml = '';
            grouped[cat].items.forEach((item, iIdx) => {
                totalItems++; let isComp = item.status === 'Completed'; if(isComp) completedItems++;
                const completedClass = isComp ? 'completed' : ''; const checkedAttr = isComp ? 'checked' : '';
                itemsHtml += `<div class="checklist-item ${completedClass}" id="item-row-${item.id}"><div class="checklist-item-left" onclick="app.toggleChecklistItemDB(${item.id}, '${isComp ? 'Not Started' : 'Completed'}', '${item.assigned_worker}', ${item.award_cost})"><input type="checkbox" ${checkedAttr} onclick="event.stopPropagation(); app.toggleChecklistItemDB(${item.id}, '${isComp ? 'Not Started' : 'Completed'}', '${item.assigned_worker}', ${item.award_cost})"><label>${item.task_name} <small style="color:var(--success); font-weight:700;" onclick="event.stopPropagation(); app.editChecklistCostDB(${item.id}, ${item.award_cost})">(₱${parseFloat(item.award_cost).toLocaleString('en-US')})</small></label></div><div class="checklist-item-actions"><button class="checklist-action-btn edit" onclick="app.editChecklistItemDB(${item.id}, '${item.task_name.replace(/'/g, "\\'")}')"><i class="fa-solid fa-pencil"></i></button><button class="checklist-action-btn delete" onclick="app.deleteChecklistItemDB(${item.id})"><i class="fa-solid fa-trash"></i></button></div></div>`;
            });
            let badgeHtml = grouped[cat].assigned ? `<span class="badge-assigned"><i class="fa-solid fa-user-check"></i> ${grouped[cat].assigned}</span>` : '';
            grid.innerHTML += `<div class="checklist-category"><h4>${cat} ${badgeHtml} <button class="checklist-action-btn delete" style="float:right;" onclick="app.deleteCategoryDB('${cat}')"><i class="fa-solid fa-xmark"></i></button></h4><div id="cat-items-${cIdx}">${itemsHtml}</div><div id="add-task-container-${cIdx}" style="display:none; margin-top:8px;"><input type="text" class="inline-input" id="add-task-input-${cIdx}" placeholder="Type task and press Enter..." onkeydown="if(event.key==='Enter') app.saveNewTaskDB('${cat}', this.value, ${cIdx})" onblur="this.parentElement.style.display='none'; document.getElementById('btn-show-add-${cIdx}').style.display='block';"></div><button id="btn-show-add-${cIdx}" class="add-task-btn" onclick="this.style.display='none'; document.getElementById('add-task-container-${cIdx}').style.display='block'; document.getElementById('add-task-input-${cIdx}').focus();"><i class="fa-solid fa-plus"></i> Add Task</button></div>`;
        });
        const pct = totalItems === 0 ? 0 : Math.round((completedItems / totalItems) * 100);
        document.getElementById('proj-progress-bar').style.width = pct + '%'; document.getElementById('proj-progress-text').innerText = pct + '%';
    },

    toggleChecklistItemDB: async function(taskId, newStatus, assignedWorker, cost) {
        await this.request('update_checklist_status', { task_id: taskId, status: newStatus });
        if(newStatus === 'Completed') { if(assignedWorker && assignedWorker !== 'null') { this.showToast(`Task completed! ₱${parseFloat(cost).toLocaleString('en-US')} auto-synced to ${assignedWorker}'s Payroll.`); } else { this.showToast(`Task completed! (Warning: No worker assigned, won't sync to Payroll).`, 'warning'); } }
        this.renderProjectChecklist();
    },

    saveNewTaskDB: async function(category, taskName, cIdx) { if(taskName.trim() !== '') { await this.request('add_checklist_task', { project_id: this.currentProjectId, category: category, task_name: taskName.trim() }); this.showToast('Task added.'); } document.getElementById('add-task-container-'+cIdx).style.display='none'; this.renderProjectChecklist(); },
    editChecklistItemDB: function(taskId, currentName) { const row = document.getElementById(`item-row-${taskId}`); row.innerHTML = `<div style="width:100%;"><input type="text" class="inline-input" value="${currentName}" onblur="app.saveEditedTaskDB(${taskId}, this.value)" onkeydown="if(event.key==='Enter') this.blur()" autofocus></div>`; row.querySelector('input').focus(); },
    saveEditedTaskDB: async function(taskId, newName) { if(newName.trim() !== '') { await this.request('edit_checklist_task', { task_id: taskId, task_name: newName.trim() }); } this.renderProjectChecklist(); },
    editChecklistCostDB: function(taskId, currentCost) { const newCost = prompt("Update Award Cost for this task (₱):", currentCost); if(newCost !== null && !isNaN(newCost)) { this.request('update_task_cost', { task_id: taskId, cost: newCost }).then(() => this.renderProjectChecklist()); } },
    deleteChecklistItemDB: async function(taskId) { await this.request('delete_checklist_task', { task_id: taskId }); this.renderProjectChecklist(); },
    showAddCategoryInput: function() { document.getElementById('btn-add-cat').style.display = 'none'; const input = document.getElementById('input-add-cat'); input.style.display = 'block'; input.focus(); },
    saveNewCategoryDB: async function(val) { const input = document.getElementById('input-add-cat'); input.style.display = 'none'; input.value = ''; document.getElementById('btn-add-cat').style.display = 'inline-flex'; if(val.trim() !== '') { await this.request('add_checklist_task', { project_id: this.currentProjectId, category: val.trim(), task_name: '' }); this.showToast('New Phase/Category added.'); this.renderProjectChecklist(); } },
    deleteCategoryDB: async function(category) { if(confirm(`Delete category "${category}" and all its tasks?`)) { await this.request('delete_checklist_category', { project_id: this.currentProjectId, category: category }); this.renderProjectChecklist(); } },

    // --- MANPOWER ASSIGNMENT (TAB 3) ---
    renderManpowerAssignments: async function() {
        const users = await this.request('get_active_manpower'); const data = await this.request('get_project_data', { project_id: this.currentProjectId });
        const workerSelect = document.getElementById('assign-worker'); workerSelect.innerHTML = '<option value="">Select Worker</option>';
        if(users && Array.isArray(users)) users.forEach(m => { workerSelect.innerHTML += `<option value="${m.name}">${m.name} (${m.position || 'Worker'})</option>`; });
        const catSelect = document.getElementById('assign-category'); catSelect.innerHTML = '<option value="">Select Category/Phase</option>';
        const tbody = document.getElementById('assignments-content'); tbody.innerHTML = '';
        if(!data.checklist || data.checklist.length === 0) { tbody.innerHTML = `<tr><td colspan="3" class="empty-state-wrapper"><p>No categories found in checklist.</p></td></tr>`; return; }
        let grouped = {}; data.checklist.forEach(item => { const cat = item.category || 'Uncategorized'; if(!grouped[cat]) grouped[cat] = { assigned: item.assigned_worker }; if(item.assigned_worker) grouped[cat].assigned = item.assigned_worker; });
        let hasAssignments = false;
        Object.keys(grouped).forEach(cat => { catSelect.innerHTML += `<option value="${cat}">${cat}</option>`; if(grouped[cat].assigned) { hasAssignments = true; tbody.innerHTML += `<tr><td><b style="color:var(--text-dark);">${cat}</b></td><td><span class="badge-assigned" style="margin:0;"><i class="fa-solid fa-user-check"></i> ${grouped[cat].assigned}</span></td><td><button class="btn-danger" style="height: 26px; padding: 0 8px; border-radius: 4px;" onclick="app.removeWorkerAssignment('${cat}')"><i class="fa-solid fa-trash"></i></button></td></tr>`; } });
        if(!hasAssignments) tbody.innerHTML = `<tr><td colspan="3" class="empty-state-wrapper"><p>No workers assigned to specific tasks yet.</p></td></tr>`;
    },
    assignWorkerToCategory: async function() { const cat = document.getElementById('assign-category').value; const worker = document.getElementById('assign-worker').value; if(!cat || !worker) { this.showToast('Select both Category and Worker.', 'error'); return; } await this.request('assign_worker', { project_id: this.currentProjectId, category: cat, worker: worker }); this.renderManpowerAssignments(); this.showToast(`${worker} assigned to ${cat}. Sync to Payroll Enabled.`); },
    removeWorkerAssignment: async function(cat) { await this.request('remove_worker', { project_id: this.currentProjectId, category: cat }); this.renderManpowerAssignments(); },

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
    switchMatTab: function(tabId) { document.getElementById('tab-mat-suppliers').classList.remove('active'); document.getElementById('tab-mat-inventory').classList.remove('active'); document.getElementById('tab-mat-' + tabId).classList.add('active'); document.getElementById('mtab-suppliers').classList.remove('active'); document.getElementById('mtab-inventory').classList.remove('active'); document.getElementById('mtab-' + tabId).classList.add('active'); },
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
        inventory.forEach(inv => { let stockStyle = "color: var(--text-dark);"; if (inv.stock <= 5) { stockStyle = "color: var(--danger); font-weight: 800;"; lowStockCount++; } let supMatch = suppliers.find(s => s.name === inv.supplier); let supplierUI = supMatch ? `<b>${supMatch.name}</b><br><small style="color:var(--text-muted);">${supMatch.contact}</small>` : `<b style="color:var(--text-muted);">Unassigned</b>`; let fmtCost = parseFloat(inv.unit_cost).toLocaleString('en-US', {minimumFractionDigits: 2}); tbody.innerHTML += `<tr><td><b style="color:var(--text-dark); font-size:0.9rem;">${inv.name}</b><br><small style="color:var(--text-muted);">${inv.unit}</small></td><td style="color:var(--text-muted); font-weight:600;">${inv.category}</td><td><span style="${stockStyle} font-size:1rem;">${inv.stock}</span></td><td style="color:var(--success); font-weight:700;">₱${fmtCost}</td><td>${supplierUI}</td></tr>`; });
        document.getElementById('stat-low-stock').innerText = `${lowStockCount} Items`;
    },
    populateInventorySupplierDropdown: async function() { const suppliers = await this.request('get_suppliers'); const select = document.getElementById('stock-supplier'); if(!select) return; select.innerHTML = '<option value="">Select Supplier</option>'; suppliers.filter(s => s.status === 'Active').forEach(sup => { select.innerHTML += `<option value="${sup.name}">${sup.name}</option>`; }); },
    populateCategoryDropdown: async function() { const categories = await this.request('get_inventory_categories'); const select = document.getElementById('stock-category'); if(!select) return; select.innerHTML = '<option value="">Select Category</option>'; categories.forEach(cat => { select.innerHTML += `<option value="${cat}">${cat}</option>`; }); select.innerHTML += `<option value="ADD_NEW" style="font-weight: 800; color: var(--primary-hover);">+ Add New Category</option>`; },
    handleCategoryChange: function(val) { const newCatInput = document.getElementById('stock-category-new'); if(!newCatInput) return; if(val === 'ADD_NEW') { newCatInput.style.display = 'block'; newCatInput.focus(); } else { newCatInput.style.display = 'none'; newCatInput.value = ''; } },
    submitNewSupplier: async function() {
        const name = document.getElementById('new-sup-name').value; const mats = document.getElementById('new-sup-materials').value; const contact = document.getElementById('new-sup-contact').value; const email = document.getElementById('new-sup-email').value;
        if (!name || !mats || !contact) { this.showToast('Name, Materials, and Contact are required!', 'error'); return; }
        await this.request('add_supplier', { name, materials: mats, contact, email });
        document.getElementById('new-sup-name').value = ''; document.getElementById('new-sup-materials').value = ''; document.getElementById('new-sup-contact').value = ''; document.getElementById('new-sup-email').value = ''; this.closeModal('modal-add-supplier'); this.renderSuppliersTable(); this.showToast('New supplier successfully added.');
    },
    submitNewStock: async function() {
        const name = document.getElementById('stock-name').value; let cat = document.getElementById('stock-category').value; const qty = document.getElementById('stock-qty').value; const unit = document.getElementById('stock-unit').value; const cost = document.getElementById('stock-cost').value; const supplier = document.getElementById('stock-supplier').value;
        if (cat === 'ADD_NEW') { cat = document.getElementById('stock-category-new').value.trim(); if (cat) await this.request('add_inventory_category', { name: cat }); }
        if (!name || !qty || !unit || !cost || !cat) { this.showToast('Name, Category, Stock, Unit, and Cost are required!', 'error'); return; }
        await this.request('add_inventory', { name, category: cat, qty, unit, cost, supplier });
        document.getElementById('stock-name').value = ''; document.getElementById('stock-category').value = ''; document.getElementById('stock-category-new').value = ''; document.getElementById('stock-category-new').style.display = 'none'; document.getElementById('stock-qty').value = ''; document.getElementById('stock-unit').value = ''; document.getElementById('stock-cost').value = ''; document.getElementById('stock-supplier').value = ''; this.closeModal('modal-add-stock'); this.renderInventoryTable(); this.showToast(`Inventory item added successfully.`);
    },

    // ==========================================
    // MODULE: MANPOWER (RECORD LIST) 
    // ==========================================
    
    // FIX: Populates all projects properly now
    loadProjectOptionsForManpower: async function() { 
        try {
            const proj = await this.request('get_projects'); 
            const select = document.getElementById('man-project'); 
            if (!select) return; 
            select.innerHTML = '<option value="">Select Project (Optional)</option>'; 
            if(proj && Array.isArray(proj)) { 
                proj.forEach(p => select.innerHTML += `<option value="${p.id}">${p.name} - ${p.location}</option>`); 
            } 
        } catch(e) { console.error(e); }
    },
    
    populateManpowerDropdowns: async function() {
        const skills = await this.request('get_manpower_skills'); const select = document.getElementById('man-skills'); if (!select) return; 
        select.innerHTML = '<option value="">Select Skill / Folder</option>';
        if(skills && Array.isArray(skills)) { skills.forEach(s => { const sName = s.skill_name || 'Uncategorized'; if(sName !== 'Uncategorized') { select.innerHTML += `<option value="${sName}">${sName}</option>`; } }); }
        select.innerHTML += `<option value="ADD_NEW" style="font-weight: 800; color: var(--primary-hover);">+ Add New Folder (Via Dropdown)</option>`;
    },
    handleSkillChange: function(val) { const newCatInput = document.getElementById('man-skills-new'); if(!newCatInput) return; if(val === 'ADD_NEW') { newCatInput.style.display = 'block'; newCatInput.focus(); } else { newCatInput.style.display = 'none'; newCatInput.value = ''; } },
    handlePosChange: function(val) { const newPosInput = document.getElementById('man-pos-new'); if(!newPosInput) return; if(val === 'ADD_NEW') { newPosInput.style.display = 'block'; newPosInput.focus(); } else { newPosInput.style.display = 'none'; newPosInput.value = ''; } },
    
    addFolderOnly: async function() { const name = prompt("Enter new Empty Folder / Skill Category name:"); if(name && name.trim() !== '') { await this.request('add_skill_category', { name: name.trim() }); this.loadManpowerFolders(); this.populateManpowerDropdowns(); this.showToast('Empty Folder created.'); } },
    editFolder: async function(oldName) { const newName = prompt("Rename Folder '" + oldName + "' to:", oldName); if(newName && newName.trim() !== '' && newName !== oldName) { await this.request('edit_skill_category', { old_name: oldName, new_name: newName.trim() }); this.loadManpowerFolders(); this.populateManpowerDropdowns(); this.showToast('Folder renamed successfully.'); } },
    deleteFolder: async function(name) { if(confirm(`Are you sure you want to delete the folder "${name}"? WARNING: This will also PERMANENTLY DELETE all active worker records inside this folder.`)) { await this.request('delete_skill_category', { name: name }); this.loadManpowerFolders(); this.populateManpowerDropdowns(); this.showToast('Folder and records deleted.'); } },

    archiveManpower: async function(id) { if(confirm('Archive this worker? They will be removed from active sites and sent to the Archived folder.')) { await this.request('archive_manpower', { id: id }); this.showToast('Worker archived.'); const currentTitleRaw = document.getElementById('current-skill-title').innerText.trim(); const cleanTitle = currentTitleRaw.replace('Rename Folder', '').replace('Delete Folder', '').trim(); if(cleanTitle) { this.openSkillFolder(cleanTitle); } else { this.loadManpowerFolders(); } } },
    restoreManpower: async function(id) { if(confirm('Restore this worker back to active status?')) { await this.request('restore_manpower', { id: id }); this.showToast('Worker restored.'); this.openArchivedFolder(); } },

    addManpower: async function() {
        const name = document.getElementById('man-name').value; 
        let skills = document.getElementById('man-skills').value; if (skills === 'ADD_NEW') skills = document.getElementById('man-skills-new').value.trim();
        let position = document.getElementById('man-pos').value; if (position === 'ADD_NEW') position = document.getElementById('man-pos-new').value.trim();
        const salary = document.getElementById('man-salary').value; const project_id = document.getElementById('man-project').value; const photoInput = document.getElementById('man-photo');

        if (!name || !position || !salary || !skills) { this.showToast('Fill in Name, Skill, Position, and Salary Rate!', 'error'); return; }
        
        const fd = new FormData(); fd.append('name', name); fd.append('skills', skills); fd.append('position', position); fd.append('salary', salary); fd.append('project_id', project_id || ''); if(photoInput.files.length > 0) fd.append('photo', photoInput.files[0]);
        
        const res = await this.request('add_manpower', fd, true);
        if (res.status === 'success') {
            document.getElementById('man-name').value = ''; document.getElementById('man-skills').value = ''; document.getElementById('man-skills-new').value = ''; document.getElementById('man-skills-new').style.display = 'none';
            document.getElementById('man-pos').value = ''; document.getElementById('man-pos-new').value = ''; document.getElementById('man-pos-new').style.display = 'none';
            document.getElementById('man-salary').value = ''; document.getElementById('man-project').value = ''; document.getElementById('man-photo').value = '';
            this.populateManpowerDropdowns(); this.loadManpowerFolders(); this.showToast("Record successfully added.");
        } else { this.showToast("Warning: " + res.message, 'error'); }
    },

    loadManpowerFolders: async function() {
        document.getElementById('manpower-folders-view').style.display = 'block'; document.getElementById('manpower-table-view').style.display = 'none';
        const skills = await this.request('get_manpower_skills'); const grid = document.getElementById('skill-folders-grid'); grid.innerHTML = '';
        if (!skills || skills.length === 0) { grid.innerHTML = '<p style="color: var(--text-muted);">No records found.</p>'; }
        
        skills.forEach(s => {
            const skillName = s.skill_name || 'Uncategorized';
            grid.innerHTML += `<div class="stat-card" style="cursor:pointer; min-height: 100px;" onclick="app.openSkillFolder('${skillName}')"><div class="stat-details"><h3 style="font-size: 1rem; color:var(--text-dark); text-transform: capitalize; font-weight:600;">${skillName}</h3><span style="color:var(--text-muted); font-size:0.85rem;">${s.worker_count} Record(s)</span></div><div class="stat-icon" style="background:var(--bg-main); color:var(--text-muted);"><i class="fa-solid fa-folder"></i></div></div>`;
        });

        grid.innerHTML += `<div class="stat-card" style="cursor:pointer; background:#FEF2F2; border-color:#FCA5A5; min-height: 100px;" onclick="app.openArchivedFolder()"><div class="stat-details"><h3 style="font-size: 1rem; color:var(--danger); font-weight:800;"><i class="fa-solid fa-box-archive" style="margin-right: 4px;"></i> Archived Records</h3><span style="color:var(--text-muted); font-size:0.85rem;">Inactive Worker Pool</span></div></div>`;
    },

    uploadBioData: async function(workerId, inputElement) {
        if (!inputElement.files || inputElement.files.length === 0) return;
        const fd = new FormData(); fd.append('worker_id', workerId); fd.append('photo', inputElement.files[0]);
        const res = await this.request('update_bio_data', fd, true);
        if (res.status === 'success') {
            this.showToast("Bio Data updated successfully.");
            const currentTitleRaw = document.getElementById('current-skill-title').innerText.trim(); 
            const cleanTitle = currentTitleRaw.replace('Rename Folder', '').replace('Delete Folder', '').trim(); 
            if(cleanTitle) { this.openSkillFolder(cleanTitle); }
        } else { this.showToast(res.message, 'error'); }
    },

    openSkillFolder: async function(skillName) {
        document.getElementById('manpower-folders-view').style.display = 'none'; document.getElementById('manpower-table-view').style.display = 'block'; 
        
        let titleHtml = `<div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
            <span><i class="fa-solid fa-folder-open" style="margin-right: 8px;"></i> ${skillName}</span>`;
        
        if (skillName !== 'Uncategorized') {
            titleHtml += `<div style="display:flex; gap:8px;">
                <button class="btn-outline" style="height: 30px; padding: 0 12px; font-size: 0.8rem;" onclick="app.editFolder('${skillName}')"><i class="fa-solid fa-pencil"></i> Rename Folder</button>
                <button class="btn-danger" style="height: 30px; padding: 0 12px; font-size: 0.8rem; border-radius: 4px;" onclick="app.deleteFolder('${skillName}')"><i class="fa-solid fa-trash"></i> Delete Folder</button>
            </div>`;
        }
        titleHtml += `</div>`;
        
        document.getElementById('current-skill-title').innerHTML = titleHtml;
        document.getElementById('table-users').innerHTML = `<thead><tr><th>Name</th><th>Project Assigned</th><th>Skills</th><th>Position</th><th>Rate</th><th>Bio Data</th><th>Action</th></tr></thead><tbody></tbody>`;
        
        const workerList = await this.request('get_manpower_by_skill', { skill: skillName }); const tbody = document.querySelector('#table-users tbody'); tbody.innerHTML = '';
        if (!workerList || workerList.length === 0) { tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">No records found.</td></tr>`; return; }

        workerList.forEach(w => {
            let photoUrl = w.photo || w.photo_path; 
            let resumeButton = ''; 
            let avatarHtml = `<div style="width: 36px; height: 36px; border-radius: 50%; background: #E5E7EB; display: flex; align-items: center; justify-content: center; color: #9CA3AF;"><i class="fa-solid fa-user"></i></div>`;
            
            if (photoUrl) { 
                resumeButton = `<button class="btn-outline btn-sm" onclick="app.viewAttachedFile('${photoUrl}')"><i class="fa-solid fa-image"></i> View</button>`; 
                avatarHtml = `<img src="${photoUrl}" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); cursor:pointer;" onclick="app.viewAttachedFile('${photoUrl}')" onerror="this.src='https://ui-avatars.com/api/?name=${w.name}&background=FACC15&color=000'">`;
            } else {
                resumeButton = `<label class="btn-outline btn-sm" style="cursor:pointer; margin:0; padding: 4px 8px; font-size:0.75rem; border-color:var(--primary); color:var(--text-dark); background:#FEFCE8;"><i class="fa-solid fa-upload"></i> Add Bio Data<input type="file" style="display:none;" accept="image/*" onchange="app.uploadBioData(${w.id}, this)"></label>`;
            }
            
            let nameDisplay = `<div style="display:flex; align-items:center; gap:10px;">${avatarHtml}<div><b style="color:var(--text-dark);">${w.name}</b> <br> <small style="color:var(--text-muted);">ID: ${w.id}</small></div></div>`;

            tbody.innerHTML += `<tr><td>${nameDisplay}</td><td>${w.project_name || '<small style="color:var(--text-muted);">Unassigned</small>'}</td><td>${w.skills || 'Uncategorized'}</td><td>${w.position || 'N/A'}</td><td style="font-weight:600;">₱${w.salary || 0}</td><td>${resumeButton}</td><td><button class="btn-outline btn-sm" style="color:var(--danger); border-color:#FCA5A5;" onclick="app.archiveManpower(${w.id})"><i class="fa-solid fa-box-archive"></i> Archive</button></td></tr>`;
        });
    },

    openArchivedFolder: async function() {
        document.getElementById('manpower-folders-view').style.display = 'none'; document.getElementById('manpower-table-view').style.display = 'block'; document.getElementById('current-skill-title').innerHTML = `<i class="fa-solid fa-box-archive" style="color:var(--danger);"></i> Archived Manpower`;
        document.getElementById('table-users').innerHTML = `<thead><tr><th>Name</th><th>Position</th><th>Folder (Skill)</th><th>Date Archived</th><th>Action</th></tr></thead><tbody></tbody>`;
        
        const workerList = await this.request('get_archived_manpower'); const tbody = document.querySelector('#table-users tbody'); tbody.innerHTML = '';
        if (!workerList || workerList.length === 0) { tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No archived records found.</td></tr>`; return; }

        workerList.forEach(w => {
            tbody.innerHTML += `<tr><td><b style="color:var(--text-dark);">${w.name}</b></td><td>${w.position || 'N/A'}</td><td><span class="badge" style="background:#E5E7EB; color:#4B5563;">${w.skills || 'Uncategorized'}</span></td><td style="color:var(--danger); font-weight:700;"><i class="fa-regular fa-clock"></i> ${w.archived_date || 'N/A'}</td><td><button class="btn-success-solid btn" style="height: 26px; padding: 0 10px; font-size: 0.75rem;" onclick="app.restoreManpower(${w.id})"><i class="fa-solid fa-rotate-left"></i> Restore</button></td></tr>`;
        });
    },

    backToSkills: function() { this.loadManpowerFolders(); },

    // ==========================================
    // MODULE: AWARD COST
    // ==========================================
    addAwardCost: async function() {
        const desc = document.getElementById('awd-desc').value; const amount = document.getElementById('awd-amount').value;
        if (!desc || !amount) { this.showToast("Fill in Job Description and Amount!", 'error'); return; }
        await this.request('add_award_cost', { desc, amount });
        document.getElementById('awd-desc').value = ''; document.getElementById('awd-amount').value = ''; this.loadAwardCosts(); this.showToast('Award Cost added.');
    },
    loadAwardCosts: async function() {
        const data = await this.request('get_award_costs'); const tbody = document.querySelector('#table-award-costs tbody'); if(tbody) { tbody.innerHTML = ''; if(data && Array.isArray(data)) { data.forEach(d => { let fmtAmt = parseFloat(d.amount).toLocaleString('en-US', {minimumFractionDigits: 2}); tbody.innerHTML += `<tr><td><b style="color:var(--text-dark);">${d.scope_of_work}</b></td><td style="font-weight:700; color:var(--text-dark);">₱${fmtAmt}</td><td><button class="btn-danger" style="height:26px; padding:0 8px; border-radius:4px;" onclick="app.deleteAwardCost(${d.id})"><i class="fa-solid fa-trash"></i></button></td></tr>`; }); } }
    },
    deleteAwardCost: async function(id) { if(confirm("Delete this Job Description?")) { await this.request('delete_award_cost', { id }); this.loadAwardCosts(); } },

    // ==========================================
    // MODULE: PAYROLL (SMART LEDGER SYNC)
    // ==========================================
    formatCurrencyInput: function(input) { let val = input.value.replace(/[^0-9.]/g, ''); let parts = val.split('.'); parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ","); input.value = parts.join('.'); },
    populatePayrollDatalists: async function() {
        const users = await this.request('get_active_manpower'); const datalist = document.getElementById('worker-names-list'); if(!datalist) return; datalist.innerHTML = ''; if(users && Array.isArray(users)) { users.forEach(u => { datalist.innerHTML += `<option value="${u.name}">`; }); }
        const jobs = await this.request('get_award_costs'); const jobList = document.getElementById('pay-job-list'); if(jobList) { jobList.innerHTML = ''; if(jobs && Array.isArray(jobs)) { jobs.forEach(j => { jobList.innerHTML += `<option value="${j.scope_of_work}">`; }); } }
    },
    clearPayrollForm: function() { ['pay-date', 'pay-name', 'pay-job', 'pay-award', 'pay-advance'].forEach(id => { if(document.getElementById(id)) document.getElementById(id).value = ''; }); },

    addManualPayroll: async function() {
        const date = document.getElementById('pay-date').value; const name = document.getElementById('pay-name').value; const job = document.getElementById('pay-job').value; 
        let rawAward = document.getElementById('pay-award').value.replace(/,/g, ''); let rawAdvance = document.getElementById('pay-advance').value.replace(/,/g, ''); const award = parseFloat(rawAward || 0); const advance = parseFloat(rawAdvance || 0);
        
        if (!name || !job) { this.showToast('Name and Job Description required.', 'error'); return; }

        const res = await this.request('add_payroll', { date: date || new Date().toISOString().split('T')[0], name: name, job_desc: job, award: award, advance: advance });
        if (res && res.status === 'success') { this.clearPayrollForm(); this.renderPayrollTab(); this.loadDashboard(); this.showToast('Transaction logged!'); } else { this.showToast(res ? res.message : 'Unknown database error.', 'error'); }
    },

    openEditPayrollModal: function(id, award, advance) { document.getElementById('edit-pay-id').value = id; document.getElementById('edit-pay-award').value = award ? parseFloat(award).toLocaleString('en-US', {minimumFractionDigits: 2}) : ''; document.getElementById('edit-pay-advance').value = advance ? parseFloat(advance).toLocaleString('en-US', {minimumFractionDigits: 2}) : ''; this.openModal('modal-edit-payroll'); },
    saveEditedPayroll: async function() {
        const id = document.getElementById('edit-pay-id').value; const rawAward = document.getElementById('edit-pay-award').value.replace(/,/g, ''); const rawAdvance = document.getElementById('edit-pay-advance').value.replace(/,/g, ''); const award = parseFloat(rawAward || 0); const advance = parseFloat(rawAdvance || 0);

        if(!isNaN(award) && !isNaN(advance)) { await this.request('edit_payroll_entry', { id: id, award_cost: award, cash_advance: advance }); this.closeModal('modal-edit-payroll'); this.renderPayrollTab(); this.loadDashboard(); this.showToast('Record updated. Balances recalculated.'); } else { this.showToast('Invalid numbers entered.', 'error'); }
    },

    deletePayrollEntry: async function(id) {
        if(confirm("Are you sure you want to delete this payroll record? It will automatically recalculate the balances.")) { await this.request('delete_payroll_entry', { id: id }); this.renderPayrollTab(); this.loadDashboard(); this.showToast('Record deleted. Balances updated.'); }
    },

    togglePayrollRow: function(workerNameSafe, btn) {
        const row = document.getElementById(`nested-${workerNameSafe}`); const isExpanding = !row.classList.contains('active');
        document.querySelectorAll('.nested-row').forEach(r => r.classList.remove('active')); document.querySelectorAll('.btn-toggle-details').forEach(b => b.innerHTML = '<i class="fa-solid fa-eye"></i> View Details');
        if(isExpanding) { row.classList.add('active'); if(btn) btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Hide Details'; }
    },

    renderPayrollTab: async function() {
        const tbody = document.getElementById('payroll-content'); if(!tbody) return; tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Retrieving live payroll data...</td></tr>`;
        const completedTasks = await this.request('get_all_completed_tasks'); const manualPayrolls = await this.request('get_payroll'); let aggregatedData = {};

        if(completedTasks && Array.isArray(completedTasks)) { completedTasks.forEach(task => { const worker = task.assigned_worker; if(!aggregatedData[worker]) aggregatedData[worker] = { job: task.category, txns: [] }; aggregatedData[worker].txns.push({ id: task.id, source: 'auto', project: task.project_name, blkLot: task.project_location || '-', award: parseFloat(task.award_cost) || 0, date: task.completion_date || 'N/A', sale: 0 }); }); }
        if(manualPayrolls && Array.isArray(manualPayrolls)) { manualPayrolls.forEach(entry => { if(!aggregatedData[entry.name]) aggregatedData[entry.name] = { job: entry.job_description, txns: [] }; aggregatedData[entry.name].job = entry.job_description; aggregatedData[entry.name].txns.push({ id: entry.id, source: 'manual', project: "Cash Advance Log", blkLot: entry.job_description, award: parseFloat(entry.award_cost) || 0, date: entry.pay_date, sale: parseFloat(entry.cash_advance) || 0 }); }); }

        Object.keys(aggregatedData).forEach(workerName => {
            let data = aggregatedData[workerName]; data.txns.sort((a, b) => new Date(a.date) - new Date(b.date)); 
            let runningAward = 0; let runningAdvance = 0;
            data.txns.forEach(txn => { runningAward += txn.award; runningAdvance += txn.sale; txn.overall = runningAdvance; txn.balance = runningAward - runningAdvance; });
            data.totalAward = runningAward; data.totalSale = runningAdvance; data.latestBalance = runningAward - runningAdvance;
        });

        tbody.innerHTML = ''; let grandTotal = 0; let workerCount = 0;

        if (Object.keys(aggregatedData).length === 0) { tbody.innerHTML = `<tr><td colspan="4" class="empty-state-wrapper"><i class="fa-solid fa-file-invoice-dollar"></i><p>No payroll data found. Complete tasks in workspace to sync.</p></td></tr>`; document.getElementById('payroll-total').innerText = '₱0.00'; document.getElementById('payroll-count').innerText = '0 Worker(s)'; return; }

        Object.keys(aggregatedData).forEach(workerName => {
            workerCount++; const data = aggregatedData[workerName]; grandTotal += data.latestBalance; let safeId = workerName.replace(/[^a-zA-Z0-9]/g, '-'); 
            tbody.innerHTML += `<tr><td><b style="color:var(--text-dark);">${workerName}</b></td><td><span class="badge ongoing">${data.job}</span></td><td style="font-weight: 800; color:var(--text-dark);">₱${data.totalAward.toLocaleString('en-US', {minimumFractionDigits: 2})}</td><td style="text-align: center;"><button class="btn-outline btn-toggle-details" id="btn-toggle-${safeId}" style="height: 26px; padding: 0 8px; font-size: 0.75rem;" onclick="app.togglePayrollRow('${safeId}', this)"><i class="fa-solid fa-eye"></i> View Details</button></td></tr>`;

            let nestedRows = '';
            data.txns.forEach(b => {
                let awardText = b.award > 0 ? `₱${b.award.toLocaleString('en-US', {minimumFractionDigits:2})}` : `<span style="color:#D1D5DB;">-</span>`; let saleText = b.sale > 0 ? `-₱${b.sale.toLocaleString('en-US', {minimumFractionDigits:2})}` : `<span style="color:var(--danger);">-₱0.00</span>`;
                let actionHtml = b.source === 'manual' ? `<button class="btn-outline" style="padding: 2px 6px; font-size: 0.7rem; margin-right: 2px;" onclick="app.openEditPayrollModal(${b.id}, ${b.award}, ${b.sale})"><i class="fa-solid fa-pencil"></i></button><button class="btn-danger" style="padding: 2px 6px; font-size: 0.7rem;" onclick="app.deletePayrollEntry(${b.id})"><i class="fa-solid fa-trash"></i></button>` : `<small style="color:var(--text-muted); font-size: 0.7rem;">Auto-Sync</small>`;
                nestedRows += `<tr><td><b>${b.project}</b></td><td>${b.blkLot}</td><td style="font-weight:600; color:var(--text-dark);">${awardText}</td><td style="color:var(--text-muted);">${b.date}</td><td style="color:var(--danger);">${saleText}</td><td>₱${b.overall.toLocaleString('en-US', {minimumFractionDigits:2})}</td><td style="font-weight:800; color:var(--success);">₱${b.balance.toLocaleString('en-US', {minimumFractionDigits:2})}</td><td>${actionHtml}</td></tr>`;
            });

            nestedRows += `<tr style="background-color: #FEFCE8; border-top: 2px solid var(--primary);"><td colspan="2" style="text-align: right; font-weight: 800; color: var(--text-dark);">SUMMARY TOTAL:</td><td style="font-weight: 800; color: var(--text-dark);">₱${data.totalAward.toLocaleString('en-US', {minimumFractionDigits:2})}</td><td></td><td style="font-weight: 800; color: var(--danger);">-₱${data.totalSale.toLocaleString('en-US', {minimumFractionDigits:2})}</td><td></td><td style="font-weight: 900; color: var(--success); font-size: 0.9rem;">₱${data.latestBalance.toLocaleString('en-US', {minimumFractionDigits:2})}</td><td></td></tr>`;
            tbody.innerHTML += `<tr class="nested-row" id="nested-${safeId}"><td colspan="4" style="padding: 0;"><div class="nested-table-container"><h4 style="margin-bottom: 8px; font-size: 0.8rem; color: var(--text-muted); font-weight: 700;">Award Cost Breakdown for ${workerName}</h4><table class="nested-table nested-header-red"><thead><tr><th>PROJECT</th><th>BLK & LOT</th><th>AWARD COST (₱)</th><th>DATE</th><th>SALE / CASH ADV. (₱)</th><th>OVERALL (₱)</th><th>BALANCE (₱)</th><th style="width: 70px;">ACTION</th></tr></thead><tbody>${nestedRows}</tbody></table></div></td></tr>`;
        });

        document.getElementById('payroll-total').innerText = `₱${grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2})}`; document.getElementById('payroll-count').innerText = `${workerCount} Worker(s)`;
    },

    resetDatabasePayroll: async function() { 
        if(confirm("This will close the cycle and archive ONLY the workers who have fully consumed their Award Cost (Balance = 0). Continue?")) { 
            const res = await this.request('archive_and_reset_payroll'); 
            this.renderPayrollTab(); 
            if(res && res.archived > 0) { this.showToast(`Cycle Closed. ${res.archived} completed records moved to History.`); } 
            else { this.showToast("No fully completed records (Balance = 0) to archive.", "warning"); }
        } 
    },
    
    toggleHistRow: function(idSafe, btn) {
        const row = document.getElementById(`nested-${idSafe}`); const isExpanding = !row.classList.contains('active');
        document.querySelectorAll('.nested-hist-row').forEach(r => r.classList.remove('active')); document.querySelectorAll('.btn-toggle-hist').forEach(b => b.innerHTML = '<i class="fa-solid fa-eye"></i> View Details');
        if(isExpanding) { row.classList.add('active'); if(btn) btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Hide Details'; }
    },

    viewPayrollHistory: async function() {
        document.getElementById('payroll-active-view').style.display = 'none'; document.getElementById('payroll-history-view').style.display = 'block';
        const tbody = document.getElementById('payroll-history-content'); if(tbody) tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Retrieving archive...</td></tr>`;

        const history = await this.request('get_payroll_history'); 
        if(history.length === 0) { tbody.innerHTML = `<tr><td colspan="4" class="empty-state-wrapper"><i class="fa-solid fa-folder-open"></i><p>No history found.</p></td></tr>`; return; }

        let groupedHistory = {};
        history.forEach(h => {
            let workerName = h.name; 
            if(!groupedHistory[workerName]) { groupedHistory[workerName] = { records: [], totalPayout: 0, cycles: new Set() }; }
            groupedHistory[workerName].records.push(h); groupedHistory[workerName].totalPayout += parseFloat(h.balance || h.net_pay || 0); groupedHistory[workerName].cycles.add(h.cycle_id);
        });

        tbody.innerHTML = '';
        Object.keys(groupedHistory).forEach((workerName, idx) => {
            let data = groupedHistory[workerName]; let safeId = 'hist-' + idx;
            tbody.innerHTML += `<tr><td><b style="color:var(--text-dark);"><i class="fa-solid fa-folder" style="color:var(--primary); margin-right:8px;"></i> ${workerName}</b></td><td><span class="badge ongoing">${data.cycles.size} Cycle(s)</span></td><td style="font-weight: 800; color:var(--text-dark);">₱${data.totalPayout.toLocaleString('en-US', {minimumFractionDigits: 2})}</td><td style="text-align: center;"><button class="btn-outline btn-toggle-hist" id="btn-toggle-${safeId}" style="height: 26px; padding: 0 8px; font-size: 0.75rem;" onclick="app.toggleHistRow('${safeId}', this)"><i class="fa-solid fa-eye"></i> View Details</button></td></tr>`;
            let nestedRows = '';
            data.records.forEach(r => { nestedRows += `<tr><td><small style="color:var(--text-muted); font-weight:700;">${r.cycle_id}</small></td><td>${r.pay_date}</td><td>${r.job_description || '-'}</td><td style="font-weight:600; color:var(--text-dark);">₱${parseFloat(r.award_cost || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</td><td style="color:var(--danger);">-₱${parseFloat(r.cash_advance || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</td><td style="font-weight:800; color:var(--success);">₱${parseFloat(r.balance || r.net_pay || 0).toLocaleString('en-US', {minimumFractionDigits:2})}</td></tr>`; });
            tbody.innerHTML += `<tr class="nested-row nested-hist-row" id="nested-${safeId}"><td colspan="4" style="padding: 0;"><div class="nested-table-container"><h4 style="margin-bottom: 8px; font-size: 0.8rem; color: var(--text-muted); font-weight: 700;">Archive Breakdown for ${workerName}</h4><table class="nested-table nested-header-red"><thead><tr><th>CYCLE ID</th><th>DATE PAID</th><th>JOB DESCRIPTION</th><th>AWARD COST (₱)</th><th>ADVANCE (₱)</th><th>BALANCE / PAYOUT (₱)</th></tr></thead><tbody>${nestedRows}</tbody></table></div></td></tr>`;
        });
    },

    backToActivePayroll: function() { if(document.getElementById('payroll-history-view')) document.getElementById('payroll-history-view').style.display = 'none'; if(document.getElementById('payroll-active-view')) document.getElementById('payroll-active-view').style.display = 'block'; },

    // --- CASH RELEASE MODULE ---
    loadCashRelease: async function() {
        const tbody = document.getElementById('cash-release-content'); if(!tbody) return;
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Retrieving ledger...</td></tr>`;
        
        const data = await this.request('get_cash_releases');
        let totalMat = 0; let totalLab = 0; let totalOth = 0; let grandTotal = 0;
        
        tbody.innerHTML = '';
        if(!data || data.length === 0) { 
            tbody.innerHTML = `<tr><td colspan="6" class="empty-state-wrapper"><p>No cash releases recorded yet.</p></td></tr>`; 
        } else {
            data.forEach(r => {
                let amt = parseFloat(r.amount) || 0; grandTotal += amt;
                let badgeClass = '';
                if(r.category === 'Material') { totalMat += amt; badgeClass = 'ongoing'; }
                else if(r.category === 'Labor') { totalLab += amt; badgeClass = 'success'; }
                else { totalOth += amt; badgeClass = 'pending'; } 
                let catBadge = `<span class="badge ${badgeClass}">${r.category}</span>`;
                tbody.innerHTML += `<tr><td style="color:var(--text-muted);">${r.release_date}</td><td>${catBadge}</td><td><b style="color:var(--text-dark);">${r.name}</b></td><td>${r.description || '-'}</td><td style="font-weight:800; color:var(--danger);">-₱${amt.toLocaleString('en-US', {minimumFractionDigits: 2})}</td><td><button class="btn-danger" style="height:26px; padding:0 8px; border-radius:4px;" onclick="app.deleteCashRelease(${r.id})"><i class="fa-solid fa-trash"></i></button></td></tr>`;
            });
        }

        document.getElementById('cr-total-materials').innerText = `₱${totalMat.toLocaleString('en-US', {minimumFractionDigits: 2})}`; document.getElementById('cr-total-labor').innerText = `₱${totalLab.toLocaleString('en-US', {minimumFractionDigits: 2})}`; document.getElementById('cr-total-others').innerText = `₱${totalOth.toLocaleString('en-US', {minimumFractionDigits: 2})}`; document.getElementById('cr-grand-total').innerText = `₱${grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
    },

    addCashRelease: async function() {
        const date = document.getElementById('cr-date').value; const cat = document.getElementById('cr-category').value; const name = document.getElementById('cr-name').value; const desc = document.getElementById('cr-desc').value; let rawAmount = document.getElementById('cr-amount').value.replace(/,/g, ''); const amt = parseFloat(rawAmount || 0);
        if(!date || !cat || !name || !amt) { this.showToast("Date, Category, Name, and Amount are required!", "error"); return; }
        const res = await this.request('add_cash_release', { date: date, category: cat, name: name, desc: desc, amount: amt });
        if(res.status === 'success') { document.getElementById('cr-date').value = ''; document.getElementById('cr-category').value = ''; document.getElementById('cr-name').value = ''; document.getElementById('cr-desc').value = ''; document.getElementById('cr-amount').value = ''; this.loadCashRelease(); this.loadDashboard(); this.showToast('Cash Release recorded.'); } else { this.showToast(res.message, 'error'); }
    },

    deleteCashRelease: async function(id) { if(confirm("Delete this cash release record?")) { await this.request('delete_cash_release', { id: id }); this.loadCashRelease(); this.loadDashboard(); this.showToast('Record deleted.'); } },

    // --- NTP GLOBAL ---
    loadGlobalNTP: async function() {
        const proj = await this.request('get_projects'); const select = document.getElementById('g-ntp-project');
        if (select) { select.innerHTML = '<option value="">Select Pending Project</option>'; if(Array.isArray(proj)){ proj.filter(p => (p.status || '').toLowerCase().trim() === 'pending').forEach(p => select.innerHTML += `<option value="${p.id}">${p.name} - ${p.location}</option>`); } }
        const ntps = await this.request('get_all_ntps'); const tbody = document.querySelector('#table-global-ntp tbody'); if(tbody) tbody.innerHTML = '';
        if(ntps) { ntps.forEach(n => { let fmtCost = n.award_cost ? '₱' + parseFloat(n.award_cost).toLocaleString('en-US') : 'N/A'; tbody.innerHTML += `<tr><td><b style="color:var(--text-dark);">${n.project_name}</b></td><td>${n.ntp_ticket || 'N/A'}</td><td>${n.date_received}</td><td style="font-weight:700;">${fmtCost}</td><td><b style="color:var(--danger);">${n.due_date || 'N/A'}</b></td><td>${n.acceptance_date || 'N/A'}</td><td><span style="cursor:pointer; color:var(--primary-hover); text-decoration:underline;" onclick="app.viewAttachedFile('${n.file_path}')">View PDF</span></td></tr>`; }); }
    },
    uploadGlobalNTP: async function() {
        const project_id = document.getElementById('g-ntp-project').value; const ticket = document.getElementById('g-ntp-ticket').value; const date = document.getElementById('g-ntp-date').value; const award_cost = document.getElementById('g-ntp-cost').value; const due_date = document.getElementById('g-ntp-due').value; const accept_date = document.getElementById('g-ntp-accept').value; const fileInput = document.getElementById('g-ntp-file');
        if (!project_id || !date || !due_date || fileInput.files.length === 0) { this.showToast('Project, NTP Date, Due Date, and File are required!', 'error'); return; }
        const fd = new FormData(); fd.append('project_id', project_id); fd.append('ticket', ticket); fd.append('date', date); fd.append('award_cost', award_cost); fd.append('due_date', due_date); fd.append('accept_date', accept_date); fd.append('file', fileInput.files[0]);
        const res = await this.request('upload_ntp_file', fd, true);
        if (res.status === 'success') { document.getElementById('g-ntp-ticket').value = ''; document.getElementById('g-ntp-cost').value = ''; document.getElementById('g-ntp-accept').value = ''; document.getElementById('g-ntp-file').value = ''; await this.loadGlobalNTP(); this.loadDashboard(); this.showToast("NTP Successfully uploaded!"); } else { this.showToast(res.message, 'error'); }
    }
};

window.onload = () => app.init();