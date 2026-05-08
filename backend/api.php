<?php
session_start();
header('Content-Type: application/json');

require 'db.php';
require 'AppSystem.php';

$app = new ConstructionSystem($pdo);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'login': echo json_encode($app->login($_POST['email'], $_POST['password'])); break;
    case 'logout': session_destroy(); echo json_encode(['status' => 'success']); break;
    case 'check_session': echo json_encode(['logged_in' => isset($_SESSION['user_id']), 'role' => $_SESSION['role'] ?? '']); break;
    case 'get_stats': echo json_encode($app->getDashboardStats()); break;

    case 'add_project': echo json_encode($app->addProject($_POST['name'], $_POST['location'], $_POST['desc'], $_POST['start_date'])); break;
    case 'get_projects': echo json_encode($app->getProjects()); break;
    case 'update_project_status': echo json_encode($app->updateProjectStatus($_POST['id'], $_POST['status'])); break;
    case 'delete_project': echo json_encode($app->deleteProject($_POST['id'])); break;
    case 'get_project_data': echo json_encode($app->getProjectData($_POST['project_id'])); break;

    case 'update_checklist_status': echo json_encode($app->updateChecklistStatus($_POST['task_id'], $_POST['status'])); break;
    case 'update_checklist_remarks': echo json_encode($app->updateChecklistRemarks($_POST['task_id'], $_POST['remarks'])); break;

    case 'get_global_materials': echo json_encode($app->getGlobalMaterials()); break;
    case 'add_project_material': echo json_encode($app->addProjectMaterial($_POST['project_id'], $_POST['supplier_id'], $_POST['name'], $_POST['qty'], $_POST['unit'], $_POST['unit_cost'])); break;
    case 'delete_project_material': echo json_encode($app->deleteProjectMaterial($_POST['id'])); break;

    case 'update_project_costs': echo json_encode($app->updateProjectCosts($_POST['project_id'], $_POST['labor'], $_POST['material'], $_POST['misc'])); break;

    case 'get_all_ntps': echo json_encode($app->getAllNTPs()); break;
    case 'upload_ntp_file': echo json_encode($app->uploadNTPFile($_POST['project_id'], $_POST['ticket'], $_POST['date'], $_POST['award_cost'], $_POST['due_date'], $_POST['accept_date'], $_POST['status'], $_FILES['file'] ?? null)); break;
    case 'delete_ntp_file': echo json_encode($app->deleteNTPFile($_POST['id'], $_POST['project_id'])); break;

    case 'add_award_cost': echo json_encode($app->addAwardCost($_POST['desc'], $_POST['amount'])); break;
    case 'get_award_costs': echo json_encode($app->getAwardCosts()); break;
    case 'delete_award_cost': echo json_encode($app->deleteAwardCost($_POST['id'])); break;

    case 'add_manpower': echo json_encode($app->addManpower($_POST['name'], $_POST['position'], $_POST['skills'], $_POST['salary'], $_POST['project_id'] ?? null, $_FILES['photo'] ?? null)); break;
    case 'get_active_manpower': echo json_encode($app->getUsers()); break;
    case 'get_manpower_skills': echo json_encode($app->getManpowerSkills()); break;
    case 'get_manpower_by_skill': echo json_encode($app->getManpowerBySkill($_POST['skill'])); break;
    case 'archive_manpower': echo json_encode($app->archiveManpower($_POST['id'])); break;
    case 'restore_manpower': echo json_encode($app->restoreManpower($_POST['id'])); break;
    case 'get_archived_manpower': echo json_encode($app->getArchivedManpower()); break;
    
    case 'add_payroll': echo json_encode($app->addPayroll($_POST['name'], $_POST['days'], $_POST['deductions'], $_POST['date'], $_POST['job_desc'])); break;
    case 'get_payroll': echo json_encode($app->getPayroll()); break;
    case 'archive_and_reset_payroll': echo json_encode($app->archiveAndResetPayroll()); break;
    case 'get_payroll_history': echo json_encode($app->getPayrollHistory()); break;

    case 'add_supplier': echo json_encode($app->addSupplier($_POST['name'], $_POST['contact'])); break;
    case 'get_suppliers': echo json_encode($app->getSuppliers()); break;

    case 'get_notifs': echo json_encode($app->getNotifications()); break;
    case 'mark_read': $app->markNotifRead($_POST['id']); echo json_encode(['status' => 'success']); break;
    
    default: echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>