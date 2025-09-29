<?php
// adopter/dashboard.php - Adopter Dashboard (Fixed and Optimized)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/');
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('DEFAULT_PET_IMAGE', BASE_URL . 'assets/images/default-pet.jpg');

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'adopter') {
    $_SESSION['error_message'] = 'Please login as an adopter to access this page.';
    header('Location: ' . BASE_URL . 'auth/login.php');
    exit();
}

// User session data
$adopter_user_id = (int)$_SESSION['user_id'];
$adopter_name = $_SESSION['first_name'] ?? 'User';
$page_title = 'Adopter Dashboard - Pet Adoption Care Guide';

// Initialize variables
$adopter_info = [];
$adoption_stats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'total_applications' => 0
];
$completed_adoptions = 0;
$recent_applications = [];
$recommended_pets = [];
$adopted_pets = [];
$care_guides_count = 0;
$upcoming_reminders = [];
$error_message = '';
$success_message = '';

// Helper Functions
function getPetImageUrl($imagePath, $baseUrl, $uploadPath) {
    if (empty($imagePath)) {
        return null;
    }
    
    $filename = basename($imagePath);
    
    // Check different possible locations
    $possiblePaths = [
        $uploadPath . 'pets/' . $filename,
        $uploadPath . $filename,
        $uploadPath . 'pets/' . $imagePath,
        $uploadPath . $imagePath
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_file($path)) {
            if (strpos($path, '/pets/') !== false) {
                return $baseUrl . 'uploads/pets/' . $filename;
            }
            return $baseUrl . 'uploads/' . $filename;
        }
    }
    
    return null;
}

function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('M j, Y', strtotime($date));
}

function formatTimeAgo($days) {
    if ($days == 0) return 'Today';
    if ($days == 1) return 'Yesterday';
    if ($days < 7) return $days . ' days ago';
    if ($days < 30) return floor($days / 7) . ' weeks ago';
    if ($days < 365) return floor($days / 30) . ' months ago';
    return floor($days / 365) . ' years ago';
}

