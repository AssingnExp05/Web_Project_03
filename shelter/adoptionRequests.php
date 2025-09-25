<?php
// shelter/adoptionRequests.php - Adoption Requests Page for Shelters
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
$page_title = 'Adoption Requests - Shelter Dashboard';

// Initialize variables
$applications = [];
$shelter_info = null;
$current_user = null;
$pets_list = [];
$stats = [
    'total_requests' => 0,
    'pending_requests' => 0,
    'approved_requests' => 0,
    'rejected_requests' => 0
];

// Filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_pet = $_GET['pet'] ?? '';
$search_query = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'application_date';
$sort_order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    
    try {
        require_once __DIR__ . '/../config/db.php';
        $db = getDB();
        
        if (!$db) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit();
        }
        
        // Get shelter info
        $stmt = $db->prepare("SELECT shelter_id FROM shelters WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $shelter_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$shelter_info) {
            echo json_encode(['success' => false, 'message' => 'Shelter not found']);
            exit();
        }
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_status':
                    $application_id = intval($_POST['application_id'] ?? 0);
                    $new_status = $_POST['new_status'] ?? '';
                    $admin_notes = trim($_POST['admin_notes'] ?? '');
                    
                    if ($application_id > 0 && in_array($new_status, ['pending', 'approved', 'rejected'])) {
                        // Verify application belongs to this shelter
                        $stmt = $db->prepare("
                            SELECT aa.application_id, aa.pet_id, p.pet_name 
                            FROM adoption_applications aa 
                            JOIN pets p ON aa.pet_id = p.pet_id 
                            WHERE aa.application_id = ? AND aa.shelter_id = ?
                        ");
                        $stmt->execute([$application_id, $shelter_info['shelter_id']]);
                        $app_info = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($app_info) {
                            $db->beginTransaction();
                            
                            try {
                                // Update application status with reviewed_by
                                $stmt = $db->prepare("
                                    UPDATE adoption_applications 
                                    SET application_status = ?, reviewed_by = ? 
                                    WHERE application_id = ?
                                ");
                                $stmt->execute([$new_status, $user_id, $application_id]);
                                
                                // If approved, create adoption record and update pet status
                                if ($new_status === 'approved') {
                                    // Update pet status to pending
                                    $stmt = $db->prepare("UPDATE pets SET status = 'pending' WHERE pet_id = ?");
                                    $stmt->execute([$app_info['pet_id']]);
                                    
                                    // Check if adoption record already exists
                                    $stmt = $db->prepare("SELECT adoption_id FROM adoptions WHERE application_id = ?");
                                    $stmt->execute([$application_id]);
                                    
                                    if (!$stmt->fetch()) {
                                        // Create adoption record
                                        $stmt = $db->prepare("
                                            INSERT INTO adoptions (application_id, pet_id, adopter_id, shelter_id, adoption_date)
                                            SELECT ?, pet_id, adopter_id, shelter_id, NOW()
                                            FROM adoption_applications 
                                            WHERE application_id = ?
                                        ");
                                        $stmt->execute([$application_id, $application_id]);
                                    }
                                } elseif ($new_status === 'rejected') {
                                    // Check if there are other approved applications for this pet
                                    $stmt = $db->prepare("
                                        SELECT COUNT(*) FROM adoption_applications 
                                        WHERE pet_id = ? AND application_status = 'approved' AND shelter_id = ?
                                    ");
                                    $stmt->execute([$app_info['pet_id'], $shelter_info['shelter_id']]);
                                    $approved_count = $stmt->fetchColumn();
                                    
                                    // If no other approved applications, set pet back to available
                                    if ($approved_count == 0) {
                                        $stmt = $db->prepare("UPDATE pets SET status = 'available' WHERE pet_id = ?");
                                        $stmt->execute([$app_info['pet_id']]);
                                    }
                                }
                                
                                $db->commit();
                                echo json_encode(['success' => true, 'message' => 'Application status updated successfully']);
                            } catch (Exception $e) {
                                $db->rollback();
                                throw $e;
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Application not found or unauthorized']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                    }
                    break;
                    
                case 'delete_application':
                    $application_id = intval($_POST['application_id'] ?? 0);
                    
                    if ($application_id > 0) {
                        // Verify application belongs to this shelter
                        $stmt = $db->prepare("
                            SELECT aa.application_id, aa.pet_id 
                            FROM adoption_applications aa 
                            WHERE aa.application_id = ? AND aa.shelter_id = ?
                        ");
                        $stmt->execute([$application_id, $shelter_info['shelter_id']]);
                        $app_info = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($app_info) {
                            $db->beginTransaction();
                            
                            try {
                                // Delete adoption record if exists
                                $stmt = $db->prepare("DELETE FROM adoptions WHERE application_id = ?");
                                $stmt->execute([$application_id]);
                                
                                // Delete application
                                $stmt = $db->prepare("DELETE FROM adoption_applications WHERE application_id = ?");
                                $stmt->execute([$application_id]);
                                
                                // Check if pet should be set back to available
                                $stmt = $db->prepare("
                                    SELECT COUNT(*) FROM adoption_applications 
                                    WHERE pet_id = ? AND application_status = 'approved' AND shelter_id = ?
                                ");
                                $stmt->execute([$app_info['pet_id'], $shelter_info['shelter_id']]);
                                $approved_count = $stmt->fetchColumn();
                                
                                if ($approved_count == 0) {
                                    $stmt = $db->prepare("UPDATE pets SET status = 'available' WHERE pet_id = ?");
                                    $stmt->execute([$app_info['pet_id']]);
                                }
                                
                                $db->commit();
                                echo json_encode(['success' => true, 'message' => 'Application deleted successfully']);
                            } catch (Exception $e) {
                                $db->rollback();
                                throw $e;
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Application not found or unauthorized']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
                    }
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
        }
    } catch (Exception $e) {
        error_log("AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Database operations
$error_message = '';
$total_applications = 0;
$total_pages = 1;

try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if (!$db) {
        throw new Exception("Could not connect to database. Please check your configuration.");
    }
    
    // Get current user info
    $stmt = $db->prepare("SELECT u.*, s.shelter_name FROM users u LEFT JOIN shelters s ON u.user_id = s.user_id WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_user) {
        throw new Exception("User not found in database");
    }
    
    // Get shelter information
    $stmt = $db->prepare("SELECT * FROM shelters WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $shelter_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$shelter_info) {
        $error_message = "No shelter found for your account. Please contact administrator to set up your shelter profile.";
    } else {
        // Get statistics - using shelter_id from adoption_applications table
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE shelter_id = ?");
            $stmt->execute([$shelter_info['shelter_id']]);
            $stats['total_requests'] = $stmt->fetchColumn() ?: 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE shelter_id = ? AND application_status = 'pending'");
            $stmt->execute([$shelter_info['shelter_id']]);
            $stats['pending_requests'] = $stmt->fetchColumn() ?: 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE shelter_id = ? AND application_status = 'approved'");
            $stmt->execute([$shelter_info['shelter_id']]);
            $stats['approved_requests'] = $stmt->fetchColumn() ?: 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE shelter_id = ? AND application_status = 'rejected'");
            $stmt->execute([$shelter_info['shelter_id']]);
            $stats['rejected_requests'] = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e) {
            error_log("Statistics error: " . $e->getMessage());
        }
        
        // Get pets list for filter dropdown
        try {
            $stmt = $db->prepare("SELECT pet_id, pet_name FROM pets WHERE shelter_id = ? ORDER BY pet_name");
            $stmt->execute([$shelter_info['shelter_id']]);
            $pets_list = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Pets list error: " . $e->getMessage());
            $pets_list = [];
        }
        
        // Build the applications query only if we have applications
        if ($stats['total_requests'] > 0) {
            try {
                $where_conditions = ["aa.shelter_id = ?"];
                $params = [$shelter_info['shelter_id']];
                
                if (!empty($filter_status)) {
                    $where_conditions[] = "aa.application_status = ?";
                    $params[] = $filter_status;
                }
                
                if (!empty($filter_pet)) {
                    $where_conditions[] = "aa.pet_id = ?";
                    $params[] = $filter_pet;
                }
                
                if (!empty($search_query)) {
                    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR p.pet_name LIKE ?)";
                    $params[] = "%$search_query%";
                    $params[] = "%$search_query%";
                    $params[] = "%$search_query%";
                    $params[] = "%$search_query%";
                }
                
                $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
                
                // Validate sort parameters
                $valid_sort_fields = ['application_date', 'pet_name', 'first_name', 'application_status'];
                $sort_by = in_array($sort_by, $valid_sort_fields) ? $sort_by : 'application_date';
                $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
                
                // Get total count for pagination
                $count_query = "
                    SELECT COUNT(*) as total 
                    FROM adoption_applications aa
                    JOIN pets p ON aa.pet_id = p.pet_id
                    JOIN users u ON aa.adopter_id = u.user_id
                    $where_clause
                ";
                $stmt = $db->prepare($count_query);
                $stmt->execute($params);
                $total_applications = $stmt->fetch()['total'] ?? 0;
                $total_pages = ceil($total_applications / $per_page);
                
                if ($total_applications > 0) {
                    // Map sort field to actual column
                    $sort_field = $sort_by === 'application_date' ? 'aa.application_date' : 
                                 ($sort_by === 'pet_name' ? 'p.pet_name' : 
                                 ($sort_by === 'first_name' ? 'u.first_name' : 'aa.application_status'));
                    
                    // Get applications with pagination
                    $applications_query = "
                        SELECT aa.*, 
                               p.pet_name, p.species, p.age, p.primary_image, p.status as pet_status,
                               u.first_name, u.last_name, u.email, u.phone, u.address,
                               pc.category_name,
                               reviewer.first_name as reviewer_first_name,
                               reviewer.last_name as reviewer_last_name
                        FROM adoption_applications aa
                        JOIN pets p ON aa.pet_id = p.pet_id
                        JOIN users u ON aa.adopter_id = u.user_id
                        LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
                        LEFT JOIN users reviewer ON aa.reviewed_by = reviewer.user_id
                        $where_clause
                        ORDER BY {$sort_field} {$sort_order}
                        LIMIT $per_page OFFSET $offset
                    ";
                    
                    $stmt = $db->prepare($applications_query);
                    $stmt->execute($params);
                    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }
            } catch (Exception $e) {
                error_log("Applications query error: " . $e->getMessage());
                $applications = [];
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Adoption Requests main error: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
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
        background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);
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
        color: #fd7e14;
    }

    .btn-primary:hover {
        background: #ffed4e;
        transform: translateY(-2px);
        text-decoration: none;
        color: #e83e8c;
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

    .stat-card.pending {
        --color: #ffc107;
    }

    .stat-card.approved {
        --color: #28a745;
    }

    .stat-card.rejected {
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

    /* Controls Section */
    .controls-section {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .controls-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 20px;
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
        border-color: #fd7e14;
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

    /* Applications Section */
    .applications-section {
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

    .application-card {
        border-bottom: 1px solid #f1f1f1;
        padding: 25px;
        transition: all 0.3s ease;
    }

    .application-card:hover {
        background: #f8f9fa;
    }

    .application-card:last-child {
        border-bottom: none;
    }

    .application-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .pet-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .pet-image {
        width: 80px;
        height: 80px;
        border-radius: 10px;
        object-fit: cover;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #999;
        font-size: 2rem;
    }

    .pet-details h3 {
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

    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-pending {
        background: rgba(255, 193, 7, 0.2);
        color: #ffc107;
    }

    .status-approved {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
    }

    .status-rejected {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
    }

    .application-content {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 20px;
    }

    .adopter-info,
    .application-details {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
    }

    .info-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        font-weight: 600;
        color: #666;
        flex: 0 0 100px;
    }

    .info-value {
        color: #2c3e50;
        flex: 1;
        text-align: right;
    }

    .application-reason {
        background: #e3f2fd;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #2196f3;
        margin: 15px 0;
    }

    .application-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
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
        color: #fd7e14;
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
    }

    .pagination a:hover {
        background: #e9ecef;
        text-decoration: none;
    }

    .pagination .current {
        background: #fd7e14;
        color: white;
        border-color: #fd7e14;
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
        max-width: 600px;
    }

    .modal-header {
        background: linear-gradient(135deg, #fd7e14, #e83e8c);
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
    .form-group textarea:focus {
        outline: none;
        border-color: #fd7e14;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
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

    .message.error {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.3);
        position: static;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
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

        .controls-grid {
            grid-template-columns: 1fr;
        }

        .application-content {
            grid-template-columns: 1fr;
            gap: 20px;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>
    <!-- Include Shelter Navbar -->
    <?php include_once __DIR__ . '/../common/navbar_shelter.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-heart"></i> Adoption Requests</h1>
                <p>Review and manage adoption applications for your pets</p>
            </div>
            <div class="header-actions">
                <a href="<?php echo $BASE_URL; ?>shelter/viewPets.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> View Pets
                </a>
                <a href="<?php echo $BASE_URL; ?>shelter/dashboard.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Display any errors -->
        <?php if (!empty($error_message)): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card total" onclick="filterByStatus('')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Requests</h3>
                        <div class="stat-number"><?php echo $stats['total_requests']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pending" onclick="filterByStatus('pending')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Pending Review</h3>
                        <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card approved" onclick="filterByStatus('approved')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Approved</h3>
                        <div class="stat-number"><?php echo $stats['approved_requests']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card rejected" onclick="filterByStatus('rejected')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Rejected</h3>
                        <div class="stat-number"><?php echo $stats['rejected_requests']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controls Section -->
        <div class="controls-section">
            <h2 class="controls-title">Filter & Search Applications</h2>

            <form method="GET" action="" id="filtersForm">
                <div class="controls-grid">
                    <div class="control-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>
                                Pending</option>
                            <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>
                                Approved</option>
                            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>
                                Rejected</option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label for="pet">Pet</label>
                        <select name="pet" id="pet">
                            <option value="">All Pets</option>
                            <?php foreach ($pets_list as $pet): ?>
                            <option value="<?php echo $pet['pet_id']; ?>"
                                <?php echo $filter_pet == $pet['pet_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pet['pet_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="control-group">
                        <label for="sort">Sort By</label>
                        <select name="sort" id="sort">
                            <option value="application_date"
                                <?php echo $sort_by === 'application_date' ? 'selected' : ''; ?>>Application Date
                            </option>
                            <option value="pet_name" <?php echo $sort_by === 'pet_name' ? 'selected' : ''; ?>>Pet Name
                            </option>
                            <option value="first_name" <?php echo $sort_by === 'first_name' ? 'selected' : ''; ?>>
                                Adopter Name</option>
                            <option value="application_status"
                                <?php echo $sort_by === 'application_status' ? 'selected' : ''; ?>>Status</option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label for="order">Order</label>
                        <select name="order" id="order">
                            <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Newest First
                            </option>
                            <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Oldest First
                            </option>
                        </select>
                    </div>

                    <div class="control-group search-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search"
                            placeholder="Search adopter name, email, or pet name"
                            value="<?php echo htmlspecialchars($search_query); ?>">
                        <i class="fas fa-search"></i>
                    </div>

                    <div class="control-group" style="display: flex; gap: 10px; align-items: end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="<?php echo $BASE_URL; ?>shelter/adoptionRequests.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Applications Section -->
        <div class="applications-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-clipboard-list"></i>
                    Adoption Applications
                    <?php if ($total_applications > 0): ?>
                    (<?php echo $total_applications; ?> total)
                    <?php endif; ?>
                </h2>

                <?php if ($total_applications > 0): ?>
                <div>
                    <span class="text-muted">
                        Showing <?php echo min($per_page, $total_applications - $offset); ?> of
                        <?php echo $total_applications; ?> applications
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($applications)): ?>
            <?php foreach ($applications as $app): ?>
            <div class="application-card" data-app-id="<?php echo $app['application_id']; ?>">
                <!-- Application Header -->
                <div class="application-header">
                    <div class="pet-info">
                        <?php if (!empty($app['primary_image'])): ?>
                        <img src="<?php echo $BASE_URL . 'uploads/pets/' . htmlspecialchars($app['primary_image']); ?>"
                            alt="<?php echo htmlspecialchars($app['pet_name']); ?>" class="pet-image">
                        <?php else: ?>
                        <div class="pet-image">
                            <i class="fas fa-paw"></i>
                        </div>
                        <?php endif; ?>

                        <div class="pet-details">
                            <h3><?php echo htmlspecialchars($app['pet_name']); ?></h3>
                            <div class="pet-meta">
                                <span><i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($app['species'] ?? 'Unknown'); ?></span>
                                <span><i class="fas fa-birthday-cake"></i>
                                    <?php echo htmlspecialchars($app['age'] ?? 'Unknown'); ?> years old</span>
                                <?php if (!empty($app['category_name'])): ?>
                                <span><i class="fas fa-folder"></i>
                                    <?php echo htmlspecialchars($app['category_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: right;">
                        <div class="status-badge status-<?php echo $app['application_status']; ?>">
                            <?php 
                                    switch($app['application_status']) {
                                        case 'pending': echo '<i class="fas fa-hourglass-half"></i> Pending'; break;
                                        case 'approved': echo '<i class="fas fa-check-circle"></i> Approved'; break;
                                        case 'rejected': echo '<i class="fas fa-times-circle"></i> Rejected'; break;
                                        default: echo ucfirst($app['application_status']);
                                    }
                                    ?>
                        </div>
                        <div class="text-muted mt-2" style="font-size: 0.8rem;">
                            Applied: <?php echo date('M j, Y g:i A', strtotime($app['application_date'])); ?>
                        </div>
                        <?php if (!empty($app['reviewer_first_name'])): ?>
                        <div class="text-muted" style="font-size: 0.8rem;">
                            Reviewed by:
                            <?php echo htmlspecialchars($app['reviewer_first_name'] . ' ' . $app['reviewer_last_name']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Application Content -->
                <div class="application-content">
                    <!-- Adopter Information -->
                    <div class="adopter-info">
                        <h4 class="info-title">
                            <i class="fas fa-user"></i> Adopter Information
                        </h4>

                        <div class="info-row">
                            <span class="info-label">Name:</span>
                            <span
                                class="info-value"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value">
                                <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>" style="color: inherit;">
                                    <?php echo htmlspecialchars($app['email']); ?>
                                </a>
                            </span>
                        </div>

                        <?php if (!empty($app['phone'])): ?>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value">
                                <a href="tel:<?php echo htmlspecialchars($app['phone']); ?>" style="color: inherit;">
                                    <?php echo htmlspecialchars($app['phone']); ?>
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($app['address'])): ?>
                        <div class="info-row">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?php echo htmlspecialchars($app['address']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Application Details -->
                    <div class="application-details">
                        <h4 class="info-title">
                            <i class="fas fa-clipboard"></i> Application Details
                        </h4>

                        <?php if (!empty($app['housing_type'])): ?>
                        <div class="info-row">
                            <span class="info-label">Housing:</span>
                            <span class="info-value"><?php echo htmlspecialchars($app['housing_type']); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="info-row">
                            <span class="info-label">Experience:</span>
                            <span class="info-value">
                                <?php echo $app['has_experience'] ? '<i class="fas fa-check text-success"></i> Yes' : '<i class="fas fa-times text-danger"></i> No'; ?>
                            </span>
                        </div>

                        <?php if (!empty($app['previous_pets'])): ?>
                        <div class="info-row">
                            <span class="info-label">Previous Pets:</span>
                            <span class="info-value"><?php echo htmlspecialchars($app['previous_pets']); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($app['household_members'])): ?>
                        <div class="info-row">
                            <span class="info-label">Household:</span>
                            <span class="info-value"><?php echo htmlspecialchars($app['household_members']); ?>
                                members</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Application Reason -->
                <?php if (!empty($app['reason_for_adoption'])): ?>
                <div class="application-reason">
                    <strong><i class="fas fa-quote-left"></i> Reason for Adoption:</strong>
                    <p style="margin: 10px 0 0 0; font-style: italic;">
                        "<?php echo nl2br(htmlspecialchars($app['reason_for_adoption'])); ?>"
                    </p>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="application-actions">
                    <?php if ($app['application_status'] === 'pending'): ?>
                    <button type="button" class="btn btn-success btn-sm"
                        onclick="updateApplicationStatus(<?php echo $app['application_id']; ?>, 'approved')">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button type="button" class="btn btn-danger btn-sm"
                        onclick="updateApplicationStatus(<?php echo $app['application_id']; ?>, 'rejected')">
                        <i class="fas fa-times"></i> Reject
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-warning btn-sm"
                        onclick="updateApplicationStatus(<?php echo $app['application_id']; ?>, 'pending')">
                        <i class="fas fa-undo"></i> Reset to Pending
                    </button>
                    <?php endif; ?>

                    <button type="button" class="btn btn-secondary btn-sm"
                        onclick="viewApplicationDetails(<?php echo $app['application_id']; ?>)">
                        <i class="fas fa-eye"></i> View Details
                    </button>

                    <button type="button" class="btn btn-danger btn-sm"
                        onclick="deleteApplication(<?php echo $app['application_id']; ?>)">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-section">
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <?php endif; ?>

                    <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                    <?php if ($start_page > 2): ?>
                    <span>...</span>
                    <?php endif;
                            endif;

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                    <?php endfor;

                            if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                    <span>...</span>
                    <?php endif; ?>
                    <a
                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                    <?php endif;

                            if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3 class="empty-title">No Adoption Applications Found</h3>
                <p class="empty-text">
                    <?php if ($stats['total_requests'] === 0): ?>
                    You haven't received any adoption applications yet. When pet lovers apply to adopt your pets,
                    they'll appear here for your review.
                    <?php else: ?>
                    No applications match your current filters. Try adjusting your search criteria or clearing the
                    filters.
                    <?php endif; ?>
                </p>
                <?php if ($stats['total_requests'] === 0): ?>
                <a href="<?php echo $BASE_URL; ?>shelter/addPet.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Your First Pet
                </a>
                <?php else: ?>
                <a href="<?php echo $BASE_URL; ?>shelter/adoptionRequests.php" class="btn btn-primary">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Application Status</h3>
                <button type="button" class="modal-close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <input type="hidden" id="statusApplicationId" name="application_id">

                    <div class="form-group">
                        <label for="statusSelect">New Status</label>
                        <select id="statusSelect" name="new_status" required>
                            <option value="pending">Pending Review</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="adminNotes">Admin Notes (Optional)</label>
                        <textarea id="adminNotes" name="admin_notes"
                            placeholder="Add any notes about this status change..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitStatusUpdate()">Update Status</button>
            </div>
        </div>
    </div>

    <!-- Application Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Application Details</h3>
                <button type="button" class="modal-close" onclick="closeModal('detailsModal')">&times;</button>
            </div>
            <div class="modal-body" id="detailsModalBody">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('detailsModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
    // Global variables
    const BASE_URL = '<?php echo $BASE_URL; ?>';
    let currentApplicationId = null;

    // Filter by status (used by stat cards)
    function filterByStatus(status) {
        const form = document.getElementById('filtersForm');
        const statusSelect = document.getElementById('status');
        statusSelect.value = status;
        form.submit();
    }

    // Update application status
    function updateApplicationStatus(applicationId, newStatus) {
        currentApplicationId = applicationId;

        // Set form values
        document.getElementById('statusApplicationId').value = applicationId;
        document.getElementById('statusSelect').value = newStatus;

        // Show modal
        document.getElementById('statusModal').style.display = 'block';
    }

    // Submit status update
    function submitStatusUpdate() {
        const form = document.getElementById('statusForm');
        const formData = new FormData(form);
        formData.append('action', 'update_status');

        // Show loading
        const submitBtn = event.target;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;

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
                    showMessage(data.message, 'success');
                    closeModal('statusModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred while updating the status', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    }

    // Delete application
    function deleteApplication(applicationId) {
        if (!confirm('Are you sure you want to delete this application? This action cannot be undone.')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_application');
        formData.append('application_id', applicationId);

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
                    showMessage(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred while deleting the application', 'error');
            });
    }

    // View application details
    function viewApplicationDetails(applicationId) {
        // For now, just show the modal with basic info
        // In a full implementation, you'd load detailed info via AJAX
        document.getElementById('detailsModalBody').innerHTML = `
                <p>Detailed view for application #${applicationId}</p>
                <p>This feature can be enhanced to show complete application history, notes, and other relevant information.</p>
            `;
        document.getElementById('detailsModal').style.display = 'block';
    }

    // Modal functions
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Show message
    function showMessage(message, type = 'success') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;

        document.body.appendChild(messageDiv);

        // Remove message after 5 seconds
        setTimeout(() => {
            messageDiv.remove();
        }, 5000);
    }

    // Auto-submit form on filter change
    document.getElementById('filtersForm').addEventListener('change', function(e) {
        if (e.target.name !== 'search') {
            this.submit();
        }
    });

    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    };

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-focus search input if it has value
        const searchInput = document.getElementById('search');
        if (searchInput.value) {
            searchInput.focus();
            searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
        }
    });
    </script>
</body>

</html>