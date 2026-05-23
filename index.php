<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>J.I.OJO Construction Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>

<body>

    <div id="auth-screen">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="fa-solid fa-helmet-safety" style="color: var(--primary);"></i> J.I.OJO</h2>
                <p>Enterprise Management</p>
            </div>

            <form id="auth-form">
                <div class="login-input-group">
                    <label for="auth-email">Corporate Email</label>
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" id="auth-email" class="with-icon" placeholder="admin@jiojo.com" required>
                </div>

                <div class="login-input-group">
                    <label for="auth-pass">Password</label>
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" id="auth-pass" class="with-icon" placeholder="••••••••" required>
                    <i class="fa-solid fa-eye" id="toggle-password" onclick="app.togglePassword()"></i>
                </div>

                <button type="submit" class="btn login-btn" id="auth-btn">
                    SECURE LOGIN <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                </button>
            </form>
        </div>
    </div>

    <div id="app-layout">

        <div id="sidebar-overlay" onclick="app.toggleSidebar()"></div>

        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fa-solid fa-helmet-safety"></i> J.I.OJO</h2>
                <span>Management System</span>
                <button class="mobile-close-btn" onclick="app.toggleSidebar()"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <ul class="nav-links">
                <li onclick="app.showModule('dashboard')" data-module="dashboard" class="active"><i
                        class="fa-solid fa-chart-pie"></i> Dashboard</li>
                <li onclick="app.showModule('users')" data-module="users"><i class="fa-solid fa-address-card"></i>
                    Record List</li>
                <li onclick="app.showModule('award_costs')" data-module="award_costs"><i
                        class="fa-solid fa-clipboard-list"></i> Award Cost</li>
                <li onclick="app.showModule('payroll')" data-module="payroll"><i
                        class="fa-solid fa-money-check-dollar"></i> Payroll</li>
                <li onclick="app.showModule('cash_release')" data-module="cash_release"><i
                        class="fa-solid fa-hand-holding-dollar"></i> Cash Release</li>
                <li onclick="app.showModule('global_ntp')" data-module="global_ntp"><i
                        class="fa-solid fa-file-signature"></i> Notice to Proceed</li>
                <li onclick="app.showModule('projects')" data-module="projects"><i class="fa-solid fa-city"></i>
                    Projects (Sites)</li>
                <li onclick="app.showModule('materials')" data-module="materials"><i
                        class="fa-solid fa-truck-ramp-box"></i> Material Supplier</li>
            </ul>
            <div class="logout-btn" onclick="app.logout()"><i class="fa-solid fa-power-off"
                    style="margin-right:10px;"></i> Logout</div>
        </aside>

        <main class="main-content">
            <header class="header">
                <button class="mobile-menu-btn" onclick="app.toggleSidebar()"><i class="fa-solid fa-bars"></i></button>

                <div class="breadcrumbs" id="dynamic-breadcrumbs">
                    <span class="breadcrumb-link" onclick="app.showModule('dashboard')"><i
                            class="fa-solid fa-house"></i> Home</span>
                    <i class="fa-solid fa-chevron-right separator"></i>
                    <b id="breadcrumb-current" class="active-crumb">Dashboard</b>
                </div>
                <div class="global-search-container">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="global-search-input" placeholder="Search Projects & Manpower..."
                        oninput="app.handleGlobalSearch(this.value)">
                    <button class="clear-search-btn" id="clear-search-btn" onclick="app.clearGlobalSearch()"><i
                            class="fa-solid fa-circle-xmark"></i></button>
                </div>
                <div class="header-right">
                    <div class="user-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin&background=FACC15&color=000&bold=true"
                            alt="Avatar">
                        <div class="user-info"><span class="user-name">System Admin</span><span
                                class="user-role">Project Manager</span></div>
                    </div>
                </div>
            </header>

            <section id="mod-dashboard" class="module active">
                <div class="quick-stats-grid">
                    <div class="stat-card" onclick="app.showModule('projects')">
                        <div class="stat-details">
                            <h3>Ongoing Projects</h3>
                            <h2 id="stat-projects">0</h2><span class="badge ongoing">Active Sites</span>
                        </div>
                        <div class="stat-icon"><i class="fa-solid fa-building"></i></div>
                    </div>
                    <div class="stat-card" onclick="app.showModule('users')">
                        <div class="stat-details">
                            <h3>Active Manpower</h3>
                            <h2 id="stat-users">0</h2><span class="badge success">Deployed</span>
                        </div>
                        <div class="stat-icon" style="background:var(--success-bg); color:var(--success);"><i
                                class="fa-solid fa-users"></i></div>
                    </div>
                    <div class="stat-card" onclick="app.showModule('cash_release')">
                        <div class="stat-details">
                            <h3>Total Cash Released</h3>
                            <h2 id="stat-cash-release">₱0.00</h2><span class="badge pending">Ledger</span>
                        </div>
                        <div class="stat-icon" style="background:var(--danger-bg); color:var(--danger);"><i
                                class="fa-solid fa-hand-holding-dollar"></i></div>
                    </div>
                    <div class="stat-card" onclick="app.showModule('payroll')">
                        <div class="stat-details">
                            <h3>Total Cash Advance</h3>
                            <h2 id="stat-payroll-advance">₱0.00</h2><span class="badge pending">Payroll</span>
                        </div>
                        <div class="stat-icon" style="background:var(--warning-bg); color:var(--warning);"><i
                                class="fa-solid fa-money-check-dollar"></i></div>
                    </div>
                </div>
                <div id="upcoming-deadlines-container" class="card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-calendar-days" style="color: var(--text-dark);"></i> Upcoming
                            Deadlines</h3>
                        <p>Automatically sorts tasks prioritizing urgent deadlines within the next 30 days.</p>
                    </div>
                    <div class="table-responsive">
                        <table class="sheet-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">Type</th>
                                    <th>Site / Name</th>
                                    <th>Action Needed</th>
                                    <th>Target Date</th>
                                    <th>Urgency</th>
                                </tr>
                            </thead>
                            <tbody id="deadlines-content"></tbody>
                        </table>
                    </div>
                </div>
                <div id="global-search-results" class="card" style="display:none;">
                    <div class="card-header"
                        style="border-bottom: 1px solid var(--border); padding-bottom: 16px; margin-bottom: 16px;">
                        <h3><i class="fa-solid fa-bolt" style="color: var(--primary-hover);"></i> Search Results
                        </h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">Found results
                            matching
                            "<b id="search-query-display" style="color:var(--text-dark);"></b>"</p>
                    </div>
                    <div id="search-results-content" style="display: flex; flex-direction: column; gap: 8px;">
                    </div>
                </div>
            </section>

            <section id="mod-projects" class="module">
                <div id="projects-list-view">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-layer-group"></i> Create New Project / Site</h3>
                        </div>
                        <div class="form-grid">
                            <input type="text" id="proj-name" placeholder="Project / Unit Name">
                            <input type="text" id="proj-client" placeholder="Client Name">
                            <input type="text" id="proj-loc" placeholder="Location / Phase">
                            <input type="text" id="proj-desc" placeholder="Model / Description">
                            <select id="proj-foreman">
                                <option value="">Select Site Foreman / In-Charge</option>
                            </select>
                            <input type="date" id="proj-start" title="Start Date">
                            <div style="grid-column: 1 / -1; display: flex; align-items: center; gap: 10px;">
                                <label id="file-dropzone-label" for="proj-ntp-init" class="btn-outline"
                                    style="cursor: pointer; width: 100%; justify-content: flex-start; border-style: dashed; border-width: 2px; color: var(--text-muted); border-color: #D1D5DB; font-weight: 600;">
                                    <i class="fa-solid fa-file-arrow-up"></i> <span id="file-name-display">Attach
                                        Initial NTP Document (Optional)</span>
                                </label>
                                <input type="file" id="proj-ntp-init" style="display: none;" accept=".pdf, image/*"
                                    onchange="app.handleFileSelect(this)">
                            </div>
                            <div
                                style="grid-column: 1 / -1; display: flex; justify-content: flex-end; margin-top: 4px;">
                                <button type="button" class="btn" onclick="app.submitProjectForm()"><i
                                        class="fa-solid fa-plus"></i> Create Project</button>
                            </div>
                        </div>
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <div style="position: relative; width: 250px;"><i class="fa-solid fa-magnifying-glass"
                                    style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i><input
                                    type="text" id="search-projects-table" placeholder="Search Projects..."
                                    style="width: 100%; padding-left: 32px; border-radius: 6px; border: 1px solid var(--border); height: 32px; font-size: 12px;">
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;"><span
                                    style="font-weight: 600; color: var(--text-muted); font-size: 0.8rem;">Filter:</span><select
                                    id="filter-projects"
                                    style="width: 130px; height: 32px; border-radius: 6px; border: 1px solid var(--border); font-size: 12px;">
                                    <option value="all">All Projects</option>
                                    <option value="pending">Pending</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                </select></div>
                        </div>
                        <div class="table-responsive">
                            <table class="sheet-table" id="table-projects">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Foreman</th>
                                        <th>Location</th>
                                        <th>Start Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="project-details-view" style="display:none;">
                    <div style="margin-bottom: 24px; display: flex; gap: 16px; align-items: flex-start;">
                        <button class="btn-outline" onclick="app.closeProjectDetails()"
                            style="height: 32px; padding: 0 12px; font-size: 0.75rem; color: var(--text-muted);"><i
                                class="fa-solid fa-arrow-left"></i> Back to List</button>
                        <div>
                            <h2 id="pd-name"
                                style="color: var(--text-dark); font-weight: 800; font-size: 1.8rem; margin-bottom:4px; line-height: 1;">
                                Project Name</h2>
                            <p style="color: var(--text-muted); font-size: 0.9rem;"><i class="fa-solid fa-location-dot"
                                    style="color: var(--primary-hover);"></i>
                                <span id="pd-loc-display">Location</span>
                            </p>
                        </div>
                    </div>

                    <div class="proj-tabs">
                        <div class="proj-tab active" id="tab-progress" onclick="app.switchProjectTab('progress')"><i
                                class="fa-solid fa-list-check"></i>
                            Checklist</div>
                        <div class="proj-tab" id="tab-materials" onclick="app.switchProjectTab('materials')"><i
                                class="fa-solid fa-truck-ramp-box"></i> Material Issuance</div>
                        <div class="proj-tab" id="tab-manpower" onclick="app.switchProjectTab('manpower')"><i
                                class="fa-solid fa-users-gear"></i> Manpower Assignment</div>
                    </div>

                    <div id="ptab-progress" class="proj-section active">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <div class="card"
                                style="padding: 16px 20px; flex: 1; margin-bottom: 0; margin-right: 16px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-weight: 800; color: var(--text-dark); font-size: 0.95rem;">Overall
                                        Project Progress</span><span id="proj-progress-text"
                                        style="font-weight: 900; color: var(--text-dark); font-size: 1.2rem;">0%</span>
                                </div>
                                <div class="progress-container">
                                    <div class="progress-bar" id="proj-progress-bar" style="width: 0%;"></div>
                                </div>
                            </div>
                            <div id="add-category-container">
                                <button id="btn-add-cat" class="btn" onclick="app.showAddCategoryInput()"><i
                                        class="fa-solid fa-folder-plus"></i> Add Category</button>
                                <input type="text" id="input-add-cat" class="inline-input"
                                    style="display:none; height: 32px;" placeholder="New Phase/Category Name..."
                                    onblur="app.saveNewCategoryDB(this.value)"
                                    onkeydown="if(event.key==='Enter') this.blur()">
                            </div>
                        </div>
                        <div id="checklist-grid" class="checklist-grid"></div>
                    </div>

                    <div id="ptab-materials" class="proj-section">
                        <div class="quick-stats-grid" style="grid-template-columns: 1fr 1fr;">
                            <div class="stat-card" style="padding: 16px 20px;">
                                <div class="stat-details">
                                    <h3>Materials Issued (Total)</h3>
                                    <h2 id="proj-summary-qty">0 Items</h2>
                                </div>
                                <div class="stat-icon" style="background:#EFF6FF; color:#3B82F6;"><i
                                        class="fa-solid fa-boxes-stacked"></i></div>
                            </div>
                            <div class="stat-card" style="padding: 16px 20px;">
                                <div class="stat-details">
                                    <h3>Total Cost Allocation</h3>
                                    <h2 id="proj-summary-cost" style="color:var(--success);">₱0.00</h2>
                                </div>
                                <div class="stat-icon" style="background:var(--success-bg); color:var(--success);"><i
                                        class="fa-solid fa-peso-sign"></i></div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fa-solid fa-clipboard-list" style="color:var(--text-dark);"></i>
                                    Log
                                    Material Issuance</h3>
                            </div>
                            <div class="form-grid"
                                style="grid-template-columns: 2fr 1fr 1.5fr auto; align-items:center;">
                                <select id="issue-item">
                                    <option value="">Select Inventory Item</option>
                                </select>
                                <input type="number" id="issue-qty" placeholder="Qty">
                                <input type="text" id="issue-receiver" placeholder="Receiver Name">
                                <button class="btn" onclick="app.issueMaterial()"><i class="fa-solid fa-check"></i>
                                    Issue</button>
                            </div>
                            <div style="margin-top: 24px;">
                                <h4
                                    style="font-weight: 800; font-size: 1rem; margin-bottom:12px; color:var(--text-dark);">
                                    Issuance History</h4>
                                <div class="table-responsive">
                                    <table class="sheet-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Item Issued</th>
                                                <th>Quantity</th>
                                                <th>Total Cost</th>
                                                <th>Received By</th>
                                            </tr>
                                        </thead>
                                        <tbody id="issuance-history-content"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="ptab-manpower" class="proj-section">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fa-solid fa-users-gear" style="color:var(--text-dark);"></i>
                                    Category
                                    Assignments</h3>
                                <p>Assign workers to checklist categories. Completed tasks auto-sync to their
                                    Payroll
                                    Breakdown.</p>
                            </div>
                            <div class="form-grid" style="grid-template-columns: 1fr 1fr auto; align-items:center;">
                                <select id="assign-category">
                                    <option value="">Select Category/Phase</option>
                                </select>
                                <select id="assign-worker">
                                    <option value="">Select Worker</option>
                                </select>
                                <button class="btn" onclick="app.assignWorkerToCategory()"><i
                                        class="fa-solid fa-link"></i> Assign Person</button>
                            </div>
                            <div style="margin-top: 24px;">
                                <div class="table-responsive">
                                    <table class="sheet-table">
                                        <thead>
                                            <tr>
                                                <th>Checklist Category</th>
                                                <th>Assigned Personnel</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="assignments-content"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="mod-materials" class="module">
                <div class="quick-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                    <div class="stat-card">
                        <div class="stat-details">
                            <h3>Active Suppliers</h3>
                            <h2 id="stat-active-suppliers">0</h2>
                        </div>
                        <div class="stat-icon" style="background:#D1FAE5; color:#10B981;"><i
                                class="fa-solid fa-truck-fast"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-details">
                            <h3>Pending Deliveries</h3>
                            <h2 id="stat-pending-deliveries">0</h2>
                        </div>
                        <div class="stat-icon" style="background:#FEF3C7; color:#F59E0B;"><i
                                class="fa-solid fa-clock-rotate-left"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-details">
                            <h3>Low Stock Alerts</h3>
                            <h2 id="stat-low-stock">0 Items</h2>
                        </div>
                        <div class="stat-icon" style="background:#FEE2E2; color:#EF4444;"><i
                                class="fa-solid fa-triangle-exclamation"></i></div>
                    </div>
                </div>
                <div class="card" style="padding-top: 10px;">
                    <div class="proj-tabs">
                        <div class="proj-tab active" id="tab-mat-suppliers" onclick="app.switchMatTab('suppliers')"><i
                                class="fa-solid fa-address-book"></i>
                            Suppliers</div>
                        <div class="proj-tab" id="tab-mat-inventory" onclick="app.switchMatTab('inventory')"><i
                                class="fa-solid fa-boxes-stacked"></i> Inventory</div>
                    </div>
                    <div id="mtab-suppliers" class="proj-section active">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h3 style="color: var(--text-dark); font-weight: 800; font-size: 1.1rem;">
                                    Supplier
                                    Directory</h3>
                            </div>
                            <button class="btn" onclick="app.openModal('modal-add-supplier')"><i
                                    class="fa-solid fa-plus"></i> Add New Supplier</button>
                        </div>
                        <div class="table-responsive">
                            <table class="sheet-table" id="table-suppliers">
                                <thead>
                                    <tr>
                                        <th>Supplier Name</th>
                                        <th>Items Provided</th>
                                        <th>Contact Number</th>
                                        <th>Email Address</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="suppliers-content"></tbody>
                            </table>
                        </div>
                    </div>
                    <div id="mtab-inventory" class="proj-section">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h3 style="color: var(--text-dark); font-weight: 800; font-size: 1.1rem;">Site
                                    Inventory
                                </h3>
                            </div>
                            <button class="btn" onclick="app.openModal('modal-add-stock')"><i
                                    class="fa-solid fa-box-open"></i> Add Stock</button>
                        </div>
                        <div class="table-responsive">
                            <table class="sheet-table" id="table-inventory">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Unit Cost</th>
                                        <th>Preferred Supplier</th>
                                    </tr>
                                </thead>
                                <tbody id="inventory-content"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section id="mod-users" class="module">
                <div id="manpower-folders-view">
                    <div class="card">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
                            <h3 style="font-weight:800; font-size:1.2rem;"><i class="fa-solid fa-address-card"></i>
                                Manpower Records</h3>
                        </div>
                        <div class="form-grid">
                            <input type="text" id="man-name" placeholder="Full Name" required>
                            <input type="text" id="man-skills" placeholder="Skills (e.g. Mason)">
                            <input type="text" id="man-pos" placeholder="Position (e.g. Foreman)">
                            <input type="number" id="man-salary" placeholder="Daily Rate (₱)">
                            <select id="man-project">
                                <option value="">Select Project</option>
                            </select>
                            <input type="file" id="man-photo" accept="image/*">
                            <div style="grid-column: 1/-1; display:flex; justify-content:flex-end;">
                                <button class="btn" onclick="app.addManpower()"><i class="fa-solid fa-check"></i> Add
                                    Record</button>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <h3 style="margin-bottom: 16px; font-weight:800; font-size:1.1rem;"><i
                                class="fa-solid fa-tags"></i> Skill Categories</h3>
                        <div id="skill-folders-grid" class="quick-stats-grid"></div>
                    </div>
                </div>
                <div id="manpower-table-view" style="display: none;">
                    <div class="card">
                        <button class="btn-outline" onclick="app.backToSkills()" style="margin-bottom: 24px;"><i
                                class="fa-solid fa-arrow-left"></i> Back to Categories</button>
                        <h3 id="current-skill-title"
                            style="margin-bottom: 24px; color: var(--text-dark); font-weight:800; font-size:1.4rem;">
                            Skill Group</h3>
                        <div class="table-responsive">
                            <table class="sheet-table" id="table-users">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Project Assigned</th>
                                        <th>Skills</th>
                                        <th>Position</th>
                                        <th>Rate</th>
                                        <th>Bio Data</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section id="mod-award_costs" class="module">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-clipboard-list"></i> Award Cost Masterlist</h3>
                    </div>
                    <div class="form-grid">
                        <input type="text" id="awd-desc" placeholder="Job Description (e.g. Roofing)" required>
                        <input type="number" id="awd-amount" placeholder="Amount (₱)" required>
                        <button class="btn" onclick="app.addAwardCost()"><i class="fa-solid fa-plus"></i> Add
                            Description</button>
                    </div>
                    <div class="table-responsive">
                        <table class="sheet-table" id="table-award-costs">
                            <thead>
                                <tr>
                                    <th>Job Description</th>
                                    <th>Amount (₱)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="mod-payroll" class="module">
                <div id="payroll-active-view">
                    <div class="card">
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px;">
                            <div>
                                <h3 style="color: var(--text-dark); font-weight: 800; font-size: 1.2rem;"><i
                                        class="fa-solid fa-file-invoice-dollar"
                                        style="color:var(--text-muted); margin-right:8px;"></i> Current Payroll
                                    Cycle
                                </h3>
                            </div>
                            <div style="display:flex; gap: 10px;">
                                <button class="btn-outline" onclick="app.viewPayrollHistory()"><i
                                        class="fa-solid fa-clock-rotate-left"></i> History</button>
                                <button class="btn-success-solid btn" onclick="app.resetDatabasePayroll()"><i
                                        class="fa-solid fa-check-double"></i> Close Cycle</button>
                            </div>
                        </div>

                        <div class="form-grid"
                            style="grid-template-columns: 1fr 1.5fr 2fr 1fr 1fr; align-items:center; background: #F8FAFC;">
                            <input type="date" id="pay-date" title="Pay Period End Date">
                            <input type="text" id="pay-name" list="worker-names-list" placeholder="Search Worker Name">
                            <datalist id="worker-names-list"></datalist>
                            <input type="text" id="pay-job" list="pay-job-list" placeholder="Job/Unit Description">
                            <datalist id="pay-job-list"></datalist>
                            <input type="text" id="pay-award" placeholder="Award Cost (₱)"
                                oninput="app.formatCurrencyInput(this)">
                            <input type="text" id="pay-advance" placeholder="Cash Advance (₱)"
                                oninput="app.formatCurrencyInput(this)">
                            <div
                                style="grid-column: 1/-1; display:flex; justify-content:flex-end; gap:8px; margin-top:4px;">
                                <button class="btn-outline" onclick="app.clearPayrollForm()"><i
                                        class="fa-solid fa-eraser"></i> Clear</button>
                                <button class="btn-yellow-solid btn" onclick="app.addManualPayroll()"><i
                                        class="fa-solid fa-plus"></i> Add to Payslip</button>
                            </div>
                        </div>

                        <div class="table-responsive" style="overflow: visible;">
                            <table class="sheet-table" id="table-payroll">
                                <thead>
                                    <tr>
                                        <th>NAME</th>
                                        <th>JOB DESCRIPTION</th>
                                        <th>AWARD COST (₱)</th>
                                        <th style="width: 120px; text-align: center;">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody id="payroll-content"></tbody>
                                <tfoot style="background: #F3F4F6;">
                                    <tr>
                                        <td colspan="2"
                                            style="text-align: right; font-weight: 800; color: var(--text-muted);">
                                            TOTAL
                                            (₱):</td>
                                        <td style="font-weight: 800; color: var(--text-dark); font-size: 1.1rem;"
                                            id="payroll-total">₱0.00</td>
                                        <td style="text-align: center; color: var(--text-muted); font-weight: 600;"
                                            id="payroll-count">0 Worker(s)</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="payroll-history-view" style="display: none;">
                    <div class="card">
                        <button class="btn-outline" onclick="app.backToActivePayroll()" style="margin-bottom: 24px;"><i
                                class="fa-solid fa-arrow-left"></i> Back to
                            Current</button>
                        <h3 style="margin-bottom: 24px; color: var(--text-dark); font-weight:800; font-size:1.2rem;">
                            <i class="fa-solid fa-box-archive"></i> Payroll History (Last 12 Months)
                        </h3>
                        <div class="table-responsive" style="overflow: visible;">
                            <table class="sheet-table" id="table-payroll-history">
                                <thead>
                                    <tr>
                                        <th>WORKER NAME</th>
                                        <th>TOTAL CYCLES</th>
                                        <th>TOTAL HISTORICAL PAYOUT (₱)</th>
                                        <th style="width: 120px; text-align: center;">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody id="payroll-history-content"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section id="mod-cash_release" class="module">
                <div class="quick-stats-grid" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="stat-card">
                        <div class="stat-details">
                            <h3>Total Materials</h3>
                            <h2 id="cr-total-materials">₱0.00</h2>
                        </div>
                        <div class="stat-icon" style="background:#EFF6FF; color:#3B82F6;"><i
                                class="fa-solid fa-boxes-stacked"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-details">
                            <h3>Total Labor</h3>
                            <h2 id="cr-total-labor">₱0.00</h2>
                        </div>
                        <div class="stat-icon" style="background:#FEF3C7; color:#F59E0B;"><i
                                class="fa-solid fa-users"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-details">
                            <h3>Other Expenses</h3>
                            <h2 id="cr-total-others">₱0.00</h2>
                        </div>
                        <div class="stat-icon" style="background:#FEE2E2; color:#EF4444;"><i
                                class="fa-solid fa-receipt"></i></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-details">
                            <h3>Grand Total</h3>
                            <h2 id="cr-grand-total">₱0.00</h2>
                        </div>
                        <div class="stat-icon" style="background:#D1FAE5; color:#10B981;"><i
                                class="fa-solid fa-wallet"></i></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-hand-holding-dollar" style="color:var(--text-dark);"></i> Cash
                            Release
                            Log</h3>
                        <p>Log all outgoing cash for materials, labor, and other expenses.</p>
                    </div>

                    <div class="form-grid"
                        style="grid-template-columns: 1fr 1fr 1.5fr 2fr 1.5fr auto; align-items:center;">
                        <input type="date" id="cr-date" required title="Release Date">
                        <select id="cr-category">
                            <option value="">Select Category</option>
                            <option value="Material">Material</option>
                            <option value="Labor">Labor</option>
                            <option value="Other Expenses">Other Expenses</option>
                        </select>
                        <input type="text" id="cr-name" placeholder="Receiver Name">
                        <input type="text" id="cr-desc" placeholder="Particulars / Description">
                        <input type="text" id="cr-amount" placeholder="Amount (₱)"
                            oninput="app.formatCurrencyInput(this)">
                        <button class="btn" onclick="app.addCashRelease()"><i class="fa-solid fa-plus"></i> Add
                            Record</button>
                    </div>

                    <div class="table-responsive" style="margin-top: 20px;">
                        <table class="sheet-table" id="table-cash-release">
                            <thead>
                                <tr>
                                    <th>DATE</th>
                                    <th>CATEGORY</th>
                                    <th>RECEIVER NAME</th>
                                    <th>PARTICULARS</th>
                                    <th>AMOUNT (₱)</th>
                                    <th style="width: 50px;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody id="cash-release-content"></tbody>
                        </table>
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
                        <select id="g-ntp-project">
                            <option value="">Select Pending Project</option>
                        </select>
                        <input type="text" id="g-ntp-ticket" placeholder="NTP Ticket">
                        <input type="date" id="g-ntp-date" title="NTP Date" required>
                        <input type="number" id="g-ntp-cost" placeholder="Award Cost (₱)">
                        <input type="date" id="g-ntp-due" title="Due Date" required>
                        <input type="date" id="g-ntp-accept" title="Acceptance Date">
                        <label for="g-ntp-file" class="sr-only">NTP File</label>
                        <input type="file" id="g-ntp-file" accept=".pdf, image/*">
                        <div style="grid-column: 1/-1; display:flex; justify-content:flex-end;">
                            <button class="btn" onclick="app.uploadGlobalNTP()"><i class="fa-solid fa-upload"></i>
                                Upload NTP</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="sheet-table" id="table-global-ntp">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Ticket</th>
                                    <th>NTP Date</th>
                                    <th>Award Cost</th>
                                    <th>Due Date</th>
                                    <th>Accept Date</th>
                                    <th>Document</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <div id="modal-add-supplier" class="modal-overlay" onclick="app.closeModalOnBackdrop(event, 'modal-add-supplier')"
        style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fa-solid fa-truck-field" style="color: var(--primary-hover);"></i> Add New
                    Supplier</h3>
                <button class="modal-close" onclick="app.closeModal('modal-add-supplier')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="modal-body">
                <div>
                    <label class="modal-label" for="new-sup-name">Supplier Name</label>
                    <input type="text" id="new-sup-name" placeholder="e.g. BuildRight Hardware">
                </div>

                <div>
                    <label class="modal-label" for="new-sup-materials">Items Provided</label>
                    <input type="text" id="new-sup-materials" placeholder="e.g. Cement, Rebars">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label class="modal-label" for="new-sup-contact">Contact Number</label>
                        <input type="text" id="new-sup-contact" placeholder="0917-XXX-XXXX">
                    </div>

                    <div>
                        <label class="modal-label" for="new-sup-email">Email</label>
                        <input type="email" id="new-sup-email" placeholder="contact@supplier.com">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-outline" onclick="app.closeModal('modal-add-supplier')">Cancel</button>
                <button class="btn" onclick="app.submitNewSupplier()">Save Supplier</button>
            </div>
        </div>
    </div>

    <div id="modal-add-stock" class="modal-overlay" onclick="app.closeModalOnBackdrop(event, 'modal-add-stock')"
        style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fa-solid fa-box-open" style="color: var(--primary-hover);"></i> Add Stock Item
                </h3>
                <button class="modal-close" onclick="app.closeModal('modal-add-stock')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="modal-body">
                <div>
                    <label class="modal-label" for="stock-name">Item Name</label>
                    <input type="text" id="stock-name" placeholder="e.g. Portland Cement">
                </div>

                <div>
                    <label class="modal-label" for="stock-category">Category</label>
                    <select id="stock-category" onchange="app.handleCategoryChange(this.value)"></select>
                    <input type="text" id="stock-category-new" placeholder="Type new category..."
                        style="display: none; margin-top: 8px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div>
                        <label class="modal-label" for="stock-qty">Stock</label>
                        <input type="number" id="stock-qty" placeholder="0">
                    </div>

                    <div>
                        <label class="modal-label" for="stock-unit">Unit</label>
                        <input type="text" id="stock-unit" placeholder="Bags">
                    </div>

                    <div>
                        <label class="modal-label" for="stock-cost">Unit Cost (₱)</label>
                        <input type="number" id="stock-cost" placeholder="0.00">
                    </div>
                </div>

                <div>
                    <label class="modal-label" for="stock-supplier">Preferred Supplier</label>
                    <select id="stock-supplier"></select>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-outline" onclick="app.closeModal('modal-add-stock')">Cancel</button>
                <button class="btn" onclick="app.submitNewStock()">Save Item</button>
            </div>
        </div>
    </div>

    <div id="modal-edit-payroll" class="modal-overlay" onclick="app.closeModalOnBackdrop(event, 'modal-edit-payroll')"
        style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fa-solid fa-pencil" style="color: var(--primary-hover);"></i> Edit Payroll Record
                </h3>
                <button class="modal-close" onclick="app.closeModal('modal-edit-payroll')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="edit-pay-id">

                <div>
                    <label class="modal-label" for="edit-pay-award">Award Cost (₱)</label>
                    <input type="text" id="edit-pay-award" oninput="app.formatCurrencyInput(this)">
                </div>

                <div>
                    <label class="modal-label" for="edit-pay-advance">Cash Advance (₱)</label>
                    <input type="text" id="edit-pay-advance" oninput="app.formatCurrencyInput(this)">
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-outline" onclick="app.closeModal('modal-edit-payroll')">Cancel</button>
                <button class="btn" onclick="app.saveEditedPayroll()">Save Changes</button>
            </div>
        </div>
    </div>
    <div id="resume-modal"
        style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background-color:rgba(9, 9, 11, 0.95); overflow:auto; backdrop-filter: blur(6px);">
        <span onclick="document.getElementById('resume-modal').style.display='none'"
            style="position:absolute; top:20px; right:40px; color:var(--primary); font-size:40px; font-weight:bold; cursor:pointer;">&times;</span>
        <img id="resume-img"
            style="margin:auto; display:block; max-width:80%; max-height:90vh; margin-top:5vh; border-radius: var(--radius-md); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
    </div>

    <script src="js/app.js?v=<?php echo time(); ?>"></script>
</body>

</html>