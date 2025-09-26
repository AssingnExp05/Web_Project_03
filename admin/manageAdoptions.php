<?php
// admin/manageAdoptions.php - Admin Adoption Management Page
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
$page_title = 'Manage Adoptions - Admin Dashboard';

// Initialize variables
$adoptions = [];
$total_adoptions = 0;
$total_pages = 1;
$stats = [
    'total_adoptions' => 0,
    'pending_applications' => 0,
    'approved_adoptions' => 0,
    'completed_adoptions' => 0,
    'rejected_applications' => 0,
    'this_month_adoptions' => 0
];

// Filter parameters
$filter_status = $_GET['status'] ?? '';
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
                case 'update_status':
                    $adoption_id = intval($_POST['adoption_id'] ?? 0);
                    $new_status = $_POST['status'] ?? '';
                    $admin_notes = $_POST['admin_notes'] ?? '';
                    
                    if ($adoption_id > 0 && in_array($new_status, ['pending', 'approved', 'rejected'])) {
                        $db->beginTransaction();
                        
                        try {
                            // Update adoption application
                            $stmt = $db->prepare("
                                UPDATE adoption_applications 
                                SET application_status = ?, reviewed_by = ? 
                                WHERE application_id = ?
                            ");
                            $stmt->execute([$new_status, $user_id, $adoption_id]);
                            
                            // If approved, create adoption record and update pet status
                            if ($new_status === 'approved') {
                                // Get application details
                                $stmt = $db->prepare("
                                    SELECT aa.pet_id, aa.adopter_id, aa.shelter_id, p.adoption_fee
                                    FROM adoption_applications aa
                                    JOIN pets p ON aa.pet_id = p.pet_id
                                    WHERE aa.application_id = ?
                                ");
                                $stmt->execute([$adoption_id]);
                                $app_details = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($app_details) {
                                    // Check if adoption record already exists
                                    $stmt = $db->prepare("
                                        SELECT adoption_id FROM adoptions 
                                        WHERE application_id = ?
                                    ");
                                    $stmt->execute([$adoption_id]);
                                    
                                    if (!$stmt->fetch()) {
                                        // Create adoption record
                                        $stmt = $db->prepare("
                                            INSERT INTO adoptions (application_id, pet_id, adopter_id, shelter_id, adoption_date, adoption_fee_paid)
                                            VALUES (?, ?, ?, ?, CURDATE(), ?)
                                        ");
                                        $stmt->execute([
                                            $adoption_id, 
                                            $app_details['pet_id'], 
                                            $app_details['adopter_id'],
                                            $app_details['shelter_id'],
                                            $app_details['adoption_fee']
                                        ]);
                                    }
                                    
                                    // Update pet status to adopted
                                    $stmt = $db->prepare("
                                        UPDATE pets 
                                        SET status = 'adopted'
                                        WHERE pet_id = ?
                                    ");
                                    $stmt->execute([$app_details['pet_id']]);
                                }
                            } elseif ($new_status === 'rejected') {
                                // If rejected, make sure pet is available again
                                $stmt = $db->prepare("
                                    SELECT pet_id FROM adoption_applications 
                                    WHERE application_id = ?
                                ");
                                $stmt->execute([$adoption_id]);
                                $pet_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($pet_data) {
                                    $stmt = $db->prepare("
                                        UPDATE pets 
                                        SET status = 'available'
                                        WHERE pet_id = ?
                                    ");
                                    $stmt->execute([$pet_data['pet_id']]);
                                }
                            }
                            
                            $db->commit();
                            echo json_encode(['success' => true, 'message' => 'Adoption status updated successfully']);
                        } catch (Exception $e) {
                            $db->rollback();
                            echo json_encode(['success' => false, 'message' => 'Failed to update adoption status: ' . $e->getMessage()]);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                    }
                    break;
                    
                case 'delete_application':
                    $adoption_id = intval($_POST['adoption_id'] ?? 0);
                    
                    if ($adoption_id > 0) {
                        $db->beginTransaction();
                        
                        try {
                            // Get pet_id before deleting application
                            $stmt = $db->prepare("SELECT pet_id FROM adoption_applications WHERE application_id = ?");
                            $stmt->execute([$adoption_id]);
                            $pet_data = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Delete related adoption record first
                            $stmt = $db->prepare("DELETE FROM adoptions WHERE application_id = ?");
                            $stmt->execute([$adoption_id]);
                            
                            // Delete adoption application
                            $stmt = $db->prepare("DELETE FROM adoption_applications WHERE application_id = ?");
                            $stmt->execute([$adoption_id]);
                            
                            // Update pet status back to available if no other approved applications
                            if ($pet_data) {
                                $stmt = $db->prepare("
                                    SELECT COUNT(*) as count FROM adoption_applications 
                                    WHERE pet_id = ? AND application_status = 'approved'
                                ");
                                $stmt->execute([$pet_data['pet_id']]);
                                $approved_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                
                                if ($approved_count == 0) {
                                    $stmt = $db->prepare("
                                        UPDATE pets 
                                        SET status = 'available'
                                        WHERE pet_id = ?
                                    ");
                                    $stmt->execute([$pet_data['pet_id']]);
                                }
                            }
                            
                            $db->commit();
                            echo json_encode(['success' => true, 'message' => 'Adoption application deleted successfully']);
                        } catch (Exception $e) {
                            $db->rollback();
                            echo json_encode(['success' => false, 'message' => 'Failed to delete adoption application: ' . $e->getMessage()]);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
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
            // Total adoptions (applications)
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_adoptions'] = $result ? (int)$result['count'] : 0;
            
            // Pending applications
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE application_status = 'pending'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['pending_applications'] = $result ? (int)$result['count'] : 0;
            
            // Approved adoptions
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE application_status = 'approved'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['approved_adoptions'] = $result ? (int)$result['count'] : 0;
            
            // Rejected applications
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE application_status = 'rejected'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['rejected_applications'] = $result ? (int)$result['count'] : 0;
            
            // This month adoptions
            $stmt = $db->prepare("
                SELECT COUNT(*) as count FROM adoption_applications 
                WHERE MONTH(application_date) = MONTH(CURDATE()) 
                AND YEAR(application_date) = YEAR(CURDATE())
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['this_month_adoptions'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            error_log("Stats error: " . $e->getMessage());
        }
        
        // Build the adoptions query
        $where_conditions = [];
        $params = [];
        
        if (!empty($filter_status)) {
            $where_conditions[] = "aa.application_status = ?";
            $params[] = $filter_status;
        }
        
        if (!empty($filter_date_from)) {
            $where_conditions[] = "DATE(aa.application_date) >= ?";
            $params[] = $filter_date_from;
        }
        
        if (!empty($filter_date_to)) {
            $where_conditions[] = "DATE(aa.application_date) <= ?";
            $params[] = $filter_date_to;
        }
        
        if (!empty($search_query)) {
            $where_conditions[] = "(p.pet_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR s.shelter_name LIKE ?)";
            $search_term = "%$search_query%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count for pagination
        $count_query = "
            SELECT COUNT(*) as total 
            FROM adoption_applications aa
            LEFT JOIN pets p ON aa.pet_id = p.pet_id
            LEFT JOIN users u ON aa.adopter_id = u.user_id
            LEFT JOIN shelters s ON aa.shelter_id = s.shelter_id
            $where_clause
        ";
        $stmt = $db->prepare($count_query);
        $stmt->execute($params);
        $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_adoptions = $count_result ? (int)$count_result['total'] : 0;
        $total_pages = max(1, ceil($total_adoptions / $per_page));
        
        // Get adoptions with pagination
        try {
            $adoptions_query = "
                SELECT aa.*, 
                       p.pet_name, p.age, p.gender, p.size, p.primary_image,
                       pb.breed_name, pc.category_name,
                       u.first_name, u.last_name, u.email, u.phone,
                       s.shelter_name,
                       admin_u.first_name as admin_first_name, admin_u.last_name as admin_last_name,
                       ad.adoption_date
                FROM adoption_applications aa
                LEFT JOIN pets p ON aa.pet_id = p.pet_id
                LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
                LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
                LEFT JOIN users u ON aa.adopter_id = u.user_id
                LEFT JOIN shelters s ON aa.shelter_id = s.shelter_id
                LEFT JOIN users admin_u ON aa.reviewed_by = admin_u.user_id
                LEFT JOIN adoptions ad ON aa.application_id = ad.application_id
                $where_clause
                ORDER BY aa.application_date DESC
                LIMIT $per_page OFFSET $offset
            ";
            
            $stmt = $db->prepare($adoptions_query);
            $stmt->execute($params);
            $adoptions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Adoptions query error: " . $e->getMessage());
            $adoptions = [];
        }
    }
} catch (Exception $e) {
    error_log("Manage Adoptions database error: " . $e->getMessage());
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

    .stat-card.approved {
        --color: #28a745;
    }

    .stat-card.rejected {
        --color: #dc3545;
    }

    .stat-card.monthly {
        --color: #fd7e14;
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

    /* Adoptions Table */
    .adoptions-section {
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

    .adoptions-table {
        width: 100%;
        border-collapse: collapse;
    }

    .adoptions-table th,
    .adoptions-table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #f1f1f1;
        vertical-align: top;
    }

    .adoptions-table th {
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

    .adoptions-table tr {
        transition: background-color 0.3s ease;
    }

    .adoptions-table tr:hover {
        background: #f8f9fa;
    }

    /* Pet Info */
    .pet-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .pet-photo {
        width: 60px;
        height: 60px;
        border-radius: 10px;
        object-fit: cover;
        background: #f8f9fa;
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

    /* Adopter Info */
    .adopter-info h4 {
        font-size: 0.9rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 2px;
    }

    .adopter-meta {
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

    /* Actions */
    .actions-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    /* Application Details */
    .application-notes {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.85rem;
        color: #666;
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
        margin: 5% auto;
        padding: 0;
        border-radius: 15px;
        width: 90%;
        max-width: 600px;
        position: relative;
        max-height: 90vh;
        overflow: hidden;
    }

    .modal-header {
        background: linear-gradient(135deg, #28a745, #20c997);
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
        max-height: 60vh;
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
        border-color: #28a745;
    }

    .form-group textarea {
        resize: vertical;
        min-height: 80px;
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
        background: #28a745;
        color: white;
        border-color: #28a745;
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

        .adoptions-table {
            font-size: 0.85rem;
        }

        .adoptions-table th,
        .adoptions-table td {
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
                <h1><i class="fas fa-heart"></i> Manage Adoptions</h1>
                <p>Monitor and manage pet adoption applications and processes</p>
            </div>
            <div class="header-actions">
                <button onclick="exportAdoptions()" class="btn btn-secondary">
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
                        <h3>Total Applications</h3>
                        <div class="stat-number"><?php echo $stats['total_adoptions']; ?></div>
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
                        <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
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
                        <div class="stat-number"><?php echo $stats['approved_adoptions']; ?></div>
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
                        <div class="stat-number"><?php echo $stats['rejected_applications']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card monthly">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>This Month</h3>
                        <div class="stat-number"><?php echo $stats['this_month_adoptions']; ?></div>
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
                Filter & Search Adoptions
            </div>
            <form method="GET" action="" id="filtersForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Status</label>
                        <select name="status" onchange="document.getElementById('filtersForm').submit()">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>
                                Pending</option>
                            <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>
                                Approved</option>
                            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>
                                Rejected</option>
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
                            placeholder="Search pet, adopter, shelter..." onkeypress="handleSearchKeypress(event)">
                        <i class="fas fa-search"></i>
                    </div>

                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 5px;">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>

                    <div class="filter-group">
                        <a href="<?php echo $BASE_URL; ?>admin/manageAdoptions.php" class="btn btn-secondary"
                            style="width: 100%; margin-top: 5px; text-align: center;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Adoptions Table -->
        <div class="adoptions-section fade-in">
            <div class="section-header">
                <h2 class="section-title">
                    Adoption Records
                    <?php if ($total_adoptions > 0): ?>
                    <span style="color: #666; font-weight: normal; font-size: 0.9rem;">
                        (<?php echo number_format($total_adoptions); ?> total)
                    </span>
                    <?php endif; ?>
                </h2>
                <div style="display: flex; gap: 10px;">
                    <span style="font-size: 0.9rem; color: #666;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                </div>
            </div>

            <?php if (empty($adoptions)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-heart-broken"></i>
                </div>
                <h3 class="empty-title">No Adoption Records Found</h3>
                <p class="empty-text">
                    <?php if (!empty($filter_status) || !empty($search_query) || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                    No adoption records match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                    There are no adoption applications in the system yet.
                    <?php endif; ?>
                </p>
                <?php if (!empty($filter_status) || !empty($search_query) || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                <a href="<?php echo $BASE_URL; ?>admin/manageAdoptions.php" class="btn btn-primary">
                    <i class="fas fa-eye"></i> View All Records
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <!-- Adoptions Table -->
            <div style="overflow-x: auto;">
                <table class="adoptions-table">
                    <thead>
                        <tr>
                            <th>Pet Information</th>
                            <th>Adopter</th>
                            <th>Shelter</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adoptions as $adoption): ?>
                        <tr data-adoption-id="<?php echo $adoption['application_id']; ?>">
                            <td>
                                <div class="pet-info">
                                    <?php if (!empty($adoption['primary_image'])): ?>
                                    <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($adoption['primary_image']); ?>"
                                        alt="<?php echo htmlspecialchars($adoption['pet_name']); ?>" class="pet-photo">
                                    <?php else: ?>
                                    <div class="pet-photo"
                                        style="display: flex; align-items: center; justify-content: center; background: #f8f9fa; color: #666;">
                                        <i class="fas fa-paw"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div class="pet-details">
                                        <h4><?php echo htmlspecialchars($adoption['pet_name'] ?? 'Unknown Pet'); ?></h4>
                                        <div class="pet-meta">
                                            <div><i class="fas fa-info-circle"></i>
                                                <?php echo htmlspecialchars(($adoption['category_name'] ?? 'Unknown') . ' • ' . ($adoption['breed_name'] ?? 'Unknown')); ?>
                                            </div>
                                            <div><i class="fas fa-birthday-cake"></i>
                                                <?php echo htmlspecialchars($adoption['age'] ?? 'Unknown'); ?> years old
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="adopter-info">
                                    <h4><?php echo htmlspecialchars(($adoption['first_name'] ?? '') . ' ' . ($adoption['last_name'] ?? '')); ?>
                                    </h4>
                                    <div class="adopter-meta">
                                        <div><i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($adoption['email'] ?? 'No email'); ?></div>
                                        <?php if (!empty($adoption['phone'])): ?>
                                        <div><i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($adoption['phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem;">
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo htmlspecialchars($adoption['shelter_name'] ?? 'Unknown Shelter'); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span
                                    class="status-badge status-<?php echo $adoption['application_status'] ?? 'pending'; ?>">
                                    <?php 
                                        $status = $adoption['application_status'] ?? 'pending';
                                        $status_icons = [
                                            'pending' => 'hourglass-half',
                                            'approved' => 'check-circle',
                                            'rejected' => 'times-circle'
                                        ];
                                        $icon = $status_icons[$status] ?? 'question-circle';
                                        echo '<i class="fas fa-' . $icon . '"></i> ' . ucfirst($status);
                                    ?>
                                </span>
                                <?php if ($adoption['application_status'] === 'approved' && !empty($adoption['adoption_date'])): ?>
                                <div style="font-size: 0.7rem; color: #666; margin-top: 4px;">
                                    Adopted: <?php echo date('M j, Y', strtotime($adoption['adoption_date'])); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 0.9rem;">
                                    <div style="font-weight: 600; color: #2c3e50;">
                                        <?php echo date('M j, Y', strtotime($adoption['application_date'])); ?>
                                    </div>
                                    <div style="color: #666; font-size: 0.8rem;">
                                        <?php echo date('g:i A', strtotime($adoption['application_date'])); ?>
                                    </div>
                                </div>
                                <?php if (!empty($adoption['reviewed_by'])): ?>
                                <div style="font-size: 0.75rem; color: #28a745; margin-top: 5px;">
                                    <i class="fas fa-user-check"></i> Reviewed by
                                    <?php echo htmlspecialchars(($adoption['admin_first_name'] ?? '') . ' ' . ($adoption['admin_last_name'] ?? '')); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($adoption['reason_for_adoption'])): ?>
                                <div class="application-notes"
                                    title="<?php echo htmlspecialchars($adoption['reason_for_adoption']); ?>">
                                    <strong>Application:</strong>
                                    <?php echo htmlspecialchars($adoption['reason_for_adoption']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if (empty($adoption['reason_for_adoption'])): ?>
                                <span style="color: #999; font-size: 0.8rem;">No notes</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-group">
                                    <button class="btn btn-info btn-sm"
                                        onclick="viewApplicationDetails(<?php echo $adoption['application_id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>

                                    <?php if ($adoption['application_status'] === 'pending'): ?>
                                    <button class="btn btn-success btn-sm"
                                        onclick="updateAdoptionStatus(<?php echo $adoption['application_id']; ?>, 'approved')">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm"
                                        onclick="updateAdoptionStatus(<?php echo $adoption['application_id']; ?>, 'rejected')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                    <?php elseif ($adoption['application_status'] === 'approved'): ?>
                                    <button class="btn btn-warning btn-sm"
                                        onclick="updateAdoptionStatus(<?php echo $adoption['application_id']; ?>, 'pending')">
                                        <i class="fas fa-undo"></i> Pending
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-warning btn-sm"
                                        onclick="updateAdoptionStatus(<?php echo $adoption['application_id']; ?>, 'pending')">
                                        <i class="fas fa-undo"></i> Review Again
                                    </button>
                                    <?php endif; ?>

                                    <button class="btn btn-danger btn-sm"
                                        onclick="confirmDeleteApplication(<?php echo $adoption['application_id']; ?>, '<?php echo htmlspecialchars($adoption['pet_name'] ?? 'Unknown Pet', ENT_QUOTES); ?>')">
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
                    <?php echo min($page * $per_page, $total_adoptions); ?> of
                    <?php echo number_format($total_adoptions); ?> records
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

    <!-- Application Details Modal -->
    <div id="applicationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Application Details</h3>
                <button class="modal-close" onclick="closeModal('applicationModal')">&times;</button>
            </div>
            <div class="modal-body" id="applicationDetails">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('applicationModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Application Status</h3>
                <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="newStatus">New Status</label>
                    <select id="newStatus">
                        <option value="pending">Pending Review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="adminNotes">Admin Notes (Optional)</label>
                    <textarea id="adminNotes" placeholder="Add any notes or reasons for this status change..."
                        rows="4"></textarea>
                </div>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <p style="margin: 0; color: #666; font-size: 0.9rem;">
                        <strong>Note:</strong> Status changes will be logged and visible to the shelter and adopter.
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
                <h3 class="modal-title">Confirm Application Deletion</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"
                        style="font-size: 3rem; color: #dc3545; margin-bottom: 15px;"></i>
                </div>
                <p><strong>Are you sure you want to delete this adoption application?</strong></p>
                <p id="deletePetName" style="color: #666; margin: 10px 0;"></p>
                <div
                    style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <p style="margin: 0; color: #856404;"><strong>⚠️ Warning:</strong></p>
                    <ul style="margin: 10px 0 0 20px; color: #856404;">
                        <li>This action cannot be undone</li>
                        <li>All application data will be permanently deleted</li>
                        <li>Related adoption records will be removed</li>
                        <li>Pet status will be updated accordingly</li>
                    </ul>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn btn-danger" onclick="confirmDeleteApplication()">
                    <i class="fas fa-trash"></i> Delete Application
                </button>
            </div>
        </div>
    </div>

    <script>
    // Global variables
    let currentAdoptionId = null;
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

    // View application details
    function viewApplicationDetails(adoptionId) {
        currentAdoptionId = adoptionId;

        // Show loading
        document.getElementById('applicationDetails').innerHTML =
            '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        document.getElementById('applicationModal').style.display = 'block';

        // For now, show basic details (you can implement the endpoint later)
        const row = document.querySelector(`tr[data-adoption-id="${adoptionId}"]`);
        if (row) {
            const petName = row.querySelector('.pet-details h4').textContent;
            const adopterName = row.querySelector('.adopter-info h4').textContent;
            const status = row.querySelector('.status-badge').textContent.trim();

            const html = `
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <strong>Application ID:</strong> #${adoptionId}
                        </div>
                        <div style="border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <strong>Pet Name:</strong> ${petName}
                        </div>
                        <div style="border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <strong>Adopter:</strong> ${adopterName}
                        </div>
                        <div style="border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <strong>Status:</strong> ${status}
                        </div>
                        <div style="text-align: center; color: #666; font-style: italic;">
                            Additional details can be loaded via API endpoint
                        </div>
                    </div>
                `;
            document.getElementById('applicationDetails').innerHTML = html;
        }
    }

    // Update adoption status
    function updateAdoptionStatus(adoptionId, status) {
        currentAdoptionId = adoptionId;
        document.getElementById('newStatus').value = status;
        document.getElementById('adminNotes').value = '';
        document.getElementById('statusModal').style.display = 'block';
    }

    function confirmStatusUpdate() {
        const status = document.getElementById('newStatus').value;
        const notes = document.getElementById('adminNotes').value;

        if (currentAdoptionId && status) {
            performAjaxAction('update_status', {
                adoption_id: currentAdoptionId,
                status: status,
                admin_notes: notes
            });
            closeModal('statusModal');
        }
    }

    // Delete application
    function confirmDeleteApplication(adoptionId, petName) {
        if (adoptionId && petName) {
            currentAdoptionId = adoptionId;
            currentPetName = petName;
            document.getElementById('deletePetName').textContent = `Application for: ${petName}`;
            document.getElementById('deleteModal').style.display = 'block';
        }
    }

    function confirmDeleteApplication() {
        if (currentAdoptionId) {
            performAjaxAction('delete_application', {
                adoption_id: currentAdoptionId
            });
            closeModal('deleteModal');
        }
    }

    // Close modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        currentAdoptionId = null;
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

    // Export adoptions data
    function exportAdoptions() {
        // This would need to be implemented with proper export functionality
        showMessage('Export functionality will be implemented', 'info');
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(event) {
        // Escape key to close modals
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => modal.style.display = 'none');
            currentAdoptionId = null;
            currentPetName = null;
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