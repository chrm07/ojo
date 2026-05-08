const app = {
    currentProjectId: null,

    init: function() {
        this.checkSession();
        document.getElementById('auth-form').addEventListener('submit', (e) => { e.preventDefault(); this.handleAuth(); });
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

    checkSession: async function() { const res = await this.request('check_session'); if (res.logged_in) this.startApp(); },
    handleAuth: async function() {
        const res = await this.request('login', { email: document.getElementById('auth-email').value, password: document.getElementById('auth-pass').value });
        if (res.status === 'success') { this.startApp(); } else { alert(res.message); }
    },
    logout: async function() { await this.request('logout'); location.reload(); },

    startApp: function() {
        document.getElementById('auth-screen').style.display = 'none';
        document.getElementById('app-layout').style.display = 'flex';
        this.showModule('dashboard');
        this.fetchNotifs(); setInterval(() => this.fetchNotifs(), 10000); 
    },

    showModule: function(id) {
        // Handle Content Visibility
        document.querySelectorAll('.module').forEach(m => m.classList.remove('active'));
        document.getElementById('mod-' + id).classList.add('active');
        
        // Handle Sidebar Highlight & Breadcrumbs
        document.querySelectorAll('.nav-links li').forEach(li => li.classList.remove('active'));
        let activeLi = document.querySelector(`.nav-links li[data-module='${id}']`);
        if(activeLi) {
            activeLi.classList.add('active');
            // Clean up text content by removing the fontawesome icon logic
            let cleanTitle = activeLi.textContent.trim();
            document.getElementById('breadcrumb-current').innerText = cleanTitle;
        }

        // Module Initialization Logic
        if(id === 'dashboard') { this.loadDashboard(); this.checkBillingNotifications(); }
        if(id === 'projects') this.loadProjects();
        if(id === 'materials') { this.loadSuppliers(); this.loadGlobalMaterials(); }
        if(id === 'award_costs') this.loadAwardCosts();
        if(id === 'users') { this.loadManpowerFolders(); this.loadProjectOptionsForManpower(); }
        if(id === 'payroll') { this.backToActivePayroll(); this.loadPayroll(); this.loadManpowerNames(); }
        if(id === 'global_ntp') this.loadGlobalNTP();
    },

    loadDashboard: async function() {
        const res = await this.request('get_stats');
        if(document.getElementById('stat-projects')) document.getElementById('stat-projects').innerText = res.projects || 0;
        if(document.getElementById('stat-users')) document.getElementById('stat-users').innerText = res.users || 0;
    },

    // ==========================================
    // NOTICE TO PROCEED (GLOBAL) & BILLING NOTIFS
    // ==========================================
    checkBillingNotifications: async function() {
        const ntps = await this.request('get_all_ntps');
        const alertBox = document.getElementById('dynamic-billing-alerts');
        alertBox.innerHTML = '';
        const today = new Date();

        ntps.forEach(n => {
            if (n.due_date) {
                const dueDate = new Date(n.due_date);
                const diffTime = dueDate - today;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                if (diffDays >= 0 && diffDays <= 30) {
                    alertBox.innerHTML += `
                        <div style="background: var(--warning-light); color: var(--warning); padding: 15px; border-left: 4px solid var(--warning); margin-bottom: 20px; border-radius: 6px; font-weight: 500; font-size: 0.95rem;">
                            <i class="fa-solid fa-bell"></i> <b>READY FOR BILLING:</b> Unit ID [${n.project_name}] (Ticket: ${n.ntp_ticket || 'N/A'}). Due in ${diffDays} day(s) on ${n.due_date}.
                        </div>`;
                }
            }
        });
    },

    loadGlobalNTP: async function() {
        const proj = await this.request('get_projects');
        const select = document.getElementById('g-ntp-project');
        if (select) {
            select.innerHTML = '<option value="">Select Unit ID (Pending Project)</option>';
            proj.filter(p => p.status === 'pending').forEach(p => select.innerHTML += `<option value="${p.id}">${p.name} - ${p.location}</option>`);
        }

        const ntps = await this.request('get_all_ntps');
        const tbody = document.querySelector('#table-global-ntp tbody'); tbody.innerHTML = '';

        ntps.forEach(n => {
            let fmtCost = n.award_cost ? '₱' + parseFloat(n.award_cost).toLocaleString('en-US') : 'N/A';
            tbody.innerHTML += `<tr>
                <td><b>${n.project_name}</b></td><td>${n.ntp_ticket || 'N/A'}</td><td>${n.date_received}</td><td>${fmtCost}</td>
                <td><b style="color:var(--danger);">${n.due_date || 'N/A'}</b></td><td>${n.acceptance_date || 'N/A'}</td>
                <td><a href="${n.file_path}" target="_blank" style="color:var(--accent); font-weight:600;"><i class="fa-solid fa-file-pdf"></i> View</a></td>
                <td><button class="btn" style="background:var(--danger); padding:4px 10px; margin:0;" onclick="app.deleteGlobalNTP(${n.id}, ${n.project_id})"><i class="fa-solid fa-trash"></i></button></td>
            </tr>`;
        });
    },

    uploadGlobalNTP: async function() {
        const project_id = document.getElementById('g-ntp-project').value;
        const ticket = document.getElementById('g-ntp-ticket').value;
        const date = document.getElementById('g-ntp-date').value;
        const award_cost = document.getElementById('g-ntp-cost').value;
        const due_date = document.getElementById('g-ntp-due').value;
        const accept_date = document.getElementById('g-ntp-accept').value;
        const status = document.getElementById('g-ntp-status').value;
        const fileInput = document.getElementById('g-ntp-file');

        if (!project_id || !date || !due_date || fileInput.files.length === 0) return alert('Project, NTP Date, Due Date, and File are required!');
        
        const fd = new FormData(); 
        fd.append('project_id', project_id); fd.append('ticket', ticket); fd.append('date', date); 
        fd.append('award_cost', award_cost); fd.append('due_date', due_date); fd.append('accept_date', accept_date);
        fd.append('status', status); fd.append('file', fileInput.files[0]);
        
        const res = await this.request('upload_ntp_file', fd, true);
        if (res.status === 'success') { 
            document.getElementById('g-ntp-ticket').value = ''; document.getElementById('g-ntp-cost').value = ''; document.getElementById('g-ntp-accept').value = '';
            this.loadGlobalNTP(); alert("NTP Successfully uploaded and Project activated!");
        } else { alert("❌ " + res.message); }
    },

    deleteGlobalNTP: async function(id, project_id) {
        if(confirm("Delete this NTP? This will revert the project to Pending.")) {
            await this.request('delete_ntp_file', { id: id, project_id: project_id });
            this.loadGlobalNTP();
        }
    },

    // ==========================================
    // MATERIAL SUPPLIER (GLOBAL)
    // ==========================================
    loadGlobalMaterials: async function() {
        const mats = await this.request('get_global_materials');
        const tbody = document.querySelector('#table-global-materials tbody'); tbody.innerHTML = '';
        
        mats.forEach(m => {
            let fmtCost = parseFloat(m.unit_cost).toLocaleString('en-US', {minimumFractionDigits: 2});
            let fmtTotal = parseFloat(m.total_cost).toLocaleString('en-US', {minimumFractionDigits: 2});
            tbody.innerHTML += `<tr>
                <td><b>${m.name}</b></td><td><b style="color:var(--success);">${m.qty} ${m.unit}</b></td>
                <td>₱${fmtCost}</td><td style="font-weight:700; color:var(--danger);">₱${fmtTotal}</td>
                <td>${m.supplier_name || 'N/A'}</td><td>${m.contact_person || 'N/A'}</td>
                <td>${m.project_name || 'N/A'}</td>
            </tr>`;
        });
    },

    addSupplier: async function() {
        const name = document.getElementById('sup-name').value; const contact = document.getElementById('sup-contact').value;
        if (!name || !contact) return alert('Fill in the Supplier information!');
        await this.request('add_supplier', { name, contact });
        document.getElementById('sup-name').value = ''; document.getElementById('sup-contact').value = ''; 
        this.loadSuppliers(); this.loadGlobalMaterials();
    },

    loadSuppliers: async function() {
        const suppliers = await this.request('get_suppliers');
        const pSupplier = document.getElementById('pm-supplier');
        if(pSupplier) {
            pSupplier.innerHTML = '<option value="">Select Supplier</option>';
            suppliers.forEach(s => pSupplier.innerHTML += `<option value="${s.id}">${s.name}</option>`);
        }
    },

    // ==========================================
    // AWARD COST
    // ==========================================
    addAwardCost: async function() {
        const desc = document.getElementById('awd-desc').value; const amount = document.getElementById('awd-amount').value;
        if (!desc || !amount) return alert("Fill in Job Description and Amount!");
        await this.request('add_award_cost', { desc, amount });
        document.getElementById('awd-desc').value = ''; document.getElementById('awd-amount').value = '';
        this.loadAwardCosts();
    },

    loadAwardCosts: async function() {
        const data = await this.request('get_award_costs');
        const tbody = document.querySelector('#table-award-costs tbody');
        if(tbody) {
            tbody.innerHTML = '';
            data.forEach(d => {
                let fmtAmt = parseFloat(d.amount).toLocaleString('en-US', {minimumFractionDigits: 2});
                tbody.innerHTML += `<tr><td><b>${d.scope_of_work}</b></td><td style="font-weight:600; color:var(--dark);">₱${fmtAmt}</td>
                    <td><button class="btn" style="background:var(--danger); padding:4px 10px; margin:0;" onclick="app.deleteAwardCost(${d.id})"><i class="fa-solid fa-trash"></i></button></td></tr>`;
            });
        }
    },

    deleteAwardCost: async function(id) {
        if(confirm("Delete this Job Description?")) { await this.request('delete_award_cost', { id }); this.loadAwardCosts(); }
    },

    // ==========================================
    // PROJECTS & PROJECT WORKSPACE
    // ==========================================
    addProject: async function() {
        const name = document.getElementById('proj-name').value; const location = document.getElementById('proj-loc').value;
        const desc = document.getElementById('proj-desc').value; const start_date = document.getElementById('proj-start').value;
        if (!name || !location || !start_date) return alert('Name, Location, and Start Date are required!');
        const res = await this.request('add_project', { name, location, desc, start_date });
        if(res.status === 'error') return alert("❌ " + res.message);
        document.getElementById('proj-name').value = ''; document.getElementById('proj-loc').value = ''; document.getElementById('proj-desc').value = ''; document.getElementById('proj-start').value = '';
        this.loadProjects(); this.loadDashboard();
    },

    loadProjects: async function() {
        this.closeProjectDetails(); 
        const proj = await this.request('get_projects');
        const tbody = document.querySelector('#table-projects tbody'); if(!tbody) return; tbody.innerHTML = '';
        proj.forEach(p => {
            let statusUI = '';
            if (p.status === 'pending') { statusUI = `<span class="badge pending">Pending (Awaiting NTP)</span>`;
            } else { statusUI = `<select onchange="app.updateProjectStatus(${p.id}, this.value)" style="padding: 5px; border-radius: 4px; border: 1px solid var(--border); font-weight: 600; background: ${p.status === 'completed' ? '#f0fdf4' : '#eff6ff'}; color: ${p.status === 'completed' ? 'var(--success)' : 'var(--accent)'};"><option value="ongoing" ${p.status === 'ongoing' ? 'selected' : ''}>Ongoing</option><option value="completed" ${p.status === 'completed' ? 'selected' : ''}>Completed</option></select>`; }
            tbody.innerHTML += `<tr><td><b style="color:var(--dark);">${p.name}</b></td><td>${p.location}</td><td>${p.start_date}</td><td>${statusUI}</td><td><button class="btn" style="width:auto; padding:6px 12px; font-size:0.85rem;" onclick="app.openProjectDetails(${p.id}, '${p.name.replace(/'/g, "\\'")}', '${p.location.replace(/'/g, "\\'")}', '${p.status}')">📂 Open Workspace</button> <button class="btn" style="background:var(--danger); padding:6px 12px;" onclick="app.deleteProject(${p.id})"><i class="fa-solid fa-trash"></i></button></td></tr>`;
        });
    },

    updateProjectStatus: async function(id, status) {
        const res = await this.request('update_project_status', { id: id, status: status });
        if (res.status === 'error') { alert("❌ " + res.message); }
        this.loadProjects(); 
    },

    deleteProject: async function(id) { if(confirm("DANGER ZONE! Deleting this project will wipe all checklists, Materials, Costs, and NTP files. Are you sure?")) { await this.request('delete_project', { id: id }); this.loadProjects(); this.loadDashboard(); } },

    openProjectDetails: async function(id, name, location, status) {
        this.currentProjectId = id;
        document.getElementById('projects-list-view').style.display = 'none'; document.getElementById('project-details-view').style.display = 'block';
        document.getElementById('pd-name').innerText = name; document.getElementById('pd-loc').innerHTML = `<i class="fa-solid fa-location-dot"></i> ${location} • [${status.toUpperCase()}]`;
        this.switchProjectTab('progress'); 
        this.refreshProjectData();
    },

    closeProjectDetails: function() {
        this.currentProjectId = null;
        if(document.getElementById('projects-list-view')) document.getElementById('projects-list-view').style.display = 'block';
        if(document.getElementById('project-details-view')) document.getElementById('project-details-view').style.display = 'none';
    },

    switchProjectTab: function(tabId) {
        document.querySelectorAll('.proj-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.proj-section').forEach(s => s.classList.remove('active'));
        event.target.classList.add('active'); document.getElementById('ptab-' + tabId).classList.add('active');
    },

    refreshProjectData: async function() {
        if(!this.currentProjectId) return;
        const res = await this.request('get_project_data', { project_id: this.currentProjectId });
        if(res.status !== 'success') return;

        const hasVerifiedNTP = res.ntps.some(n => n.status === 'verified');
        const isCompleted = res.project_status === 'completed';
        const uiLockElements = ['pm-name', 'pm-qty', 'pm-unit', 'pm-cost', 'btn-add-bom', 'pc-labor', 'pc-material', 'pc-misc', 'btn-save-costs'];
        const banner = document.getElementById('ntp-warning-banner');

        if (!hasVerifiedNTP) {
            banner.style.display = 'block'; banner.style.backgroundColor = 'var(--danger-light)'; banner.style.color = 'var(--danger)'; banner.style.borderLeft = '4px solid var(--danger)';
            banner.innerHTML = '<strong><i class="fa-solid fa-triangle-exclamation"></i> Notice to Proceed Required:</strong> This project lacks a verified NTP. Execution tasks are locked.';
            uiLockElements.forEach(id => { if(document.getElementById(id)) document.getElementById(id).disabled = true; });
        } else if (isCompleted) {
            banner.style.display = 'block'; banner.style.backgroundColor = '#f0fdf4'; banner.style.color = '#15803d'; banner.style.borderLeft = '4px solid #15803d';
            banner.innerHTML = '<strong><i class="fa-solid fa-circle-check"></i> Project Completed:</strong> This project is marked as completed. Progress, BOM, and Financials are securely locked.';
            uiLockElements.forEach(id => { if(document.getElementById(id)) document.getElementById(id).disabled = true; });
        } else {
            banner.style.display = 'none';
            uiLockElements.forEach(id => { if(document.getElementById(id)) document.getElementById(id).disabled = false; });
        }

        const accTb = document.querySelector('#table-acc tbody'); accTb.innerHTML = '';
        let completedCount = 0; let totalCount = res.checklist.length;
        res.checklist.forEach((task, index) => {
            if (task.status === 'Completed') completedCount++;
            let statusColor = task.status === 'Completed' ? 'var(--success)' : (task.status === 'Ongoing' ? 'var(--accent)' : (task.status === 'Delayed' ? 'var(--danger)' : 'var(--text-muted)'));
            let statusDropdown = `<select onchange="app.updateChecklistStatus(${task.id}, this.value)" style="padding:4px 8px; border:1px solid var(--border); font-weight:600; color:${statusColor}; background:transparent;" ${(isCompleted || !hasVerifiedNTP) ? 'disabled' : ''}>
                <option value="Not Started" ${task.status === 'Not Started' ? 'selected' : ''}>Not Started</option><option value="Ongoing" ${task.status === 'Ongoing' ? 'selected' : ''}>Ongoing</option><option value="Delayed" ${task.status === 'Delayed' ? 'selected' : ''}>Delayed</option><option value="Completed" ${task.status === 'Completed' ? 'selected' : ''}>Completed</option>
            </select>`;
            let compDate = task.completion_date ? `<br><small style="color:var(--success); font-weight:500;"><i class="fa-solid fa-check"></i> ${task.completion_date}</small>` : '';
            accTb.innerHTML += `<tr><td style="color:var(--text-muted); font-size:0.8rem;">${index + 1}</td><td><b style="color:var(--dark);">${task.task_name}</b></td><td>${statusDropdown}</td><td><input type="text" value="${task.remarks || ''}" onchange="app.updateChecklistRemarks(${task.id}, this.value)" placeholder="Add notes..." style="width:100%; border:none; border-bottom:1px solid var(--border); padding:5px; background:transparent;" ${(isCompleted || !hasVerifiedNTP) ? 'disabled' : ''}></td><td>${compDate}</td></tr>`;
        });
        let percentage = totalCount > 0 ? Math.round((completedCount / totalCount) * 100) : 0;
        document.getElementById('proj-progress-bar').style.width = percentage + '%'; document.getElementById('proj-progress-text').innerText = `${percentage}% Completed`;

        // Render Materials (BOM) for this project
        const mtb = document.querySelector('#table-pm tbody'); mtb.innerHTML = '';
        res.materials.forEach(m => {
            let fmtCost = parseFloat(m.unit_cost).toLocaleString('en-US', {minimumFractionDigits: 2}); let fmtTotal = parseFloat(m.total_cost).toLocaleString('en-US', {minimumFractionDigits: 2});
            let actionHtml = isCompleted ? '<span style="color:var(--text-muted); font-size:0.8rem; font-weight:bold;"><i class="fa-solid fa-lock"></i> Locked</span>' : `<button class="btn" style="background:var(--danger); padding:4px 10px; margin:0;" onclick="app.deleteProjectMaterial(${m.id})"><i class="fa-solid fa-trash"></i></button>`;
            mtb.innerHTML += `<tr><td><b style="color:var(--dark);">${m.name}</b></td><td>${m.qty}</td><td>${m.unit}</td><td>₱${fmtCost}</td><td style="font-weight:700; color:var(--dark);">₱${fmtTotal}</td><td>${actionHtml}</td></tr>`;
        });

        if(res.costs) {
            document.getElementById('pc-labor').value = res.costs.labor_cost; document.getElementById('pc-material').value = res.costs.material_cost;
            document.getElementById('pc-misc').value = res.costs.misc_cost; 
        }
    },

    updateChecklistStatus: async function(taskId, status) {
        const res = await this.request('update_checklist_status', { task_id: taskId, status: status });
        if(res.status === 'error') return alert("❌ " + res.message);
        this.refreshProjectData();
    },

    updateChecklistRemarks: async function(taskId, remarks) {
        const res = await this.request('update_checklist_remarks', { task_id: taskId, remarks: remarks });
        if(res.status === 'error') return alert("❌ " + res.message);
    },

    addProjectMaterial: async function() {
        const name = document.getElementById('pm-name').value; const qty = document.getElementById('pm-qty').value; const unit = document.getElementById('pm-unit').value; const unit_cost = document.getElementById('pm-cost').value;
        const supplier_id = document.getElementById('pm-supplier').value;
        if (!name || !qty || !unit || !unit_cost || !supplier_id) return alert('Fill all material fields including supplier!');
        const res = await this.request('add_project_material', { project_id: this.currentProjectId, supplier_id: supplier_id, name: name, qty: qty, unit: unit, unit_cost: unit_cost });
        if (res.status === 'error') return alert("❌ " + res.message);
        document.getElementById('pm-name').value = ''; document.getElementById('pm-qty').value = ''; document.getElementById('pm-unit').value = ''; document.getElementById('pm-cost').value = '';
        this.refreshProjectData();
    },

    deleteProjectMaterial: async function(id) { if(confirm("Remove material from BOM?")) { await this.request('delete_project_material', { id }); this.refreshProjectData(); } },

    updateProjectCosts: async function() {
        const labor = document.getElementById('pc-labor').value; const material = document.getElementById('pc-material').value; const misc = document.getElementById('pc-misc').value;
        const res = await this.request('update_project_costs', { project_id: this.currentProjectId, labor, material, misc });
        if (res.status === 'error') return alert("❌ " + res.message);
        this.refreshProjectData(); alert('Financials successfully saved.');
    },

    // ==========================================
    // RECORD LIST (MANPOWER)
    // ==========================================

    loadProjectOptionsForManpower: async function() {
        const proj = await this.request('get_projects');
        const select = document.getElementById('man-project');
        if (select) {
            select.innerHTML = '<option value="">Select Project</option>';
            const activeProjects = proj.filter(p => p.status === 'ongoing' || p.status === 'pending');
            activeProjects.forEach(p => select.innerHTML += `<option value="${p.id}">${p.name} - ${p.location}</option>`);
        }
    },

    loadManpowerNames: async function() {
        const users = await this.request('get_active_manpower');
        const datalist = document.getElementById('pay-name-list');
        if (datalist) { datalist.innerHTML = ''; if (Array.isArray(users)) users.forEach(u => datalist.innerHTML += `<option value="${u.name}">`); }
    },

    addManpower: async function() {
        const name = document.getElementById('man-name').value; const skills = document.getElementById('man-skills').value;
        const position = document.getElementById('man-pos').value; const salary = document.getElementById('man-salary').value;
        const project_id = document.getElementById('man-project').value;
        const photoInput = document.getElementById('man-photo');

        if (!name || !position || !salary) return alert('Fill in Name, Position, and Salary Rate!');
        const fd = new FormData(); fd.append('name', name); fd.append('skills', skills); fd.append('position', position); fd.append('salary', salary);
        if (project_id) fd.append('project_id', project_id);
        if(photoInput.files.length > 0) fd.append('photo', photoInput.files[0]);

        const res = await this.request('add_manpower', fd, true);
        if (res.status === 'success') {
            document.getElementById('man-name').value = ''; document.getElementById('man-skills').value = '';
            document.getElementById('man-pos').value = ''; document.getElementById('man-salary').value = ''; 
            document.getElementById('man-project').value = ''; document.getElementById('man-photo').value = '';
            this.loadManpowerFolders(); this.loadManpowerNames(); this.loadDashboard();
        } else { alert("❌ Warning: " + res.message); }
    },

    loadManpowerFolders: async function() {
        document.getElementById('manpower-folders-view').style.display = 'block'; document.getElementById('manpower-table-view').style.display = 'none'; document.getElementById('manpower-archive-view').style.display = 'none';
        const response = await this.request('get_manpower_skills');
        const grid = document.getElementById('skill-folders-grid'); grid.innerHTML = '';
        if (response && response.status === 'error') { grid.innerHTML = `<p style="color: red;">Error: ${response.message}</p>`; return; }
        const skills = Array.isArray(response) ? response : [];
        if (skills.length === 0) { grid.innerHTML = '<p style="color: var(--text-muted);">No active records found.</p>'; return; }

        skills.forEach(s => {
            const skillName = s.skill_name || 'Uncategorized';
            grid.innerHTML += `
                <div class="stat-card" style="cursor:pointer;" onclick="app.openSkillFolder('${skillName}')">
                    <div class="stat-details">
                        <h3 style="font-size: 1rem; color:var(--dark); text-transform: capitalize; font-weight:600;">${skillName}</h3>
                        <span style="color:var(--text-muted); font-size:0.85rem;">${s.worker_count} Record(s)</span>
                    </div>
                    <div class="stat-icon" style="background:var(--bg-canvas); color:var(--text-muted);"><i class="fa-solid fa-folder"></i></div>
                </div>`;
        });
    },

    openSkillFolder: async function(skillName) {
        document.getElementById('manpower-folders-view').style.display = 'none'; document.getElementById('manpower-table-view').style.display = 'block'; document.getElementById('current-skill-title').innerHTML = `<i class="fa-solid fa-folder-open"></i> ${skillName} Records`;
        const response = await this.request('get_manpower_by_skill', { skill: skillName });
        const tbody = document.querySelector('#table-users tbody'); tbody.innerHTML = '';
        if (response && response.status === 'error') { tbody.innerHTML = `<tr><td colspan="7">Error: ${response.message}</td></tr>`; return; }
        const workerList = Array.isArray(response) ? response : [];
        if (workerList.length === 0) { tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;">No records found.</td></tr>`; return; }

        workerList.forEach(w => {
            let resumeButton = `<span style="color:var(--text-muted); font-size: 0.85rem;">No Bio Data</span>`;
            if (w.photo) resumeButton = `<button class="btn-outline btn-sm" onclick="app.viewResume('${w.photo}')"><i class="fa-solid fa-image"></i> View</button>`;
            tbody.innerHTML += `<tr><td><b style="color:var(--dark);">${w.name}</b> <br> <small style="color:var(--text-muted);">ID: ${w.id}</small></td><td>${w.project_name || '<small style="color:var(--text-muted);">Unassigned</small>'}</td><td>${w.skills || 'Uncategorized'}</td><td>${w.position || 'N/A'}</td><td style="font-weight:600;">₱${w.salary || 0}</td><td>${resumeButton}</td><td><button class="btn" style="background:var(--warning); width:auto; padding:5px 10px; margin:0; font-size:0.85rem;" onclick="app.archiveManpower(${w.id}, '${skillName}')"><i class="fa-solid fa-box-archive"></i></button></td></tr>`;
        });
    },

    archiveManpower: async function(id, skillName) { if(confirm("Archive this record?")) { await this.request('archive_manpower', { id: id }); this.openSkillFolder(skillName); this.loadManpowerNames(); this.loadDashboard(); } },
    loadArchivedManpower: async function() {
        document.getElementById('manpower-folders-view').style.display = 'none'; document.getElementById('manpower-table-view').style.display = 'none'; document.getElementById('manpower-archive-view').style.display = 'block';
        const response = await this.request('get_archived_manpower');
        const tbody = document.querySelector('#table-archived-users tbody'); tbody.innerHTML = '';
        const workerList = Array.isArray(response) ? response : [];
        if (workerList.length === 0) { tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No archived records found.</td></tr>`; return; }
        workerList.forEach(w => {
            tbody.innerHTML += `<tr style="background:var(--bg-canvas); color:var(--text-muted);"><td><b>${w.name}</b> <br> <small>ID: ${w.id}</small></td><td>${w.project_name || 'Unassigned'}</td><td>${w.skills || 'Uncategorized'}</td><td>${w.position || 'N/A'}</td><td>₱${w.salary || 0}</td><td><button class="btn" style="background:var(--success); width:auto; padding:5px 10px; margin:0; font-size:0.85rem;" onclick="app.restoreManpower(${w.id})"><i class="fa-solid fa-rotate-left"></i> Restore</button></td></tr>`; 
        });
    },
    restoreManpower: async function(id) { if(confirm("Restore this record?")) { await this.request('restore_manpower', { id: id }); this.loadArchivedManpower(); this.loadManpowerNames(); this.loadDashboard(); } },
    backToSkills: function() { this.loadManpowerFolders(); },
    viewResume: function(photoUrl) { document.getElementById('resume-img').src = photoUrl; document.getElementById('resume-modal').style.display = 'block'; },

    // ==========================================
    // PAYROLL 
    // ==========================================
    clearPayrollForm: function() {
        const inputs = ['pay-date', 'pay-name', 'pay-job', 'pay-days', 'pay-deduc'];
        inputs.forEach(id => { if(document.getElementById(id)) document.getElementById(id).value = ''; });
    },

    addPayroll: async function() {
        const date = document.getElementById('pay-date').value; const name = document.getElementById('pay-name').value;
        const job_desc = document.getElementById('pay-job').value; const days = document.getElementById('pay-days').value;
        const deduc = document.getElementById('pay-deduc').value || 0; 
        if (!name || !days || !date || !job_desc) return alert('Date, Name, Job Description, and Days are required!');
        const res = await this.request('add_payroll', { name: name, days: days, deductions: deduc, date: date, job_desc: job_desc });
        if(res.status === 'success') { this.clearPayrollForm(); this.loadPayroll(); } else { alert(res.message); }
    },

    loadPayroll: async function() {
        const pay = await this.request('get_payroll');
        const tbody = document.querySelector('#table-payroll tbody'); if(tbody) tbody.innerHTML = '';
        pay.forEach(p => {
            tbody.innerHTML += `<tr><td>${p.pay_date}</td><td><b style="color:var(--dark);">${p.name}</b></td><td>${p.job_description || 'N/A'}</td><td>₱${p.rate}</td><td>${p.days_worked}</td><td style="font-weight:600;">₱${p.gross_pay}</td><td style="color:var(--danger); font-weight:500;">-₱${p.deductions}</td><td style="font-weight:700; color:var(--accent);">₱${p.net_pay}</td></tr>`;
        });
    },

    resetDatabasePayroll: async function() {
        if(confirm("⚠️ DANGER: Move all current records to History and start a new Payroll Cycle?")) {
            if (prompt("Type 'RESET' to confirm:") === 'RESET') { await this.request('archive_and_reset_payroll'); this.loadPayroll(); alert("Cycle Closed. Records moved to History."); }
        }
    },

    viewPayrollHistory: async function() {
        document.getElementById('payroll-active-view').style.display = 'none'; document.getElementById('payroll-history-view').style.display = 'block';
        const history = await this.request('get_payroll_history');
        const tbody = document.querySelector('#table-payroll-history tbody');
        if(tbody) {
            tbody.innerHTML = '';
            if(history.length === 0) { tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No history found.</td></tr>`; return; }
            history.forEach(h => {
                tbody.innerHTML += `<tr><td><small style="color:var(--text-muted); font-weight:600;">${h.cycle_id}</small></td><td>${h.pay_date}</td><td><b style="color:var(--dark);">${h.name}</b></td><td>${h.rate}</td><td style="font-weight:700; color:var(--accent);">₱${h.net_pay}</td></tr>`;
            });
        }
    },

    backToActivePayroll: function() { document.getElementById('payroll-history-view').style.display = 'none'; document.getElementById('payroll-active-view').style.display = 'block'; },

    fetchNotifs: async function() {
        const notifs = await this.request('get_notifs');
        const list = document.getElementById('notif-list'); list.innerHTML = ''; let unread = 0;
        notifs.forEach(n => {
            if (n.is_read == 0) unread++;
            list.innerHTML += `<div class="notif-item ${n.is_read == 0 ? 'unread' : ''}" onclick="app.readNotif(${n.id}, this)">${n.message}</div>`;
        });
        if(document.getElementById('notif-count')) document.getElementById('notif-count').innerText = unread > 9 ? '9+' : unread;
    },

    readNotif: async function(id, el) { await this.request('mark_read', { id }); el.classList.remove('unread'); this.fetchNotifs(); },
    toggleNotifs: function() { const list = document.getElementById('notif-list'); list.style.display = list.style.display === 'block' ? 'none' : 'block'; }
};

window.onload = () => app.init();