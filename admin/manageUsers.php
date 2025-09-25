<?php
// admin/manageUsers.php - Admin User Management Page
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
$page_title = 'Manage Users - Admin Dashboard';

// Initialize variables
$users = [];
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'pending_users' => 0,
    'total_adopters' => 0,
    'total_shelters' => 0,
    'total_admins' => 0
];

// Filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
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
                case 'toggle_status':
                    $target_user_id = intval($_POST['user_id'] ?? 0);
                    $new_status = intval($_POST['status'] ?? 0);
                    
                    if ($target_user_id > 0 && $target_user_id !== $user_id) {
                        $stmt = $db->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE user_id = ?");
                        if ($stmt->execute([$new_status, $target_user_id])) {
                            $action_text = $new_status ? 'activated' : 'deactivated';
                            echo json_encode(['success' => true, 'message' => "User $action_text successfully"]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid user ID or cannot modify your own account']);
                    }
                    break;
                    
                case 'delete_user':
                    $target_user_id = intval($_POST['user_id'] ?? 0);
                    
                    if ($target_user_id > 0 && $target_user_id !== $user_id) {
                        // Start transaction
                        $db->beginTransaction();
                        
                        try {
                            // Delete related records first
                            $stmt = $db->prepare("DELETE FROM adoption_applications WHERE adopter_id = ?");
                            $stmt->execute([$target_user_id]);
                            
                            $stmt = $db->prepare("DELETE FROM adoptions WHERE adopter_id = ?");
                            $stmt->execute([$target_user_id]);
                            
                            // If it's a shelter user, delete shelter and pets
                            $stmt = $db->prepare("SELECT user_type FROM users WHERE user_id = ?");
                            $stmt->execute([$target_user_id]);
                            $user_type = $stmt->fetchColumn();
                            
                            if ($user_type === 'shelter') {
                                // Get shelter_id
                                $stmt = $db->prepare("SELECT shelter_id FROM shelters WHERE user_id = ?");
                                $stmt->execute([$target_user_id]);
                                $shelter_id = $stmt->fetchColumn();
                                
                                if ($shelter_id) {
                                    // Delete pets from this shelter
                                    $stmt = $db->prepare("DELETE FROM pets WHERE shelter_id = ?");
                                    $stmt->execute([$shelter_id]);
                                }
                                
                                // Delete shelter record
                                $stmt = $db->prepare("DELETE FROM shelters WHERE user_id = ?");
                                $stmt->execute([$target_user_id]);
                            }
                            
                            // Finally delete the user
                            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                            $stmt->execute([$target_user_id]);
                            
                            $db->commit();
                            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                        } catch (Exception $e) {
                            $db->rollback();
                            echo json_encode(['success' => false, 'message' => 'Failed to delete user: ' . $e->getMessage()]);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid user ID or cannot delete your own account']);
                    }
                    break;
                    
                case 'change_user_type':
                    $target_user_id = intval($_POST['user_id'] ?? 0);
                    $new_type = $_POST['user_type'] ?? '';
                    
                    if ($target_user_id > 0 && $target_user_id !== $user_id && in_array($new_type, ['adopter', 'shelter', 'admin'])) {
                        $stmt = $db->prepare("UPDATE users SET user_type = ?, updated_at = NOW() WHERE user_id = ?");
                        if ($stmt->execute([$new_type, $target_user_id])) {
                            echo json_encode(['success' => true, 'message' => 'User type updated successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to update user type']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
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
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_users'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['active_users'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE is_active = 0");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['pending_users'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'adopter'");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_adopters'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'shelter'");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_shelters'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_admins'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        // Build the users query
        $where_conditions = [];
        $params = [];
        
        if (!empty($filter_type)) {
            $where_conditions[] = "u.user_type = ?";
            $params[] = $filter_type;
        }
        
        if (!empty($filter_status)) {
            if ($filter_status === 'active') {
                $where_conditions[] = "u.is_active = 1";
            } elseif ($filter_status === 'inactive') {
                $where_conditions[] = "u.is_active = 0";
            }
        }
        
        if (!empty($search_query)) {
            $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) as total FROM users u $where_clause";
        $stmt = $db->prepare($count_query);
        $stmt->execute($params);
        $total_users = $stmt->fetch()['total'] ?? 0;
        $total_pages = ceil($total_users / $per_page);
        
        // Get users with pagination
        try {
            $users_query = "
                SELECT u.*, s.shelter_name, s.license_number,
                       (SELECT COUNT(*) FROM adoption_applications WHERE adopter_id = u.user_id) as adoption_count,
                       (SELECT COUNT(*) FROM pets WHERE shelter_id = (SELECT shelter_id FROM shelters WHERE user_id = u.user_id)) as pets_count
                FROM users u
                LEFT JOIN shelters s ON u.user_id = s.user_id
                $where_clause
                ORDER BY u.created_at DESC
                LIMIT $per_page OFFSET $offset
            ";
            
            $stmt = $db->prepare($users_query);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $users = [];
        }
    }
} catch (Exception $e) {
    error_log("Manage Users database error: " . $e->getMessage());
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

    .stat-card.active {
        --color: #28a745;
    }

    .stat-card.pending {
        --color: #ffc107;
    }

    .stat-card.adopters {
        --color: #17a2b8;
    }

    .stat-card.shelters {
        --color: #fd7e14;
    }

    .stat-card.admins {
        --color: #dc3545;
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

    /* Users Table */
    .users-section {
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

    .users-table {
        width: 100%;
        border-collapse: collapse;
    }

    .users-table th,
    .users-table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #f1f1f1;
    }

    .users-table th {
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

    .users-table tr {
        transition: background-color 0.3s ease;
    }

    .users-table tr:hover {
        background: #f8f9fa;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
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

    .user-details h4 {
        font-size: 1rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 2px;
    }

    .user-meta {
        font-size: 0.8rem;
        color: #666;
    }

    .user-type-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }

    .type-admin {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
    }

    .type-shelter {
        background: rgba(253, 126, 20, 0.2);
        color: #fd7e14;
    }

    .type-adopter {
        background: rgba(23, 162, 184, 0.2);
        color: #17a2b8;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }

    .status-active {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
    }

    .status-inactive {
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
        min-width: 180px;
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

    .actions-menu a.text-success:hover {
        background: #f8fff9;
        color: #28a745;
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
        margin: 10% auto;
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

        .users-table {
            font-size: 0.85rem;
        }

        .users-table th,
        .users-table td {
            padding: 10px 8px;
        }

        .pagination-section {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
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
    </style>
</head>

<body>
    <!-- Include Admin Navbar -->
    <?php include_once __DIR__ . '/../common/navbar_admin.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <div>
                <h1><i class="fas fa-users"></i> Manage Users</h1>
                <p>Monitor and manage all users across the platform</p>
            </div>
            <div class="header-actions">
                <button onclick="exportUsers()" class="btn btn-secondary">
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
            <div class="stat-card total" onclick="filterByType('')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Users</h3>
                        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card active" onclick="filterByStatus('active')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Active Users</h3>
                        <div class="stat-number"><?php echo $stats['active_users']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pending" onclick="filterByStatus('inactive')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Pending Approval</h3>
                        <div class="stat-number"><?php echo $stats['pending_users']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card adopters" onclick="filterByType('adopter')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Adopters</h3>
                        <div class="stat-number"><?php echo $stats['total_adopters']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card shelters" onclick="filterByType('shelter')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Shelters</h3>
                        <div class="stat-number"><?php echo $stats['total_shelters']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card admins" onclick="filterByType('admin')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Administrators</h3>
                        <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section fade-in">
            <div class="filters-title">
                <i class="fas fa-filter"></i>
                Filter & Search Users
            </div>
            <form method="GET" action="" id="filtersForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>User Type</label>
                        <select name="type" onchange="document.getElementById('filtersForm').submit()">
                            <option value="">All Types</option>
                            <option value="adopter" <?php echo $filter_type === 'adopter' ? 'selected' : ''; ?>>Adopters
                            </option>
                            <option value="shelter" <?php echo $filter_type === 'shelter' ? 'selected' : ''; ?>>Shelters
                            </option>
                            <option value="admin" <?php echo $filter_type === 'admin' ? 'selected' : ''; ?>>
                                Administrators</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" onchange="document.getElementById('filtersForm').submit()">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active
                            </option>
                            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>
                                Inactive</option>
                        </select>
                    </div>

                    <div class="filter-group search-group">
                        <label>Search Users</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                            placeholder="Search by name, email..." onkeypress="handleSearchKeypress(event)">
                        <i class="fas fa-search"></i>
                    </div>

                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 5px;">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>

                    <div class="filter-group">
                        <a href="<?php echo $BASE_URL; ?>admin/manageUsers.php" class="btn btn-secondary"
                            style="width: 100%; margin-top: 5px; text-align: center;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="users-section fade-in">
            <div class="section-header">
                <h2 class="section-title">
                    User Records
                    <?php if ($total_users > 0): ?>
                    <span style="color: #666; font-weight: normal; font-size: 0.9rem;">
                        (<?php echo number_format($total_users); ?> total)
                    </span>
                    <?php endif; ?>
                </h2>
                <div style="display: flex; gap: 10px;">
                    <span style="font-size: 0.9rem; color: #666;">
                        Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>
                    </span>
                </div>
            </div>

            <?php if (empty($users)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-users-slash"></i>
                </div>
                <h3 class="empty-title">No Users Found</h3>
                <p class="empty-text">
                    <?php if (!empty($filter_type) || !empty($filter_status) || !empty($search_query)): ?>
                    No users match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                    There are no users in the system yet.
                    <?php endif; ?>
                </p>
                <?php if (!empty($filter_type) || !empty($filter_status) || !empty($search_query)): ?>
                <a href="<?php echo $BASE_URL; ?>admin/manageUsers.php" class="btn btn-primary">
                    <i class="fas fa-eye"></i> View All Users
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <!-- Users Table -->
            <div style="overflow-x: auto;">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User Info</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr data-user-id="<?php echo $user['user_id']; ?>">
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php 
                                                $initials = '';
                                                if (!empty($user['first_name'])) $initials .= strtoupper($user['first_name'][0]);
                                                if (!empty($user['last_name'])) $initials .= strtoupper($user['last_name'][0]);
                                                if (empty($initials)) $initials = strtoupper($user['username'][0] ?? 'U');
                                                echo htmlspecialchars($initials);
                                                ?>
                                    </div>
                                    <div class="user-details">
                                        <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </h4>
                                        <div class="user-meta">
                                            <div><i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($user['username']); ?></div>
                                            <div><i class="fas fa-envelope"></i>
                                                <?php echo htmlspecialchars($user['email']); ?></div>
                                            <?php if (!empty($user['phone'])): ?>
                                            <div><i class="fas fa-phone"></i>
                                                <?php echo htmlspecialchars($user['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($user['user_type'] === 'shelter' && !empty($user['shelter_name'])): ?>
                                            <div><i class="fas fa-home"></i>
                                                <?php echo htmlspecialchars($user['shelter_name']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="user-type-badge type-<?php echo $user['user_type']; ?>">
                                    <?php 
                                            switch($user['user_type']) {
                                                case 'admin': echo '<i class="fas fa-user-shield"></i> Admin'; break;
                                                case 'shelter': echo '<i class="fas fa-home"></i> Shelter'; break;
                                                case 'adopter': echo '<i class="fas fa-heart"></i> Adopter'; break;
                                                default: echo ucfirst($user['user_type']); break;
                                            }
                                            ?>
                                </span>
                            </td>
                            <td>
                                <span
                                    class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $user['is_active'] ? '<i class="fas fa-check-circle"></i> Active' : '<i class="fas fa-times-circle"></i> Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem;">
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </div>
                                    <div style="color: #666; font-size: 0.8rem;">
                                        <?php echo date('g:i A', strtotime($user['created_at'])); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem;">
                                    <?php if ($user['user_type'] === 'adopter'): ?>
                                    <div style="color: #17a2b8;">
                                        <i class="fas fa-clipboard-list"></i>
                                        <?php echo (int)$user['adoption_count']; ?> applications
                                    </div>
                                    <?php elseif ($user['user_type'] === 'shelter'): ?>
                                    <div style="color: #fd7e14;">
                                        <i class="fas fa-paw"></i>
                                        <?php echo (int)$user['pets_count']; ?> pets
                                    </div>
                                    <?php if (!empty($user['license_number'])): ?>
                                    <div style="color: #666; font-size: 0.8rem;">
                                        License: <?php echo htmlspecialchars($user['license_number']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <div style="color: #dc3545;">
                                        <i class="fas fa-user-shield"></i> Administrator
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="actions-dropdown">
                                    <button class="actions-btn"
                                        onclick="toggleActionsMenu(<?php echo $user['user_id']; ?>)">
                                        <i class="fas fa-cog"></i> Actions <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <div class="actions-menu" id="actions-<?php echo $user['user_id']; ?>">
                                        <a
                                            href="<?php echo $BASE_URL; ?>admin/viewUser.php?id=<?php echo $user['user_id']; ?>">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>

                                        <?php if ($user['user_id'] !== $user_id): ?>
                                        <a href="#"
                                            onclick="showChangeTypeModal(<?php echo $user['user_id']; ?>, '<?php echo $user['user_type']; ?>')">
                                            <i class="fas fa-exchange-alt"></i> Change Type
                                        </a>

                                        <a href="#"
                                            onclick="toggleUserStatus(<?php echo $user['user_id']; ?>, <?php echo $user['is_active'] ? '0' : '1'; ?>)"
                                            class="<?php echo $user['is_active'] ? 'text-danger' : 'text-success'; ?>">
                                            <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                            <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </a>

                                        <a href="#"
                                            onclick="confirmDeleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES); ?>')"
                                            class="text-danger">
                                            <i class="fas fa-trash"></i> Delete User
                                        </a>
                                        <?php else: ?>
                                        <span style="padding: 8px 12px; color: #999; font-size: 0.8rem;">
                                            <i class="fas fa-lock"></i> Cannot modify own account
                                        </span>
                                        <?php endif; ?>
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
                    <?php echo min($page * $per_page, $total_users); ?> of
                    <?php echo number_format($total_users); ?> users
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

    <!-- Change User Type Modal -->
    <div id="changeTypeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Change User Type</h3>
                <button class="modal-close" onclick="closeModal('changeTypeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Select the new user type:</p>
                <select id="newUserType"
                    style="width: 100%; padding: 10px; border: 2px solid #e1e8ed; border-radius: 8px; margin: 10px 0;">
                    <option value="adopter">Adopter</option>
                    <option value="shelter">Shelter</option>
                    <option value="admin">Administrator</option>
                </select>
                <p style="font-size: 0.9rem; color: #666; margin-top: 15px;">
                    <strong>Warning:</strong> Changing user type may affect their access permissions and data.
                </p>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('changeTypeModal')">Cancel</button>
                <button class="btn btn-warning" onclick="confirmChangeUserType()">
                    <i class="fas fa-exchange-alt"></i> Change Type
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm User Deletion</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"
                        style="font-size: 3rem; color: #dc3545; margin-bottom: 15px;"></i>
                </div>
                <p><strong>Are you sure you want to delete this user?</strong></p>
                <p id="deleteUserName" style="color: #666; margin: 10px 0;"></p>
                <div
                    style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <p style="margin: 0; color: #856404;"><strong>⚠️ Warning:</strong></p>
                    <ul style="margin: 10px 0 0 20px; color: #856404;">
                        <li>This action cannot be undone</li>
                        <li>All user data will be permanently deleted</li>
                        <li>Related adoption records will be removed</li>
                        <li>For shelters, all pets will also be deleted</li>
                    </ul>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDeleteUserAction()">
                    <i class="fas fa-trash"></i> Delete User
                </button>
            </div>
        </div>
    </div>

    <script>
    // Global variables
    let currentUserId = null;
    let currentActionMenu = null;

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (currentActionMenu && !event.target.closest('.actions-dropdown')) {
                currentActionMenu.classList.remove('show');
                currentActionMenu = null;
            }
        });

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });
    });

    // Toggle actions dropdown menu
    function toggleActionsMenu(userId) {
        const menu = document.getElementById('actions-' + userId);

        // Close any other open menus
        if (currentActionMenu && currentActionMenu !== menu) {
            currentActionMenu.classList.remove('show');
        }

        // Toggle current menu
        menu.classList.toggle('show');
        currentActionMenu = menu.classList.contains('show') ? menu : null;
    }

    // Filter functions
    function filterByType(type) {
        const url = new URL(window.location);
        if (type) {
            url.searchParams.set('type', type);
        } else {
            url.searchParams.delete('type');
        }
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

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

    // User status toggle
    function toggleUserStatus(userId, newStatus) {
        if (confirm(newStatus ? 'Activate this user?' : 'Deactivate this user?')) {
            performAjaxAction('toggle_status', {
                user_id: userId,
                status: newStatus
            });
        }
    }

    // Show change type modal
    function showChangeTypeModal(userId, currentType) {
        currentUserId = userId;
        document.getElementById('newUserType').value = currentType;
        document.getElementById('changeTypeModal').style.display = 'block';
    }

    // Confirm change user type
    function confirmChangeUserType() {
        const newType = document.getElementById('newUserType').value;
        if (currentUserId && newType) {
            performAjaxAction('change_user_type', {
                user_id: currentUserId,
                user_type: newType
            });
            closeModal('changeTypeModal');
        }
    }

    // Show delete confirmation modal
    function confirmDeleteUser(userId, userName) {
        currentUserId = userId;
        document.getElementById('deleteUserName').textContent = 'User: ' + userName;
        document.getElementById('deleteModal').style.display = 'block';
    }

    // Confirm delete user action
    function confirmDeleteUserAction() {
        if (currentUserId) {
            performAjaxAction('delete_user', {
                user_id: currentUserId
            });
            closeModal('deleteModal');
        }
    }

    // Close modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        currentUserId = null;
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
            messageDiv.remove();
        }, 5000);
    }

    // Refresh data
    function refreshData() {
        window.location.reload();
    }

    // Export users data
    function exportUsers() {
        const url = new URL(window.location);
        url.searchParams.set('export', 'csv');
        window.open(url.toString(), '_blank');
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(event) {
        // Escape key to close modals
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => modal.style.display = 'none');
            currentUserId = null;
        }

        // Ctrl+R to refresh
        if (event.ctrlKey && event.key === 'r') {
            event.preventDefault();
            refreshData();
        }
    });
    </script>
</body>

</html>