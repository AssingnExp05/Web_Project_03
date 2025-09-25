<?php
// shelter/viewPets.php - View All Pets Page for Shelters
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Base URL
$BASE_URL = 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Check if user is logged in and is a shelter
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'shelter') {
    $_SESSION['error_message'] = 'Please login as a shelter to access this page.';
    header('Location: ' . $BASE_URL . 'auth/login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$page_title = 'My Pets - Shelter Dashboard';

// Initialize variables
$pets = [];
$shelter_info = null;
$categories = [];
$current_user = null;
$stats = [
    'total_pets' => 0,
    'available_pets' => 0,
    'pending_pets' => 0,
    'adopted_pets' => 0
];

// Filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search_query = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Handle AJAX requests for pet actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    try {
        require_once __DIR__ . '/../config/db.php';
        $db = getDB();
        
        if ($db && isset($_POST['action'])) {
            // Get shelter info
            $stmt = $db->prepare("SELECT shelter_id FROM shelters WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $shelter_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$shelter_info) {
                echo json_encode(['success' => false, 'message' => 'Shelter not found']);
                exit();
            }
            
            switch ($_POST['action']) {
                case 'update_status':
                    $pet_id = intval($_POST['pet_id'] ?? 0);
                    $new_status = $_POST['new_status'] ?? '';
                    
                    if ($pet_id > 0 && in_array($new_status, ['available', 'pending', 'adopted'])) {
                        // Verify pet belongs to this shelter
                        $stmt = $db->prepare("SELECT pet_id FROM pets WHERE pet_id = ? AND shelter_id = ?");
                        $stmt->execute([$pet_id, $shelter_info['shelter_id']]);
                        
                        if ($stmt->fetch()) {
                            $stmt = $db->prepare("UPDATE pets SET status = ? WHERE pet_id = ?");
                            if ($stmt->execute([$new_status, $pet_id])) {
                                echo json_encode(['success' => true, 'message' => 'Pet status updated successfully']);
                            } else {
                                echo json_encode(['success' => false, 'message' => 'Failed to update pet status']);
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Pet not found or unauthorized']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                    }
                    break;
                    
                case 'delete_pet':
                    $pet_id = intval($_POST['pet_id'] ?? 0);
                    
                    if ($pet_id > 0) {
                        // Verify pet belongs to this shelter
                        $stmt = $db->prepare("SELECT pet_id, primary_image FROM pets WHERE pet_id = ? AND shelter_id = ?");
                        $stmt->execute([$pet_id, $shelter_info['shelter_id']]);
                        $pet = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($pet) {
                            $db->beginTransaction();
                            
                            try {
                                // Delete primary image if it exists
                                if ($pet['primary_image']) {
                                    $primary_path = __DIR__ . '/../uploads/' . $pet['primary_image'];
                                    if (file_exists($primary_path)) {
                                        unlink($primary_path);
                                    }
                                }
                                
                                // Delete the pet (foreign key constraints will handle related records)
                                $stmt = $db->prepare("DELETE FROM pets WHERE pet_id = ?");
                                $stmt->execute([$pet_id]);
                                
                                $db->commit();
                                echo json_encode(['success' => true, 'message' => 'Pet deleted successfully']);
                            } catch (Exception $e) {
                                $db->rollback();
                                echo json_encode(['success' => false, 'message' => 'Failed to delete pet: ' . $e->getMessage()]);
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Pet not found or unauthorized']);
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
        // Get current user info
        $stmt = $db->prepare("SELECT u.*, s.shelter_name FROM users u LEFT JOIN shelters s ON u.user_id = s.user_id WHERE u.user_id = ?");
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get shelter information
        $stmt = $db->prepare("SELECT * FROM shelters WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $shelter_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$shelter_info) {
            $_SESSION['error_message'] = 'Shelter information not found.';
            header('Location: ' . $BASE_URL . 'auth/login.php');
            exit();
        }
        
        // Get categories for filter
        $stmt = $db->prepare("SELECT * FROM pet_categories ORDER BY category_name");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Get statistics
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ?");
        $stmt->execute([$shelter_info['shelter_id']]);
        $stats['total_pets'] = $stmt->fetchColumn() ?: 0;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'available'");
        $stmt->execute([$shelter_info['shelter_id']]);
        $stats['available_pets'] = $stmt->fetchColumn() ?: 0;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'pending'");
        $stmt->execute([$shelter_info['shelter_id']]);
        $stats['pending_pets'] = $stmt->fetchColumn() ?: 0;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'adopted'");
        $stmt->execute([$shelter_info['shelter_id']]);
        $stats['adopted_pets'] = $stmt->fetchColumn() ?: 0;
        
        // Build the pets query
        $where_conditions = ["p.shelter_id = ?"];
        $params = [$shelter_info['shelter_id']];
        
        if (!empty($filter_status)) {
            $where_conditions[] = "p.status = ?";
            $params[] = $filter_status;
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
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Validate sort parameters
        $valid_sort_fields = ['pet_name', 'age', 'created_at', 'status', 'adoption_fee'];
        $sort_by = in_array($sort_by, $valid_sort_fields) ? $sort_by : 'created_at';
        $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
        
        // Get total count for pagination
        $count_query = "
            SELECT COUNT(*) as total 
            FROM pets p
            LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
            $where_clause
        ";
        $stmt = $db->prepare($count_query);
        $stmt->execute($params);
        $total_pets = $stmt->fetch()['total'] ?? 0;
        $total_pages = ceil($total_pets / $per_page);
        
        // Get pets with pagination
        $pets_query = "
            SELECT p.*, 
                   pc.category_name, 
                   pb.breed_name
            FROM pets p
            LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
            LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
            $where_clause
            ORDER BY p.{$sort_by} {$sort_order}
            LIMIT $per_page OFFSET $offset
        ";
        
        $stmt = $db->prepare($pets_query);
        $stmt->execute($params);
        $pets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        throw new Exception("Database connection failed");
    }
    
} catch (Exception $e) {
    error_log("View Pets database error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again later.";
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
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Header Section */
    .page-header {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        color: #28a745;
    }

    .btn-primary:hover {
        background: #ffed4e;
        transform: translateY(-2px);
        text-decoration: none;
        color: #20c997;
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

    .stat-card.pending {
        --color: #ffc107;
    }

    .stat-card.adopted {
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

    /* Filters and Controls */
    .controls-section {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .controls-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .controls-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
    }

    .controls-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: end;
    }

    .control-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .control-group label {
        font-weight: 600;
        color: #555;
        font-size: 0.9rem;
    }

    .control-group select,
    .control-group input {
        padding: 10px 12px;
        border: 2px solid #e1e8ed;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.3s ease;
    }

    .control-group select:focus,
    .control-group input:focus {
        outline: none;
        border-color: #28a745;
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

    /* Pets Grid */
    .pets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .pet-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
    }

    .pet-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .pet-image {
        width: 100%;
        height: 250px;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #999;
        font-size: 3rem;
        position: relative;
        overflow: hidden;
    }

    .pet-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .pet-status {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-available {
        background: rgba(40, 167, 69, 0.9);
        color: white;
    }

    .status-pending {
        background: rgba(255, 193, 7, 0.9);
        color: #212529;
    }

    .status-adopted {
        background: rgba(23, 162, 184, 0.9);
        color: white;
    }

    .pet-content {
        padding: 20px;
    }

    .pet-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .pet-info h3 {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 5px;
    }

    .pet-meta {
        font-size: 0.9rem;
        color: #666;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .pet-fee {
        font-size: 1.1rem;
        font-weight: 700;
        color: #28a745;
    }

    .pet-description {
        color: #666;
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: 15px;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .pet-stats {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        font-size: 0.85rem;
        color: #666;
    }

    .pet-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .pet-actions .btn {
        flex: 1;
        justify-content: center;
        min-width: 80px;
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
        color: #28a745;
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
        justify-content: center;
        align-items: center;
        padding: 30px 0;
    }

    .pagination {
        display: flex;
        gap: 5px;
        align-items: center;
    }

    .pagination a,
    .pagination span {
        padding: 10px 15px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        text-decoration: none;
        color: #495057;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        min-width: 45px;
        text-align: center;
    }

    .pagination a:hover {
        background: #e9ecef;
        text-decoration: none;
    }

    .pagination .current {
        background: #28a745;
        color: white;
        border-color: #28a745;
    }

    .pagination .disabled {
        opacity: 0.5;
        pointer-events: none;
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
        margin: 5% auto;
        padding: 0;
        border-radius: 15px;
        width: 90%;
        max-width: 500px;
        position: relative;
    }

    .modal-header {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 15px 15px 0 0;
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
    }

    .modal-actions {
        padding: 20px 25px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        background: #f8f9fa;
        border-radius: 0 0 15px 15px;
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

    /* Loading States */
    .loading {
        opacity: 0.6;
        pointer-events: none;
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
    @media (max-width: 1200px) {
        .pets-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
    }

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

        .controls-grid {
            grid-template-columns: 1fr;
        }

        .pets-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .pet-actions {
            flex-direction: column;
        }
    }
    </style>
</head>

<body>
    <!-- Include Shelter Navbar -->
    <?php include_once __DIR__ . '/../common/navbar_shelter.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <div>
                <h1><i class="fas fa-list"></i> My Pets</h1>
                <p>Manage and view all pets in your shelter</p>
            </div>
            <div class="header-actions">
                <a href="<?php echo $BASE_URL; ?>shelter/addPet.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Add New Pet
                </a>
                <a href="<?php echo $BASE_URL; ?>shelter/dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Display any errors -->
        <?php if (isset($error_message)): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

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
                        <h3>Available</h3>
                        <div class="stat-number"><?php echo $stats['available_pets']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pending" onclick="filterByStatus('pending')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Pending</h3>
                        <div class="stat-number"><?php echo $stats['pending_pets']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card adopted" onclick="filterByStatus('adopted')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Adopted</h3>
                        <div class="stat-number"><?php echo $stats['adopted_pets']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controls Section -->
        <div class="controls-section fade-in">
            <div class="controls-header">
                <h2 class="controls-title">Filter & Search</h2>
            </div>

            <form method="GET" action="" id="filtersForm">
                <div class="controls-grid">
                    <div class="control-group">
                        <label>Status</label>
                        <select name="status" onchange="document.getElementById('filtersForm').submit()">
                            <option value="">All Statuses</option>
                            <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>
                                Available</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>
                                Pending</option>
                            <option value="adopted" <?php echo $filter_status === 'adopted' ? 'selected' : ''; ?>>
                                Adopted</option>
                        </select>
                    </div>

                    <div class="control-group">
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

                    <div class="control-group">
                        <label>Sort By</label>
                        <select name="sort" onchange="document.getElementById('filtersForm').submit()">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date
                                Added</option>
                            <option value="pet_name" <?php echo $sort_by === 'pet_name' ? 'selected' : ''; ?>>Name
                            </option>
                            <option value="age" <?php echo $sort_by === 'age' ? 'selected' : ''; ?>>Age</option>
                            <option value="adoption_fee" <?php echo $sort_by === 'adoption_fee' ? 'selected' : ''; ?>>
                                Adoption Fee</option>
                            <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status
                            </option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label>Order</label>
                        <select name="order" onchange="document.getElementById('filtersForm').submit()">
                            <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Descending
                            </option>
                            <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Ascending
                            </option>
                        </select>
                    </div>

                    <div class="control-group search-group">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                            placeholder="Search pets..." onkeypress="handleSearchKeypress(event)">
                        <i class="fas fa-search"></i>
                    </div>

                    <div class="control-group">
                        <a href="<?php echo $BASE_URL; ?>shelter/viewPets.php" class="btn btn-secondary"
                            style="width: 100%; margin-top: 5px; text-align: center;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <?php if (empty($pets)): ?>
        <!-- Empty State -->
        <div class="empty-state fade-in">
            <div class="empty-icon">
                <i class="fas fa-paw"></i>
            </div>
            <h3 class="empty-title">No Pets Found</h3>
            <p class="empty-text">
                <?php if (!empty($filter_status) || !empty($filter_category) || !empty($search_query)): ?>
                No pets match your current filters. Try adjusting your search criteria.
                <?php else: ?>
                You haven't added any pets yet. Start by adding your first pet!
                <?php endif; ?>
            </p>
            <?php if (empty($filter_status) && empty($filter_category) && empty($search_query)): ?>
            <a href="<?php echo $BASE_URL; ?>shelter/addPet.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add Your First Pet
            </a>
            <?php else: ?>
            <a href="<?php echo $BASE_URL; ?>shelter/viewPets.php" class="btn btn-secondary">
                <i class="fas fa-eye"></i> View All Pets
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <!-- Pets Grid -->
        <div class="pets-grid fade-in">
            <?php foreach ($pets as $pet): ?>
            <div class="pet-card" data-pet-id="<?php echo $pet['pet_id']; ?>">
                <div class="pet-image">
                    <?php if (!empty($pet['primary_image'])): ?>
                    <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                        alt="<?php echo htmlspecialchars($pet['pet_name']); ?>">
                    <?php else: ?>
                    <i class="fas fa-paw"></i>
                    <?php endif; ?>
                </div>

                <div class="pet-status status-<?php echo $pet['status']; ?>">
                    <?php echo ucfirst($pet['status']); ?>
                </div>

                <div class="pet-content">
                    <div class="pet-header">
                        <div class="pet-info">
                            <h3><?php echo htmlspecialchars($pet['pet_name']); ?></h3>
                            <div class="pet-meta">
                                <span><i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($pet['category_name'] ?? 'Unknown'); ?></span>
                                <?php if (!empty($pet['breed_name'])): ?>
                                <span><i class="fas fa-dna"></i>
                                    <?php echo htmlspecialchars($pet['breed_name']); ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-birthday-cake"></i> <?php echo $pet['age']; ?> years old</span>
                                <span><i class="fas fa-venus-mars"></i> <?php echo ucfirst($pet['gender']); ?></span>
                                <?php if (!empty($pet['size'])): ?>
                                <span><i class="fas fa-ruler"></i> <?php echo ucfirst($pet['size']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pet-fee">
                            <?php if ($pet['adoption_fee'] > 0): ?>
                            $<?php echo number_format($pet['adoption_fee'], 2); ?>
                            <?php else: ?>
                            <span style="color: #28a745;">Free</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="pet-description">
                        <?php echo htmlspecialchars($pet['description']); ?>
                    </div>

                    <div class="pet-stats">
                        <span>
                            <i class="fas fa-calendar-plus"></i>
                            Added <?php echo date('M j, Y', strtotime($pet['created_at'])); ?>
                        </span>
                        <span>
                            <i class="fas fa-heartbeat"></i>
                            <?php echo htmlspecialchars($pet['health_status'] ?? 'Good'); ?>
                        </span>
                    </div>

                    <div class="pet-actions">
                        <a href="<?php echo $BASE_URL; ?>shelter/editPet.php?id=<?php echo $pet['pet_id']; ?>"
                            class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Edit
                        </a>

                        <?php if ($pet['status'] !== 'adopted'): ?>
                        <button
                            onclick="showStatusModal(<?php echo $pet['pet_id']; ?>, '<?php echo $pet['status']; ?>')"
                            class="btn btn-success btn-sm">
                            <i class="fas fa-sync"></i> Status
                        </button>
                        <?php endif; ?>

                        <button
                            onclick="confirmDeletePet(<?php echo $pet['pet_id']; ?>, '<?php echo htmlspecialchars($pet['pet_name'], ENT_QUOTES); ?>')"
                            class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-section">
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

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Pet Status</h3>
                <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Select the new status for this pet:</p>
                <select id="newStatus"
                    style="width: 100%; padding: 10px; border: 2px solid #e1e8ed; border-radius: 8px; margin: 15px 0;">
                    <option value="available">Available for Adoption</option>
                    <option value="pending">Pending Adoption</option>
                    <option value="adopted">Adopted</option>
                </select>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">
                        <strong>Note:</strong> Changing status will affect the pet's visibility and adoption process.
                    </p>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button class="btn btn-success" onclick="confirmStatusUpdate()">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Pet Deletion</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"
                        style="font-size: 3rem; color: #dc3545; margin-bottom: 15px;"></i>
                </div>
                <p><strong>Are you sure you want to delete this pet?</strong></p>
                <p id="deletePetName" style="color: #666; margin: 10px 0;"></p>
                <div
                    style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <p style="margin: 0; color: #856404;"><strong>⚠️ Warning:</strong></p>
                    <ul style="margin: 10px 0 0 20px; color: #856404;">
                        <li>This action cannot be undone</li>
                        <li>All pet data will be permanently deleted</li>
                        <li>All adoption applications will be removed</li>
                        <li>All images and medical records will be deleted</li>
                    </ul>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDeletePetAction()">
                    <i class="fas fa-trash"></i> Delete Pet
                </button>
            </div>
        </div>
    </div>

    <script>
    // Global variables
    let currentPetId = null;
    let currentPetName = null;

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

    // Show status update modal
    function showStatusModal(petId, currentStatus) {
        currentPetId = petId;
        document.getElementById('newStatus').value = currentStatus;
        document.getElementById('statusModal').style.display = 'block';
    }

    // Confirm status update
    function confirmStatusUpdate() {
        const newStatus = document.getElementById('newStatus').value;

        if (currentPetId && newStatus) {
            performAjaxAction('update_status', {
                pet_id: currentPetId,
                new_status: newStatus
            });
            closeModal('statusModal');
        }
    }

    // Show delete confirmation modal
    function confirmDeletePet(petId, petName) {
        currentPetId = petId;
        currentPetName = petName;
        document.getElementById('deletePetName').textContent = `Pet: ${petName}`;
        document.getElementById('deleteModal').style.display = 'block';
    }

    // Confirm delete pet action
    function confirmDeletePetAction() {
        if (currentPetId) {
            performAjaxAction('delete_pet', {
                pet_id: currentPetId
            });
            closeModal('deleteModal');
        }
    }

    // Close modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        currentPetId = null;
        currentPetName = null;
    }

    // Perform AJAX action
    function performAjaxAction(action, data) {
        const formData = new FormData();
        formData.append('action', action);

        for (const key in data) {
            formData.append(key, data[key]);
        }

        // Show loading state
        document.body.classList.add('loading');

        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.classList.remove('loading');

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
                document.body.classList.remove('loading');
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
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(event) {
        // Escape key to close modals
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => modal.style.display = 'none');
            currentPetId = null;
            currentPetName = null;
        }
    });

    // Auto-hide success messages
    setTimeout(() => {
        const successMessages = document.querySelectorAll('.message.success');
        successMessages.forEach(message => {
            message.style.opacity = '0';
            setTimeout(() => {
                if (message.parentNode) {
                    message.remove();
                }
            }, 500);
        });
    }, 5000);

    // Add hover effects to pet cards
    document.addEventListener('DOMContentLoaded', function() {
        const petCards = document.querySelectorAll('.pet-card');
        petCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0px)';
            });
        });
    });
    </script>
</body>

</html>