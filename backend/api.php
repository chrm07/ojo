<?php
session_start();
header('Content-Type: application/json');

require 'db.php';
require 'AppSystem.php';

$app = new ConstructionSystem($pdo);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        echo json_encode($app->login($_POST['email'] ?? '', $_POST['password'] ?? ''));
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['status' => 'success']);
        break;

    case 'check_session':
        echo json_encode([
            'logged_in' => isset($_SESSION['user_id']),
            'role' => $_SESSION['role'] ?? ''
        ]);
        break;

    case 'get_stats':
        echo json_encode($app->getDashboardStats());
        break;

    // =========================
    // PROJECTS
    // =========================
    case 'add_project':
        echo json_encode($app->addProject(
            $_POST['name'] ?? '',
            $_POST['client'] ?? '',
            $_POST['location'] ?? '',
            $_POST['desc'] ?? '',
            $_POST['foreman'] ?? '',
            $_POST['start_date'] ?? ''
        ));
        break;

    case 'get_projects':
        echo json_encode($app->getProjects());
        break;

    case 'update_project_status':
        echo json_encode($app->updateProjectStatus($_POST['id'] ?? '', $_POST['status'] ?? ''));
        break;

    case 'delete_project':
        echo json_encode($app->deleteProject($_POST['id'] ?? ''));
        break;

    case 'get_project_data':
        echo json_encode($app->getProjectData($_POST['project_id'] ?? ''));
        break;

    // =========================
    // CHECKLIST
    // =========================
    case 'add_checklist_task':
        echo json_encode($app->addChecklistTask(
            $_POST['project_id'] ?? '',
            $_POST['category'] ?? '',
            $_POST['task_name'] ?? ''
        ));
        break;

    case 'update_checklist_status':
        echo json_encode($app->updateChecklistStatus($_POST['task_id'] ?? '', $_POST['status'] ?? ''));
        break;

    case 'edit_checklist_task':
        echo json_encode($app->editChecklistTask($_POST['task_id'] ?? '', $_POST['task_name'] ?? ''));
        break;

    case 'update_task_cost':
        echo json_encode($app->updateTaskCost($_POST['task_id'] ?? '', $_POST['cost'] ?? 0));
        break;

    case 'delete_checklist_task':
        echo json_encode($app->deleteChecklistTask($_POST['task_id'] ?? ''));
        break;

    case 'delete_checklist_category':
        echo json_encode($app->deleteChecklistCategory($_POST['project_id'] ?? '', $_POST['category'] ?? ''));
        break;

    case 'assign_worker':
        echo json_encode($app->assignWorker(
            $_POST['project_id'] ?? '',
            $_POST['category'] ?? '',
            $_POST['worker'] ?? ''
        ));
        break;

    case 'remove_worker':
        echo json_encode($app->removeWorkerAssignment($_POST['project_id'] ?? '', $_POST['category'] ?? ''));
        break;

    // =========================
    // SUPPLIERS / INVENTORY
    // =========================
    case 'get_suppliers':
        echo json_encode($app->getSuppliers());
        break;

    case 'add_supplier':
        echo json_encode($app->addSupplier(
            $_POST['name'] ?? '',
            $_POST['materials'] ?? '',
            $_POST['contact'] ?? '',
            $_POST['email'] ?? ''
        ));
        break;

    case 'get_inventory_categories':
        echo json_encode($app->getInventoryCategories());
        break;

    case 'add_inventory_category':
        echo json_encode($app->addInventoryCategory($_POST['name'] ?? ''));
        break;

    case 'get_inventory':
        echo json_encode($app->getInventory());
        break;

    case 'add_inventory':
        echo json_encode($app->addInventory(
            $_POST['name'] ?? '',
            $_POST['category'] ?? '',
            $_POST['qty'] ?? 0,
            $_POST['unit'] ?? '',
            $_POST['cost'] ?? 0,
            $_POST['supplier'] ?? ''
        ));
        break;

    case 'issue_material':
        echo json_encode($app->issueMaterial(
            $_POST['project_id'] ?? '',
            $_POST['item_id'] ?? '',
            $_POST['qty'] ?? 0,
            $_POST['receiver'] ?? ''
        ));
        break;

    // =========================
    // MANPOWER
    // =========================
    case 'get_active_manpower':
        echo json_encode($app->getUsers());
        break;

    case 'get_manpower_skills':
        echo json_encode($app->getManpowerSkills());
        break;

    case 'get_manpower_by_skill':
        echo json_encode($app->getManpowerBySkill($_POST['skill'] ?? ''));
        break;

    case 'add_manpower':
        echo json_encode($app->addManpower(
            $_POST['name'] ?? '',
            $_POST['skills'] ?? '',
            $_POST['position'] ?? '',
            $_POST['salary'] ?? 0,
            $_POST['project_id'] ?? '',
            $_FILES['photo'] ?? null
        ));
        break;

    case 'update_bio_data':
        echo json_encode($app->updateBioData($_POST['worker_id'] ?? '', $_FILES['photo'] ?? null));
        break;

    // =========================
    // MANPOWER FOLDERS
    // =========================
    case 'add_skill_category':
        echo json_encode($app->addSkillCategory($_POST['name'] ?? ''));
        break;

    case 'edit_skill_category':
        echo json_encode($app->editSkillCategory($_POST['old_name'] ?? '', $_POST['new_name'] ?? ''));
        break;

    case 'delete_skill_category':
        echo json_encode($app->deleteSkillCategory($_POST['name'] ?? ''));
        break;

    // =========================
    // ARCHIVED MANPOWER
    // =========================
    case 'get_archived_manpower':
        echo json_encode($app->getArchivedManpower());
        break;

    case 'archive_manpower':
        echo json_encode($app->archiveManpower($_POST['id'] ?? ''));
        break;

    case 'restore_manpower':
        echo json_encode($app->restoreManpower($_POST['id'] ?? ''));
        break;

    // =========================
    // AWARD COSTS
    // =========================
    case 'get_award_costs':
        echo json_encode($app->getAwardCosts());
        break;

    case 'add_award_cost':
        echo json_encode($app->addAwardCost($_POST['desc'] ?? '', $_POST['amount'] ?? 0));
        break;

    case 'delete_award_cost':
        echo json_encode($app->deleteAwardCost($_POST['id'] ?? ''));
        break;

    // =========================
    // PAYROLL
    // =========================
    case 'get_all_completed_tasks':
        echo json_encode($app->getAllCompletedTasks());
        break;

    case 'get_payroll':
        echo json_encode($app->getPayroll());
        break;

    case 'add_payroll':
        echo json_encode($app->addPayroll(
            $_POST['date'] ?? '',
            $_POST['name'] ?? '',
            $_POST['job_desc'] ?? '',
            $_POST['award'] ?? 0,
            $_POST['advance'] ?? 0
        ));
        break;

    case 'edit_payroll_entry':
        echo json_encode($app->editPayrollEntry(
            $_POST['id'] ?? '',
            $_POST['award_cost'] ?? 0,
            $_POST['cash_advance'] ?? 0
        ));
        break;

    case 'delete_payroll_entry':
        echo json_encode($app->deletePayrollEntry($_POST['id'] ?? ''));
        break;

    case 'archive_and_reset_payroll':
        echo json_encode($app->archiveAndResetPayroll());
        break;

    case 'get_payroll_history':
        echo json_encode($app->getPayrollHistory());
        break;

    // =========================
    // CASH RELEASE
    // =========================
    case 'get_cash_releases':
        echo json_encode($app->getCashReleases());
        break;

    case 'add_cash_release':
        echo json_encode($app->addCashRelease(
            $_POST['date'] ?? '',
            $_POST['category'] ?? '',
            $_POST['name'] ?? '',
            $_POST['desc'] ?? '',
            $_POST['amount'] ?? 0
        ));
        break;

    case 'delete_cash_release':
        echo json_encode($app->deleteCashRelease($_POST['id'] ?? ''));
        break;

    // =========================
    // NTP
    // =========================
    case 'get_all_ntps':
        echo json_encode($app->getAllNTPs());
        break;

    case 'upload_ntp_file':
        echo json_encode($app->uploadNTPFile(
            $_POST['project_id'] ?? '',
            $_POST['ticket'] ?? '',
            $_POST['date'] ?? '',
            $_POST['award_cost'] ?? 0,
            $_POST['due_date'] ?? '',
            $_POST['accept_date'] ?? '',
            $_FILES['file'] ?? null
        ));
        break;

    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action'
        ]);
        break;
}
?>