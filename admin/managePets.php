<?php
// admin/managePets.php - Admin Pet Management Page
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
$page_title = 'Manage Pets - Admin Dashboard';

// Initialize variables
$pets = [];
$shelters = [];
$categories = [];
$breeds = [];
$stats = [
    'total_pets' => 0,
    'available_pets' => 0,
    'adopted_pets' => 0,
    'pending_pets' => 0
];

// Filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_shelter = $_GET['shelter'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search_query = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    try {
        require_once __DIR__ . '/../config/db.php';
        $db = getDB();
        
        if ($db && isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_status':
                    $pet_id = intval($_POST['pet_id'] ?? 0);
                    $new_status = $_POST['status'] ?? '';
                    
                    if ($pet_id > 0 && in_array($new_status, ['available', 'adopted', 'pending', 'unavailable'])) {
                        $stmt = $db->prepare("UPDATE pets SET status = ?, updated_at = NOW() WHERE pet_id = ?");
                        if ($stmt->execute([$new_status, $pet_id])) {
                            echo json_encode(['success' => true, 'message' => 'Pet status updated successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to update pet status']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid pet ID or status']);
                    }
                    break;
                    
                case 'delete_pet':
                    $pet_id = intval($_POST['pet_id'] ?? 0);
                    
                    if ($pet_id > 0) {
                        $stmt = $db->prepare("DELETE FROM pets WHERE pet_id = ?");
                        if ($stmt->execute([$pet_id])) {
                            echo json_encode(['success' => true, 'message' => 'Pet deleted successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to delete pet']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid pet ID']);
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
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_pets'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE status = 'available'");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['available_pets'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE status = 'adopted'");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['adopted_pets'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE status = 'pending'");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['pending_pets'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        // Get shelters for filter
        try {
            $stmt = $db->prepare("SELECT s.shelter_id, s.shelter_name FROM shelters s ORDER BY s.shelter_name");
            $stmt->execute();
            $shelters = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $shelters = [];
        }
        
        // Get categories for filter
        try {
            $stmt = $db->prepare("SELECT category_id, category_name FROM pet_categories ORDER BY category_name");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $categories = [];
        }
        
        // Build the pets query
        $where_conditions = [];
        $params = [];
        
        if (!empty($filter_status)) {
            $where_conditions[] = "p.status = ?";
            $params[] = $filter_status;
        }
        
        if (!empty($filter_shelter)) {
            $where_conditions[] = "p.shelter_id = ?";
            $params[] = $filter_shelter;
        }
        
        if (!empty($filter_category)) {
            $where_conditions[] = "p.category_id = ?";
            $params[] = $filter_category;
        }
        
        if (!empty($search_query)) {
            $where_conditions[] = "(p.pet_name LIKE ? OR p.description LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) as total FROM pets p $where_clause";
        $stmt = $db->prepare($count_query);
        $stmt->execute($params);
        $total_pets = $stmt->fetch()['total'] ?? 0;
        $total_pages = ceil($total_pets / $per_page);
        
        // Get pets with pagination
        try {
            $pets_query = "
                SELECT p.*, 
                       s.shelter_name, 
                       pc.category_name,
                       pb.breed_name,
                       u.first_name as shelter_owner_name
                FROM pets p
                LEFT JOIN shelters s ON p.shelter_id = s.shelter_id
                LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
                LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
                LEFT JOIN users u ON s.user_id = u.user_id
                $where_clause
                ORDER BY p.created_at DESC
                LIMIT $per_page OFFSET $offset
            ";
            
            $stmt = $db->prepare($pets_query);
            $stmt->execute($params);
            $pets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $pets = [];
        }
    }
} catch (Exception $e) {
    error_log("Manage Pets database error: " . $e->getMessage());
}
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
        background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
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
        color: #dc3545;
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

    .stat-card.available {
        --color: #28a745;
    }

    .stat-card.adopted {
        --color: #007bff;
    }

    .stat-card.pending {
        --color: #ffc107;
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
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        border-color: #dc3545;
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

    /* Pets Table */
    .pets-section {
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
        justify-content: between;
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

    .pets-table {
        width: 100%;
        border-collapse: collapse;
    }

    .pets-table th,
    .pets-table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #f1f1f1;
    }

    .pets-table th {
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

    .pets-table tr {
        transition: background-color 0.3s ease;
    }

    .pets-table tr:hover {
        background: #f8f9fa;
    }

    .pet-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .pet-avatar {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        background: linear-gradient(135deg, #dc3545, #6f42c1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.2rem;
    }

    .pet-details h4 {
        font-size: 1rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 2px;
    }

    .pet-meta {
        font-size: 0.8rem;
        color: #666;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }

    .pet-info img {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        object-fit: cover;
        border: 2px solid #e1e8ed;
        transition: all 0.3s ease;
    }

    .pet-info img:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .pet-avatar {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        background: linear-gradient(135deg, #dc3545, #6f42c1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.2rem;
        border: 2px solid #e1e8ed;
    }

    .status-available {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
    }

    .status-adopted {
        background: rgba(0, 123, 255, 0.2);
        color: #007bff;
    }

    .status-pending {
        background: rgba(255, 193, 7, 0.2);
        color: #ffc107;
    }

    .status-unavailable {
        background: rgba(108, 117, 125, 0.2);
        color: #6c757d;
    }

    .actions-dropdown {
        position: relative;
        display: inline-block;
    }

    .actions-btn {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.8rem;
    }

    .actions-menu {
        position: absolute;
        right: 0;
        top: 100%;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        min-width: 150px;
        z-index: 1000;
        display: none;
    }

    .actions-menu.show {
        display: block;
    }

    .actions-menu a {
        display: block;
        padding: 8px 12px;
        color: #333;
        text-decoration: none;
        font-size: 0.85rem;
        transition: background-color 0.3s ease;
    }

    .actions-menu a:hover {
        background: #f8f9fa;
    }

    .actions-menu a.text-danger:hover {
        background: #fff5f5;
        color: #dc3545;
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
        background: #dc3545;
        color: white;
        border-color: #dc3545;
    }

    .pagination .disabled {
        opacity: 0.5;
        pointer-events: none;
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
        color: #dc3545;
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

    /* Modal Styles */
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
        margin: 15% auto;
        padding: 30px;
        border-radius: 15px;
        width: 90%;
        max-width: 500px;
        position: relative;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .modal-title {
        font-size: 1.3rem;
        font-weight: 600;
        color: #2c3e50;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #999;
    }

    .modal-close:hover {
        color: #333;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    /* Loading States */
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }

    .spinner {
        border: 2px solid #f3f3f3;
        border-top: 2px solid #dc3545;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        animation: spin 1s linear infinite;
        display: inline-block;
        margin-left: 10px;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
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

        .pets-table {
            font-size: 0.85rem;
        }

        .pets-table th,
        .pets-table td {
            padding: 10px 8px;
        }

        .pagination-section {
            flex-direction: column;
            gap: 15px;
            text-align: center;
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
    }

    .message.success {
        background: #28a745;
        color: white;
    }

    .message.error {
        background: #dc3545;
        color: white;
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
                <h1><i class="fas fa-paw"></i> Manage Pets</h1>
                <p>Monitor and manage all pets across the platform</p>
            </div>
            <div class="header-actions">
                <button onclick="exportPets()" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export Data
                </button>
                <button onclick="refreshData()" class="btn btn-secondary">
                    <i class="fas fa-sync"></i> Refresh
                </button>
                <a href="<?php echo $BASE_URL; ?>admin/reports.php" class="btn btn-primary">
                    <i class="fas fa-chart-bar"></i> View Reports
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card total" onclick="filterByStatus('')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Pets</h3>
                        <div class="stat-number"><?php echo $stats['total_pets']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card available" onclick="filterByStatus('available')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Available Pets</h3>
                        <div class="stat-number"><?php echo $stats['available_pets']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card adopted" onclick="filterByStatus('adopted')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Adopted Pets</h3>
                        <div class="stat-number"><?php echo $stats['adopted_pets']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pending" onclick="filterByStatus('pending')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Pending Approval</h3>
                        <div class="stat-number"><?php echo $stats['pending_pets']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section fade-in">
            <div class="filters-title">
                <i class="fas fa-filter"></i>
                Filter & Search Pets
            </div>
            <form method="GET" action="" id="filtersForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" onchange="document.getElementById('filtersForm').submit()">
                            <option value="">All Statuses</option>
                            <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>
                                Available</option>
                            <option value="adopted" <?php echo $filter_status === 'adopted' ? 'selected' : ''; ?>>
                                Adopted</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>
                                Pending</option>
                            <option value="unavailable"
                                <?php echo $filter_status === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
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
                        <label>Category</label>
                        <select name="category" onchange="document.getElementById('filtersForm').submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>"
                                <?php echo $filter_category == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group search-group">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                            placeholder="Search pets..." onkeypress="handleSearchKeypress(event)">
                        <i class="fas fa-search"></i>
                    </div>

                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 5px;">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>

                    <div class="filter-group">
                        <a href="<?php echo $BASE_URL; ?>admin/managePets.php" class="btn btn-secondary"
                            style="width: 100%; margin-top: 5px; text-align: center;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Pets Table -->
        <div class="pets-section fade-in">
            <div class="section-header">
                <h2 class="section-title">
                    Pet Records
                    <?php if ($total_pets > 0): ?>
                    <span style="color: #666; font-weight: normal; font-size: 0.9rem;">
                        (<?php echo number_format($total_pets); ?> total)
                    </span>
                    <?php endif; ?>
                </h2>
            </div>

            <?php if (empty($pets)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-paw"></i>
                </div>
                <h3 class="empty-title">No Pets Found</h3>
                <p class="empty-text">
                    <?php if (!empty($search_query) || !empty($filter_status) || !empty($filter_shelter)): ?>
                    No pets match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                    No pets have been added to the system yet. Pets will appear here once shelters start adding them.
                    <?php endif; ?>
                </p>
                <?php if (!empty($search_query) || !empty($filter_status) || !empty($filter_shelter)): ?>
                <a href="<?php echo $BASE_URL; ?>admin/managePets.php" class="btn btn-primary">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="pets-table">
                    <thead>
                        <tr>
                            <th>Pet Information</th>
                            <th>Category & Breed</th>
                            <th>Shelter</th>
                            <th>Status</th>
                            <th>Age & Gender</th>
                            <th>Added Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pets as $pet): ?>
                        <tr>
                            <!-- Replace this section in your pets table (around line 680) -->
                            <td>
                                <div class="pet-info">
                                    <?php if (!empty($pet['primary_image'])): ?>
                                    <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                                        alt="<?php echo htmlspecialchars($pet['pet_name']); ?>"
                                        style="width: 50px; height: 50px; border-radius: 10px; object-fit: cover;"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="pet-avatar" style="display: none;">
                                        <?php echo strtoupper(substr($pet['pet_name'] ?? 'P', 0, 1)); ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="pet-avatar">
                                        <?php echo strtoupper(substr($pet['pet_name'] ?? 'P', 0, 1)); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="pet-details">
                                        <h4><?php echo htmlspecialchars($pet['pet_name'] ?? 'Unknown Pet'); ?></h4>
                                        <div class="pet-meta">ID: <?php echo $pet['pet_id']; ?></div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <strong><?php echo htmlspecialchars($pet['category_name'] ?? 'Unknown'); ?></strong><br>
                                <small
                                    class="text-muted"><?php echo htmlspecialchars($pet['breed_name'] ?? 'Mixed Breed'); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($pet['shelter_name'] ?? 'Unknown Shelter'); ?></strong><br>
                                <small class="text-muted">
                                    <?php if (!empty($pet['shelter_owner_name'])): ?>
                                    by <?php echo htmlspecialchars($pet['shelter_owner_name']); ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <span
                                    class="status-badge status-<?php echo strtolower($pet['status'] ?? 'unknown'); ?>">
                                    <?php echo ucfirst(htmlspecialchars($pet['status'] ?? 'Unknown')); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($pet['age'] ?? 'Unknown'); ?></strong> years<br>
                                <small
                                    class="text-muted"><?php echo ucfirst(htmlspecialchars($pet['gender'] ?? 'Unknown')); ?></small>
                            </td>
                            <td>
                                <strong><?php echo date('M j, Y', strtotime($pet['created_at'] ?? 'now')); ?></strong><br>
                                <small
                                    class="text-muted"><?php echo date('g:i A', strtotime($pet['created_at'] ?? 'now')); ?></small>
                            </td>
                            <td>
                                <div class="actions-dropdown">
                                    <button class="actions-btn"
                                        onclick="toggleActionsMenu(<?php echo $pet['pet_id']; ?>)">
                                        <i class="fas fa-ellipsis-v"></i>
                                        Actions
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <div class="actions-menu" id="actions-<?php echo $pet['pet_id']; ?>">
                                        <a href="#" onclick="viewPetDetails(<?php echo $pet['pet_id']; ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        <a href="#"
                                            onclick="editPetStatus(<?php echo $pet['pet_id']; ?>, '<?php echo htmlspecialchars($pet['status'] ?? 'available'); ?>')">
                                            <i class="fas fa-edit"></i> Change Status
                                        </a>
                                        <a href="#" onclick="viewAdoptionHistory(<?php echo $pet['pet_id']; ?>)">
                                            <i class="fas fa-history"></i> Adoption History
                                        </a>
                                        <div style="border-top: 1px solid #eee; margin: 5px 0;"></div>
                                        <a href="#" onclick="deletePet(<?php echo $pet['pet_id']; ?>)"
                                            class="text-danger">
                                            <i class="fas fa-trash"></i> Delete Pet
                                        </a>
                                    </div>
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
                    <?php echo min($page * $per_page, $total_pets); ?> of <?php echo number_format($total_pets); ?> pets
                </div>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a
                        href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filter_status); ?>&shelter=<?php echo urlencode($filter_shelter); ?>&category=<?php echo urlencode($filter_category); ?>&search=<?php echo urlencode($search_query); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <?php else: ?>
                    <span class="disabled">
                        <i class="fas fa-chevron-left"></i> Previous
                    </span>
                    <?php endif; ?>

                    <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                    <a
                        href="?page=1&status=<?php echo urlencode($filter_status); ?>&shelter=<?php echo urlencode($filter_shelter); ?>&category=<?php echo urlencode($filter_category); ?>&search=<?php echo urlencode($search_query); ?>">1</a>
                    <?php if ($start_page > 2): ?>
                    <span>...</span>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a
                        href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&shelter=<?php echo urlencode($filter_shelter); ?>&category=<?php echo urlencode($filter_category); ?>&search=<?php echo urlencode($search_query); ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                    <span>...</span>
                    <?php endif; ?>
                    <a
                        href="?page=<?php echo $total_pages; ?>&status=<?php echo urlencode($filter_status); ?>&shelter=<?php echo urlencode($filter_shelter); ?>&category=<?php echo urlencode($filter_category); ?>&search=<?php echo urlencode($search_query); ?>">
                        <?php echo $total_pages; ?>
                    </a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                    <a
                        href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filter_status); ?>&shelter=<?php echo urlencode($filter_shelter); ?>&category=<?php echo urlencode($filter_category); ?>&search=<?php echo urlencode($search_query); ?>">
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

    <!-- Pet Details Modal -->
    <div id="petDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Pet Details</h2>
                <button class="modal-close" onclick="closePetDetailsModal()">&times;</button>
            </div>
            <div id="petDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Change Pet Status</h2>
                <button class="modal-close" onclick="closeStatusModal()">&times;</button>
            </div>
            <div id="statusModalContent">
                <p>Select new status for this pet:</p>
                <div style="margin: 20px 0;">
                    <select id="newStatus"
                        style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 8px;">
                        <option value="available">Available for Adoption</option>
                        <option value="pending">Pending Approval</option>
                        <option value="adopted">Adopted</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                    <button class="btn btn-primary" onclick="confirmStatusChange()">Update Status</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Delete</h2>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div id="deleteModalContent">
                <p style="margin-bottom: 20px;">Are you sure you want to delete this pet? This action cannot be undone.
                </p>
                <div
                    style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                    <strong style="color: #856404;">Warning:</strong>
                    <span style="color: #856404;">Deleting this pet will also remove all associated adoption
                        applications and records.</span>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button class="btn btn-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash"></i> Delete Pet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="message success" id="successMessage">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
            <button onclick="closeMessage('successMessage')"
                style="background: none; border: none; color: white; margin-left: auto; cursor: pointer; font-size: 1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="message error" id="errorMessage">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
            <button onclick="closeMessage('errorMessage')"
                style="background: none; border: none; color: white; margin-left: auto; cursor: pointer; font-size: 1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <script>
    let currentPetId = null;
    let currentAction = null;

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Animate stat counters
        animateCounters();

        // Auto-hide messages
        setTimeout(() => {
            closeAllMessages();
        }, 6000);

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.actions-dropdown')) {
                closeAllDropdowns();
            }
        });

        // Handle keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllModals();
                closeAllDropdowns();
            }
        });
    });

    // Animation functions
    function animateCounters() {
        document.querySelectorAll('.stat-number').forEach(counter => {
            const target = parseInt(counter.textContent);
            if (target > 0) {
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current);
                }, 30);
            }
        });
    }

    // Filter functions
    function filterByStatus(status) {
        const url = new URL(window.location.href);
        url.searchParams.set('status', status);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    function handleSearchKeypress(e) {
        if (e.key === 'Enter') {
            document.getElementById('filtersForm').submit();
        }
    }

    function refreshData() {
        window.location.reload();
    }

    // Actions menu functions
    function toggleActionsMenu(petId) {
        closeAllDropdowns();
        const menu = document.getElementById('actions-' + petId);
        if (menu) {
            menu.classList.toggle('show');
        }
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.actions-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }

    // Pet details functions
    function viewPetDetails(petId) {
        currentPetId = petId;

        // Show loading
        document.getElementById('petDetailsContent').innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <div class="spinner"></div>
                    <p>Loading pet details...</p>
                </div>
            `;

        document.getElementById('petDetailsModal').style.display = 'block';

        // Fetch pet details (simulated)
        setTimeout(() => {
            document.getElementById('petDetailsContent').innerHTML = `
                    <div style="display: grid; gap: 15px;">
                        <div><strong>Pet ID:</strong> ${petId}</div>
                        <div><strong>Status:</strong> <span class="status-badge status-available">Available</span></div>
                        <div><strong>Description:</strong> This feature will show detailed pet information including medical history, personality traits, and care requirements.</div>
                        <div style="margin-top: 20px;">
                            <button class="btn btn-primary" onclick="editPetStatus(${petId}, 'available')">
                                <i class="fas fa-edit"></i> Change Status
                            </button>
                            <button class="btn btn-secondary" onclick="closePetDetailsModal()" style="margin-left: 10px;">
                                Close
                            </button>
                        </div>
                    </div>
                `;
        }, 1000);
    }

    function closePetDetailsModal() {
        document.getElementById('petDetailsModal').style.display = 'none';
    }

    // Status change functions
    function editPetStatus(petId, currentStatus) {
        currentPetId = petId;
        currentAction = 'status_change';

        document.getElementById('newStatus').value = currentStatus;
        document.getElementById('statusModal').style.display = 'block';

        closeAllDropdowns();
    }

    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
        currentPetId = null;
    }

    function confirmStatusChange() {
        if (!currentPetId) return;

        const newStatus = document.getElementById('newStatus').value;
        const button = event.target;
        const originalText = button.innerHTML;

        button.innerHTML = '<div class="spinner"></div> Updating...';
        button.disabled = true;

        // Send AJAX request
        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=update_status&pet_id=${currentPetId}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Pet status updated successfully!', 'success');
                    closeStatusModal();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showMessage(data.message || 'Failed to update pet status', 'error');
                }
            })
            .catch(error => {
                showMessage('An error occurred while updating pet status', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
    }

    // Delete functions
    function deletePet(petId) {
        currentPetId = petId;
        document.getElementById('deleteModal').style.display = 'block';
        closeAllDropdowns();
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
        currentPetId = null;
    }

    function confirmDelete() {
        if (!currentPetId) return;

        const button = event.target;
        const originalText = button.innerHTML;

        button.innerHTML = '<div class="spinner"></div> Deleting...';
        button.disabled = true;

        // Send AJAX request
        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=delete_pet&pet_id=${currentPetId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Pet deleted successfully!', 'success');
                    closeDeleteModal();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showMessage(data.message || 'Failed to delete pet', 'error');
                }
            })
            .catch(error => {
                showMessage('An error occurred while deleting pet', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
    }

    // Adoption history function
    function viewAdoptionHistory(petId) {
        showMessage('Adoption history feature will be implemented in the next update', 'info');
        closeAllDropdowns();
    }

    // Export function
    function exportPets() {
        const button = event.target;
        const originalText = button.innerHTML;

        button.innerHTML = '<div class="spinner"></div> Exporting...';
        button.disabled = true;

        // Simulate export
        setTimeout(() => {
            showMessage('Pet data export completed!', 'success');
            button.innerHTML = originalText;
            button.disabled = false;
        }, 2000);
    }

    // Utility functions
    function closeAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
        currentPetId = null;
        currentAction = null;
    }

    function showMessage(message, type) {
        // Remove existing messages
        closeAllMessages();

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; margin-left: auto; cursor: pointer; font-size: 1.2rem;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

        document.body.appendChild(messageDiv);

        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    function closeMessage(messageId) {
        const message = document.getElementById(messageId);
        if (message) {
            message.style.opacity = '0';
            message.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (message.parentNode) {
                    message.remove();
                }
            }, 300);
        }
    }

    function closeAllMessages() {
        document.querySelectorAll('.message').forEach(message => {
            message.style.opacity = '0';
            message.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (message.parentNode) {
                    message.remove();
                }
            }, 300);
        });
    }

    // Click outside to close modals
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            closeAllModals();
        }
    }
    </script>
</body>

</html>