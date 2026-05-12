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

    // Projects & Checklist
    case 'add_project': echo json_encode($app->addProject($_POST['name'], $_POST['client'], $_POST['location'], $_POST['desc'], $_POST['foreman'], $_POST['start_date'])); break;
    case 'get_projects': echo json_encode($app->getProjects()); break;
    case 'update_project_status': echo json_encode($app->updateProjectStatus($_POST['id'], $_POST['status'])); break;
    case 'delete_project': echo json_encode($app->deleteProject($_POST['id'])); break;
    case 'get_project_data': echo json_encode($app->getProjectData($_POST['project_id'])); break;
    
    case 'add_checklist_task': echo json_encode($app->addChecklistTask($_POST['project_id'], $_POST['category'], $_POST['task_name'])); break;
    case 'update_checklist_status': echo json_encode($app->updateChecklistStatus($_POST['task_id'], $_POST['status'])); break;
    case 'edit_checklist_task': echo json_encode($app->editChecklistTask($_POST['task_id'], $_POST['task_name'])); break;
    case 'delete_checklist_task': echo json_encode($app->deleteChecklistTask($_POST['task_id'])); break;
    case 'delete_checklist_category': echo json_encode($app->deleteChecklistCategory($_POST['project_id'], $_POST['category'])); break;

    // Inventory & Suppliers
    case 'get_suppliers': echo json_encode($app->getSuppliers()); break;
    case 'add_supplier': echo json_encode($app->addSupplier($_POST['name'], $_POST['materials'], $_POST['contact'], $_POST['email'])); break;
    
    case 'get_inventory_categories': echo json_encode($app->getInventoryCategories()); break;
    case 'add_inventory_category': echo json_encode($app->addInventoryCategory($_POST['name'])); break;
    
    case 'get_inventory': echo json_encode($app->getInventory()); break;
    case 'add_inventory': echo json_encode($app->addInventory($_POST['name'], $_POST['category'], $_POST['qty'], $_POST['unit'], $_POST['cost'], $_POST['supplier'])); break;
    case 'issue_material': echo json_encode($app->issueMaterial($_POST['project_id'], $_POST['item_id'], $_POST['qty'], $_POST['receiver'])); break;

    // Manpower
    case 'get_active_manpower': echo json_encode($app->getUsers()); break;

    default: echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>