// Database operations
try {
    require_once BASE_PATH . '/config/db.php';
    $db = getDB();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Start transaction for consistent data
    $db->beginTransaction();
    
    // 1. Get adopter's information
    $stmt = $db->prepare("
        SELECT 
            user_id, 
            username, 
            email, 
            first_name, 
            last_name, 
            phone, 
            address, 
            user_type, 
            created_at,
            CONCAT(first_name, ' ', last_name) as full_name 
        FROM users 
        WHERE user_id = :user_id 
            AND user_type = 'adopter' 
            AND is_active = TRUE
    ");
    $stmt->execute(['user_id' => $adopter_user_id]);
    $adopter_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$adopter_info) {
        throw new Exception("User information not found");
    }
    
    // 2. Get adoption application statistics
    $stmt = $db->prepare("
        SELECT 
            application_status,
            COUNT(*) as count
        FROM adoption_applications 
        WHERE adopter_id = :adopter_id
        GROUP BY application_status
    ");
    $stmt->execute(['adopter_id' => $adopter_user_id]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = strtolower($row['application_status']);
        if (array_key_exists($status, $adoption_stats)) {
            $adoption_stats[$status] = (int)$row['count'];
            $adoption_stats['total_applications'] += (int)$row['count'];
        }
    }
    
    // 3. Get completed adoptions count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM adoptions 
        WHERE adopter_id = :adopter_id
    ");
    $stmt->execute(['adopter_id' => $adopter_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $completed_adoptions = (int)($result['count'] ?? 0);
    
    // 4. Get recent adoption applications with pet details
    $stmt = $db->prepare("
        SELECT 
            aa.application_id,
            aa.pet_id,
            aa.shelter_id,
            aa.application_status,
            aa.application_date,
            aa.housing_type,
            aa.reason_for_adoption,
            p.pet_name,
            p.age,
            p.gender,
            p.size,
            p.adoption_fee,
            p.primary_image,
            p.status as pet_status,
            pc.category_name,
            pb.breed_name,
            s.shelter_name,
            DATEDIFF(CURDATE(), aa.application_date) as days_ago
        FROM adoption_applications aa
        INNER JOIN pets p ON aa.pet_id = p.pet_id
        INNER JOIN shelters s ON aa.shelter_id = s.shelter_id
        INNER JOIN pet_categories pc ON p.category_id = pc.category_id
        LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
        WHERE aa.adopter_id = :adopter_id
        ORDER BY aa.application_date DESC
        LIMIT 5
    ");
    $stmt->execute(['adopter_id' => $adopter_user_id]);
    $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process image paths for recent applications
    foreach ($recent_applications as &$app) {
        $app['image_url'] = getPetImageUrl($app['primary_image'], BASE_URL, UPLOAD_PATH);
        $app['formatted_date'] = formatTimeAgo($app['days_ago'] ?? 0);
    }
    unset($app);
    
    // 5. Get recommended pets (available pets not yet applied for)
    $stmt = $db->prepare("
        SELECT 
            p.pet_id,
            p.pet_name,
            p.age,
            p.gender,
            p.size,
            p.description,
            p.adoption_fee,
            p.primary_image,
            pc.category_name,
            pb.breed_name,
            s.shelter_name,
            (SELECT COUNT(*) FROM adoption_applications WHERE pet_id = p.pet_id) as total_applications
        FROM pets p
        INNER JOIN shelters s ON p.shelter_id = s.shelter_id
        INNER JOIN pet_categories pc ON p.category_id = pc.category_id
        LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
        WHERE p.status = 'available'
            AND p.pet_id NOT IN (
                SELECT pet_id 
                FROM adoption_applications 
                WHERE adopter_id = :adopter_id
            )
        ORDER BY p.created_at DESC
        LIMIT 6
    ");
    $stmt->execute(['adopter_id' => $adopter_user_id]);
    $recommended_pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process image paths for recommended pets
    foreach ($recommended_pets as &$pet) {
        $pet['image_url'] = getPetImageUrl($pet['primary_image'], BASE_URL, UPLOAD_PATH);
    }
    unset($pet);
    
    // 6. Get adopted pets with details
    $stmt = $db->prepare("
        SELECT 
            a.adoption_id,
            a.adoption_date,
            a.adoption_fee_paid,
            a.pet_id,
            p.pet_name,
            p.age,
            p.gender,
            p.size,
            p.primary_image,
            pc.category_name,
            pb.breed_name,
            s.shelter_name,
            DATEDIFF(CURDATE(), a.adoption_date) as days_since_adoption
        FROM adoptions a
        INNER JOIN pets p ON a.pet_id = p.pet_id
        INNER JOIN shelters s ON a.shelter_id = s.shelter_id
        INNER JOIN pet_categories pc ON p.category_id = pc.category_id
        LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
        WHERE a.adopter_id = :adopter_id
        ORDER BY a.adoption_date DESC
        LIMIT 3
    ");
    $stmt->execute(['adopter_id' => $adopter_user_id]);
    $adopted_pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process image paths and generate reminders for adopted pets
    foreach ($adopted_pets as &$pet) {
        $pet['image_url'] = getPetImageUrl($pet['primary_image'], BASE_URL, UPLOAD_PATH);
        $days_since = (int)($pet['days_since_adoption'] ?? 0);
        
        // Generate reminders based on adoption timeline
        if ($days_since > 0) {
            // Monthly checkup reminder
            if ($days_since % 30 <= 7 && $days_since >= 30) {
                $upcoming_reminders[] = [
                    'type' => 'checkup',
                    'message' => "Monthly checkup reminder for {$pet['pet_name']}",
                    'pet_name' => $pet['pet_name'],
                    'priority' => 'medium'
                ];
            }
            
            // Vaccination reminder (every 365 days)
            if ($days_since % 365 <= 30 && $days_since >= 365) {
                $upcoming_reminders[] = [
                    'type' => 'vaccination',
                    'message' => "Annual vaccination due for {$pet['pet_name']}",
                    'pet_name' => $pet['pet_name'],
                    'priority' => 'high'
                ];
            }
        }
    }
    unset($pet);
    
    // 7. Get care guides count
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM care_guides 
        WHERE is_published = TRUE
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $care_guides_count = (int)($result['count'] ?? 0);
    
    // Commit transaction
    $db->commit();
    
    // Set success message if coming from another page
    if (isset($_SESSION['dashboard_message'])) {
        $success_message = $_SESSION['dashboard_message'];
        unset($_SESSION['dashboard_message']);
    }
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Database error in adopter dashboard: " . $e->getMessage());
    $error_message = "Unable to load dashboard data. Please try again later.";
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error in adopter dashboard: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
    :root {
        --primary-color: #667eea;
        --secondary-color: #764ba2;
        --success-color: #28a745;
        --warning-color: #ffa726;
        --danger-color: #ef5350;
        --info-color: #42a5f5;
        --dark-color: #333;
        --light-color: #f8f9fa;
        --border-radius: 12px;
        --transition: all 0.3s ease;
        --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
        --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.12);
        --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.16);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: #f5f7fa;
        min-height: 100vh;
        color: var(--dark-color);
        line-height: 1.6;
    }

    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Alert Messages */
    .alert {
        padding: 15px 20px;
        border-radius: var(--border-radius);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        border-left: 4px solid;
        animation: slideIn 0.3s ease;
    }

    .alert-error {
        background: #fee;
        color: #c33;
        border-left-color: #c33;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border-left-color: #28a745;
    }

    @keyframes slideIn {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Welcome Section */
    .welcome-section {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 40px;
        border-radius: var(--border-radius);
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .welcome-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .welcome-text h1 {
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .welcome-text p {
        font-size: 1.1rem;
        opacity: 0.9;
    }

    .user-info-box {
        background: rgba(255, 255, 255, 0.15);
        padding: 20px 30px;
        border-radius: var(--border-radius);
        backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        gap: 15px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .user-avatar {
        width: 50px;
        height: 50px;
        background: white;
        color: var(--primary-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    /* Statistics Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 24px;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        border-top: 4px solid transparent;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .stat-card.pending {
        border-top-color: var(--warning-color);
    }

    .stat-card.approved {
        border-top-color: var(--success-color);
    }

    .stat-card.rejected {
        border-top-color: var(--danger-color);
    }

    .stat-card.adopted {
        border-top-color: var(--info-color);
    }

    .stat-card.guides {
        border-top-color: var(--secondary-color);
    }

    .stat-card-content {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .stat-icon {
        font-size: 2.5rem;
        opacity: 0.8;
    }

    .stat-card.pending .stat-icon {
        color: var(--warning-color);
    }

    .stat-card.approved .stat-icon {
        color: var(--success-color);
    }

    .stat-card.rejected .stat-icon {
        color: var(--danger-color);
    }

    .stat-card.adopted .stat-icon {
        color: var(--info-color);
    }

    .stat-card.guides .stat-icon {
        color: var(--secondary-color);
    }

    .stat-info {
        flex: 1;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 4px;
        color: var(--dark-color);
    }

    .stat-label {
        font-size: 0.9rem;
        color: #666;
    }

    /* Main Layout */
    .dashboard-main {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 30px;
        margin-bottom: 30px;
    }

    /* Content Cards */
    .content-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }

    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafbfc;
    }

    .card-header h3 {
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--dark-color);
    }

    .card-body {
        padding: 24px;
    }

    /* Application List */
    .application-item {
        display: flex;
        gap: 16px;
        padding: 16px;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        margin-bottom: 12px;
        transition: var(--transition);
        cursor: pointer;
        background: white;
    }

    .application-item:hover {
        border-color: var(--primary-color);
        background: #f8f9fa;
        box-shadow: var(--shadow-sm);
    }

    .pet-thumbnail {
        width: 60px;
        height: 60px;
        border-radius: 8px;
        overflow: hidden;
        flex-shrink: 0;
        background: #f0f2f5;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pet-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .pet-thumbnail .no-image {
        color: #a0a5b8;
        font-size: 1.5rem;
    }

    .application-details {
        flex: 1;
        min-width: 0;
    }

    .pet-name {
        font-weight: 600;
        margin-bottom: 4px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: var(--dark-color);
    }

    .pet-meta {
        font-size: 0.85rem;
        color: #666;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        align-self: center;
        text-transform: capitalize;
    }

    .status-badge.pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-badge.approved {
        background: #d4edda;
        color: #155724;
    }

    .status-badge.rejected {
        background: #f8d7da;
        color: #721c24;
    }

    /* Pet Cards */
    .pet-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 24px;
    }

    .pet-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        transition: var(--transition);
        cursor: pointer;
        position: relative;
    }

    .pet-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .pet-image {
        width: 100%;
        height: 200px;
        background: #f0f2f5;
        position: relative;
        overflow: hidden;
    }

    .pet-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .pet-card:hover .pet-image img {
        transform: scale(1.05);
    }

    .pet-image .no-image {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #e3e7f3, #f0f2f5);
        color: #a0a5b8;
        font-size: 3rem;
    }

    .pet-card-body {
        padding: 20px;
    }

    .pet-card-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--dark-color);
    }

    .pet-card-info {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 16px;
        line-height: 1.5;
    }

    .pet-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 16px;
        border-top: 1px solid #e9ecef;
    }

    .pet-price {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--success-color);
    }

    /* Adopted Pets */
    .adopted-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: var(--success-color);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 4px;
        box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
    }

    /* Quick Actions */
    .quick-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .quick-action-link {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        text-decoration: none;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .quick-action-link:hover {
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    /* Reminder Items */
    .reminder-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: #fff8dc;
        border-radius: 6px;
        margin-bottom: 8px;
        font-size: 0.9rem;
        border-left: 3px solid;
    }

    .reminder-item.high {
        border-left-color: var(--danger-color);
        background: #fee;
    }

    .reminder-item.medium {
        border-left-color: var(--warning-color);
    }

    .reminder-item i {
        color: var(--warning-color);
    }

    .reminder-item.high i {
        color: var(--danger-color);
    }

    /* Buttons */
    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-primary {
        background: var(--primary-color);
        color: white;
    }

    .btn-primary:hover {
        background: #5a67d8;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }

    .btn-outline {
        background: white;
        color: var(--primary-color);
        border: 1px solid var(--primary-color);
    }

    .btn-outline:hover {
        background: var(--primary-color);
        color: white;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.85rem;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #666;
    }

    .empty-icon {
        font-size: 3rem;
        margin-bottom: 16px;
        opacity: 0.3;
        color: var(--primary-color);
    }

    .empty-state h4 {
        font-size: 1.2rem;
        margin-bottom: 8px;
        color: var(--dark-color);
    }

    /* Loading Spinner */
    .loading-spinner {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 9999;
    }

    .spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f3f3;
        border-top: 5px solid var(--primary-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .dashboard-main {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .welcome-header {
            flex-direction: column;
            text-align: center;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .pet-grid {
            grid-template-columns: 1fr;
        }

        .dashboard-container {
            padding: 10px;
        }

        .welcome-section {
            padding: 30px 20px;
        }

        .welcome-text h1 {
            font-size: 1.8rem;
        }

        .user-info-box {
            padding: 15px 20px;
        }

        .dashboard-main {
            gap: 20px;
        }
    }
    </style>
</head>

<body>
    <?php 
    $navbar_path = BASE_PATH . '/common/navbar_adopter.php';
    if (file_exists($navbar_path)) {
        include_once $navbar_path;
    }
    ?>

    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
    </div>

    <div class="dashboard-container">
        <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success_message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-header">
                <div class="welcome-text">
                    <h1>Welcome back, <?php echo htmlspecialchars($adopter_name); ?>!</h1>
                    <p>Find your perfect companion and give them a loving home</p>
                </div>
                <div class="user-info-box">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($adopter_name, 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;">
                            <?php echo htmlspecialchars($adopter_info['full_name'] ?? $adopter_name); ?>
                        </div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">
                            <?php echo htmlspecialchars($adopter_info['email'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card pending"
                onclick="location.href='<?php echo BASE_URL; ?>adopter/myAdoptions.php?filter=pending'">
                <div class="stat-card-content">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number" data-count="<?php echo $adoption_stats['pending']; ?>">0</div>
                        <div class="stat-label">Pending Applications</div>
                    </div>
                </div>
            </div>

            <div class="stat-card approved"
                onclick="location.href='<?php echo BASE_URL; ?>adopter/myAdoptions.php?filter=approved'">
                <div class="stat-card-content">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number" data-count="<?php echo $adoption_stats['approved']; ?>">0</div>
                        <div class="stat-label">Approved Applications</div>
                    </div>
                </div>
            </div>

            <div class="stat-card adopted"
                onclick="location.href='<?php echo BASE_URL; ?>adopter/myAdoptions.php?filter=adopted'">
                <div class="stat-card-content">
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number" data-count="<?php echo $completed_adoptions; ?>">0</div>
                        <div class="stat-label">Adopted Pets</div>
                    </div>
                </div>
            </div>

            <div class="stat-card guides" onclick="location.href='<?php echo BASE_URL; ?>adopter/careGuides.php'">
                <div class="stat-card-content">
                    <div class="stat-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number" data-count="<?php echo $care_guides_count; ?>">0</div>
                        <div class="stat-label">Care Guides</div>
                    </div>
                </div>
            </div>

            <?php if ($adoption_stats['rejected'] > 0): ?>
            <div class="stat-card rejected"
                onclick="location.href='<?php echo BASE_URL; ?>adopter/myAdoptions.php?filter=rejected'">
                <div class="stat-card-content">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number" data-count="<?php echo $adoption_stats['rejected']; ?>">0</div>
                        <div class="stat-label">Rejected Applications</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Main Dashboard Content -->
        <div class="dashboard-main">
            <!-- Recent Applications -->
            <div class="content-card">
                <div class="card-header">
                    <h3><i class="fas fa-file-alt"></i> Recent Applications</h3>
                    <?php if (!empty($recent_applications)): ?>
                    <a href="<?php echo BASE_URL; ?>adopter/myAdoptions.php" class="btn btn-outline btn-sm">
                        View All
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_applications)): ?>
                    <?php foreach ($recent_applications as $app): ?>
                    <div class="application-item"
                        onclick="location.href='<?php echo BASE_URL; ?>adopter/petDetails.php?id=<?php echo $app['pet_id']; ?>'">
                        <div class="pet-thumbnail">
                            <?php if (!empty($app['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($app['image_url']); ?>"
                                alt="<?php echo htmlspecialchars($app['pet_name']); ?>"
                                onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'no-image\'><i class=\'fas fa-paw\'></i></div>';">
                            <?php else: ?>
                            <div class="no-image">
                                <i class="fas fa-paw"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="application-details">
                            <div class="pet-name"><?php echo htmlspecialchars($app['pet_name']); ?></div>
                            <div class="pet-meta">
                                <i class="fas fa-home"></i> <?php echo htmlspecialchars($app['shelter_name']); ?> •
                                <i class="fas fa-calendar"></i> <?php echo $app['formatted_date']; ?>
                            </div>
                        </div>
                        <div class="status-badge <?php echo $app['application_status']; ?>">
                            <?php echo ucfirst($app['application_status']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt empty-icon"></i>
                        <h4>No Applications Yet</h4>
                        <p>Start browsing pets to find your perfect companion!</p>
                        <a href="<?php echo BASE_URL; ?>adopter/browsePets.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-search"></i> Browse Pets
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Quick Actions -->
                <div class="content-card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="<?php echo BASE_URL; ?>adopter/browsePets.php" class="quick-action-link">
                                <i class="fas fa-search"></i>
                                <span>Browse Available Pets</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>adopter/myAdoptions.php" class="quick-action-link">
                                <i class="fas fa-heart"></i>
                                <span>My Adoptions</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>adopter/careGuides.php" class="quick-action-link">
                                <i class="fas fa-book"></i>
                                <span>Pet Care Guides</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Reminders -->
                <?php if (!empty($upcoming_reminders)): ?>
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bell"></i> Reminders</h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($upcoming_reminders as $reminder): ?>
                        <div class="reminder-item <?php echo $reminder['priority'] ?? 'medium'; ?>">
                            <i
                                class="fas fa-<?php echo $reminder['type'] == 'vaccination' ? 'syringe' : 'stethoscope'; ?>"></i>
                            <?php echo htmlspecialchars($reminder['message']); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recommended Pets -->
        <?php if (!empty($recommended_pets)): ?>
        <div class="content-card" style="margin-bottom: 30px;">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Recommended for You</h3>
                <a href="<?php echo BASE_URL; ?>adopter/browsePets.php" class="btn btn-outline btn-sm">Browse All</a>
            </div>
            <div class="card-body">
                <div class="pet-grid">
                    <?php foreach (array_slice($recommended_pets, 0, 3) as $pet): ?>
                    <div class="pet-card"
                        onclick="location.href='<?php echo BASE_URL; ?>adopter/petDetails.php?id=<?php echo $pet['pet_id']; ?>'">
                        <div class="pet-image">
                            <?php if (!empty($pet['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($pet['image_url']); ?>"
                                alt="<?php echo htmlspecialchars($pet['pet_name']); ?>"
                                onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'no-image\'><i class=\'fas fa-paw\'></i></div>';">
                            <?php else: ?>
                            <div class="no-image">
                                <i class="fas fa-paw"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="pet-card-body">
                            <h4 class="pet-card-title"><?php echo htmlspecialchars($pet['pet_name']); ?></h4>
                            <div class="pet-card-info">
                                <?php echo htmlspecialchars($pet['category_name']); ?>
                                <?php if (!empty($pet['breed_name'])): ?>
                                • <?php echo htmlspecialchars($pet['breed_name']); ?>
                                <?php endif; ?><br>
                                <?php echo $pet['age']; ?> year<?php echo $pet['age'] != 1 ? 's' : ''; ?> •
                                <?php echo ucfirst($pet['gender']); ?>
                                <?php if (!empty($pet['size'])): ?>
                                • <?php echo ucfirst($pet['size']); ?>
                                <?php endif; ?><br>
                                <small><i class="fas fa-home"></i>
                                    <?php echo htmlspecialchars($pet['shelter_name']); ?></small>
                                <?php if ($pet['total_applications'] > 0): ?>
                                <br><small style="color: #ff9800;">
                                    <i class="fas fa-users"></i> <?php echo $pet['total_applications']; ?>
                                    application<?php echo $pet['total_applications'] > 1 ? 's' : ''; ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <div class="pet-card-footer">
                                <span
                                    class="pet-price">$<?php echo number_format((float)$pet['adoption_fee'], 2); ?></span>
                                <button class="btn btn-primary btn-sm">View Details</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Adopted Pets -->
        <?php if (!empty($adopted_pets)): ?>
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-heart"></i> Your Adopted Pets</h3>
            </div>
            <div class="card-body">
                <div class="pet-grid">
                    <?php foreach ($adopted_pets as $pet): ?>
                    <div class="pet-card adopted-pet-card">
                        <div class="adopted-badge">
                            <i class="fas fa-check"></i> Adopted
                        </div>
                        <div class="pet-image">
                            <?php if (!empty($pet['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($pet['image_url']); ?>"
                                alt="<?php echo htmlspecialchars($pet['pet_name']); ?>"
                                onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'no-image\'><i class=\'fas fa-paw\'></i></div>';">
                            <?php else: ?>
                            <div class="no-image">
                                <i class="fas fa-paw"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="pet-card-body">
                            <h4 class="pet-card-title">
                                <?php echo htmlspecialchars($pet['pet_name']); ?>
                                <i class="fas fa-heart" style="color: #e74c3c; font-size: 1rem; margin-left: 8px;"></i>
                            </h4>
                            <div class="pet-card-info">
                                <div style="margin-bottom: 8px;">
                                    <i class="fas fa-calendar"
                                        style="color: var(--primary-color); margin-right: 6px;"></i>
                                    Adopted on <?php echo formatDate($pet['adoption_date']); ?>
                                </div>
                                <div style="margin-bottom: 8px;">
                                    <i class="fas fa-tag" style="color: var(--primary-color); margin-right: 6px;"></i>
                                    <?php echo htmlspecialchars($pet['category_name']); ?>
                                    <?php if (!empty($pet['breed_name'])): ?>
                                    • <?php echo htmlspecialchars($pet['breed_name']); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <i class="fas fa-home" style="color: var(--primary-color); margin-right: 6px;"></i>
                                    From <?php echo htmlspecialchars($pet['shelter_name']); ?>
                                </div>
                            </div>
                            <div class="pet-card-footer">
                                <span style="color: #666; font-size: 0.9rem;">
                                    <i class="fas fa-clock"></i>
                                    <?php 
                                    $days = isset($pet['days_since_adoption']) ? (int)$pet['days_since_adoption'] : 0;
                                    echo $days . ' day' . ($days != 1 ? 's' : '') . ' together';
                                    ?>
                                </span>
                                <a href="<?php echo BASE_URL; ?>adopter/careGuides.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-book"></i> Care Guide
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Empty State for New Users -->
        <?php if (empty($recent_applications) && empty($adopted_pets) && $adoption_stats['total_applications'] == 0): ?>
        <div class="content-card">
            <div class="card-body" style="padding: 80px 40px; text-align: center;">
                <div class="empty-state">
                    <i class="fas fa-paw empty-icon" style="color: var(--primary-color); font-size: 5rem;"></i>
                    <h2 style="font-size: 2rem; margin-bottom: 15px; color: var(--dark-color);">Welcome to Pet Adoption!
                    </h2>
                    <p
                        style="font-size: 1.1rem; margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto; color: #666;">
                        You haven't started your adoption journey yet. Browse through hundreds of loving pets waiting
                        for their forever homes!
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <a href="<?php echo BASE_URL; ?>adopter/browsePets.php" class="btn btn-primary"
                            style="padding: 12px 24px; font-size: 1rem;">
                            <i class="fas fa-search"></i> Start Browsing Pets
                        </a>
                        <a href="<?php echo BASE_URL; ?>adopter/careGuides.php" class="btn btn-outline"
                            style="padding: 12px 24px; font-size: 1rem;">
                            <i class="fas fa-book"></i> Learn About Pet Care
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Success/Error Messages (Flash Messages) -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div id="successMessage"
        style="position: fixed; top: 20px; right: 20px; background: var(--success-color); color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 1000; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message']) && empty($error_message)): ?>
    <div id="errorMessage"
        style="position: fixed; top: 20px; right: 20px; background: var(--danger-color); color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 1000; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show loading spinner for ajax requests
        function showLoader() {
            document.getElementById('loadingSpinner').style.display = 'block';
        }

        function hideLoader() {
            document.getElementById('loadingSpinner').style.display = 'none';
        }

        // Auto-hide flash messages after 5 seconds
        const flashMessages = document.querySelectorAll('#successMessage, #errorMessage, .alert');
        flashMessages.forEach(message => {
            if (message) {
                setTimeout(() => {
                    message.style.transition = 'opacity 0.5s ease';
                    message.style.opacity = '0';
                    setTimeout(() => message.remove(), 500);
                }, 5000);
            }
        });

        // Animate statistics counters
        function animateValue(element, start, end, duration) {
            if (!element) return;

            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const value = Math.floor(progress * (end - start) + start);
                element.textContent = value;

                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        // Animate all stat numbers
        const statNumbers = document.querySelectorAll('.stat-number[data-count]');
        statNumbers.forEach(stat => {
            const endValue = parseInt(stat.getAttribute('data-count')) || 0;
            animateValue(stat, 0, endValue, 1000);
        });

        // Add hover effects with throttling
        let hoverTimeout;
        const interactiveElements = document.querySelectorAll(
            '.pet-card, .stat-card, .application-item, .quick-action-link');

        interactiveElements.forEach(element => {
            element.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                hoverTimeout = setTimeout(() => {
                    this.style.transition = 'all 0.3s ease';
                }, 50);
            });
        });

        // Lazy load images
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    img.style.opacity = '1';
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px'
        });

        // Observe all images
        document.querySelectorAll('img').forEach(img => {
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.3s ease';
            imageObserver.observe(img);
        });

        // Keyboard shortcuts
        const shortcuts = {
            'b': '<?php echo BASE_URL; ?>adopter/browsePets.php',
            'm': '<?php echo BASE_URL; ?>adopter/myAdoptions.php',
            'c': '<?php echo BASE_URL; ?>adopter/careGuides.php',
            'p': '<?php echo BASE_URL; ?>adopter/profile.php',
            'h': '<?php echo BASE_URL; ?>adopter/dashboard.php'
        };

        document.addEventListener('keydown', function(e) {
            // Don't trigger shortcuts when typing in inputs
            if (e.target.matches('input, textarea, select')) return;

            const key = e.key.toLowerCase();

            if (shortcuts[key]) {
                e.preventDefault();
                window.location.href = shortcuts[key];
            } else if (key === '?') {
                e.preventDefault();
                showKeyboardHelp();
            }
        });

        function showKeyboardHelp() {
            const helpText = `Keyboard Shortcuts:
            
B - Browse Pets
M - My Adoptions  
C - Care Guides
P - Profile
H - Home (Dashboard)
? - Show this help`;

            alert(helpText);
        }

        // Online/offline status
        let isOnline = navigator.onLine;

        window.addEventListener('online', () => {
            if (!isOnline) {
                isOnline = true;
                showNotification('Connection restored', 'success');
                // Refresh data if needed
                setTimeout(() => location.reload(), 1000);
            }
        });

        window.addEventListener('offline', () => {
            isOnline = false;
            showNotification('You are offline. Some features may be limited.', 'warning');
        });

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const bgColor = {
                'success': 'var(--success-color)',
                'error': 'var(--danger-color)',
                'warning': 'var(--warning-color)',
                'info': 'var(--info-color)'
            } [type] || 'var(--info-color)';

            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${bgColor};
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 1000;
                display: flex;
                align-items: center;
                gap: 10px;
                animation: slideIn 0.3s ease;
                max-width: 350px;
            `;

            const iconMap = {
                'success': 'check',
                'error': 'exclamation',
                'warning': 'exclamation-triangle',
                'info': 'info'
            };

            notification.innerHTML = `
                <i class="fas fa-${iconMap[type] || 'info'}-circle"></i>
                <span>${message}</span>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }

        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);

                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Log dashboard load time for performance monitoring
        window.addEventListener('load', () => {
            const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
            console.log(`Dashboard loaded in ${loadTime}ms`);

            // Send to analytics if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'timing_complete', {
                    'name': 'dashboard_load',
                    'value': loadTime
                });
            }
        });

        // Auto-refresh reminders every 5 minutes if page is visible
        let refreshInterval = setInterval(() => {
            if (document.visibilityState === 'visible' && navigator.onLine) {
                console.log('Checking for new reminders...');
                // You can add AJAX call here to refresh reminders without page reload
            }
        }, 300000); // 5 minutes

        // Clean up interval when page is hidden
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearInterval(refreshInterval);
            } else {
                refreshInterval = setInterval(() => {
                    if (document.visibilityState === 'visible' && navigator.onLine) {
                        console.log('Checking for new reminders...');
                    }
                }, 300000);
            }
        });

        // Debug mode (only in development)
        <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
        console.group('🐾 Dashboard Debug Information');
        console.log('User ID:', <?php echo json_encode($adopter_user_id); ?>);
        console.log('User Info:', <?php echo json_encode($adopter_info); ?>);
        console.log('Adoption Statistics:', <?php echo json_encode($adoption_stats); ?>);
        console.log('Recent Applications:', <?php echo json_encode(count($recent_applications)); ?>);
        console.log('Recommended Pets:', <?php echo json_encode(count($recommended_pets)); ?>);
        console.log('Adopted Pets:', <?php echo json_encode(count($adopted_pets)); ?>);
        console.log('Upcoming Reminders:', <?php echo json_encode(count($upcoming_reminders)); ?>);
        console.groupEnd();
        <?php endif; ?>

        // Initialize tooltips if Bootstrap is available
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(
                tooltipTriggerEl));
        }

        console.log('✅ Dashboard initialized successfully');
    });

    // Prevent accidental navigation away from unsaved changes
    let hasUnsavedChanges = false;

    window.addEventListener('beforeunload', (e) => {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    </script>
</body>

</html>