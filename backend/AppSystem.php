<?php
class ConstructionSystem
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;

        try {
            // AUTO-FIX: Create tables and missing columns if they don't exist
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS skill_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) UNIQUE
        )");

            // AUTO-FIX: Required manpower archive columns
            $archivedFlagExists = $this->pdo->query("SHOW COLUMNS FROM manpower LIKE 'is_archived'")->rowCount();
            if ($archivedFlagExists == 0) {
                $this->pdo->exec("ALTER TABLE manpower ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0");
            }

            $archivedDateExists = $this->pdo->query("SHOW COLUMNS FROM manpower LIKE 'archived_date'")->rowCount();
            if ($archivedDateExists == 0) {
                $this->pdo->exec("ALTER TABLE manpower ADD COLUMN archived_date DATETIME DEFAULT NULL");
            }

            // BULLETPROOF PROJECTS TABLE
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            client_name VARCHAR(255),
            location VARCHAR(255),
            description TEXT,
            foreman VARCHAR(255),
            start_date DATE,
            status VARCHAR(50) DEFAULT 'pending'
        )");

            $colCreated = $this->pdo->query("SHOW COLUMNS FROM projects LIKE 'created_at'")->rowCount();
            if ($colCreated == 0) {
                $this->pdo->exec("ALTER TABLE projects ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            }

            // AUTO-FIX: Create award_costs table kung wala pa
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS award_costs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scope_of_work VARCHAR(255) NOT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00
        )");
        } catch (Exception $e) {
            // Silent fail para hindi bumagsak buong app kapag may existing DB mismatch
        }
    }

    public function login($email, $password)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            return ['status' => 'error', 'message' => 'Email address not found.', 'field' => 'email'];
        }
        if (!password_verify($password, $admin['password'])) {
            return ['status' => 'error', 'message' => 'Incorrect password.', 'field' => 'password'];
        }

        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['role'] = 'admin';
        return ['status' => 'success', 'role' => 'admin'];
    }

    public function getDashboardStats()
    {
        $stats = ['projects' => 0, 'users' => 0, 'total_cash_release' => 0, 'total_payroll_advance' => 0];

        // FIX: Ginawa kong LOWER() and TRIM() sa database level para walang ligtas na ongoing projects!
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM projects WHERE LOWER(TRIM(status)) IN ('ongoing', 'pending')");
            if ($stmt)
                $stats['projects'] = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
        }

        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM manpower WHERE is_archived = 0");
            if ($stmt)
                $stats['users'] = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
        }

        try {
            $stmt = $this->pdo->query("SELECT SUM(amount) FROM cash_releases");
            if ($stmt)
                $stats['total_cash_release'] = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
        }

        try {
            $stmt = $this->pdo->query("SELECT SUM(cash_advance) FROM payroll");
            if ($stmt)
                $stats['total_payroll_advance'] = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
        }

        return $stats;
    }

    private function generateDefaultChecklist($project_id)
    {
        $template = [
            'SOG TO ROOF BEAM' => ['Layout', 'Batter Boards', 'Excavation (Footing/Tie Beam)', 'Rebars Fabrication', 'Slab on Grade'],
            'TRUSSES AND ROOFING' => ['QDE Application', 'Trusses Installation', 'Roofing and Accessories'],
            'CHB LAYING' => ['GF and 2F Area', 'Interior/Exterior Walls', 'Electrical/Plumbing Rough-ins']
        ];
        $stmt = $this->pdo->prepare("INSERT INTO project_accomplishments (project_id, category, task_name, status, award_cost) VALUES (?, ?, ?, 'Not Started', 1500.00)");
        foreach ($template as $cat => $tasks) {
            foreach ($tasks as $task) {
                $stmt->execute([$project_id, $cat, $task]);
            }
        }
    }

    public function addProject($name, $client, $location, $desc, $foreman, $start_date)
    {
        $stmt = $this->pdo->prepare("INSERT INTO projects (name, client_name, location, description, foreman, start_date, status) VALUES (?, ?, ?, ?, ?, ?, 'ongoing')");
        $stmt->execute([$name, $client, $location, $desc, $foreman, $start_date]);
        $projectId = $this->pdo->lastInsertId();

        try {
            $this->pdo->prepare("INSERT INTO project_costs (project_id) VALUES (?)")->execute([$projectId]);
        } catch (PDOException $e) {
        }

        $this->generateDefaultChecklist($projectId);
        return ['status' => 'success'];
    }

    public function getProjects()
    {
        try {
            return $this->pdo->query("SELECT * FROM projects ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            try {
                return $this->pdo->query("SELECT * FROM projects ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e2) {
                return [];
            }
        }
    }

    public function updateProjectStatus($id, $status)
    {
        $this->pdo->prepare("UPDATE projects SET status=? WHERE id=?")->execute([strtolower(trim($status)), $id]);
        return ['status' => 'success'];
    }

    public function deleteProject($id)
    {
        $this->pdo->prepare("DELETE FROM project_accomplishments WHERE project_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM material_issuances WHERE project_id = ?")->execute([$id]);
        $this->pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
        return ['status' => 'success'];
    }

    public function getProjectData($project_id)
    {
        $projStmt = $this->pdo->prepare("SELECT status FROM projects WHERE id = ?");
        $projStmt->execute([$project_id]);
        $accStmt = $this->pdo->prepare("SELECT * FROM project_accomplishments WHERE project_id = ? ORDER BY id ASC");
        $accStmt->execute([$project_id]);
        $issStmt = $this->pdo->prepare("SELECT i.*, inv.name as item_name, inv.unit FROM material_issuances i JOIN inventory inv ON i.item_id = inv.id WHERE i.project_id = ? ORDER BY i.id DESC");
        $issStmt->execute([$project_id]);
        return ['status' => 'success', 'project_status' => $projStmt->fetchColumn(), 'checklist' => $accStmt->fetchAll(PDO::FETCH_ASSOC), 'issuances' => $issStmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function addChecklistTask($project_id, $category, $task_name)
    {
        $this->pdo->prepare("INSERT INTO project_accomplishments (project_id, category, task_name, status, award_cost) VALUES (?, ?, ?, 'Not Started', 1500.00)")->execute([$project_id, $category, $task_name]);
        return ['status' => 'success'];
    }
    public function updateChecklistStatus($task_id, $status)
    {
        $this->pdo->prepare("UPDATE project_accomplishments SET status = ?, completion_date = ? WHERE id = ?")->execute([$status, ($status === 'Completed') ? date('Y-m-d') : null, $task_id]);
        return ['status' => 'success'];
    }
    public function editChecklistTask($task_id, $task_name)
    {
        $this->pdo->prepare("UPDATE project_accomplishments SET task_name = ? WHERE id = ?")->execute([$task_name, $task_id]);
        return ['status' => 'success'];
    }
    public function updateTaskCost($task_id, $cost)
    {
        $this->pdo->prepare("UPDATE project_accomplishments SET award_cost = ? WHERE id = ?")->execute([$cost, $task_id]);
        return ['status' => 'success'];
    }
    public function deleteChecklistTask($task_id)
    {
        $this->pdo->prepare("DELETE FROM project_accomplishments WHERE id = ?")->execute([$task_id]);
        return ['status' => 'success'];
    }
    public function deleteChecklistCategory($project_id, $category)
    {
        $this->pdo->prepare("DELETE FROM project_accomplishments WHERE project_id = ? AND category = ?")->execute([$project_id, $category]);
        return ['status' => 'success'];
    }
    public function assignWorker($project_id, $category, $worker)
    {
        $this->pdo->prepare("UPDATE project_accomplishments SET assigned_worker = ? WHERE project_id = ? AND category = ?")->execute([$worker, $project_id, $category]);
        return ['status' => 'success'];
    }
    public function removeWorkerAssignment($project_id, $category)
    {
        $this->pdo->prepare("UPDATE project_accomplishments SET assigned_worker = NULL WHERE project_id = ? AND category = ?")->execute([$project_id, $category]);
        return ['status' => 'success'];
    }

    public function getSuppliers()
    {
        return $this->pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function addSupplier($name, $materials, $contact, $email)
    {
        $this->pdo->prepare("INSERT INTO suppliers (name, materials, contact, email, status) VALUES (?, ?, ?, ?, 'Active')")->execute([$name, $materials, $contact, $email]);
        return ['status' => 'success'];
    }
    public function getInventoryCategories()
    {
        return $this->pdo->query("SELECT name FROM inventory_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
    }
    public function addInventoryCategory($name)
    {
        $this->pdo->prepare("INSERT IGNORE INTO inventory_categories (name) VALUES (?)")->execute([$name]);
        return ['status' => 'success'];
    }
    public function getInventory()
    {
        return $this->pdo->query("SELECT * FROM inventory ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function addInventory($name, $category, $stock, $unit, $cost, $supplier)
    {
        $this->pdo->prepare("INSERT INTO inventory (name, category, stock, unit, unit_cost, supplier) VALUES (?, ?, ?, ?, ?, ?)")->execute([$name, $category, $stock, $unit, $cost, $supplier]);
        return ['status' => 'success'];
    }

    public function issueMaterial($project_id, $item_id, $qty, $receiver)
    {
        $stmt = $this->pdo->prepare("SELECT stock, unit_cost FROM inventory WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        if (!$item || $item['stock'] < $qty)
            return ['status' => 'error', 'message' => 'Insufficient stock! Only ' . ($item['stock'] ?? 0) . ' left.'];
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE inventory SET stock = stock - ? WHERE id = ?")->execute([$qty, $item_id]);
            $this->pdo->prepare("INSERT INTO material_issuances (project_id, item_id, qty, unit_cost, receiver) VALUES (?, ?, ?, ?, ?)")->execute([$project_id, $item_id, $qty, $item['unit_cost'], $receiver]);
            $this->pdo->commit();
            return ['status' => 'success'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()];
        }
    }

    public function addSkillCategory($name)
    {
        $this->pdo->prepare("INSERT IGNORE INTO skill_categories (name) VALUES (?)")->execute([trim($name)]);
        return ['status' => 'success'];
    }
    public function editSkillCategory($old_name, $new_name)
    {
        $this->pdo->prepare("UPDATE skill_categories SET name = ? WHERE name = ?")->execute([trim($new_name), trim($old_name)]);
        $this->pdo->prepare("UPDATE manpower SET skills = ? WHERE skills = ?")->execute([trim($new_name), trim($old_name)]);
        return ['status' => 'success'];
    }
    public function deleteSkillCategory($name)
    {
        $this->pdo->prepare("DELETE FROM skill_categories WHERE name = ?")->execute([trim($name)]);
        $this->pdo->prepare("DELETE FROM manpower WHERE skills = ?")->execute([trim($name)]);
        return ['status' => 'success'];
    }
    public function archiveManpower($id)
    {
        $this->pdo->prepare("UPDATE manpower SET is_archived = 1, archived_date = NOW() WHERE id = ?")->execute([$id]);
        return ['status' => 'success'];
    }
    public function restoreManpower($id)
    {
        $this->pdo->prepare("UPDATE manpower SET is_archived = 0, archived_date = NULL WHERE id = ?")->execute([$id]);
        return ['status' => 'success'];
    }

    public function getArchivedManpower()
    {
        try {
            return $this->pdo->query("SELECT m.*, m.photo_path as photo, p.name as project_name FROM manpower m LEFT JOIN projects p ON m.project_id = p.id WHERE m.is_archived = 1 ORDER BY m.archived_date DESC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    public function getUsers()
    {
        try {
            return $this->pdo->query("SELECT m.id, m.name, m.position, m.skills, m.rate as salary, m.photo_path as photo, p.name as project_name FROM manpower m LEFT JOIN projects p ON m.project_id = p.id WHERE m.is_archived = 0 ORDER BY m.name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getManpowerSkills()
    {
        $this->pdo->exec("INSERT IGNORE INTO skill_categories (name) SELECT DISTINCT skills FROM manpower WHERE skills IS NOT NULL AND TRIM(skills) != '' AND TRIM(skills) != 'Uncategorized'");
        $skills = $this->pdo->query("SELECT c.name as skill_name, (SELECT COUNT(*) FROM manpower m WHERE TRIM(m.skills) = c.name AND m.is_archived = 0) as worker_count FROM skill_categories c ORDER BY c.name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $uncat = $this->pdo->query("SELECT COUNT(*) FROM manpower WHERE (skills IS NULL OR TRIM(skills) = '' OR TRIM(skills) = 'Uncategorized') AND is_archived = 0")->fetchColumn();
        if ($uncat > 0) {
            $skills[] = ['skill_name' => 'Uncategorized', 'worker_count' => $uncat];
        }
        return $skills;
    }

    public function getManpowerBySkill($skill)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT m.*, m.photo_path as photo, p.name as project_name FROM manpower m LEFT JOIN projects p ON m.project_id = p.id WHERE TRIM(m.skills) = ? AND m.is_archived = 0 ORDER BY m.name ASC");
            $stmt->execute([trim($skill)]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }


    public function addManpower($name, $skills, $position, $salary, $project_id, $photo)
    {
        try {
            $name = trim($name);
            $skills = trim($skills);
            $position = trim($position);
            $salary = trim((string) $salary);

            if ($name === '' || $skills === '' || $position === '' || $salary === '') {
                return ['status' => 'error', 'message' => 'Please fill in all required fields.'];
            }

            $salary = preg_replace('/[^0-9.]/', '', $salary);

            if ($salary === '' || !is_numeric($salary)) {
                return ['status' => 'error', 'message' => 'Invalid salary/rate.'];
            }

            $filePath = null;

            if ($photo && isset($photo['tmp_name']) && $photo['tmp_name']) {
                $uploadDir = '../uploads/manpower/';

                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
                $fileName = 'MP_' . uniqid() . '.' . $ext;
                $filePath = 'uploads/manpower/' . $fileName;

                move_uploaded_file($photo['tmp_name'], '../' . $filePath);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO manpower (name, skills, position, rate, project_id, photo_path) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name,
                $skills,
                $position,
                $salary,
                $project_id ?: null,
                $filePath
            ]);

            $this->pdo->prepare("
                INSERT IGNORE INTO skill_categories (name) 
                VALUES (?)
            ")->execute([$skills]);

            return ['status' => 'success'];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Add manpower failed: ' . $e->getMessage()
            ];
        }
    }

    public function bulkAddManpower($itemsJson)
    {
        try {
            $items = json_decode($itemsJson, true);

            if (!is_array($items) || count($items) === 0) {
                return ['status' => 'error', 'message' => 'No records received.'];
            }

            $this->pdo->beginTransaction();

            $insertStmt = $this->pdo->prepare("
            INSERT INTO manpower (name, skills, position, rate, project_id, photo_path)
            VALUES (?, ?, ?, ?, ?, NULL)
        ");

            $skillStmt = $this->pdo->prepare("
            INSERT IGNORE INTO skill_categories (name)
            VALUES (?)
        ");

            $projectStmt = $this->pdo->prepare("
            SELECT id FROM projects 
            WHERE name = ? OR location = ?
            LIMIT 1
        ");

            $inserted = 0;
            $skipped = [];
            $projectCache = [];

            foreach ($items as $index => $item) {
                $lineNumber = $index + 1;

                $name = trim($item['name'] ?? '');
                $skills = trim($item['skills'] ?? '');
                $position = trim($item['position'] ?? '');
                $salaryRaw = trim((string) ($item['salary'] ?? ''));
                $projectRaw = trim((string) ($item['project'] ?? ''));

                $salary = preg_replace('/[^0-9.]/', '', $salaryRaw);

                if ($name === '' || $skills === '' || $position === '' || $salary === '' || !is_numeric($salary)) {
                    $skipped[] = [
                        'line' => $lineNumber,
                        'reason' => 'Incomplete or invalid data'
                    ];
                    continue;
                }

                $projectId = null;

                if ($projectRaw !== '') {
                    if (is_numeric($projectRaw)) {
                        $projectId = (int) $projectRaw;
                    } else {
                        $projectKey = strtolower($projectRaw);

                        if (!array_key_exists($projectKey, $projectCache)) {
                            $projectStmt->execute([$projectRaw, $projectRaw]);
                            $foundProjectId = $projectStmt->fetchColumn();
                            $projectCache[$projectKey] = $foundProjectId ?: null;
                        }

                        $projectId = $projectCache[$projectKey];
                    }
                }

                $insertStmt->execute([
                    $name,
                    $skills,
                    $position,
                    $salary,
                    $projectId
                ]);

                $skillStmt->execute([$skills]);

                $inserted++;
            }

            $this->pdo->commit();

            if ($inserted === 0) {
                return [
                    'status' => 'error',
                    'message' => 'No valid records were added.',
                    'skipped' => $skipped
                ];
            }

            return [
                'status' => 'success',
                'inserted' => $inserted,
                'skipped' => $skipped
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'status' => 'error',
                'message' => 'Bulk add failed: ' . $e->getMessage()
            ];
        }
    }

    public function updateBioData($worker_id, $photo)
    {
        if (!$photo || !isset($photo['tmp_name']) || !$photo['tmp_name']) {
            return ['status' => 'error', 'message' => 'No file uploaded.'];
        }
        $uploadDir = '../uploads/manpower/';
        if (!file_exists($uploadDir))
            mkdir($uploadDir, 0777, true);
        $fileName = 'MP_' . uniqid() . '.' . pathinfo($photo['name'], PATHINFO_EXTENSION);
        $filePath = 'uploads/manpower/' . $fileName;
        move_uploaded_file($photo['tmp_name'], '../' . $filePath);
        $this->pdo->prepare("UPDATE manpower SET photo_path = ? WHERE id = ?")->execute([$filePath, $worker_id]);
        return ['status' => 'success'];
    }

    public function getAwardCosts()
    {
        try {
            return $this->pdo->query("SELECT * FROM award_costs ORDER BY scope_of_work ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    public function addAwardCost($desc, $amount)
    {
        $this->pdo->prepare("INSERT INTO award_costs (scope_of_work, amount) VALUES (?, ?)")->execute([$desc, $amount]);
        return ['status' => 'success'];
    }
    public function deleteAwardCost($id)
    {
        $this->pdo->prepare("DELETE FROM award_costs WHERE id = ?")->execute([$id]);
        return ['status' => 'success'];
    }

    public function getAllCompletedTasks()
    {
        try {
            return $this->pdo->query("SELECT a.*, p.name as project_name, p.location as project_location FROM project_accomplishments a JOIN projects p ON a.project_id = p.id WHERE a.status = 'Completed' AND a.assigned_worker IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    public function getPayroll()
    {
        try {
            return $this->pdo->query("SELECT p.*, m.name FROM payroll p JOIN manpower m ON p.manpower_id = m.id")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    public function bulkAddAll($module, $itemsJson)
    {
        try {
            $module = strtolower(trim($module));
            $items = json_decode($itemsJson, true);

            if (!is_array($items) || count($items) === 0) {
                return ['status' => 'error', 'message' => 'No records received.'];
            }

            $allowedModules = [
                'projects',
                'suppliers',
                'inventory',
                'manpower',
                'award_costs',
                'payroll',
                'cash_release',
                'ntp'
            ];

            if (!in_array($module, $allowedModules, true)) {
                return ['status' => 'error', 'message' => 'Invalid bulk module.'];
            }

            $inserted = 0;
            $skipped = [];

            $this->pdo->beginTransaction();

            $projectLookupStmt = $this->pdo->prepare("
            SELECT id FROM projects 
            WHERE id = ? OR name = ? OR location = ? 
            LIMIT 1
        ");

            foreach ($items as $index => $item) {
                $lineNumber = $index + 1;

                try {
                    if ($module === 'projects') {
                        $name = trim($item['name'] ?? '');
                        $client = trim($item['client'] ?? '');
                        $location = trim($item['location'] ?? '');
                        $desc = trim($item['description'] ?? '');
                        $foreman = trim($item['foreman'] ?? '');
                        $startDate = trim($item['start_date'] ?? '');

                        if ($name === '' || $location === '' || $foreman === '' || $startDate === '') {
                            $skipped[] = ['line' => $lineNumber, 'reason' => 'Project name, location, foreman, and start date are required.'];
                            continue;
                        }

                        $stmt = $this->pdo->prepare("
                        INSERT INTO projects 
                        (name, client_name, location, description, foreman, start_date, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'ongoing')
                    ");
                        $stmt->execute([$name, $client ?: '-', $location, $desc, $foreman, $startDate]);

                        $projectId = $this->pdo->lastInsertId();

                        try {
                            $this->pdo->prepare("INSERT INTO project_costs (project_id) VALUES (?)")->execute([$projectId]);
                        } catch (Exception $e) {
                        }

                        $this->generateDefaultChecklist($projectId);
                        $inserted++;
                        continue;
                    }

                    if ($module === 'suppliers') {
                        $name = trim($item['name'] ?? '');
                        $materials = trim($item['materials'] ?? '');
                        $contact = trim($item['contact'] ?? '');
                        $email = trim($item['email'] ?? '');

                        if ($name === '' || $materials === '' || $contact === '') {
                            $skipped[] = ['line' => $lineNumber, 'reason' => 'Supplier name, materials, and contact are required.'];
                            continue;
                        }

                        $this->pdo->prepare("
                        INSERT INTO suppliers (name, materials, contact, email, status) 
                        VALUES (?, ?, ?, ?, 'Active')
                    ")->execute([$name, $materials, $contact, $email]);

                        $inserted++;
                        continue;
                    }

                    if ($module === 'inventory') {
                        $name = trim($item['name'] ?? '');
                        $category = trim($item['category'] ?? '');
                        $qty = preg_replace('/[^0-9.]/', '', (string) ($item['qty'] ?? ''));
                        $unit = trim($item['unit'] ?? '');
                        $cost = preg_replace('/[^0-9.]/', '', (string) ($item['cost'] ?? ''));
                        $supplier = trim($item['supplier'] ?? '');

                        if ($name === '' || $category === '' || $qty === '' || $unit === '' || $cost === '' || !is_numeric($qty) || !is_numeric($cost)) {
                            $skipped[] = ['line' => $lineNumber, 'reason' => 'Item name, category, quantity, unit, and cost are required.'];
                            continue;
                        }

                        $this->pdo->prepare("
                        INSERT IGNORE INTO inventory_categories (name) 
                        VALUES (?)
                    ")->execute([$category]);

                        $this->pdo->prepare("
                        INSERT INTO inventory (name, category, stock, unit, unit_cost, supplier) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([$name, $category, $qty, $unit, $cost, $supplier]);

                        $inserted++;
                        continue;
                    }

                    if ($module === 'manpower') {
                        $name = trim($item['name'] ?? '');
                        $skills = trim($item['skills'] ?? '');
                        $position = trim($item['position'] ?? '');
                        $salary = preg_replace('/[^0-9.]/', '', (string) ($item['salary'] ?? ''));
                        $projectRaw = trim((string) ($item['project'] ?? ''));
                        $projectId = null;

                        if ($name === '' || $skills === '' || $position === '' || $salary === '' || !is_numeric($salary)) {
                            $skipped[] = ['line' => $lineNumber, 'reason' => 'Name, skills, position, and daily rate are required.'];
                            continue;
                        }

                        if ($projectRaw !== '') {
                            $projectLookupStmt->execute([$projectRaw, $projectRaw, $projectRaw]);
                            $projectId = $projectLookupStmt->fetchColumn() ?: null;
                        }

                        $this->pdo->prepare("
                        INSERT INTO manpower (name, skills, position, rate, project_id, photo_path) 
                        VALUES (?, ?, ?, ?, ?, NULL)
                    ")->execute([$name, $skills, $position, $salary, $projectId]);

                        $this->pdo->prepare("
                        INSERT IGNORE INTO skill_categories (name) 
                        VALUES (?)
                    ")->execute([$skills]);

                        $inserted++;
                        continue;
                    }

                    if ($module === 'award_costs') {
                        $desc = trim($item['description'] ?? '');
                        $amount = preg_replace('/[^0-9.]/', '', (string) ($item['amount'] ?? ''));

                        if ($desc === '' || $amount === '' || !is_numeric($amount)) {
                            $skipped[] = ['line' => $lineNumber, 'reason' => 'Scope of work and amount are required.'];
                            continue;
                        }

                        $this->pdo->prepare("
                        INSERT INTO award_costs (scope_of_work, amount) 
                        VALUES (?, ?)
                    ")->execute([$desc, $amount]);

                        $inserted++;
                        continue;
                    }

                    if ($module === 'payroll') {
                        $date = trim($item['date'] ?? '');
                        $name = trim($item['name'] ?? '');
                        $job = trim($item['job_desc'] ?? '');
                        $award = preg_replace('/[^0-9.]/', '', (string) ($item['award'] ?? '0'));
                        $advance = preg_replace('/[^0-9.]/', '', (string) ($item['advance'] ?? '0'));

                        if ($date === '' || $name === '' || $job === '') {
                            $skipped[] = ['line' => $lineNumber, 'reason' => 'Date, name, and job description are required.'];
                            continue;
                        }

                        $stmt = $this->pdo->prepare("SELECT id FROM manpower WHERE name = ? LIMIT 1");
                        $stmt->execute([$name]);
                        $worker = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$worker) {
                            $this->pdo->prepare("
                            INSERT INTO manpower (name, position, skills, rate) 
                            VALUES (?, 'Worker', 'Uncategorized', 500)
                        ")->execute([$name]);

                            $manpowerId = $this->pdo->lastInsertId();
                        } else {
                            $manpowerId = $worker['id'];
                        }

                        $this->pdo->prepare("
                        INSERT INTO payroll 
                        (manpower_id, pay_date, job_description, rate, days_worked, gross_pay, deductions, net_pay, award_cost, cash_advance, overall_advance, balance) 
                        VALUES (?, ?, ?, 0, 0, 0, 0, 0, ?, ?, 0, 0)
                    ")->execute([$manpowerId, $date, $job, $award ?: 0, $advance ?: 0]);

                        $inserted++;
                        continue;
                    }

                    if ($module === 'cash_release') {
                        $date = trim($item['date'] ?? '');
                        $category = trim($item['category'] ?? '');
                        $name = trim($item['name'] ?? '');
                        $desc = trim($item['description'] ?? '');
                        $amount = preg_replace('/[^0-9.]/', '', (string) ($item['amount'] ?? ''));

                        if ($date === '' || $category === '' || $name === '' || $amount === '' || !is_numeric($amount)) {
                            $skipped[] = ['line' => $lineNumber, 'reason' => 'Date, category, name, and amount are required.'];
                            continue;
                        }

                        $this->pdo->prepare("
                        INSERT INTO cash_releases (release_date, category, name, description, amount) 
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$date, $category, $name, $desc, $amount]);

                        $inserted++;
                        continue;
                    }

                    if ($module === 'ntp') {
                        $projectRaw = trim((string) ($item['project'] ?? ''));
                        $ticket = trim($item['ticket'] ?? '');
                        $date = trim($item['date'] ?? '');
                        $awardCost = preg_replace('/[^0-9.]/', '', (string) ($item['award_cost'] ?? '0'));
                        $dueDate = trim($item['due_date'] ?? '');
                        $acceptDate = trim($item['accept_date'] ?? '');

                        if ($projectRaw === '' || $date === '' || $dueDate === '') {
                            $skipped[] = ['line' => $lineNumber, 'reason' => 'Project, date received, and due date are required.'];
                            continue;
                        }

                        $projectLookupStmt->execute([$projectRaw, $projectRaw, $projectRaw]);
                        $projectId = $projectLookupStmt->fetchColumn();

                        if (!$projectId) {
                            $skipped[] = ['line' => $lineNumber, 'reason' => 'Project not found. Use project ID or exact project/location name.'];
                            continue;
                        }

                        $this->pdo->prepare("
                        INSERT INTO project_ntp 
                        (project_id, ntp_ticket, date_received, award_cost, due_date, acceptance_date, file_path) 
                        VALUES (?, ?, ?, ?, ?, ?, '')
                    ")->execute([$projectId, $ticket, $date, $awardCost ?: 0, $dueDate, $acceptDate]);

                        $this->pdo->prepare("
                        UPDATE projects SET status = 'ongoing' WHERE id = ?
                    ")->execute([$projectId]);

                        $inserted++;
                        continue;
                    }
                } catch (Exception $rowError) {
                    $skipped[] = ['line' => $lineNumber, 'reason' => $rowError->getMessage()];
                    continue;
                }
            }

            $this->pdo->commit();

            if ($inserted === 0) {
                return [
                    'status' => 'error',
                    'message' => 'No valid records were added.',
                    'inserted' => 0,
                    'skipped' => $skipped
                ];
            }

            return [
                'status' => 'success',
                'inserted' => $inserted,
                'skipped' => $skipped
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'status' => 'error',
                'message' => 'Bulk add failed: ' . $e->getMessage()
            ];
        }
    }
    public function deletePayrollEntry($id)
    {
        try {
            $this->pdo->prepare("DELETE FROM payroll WHERE id = ?")->execute([$id]);
            return ['status' => 'success'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    public function editPayrollEntry($id, $award, $advance)
    {
        try {
            $this->pdo->prepare("UPDATE payroll SET award_cost = ?, cash_advance = ? WHERE id = ?")->execute([$award, $advance, $id]);
            return ['status' => 'success'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function archiveAndResetPayroll()
    {
        $cycle = 'CYCLE-' . date('Ymd-His');
        $payroll = $this->getPayroll();
        $balances = [];
        foreach ($payroll as $p) {
            $key = $p['manpower_id'] . '_' . $p['job_description'];
            if (!isset($balances[$key]))
                $balances[$key] = 0;
            $balances[$key] += ($p['award_cost'] - $p['cash_advance']);
        }
        $stmt = $this->pdo->prepare("INSERT INTO payroll_history (cycle_id, manpower_id, pay_date, job_description, rate, net_pay, award_cost, cash_advance, overall_advance, balance) VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?, ?)");
        $deleteStmt = $this->pdo->prepare("DELETE FROM payroll WHERE id = ?");
        $archivedCount = 0;
        foreach ($payroll as $p) {
            $key = $p['manpower_id'] . '_' . $p['job_description'];
            if ($balances[$key] <= 0) {
                $stmt->execute([$cycle, $p['manpower_id'], $p['pay_date'], $p['job_description'], $p['award_cost'], $p['cash_advance'], $p['overall_advance'], $p['balance']]);
                $deleteStmt->execute([$p['id']]);
                $archivedCount++;
            }
        }
        return ['status' => 'success', 'archived' => $archivedCount];
    }
    public function getPayrollHistory()
    {
        $this->pdo->query("DELETE FROM payroll_history WHERE pay_date < DATE_SUB(CURDATE(), INTERVAL 1 YEAR)");
        return $this->pdo->query("SELECT h.*, m.name FROM payroll_history h JOIN manpower m ON h.manpower_id = m.id ORDER BY h.pay_date DESC, h.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCashReleases()
    {
        try {
            return $this->pdo->query("SELECT * FROM cash_releases ORDER BY release_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    public function addCashRelease($date, $category, $name, $desc, $amount)
    {
        try {
            $this->pdo->prepare("INSERT INTO cash_releases (release_date, category, name, description, amount) VALUES (?, ?, ?, ?, ?)")->execute([$date, $category, $name, $desc, $amount]);
            return ['status' => 'success'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    public function deleteCashRelease($id)
    {
        try {
            $this->pdo->prepare("DELETE FROM cash_releases WHERE id = ?")->execute([$id]);
            return ['status' => 'success'];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getAllNTPs()
    {
        return $this->pdo->query("SELECT n.*, p.name as project_name FROM project_ntp n JOIN projects p ON n.project_id = p.id ORDER BY n.due_date ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function uploadNTPFile($project_id, $ticket, $date, $award_cost, $due_date, $accept_date, $file)
    {
        $filePath = '';
        if ($file && isset($file['tmp_name']) && $file['tmp_name']) {
            $uploadDir = '../uploads/ntp/';
            if (!file_exists($uploadDir))
                mkdir($uploadDir, 0777, true);
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