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
        $stats['projects'] = $this->pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ongoing' OR status = 'pending'")->fetchColumn();
        $stats['users'] = $this->pdo->query("SELECT COUNT(*) FROM manpower WHERE is_archived = 0")->fetchColumn();
        return $stats;
    }

    private function generateDefaultChecklist($project_id) {
        $template = [
            'SOG TO ROOF BEAM' => ['Layout', 'Batter Boards', 'Excavation (Footing/Tie Beam)', 'Rebars Fabrication', 'Slab on Grade'],
            'TRUSSES AND ROOFING' => ['QDE Application', 'Trusses Installation', 'Roofing and Accessories'],
            'CHB LAYING' => ['GF and 2F Area', 'Interior/Exterior Walls', 'Electrical/Plumbing Rough-ins']
        ];
        $stmt = $this->pdo->prepare("INSERT INTO project_accomplishments (project_id, category, task_name, status, award_cost) VALUES (?, ?, ?, 'Not Started', 1500.00)");
        foreach ($template as $cat => $tasks) { foreach ($tasks as $task) { $stmt->execute([$project_id, $cat, $task]); } }
    }

    public function addProject($name, $client, $location, $desc, $foreman, $start_date) {
        $stmt = $this->pdo->prepare("INSERT INTO projects (name, client_name, location, description, foreman, start_date, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$name, $client, $location, $desc, $foreman, $start_date]);
        $projectId = $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO project_costs (project_id) VALUES (?)")->execute([$projectId]);
        $this->generateDefaultChecklist($projectId);
        return ['status' => 'success'];
    }

    public function getProjects() { return $this->pdo->query("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC); }
    public function updateProjectStatus($id, $status) { $this->pdo->prepare("UPDATE projects SET status=? WHERE id=?")->execute([$status, $id]); return ['status' => 'success']; }
    public function deleteProject($id) { 
        $this->pdo->prepare("DELETE FROM project_accomplishments WHERE project_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM material_issuances WHERE project_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]); 
        return ['status' => 'success']; 
    }

    public function getProjectData($project_id) {
        $projStmt = $this->pdo->prepare("SELECT status FROM projects WHERE id = ?"); $projStmt->execute([$project_id]); 
        $accStmt = $this->pdo->prepare("SELECT * FROM project_accomplishments WHERE project_id = ? ORDER BY id ASC"); $accStmt->execute([$project_id]);
        $issStmt = $this->pdo->prepare("SELECT i.*, inv.name as item_name, inv.unit FROM material_issuances i JOIN inventory inv ON i.item_id = inv.id WHERE i.project_id = ? ORDER BY i.id DESC"); $issStmt->execute([$project_id]);
        return ['status' => 'success', 'project_status' => $projStmt->fetchColumn(), 'checklist' => $accStmt->fetchAll(PDO::FETCH_ASSOC), 'issuances' => $issStmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function addChecklistTask($project_id, $category, $task_name) { $this->pdo->prepare("INSERT INTO project_accomplishments (project_id, category, task_name, status, award_cost) VALUES (?, ?, ?, 'Not Started', 1500.00)")->execute([$project_id, $category, $task_name]); return ['status' => 'success']; }
    public function updateChecklistStatus($task_id, $status) { $this->pdo->prepare("UPDATE project_accomplishments SET status = ?, completion_date = ? WHERE id = ?")->execute([$status, ($status === 'Completed') ? date('Y-m-d') : null, $task_id]); return ['status' => 'success']; }
    public function editChecklistTask($task_id, $task_name) { $this->pdo->prepare("UPDATE project_accomplishments SET task_name = ? WHERE id = ?")->execute([$task_name, $task_id]); return ['status' => 'success']; }
    public function updateTaskCost($task_id, $cost) { $this->pdo->prepare("UPDATE project_accomplishments SET award_cost = ? WHERE id = ?")->execute([$cost, $task_id]); return ['status' => 'success']; }
    public function deleteChecklistTask($task_id) { $this->pdo->prepare("DELETE FROM project_accomplishments WHERE id = ?")->execute([$task_id]); return ['status' => 'success']; }
    public function deleteChecklistCategory($project_id, $category) { $this->pdo->prepare("DELETE FROM project_accomplishments WHERE project_id = ? AND category = ?")->execute([$project_id, $category]); return ['status' => 'success']; }

    public function assignWorker($project_id, $category, $worker) { $this->pdo->prepare("UPDATE project_accomplishments SET assigned_worker = ? WHERE project_id = ? AND category = ?")->execute([$worker, $project_id, $category]); return ['status' => 'success']; }
    public function removeWorkerAssignment($project_id, $category) { $this->pdo->prepare("UPDATE project_accomplishments SET assigned_worker = NULL WHERE project_id = ? AND category = ?")->execute([$project_id, $category]); return ['status' => 'success']; }

    public function getSuppliers() { return $this->pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function addSupplier($name, $materials, $contact, $email) { $this->pdo->prepare("INSERT INTO suppliers (name, materials, contact, email, status) VALUES (?, ?, ?, ?, 'Active')")->execute([$name, $materials, $contact, $email]); return ['status' => 'success']; }
    public function getInventoryCategories() { return $this->pdo->query("SELECT name FROM inventory_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN); }
    public function addInventoryCategory($name) { $this->pdo->prepare("INSERT IGNORE INTO inventory_categories (name) VALUES (?)")->execute([$name]); return ['status' => 'success']; }
    public function getInventory() { return $this->pdo->query("SELECT * FROM inventory ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function addInventory($name, $category, $stock, $unit, $cost, $supplier) { $this->pdo->prepare("INSERT INTO inventory (name, category, stock, unit, unit_cost, supplier) VALUES (?, ?, ?, ?, ?, ?)")->execute([$name, $category, $stock, $unit, $cost, $supplier]); return ['status' => 'success']; }
    
    public function issueMaterial($project_id, $item_id, $qty, $receiver) {
        $stmt = $this->pdo->prepare("SELECT stock, unit_cost FROM inventory WHERE id = ?"); $stmt->execute([$item_id]); $item = $stmt->fetch();
        if(!$item || $item['stock'] < $qty) return ['status' => 'error', 'message' => 'Insufficient stock! Only ' . ($item['stock'] ?? 0) . ' left.'];
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE inventory SET stock = stock - ? WHERE id = ?")->execute([$qty, $item_id]);
            $this->pdo->prepare("INSERT INTO material_issuances (project_id, item_id, qty, unit_cost, receiver) VALUES (?, ?, ?, ?, ?)")->execute([$project_id, $item_id, $qty, $item['unit_cost'], $receiver]);
            $this->pdo->commit(); return ['status' => 'success'];
        } catch(Exception $e) { $this->pdo->rollBack(); return ['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]; }
    }

    public function getUsers() { return $this->pdo->query("SELECT m.id, m.name, m.position, m.skills, m.rate as salary, m.photo_path as photo, p.name as project_name FROM manpower m LEFT JOIN projects p ON m.project_id = p.id WHERE m.is_archived = 0 ORDER BY m.name ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function getManpowerSkills() { return $this->pdo->query("SELECT CASE WHEN skills IS NULL OR TRIM(skills) = '' THEN 'Uncategorized' ELSE TRIM(skills) END AS skill_name, COUNT(*) as worker_count FROM manpower WHERE is_archived = 0 GROUP BY skill_name ORDER BY skill_name ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function getManpowerBySkill($skill) { $stmt = $this->pdo->prepare("SELECT m.*, p.name as project_name FROM manpower m LEFT JOIN projects p ON m.project_id = p.id WHERE TRIM(m.skills) = ? AND m.is_archived = 0 ORDER BY m.name ASC"); $stmt->execute([trim($skill)]); return $stmt->fetchAll(PDO::FETCH_ASSOC); }
    
    public function addManpower($name, $skills, $position, $salary, $project_id, $photo) {
        $filePath = null;
        if ($photo && isset($photo['tmp_name']) && $photo['tmp_name']) {
            $uploadDir = '../uploads/manpower/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = 'MP_' . uniqid() . '.' . pathinfo($photo['name'], PATHINFO_EXTENSION);
            $filePath = 'uploads/manpower/' . $fileName;
            move_uploaded_file($photo['tmp_name'], '../' . $filePath);
        }
        $this->pdo->prepare("INSERT INTO manpower (name, skills, position, rate, project_id, photo_path) VALUES (?, ?, ?, ?, ?, ?)")->execute([$name, $skills, $position, $salary, $project_id ?: null, $filePath]);
        return ['status' => 'success'];
    }

    public function getAwardCosts() { return $this->pdo->query("SELECT * FROM award_costs ORDER BY scope_of_work ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function addAwardCost($desc, $amount) { $this->pdo->prepare("INSERT INTO award_costs (scope_of_work, amount) VALUES (?, ?)")->execute([$desc, $amount]); return ['status' => 'success']; }
    public function deleteAwardCost($id) { $this->pdo->prepare("DELETE FROM award_costs WHERE id = ?")->execute([$id]); return ['status' => 'success']; }

    // --- PAYROLL ---
    public function getAllCompletedTasks() { 
        try { return $this->pdo->query("SELECT a.*, p.name as project_name, p.location as project_location FROM project_accomplishments a JOIN projects p ON a.project_id = p.id WHERE a.status = 'Completed' AND a.assigned_worker IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC); } 
        catch (PDOException $e) { return []; } 
    }
    
    public function getPayroll() { 
        try { return $this->pdo->query("SELECT p.*, m.name FROM payroll p JOIN manpower m ON p.manpower_id = m.id")->fetchAll(PDO::FETCH_ASSOC); } 
        catch (PDOException $e) { return []; } 
    }
    
    public function addPayroll($date, $name, $job, $award_cost, $cash_advance) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM manpower WHERE name = ? LIMIT 1"); 
            $stmt->execute([$name]); 
            $worker = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$worker) {
                $this->pdo->prepare("INSERT INTO manpower (name, position, skills, rate) VALUES (?, 'Worker', 'Uncategorized', 500)")->execute([$name]);
                $manpower_id = $this->pdo->lastInsertId();
            } else {
                $manpower_id = $worker['id'];
            }

            // We just log the exact transaction (Award Cost or Cash Advance)
            // The magic calculation (Balance & Overall) happens in the Javascript real-time!
            $this->pdo->prepare("INSERT INTO payroll (manpower_id, pay_date, job_description, rate, days_worked, gross_pay, deductions, net_pay, award_cost, cash_advance, overall_advance, balance) 
                                 VALUES (?, ?, ?, 0, 0, 0, 0, 0, ?, ?, 0, 0)")
                      ->execute([$manpower_id, $date, $job, $award_cost, $cash_advance]);
            
            return ['status' => 'success'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'SQL Error: ' . $e->getMessage()];
        }
    }
    
    public function archiveAndResetPayroll() {
        $cycle = 'CYCLE-' . date('Ymd-His'); 
        $payroll = $this->getPayroll();
        $stmt = $this->pdo->prepare("INSERT INTO payroll_history (cycle_id, manpower_id, pay_date, job_description, rate, net_pay, award_cost, cash_advance, overall_advance, balance) VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?, ?)");
        foreach($payroll as $p) { $stmt->execute([$cycle, $p['manpower_id'], $p['pay_date'], $p['job_description'], $p['award_cost'], $p['cash_advance'], $p['overall_advance'], $p['balance']]); }
        $this->pdo->query("TRUNCATE TABLE payroll");
        return ['status' => 'success'];
    }
    
    public function getPayrollHistory() { return $this->pdo->query("SELECT h.*, m.name FROM payroll_history h JOIN manpower m ON h.manpower_id = m.id ORDER BY h.id DESC")->fetchAll(PDO::FETCH_ASSOC); }

    public function getAllNTPs() { return $this->pdo->query("SELECT n.*, p.name as project_name FROM project_ntp n JOIN projects p ON n.project_id = p.id ORDER BY n.due_date ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function uploadNTPFile($project_id, $ticket, $date, $award_cost, $due_date, $accept_date, $file) {
        $filePath = '';
        if ($file && isset($file['tmp_name']) && $file['tmp_name']) {
            $uploadDir = '../uploads/ntp/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = 'NTP_PROJ' . $project_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $filePath = 'uploads/ntp/' . $fileName;
            move_uploaded_file($file['tmp_name'], '../' . $filePath);
        }
        $this->pdo->prepare("INSERT INTO project_ntp (project_id, ntp_ticket, date_received, award_cost, due_date, acceptance_date, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$project_id, $ticket, $date, $award_cost, $due_date, $accept_date, $filePath]);
        $this->pdo->prepare("UPDATE projects SET status = 'ongoing' WHERE id = ?")->execute([$project_id]);
        return ['status' => 'success'];
    }
}
?>