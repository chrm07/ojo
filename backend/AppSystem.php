<?php
class ConstructionSystem {
    private $pdo;

    public function __construct($pdo) { $this->pdo = $pdo; }

    public function login($email, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['user_id'] = $admin['id']; $_SESSION['role'] = 'admin';
            return ['status' => 'success', 'role' => 'admin'];
        }
        return ['status' => 'error', 'message' => 'Invalid credentials.'];
    }

    public function getDashboardStats() {
        $stats = [];
        $stats['projects'] = $this->pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing'")->fetchColumn();
        $stats['users'] = $this->pdo->query("SELECT COUNT(*) FROM manpower WHERE is_archived = 0")->fetchColumn();
        return $stats;
    }

    private function hasApprovedNTP($project_id) {
        if (empty($project_id)) return true; 
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM project_ntp WHERE project_id = ? AND status = 'verified'");
        $stmt->execute([$project_id]); return $stmt->fetchColumn() > 0;
    }

    private function hasAnyNTP($project_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM project_ntp WHERE project_id = ?");
        $stmt->execute([$project_id]); return $stmt->fetchColumn() > 0;
    }

    private function isProjectCompleted($project_id) {
        $stmt = $this->pdo->prepare("SELECT status FROM projects WHERE id = ?");
        $stmt->execute([$project_id]); return $stmt->fetchColumn() === 'completed';
    }

    private function checkAndAutoActivateProject($project_id) {
        if ($this->hasApprovedNTP($project_id)) {
            $this->pdo->prepare("UPDATE projects SET status = 'ongoing' WHERE id = ? AND status = 'pending'")->execute([$project_id]);
        } else {
            $this->pdo->prepare("UPDATE projects SET status = 'pending' WHERE id = ? AND status = 'ongoing'")->execute([$project_id]);
        }
    }

    private function generateDefaultChecklist($project_id) {
        $tasks = [
            'LAYOUT & EXCAVATION', 'FOOTING & PEDESTAL', 'TERMITE CONTROL', 'TIE BEAM', 'GF PLUMBING LINES', 'GF ELECTRICAL COND.', 
            'SLAB ON GRADE', 'GF COLUMN', '2F PLUMBING LINES', '2F ELECTRICAL CONDUITS', '2F BEAM & SLAB', 'ROOF BEAM', 'STAIR CONSTRUCTION',
            'CHB (GF EXTERIOR)', 'CHB (GF INTERIOR)', 'CHB (2F EXTERIOR)', 'CHB (2F INTERIOR)', 'APEX CHB', 'FALSE COLUMN CHB',
            'PLASTERING (GF EXTERIOR)', 'PLASTERING (GF INTERIOR)', 'PLASTERING (2F EXTERIOR)', 'PLASTERING (2F INTERIOR)', 
            'WINDOW OP. & MOULDINGS', 'BELT BAND MOULDINGS', 'LOWER BELT MOULDINGS', 'KEYSTONE MOULDINGS', 'SANDBLASTING', 'FALSE COLUMN PLAST.',
            'TRUSSES', 'ROOF UNDERSHEETING', 'ROOF TILES', 'LOWER ROOF', 'TUBULAR DESIGNS',
            'GF FURRING CHANNELS', '2F FURRING CHANNELS', 'PLUMBING LINES', 'ELECTRICAL COND.', 'GF CEILING', '2F CEILING', 'EXT. CEILING EAVES', 'CARPORT CEILING', 'CEILING VENTS',
            'JAMBS INSTALLATION', 'DOORS INSTALLATION', 'STEELDOOR INSTALLATION', 'LOCKSET', 'KITCHEN COUNTER CONST.', 'KITCHEN CABINET',
            'GF TILES', '2F TILES', 'STAIR TILES', 'GF CR TILES', '2F / MBR CR TILES', 'CR LAVATORY PEDESTALS', 'KIT. COUNTER TILES', 'TILE GROUTING', 'DECOSTONE',
            'STAIR RAILINGS', 'WOODEN HANDRAIL', 'BALCONY GRILL', 'STORAGE DOOR',
            'ELECT. OUTLET & SWITCH', 'BULBS & RECEPTACLES', 'PANEL BOX', 'CIRCUIT BREAKERS', 'CASTLE LAMPS', 'WEATHERPROOF COVERS',
            'BOWL & LAVATORIES', 'SHOWER ', 'KITCHEN SINK', 'FLOOR DRAINS', 'HOSE BIBB',
            'GF SKIMCOAT (INT)', '2F SKIMCOAT (INT)', 'MOULDINGS SKIMCOAT', 'GF INTERIOR PAINT', '2F INTERIOR PAINT', 'CEILING PAINT', 'DOORS/JAMB PAINT', 'GF EXTERIOR PAINT', '2F EXTERIOR PAINT', 'DECOSTONE PAINT',
            'SLIDING WINDOWS', 'SLIDING DOOR', 'SEPTIC TANK', 'CATCH BASINS', 'AREA DRAIN TAPPING', 'LOT LEVELING', 'CLEANING & CLEARING', 'COMMISION & TESTING'
        ];
        $stmt = $this->pdo->prepare("INSERT INTO project_accomplishments (project_id, task_name) VALUES (?, ?)");
        foreach ($tasks as $task) { $stmt->execute([$project_id, $task]); }
    }

    public function addProject($name, $location, $desc, $start_date) {
        $stmt = $this->pdo->prepare("INSERT INTO projects (name, location, description, start_date, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$name, $location, $desc, $start_date]);
        $projectId = $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO project_costs (project_id) VALUES (?)")->execute([$projectId]);
        $this->generateDefaultChecklist($projectId);
        return ['status' => 'success'];
    }

    public function getProjects() { return $this->pdo->query("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC); }
    public function updateProjectStatus($id, $status) {
        if ($status === 'ongoing' && !$this->hasApprovedNTP($id)) return ['status' => 'error', 'message' => 'Cannot change status to Ongoing: Verified NTP required.'];
        $this->pdo->prepare("UPDATE projects SET status=? WHERE id=?")->execute([$status, $id]); return ['status' => 'success'];
    }
    public function deleteProject($id) { $this->pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]); return ['status' => 'success']; }

    public function updateChecklistStatus($task_id, $status) {
        $stmt = $this->pdo->prepare("SELECT project_id FROM project_accomplishments WHERE id = ?"); $stmt->execute([$task_id]); $project_id = $stmt->fetchColumn();
        if ($this->isProjectCompleted($project_id)) return ['status' => 'error', 'message' => 'Project is locked.'];
        if (!$this->hasApprovedNTP($project_id)) return ['status' => 'error', 'message' => 'Verified NTP required.'];
        $completion_date = ($status === 'Completed') ? date('Y-m-d') : null;
        $this->pdo->prepare("UPDATE project_accomplishments SET status = ?, completion_date = ? WHERE id = ?")->execute([$status, $completion_date, $task_id]);
        return ['status' => 'success'];
    }

    public function updateChecklistRemarks($task_id, $remarks) {
        $stmt = $this->pdo->prepare("SELECT project_id FROM project_accomplishments WHERE id = ?"); $stmt->execute([$task_id]); $project_id = $stmt->fetchColumn();
        if ($this->isProjectCompleted($project_id)) return ['status' => 'error', 'message' => 'Project is locked.'];
        $this->pdo->prepare("UPDATE project_accomplishments SET remarks = ? WHERE id = ?")->execute([$remarks, $task_id]);
        return ['status' => 'success'];
    }

    public function getProjectData($project_id) {
        $projStmt = $this->pdo->prepare("SELECT status FROM projects WHERE id = ?"); $projStmt->execute([$project_id]); $project_status = $projStmt->fetchColumn();
        $matsStmt = $this->pdo->prepare("SELECT * FROM project_materials WHERE project_id = ? ORDER BY id DESC"); $matsStmt->execute([$project_id]);
        $costsStmt = $this->pdo->prepare("SELECT * FROM project_costs WHERE project_id = ?"); $costsStmt->execute([$project_id]);
        $ntpStmt = $this->pdo->prepare("SELECT * FROM project_ntp WHERE project_id = ? LIMIT 1"); $ntpStmt->execute([$project_id]);
        $accStmt = $this->pdo->prepare("SELECT * FROM project_accomplishments WHERE project_id = ? ORDER BY id ASC"); $accStmt->execute([$project_id]);
        return ['status' => 'success', 'project_status' => $project_status, 'materials' => $matsStmt->fetchAll(PDO::FETCH_ASSOC), 'costs' => $costsStmt->fetch(PDO::FETCH_ASSOC), 'ntps' => $ntpStmt->fetchAll(PDO::FETCH_ASSOC), 'checklist' => $accStmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    // --- GLOBAL NTP MODULE ---
    public function getAllNTPs() {
        return $this->pdo->query("
            SELECT n.*, p.name as project_name 
            FROM project_ntp n 
            JOIN projects p ON n.project_id = p.id 
            ORDER BY n.due_date ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function uploadNTPFile($project_id, $ticket, $date, $award_cost, $due_date, $accept_date, $status, $file) {
        if ($this->isProjectCompleted($project_id)) return ['status' => 'error', 'message' => 'Project is locked.'];
        if ($this->hasAnyNTP($project_id)) return ['status' => 'error', 'message' => 'An NTP already exists for this project.'];

        $projStmt = $this->pdo->prepare("SELECT status FROM projects WHERE id = ?"); $projStmt->execute([$project_id]);
        if ($projStmt->fetchColumn() !== 'pending') return ['status' => 'error', 'message' => 'NTPs can only be uploaded for Pending projects.'];
        if (!$file || $file['error'] != 0) return ['status' => 'error', 'message' => 'No valid file uploaded.'];
        
        $uploadDir = __DIR__ . '/../uploads/ntp/'; if (!file_exists($uploadDir)) { mkdir($uploadDir, 0777, true); }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) return ['status' => 'error', 'message' => 'Invalid file type. Only PDF, JPG, and PNG are allowed.'];

        $filename = 'NTP_PROJ' . $project_id . '_' . time() . '.' . $ext; $dest = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $path = 'uploads/ntp/' . $filename;
            $this->pdo->prepare("INSERT INTO project_ntp (project_id, ntp_ticket, file_path, date_received, award_cost, due_date, acceptance_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([$project_id, $ticket, $path, $date, $award_cost, $due_date, $accept_date, $status]);
            $this->checkAndAutoActivateProject($project_id);
            return ['status' => 'success'];
        }
        return ['status' => 'error', 'message' => 'Failed to save file. Check folder permissions.'];
    }

    public function deleteNTPFile($id, $project_id) { 
        if ($this->isProjectCompleted($project_id)) return ['status' => 'error', 'message' => 'Project is locked.'];
        $this->pdo->prepare("DELETE FROM project_ntp WHERE id = ?")->execute([$id]); 
        $this->checkAndAutoActivateProject($project_id); return ['status' => 'success']; 
    }

    // --- GLOBAL MATERIALS & SUPPLIERS MODULE ---
    public function getGlobalMaterials() {
        return $this->pdo->query("
            SELECT m.*, p.name as project_name, s.name as supplier_name, s.contact as contact_person 
            FROM project_materials m 
            LEFT JOIN projects p ON m.project_id = p.id 
            LEFT JOIN suppliers s ON m.supplier_id = s.id 
            ORDER BY m.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addSupplier($name, $contact) { $this->pdo->prepare("INSERT INTO suppliers (name, contact) VALUES (?, ?)")->execute([$name, $contact]); return ['status' => 'success']; }
    public function getSuppliers() { return $this->pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); }

    public function addProjectMaterial($project_id, $supplier_id, $name, $qty, $unit, $unit_cost) {
        if ($this->isProjectCompleted($project_id)) return ['status' => 'error', 'message' => 'Project is locked.'];
        if (!$this->hasApprovedNTP($project_id)) return ['status' => 'error', 'message' => 'Verified NTP required.'];

        $total_cost = floatval($qty) * floatval($unit_cost);
        $this->pdo->prepare("INSERT INTO project_materials (project_id, supplier_id, name, qty, unit, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$project_id, $supplier_id, $name, $qty, $unit, $unit_cost, $total_cost]);
        return ['status' => 'success'];
    }

    public function deleteProjectMaterial($id) { 
        $stmt = $this->pdo->prepare("SELECT project_id FROM project_materials WHERE id = ?"); $stmt->execute([$id]);
        if ($this->isProjectCompleted($stmt->fetchColumn())) return ['status' => 'error', 'message' => 'Project is locked.'];
        $this->pdo->prepare("DELETE FROM project_materials WHERE id = ?")->execute([$id]); return ['status' => 'success']; 
    }

    public function updateProjectCosts($project_id, $labor, $material, $misc) {
        if ($this->isProjectCompleted($project_id)) return ['status' => 'error', 'message' => 'Project is locked.'];
        $total = floatval($labor) + floatval($material) + floatval($misc);
        $this->pdo->prepare("UPDATE project_costs SET labor_cost=?, material_cost=?, misc_cost=?, total_cost=? WHERE project_id=?")->execute([$labor, $material, $misc, $total, $project_id]);
        return ['status' => 'success'];
    }

    // --- GLOBAL AWARD COSTS (Job Descriptions) ---
    public function addAwardCost($desc, $amount) {
        $this->pdo->prepare("INSERT INTO standard_labor_costs (scope_of_work, model_type, amount) VALUES (?, 'General', ?)")->execute([$desc, $amount]);
        return ['status' => 'success'];
    }

    public function getAwardCosts() {
        return $this->pdo->query("SELECT * FROM standard_labor_costs ORDER BY scope_of_work ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteAwardCost($id) {
        $this->pdo->prepare("DELETE FROM standard_labor_costs WHERE id = ?")->execute([$id]); return ['status' => 'success'];
    }

    // --- MANPOWER (RECORD LIST) ---
    public function addManpower($name, $position, $skills, $salary, $project_id, $photoFile) { 
        if (!empty($project_id) && !$this->hasApprovedNTP($project_id)) return ['status' => 'error', 'message' => 'Verified NTP required for this project.'];

        $photoPath = null;
        if ($photoFile && $photoFile['error'] == 0) {
            $uploadDir = __DIR__ . '/../uploads/manpower/'; if (!file_exists($uploadDir)) { mkdir($uploadDir, 0777, true); }
            $ext = strtolower(pathinfo($photoFile['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) return ['status' => 'error', 'message' => 'Invalid image type.'];

            $filename = 'MP_' . uniqid() . '.' . $ext; $dest = $uploadDir . $filename;
            if (move_uploaded_file($photoFile['tmp_name'], $dest)) { $photoPath = 'uploads/manpower/' . $filename; } else { return ['status' => 'error', 'message' => 'Failed to save image.']; }
        }

        $salary = empty($salary) ? 0 : $salary; $project_id = empty($project_id) ? null : $project_id;
        $this->pdo->prepare("INSERT INTO manpower (project_id, name, skills, position, rate, photo_path) VALUES (?, ?, ?, ?, ?, ?)")->execute([$project_id, $name, $skills, $position, $salary, $photoPath]); 
        return ['status' => 'success']; 
    }
    
    public function getUsers() { return $this->pdo->query("SELECT m.id, m.name, m.position, m.skills, m.rate as salary, m.photo_path as photo, p.name as project_name FROM manpower m LEFT JOIN projects p ON m.project_id = p.id WHERE m.is_archived = 0 ORDER BY m.name ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function getManpowerSkills() { return $this->pdo->query("SELECT CASE WHEN skills IS NULL OR TRIM(skills) = '' THEN 'Uncategorized' ELSE TRIM(skills) END AS skill_name, COUNT(*) as worker_count FROM manpower WHERE is_archived = 0 GROUP BY skill_name ORDER BY skill_name ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function getManpowerBySkill($skill) { 
        if ($skill === 'Uncategorized') { $stmt = $this->pdo->prepare("SELECT m.id, m.name, m.position, m.skills, m.rate as salary, m.photo_path as photo, p.name as project_name FROM manpower m LEFT JOIN projects p ON m.project_id = p.id WHERE (TRIM(m.skills) = '' OR m.skills IS NULL) AND m.is_archived = 0 ORDER BY m.name ASC"); $stmt->execute(); 
        } else { $stmt = $this->pdo->prepare("SELECT m.id, m.name, m.position, m.skills, m.rate as salary, m.photo_path as photo, p.name as project_name FROM manpower m LEFT JOIN projects p ON m.project_id = p.id WHERE TRIM(m.skills) = ? AND m.is_archived = 0 ORDER BY m.name ASC"); $stmt->execute([trim($skill)]); } 
        return $stmt->fetchAll(PDO::FETCH_ASSOC); 
    }
    public function archiveManpower($id) { $this->pdo->prepare("UPDATE manpower SET is_archived = 1 WHERE id = ?")->execute([$id]); return ['status' => 'success']; }
    public function restoreManpower($id) { $this->pdo->prepare("UPDATE manpower SET is_archived = 0 WHERE id = ?")->execute([$id]); return ['status' => 'success']; }
    public function getArchivedManpower() { return $this->pdo->query("SELECT m.id, m.name, m.position, m.skills, m.rate as salary, m.photo_path as photo, p.name as project_name FROM manpower m LEFT JOIN projects p ON m.project_id = p.id WHERE m.is_archived = 1 ORDER BY m.name ASC")->fetchAll(PDO::FETCH_ASSOC); }
    
    // --- PAYROLL MODULE ---
    public function addPayroll($name, $daysWorked, $deductions, $date, $job_desc) {
        $stmt = $this->pdo->prepare("SELECT id, name, rate FROM manpower WHERE name = ? AND is_archived = 0 LIMIT 1"); $stmt->execute([trim($name)]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $gross = floatval($user['rate']) * floatval($daysWorked); $net = $gross - floatval($deductions);
            $this->pdo->prepare("INSERT INTO payroll (manpower_id, days_worked, rate, job_description, deductions, net_pay, pay_date) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$user['id'], $daysWorked, $user['rate'], $job_desc, $deductions, $net, $date]);
            return ['status' => 'success'];
        }
        return ['status' => 'error', 'message' => 'Name not found in active manpower records.'];
    }
    
    public function getPayroll() { return $this->pdo->query("SELECT p.manpower_id as user_id, m.name, p.rate, p.job_description, p.days_worked, (p.rate * p.days_worked) as gross_pay, p.deductions, p.net_pay, p.pay_date FROM payroll p JOIN manpower m ON p.manpower_id = m.id ORDER BY p.pay_date DESC")->fetchAll(PDO::FETCH_ASSOC); }
    public function archiveAndResetPayroll() { $cycleId = 'CYCLE_' . date('Ymd_His'); $this->pdo->query("INSERT INTO payroll_history (cycle_id, manpower_id, days_worked, rate, deductions, net_pay, pay_date) SELECT '$cycleId', manpower_id, days_worked, rate, deductions, net_pay, pay_date FROM payroll"); $this->pdo->query("TRUNCATE TABLE payroll"); return ['status' => 'success']; }
    public function getPayrollHistory() { return $this->pdo->query("SELECT ph.cycle_id, m.name, ph.days_worked, ph.rate, ph.deductions, ph.net_pay, ph.pay_date, ph.archived_at FROM payroll_history ph JOIN manpower m ON ph.manpower_id = m.id ORDER BY ph.archived_at DESC")->fetchAll(PDO::FETCH_ASSOC); }

    public function getNotifications() { return $this->pdo->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC); }
    public function markNotifRead($id) { $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$id]); }
}
?>