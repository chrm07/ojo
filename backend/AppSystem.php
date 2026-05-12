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

    private function generateDefaultChecklist($project_id) {
        $template = [
            'SOG TO ROOF BEAM' => ['Layout', 'Batter Boards', 'Excavation (Footing/Tie Beam)', 'Rebars Fabrication (Footing, Tie Beam, GF/2F Column, Roof Beam)', 'Slab on Grade'],
            'TRUSSES AND ROOFING' => ['QDE Application', 'Trusses Installation', 'Roofing and Accessories'],
            'CHB LAYING' => ['GF and 2F Area (Plumbness & Squareness)', 'Interior/Exterior Walls', 'Electrical/Plumbing Rough-ins'],
            'STAIRCASE CONSTRUCTION' => ['Lay out', 'Form works', 'RSB Installation', 'Concrete Pouring'],
            'PLASTERING WORKS' => ['GF/2F Interior and Exterior Walls', 'Firewall', 'Window Opening Finishes'],
            'DOORS & JAMBS' => ['Maindoor/Steel Door', 'T&B doors', 'Bedroom doors'],
            'CEILING WORKS' => ['Electrical Lines', 'Framing', 'Exterior Ceilings', 'Carport Ceiling'],
            'PLUMBING FIXTURES' => ['Installation of Water Closets', 'Lavatories', 'Shower Sets', 'Kitchen Sinks'],
            'KITCHEN & T&B' => ['Kitchen Counter Construction', 'T&B Lavatory Pedestals', 'Floor/Wall Tiling'],
            'EXTERIOR & FINAL WORKS' => ['Septic Tank Construction', 'Concrete Step Pads', 'Stair Railings', 'Painting (Interior/Exterior)', 'Sliding Window Installation'],
            'COMPLETION' => ['Testing & Commissioning (Water/Electrical)', 'Cleaning/Clearing', 'Turnover of Keys']
        ];
        $stmt = $this->pdo->prepare("INSERT INTO project_accomplishments (project_id, category, task_name, status) VALUES (?, ?, ?, 'Not Started')");
        foreach ($template as $cat => $tasks) {
            foreach ($tasks as $task) {
                $stmt->execute([$project_id, $cat, $task]);
            }
        }
    }

    // --- PROJECTS ---
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
        $project_status = $projStmt->fetchColumn();
        
        $accStmt = $this->pdo->prepare("SELECT * FROM project_accomplishments WHERE project_id = ? ORDER BY id ASC"); $accStmt->execute([$project_id]);
        $issStmt = $this->pdo->prepare("SELECT i.*, inv.name as item_name, inv.unit FROM material_issuances i JOIN inventory inv ON i.item_id = inv.id WHERE i.project_id = ? ORDER BY i.id DESC"); $issStmt->execute([$project_id]);

        return [
            'status' => 'success', 
            'project_status' => $project_status, 
            'checklist' => $accStmt->fetchAll(PDO::FETCH_ASSOC),
            'issuances' => $issStmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    // --- CHECKLIST CRUD ---
    public function addChecklistTask($project_id, $category, $task_name) {
        $this->pdo->prepare("INSERT INTO project_accomplishments (project_id, category, task_name, status) VALUES (?, ?, ?, 'Not Started')")->execute([$project_id, $category, $task_name]);
        return ['status' => 'success'];
    }
    public function updateChecklistStatus($task_id, $status) {
        $completion_date = ($status === 'Completed') ? date('Y-m-d') : null;
        $this->pdo->prepare("UPDATE project_accomplishments SET status = ?, completion_date = ? WHERE id = ?")->execute([$status, $completion_date, $task_id]);
        return ['status' => 'success'];
    }
    public function editChecklistTask($task_id, $task_name) {
        $this->pdo->prepare("UPDATE project_accomplishments SET task_name = ? WHERE id = ?")->execute([$task_name, $task_id]);
        return ['status' => 'success'];
    }
    public function deleteChecklistTask($task_id) {
        $this->pdo->prepare("DELETE FROM project_accomplishments WHERE id = ?")->execute([$task_id]);
        return ['status' => 'success'];
    }
    public function deleteChecklistCategory($project_id, $category) {
        $this->pdo->prepare("DELETE FROM project_accomplishments WHERE project_id = ? AND category = ?")->execute([$project_id, $category]);
        return ['status' => 'success'];
    }

    // --- INVENTORY & SUPPLIERS ---
    public function getSuppliers() { return $this->pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function addSupplier($name, $materials, $contact, $email) { 
        $this->pdo->prepare("INSERT INTO suppliers (name, materials, contact, email, status) VALUES (?, ?, ?, ?, 'Active')")->execute([$name, $materials, $contact, $email]); 
        return ['status' => 'success']; 
    }

    public function getInventoryCategories() { return $this->pdo->query("SELECT name FROM inventory_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN); }
    public function addInventoryCategory($name) {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO inventory_categories (name) VALUES (?)");
        $stmt->execute([$name]); return ['status' => 'success'];
    }

    public function getInventory() { return $this->pdo->query("SELECT * FROM inventory ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); }
    public function addInventory($name, $category, $stock, $unit, $cost, $supplier) {
        $this->pdo->prepare("INSERT INTO inventory (name, category, stock, unit, unit_cost, supplier) VALUES (?, ?, ?, ?, ?, ?)")->execute([$name, $category, $stock, $unit, $cost, $supplier]);
        return ['status' => 'success'];
    }

    public function issueMaterial($project_id, $item_id, $qty, $receiver) {
        $stmt = $this->pdo->prepare("SELECT stock, unit_cost FROM inventory WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        if(!$item || $item['stock'] < $qty) return ['status' => 'error', 'message' => 'Insufficient stock! Only ' . ($item['stock'] ?? 0) . ' left.'];

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE inventory SET stock = stock - ? WHERE id = ?")->execute([$qty, $item_id]);
            $this->pdo->prepare("INSERT INTO material_issuances (project_id, item_id, qty, unit_cost, receiver) VALUES (?, ?, ?, ?, ?)")->execute([$project_id, $item_id, $qty, $item['unit_cost'], $receiver]);
            $this->pdo->commit();
            return ['status' => 'success'];
        } catch(Exception $e) {
            $this->pdo->rollBack(); return ['status' => 'error', 'message' => 'Transaction failed.'];
        }
    }

    // --- MANPOWER ---
    public function getUsers() { return $this->pdo->query("SELECT m.id, m.name, m.position, m.skills, m.rate as salary, m.photo_path as photo, p.name as project_name FROM manpower m LEFT JOIN projects p ON m.project_id = p.id WHERE m.is_archived = 0 ORDER BY m.name ASC")->fetchAll(PDO::FETCH_ASSOC); }
}
?>