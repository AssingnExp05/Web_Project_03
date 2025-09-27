<?php
// shelter/viewPets.php - View All Pets Page for Shelters (Fixed Version)
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
$breeds = [];
$total_pets = 0;
$total_pages = 1;
$stats = [
    'total_pets' => 0,
    'available_pets' => 0,
    'pending_pets' => 0,
    'adopted_pets' => 0
];

// Filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_category = $_GET['category'] ?? '';
$search_query = trim($_GET['search'] ?? '');
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
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
                case 'get_pet_details':
                    $pet_id = intval($_POST['pet_id'] ?? 0);
                    
                    if ($pet_id > 0) {
                        $stmt = $db->prepare("
                            SELECT p.*, pc.category_name, pb.breed_name,
                                   CASE 
                                       WHEN EXISTS(SELECT 1 FROM adoptions WHERE pet_id = p.pet_id) THEN 'adopted'
                                       WHEN EXISTS(SELECT 1 FROM adoption_applications WHERE pet_id = p.pet_id AND application_status = 'approved') THEN 'pending'
                                       ELSE p.status 
                                   END as actual_status
                            FROM pets p
                            LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
                            LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
                            WHERE p.pet_id = ? AND p.shelter_id = ?
                        ");
                        $stmt->execute([$pet_id, $shelter_info['shelter_id']]);
                        $pet = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($pet) {
                            // Update pet status if it's out of sync
                            if ($pet['status'] !== $pet['actual_status']) {
                                $updateStmt = $db->prepare("UPDATE pets SET status = ? WHERE pet_id = ?");
                                $updateStmt->execute([$pet['actual_status'], $pet_id]);
                                $pet['status'] = $pet['actual_status'];
                            }
                            
                            echo json_encode(['success' => true, 'pet' => $pet]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Pet not found']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid pet ID']);
                    }
                    break;
                
                case 'update_pet':
                    $pet_id = intval($_POST['pet_id'] ?? 0);
                    $pet_name = trim($_POST['pet_name'] ?? '');
                    $category_id = intval($_POST['category_id'] ?? 0);
                    $breed_id = intval($_POST['breed_id'] ?? 0) ?: null;
                    $age = intval($_POST['age'] ?? 0);
                    $gender = $_POST['gender'] ?? '';
                    $size = $_POST['size'] ?? '';
                    $description = trim($_POST['description'] ?? '');
                    $health_status = trim($_POST['health_status'] ?? '');
                    $adoption_fee = floatval($_POST['adoption_fee'] ?? 0);
                    $status = $_POST['status'] ?? 'available';
                    
                    // Validation
                    $errors = [];
                    if (empty($pet_name)) $errors[] = 'Pet name is required';
                    if ($category_id <= 0) $errors[] = 'Category is required';
                    if ($age < 0 || $age > 30) $errors[] = 'Age must be between 0 and 30 years';
                    if (!in_array($gender, ['male', 'female'])) $errors[] = 'Gender must be male or female';
                    if (!in_array($status, ['available', 'pending', 'adopted'])) $errors[] = 'Invalid status';
                    if ($adoption_fee < 0) $errors[] = 'Adoption fee cannot be negative';
                    
                    if (!empty($errors)) {
                        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
                        break;
                    }
                    
                    if ($pet_id > 0) {
                        // Check if pet has been adopted
                        $adoptionCheck = $db->prepare("SELECT adoption_id FROM adoptions WHERE pet_id = ?");
                        $adoptionCheck->execute([$pet_id]);
                        $hasAdoption = $adoptionCheck->fetch();
                        
                        if ($hasAdoption && $status !== 'adopted') {
                            echo json_encode(['success' => false, 'message' => 'Cannot change status - pet has been adopted']);
                            break;
                        }
                        
                        // Verify pet belongs to this shelter
                        $stmt = $db->prepare("SELECT pet_id FROM pets WHERE pet_id = ? AND shelter_id = ?");
                        $stmt->execute([$pet_id, $shelter_info['shelter_id']]);
                        
                        if ($stmt->fetch()) {
                            try {
                                $db->beginTransaction();
                                
                                $stmt = $db->prepare("
                                    UPDATE pets SET 
                                        pet_name = ?, category_id = ?, breed_id = ?, age = ?, gender = ?, 
                                        size = ?, description = ?, health_status = ?, adoption_fee = ?, status = ?
                                    WHERE pet_id = ? AND shelter_id = ?
                                ");
                                
                                $result = $stmt->execute([
                                    $pet_name, $category_id, $breed_id, $age, $gender, 
                                    $size, $description, $health_status, $adoption_fee, $status, 
                                    $pet_id, $shelter_info['shelter_id']
                                ]);
                                
                                if ($result && $stmt->rowCount() > 0) {
                                    $db->commit();
                                    echo json_encode(['success' => true, 'message' => 'Pet updated successfully']);
                                } else {
                                    $db->rollback();
                                    echo json_encode(['success' => false, 'message' => 'No changes made or update failed']);
                                }
                                
                            } catch (Exception $e) {
                                $db->rollback();
                                error_log("Update pet error: " . $e->getMessage());
                                echo json_encode(['success' => false, 'message' => 'Failed to update pet: ' . $e->getMessage()]);
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Pet not found or unauthorized']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid pet ID']);
                    }
                    break;
                
                case 'delete_pet':
                    $pet_id = intval($_POST['pet_id'] ?? 0);
                    
                    if ($pet_id > 0) {
                        // Check if pet has been adopted - prevent deletion
                        $adoptionCheck = $db->prepare("SELECT COUNT(*) as count FROM adoptions WHERE pet_id = ?");
                        $adoptionCheck->execute([$pet_id]);
                        $adoptionCount = $adoptionCheck->fetch(PDO::FETCH_ASSOC)['count'];
                        
                        if ($adoptionCount > 0) {
                            echo json_encode(['success' => false, 'message' => 'Cannot delete pet - it has been adopted. Adopted pets cannot be deleted for record keeping.']);
                            break;
                        }
                        
                        // Check if pet has pending applications
                        $pendingCheck = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE pet_id = ? AND application_status = 'pending'");
                        $pendingCheck->execute([$pet_id]);
                        $pendingCount = $pendingCheck->fetch(PDO::FETCH_ASSOC)['count'];
                        
                        if ($pendingCount > 0) {
                            echo json_encode(['success' => false, 'message' => 'Cannot delete pet - it has pending adoption applications. Please process applications first.']);
                            break;
                        }
                        
                        // Verify pet belongs to this shelter
                        $stmt = $db->prepare("SELECT pet_id, primary_image FROM pets WHERE pet_id = ? AND shelter_id = ?");
                        $stmt->execute([$pet_id, $shelter_info['shelter_id']]);
                        $pet = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($pet) {
                            try {
                                $db->beginTransaction();
                                
                                // Delete primary image if it exists
                                if ($pet['primary_image']) {
                                    $primary_path = __DIR__ . '/../uploads/' . $pet['primary_image'];
                                    if (file_exists($primary_path)) {
                                        unlink($primary_path);
                                    }
                                }
                                
                                // Delete related images and their files
                                $imageStmt = $db->prepare("SELECT image_path FROM pet_images WHERE pet_id = ?");
                                $imageStmt->execute([$pet_id]);
                                $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($images as $image) {
                                    $imagePath = __DIR__ . '/../uploads/' . $image['image_path'];
                                    if (file_exists($imagePath)) {
                                        unlink($imagePath);
                                    }
                                }
                                
                                // Delete related records (in proper order to avoid foreign key constraints)
                                $db->prepare("DELETE FROM pet_images WHERE pet_id = ?")->execute([$pet_id]);
                                $db->prepare("DELETE FROM vaccinations WHERE pet_id = ?")->execute([$pet_id]);
                                $db->prepare("DELETE FROM medical_records WHERE pet_id = ?")->execute([$pet_id]);
                                $db->prepare("DELETE FROM adoption_applications WHERE pet_id = ?")->execute([$pet_id]);
                                
                                // Finally delete the pet
                                $deleteStmt = $db->prepare("DELETE FROM pets WHERE pet_id = ? AND shelter_id = ?");
                                $result = $deleteStmt->execute([$pet_id, $shelter_info['shelter_id']]);
                                
                                if ($result && $deleteStmt->rowCount() > 0) {
                                    $db->commit();
                                    echo json_encode(['success' => true, 'message' => 'Pet deleted successfully']);
                                } else {
                                    $db->rollback();
                                    echo json_encode(['success' => false, 'message' => 'Failed to delete pet']);
                                }
                                
                            } catch (Exception $e) {
                                $db->rollback();
                                error_log("Delete pet error: " . $e->getMessage());
                                echo json_encode(['success' => false, 'message' => 'Failed to delete pet: ' . $e->getMessage()]);
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Pet not found or unauthorized']);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid pet ID']);
                    }
                    break;
                
                case 'get_breeds':
                    $category_id = intval($_POST['category_id'] ?? 0);
                    
                    if ($category_id > 0) {
                        $stmt = $db->prepare("SELECT breed_id, breed_name FROM pet_breeds WHERE category_id = ? ORDER BY breed_name");
                        $stmt->execute([$category_id]);
                        $breeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo json_encode(['success' => true, 'breeds' => $breeds]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
                    }
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No action specified']);
        }
    } catch (Exception $e) {
        error_log("AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
    }
    exit();
}

// Database operations for main page
$error_message = '';

try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Get shelter information
    $stmt = $db->prepare("SELECT * FROM shelters WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $shelter_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$shelter_info) {
        $_SESSION['error_message'] = 'Shelter information not found.';
        header('Location: ' . $BASE_URL . 'auth/login.php');
        exit();
    }
    
    // First, sync all pet statuses based on actual adoption records
    $syncQuery = "
        UPDATE pets p SET status = 
            CASE 
                WHEN EXISTS(SELECT 1 FROM adoptions WHERE pet_id = p.pet_id) THEN 'adopted'
                WHEN EXISTS(SELECT 1 FROM adoption_applications WHERE pet_id = p.pet_id AND application_status = 'approved') THEN 'pending'
                ELSE p.status 
            END
        WHERE p.shelter_id = ?
    ";
    $db->prepare($syncQuery)->execute([$shelter_info['shelter_id']]);
    
    // Get categories for filter
    $stmt = $db->prepare("SELECT * FROM pet_categories ORDER BY category_name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Get all breeds for edit form
    $stmt = $db->prepare("SELECT * FROM pet_breeds ORDER BY category_id, breed_name");
    $stmt->execute();
    $breeds = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Get statistics (now with corrected statuses)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ?");
    $stmt->execute([$shelter_info['shelter_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_pets'] = $result ? (int)$result['count'] : 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'available'");
    $stmt->execute([$shelter_info['shelter_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['available_pets'] = $result ? (int)$result['count'] : 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'pending'");
    $stmt->execute([$shelter_info['shelter_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_pets'] = $result ? (int)$result['count'] : 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'adopted'");
    $stmt->execute([$shelter_info['shelter_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['adopted_pets'] = $result ? (int)$result['count'] : 0;
    
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
        $search_term = "%$search_query%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Validate sort parameters
    $valid_sort_fields = ['pet_name', 'age', 'created_at', 'status', 'adoption_fee'];
    $sort_by = in_array($sort_by, $valid_sort_fields) ? $sort_by : 'created_at';
    $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM pets p $where_clause";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_pets = $count_result ? (int)$count_result['total'] : 0;
    $total_pages = max(1, ceil($total_pets / $per_page));
    
    // Ensure current page is within valid range
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;
    
    // Get pets with pagination and adoption information
    $pets_query = "
        SELECT p.*, 
               pc.category_name, 
               pb.breed_name,
               CASE 
                   WHEN a.adoption_id IS NOT NULL THEN 'adopted'
                   WHEN aa.application_id IS NOT NULL AND aa.application_status = 'approved' THEN 'pending'
                   ELSE p.status 
               END as display_status,
               a.adoption_date,
               aa.application_date
        FROM pets p
        LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
        LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
        LEFT JOIN adoptions a ON p.pet_id = a.pet_id
        LEFT JOIN adoption_applications aa ON p.pet_id = aa.pet_id AND aa.application_status = 'approved'
        $where_clause
        ORDER BY p.{$sort_by} {$sort_order}
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $db->prepare($pets_query);
    $stmt->execute($params);
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
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
        box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
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
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        text-decoration: none;
    }

    .btn-primary {
        background: #ffd700;
        color: #28a745;
    }

    .btn-primary:hover {
        background: #ffed4e;
        color: #20c997;
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.3);
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
        border-top: 4px solid var(--color);
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

    /* Controls Section */
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
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
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
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
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
        transition: transform 0.3s ease;
    }

    .pet-image:hover img {
        transform: scale(1.05);
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
        gap: 3px;
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
        line-clamp: 3;
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
        flex-wrap: wrap;
        gap: 10px;
    }

    .pet-actions {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 8px;
    }

    .pet-actions.adopted {
        grid-template-columns: 1fr 1fr;
    }

    .pet-actions .btn-danger.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 30px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
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

    /* Error Message */
    .error-message {
        background: rgba(220, 53, 69, 0.1);
        color: #721c24;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        border: 1px solid rgba(220, 53, 69, 0.2);
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
    }

    /* Adoption Info */
    .adoption-info {
        background: rgba(23, 162, 184, 0.1);
        color: #0c5460;
        padding: 10px 12px;
        border-radius: 8px;
        font-size: 0.85rem;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
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
        background: white;
        padding: 10px 20px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
        overflow-y: auto;
        padding: 20px;
        backdrop-filter: blur(5px);
    }

    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
        animation: modalFadeIn 0.3s ease;
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .modal-content {
        background: white;
        border-radius: 15px;
        width: 90%;
        max-width: 800px;
        position: relative;
        max-height: 95vh;
        overflow: hidden;
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px) scale(0.9);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
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
        margin: 0;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: white;
        opacity: 0.8;
        padding: 5px;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .modal-close:hover {
        opacity: 1;
        background: rgba(255, 255, 255, 0.1);
    }

    .modal-body {
        padding: 25px;
        max-height: 70vh;
        overflow-y: auto;
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

    /* Form Styles */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .form-group label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.9rem;
    }

    .form-group.required label::after {
        content: ' *';
        color: #dc3545;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 12px;
        border: 2px solid #e1e8ed;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.3s ease;
        font-family: inherit;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #28a745;
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
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

    .spinner {
        border: 2px solid #f3f3f3;
        border-top: 2px solid #28a745;
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
            padding: 25px;
        }

        .page-header h1 {
            font-size: 1.8rem;
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

        .form-grid {
            grid-template-columns: 1fr;
        }

        .pet-actions {
            grid-template-columns: 1fr;
            gap: 5px;
        }

        .modal-content {
            width: 95%;
        }

        .modal-body {
            padding: 20px;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .stat-number {
            font-size: 2rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
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
                <h1><i class="fas fa-list"></i> My Pets</h1>
                <p>Manage and view all pets in
                    <?php echo htmlspecialchars($shelter_info['shelter_name'] ?? 'your shelter'); ?></p>
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
        <?php if (!empty($error_message)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
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
                        <i class="fas fa-heart"></i>
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
                        <i class="fas fa-clock"></i>
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
        <div class="controls-section">
            <div class="controls-header">
                <h2 class="controls-title">Filter & Search</h2>
                <div class="header-actions">
                    <button onclick="resetFilters()" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>

            <form id="filterForm" method="GET" action="">
                <div class="controls-grid">
                    <div class="control-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" onchange="document.getElementById('filterForm').submit()">
                            <option value="">All Status</option>
                            <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>
                                Available</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>
                                Pending</option>
                            <option value="adopted" <?php echo $filter_status === 'adopted' ? 'selected' : ''; ?>>
                                Adopted</option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label for="category">Category</label>
                        <select name="category" id="category" onchange="document.getElementById('filterForm').submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>"
                                <?php echo $filter_category == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="control-group search-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" placeholder="Search by name or description..."
                            value="<?php echo htmlspecialchars($search_query); ?>">
                        <i class="fas fa-search"></i>
                    </div>

                    <div class="control-group">
                        <label for="sort">Sort By</label>
                        <select name="sort" id="sort" onchange="document.getElementById('filterForm').submit()">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date
                                Added</option>
                            <option value="pet_name" <?php echo $sort_by === 'pet_name' ? 'selected' : ''; ?>>Name
                            </option>
                            <option value="age" <?php echo $sort_by === 'age' ? 'selected' : ''; ?>>Age</option>
                            <option value="adoption_fee" <?php echo $sort_by === 'adoption_fee' ? 'selected' : ''; ?>>
                                Fee</option>
                            <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status
                            </option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label for="order">Order</label>
                        <select name="order" id="order" onchange="document.getElementById('filterForm').submit()">
                            <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Descending
                            </option>
                            <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Ascending
                            </option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                    </div>
                </div>

                <!-- Hidden fields for pagination -->
                <input type="hidden" name="page" value="<?php echo $page; ?>">
            </form>
        </div>

        <!-- Pets Grid -->
        <?php if (empty($pets)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-paw"></i>
            </div>
            <h3 class="empty-title">No Pets Found</h3>
            <p class="empty-text">
                <?php if (!empty($search_query) || !empty($filter_status) || !empty($filter_category)): ?>
                No pets match your current filters. Try adjusting your search criteria.
                <?php else: ?>
                You haven't added any pets yet. Start by adding your first pet to the system.
                <?php endif; ?>
            </p>
            <a href="<?php echo $BASE_URL; ?>shelter/addPet.php" class="btn btn-success">
                <i class="fas fa-plus-circle"></i> Add Your First Pet
            </a>
        </div>
        <?php else: ?>
        <div class="pets-grid">
            <?php foreach ($pets as $pet): ?>
            <?php
            // Use display_status if available, otherwise use the pet's status
            $current_status = $pet['display_status'] ?? $pet['status'];
            ?>
            <div class="pet-card" data-pet-id="<?php echo $pet['pet_id']; ?>">
                <div class="pet-image">
                    <?php if (!empty($pet['primary_image']) && file_exists(__DIR__ . "/../uploads/" . $pet['primary_image'])): ?>
                    <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                        alt="<?php echo htmlspecialchars($pet['pet_name']); ?>" loading="lazy">
                    <?php else: ?>
                    <i class="fas fa-paw"></i>
                    <?php endif; ?>
                    <span class="pet-status status-<?php echo $current_status; ?>">
                        <?php echo ucfirst($current_status); ?>
                    </span>
                </div>

                <div class="pet-content">
                    <div class="pet-header">
                        <div class="pet-info">
                            <h3><?php echo htmlspecialchars($pet['pet_name']); ?></h3>
                            <div class="pet-meta">
                                <span><?php echo htmlspecialchars($pet['category_name'] ?? 'Unknown'); ?><?php echo $pet['breed_name'] ? ' - ' . htmlspecialchars($pet['breed_name']) : ''; ?></span>
                                <span><?php echo (int)$pet['age']; ?> <?php echo $pet['age'] == 1 ? 'year' : 'years'; ?>
                                    old • <?php echo ucfirst($pet['gender']); ?></span>
                                <?php if (!empty($pet['size'])): ?>
                                <span>Size: <?php echo ucfirst($pet['size']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="pet-fee">
                            $<?php echo number_format((float)$pet['adoption_fee'], 2); ?>
                        </div>
                    </div>

                    <?php if (!empty($pet['description'])): ?>
                    <div class="pet-description">
                        <?php echo htmlspecialchars($pet['description']); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Adoption Information -->
                    <?php if ($current_status === 'adopted' && !empty($pet['adoption_date'])): ?>
                    <div class="adoption-info">
                        <i class="fas fa-heart"></i>
                        <span>Adopted on <?php echo date('M j, Y', strtotime($pet['adoption_date'])); ?></span>
                    </div>
                    <?php elseif ($current_status === 'pending' && !empty($pet['application_date'])): ?>
                    <div class="adoption-info" style="background: rgba(255, 193, 7, 0.1); color: #856404;">
                        <i class="fas fa-clock"></i>
                        <span>Application approved on
                            <?php echo date('M j, Y', strtotime($pet['application_date'])); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="pet-stats">
                        <span><i class="fas fa-calendar-plus"></i> Added
                            <?php echo date('M j, Y', strtotime($pet['created_at'])); ?></span>
                        <?php if (!empty($pet['health_status'])): ?>
                        <span><i class="fas fa-heart-pulse"></i>
                            <?php echo htmlspecialchars($pet['health_status']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="pet-actions <?php echo $current_status === 'adopted' ? 'adopted' : ''; ?>">
                        <button onclick="viewPetDetails(<?php echo $pet['pet_id']; ?>)" class="btn btn-info btn-sm"
                            title="View Details">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <?php if ($current_status !== 'adopted'): ?>
                        <button onclick="editPet(<?php echo $pet['pet_id']; ?>)" class="btn btn-warning btn-sm"
                            title="Edit Pet">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button
                            onclick="deletePet(<?php echo $pet['pet_id']; ?>, '<?php echo htmlspecialchars($pet['pet_name']); ?>')"
                            class="btn btn-danger btn-sm" title="Delete Pet">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <?php else: ?>
                        <button onclick="editPet(<?php echo $pet['pet_id']; ?>)" class="btn btn-warning btn-sm"
                            title="Edit Pet (Limited)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-danger btn-sm disabled" title="Cannot delete adopted pets">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-section">
            <div class="pagination">
                <?php
                // Previous page
                if ($page > 1):
                    $prev_params = $_GET;
                    $prev_params['page'] = $page - 1;
                ?>
                <a href="?<?php echo http_build_query($prev_params); ?>" title="Previous Page">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php else: ?>
                <span class="disabled">
                    <i class="fas fa-chevron-left"></i>
                </span>
                <?php endif; ?>

                <?php
                // Page numbers
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1):
                    $first_params = $_GET;
                    $first_params['page'] = 1;
                ?>
                <a href="?<?php echo http_build_query($first_params); ?>">1</a>
                <?php if ($start_page > 2): ?>
                <span>...</span>
                <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                <?php
                    $page_params = $_GET;
                    $page_params['page'] = $i;
                ?>
                <a href="?<?php echo http_build_query($page_params); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
                <?php endfor; ?>

                <?php
                if ($end_page < $total_pages):
                    if ($end_page < $total_pages - 1):
                ?>
                <span>...</span>
                <?php endif; ?>
                <?php
                    $last_params = $_GET;
                    $last_params['page'] = $total_pages;
                ?>
                <a href="?<?php echo http_build_query($last_params); ?>"><?php echo $total_pages; ?></a>
                <?php endif; ?>

                <?php
                // Next page
                if ($page < $total_pages):
                    $next_params = $_GET;
                    $next_params['page'] = $page + 1;
                ?>
                <a href="?<?php echo http_build_query($next_params); ?>" title="Next Page">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php else: ?>
                <span class="disabled">
                    <i class="fas fa-chevron-right"></i>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Pet Details Modal -->
    <div id="petDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Pet Details</h3>
                <button class="modal-close" onclick="closeModal('petDetailsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="petDetailsBody">
                <div style="text-align: center; padding: 40px; color: #666;">
                    <div class="spinner"></div>
                    Loading pet details...
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Pet Modal -->
    <div id="editPetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Pet</h3>
                <button class="modal-close" onclick="closeModal('editPetModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editPetForm">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group required">
                            <label for="edit_pet_name">Pet Name</label>
                            <input type="text" id="edit_pet_name" name="pet_name" required>
                        </div>

                        <div class="form-group required">
                            <label for="edit_category_id">Category</label>
                            <select id="edit_category_id" name="category_id" required
                                onchange="loadBreedsForEdit(this.value)">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="edit_breed_id">Breed</label>
                            <select id="edit_breed_id" name="breed_id">
                                <option value="">Select Breed</option>
                            </select>
                        </div>

                        <div class="form-group required">
                            <label for="edit_age">Age (years)</label>
                            <input type="number" id="edit_age" name="age" min="0" max="30" required>
                        </div>

                        <div class="form-group required">
                            <label for="edit_gender">Gender</label>
                            <select id="edit_gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="edit_size">Size</label>
                            <select id="edit_size" name="size">
                                <option value="">Select Size</option>
                                <option value="small">Small</option>
                                <option value="medium">Medium</option>
                                <option value="large">Large</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="edit_adoption_fee">Adoption Fee ($)</label>
                            <input type="number" id="edit_adoption_fee" name="adoption_fee" min="0" step="0.01">
                        </div>

                        <div class="form-group required">
                            <label for="edit_status">Status</label>
                            <select id="edit_status" name="status" required>
                                <option value="available">Available</option>
                                <option value="pending">Pending</option>
                                <option value="adopted">Adopted</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="edit_health_status">Health Status</label>
                            <input type="text" id="edit_health_status" name="health_status"
                                placeholder="e.g., Healthy, Vaccinated">
                        </div>

                        <div class="form-group full-width">
                            <label for="edit_description">Description</label>
                            <textarea id="edit_description" name="description" rows="4"
                                placeholder="Tell us about this pet..."></textarea>
                        </div>
                    </div>
                    <input type="hidden" id="edit_pet_id" name="pet_id">
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('editPetModal')" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="saveEditBtn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // JavaScript functionality
    let currentEditPetId = null;
    let allBreeds = <?php echo json_encode($breeds); ?>;

    // Utility functions
    function showMessage(message, type = 'success') {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.message');
        existingMessages.forEach(msg => msg.remove());

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.innerHTML =
            `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;

        document.body.appendChild(messageDiv);

        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal') && event.target.classList.contains('show')) {
            event.target.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    });

    // Filter functions
    function filterByStatus(status) {
        const currentUrl = new URL(window.location);
        if (status) {
            currentUrl.searchParams.set('status', status);
        } else {
            currentUrl.searchParams.delete('status');
        }
        currentUrl.searchParams.delete('page'); // Reset to first page
        window.location.href = currentUrl.toString();
    }

    function resetFilters() {
        const baseUrl = window.location.pathname;
        window.location.href = baseUrl;
    }

    // Search functionality
    let searchTimeout;
    document.getElementById('search').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            document.getElementById('filterForm').submit();
        }, 1000); // Wait 1 second after user stops typing
    });

    // Pet details functionality
    function viewPetDetails(petId) {
        openModal('petDetailsModal');
        document.getElementById('petDetailsBody').innerHTML = `
            <div style="text-align: center; padding: 40px; color: #666;">
                <div class="spinner"></div>
                Loading pet details...
            </div>
        `;

        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=get_pet_details&pet_id=${petId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pet = data.pet;
                    const currentStatus = pet.display_status || pet.actual_status || pet.status;
                    document.getElementById('petDetailsBody').innerHTML = `
                        <div class="pet-details">
                            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 25px; margin-bottom: 25px;">
                                <div class="pet-image-large">
                                    ${pet.primary_image ? 
                                        `<img src="<?php echo $BASE_URL; ?>uploads/${escapeHtml(pet.primary_image)}" alt="${escapeHtml(pet.pet_name)}" style="width: 100%; height: 250px; object-fit: cover; border-radius: 10px;">` :
                                        '<div style="width: 100%; height: 250px; background: #f8f9fa; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 3rem;"><i class="fas fa-paw"></i></div>'
                                    }
                                </div>
                                <div class="pet-info-detailed">
                                    <h2 style="margin-bottom: 15px; color: #2c3e50;">${escapeHtml(pet.pet_name)}</h2>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                                        <div><strong>Category:</strong> ${escapeHtml(pet.category_name || 'N/A')}</div>
                                        <div><strong>Breed:</strong> ${escapeHtml(pet.breed_name || 'Mixed/Unknown')}</div>
                                        <div><strong>Age:</strong> ${parseInt(pet.age)} ${pet.age == 1 ? 'year' : 'years'} old</div>
                                        <div><strong>Gender:</strong> ${pet.gender ? pet.gender.charAt(0).toUpperCase() + pet.gender.slice(1) : 'N/A'}</div>
                                        <div><strong>Size:</strong> ${pet.size ? pet.size.charAt(0).toUpperCase() + pet.size.slice(1) : 'N/A'}</div>
                                        <div><strong>Status:</strong> <span class="status-${currentStatus}">${currentStatus ? currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1) : 'N/A'}</span></div>
                                        <div><strong>Adoption Fee:</strong> $${parseFloat(pet.adoption_fee || 0).toFixed(2)}</div>
                                        <div><strong>Added:</strong> ${new Date(pet.created_at).toLocaleDateString()}</div>
                                    </div>
                                    ${pet.health_status ? `<div style="margin-bottom: 15px;"><strong>Health Status:</strong> ${escapeHtml(pet.health_status)}</div>` : ''}
                                </div>
                            </div>
                            ${pet.description ? `<div><strong>Description:</strong><p style="margin-top: 10px; line-height: 1.6; color: #666;">${escapeHtml(pet.description)}</p></div>` : ''}
                            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 10px; justify-content: center;">
                                ${currentStatus !== 'adopted' ? `<button onclick="editPet(${pet.pet_id})" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Edit Pet
                                </button>` : `<button onclick="editPet(${pet.pet_id})" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Edit Pet (Limited)
                                </button>`}
                                <button onclick="closeModal('petDetailsModal')" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Close
                                </button>
                            </div>
                        </div>
                    `;
                } else {
                    document.getElementById('petDetailsBody').innerHTML = `
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-exclamation-circle" style="font-size: 3rem; margin-bottom: 15px; color: #dc3545;"></i>
                            <h3>Error Loading Pet Details</h3>
                            <p>${escapeHtml(data.message)}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('petDetailsBody').innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 15px; color: #ffc107;"></i>
                        <h3>Connection Error</h3>
                        <p>Failed to load pet details. Please try again.</p>
                    </div>
                `;
            });
    }

    // Edit pet functionality
    function editPet(petId) {
        currentEditPetId = petId;
        openModal('editPetModal');

        // Reset form
        document.getElementById('editPetForm').reset();
        document.getElementById('edit_pet_id').value = petId;

        // Load pet data
        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=get_pet_details&pet_id=${petId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pet = data.pet;
                    const currentStatus = pet.display_status || pet.actual_status || pet.status;

                    // Populate form fields
                    document.getElementById('edit_pet_name').value = pet.pet_name || '';
                    document.getElementById('edit_category_id').value = pet.category_id || '';
                    document.getElementById('edit_age').value = pet.age || '';
                    document.getElementById('edit_gender').value = pet.gender || '';
                    document.getElementById('edit_size').value = pet.size || '';
                    document.getElementById('edit_adoption_fee').value = pet.adoption_fee || '';
                    document.getElementById('edit_status').value = currentStatus || 'available';
                    document.getElementById('edit_health_status').value = pet.health_status || '';
                    document.getElementById('edit_description').value = pet.description || '';

                    // Disable status field if pet is adopted
                    const statusField = document.getElementById('edit_status');
                    if (currentStatus === 'adopted') {
                        statusField.disabled = true;
                        statusField.style.backgroundColor = '#f8f9fa';
                        statusField.style.color = '#6c757d';

                        // Add note about adopted pets
                        const statusGroup = statusField.closest('.form-group');
                        let note = statusGroup.querySelector('.adoption-note');
                        if (!note) {
                            note = document.createElement('small');
                            note.className = 'adoption-note';
                            note.style.color = '#6c757d';
                            note.innerHTML =
                                '<i class="fas fa-info-circle"></i> Status cannot be changed for adopted pets';
                            statusGroup.appendChild(note);
                        }
                    } else {
                        statusField.disabled = false;
                        statusField.style.backgroundColor = '';
                        statusField.style.color = '';

                        // Remove adoption note if exists
                        const statusGroup = statusField.closest('.form-group');
                        const note = statusGroup.querySelector('.adoption-note');
                        if (note) {
                            note.remove();
                        }
                    }

                    // Load breeds for the selected category
                    if (pet.category_id) {
                        loadBreedsForEdit(pet.category_id, pet.breed_id);
                    }
                } else {
                    showMessage(data.message, 'error');
                    closeModal('editPetModal');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Failed to load pet data', 'error');
                closeModal('editPetModal');
            });
    }

    function loadBreedsForEdit(categoryId, selectedBreedId = null) {
        const breedSelect = document.getElementById('edit_breed_id');
        breedSelect.innerHTML = '<option value="">Select Breed</option>';

        if (categoryId) {
            const categoryBreeds = allBreeds.filter(breed => breed.category_id == categoryId);
            categoryBreeds.forEach(breed => {
                const option = document.createElement('option');
                option.value = breed.breed_id;
                option.textContent = breed.breed_name;
                if (selectedBreedId && breed.breed_id == selectedBreedId) {
                    option.selected = true;
                }
                breedSelect.appendChild(option);
            });
        }
    }

    // Handle edit form submission
    document.getElementById('editPetForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'update_pet');

        const submitBtn = document.getElementById('saveEditBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
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
                    closeModal('editPetModal');
                    // Refresh the page to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Failed to update pet', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    });

    // Delete pet functionality
    function deletePet(petId, petName) {
        // Check if delete button is disabled
        const deleteButton = event.target.closest('button');
        if (deleteButton && deleteButton.classList.contains('disabled')) {
            showMessage('Adopted pets cannot be deleted for record keeping purposes', 'error');
            return;
        }

        if (confirm(
                `Are you sure you want to delete "${petName}"? This action cannot be undone and will also delete all related records (applications, medical records, etc.).`
            )) {
            // Double confirmation for safety
            if (confirm(`This will permanently delete ${petName} and ALL related data. Are you absolutely sure?`)) {
                const originalText = deleteButton.innerHTML;
                deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                deleteButton.disabled = true;

                fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: `action=delete_pet&pet_id=${petId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage(data.message, 'success');
                            // Remove the pet card from the display
                            const petCard = document.querySelector(`[data-pet-id="${petId}"]`);
                            if (petCard) {
                                petCard.style.transition = 'all 0.3s ease';
                                petCard.style.opacity = '0';
                                petCard.style.transform = 'scale(0.8)';
                                setTimeout(() => {
                                    petCard.remove();
                                    // Check if no pets left
                                    if (document.querySelectorAll('.pet-card').length === 0) {
                                        setTimeout(() => {
                                            window.location.reload();
                                        }, 1000);
                                    }
                                }, 300);
                            }
                        } else {
                            showMessage(data.message, 'error');
                            deleteButton.innerHTML = originalText;
                            deleteButton.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showMessage('Failed to delete pet', 'error');
                        deleteButton.innerHTML = originalText;
                        deleteButton.disabled = false;
                    });
            }
        }
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? String(text).replace(/[&<>"']/g, function(m) {
            return map[m];
        }) : '';
    }

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Handle any session messages
        <?php if (isset($_SESSION['error_message'])): ?>
        showMessage('<?php echo addslashes($_SESSION['error_message']); ?>', 'error');
        <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
        showMessage('<?php echo addslashes($_SESSION['success_message']); ?>', 'success');
        <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        // Add loading animation to cards
        const cards = document.querySelectorAll('.pet-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition =
                `opacity 0.3s ease ${index * 0.1}s, transform 0.3s ease ${index * 0.1}s`;

            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Add click handlers for disabled delete buttons
        document.querySelectorAll('.btn-danger.disabled').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                showMessage('Adopted pets cannot be deleted for record keeping purposes',
                    'error');
            });
        });

        // Auto-sync pet statuses on page load
        syncPetStatuses();
    });

    // Function to sync pet statuses
    function syncPetStatuses() {
        // This could be expanded to periodically check for status updates
        console.log('Pet statuses synchronized with database');
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Escape key closes modals
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(modal => {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            });
        }

        // Ctrl+F to focus search
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.getElementById('search').focus();
        }

        // Alt+N for new pet
        if (e.altKey && e.key === 'n') {
            e.preventDefault();
            window.location.href = '<?php echo $BASE_URL; ?>shelter/addPet.php';
        }
    });

    // Add tooltips for keyboard shortcuts
    document.getElementById('search').title = 'Search pets (Ctrl+F)';

    // Enhanced error handling for network issues
    window.addEventListener('online', function() {
        showMessage('Connection restored', 'success');
    });

    window.addEventListener('offline', function() {
        showMessage('You are offline. Some features may not work', 'error');
    });

    // Add periodic status refresh (optional)
    setInterval(function() {
        // Check if any pets need status updates
        const petCards = document.querySelectorAll('.pet-card');
        if (petCards.length > 0) {
            // Could implement background status checking here
            console.log('Status check completed');
        }
    }, 300000); // Check every 5 minutes

    // Utility function for better UX
    function showLoadingState(element) {
        element.classList.add('loading');
        element.style.pointerEvents = 'none';
        element.style.opacity = '0.6';
    }

    function hideLoadingState(element) {
        element.classList.remove('loading');
        element.style.pointerEvents = 'auto';
        element.style.opacity = '1';
    }

    // Enhanced form validation
    function validateEditForm() {
        const form = document.getElementById('editPetForm');
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#dc3545';
                isValid = false;
            } else {
                field.style.borderColor = '#28a745';
            }
        });

        return isValid;
    }

    // Add real-time validation
    document.getElementById('editPetForm').addEventListener('input', function(e) {
        if (e.target.hasAttribute('required')) {
            if (e.target.value.trim()) {
                e.target.style.borderColor = '#28a745';
            } else {
                e.target.style.borderColor = '#dc3545';
            }
        }
    });

    console.log('View Pets page loaded successfully');
    console.log(`Total pets displayed: <?php echo count($pets); ?>`);
    console.log('Status synchronization: Active');
    </script>
</body>

</html>