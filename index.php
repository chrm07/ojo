<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>J.I.OJO Construction Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <div id="auth-screen">
        <div class="card" style="width: 100%; max-width: 420px; text-align:center; padding: 50px 40px; margin: 20px;">
            <h2 style="color:var(--text-dark); font-weight:800; font-size:2rem; margin-bottom:5px; letter-spacing:-0.5px;">J.I.OJO</h2>
            <p style="color:var(--text-muted); margin-bottom:30px; font-weight:500;">Enterprise Management System</p>
            <form id="auth-form">
                <input type="email" id="auth-email" placeholder="Corporate Email" required style="margin-bottom:16px;">
                <input type="password" id="auth-pass" placeholder="Password" required style="margin-bottom:24px;">
                <button type="submit" class="btn" id="auth-btn" style="width:100%;"><i class="fa-solid fa-lock"></i> Secure Login</button>
            </form>
        </div>
    </div>

    <div id="app-layout">
        
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fa-solid fa-helmet-safety"></i> J.I.OJO</h2>
                <span>Management System</span>
            </div>
            <ul class="nav-links">
                <li onclick="app.showModule('dashboard')" data-module="dashboard" class="active"><i class="fa-solid fa-chart-pie"></i> Dashboard</li>
                <li onclick="app.showModule('projects')" data-module="projects"><i class="fa-solid fa-city"></i> Projects (Sites)</li>
                <li onclick="app.showModule('materials')" data-module="materials"><i class="fa-solid fa-truck-ramp-box"></i> Material Supplier</li>
                <li onclick="app.showModule('users')" data-module="users"><i class="fa-solid fa-users-gear"></i> Record List</li>
                <li onclick="app.showModule('award_costs')" data-module="award_costs"><i class="fa-solid fa-file-invoice-dollar"></i> Award Cost</li>
                <li onclick="app.showModule('payroll')" data-module="payroll"><i class="fa-solid fa-money-check-dollar"></i> Payroll</li>
                <li onclick="app.showModule('global_ntp')" data-module="global_ntp"><i class="fa-solid fa-file-signature"></i> Notice to Proceed</li>
            </ul>
            <div class="logout-btn" onclick="app.logout()"><i class="fa-solid fa-power-off" style="margin-right:12px;"></i> Logout</div>
        </aside>

        <main class="main-content">
            <header class="header">
                <div class="breadcrumbs"><i class="fa-solid fa-house"></i> Home <i class="fa-solid fa-chevron-right" style="font-size:0.7rem; margin:0 8px; color: #CBD5E1;"></i> <b id="breadcrumb-current">Dashboard</b></div>
                <div class="header-right">
                    <div class="user-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin&background=2563eb&color=fff&bold=true" alt="Avatar">
                        <div class="user-info">
                            <span class="user-name">System Admin</span>
                            <span class="user-role">Project Manager</span>
                        </div>
                    </div>
                </div>
            </header>

            <section id="mod-dashboard" class="module active">
                <div id="dynamic-billing-alerts"></div>
                <div class="quick-stats-grid">
                    <div class="stat-card" onclick="app.showModule('projects')">
                        <div class="stat-details">
                            <h3>Ongoing Projects</h3>
                            <h2 id="stat-projects">0</h2>
                            <span class="badge ongoing">Active Sites</span>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-building"></i></div>
                    </div>
                    <div class="stat-card" onclick="app.showModule('users')">
                        <div class="stat-details">
                            <h3>Active Manpower</h3>
                            <h2 id="stat-users">0</h2>
                            <span class="badge success">Deployed</span>
                        </div>
                        <div class="stat-icon" style="background:var(--success-bg); color:var(--success);"><i class="fa-solid fa-users"></i></div>
                    </div>
                </div>
            </section>

            <section id="mod-projects" class="module">
                <div id="projects-list-view">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-layer-group"></i> Create New Project / Site</h3>
                            <p>Projects are initialized as <b>Pending</b> until an official Notice to Proceed (NTP) is verified.</p>
                        </div>
                        
                        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                            <input type="text" id="proj-name" placeholder="Project / Unit Name" required>
                            <input type="text" id="proj-client" placeholder="Client Name" required>
                            <input type="text" id="proj-loc" placeholder="Location / Phase" required>
                            <input type="text" id="proj-desc" placeholder="Model / Description">
                            
                            <select id="proj-foreman">
                                <option value="">Select Site Foreman / In-Charge</option>
                                </select>
                            
                            <input type="date" id="proj-start" required title="Start Date">
                            
                            <div style="grid-column: 1 / -1; display: flex; align-items: center; gap: 10px;">
                                <label for="proj-ntp-init" class="btn-outline" style="cursor: pointer; width: 100%; justify-content: flex-start; padding: 12px 16px; border-style: dashed; border-width: 2px;">
                                    <i class="fa-solid fa-file-arrow-up"></i> Attach Initial NTP Document (Optional)
                                </label>
                                <input type="file" id="proj-ntp-init" style="display: none;" accept=".pdf, image/*">
                            </div>

                            <div style="grid-column: 1 / -1; display: flex; justify-content: flex-end; margin-top: 10px;">
                                <button class="btn" onclick="app.addProject()" style="padding: 0 32px;">
                                    <i class="fa-solid fa-plus"></i> Create Project
                                </button>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div style="position: relative; width: 300px;">
                                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                <input type="text" id="search-projects" placeholder="Search Projects..." style="width: 100%; padding-left: 40px; height: 42px;">
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-weight: 500; color: var(--text-muted);">Filter:</span>
                                <select id="filter-projects" style="width: 150px; height: 42px;">
                                    <option value="all">All Projects</option>
                                    <option value="pending">Pending</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="sheet-table" id="table-projects">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Client</th>
                                        <th>Location</th>
                                        <th>Start Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="project-details-view" style="display:none;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px;">
                        <div>
                            <h2 id="pd-name" style="color: var(--text-dark); font-weight: 800; font-size:2.2rem; margin-bottom:8px; letter-spacing:-0.5px;">Project Name</h2>
                            <p id="pd-loc" style="color: var(--text-muted); font-size: 1.1rem; font-weight:500;"><i class="fa-solid fa-location-dot"></i> Location • Status</p>
                        </div>
                        <button class="btn-outline" onclick="app.closeProjectDetails()"><i class="fa-solid fa-arrow-left"></i> Back to List</button>
                    </div>

                    <div id="ntp-warning-banner" class="alert-banner alert-danger" style="display:none;"></div>

                    <div class="proj-tabs">
                        <div class="proj-tab active" onclick="app.switchProjectTab('progress')"><i class="fa-solid fa-list-check"></i> Checklist</div>
                        <div class="proj-tab" onclick="app.switchProjectTab('materials')"><i class="fa-solid fa-cubes"></i> Materials</div>
                        <div class="proj-tab" onclick="app.switchProjectTab('costs')"><i class="fa-solid fa-sack-dollar"></i> Financials</div>
                    </div>

                    <div id="ptab-progress" class="proj-section active">
                        <div class="card" style="padding:24px;">
                            <div style="display:flex; justify-content: space-between; margin-bottom: 10px;">
                                <span style="font-weight: 700; color:var(--text-dark); font-size:1.1rem;">Overall Completion</span>
                                <span id="proj-progress-text" style="font-weight: 800; color: var(--primary); font-size:1.1rem;">0%</span>
                            </div>
                            <div class="progress-container"><div class="progress-bar" id="proj-progress-bar"></div></div>
                        </div>
                        
                        <div class="card" style="padding:0; overflow:hidden;">
                            <div style="max-height: 600px; overflow-y: auto;">
                                <table class="sheet-table" id="table-acc" style="margin-top:0;">
                                    <thead style="position: sticky; top: 0; z-index: 2;">
                                        <tr><th style="width:50px;">#</th><th style="width:35%;">Task / Stage</th><th style="width:20%;">Status</th><th style="width:30%;">Remarks</th><th>Date</th></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="ptab-materials" class="proj-section">
                        <div class="card">
                            <div class="form-grid" style="grid-template-columns: 1.5fr 2fr 1fr 1fr 1.5fr auto;">
                                <select id="pm-supplier"><option value="">Select Supplier</option></select>
                                <input type="text" id="pm-name" placeholder="Material Needed (BOM)">
                                <input type="number" id="pm-qty" placeholder="Qty">
                                <input type="text" id="pm-unit" placeholder="Unit">
                                <input type="number" id="pm-cost" placeholder="Unit Cost (₱)">
                                <button id="btn-add-bom" class="btn" onclick="app.addProjectMaterial()"><i class="fa-solid fa-plus"></i> Add</button>
                            </div>
                            <div class="table-responsive">
                                <table class="sheet-table" id="table-pm">
                                    <thead><tr><th>Material</th><th>Qty</th><th>Unit</th><th>Unit Cost</th><th>Total Cost</th><th>Action</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div id="ptab-costs" class="proj-section">
                        <div class="card" style="max-width: 700px; padding: 40px;">
                            <h3 style="margin-bottom: 32px; color:var(--text-dark); font-weight:800; font-size:1.5rem;"><i class="fa-solid fa-calculator" style="color:var(--text-muted);"></i> Financial Breakdown</h3>
                            
                            <div style="display:flex; justify-content:space-between; margin-bottom: 24px; align-items: center;">
                                <label style="font-weight:600; font-size:1.1rem;">Labor Cost (₱):</label>
                                <input type="number" id="pc-labor" value="0" style="width: 250px; text-align:right; font-size:1.1rem; font-weight:700;">
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-bottom: 24px; align-items: center;">
                                <label style="font-weight:600; font-size:1.1rem;">Material Cost (₱): <br><small style="cursor:pointer; color:var(--primary); font-weight:700; margin-top:5px; display:inline-block;" onclick="app.fillCostFromBOM()"><i class="fa-solid fa-rotate"></i> Auto-fill</small></label>
                                <input type="number" id="pc-material" value="0" style="width: 250px; text-align:right; font-size:1.1rem; font-weight:700;">
                            </div>
                            <div style="display:flex; justify-content:space-between; margin-bottom: 32px; align-items: center;">
                                <label style="font-weight:600; font-size:1.1rem;">Misc / Other (₱):</label>
                                <input type="number" id="pc-misc" value="0" style="width: 250px; text-align:right; font-size:1.1rem; font-weight:700;">
                            </div>
                            <hr style="border-top: 1px solid var(--border); margin-bottom: 24px;">
                            <button id="btn-save-costs" class="btn" style="width: 100%; height: 56px; font-size: 1.1rem;" onclick="app.updateProjectCosts()"><i class="fa-solid fa-floppy-disk"></i> Save Financials</button>
                        </div>
                    </div>
                </div>
            </section>

            <section id="mod-materials" class="module">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-truck-ramp-box"></i> Material Supplier Masterlist</h3>
                        <p>Global view of all materials, suppliers, and pricing across all projects.</p>
                    </div>
                    <div class="form-grid">
                        <input type="text" id="sup-name" placeholder="Hardware / Supplier Name" style="flex:1;">
                        <input type="text" id="sup-contact" placeholder="Contact Person / Number" style="flex:1;">
                        <button class="btn" onclick="app.addSupplier()"><i class="fa-solid fa-user-plus"></i> Add Supplier</button>
                    </div>
                    <div class="table-responsive">
                        <table class="sheet-table" id="table-global-materials">
                            <thead><tr><th>Material Needed</th><th>Balance (Qty)</th><th>Unit Cost</th><th>Total Pricing</th><th>Supplier Name</th><th>Contact Person</th><th>Assigned Project</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="mod-users" class="module">
                <div id="manpower-folders-view">
                    <div class="card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
                            <h3 style="font-weight:800; font-size:1.5rem;"><i class="fa-solid fa-address-card"></i> Manpower Records</h3>
                            <button class="btn-outline" onclick="app.loadArchivedManpower()"><i class="fa-solid fa-box-archive"></i> View Archives</button>
                        </div>
                        <div class="form-grid">
                            <input type="text" id="man-name" placeholder="Full Name" required>
                            <input type="text" id="man-skills" placeholder="Skills (e.g. Mason)">
                            <input type="text" id="man-pos" placeholder="Position">
                            <input type="number" id="man-salary" placeholder="Daily Rate (₱)">
                            <select id="man-project"><option value="">Select Project</option></select>
                            <input type="file" id="man-photo" accept="image/*">
                            <button class="btn" onclick="app.addManpower()"><i class="fa-solid fa-check"></i> Add Record</button>
                        </div>
                    </div>
                    <div class="card">
                        <h3 style="margin-bottom: 24px; font-weight:800; font-size:1.5rem;"><i class="fa-solid fa-tags"></i> Skill Categories</h3>
                        <div id="skill-folders-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:24px;"></div>
                    </div>
                </div>
                
                <div id="manpower-table-view" style="display: none;">
                    <div class="card">
                        <button class="btn-outline" onclick="app.backToSkills()" style="margin-bottom: 24px;"><i class="fa-solid fa-arrow-left"></i> Back to Categories</button>
                        <h3 id="current-skill-title" style="margin-bottom: 24px; color: var(--text-dark); font-weight:800; font-size:1.8rem;">Skill Group</h3>
                        <div class="table-responsive">
                            <table class="sheet-table" id="table-users"><thead><tr><th>Name</th><th>Project Assigned</th><th>Skills</th><th>Position</th><th>Rate</th><th>Bio Data</th><th>Action</th></tr></thead><tbody></tbody></table>
                        </div>
                    </div>
                </div>

                <div id="manpower-archive-view" style="display: none;">
                    <div class="card">
                        <button class="btn-outline" onclick="app.backToSkills()" style="margin-bottom: 24px;"><i class="fa-solid fa-arrow-left"></i> Back to Active</button>
                        <h3 style="margin-bottom: 24px; color: var(--danger); font-weight:800; font-size:1.8rem;"><i class="fa-solid fa-box-archive"></i> Archived Records</h3>
                        <div class="table-responsive">
                            <table class="sheet-table" id="table-archived-users"><thead><tr><th>Name</th><th>Project Assigned</th><th>Skills</th><th>Position</th><th>Rate</th><th>Action</th></tr></thead><tbody></tbody></table>
                        </div>
                    </div>
                </div>
            </section>

            <section id="mod-award_costs" class="module">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-clipboard-list"></i> Award Cost Masterlist</h3>
                        <p>Standardized job descriptions and award pricing for operations.</p>
                    </div>
                    <div class="form-grid">
                        <input type="text" id="awd-desc" placeholder="Job Description (e.g. Roofing)" required>
                        <input type="number" id="awd-amount" placeholder="Amount (₱)" required>
                        <button class="btn" onclick="app.addAwardCost()"><i class="fa-solid fa-plus"></i> Add Description</button>
                    </div>
                    <div class="table-responsive">
                        <table class="sheet-table" id="table-award-costs">
                            <thead><tr><th>Job Description</th><th>Amount (₱)</th><th>Action</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="mod-payroll" class="module">
                <div id="payroll-active-view">
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
                            <h3 style="font-weight:800; font-size:1.5rem;"><i class="fa-solid fa-file-invoice-dollar"></i> Current Payroll Cycle</h3>
                            <div style="display:flex; gap: 12px;">
                                <button class="btn-outline" onclick="app.viewPayrollHistory()"><i class="fa-solid fa-clock-rotate-left"></i> History</button>
                                <button class="btn btn-success" onclick="app.resetDatabasePayroll()"><i class="fa-solid fa-check-double"></i> Close Cycle</button>
                            </div>
                        </div>
                        <div class="form-grid">
                            <input type="date" id="pay-date" required>
                            <input type="text" id="pay-name" list="pay-name-list" placeholder="Search Worker" required>
                            <datalist id="pay-name-list"></datalist>
                            <input type="text" id="pay-job" placeholder="Job Description" required>
                            <input type="number" step="0.5" id="pay-days" placeholder="Days" required>
                            <input type="number" id="pay-deduc" placeholder="Deductions" value="">
                            <button class="btn" onclick="app.addPayroll()"><i class="fa-solid fa-plus"></i> Add to Payslip</button>
                            <button type="button" class="btn-outline" onclick="app.clearPayrollForm()"><i class="fa-solid fa-eraser"></i> Clear</button>
                        </div>
                        <div class="table-responsive">
                            <table class="sheet-table" id="table-payroll">
                                <thead><tr><th>Date</th><th>Name</th><th>Job Desc.</th><th>Rate</th><th>Days</th><th>Gross</th><th>Deductions</th><th>Net Pay</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="payroll-history-view" style="display: none;">
                    <div class="card">
                        <button class="btn-outline" onclick="app.backToActivePayroll()" style="margin-bottom: 24px;"><i class="fa-solid fa-arrow-left"></i> Back to Current</button>
                        <h3 style="margin-bottom: 24px; color: var(--text-dark); font-weight:800; font-size:1.5rem;"><i class="fa-solid fa-box-archive"></i> Payroll History</h3>
                        <div class="table-responsive">
                            <table class="sheet-table" id="table-payroll-history">
                                <thead><tr><th>Cycle ID</th><th>Date Paid</th><th>Name</th><th>Job Description</th><th>Rate</th><th>Net Pay</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section id="mod-global_ntp" class="module">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-file-signature"></i> Notice to Proceed (NTP)</h3>
                        <p>Upload verified NTPs here to unlock a Pending project's execution phases.</p>
                    </div>
                    <div class="form-grid">
                        <select id="g-ntp-project"><option value="">Select Pending Project</option></select>
                        <input type="text" id="g-ntp-ticket" placeholder="NTP Ticket">
                        <input type="date" id="g-ntp-date" title="NTP Date" required>
                        <input type="number" id="g-ntp-cost" placeholder="Award Cost (₱)">
                        <input type="date" id="g-ntp-due" title="Due Date" required>
                        <input type="date" id="g-ntp-accept" title="Acceptance Date">
                        <input type="hidden" id="g-ntp-status" value="verified">
                        <input type="file" id="g-ntp-file" accept=".pdf, image/*">
                        <button class="btn" onclick="app.uploadGlobalNTP()"><i class="fa-solid fa-upload"></i> Upload</button>
                    </div>

                    <div class="table-responsive">
                        <table class="sheet-table" id="table-global-ntp">
                            <thead><tr><th>Project</th><th>Ticket</th><th>NTP Date</th><th>Award Cost</th><th>Due Date</th><th>Accept Date</th><th>Document</th><th>Action</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <div id="resume-modal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background-color:rgba(15, 23, 42, 0.9); overflow:auto; backdrop-filter: blur(4px);">
        <span onclick="document.getElementById('resume-modal').style.display='none'" style="position:absolute; top:20px; right:40px; color:white; font-size:40px; font-weight:bold; cursor:pointer;">&times;</span>
        <img id="resume-img" style="margin:auto; display:block; max-width:80%; max-height:90vh; margin-top:5vh; border-radius: var(--radius-md); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
    </div>

    <script src="js/app.js"></script>
</body>
</html>