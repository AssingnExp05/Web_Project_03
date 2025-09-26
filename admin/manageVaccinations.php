<?php
// admin/manageVaccinations.php - Admin Vaccination Management Page
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Base URL
$BASE_URL = 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error_message'] = 'Please login as an admin to access this page.';
    header('Location: ' . $BASE_URL . 'auth/login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$page_title = 'Manage Vaccinations - Admin Dashboard';

// Initialize variables
$vaccinations = [];
$pets = [];
$shelters = [];
$total_vaccinations = 0;
$total_pages = 1;
$stats = [
    'total_vaccinations' => 0,
    'pending_vaccinations' => 0,
    'completed_vaccinations' => 0,
    'overdue_vaccinations' => 0,
    'this_month_vaccinations' => 0,
    'upcoming_vaccinations' => 0
];

// Filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_shelter = $_GET['shelter'] ?? '';
$filter_pet = $_GET['pet'] ?? '';
$filter_vaccine_type = $_GET['vaccine_type'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$search_query = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    try {
        require_once __DIR__ . '/../config/db.php';
        $db = getDB();
        
        if ($db && isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_vaccination':
                    $pet_id = intval($_POST['pet_id'] ?? 0);
                    $vaccine_name = trim($_POST['vaccine_name'] ?? '');
                    $vaccination_date = $_POST['vaccination_date'] ?? '';
                    $next_due_date = $_POST['next_due_date'] ?? '';
                    $veterinarian_name = trim($_POST['veterinarian_name'] ?? '');
                    $notes = trim($_POST['notes'] ?? '');
                    
                    if ($pet_id > 0 && !empty($vaccine_name)) {
                        $stmt = $db->prepare("
                            INSERT INTO vaccinations (pet_id, vaccine_name, vaccination_date, next_due_date, 
                                                    veterinarian_name, notes)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $vacc_date = !empty($vaccination_date) ? $vaccination_date : null;
                        $next_due = !empty($next_due_date) ? $next_due_date : null;
                        
                        if ($stmt->execute([$pet_id, $vaccine_name, $vacc_date, $next_due, $veterinarian_name, $notes])) {
                            echo json_encode(['success' => true, 'message' => 'Vaccination record added successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to add vaccination record']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
                    }
                    break;
                    
                case 'update_vaccination':
                    $vaccination_id = intval($_POST['vaccination_id'] ?? 0);
                    $vaccine_name = trim($_POST['vaccine_name'] ?? '');
                    $vaccination_date = $_POST['vaccination_date'] ?? '';
                    $next_due_date = $_POST['next_due_date'] ?? '';
                    $veterinarian_name = trim($_POST['veterinarian_name'] ?? '');
                    $notes = trim($_POST['notes'] ?? '');
                    
                    if ($vaccination_id > 0 && !empty($vaccine_name)) {
                        $stmt = $db->prepare("
                            UPDATE vaccinations 
                            SET vaccine_name = ?, vaccination_date = ?, next_due_date = ?,
                                veterinarian_name = ?, notes = ?
                            WHERE vaccination_id = ?
                        ");
                        
                        $vacc_date = !empty($vaccination_date) ? $vaccination_date : null;
                        $next_due = !empty($next_due_date) ? $next_due_date : null;
                        
                        if ($stmt->execute([$vaccine_name, $vacc_date, $next_due, $veterinarian_name, $notes, $vaccination_id])) {
                            echo json_encode(['success' => true, 'message' => 'Vaccination record updated successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to update vaccination record']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
                    }
                    break;
                    
                case 'delete_vaccination':
                    $vaccination_id = intval($_POST['vaccination_id'] ?? 0);
                    
                    if ($vaccination_id > 0) {
                        $stmt = $db->prepare("DELETE FROM vaccinations WHERE vaccination_id = ?");
                        if ($stmt->execute([$vaccination_id])) {
                            echo json_encode(['success' => true, 'message' => 'Vaccination record deleted successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to delete vaccination record']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid vaccination ID']);
                    }
                    break;
                    
                case 'get_vaccination_details':
                    $vaccination_id = intval($_POST['vaccination_id'] ?? 0);
                    
                    if ($vaccination_id > 0) {
                        $stmt = $db->prepare("
                            SELECT v.*, p.pet_name, pc.category_name, pb.breed_name, s.shelter_name
                            FROM vaccinations v
                            LEFT JOIN pets p ON v.pet_id = p.pet_id
                            LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
                            LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
                            LEFT JOIN shelters s ON p.shelter_id = s.shelter_id
                            WHERE v.vaccination_id = ?
                        ");
                        $stmt->execute([$vaccination_id]);
                        $vaccination = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($vaccination) {
                            echo json_encode(['success' => true, 'vaccination' => $vaccination]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Vaccination not found']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid vaccination ID']);
                    }
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Database operations
try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if ($db) {
        // Get statistics
        try {
            // Total vaccinations
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM vaccinations");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_vaccinations'] = $result ? (int)$result['count'] : 0;
            
            // Completed vaccinations (have vaccination date)
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM vaccinations WHERE vaccination_date IS NOT NULL");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['completed_vaccinations'] = $result ? (int)$result['count'] : 0;
            
            // Pending vaccinations (no vaccination date but have next due date in future)
            $stmt = $db->prepare("
                SELECT COUNT(*) as count FROM vaccinations 
                WHERE vaccination_date IS NULL AND next_due_date >= CURDATE()
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['pending_vaccinations'] = $result ? (int)$result['count'] : 0;
            
            // Overdue vaccinations (no vaccination date and next due date is past)
            $stmt = $db->prepare("
                SELECT COUNT(*) as count FROM vaccinations 
                WHERE vaccination_date IS NULL AND next_due_date < CURDATE()
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['overdue_vaccinations'] = $result ? (int)$result['count'] : 0;
            
            // This month vaccinations
            $stmt = $db->prepare("
                SELECT COUNT(*) as count FROM vaccinations 
                WHERE MONTH(vaccination_date) = MONTH(CURDATE()) 
                AND YEAR(vaccination_date) = YEAR(CURDATE())
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['this_month_vaccinations'] = $result ? (int)$result['count'] : 0;
            
            // Upcoming vaccinations (next 30 days)
            $stmt = $db->prepare("
                SELECT COUNT(*) as count FROM vaccinations 
                WHERE next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND vaccination_date IS NULL
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['upcoming_vaccinations'] = $result ? (int)$result['count'] : 0;
            
        } catch (Exception $e) {
            error_log("Stats error: " . $e->getMessage());
        }
        
        // Get shelters for filter dropdown
        try {
            $stmt = $db->prepare("SELECT shelter_id, shelter_name FROM shelters ORDER BY shelter_name");
            $stmt->execute();
            $shelters = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $shelters = [];
        }
        
        // Build the vaccinations query
        $where_conditions = [];
        $params = [];
        
        if (!empty($filter_status)) {
            if ($filter_status === 'completed') {
                $where_conditions[] = "v.vaccination_date IS NOT NULL";
            } elseif ($filter_status === 'pending') {
                $where_conditions[] = "v.vaccination_date IS NULL AND v.next_due_date >= CURDATE()";
            } elseif ($filter_status === 'overdue') {
                $where_conditions[] = "v.vaccination_date IS NULL AND v.next_due_date < CURDATE()";
            }
        }
        
        if (!empty($filter_shelter)) {
            $where_conditions[] = "s.shelter_id = ?";
            $params[] = $filter_shelter;
        }
        
        if (!empty($filter_pet)) {
            $where_conditions[] = "v.pet_id = ?";
            $params[] = $filter_pet;
        }
        
        if (!empty($filter_date_from)) {
            $where_conditions[] = "DATE(COALESCE(v.vaccination_date, v.next_due_date)) >= ?";
            $params[] = $filter_date_from;
        }
        
        if (!empty($filter_date_to)) {
            $where_conditions[] = "DATE(COALESCE(v.vaccination_date, v.next_due_date)) <= ?";
            $params[] = $filter_date_to;
        }
        
        if (!empty($search_query)) {
            $where_conditions[] = "(p.pet_name LIKE ? OR v.vaccine_name LIKE ? OR v.veterinarian_name LIKE ? OR s.shelter_name LIKE ?)";
            $search_term = "%$search_query%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count for pagination
        $count_query = "
            SELECT COUNT(*) as total 
            FROM vaccinations v
            LEFT JOIN pets p ON v.pet_id = p.pet_id
            LEFT JOIN shelters s ON p.shelter_id = s.shelter_id
            $where_clause
        ";
        $stmt = $db->prepare($count_query);
        $stmt->execute($params);
        $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_vaccinations = $count_result ? (int)$count_result['total'] : 0;
        $total_pages = max(1, ceil($total_vaccinations / $per_page));
        
        // Get vaccinations with pagination
        try {
            $vaccinations_query = "
                SELECT v.*, 
                       p.pet_name, p.age, p.gender, p.primary_image,
                       pc.category_name, pb.breed_name,
                       s.shelter_name,
                       CASE 
                           WHEN v.vaccination_date IS NOT NULL THEN 'completed'
                           WHEN v.vaccination_date IS NULL AND v.next_due_date >= CURDATE() THEN 'pending'
                           WHEN v.vaccination_date IS NULL AND v.next_due_date < CURDATE() THEN 'overdue'
                           ELSE 'unknown'
                       END as vaccination_status
                FROM vaccinations v
                LEFT JOIN pets p ON v.pet_id = p.pet_id
                LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
                LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
                LEFT JOIN shelters s ON p.shelter_id = s.shelter_id
                $where_clause
                ORDER BY 
                    CASE 
                        WHEN v.vaccination_date IS NULL AND v.next_due_date < CURDATE() THEN 1
                        WHEN v.vaccination_date IS NULL AND v.next_due_date >= CURDATE() THEN 2
                        ELSE 3
                    END,
                    COALESCE(v.next_due_date, v.vaccination_date) DESC
                LIMIT $per_page OFFSET $offset
            ";
            
            $stmt = $db->prepare($vaccinations_query);
            $stmt->execute($params);
            $vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Vaccinations query error: " . $e->getMessage());
            $vaccinations = [];
        }
        
        // Get all pets for add vaccination form
        try {
            $stmt = $db->prepare("
                SELECT p.pet_id, p.pet_name, pc.category_name, pb.breed_name, s.shelter_name, s.shelter_id 
                FROM pets p 
                LEFT JOIN shelters s ON p.shelter_id = s.shelter_id 
                LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
                LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
                WHERE p.status IN ('available', 'pending')
                ORDER BY s.shelter_name, p.pet_name
            ");
            $stmt->execute();
            $pets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $pets = [];
        }
    }
} catch (Exception $e) {
    error_log("Manage Vaccinations database error: " . $e->getMessage());
}

// Common vaccine names for quick selection
$common_vaccines = [
    'Rabies', 'DHPP (Distemper, Hepatitis, Parvovirus, Parainfluenza)', 
    'Bordetella', 'Lyme Disease', 'FVRCP (Feline Viral Rhinotracheitis, Calicivirus, Panleukopenia)',
    'FeLV (Feline Leukemia)', 'FIV (Feline Immunodeficiency Virus)'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        color: #333;
        line-height: 1.6;
        min-height: 100vh;
    }

    .container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Header Section */
    .page-header {
        background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        color: white;
        border-radius: 20px;
        padding: 30px 40px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .page-header h1 {
        font-size: 2.2rem;
        font-weight: 700;
        margin: 0;
    }

    .page-header p {
        font-size: 1.1rem;
        opacity: 0.9;
        margin: 5px 0 0 0;
    }

    .header-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .btn-primary {
        background: #ffd700;
        color: #17a2b8;
    }

    .btn-primary:hover {
        background: #ffed4e;
        transform: translateY(-2px);
        text-decoration: none;
        color: #6f42c1;
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.3);
        text-decoration: none;
        color: white;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .btn-success:hover {
        background: #218838;
    }

    .btn-danger {
        background: #dc3545;
        color: white;
    }

    .btn-danger:hover {
        background: #c82333;
    }

    .btn-warning {
        background: #ffc107;
        color: #212529;
    }

    .btn-warning:hover {
        background: #e0a800;
    }

    .btn-info {
        background: #17a2b8;
        color: white;
    }

    .btn-info:hover {
        background: #138496;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.8rem;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--color);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .stat-card.total {
        --color: #6f42c1;
    }

    .stat-card.pending {
        --color: #ffc107;
    }

    .stat-card.completed {
        --color: #28a745;
    }

    .stat-card.overdue {
        --color: #dc3545;
    }

    .stat-card.monthly {
        --color: #fd7e14;
    }

    .stat-card.upcoming {
        --color: #17a2b8;
    }

    .stat-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .stat-info h3 {
        color: #666;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--color);
        line-height: 1;
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: var(--color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        opacity: 0.9;
    }

    /* Filters Section */
    .filters-section {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .filters-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .filter-group label {
        font-weight: 600;
        color: #555;
        font-size: 0.9rem;
    }

    .filter-group select,
    .filter-group input {
        padding: 10px 12px;
        border: 2px solid #e1e8ed;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.3s ease;
    }

    .filter-group select:focus,
    .filter-group input:focus {
        outline: none;
        border-color: #17a2b8;
    }

    .search-group {
        position: relative;
    }

    .search-group input {
        padding-left: 40px;
    }

    .search-group i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #666;
    }

    /* Vaccinations Table */
    .vaccinations-section {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 25px;
    }

    .section-header {
        background: #f8f9fa;
        padding: 20px 25px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .section-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
    }

    .vaccinations-table {
        width: 100%;
        border-collapse: collapse;
    }

    .vaccinations-table th,
    .vaccinations-table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #f1f1f1;
        vertical-align: top;
    }

    .vaccinations-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #2c3e50;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .vaccinations-table tr {
        transition: background-color 0.3s ease;
    }

    .vaccinations-table tr:hover {
        background: #f8f9fa;
    }

    /* Pet Info */
    .pet-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .pet-photo {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        object-fit: cover;
        background: #f8f9fa;
    }

    .pet-details h4 {
        font-size: 0.95rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 2px;
    }

    .pet-meta {
        font-size: 0.8rem;
        color: #666;
    }

    /* Vaccine Info */
    .vaccine-info h4 {
        font-size: 0.95rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 2px;
    }

    .vaccine-meta {
        font-size: 0.8rem;
        color: #666;
    }

    /* Status Badges */
    .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }

    .status-completed {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
    }

    .status-pending {
        background: rgba(255, 193, 7, 0.2);
        color: #ffc107;
    }

    .status-overdue {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
    }

    /* Actions */
    .actions-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    /* Modals */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background: white;
        margin: 3% auto;
        padding: 0;
        border-radius: 15px;
        width: 90%;
        max-width: 700px;
        position: relative;
        max-height: 90vh;
        overflow: hidden;
    }

    .modal-header {
        background: linear-gradient(135deg, #17a2b8, #6f42c1);
        color: white;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title {
        font-size: 1.3rem;
        font-weight: 600;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: white;
        opacity: 0.8;
    }

    .modal-close:hover {
        opacity: 1;
    }

    .modal-body {
        padding: 25px;
        max-height: 65vh;
        overflow-y: auto;
    }

    .modal-actions {
        padding: 20px 25px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        background: #f8f9fa;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #2c3e50;
    }

    .form-group select,
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid #e1e8ed;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.3s ease;
        font-family: inherit;
    }

    .form-group select:focus,
    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #17a2b8;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }

    .form-group.required label::after {
        content: ' *';
        color: #dc3545;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 30px;
        color: #666;
    }

    .empty-icon {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
        color: #17a2b8;
    }

    .empty-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 10px;
        color: #2c3e50;
    }

    .empty-text {
        margin-bottom: 20px;
        line-height: 1.6;
    }

    /* Pagination */
    .pagination-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 25px;
        background: #f8f9fa;
        border-top: 1px solid #eee;
    }

    .pagination-info {
        font-size: 0.9rem;
        color: #666;
    }

    .pagination {
        display: flex;
        gap: 5px;
    }

    .pagination a,
    .pagination span {
        padding: 8px 12px;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        text-decoration: none;
        color: #495057;
        font-size: 0.85rem;
        transition: all 0.3s ease;
    }

    .pagination a:hover {
        background: #e9ecef;
        text-decoration: none;
    }

    .pagination .current {
        background: #17a2b8;
        color: white;
        border-color: #17a2b8;
    }

    .pagination .disabled {
        opacity: 0.5;
        pointer-events: none;
    }

    /* Messages */
    .message {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 10px;
        z-index: 1001;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        max-width: 400px;
        animation: slideInRight 0.5s ease-out;
    }

    .message.success {
        background: #28a745;
        color: white;
    }

    .message.error {
        background: #dc3545;
        color: white;
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-in {
        animation: fadeIn 0.6s ease-out;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .page-header {
            flex-direction: column;
            text-align: center;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .vaccinations-table {
            font-size: 0.85rem;
        }

        .vaccinations-table th,
        .vaccinations-table td {
            padding: 10px 8px;
        }

        .modal-content {
            margin: 2% auto;
            width: 95%;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .pet-info {
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
        }
    }
    </style>
</head>

<body>
    <!-- Include Admin Navbar -->
    <?php include_once __DIR__ . '/../common/navbar_admin.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <div>
                <h1><i class="fas fa-syringe"></i> Manage Vaccinations</h1>
                <p>Track and manage pet vaccination records and schedules</p>
            </div>
            <div class="header-actions">
                <button onclick="showAddVaccinationModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Vaccination
                </button>
                <button onclick="exportVaccinations()" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export Data
                </button>
                <button onclick="refreshData()" class="btn btn-secondary">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card total">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Records</h3>
                        <div class="stat-number"><?php echo $stats['total_vaccinations']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card overdue" onclick="filterByStatus('overdue')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Overdue</h3>
                        <div class="stat-number"><?php echo $stats['overdue_vaccinations']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pending" onclick="filterByStatus('pending')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Pending</h3>
                        <div class="stat-number"><?php echo $stats['pending_vaccinations']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card completed" onclick="filterByStatus('completed')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Completed</h3>
                        <div class="stat-number"><?php echo $stats['completed_vaccinations']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card upcoming">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Upcoming (30 days)</h3>
                        <div class="stat-number"><?php echo $stats['upcoming_vaccinations']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card monthly">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>This Month</h3>
                        <div class="stat-number"><?php echo $stats['this_month_vaccinations']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section fade-in">
            <div class="filters-title">
                <i class="fas fa-filter"></i>
                Filter & Search Vaccinations
            </div>
            <form method="GET" action="" id="filtersForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" onchange="document.getElementById('filtersForm').submit()">
                            <option value="">All Statuses</option>
                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>
                                Completed</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>
                                Pending</option>
                            <option value="overdue" <?php echo $filter_status === 'overdue' ? 'selected' : ''; ?>>
                                Overdue</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Shelter</label>
                        <select name="shelter" onchange="document.getElementById('filtersForm').submit()">
                            <option value="">All Shelters</option>
                            <?php foreach ($shelters as $shelter): ?>
                            <option value="<?php echo $shelter['shelter_id']; ?>"
                                <?php echo $filter_shelter == $shelter['shelter_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($shelter['shelter_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>

                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>

                    <div class="filter-group search-group">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                            placeholder="Search pet, vaccine, vet..." onkeypress="handleSearchKeypress(event)">
                        <i class="fas fa-search"></i>
                    </div>

                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 5px;">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>

                    <div class="filter-group">
                        <a href="<?php echo $BASE_URL; ?>admin/manageVaccinations.php" class="btn btn-secondary"
                            style="width: 100%; margin-top: 5px; text-align: center;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Vaccinations Table -->
        <div class="vaccinations-section fade-in">
            <div class="section-header">
                <h2 class="section-title">
                    Vaccination Records
                    <?php if ($total_vaccinations > 0): ?>
                    <span style="color: #666; font-weight: normal; font-size: 0.9rem;">
                        (<?php echo number_format($total_vaccinations); ?> total)
                    </span>
                    <?php endif; ?>
                </h2>
                <div style="display: flex; gap: 10px;">
                    <span style="font-size: 0.9rem; color: #666;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                </div>
            </div>

            <?php if (empty($vaccinations)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-syringe"></i>
                </div>
                <h3 class="empty-title">No Vaccination Records Found</h3>
                <p class="empty-text">
                    <?php if (!empty($filter_status) || !empty($search_query) || !empty($filter_shelter)): ?>
                    No vaccination records match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                    There are no vaccination records in the system yet.
                    <?php endif; ?>
                </p>
                <?php if (!empty($filter_status) || !empty($search_query) || !empty($filter_shelter)): ?>
                <a href="<?php echo $BASE_URL; ?>admin/manageVaccinations.php" class="btn btn-primary">
                    <i class="fas fa-eye"></i> View All Records
                </a>
                <?php else: ?>
                <button onclick="showAddVaccinationModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add First Vaccination
                </button>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <!-- Vaccinations Table -->
            <div style="overflow-x: auto;">
                <table class="vaccinations-table">
                    <thead>
                        <tr>
                            <th>Pet Information</th>
                            <th>Vaccine Details</th>
                            <th>Dates</th>
                            <th>Veterinarian</th>
                            <th>Status</th>
                            <th>Shelter</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vaccinations as $vaccination): ?>
                        <tr data-vaccination-id="<?php echo $vaccination['vaccination_id']; ?>">
                            <td>
                                <div class="pet-info">
                                    <?php if (!empty($vaccination['primary_image'])): ?>
                                    <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($vaccination['primary_image']); ?>"
                                        alt="<?php echo htmlspecialchars($vaccination['pet_name']); ?>"
                                        class="pet-photo">
                                    <?php else: ?>
                                    <div class="pet-photo"
                                        style="display: flex; align-items: center; justify-content: center; background: #f8f9fa; color: #666;">
                                        <i class="fas fa-paw"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div class="pet-details">
                                        <h4><?php echo htmlspecialchars($vaccination['pet_name'] ?? 'Unknown Pet'); ?>
                                        </h4>
                                        <div class="pet-meta">
                                            <div><i class="fas fa-info-circle"></i>
                                                <?php echo htmlspecialchars(($vaccination['category_name'] ?? 'Unknown') . ' • ' . ($vaccination['breed_name'] ?? 'Mixed')); ?>
                                            </div>
                                            <div><i class="fas fa-birthday-cake"></i>
                                                <?php echo htmlspecialchars($vaccination['age'] ?? 'Unknown'); ?> years
                                                old</div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="vaccine-info">
                                    <h4><?php echo htmlspecialchars($vaccination['vaccine_name'] ?? 'Unknown Vaccine'); ?>
                                    </h4>
                                    <div class="vaccine-meta">
                                        <?php if (!empty($vaccination['notes'])): ?>
                                        <div style="margin-top: 5px; font-size: 0.75rem; color: #666;">
                                            <i class="fas fa-sticky-note"></i>
                                            <?php echo htmlspecialchars(substr($vaccination['notes'], 0, 50)) . (strlen($vaccination['notes']) > 50 ? '...' : ''); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem;">
                                    <?php if (!empty($vaccination['vaccination_date'])): ?>
                                    <div style="margin-bottom: 5px;">
                                        <strong style="color: #28a745;">Administered:</strong><br>
                                        <span
                                            style="color: #2c3e50;"><?php echo date('M j, Y', strtotime($vaccination['vaccination_date'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($vaccination['next_due_date'])): ?>
                                    <div>
                                        <strong
                                            style="color: <?php echo strtotime($vaccination['next_due_date']) < time() && empty($vaccination['vaccination_date']) ? '#dc3545' : '#17a2b8'; ?>;">Next
                                            Due:</strong><br>
                                        <span
                                            style="color: #2c3e50;"><?php echo date('M j, Y', strtotime($vaccination['next_due_date'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem;">
                                    <?php if (!empty($vaccination['veterinarian_name'])): ?>
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <i class="fas fa-user-md"></i>
                                        <?php echo htmlspecialchars($vaccination['veterinarian_name']); ?>
                                    </div>
                                    <?php else: ?>
                                    <span style="color: #999; font-size: 0.8rem;">Not specified</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                    $status_class = $vaccination['vaccination_status'] ?? 'unknown';
                                    $status_icon = [
                                        'completed' => 'check-circle',
                                        'pending' => 'hourglass-half',
                                        'overdue' => 'exclamation-triangle'
                                    ][$status_class] ?? 'question-circle';
                                ?>
                                <span class="status-badge status-<?php echo $status_class; ?>">
                                    <i class="fas fa-<?php echo $status_icon; ?>"></i>
                                    <?php echo ucfirst($status_class); ?>
                                </span>
                                <?php if ($status_class === 'overdue' && !empty($vaccination['next_due_date'])): ?>
                                <div style="font-size: 0.7rem; color: #dc3545; margin-top: 4px;">
                                    <?php 
                                        $days_overdue = floor((time() - strtotime($vaccination['next_due_date'])) / (60*60*24));
                                        echo $days_overdue . ' days overdue';
                                    ?>
                                </div>
                                <?php elseif ($status_class === 'pending' && !empty($vaccination['next_due_date'])): ?>
                                <div style="font-size: 0.7rem; color: #ffc107; margin-top: 4px;">
                                    <?php 
                                        $days_until = floor((strtotime($vaccination['next_due_date']) - time()) / (60*60*24));
                                        echo max(0, $days_until) . ' days remaining';
                                    ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem;">
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo htmlspecialchars($vaccination['shelter_name'] ?? 'Unknown Shelter'); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="actions-group">
                                    <button class="btn btn-info btn-sm"
                                        onclick="viewVaccinationDetails(<?php echo $vaccination['vaccination_id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-warning btn-sm"
                                        onclick="editVaccination(<?php echo $vaccination['vaccination_id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm"
                                        onclick="confirmDeleteVaccination(<?php echo $vaccination['vaccination_id']; ?>, '<?php echo htmlspecialchars(($vaccination['vaccine_name'] ?? 'Unknown') . ' - ' . ($vaccination['pet_name'] ?? 'Unknown Pet'), ENT_QUOTES); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-section">
                <div class="pagination-info">
                    Showing <?php echo (($page - 1) * $per_page) + 1; ?> to
                    <?php echo min($page * $per_page, $total_vaccinations); ?> of
                    <?php echo number_format($total_vaccinations); ?> records
                </div>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <?php else: ?>
                    <span class="disabled">
                        <i class="fas fa-chevron-left"></i> Previous
                    </span>
                    <?php endif; ?>

                    <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                    <?php if ($start > 2): ?>
                    <span>...</span>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?>
                    <span>...</span>
                    <?php endif; ?>
                    <a
                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php else: ?>
                    <span class="disabled">
                        Next <i class="fas fa-chevron-right"></i>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Vaccination Modal -->
    <div id="vaccinationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Vaccination Record</h3>
                <button class="modal-close" onclick="closeModal('vaccinationModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="vaccinationForm">
                    <input type="hidden" id="vaccinationId" name="vaccination_id">
                    <input type="hidden" id="actionType" name="action" value="add_vaccination">

                    <div class="form-grid">
                        <div class="form-group required">
                            <label for="petSelect">Select Pet</label>
                            <select id="petSelect" name="pet_id" required>
                                <option value="">Choose a pet...</option>
                                <?php 
                                $current_shelter = '';
                                foreach ($pets as $pet): 
                                    if ($current_shelter !== ($pet['shelter_name'] ?? 'Unknown')):
                                        if ($current_shelter !== '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($pet['shelter_name'] ?? 'Unknown Shelter') . '">';
                                        $current_shelter = $pet['shelter_name'] ?? 'Unknown';
                                    endif;
                                ?>
                                <option value="<?php echo $pet['pet_id']; ?>">
                                    <?php echo htmlspecialchars(($pet['pet_name'] ?? 'Unknown') . ' - ' . ($pet['category_name'] ?? 'Unknown') . ' (' . ($pet['breed_name'] ?? 'Mixed') . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                                <?php if ($current_shelter !== '') echo '</optgroup>'; ?>
                            </select>
                        </div>

                        <div class="form-group required">
                            <label for="vaccineName">Vaccine Name</label>
                            <input type="text" id="vaccineName" name="vaccine_name" list="commonVaccines"
                                placeholder="e.g., Rabies, DHPP, FVRCP" required>
                            <datalist id="commonVaccines">
                                <?php foreach ($common_vaccines as $vaccine): ?>
                                <option value="<?php echo htmlspecialchars($vaccine); ?>">
                                    <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="vaccinationDate">Vaccination Date</label>
                            <input type="date" id="vaccinationDate" name="vaccination_date">
                        </div>

                        <div class="form-group">
                            <label for="nextDueDate">Next Due Date</label>
                            <input type="date" id="nextDueDate" name="next_due_date">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="veterinarianName">Veterinarian Name</label>
                        <input type="text" id="veterinarianName" name="veterinarian_name"
                            placeholder="Dr. Name or Clinic Name">
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes"
                            placeholder="Additional notes about the vaccination..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('vaccinationModal')">Cancel</button>
                <button class="btn btn-success" onclick="saveVaccination()">
                    <i class="fas fa-save"></i> Save Vaccination
                </button>
            </div>
        </div>
    </div>

    <!-- View Vaccination Details Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Vaccination Details</h3>
                <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewModalContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Vaccination Deletion</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"
                        style="font-size: 3rem; color: #dc3545; margin-bottom: 15px;"></i>
                </div>
                <p><strong>Are you sure you want to delete this vaccination record?</strong></p>
                <p id="deleteVaccinationName" style="color: #666; margin: 10px 0;"></p>
                <div
                    style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <p style="margin: 0; color: #856404;"><strong>⚠️ Warning:</strong></p>
                    <ul style="margin: 10px 0 0 20px; color: #856404;">
                        <li>This action cannot be undone</li>
                        <li>All vaccination data will be permanently deleted</li>
                        <li>This may affect pet health tracking</li>
                    </ul>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDeleteVaccination()">
                    <i class="fas fa-trash"></i> Delete Record
                </button>
            </div>
        </div>
    </div>

    <script>
    // Global variables
    let currentVaccinationId = null;
    let isEditMode = false;

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });
    });

    // Filter functions
    function filterByStatus(status) {
        const url = new URL(window.location);
        if (status) {
            url.searchParams.set('status', status);
        } else {
            url.searchParams.delete('status');
        }
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    function handleSearchKeypress(event) {
        if (event.key === 'Enter') {
            document.getElementById('filtersForm').submit();
        }
    }

    // Show add vaccination modal
    function showAddVaccinationModal() {
        isEditMode = false;
        document.getElementById('modalTitle').textContent = 'Add Vaccination Record';
        document.getElementById('actionType').value = 'add_vaccination';
        document.getElementById('vaccinationForm').reset();
        document.getElementById('vaccinationId').value = '';
        document.getElementById('vaccinationModal').style.display = 'block';
    }

    // Edit vaccination
    function editVaccination(vaccinationId) {
        isEditMode = true;
        currentVaccinationId = vaccinationId;
        document.getElementById('modalTitle').textContent = 'Edit Vaccination Record';
        document.getElementById('actionType').value = 'update_vaccination';
        document.getElementById('vaccinationId').value = vaccinationId;

        // Fetch vaccination details via AJAX
        const formData = new FormData();
        formData.append('action', 'get_vaccination_details');
        formData.append('vaccination_id', vaccinationId);

        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const vaccination = data.vaccination;
                    document.getElementById('petSelect').value = vaccination.pet_id || '';
                    document.getElementById('vaccineName').value = vaccination.vaccine_name || '';
                    document.getElementById('vaccinationDate').value = vaccination.vaccination_date || '';
                    document.getElementById('nextDueDate').value = vaccination.next_due_date || '';
                    document.getElementById('veterinarianName').value = vaccination.veterinarian_name || '';
                    document.getElementById('notes').value = vaccination.notes || '';

                    document.getElementById('vaccinationModal').style.display = 'block';
                } else {
                    showMessage('Failed to load vaccination details', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error loading vaccination details', 'error');
            });
    }

    // View vaccination details
    function viewVaccinationDetails(vaccinationId) {
        currentVaccinationId = vaccinationId;

        // Show loading
        document.getElementById('viewModalContent').innerHTML =
            '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        document.getElementById('viewModal').style.display = 'block';

        // Fetch vaccination details
        const formData = new FormData();
        formData.append('action', 'get_vaccination_details');
        formData.append('vaccination_id', vaccinationId);

        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const vaccination = data.vaccination;
                    const detailsHtml = `
                        <div style="display: grid; gap: 15px;">
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <h4 style="margin-bottom: 10px; color: #2c3e50;">Pet Information</h4>
                                <p style="margin: 0;"><strong>Name:</strong> ${vaccination.pet_name || 'Unknown'}</p>
                                <p style="margin: 0;"><strong>Category:</strong> ${vaccination.category_name || 'Unknown'}</p>
                                <p style="margin: 0;"><strong>Breed:</strong> ${vaccination.breed_name || 'Unknown'}</p>
                                <p style="margin: 0;"><strong>Shelter:</strong> ${vaccination.shelter_name || 'Unknown'}</p>
                            </div>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <h4 style="margin-bottom: 10px; color: #2c3e50;">Vaccination Information</h4>
                                <p style="margin: 0;"><strong>Vaccine:</strong> ${vaccination.vaccine_name || 'N/A'}</p>
                                <p style="margin: 0;"><strong>Administered Date:</strong> ${vaccination.vaccination_date ? new Date(vaccination.vaccination_date).toLocaleDateString() : 'Not administered'}</p>
                                <p style="margin: 0;"><strong>Next Due Date:</strong> ${vaccination.next_due_date ? new Date(vaccination.next_due_date).toLocaleDateString() : 'Not set'}</p>
                                <p style="margin: 0;"><strong>Veterinarian:</strong> ${vaccination.veterinarian_name || 'Not specified'}</p>
                            </div>
                            ${vaccination.notes ? `
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <h4 style="margin-bottom: 10px; color: #2c3e50;">Notes</h4>
                                <p style="margin: 0; white-space: pre-wrap;">${vaccination.notes}</p>
                            </div>
                            ` : ''}
                        </div>
                    `;
                    document.getElementById('viewModalContent').innerHTML = detailsHtml;
                } else {
                    document.getElementById('viewModalContent').innerHTML =
                        '<div style="color: #dc3545; text-align: center;">Failed to load vaccination details</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('viewModalContent').innerHTML =
                    '<div style="color: #dc3545; text-align: center;">Error loading vaccination details</div>';
            });
    }

    // Save vaccination
    function saveVaccination() {
        const form = document.getElementById('vaccinationForm');
        const formData = new FormData(form);

        // Validate required fields
        const petId = formData.get('pet_id');
        const vaccineName = formData.get('vaccine_name');

        if (!petId || !vaccineName) {
            showMessage('Please fill in all required fields', 'error');
            return;
        }

        performAjaxAction(formData);
        closeModal('vaccinationModal');
    }

    // Confirm delete vaccination
    function confirmDeleteVaccination(vaccinationId, vaccinationName) {
        currentVaccinationId = vaccinationId;
        document.getElementById('deleteVaccinationName').textContent = `Vaccination: ${vaccinationName}`;
        document.getElementById('deleteModal').style.display = 'block';
    }

    function confirmDeleteVaccination() {
        if (currentVaccinationId) {
            const formData = new FormData();
            formData.append('action', 'delete_vaccination');
            formData.append('vaccination_id', currentVaccinationId);

            performAjaxAction(formData);
            closeModal('deleteModal');
        }
    }

    // Close modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        currentVaccinationId = null;
        isEditMode = false;
    }

    // Perform AJAX action
    function performAjaxAction(formData) {
        // Show loading state
        document.body.style.opacity = '0.7';
        document.body.style.pointerEvents = 'none';

        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.style.opacity = '1';
                document.body.style.pointerEvents = 'auto';

                if (data.success) {
                    showMessage(data.message, 'success');
                    // Refresh page after successful action
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage(data.message || 'An error occurred', 'error');
                }
            })
            .catch(error => {
                document.body.style.opacity = '1';
                document.body.style.pointerEvents = 'auto';
                console.error('Error:', error);
                showMessage('Network error occurred', 'error');
            });
    }

    // Show message notification
    function showMessage(message, type) {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.message');
        existingMessages.forEach(msg => msg.remove());

        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;

        document.body.appendChild(messageDiv);

        // Auto remove after 5 seconds
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }

    // Refresh data
    function refreshData() {
        window.location.reload();
    }

    // Export vaccinations data
    function exportVaccinations() {
        showMessage('Export functionality will be implemented', 'info');
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(event) {
        // Escape key to close modals
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => modal.style.display = 'none');
            currentVaccinationId = null;
            isEditMode = false;
        }

        // Ctrl+R to refresh
        if (event.ctrlKey && event.key === 'r') {
            event.preventDefault();
            refreshData();
        }

        // Ctrl+N to add new vaccination
        if (event.ctrlKey && event.key === 'n') {
            event.preventDefault();
            showAddVaccinationModal();
        }
    });
    </script>
</body>

</html>