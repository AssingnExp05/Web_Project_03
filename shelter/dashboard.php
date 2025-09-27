<?php
// shelter/dashboard.php - Shelter Dashboard Page (Fixed Version)
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
$user_first_name = $_SESSION['first_name'] ?? 'Shelter User';
$page_title = 'Shelter Dashboard - Pet Adoption Care Guide';

// Initialize variables
$shelter_info = [
    'shelter_name' => 'Your Shelter',
    'shelter_id' => 0,
    'capacity' => 0,
    'license_number' => '',
    'first_name' => '',
    'last_name' => ''
];

$stats = [
    'total_pets' => 0,
    'available_pets' => 0,
    'pending_pets' => 0,
    'adopted_pets' => 0,
    'pending_requests' => 0,
    'total_applications' => 0,
    'approved_applications' => 0,
    'recent_adoptions' => 0
];

$recent_pets = [];
$recent_applications = [];
$recent_adoptions = [];
$db_connected = false;
$error_message = '';

// Database operations
try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if ($db) {
        $db_connected = true;
        
        // Get shelter information with user details
        $stmt = $db->prepare("
            SELECT s.*, u.first_name, u.last_name, u.email, u.phone 
            FROM shelters s 
            JOIN users u ON s.user_id = u.user_id 
            WHERE s.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $shelter_result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($shelter_result) {
            $shelter_info = $shelter_result;
        } else {
            // Create shelter record if it doesn't exist
            $stmt = $db->prepare("
                INSERT INTO shelters (user_id, shelter_name, capacity) 
                SELECT ?, CONCAT(first_name, ' ', last_name, ' Shelter'), 50 
                FROM users WHERE user_id = ?
            ");
            if ($stmt->execute([$user_id, $user_id])) {
                // Get the newly created shelter info
                $stmt = $db->prepare("
                    SELECT s.*, u.first_name, u.last_name, u.email, u.phone 
                    FROM shelters s 
                    JOIN users u ON s.user_id = u.user_id 
                    WHERE s.user_id = ?
                ");
                $stmt->execute([$user_id]);
                $shelter_info = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        
        $shelter_id = $shelter_info['shelter_id'];
        
        if ($shelter_id > 0) {
            // Get pet statistics
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ?");
            $stmt->execute([$shelter_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_pets'] = $result ? (int)$result['count'] : 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'available'");
            $stmt->execute([$shelter_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['available_pets'] = $result ? (int)$result['count'] : 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'pending'");
            $stmt->execute([$shelter_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['pending_pets'] = $result ? (int)$result['count'] : 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'adopted'");
            $stmt->execute([$shelter_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['adopted_pets'] = $result ? (int)$result['count'] : 0;
            
            // Get adoption application statistics
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE shelter_id = ?");
            $stmt->execute([$shelter_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_applications'] = $result ? (int)$result['count'] : 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE shelter_id = ? AND application_status = 'pending'");
            $stmt->execute([$shelter_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['pending_requests'] = $result ? (int)$result['count'] : 0;
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE shelter_id = ? AND application_status = 'approved'");
            $stmt->execute([$shelter_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['approved_applications'] = $result ? (int)$result['count'] : 0;
            
            // Get recent adoptions count (last 30 days)
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoptions WHERE shelter_id = ? AND adoption_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute([$shelter_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['recent_adoptions'] = $result ? (int)$result['count'] : 0;
            
            // Get recent pets with category information
            $stmt = $db->prepare("
                SELECT p.pet_id, p.pet_name, p.status, p.age, p.gender, p.created_at, p.primary_image,
                       pc.category_name, pb.breed_name
                FROM pets p 
                LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
                LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
                WHERE p.shelter_id = ? 
                ORDER BY p.created_at DESC 
                LIMIT 6
            ");
            $stmt->execute([$shelter_id]);
            $recent_pets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            // Get recent adoption applications with user and pet details
            $stmt = $db->prepare("
                SELECT aa.application_id, aa.application_status, aa.application_date,
                       p.pet_name, p.pet_id,
                       u.first_name, u.last_name, u.email
                FROM adoption_applications aa
                JOIN pets p ON aa.pet_id = p.pet_id
                JOIN users u ON aa.adopter_id = u.user_id
                WHERE aa.shelter_id = ?
                ORDER BY aa.application_date DESC
                LIMIT 5
            ");
            $stmt->execute([$shelter_id]);
            $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            // Get recent successful adoptions
            $stmt = $db->prepare("
                SELECT a.adoption_id, a.adoption_date, 
                       p.pet_name, p.pet_id,
                       u.first_name, u.last_name
                FROM adoptions a
                JOIN pets p ON a.pet_id = p.pet_id
                JOIN users u ON a.adopter_id = u.user_id
                WHERE a.shelter_id = ?
                ORDER BY a.adoption_date DESC
                LIMIT 5
            ");
            $stmt->execute([$shelter_id]);
            $recent_adoptions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
} catch (Exception $e) {
    error_log("Dashboard database error: " . $e->getMessage());
    $error_message = "Database connection issue. Some data may not be available.";
    $db_connected = false;
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
    .header {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        border-radius: 20px;
        padding: 40px;
        margin-bottom: 30px;
        text-align: center;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
    }

    .header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {

        0%,
        100% {
            transform: translateY(0px) rotate(0deg);
        }

        50% {
            transform: translateY(-20px) rotate(10deg);
        }
    }

    .header h1 {
        font-size: 2.8rem;
        margin-bottom: 15px;
        font-weight: 700;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        position: relative;
        z-index: 2;
    }

    .header p {
        font-size: 1.3rem;
        margin-bottom: 25px;
        opacity: 0.95;
        position: relative;
        z-index: 2;
    }

    .shelter-name {
        background: rgba(255, 255, 255, 0.25);
        padding: 12px 25px;
        border-radius: 30px;
        display: inline-block;
        font-weight: 600;
        margin: 15px 0 25px 0;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        position: relative;
        z-index: 2;
    }

    .header-actions {
        display: flex;
        gap: 20px;
        justify-content: center;
        flex-wrap: wrap;
        position: relative;
        z-index: 2;
    }

    .btn {
        padding: 14px 30px;
        border: none;
        border-radius: 30px;
        text-decoration: none;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
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
        border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        border-top: 5px solid var(--color);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        transition: left 0.5s;
    }

    .stat-card:hover::before {
        left: 100%;
    }

    .stat-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .stat-card.total {
        --color: #28a745;
    }

    .stat-card.available {
        --color: #007bff;
    }

    .stat-card.pending {
        --color: #ffc107;
    }

    .stat-card.adopted {
        --color: #6f42c1;
    }

    .stat-card.applications {
        --color: #17a2b8;
    }

    .stat-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        z-index: 2;
    }

    .stat-info h3 {
        color: #666;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
    }

    .stat-number {
        font-size: 3rem;
        font-weight: 700;
        color: var(--color);
        line-height: 1;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .stat-change {
        font-size: 0.8rem;
        color: var(--color);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 5px;
    }

    .stat-icon {
        width: 70px;
        height: 70px;
        border-radius: 15px;
        background: var(--color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        opacity: 0.9;
    }

    /* Section Cards */
    .section {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .section-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 25px 30px;
        border-bottom: 1px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .section-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .section-title i {
        color: #28a745;
        font-size: 1.4rem;
    }

    .section-link {
        color: #28a745;
        text-decoration: none;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: color 0.3s ease;
        padding: 8px 16px;
        border-radius: 20px;
        border: 2px solid transparent;
    }

    .section-link:hover {
        color: #20c997;
        text-decoration: none;
        background: rgba(40, 167, 69, 0.1);
        border-color: rgba(40, 167, 69, 0.2);
    }

    .section-content {
        padding: 30px;
    }

    /* Pet Cards */
    .pet-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
    }

    .pet-card {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 20px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        cursor: pointer;
    }

    .pet-card:hover {
        background: white;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        border-color: #28a745;
        transform: translateY(-5px);
    }

    .pet-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .pet-image {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #28a745, #20c997);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        overflow: hidden;
    }

    .pet-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .pet-details h4 {
        color: #2c3e50;
        margin-bottom: 5px;
        font-size: 1.2rem;
        font-weight: 600;
    }

    .pet-meta {
        color: #666;
        font-size: 0.9rem;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }

    .pet-meta span {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        margin-top: 8px;
        display: inline-block;
    }

    .status-available {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
    }

    .status-pending {
        background: rgba(255, 193, 7, 0.2);
        color: #ffc107;
    }

    .status-adopted {
        background: rgba(111, 66, 193, 0.2);
        color: #6f42c1;
    }

    /* Application Cards */
    .application-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        border-left: 4px solid var(--status-color);
        transition: all 0.3s ease;
    }

    .application-card:hover {
        background: white;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        transform: translateX(5px);
    }

    .application-card.pending {
        --status-color: #ffc107;
    }

    .application-card.approved {
        --status-color: #28a745;
    }

    .application-card.rejected {
        --status-color: #dc3545;
    }

    .application-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .application-info h5 {
        color: #2c3e50;
        font-size: 1.1rem;
        margin-bottom: 5px;
    }

    .application-meta {
        color: #666;
        font-size: 0.9rem;
    }

    .application-status {
        padding: 6px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .application-status.pending {
        background: rgba(255, 193, 7, 0.2);
        color: #ffc107;
    }

    .application-status.approved {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
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
        margin-bottom: 15px;
        color: #2c3e50;
    }

    .empty-text {
        margin-bottom: 30px;
        line-height: 1.8;
        font-size: 1.1rem;
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
        animation: slideInRight 0.5s ease-out;
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

    .message.warning {
        background: #ffc107;
        color: #212529;
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

    /* Quick Actions */
    .quick-actions {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .action-btn {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        background: #28a745;
    }

    .action-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .header {
            padding: 30px 20px;
        }

        .header h1 {
            font-size: 2.2rem;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .pet-grid {
            grid-template-columns: 1fr;
        }

        .quick-actions {
            bottom: 20px;
            right: 20px;
        }

        .action-btn {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .header-actions {
            flex-direction: column;
            align-items: center;
        }

        .btn {
            width: 100%;
            max-width: 300px;
            justify-content: center;
        }
    }

    /* Animation */
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate {
        animation: slideInUp 0.8s ease-out;
    }

    /* Additional Styles */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }

    .info-card {
        padding: 20px;
        border-radius: 15px;
        border-left: 4px solid var(--accent-color);
    }

    .info-card.green {
        --accent-color: #28a745;
        background: #e8f5e8;
    }

    .info-card.blue {
        --accent-color: #2196f3;
        background: #e3f2fd;
    }

    .info-card.orange {
        --accent-color: #ff9800;
        background: #fff3e0;
    }

    .info-card h4 {
        color: var(--accent-color);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.1rem;
    }

    .info-card p {
        color: #666;
        margin-bottom: 15px;
        line-height: 1.6;
    }

    .info-card .btn {
        font-size: 0.9rem;
        padding: 8px 20px;
    }
    </style>
</head>

<body>
    <!-- Include Shelter Navbar -->
    <?php include_once __DIR__ . '/../common/navbar_shelter.php'; ?>

    <div class="container">
        <!-- Welcome Header -->
        <div class="header animate">
            <h1>Welcome to Your Shelter Dashboard! 🏠</h1>
            <p>Manage your pets, track adoptions, and help animals find loving homes</p>
            <?php if (!empty($shelter_info['shelter_name']) && $shelter_info['shelter_name'] !== 'Your Shelter'): ?>
            <div class="shelter-name">
                <i class="fas fa-home"></i> <?php echo htmlspecialchars($shelter_info['shelter_name']); ?>
            </div>
            <?php endif; ?>
            <div class="header-actions">
                <a href="<?php echo $BASE_URL; ?>shelter/addPet.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i>
                    Add New Pet
                </a>
                <a href="<?php echo $BASE_URL; ?>shelter/viewPets.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i>
                    View All Pets
                </a>
                <a href="<?php echo $BASE_URL; ?>shelter/adoptionRequests.php" class="btn btn-secondary">
                    <i class="fas fa-heart"></i>
                    Adoption Requests
                </a>
            </div>
        </div>

        <!-- Display Messages -->
        <?php if (!empty($error_message)): ?>
        <div class="message warning">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid animate">
            <div class="stat-card total" onclick="navigateToPage('viewPets')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Pets</h3>
                        <div class="stat-number"><?php echo $stats['total_pets']; ?></div>
                        <?php if ($stats['total_pets'] > 0): ?>
                        <div class="stat-change">
                            <i class="fas fa-paw"></i>
                            In your care
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card available" onclick="navigateToPage('viewPets', 'available')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Available for Adoption</h3>
                        <div class="stat-number"><?php echo $stats['available_pets']; ?></div>
                        <?php if ($stats['available_pets'] > 0): ?>
                        <div class="stat-change">
                            <i class="fas fa-heart"></i>
                            Ready for homes
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pending" onclick="navigateToPage('viewPets', 'pending')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Pending Adoption</h3>
                        <div class="stat-number"><?php echo $stats['pending_pets']; ?></div>
                        <?php if ($stats['pending_pets'] > 0): ?>
                        <div class="stat-change">
                            <i class="fas fa-clock"></i>
                            In process
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card adopted" onclick="navigateToPage('viewPets', 'adopted')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Successfully Adopted</h3>
                        <div class="stat-number"><?php echo $stats['adopted_pets']; ?></div>
                        <?php if ($stats['recent_adoptions'] > 0): ?>
                        <div class="stat-change">
                            <i class="fas fa-arrow-up"></i>
                            <?php echo $stats['recent_adoptions']; ?> this month
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card applications" onclick="navigateToPage('adoptionRequests')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Pending Applications</h3>
                        <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                        <?php if ($stats['total_applications'] > 0): ?>
                        <div class="stat-change">
                            <i class="fas fa-file-alt"></i>
                            <?php echo $stats['total_applications']; ?> total
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Connection Status -->
        <?php if (!$db_connected): ?>
        <div class="section animate">
            <div class="section-content">
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <h3 class="empty-title">Database Setup Needed</h3>
                    <p class="empty-text">
                        It looks like your database isn't set up yet. Please make sure your database connection is
                        configured properly and the required tables are created.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Pets -->
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-paw"></i>
                    Recent Pets
                </h2>
                <a href="<?php echo $BASE_URL; ?>shelter/viewPets.php" class="section-link">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="section-content">
                <?php if (empty($recent_pets)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                    <h3 class="empty-title">No Pets Added Yet</h3>
                    <p class="empty-text">
                        Start by adding pets to your shelter. This will help potential adopters find their perfect
                        companions!
                    </p>
                    <a href="<?php echo $BASE_URL; ?>shelter/addPet.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i>
                        Add Your First Pet
                    </a>
                </div>
                <?php else: ?>
                <div class="pet-grid">
                    <?php foreach ($recent_pets as $pet): ?>
                    <div class="pet-card" onclick="navigateToPet(<?php echo $pet['pet_id']; ?>)">
                        <div class="pet-info">
                            <div class="pet-image">
                                <?php if (!empty($pet['primary_image'])): ?>
                                <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                                    alt="<?php echo htmlspecialchars($pet['pet_name']); ?>">
                                <?php else: ?>
                                <?php echo strtoupper(substr($pet['pet_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="pet-details">
                                <h4><?php echo htmlspecialchars($pet['pet_name']); ?></h4>
                                <div class="pet-meta">
                                    <span><i class="fas fa-tag"></i>
                                        <?php echo htmlspecialchars($pet['category_name'] ?: 'Unknown'); ?></span>
                                    <span><i class="fas fa-venus-mars"></i>
                                        <?php echo ucfirst(htmlspecialchars($pet['gender'] ?: 'Unknown')); ?></span>
                                    <span><i class="fas fa-birthday-cake"></i>
                                        <?php echo htmlspecialchars($pet['age'] ?: 'Unknown'); ?> years</span>
                                </div>
                                <div
                                    class="status-badge status-<?php echo strtolower($pet['status'] ?: 'available'); ?>">
                                    <?php echo ucfirst(htmlspecialchars($pet['status'] ?: 'Available')); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Applications -->
        <?php if (!empty($recent_applications)): ?>
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-file-alt"></i>
                    Recent Applications
                </h2>
                <a href="<?php echo $BASE_URL; ?>shelter/adoptionRequests.php" class="section-link">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="section-content">
                <?php foreach ($recent_applications as $application): ?>
                <div class="application-card <?php echo strtolower($application['application_status']); ?>">
                    <div class="application-header">
                        <div class="application-info">
                            <h5><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                            </h5>
                            <div class="application-meta">
                                <span><i class="fas fa-paw"></i>
                                    <?php echo htmlspecialchars($application['pet_name']); ?></span>
                                <span><i class="fas fa-calendar"></i>
                                    <?php echo date('M j, Y', strtotime($application['application_date'])); ?></span>
                                <span><i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($application['email']); ?></span>
                            </div>
                        </div>
                        <div class="application-status <?php echo strtolower($application['application_status']); ?>">
                            <?php echo ucfirst($application['application_status']); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Adoptions -->
        <?php if (!empty($recent_adoptions)): ?>
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-heart"></i>
                    Recent Successful Adoptions
                </h2>
            </div>
            <div class="section-content">
                <div class="pet-grid">
                    <?php foreach ($recent_adoptions as $adoption): ?>
                    <div class="pet-card" style="border-left: 4px solid #28a745;">
                        <div class="pet-info">
                            <div class="pet-image" style="background: linear-gradient(135deg, #28a745, #20c997);">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="pet-details">
                                <h4><?php echo htmlspecialchars($adoption['pet_name']); ?></h4>
                                <div class="pet-meta">
                                    <span><i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($adoption['first_name'] . ' ' . $adoption['last_name']); ?></span>
                                    <span><i class="fas fa-calendar-check"></i>
                                        <?php echo date('M j, Y', strtotime($adoption['adoption_date'])); ?></span>
                                </div>
                                <div class="status-badge" style="background: rgba(40, 167, 69, 0.2); color: #28a745;">
                                    Successfully Adopted
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Getting Started Section -->
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-rocket"></i>
                    Getting Started Guide
                </h2>
            </div>
            <div class="section-content">
                <div class="info-grid">
                    <div class="info-card green">
                        <h4>
                            <i class="fas fa-plus-circle"></i>
                            Add Your Pets
                        </h4>
                        <p>
                            Start by adding pets to your shelter database. Include photos, detailed descriptions,
                            health information, and medical records to help potential adopters make informed decisions.
                        </p>
                        <a href="<?php echo $BASE_URL; ?>shelter/addPet.php" class="btn"
                            style="background: #28a745; color: white;">
                            <i class="fas fa-plus"></i> Add New Pet
                        </a>
                    </div>

                    <div class="info-card blue">
                        <h4>
                            <i class="fas fa-heart"></i>
                            Manage Adoptions
                        </h4>
                        <p>
                            Review and process adoption applications from potential pet parents.
                            Communicate with applicants and facilitate successful matches between pets and families.
                        </p>
                        <a href="<?php echo $BASE_URL; ?>shelter/adoptionRequests.php" class="btn"
                            style="background: #2196f3; color: white;">
                            <i class="fas fa-file-alt"></i> View Applications
                        </a>
                    </div>

                    <div class="info-card orange">
                        <h4>
                            <i class="fas fa-syringe"></i>
                            Track Health Records
                        </h4>
                        <p>
                            Keep comprehensive health records for all pets including vaccinations,
                            medical treatments, and regular check-ups to ensure their well-being.
                        </p>
                        <a href="<?php echo $BASE_URL; ?>shelter/vaccinationTracker.php" class="btn"
                            style="background: #ff9800; color: white;">
                            <i class="fas fa-notes-medical"></i> Health Tracker
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shelter Information Section -->
        <?php if ($db_connected && !empty($shelter_info['shelter_name'])): ?>
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Shelter Information
                </h2>
            </div>
            <div class="section-content">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div>
                        <h5 style="color: #2c3e50; margin-bottom: 10px;">Shelter Details</h5>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($shelter_info['shelter_name']); ?></p>
                        <p><strong>Manager:</strong>
                            <?php echo htmlspecialchars($shelter_info['first_name'] . ' ' . $shelter_info['last_name']); ?>
                        </p>
                        <?php if (!empty($shelter_info['license_number'])): ?>
                        <p><strong>License:</strong> <?php echo htmlspecialchars($shelter_info['license_number']); ?>
                        </p>
                        <?php endif; ?>
                        <?php if ($shelter_info['capacity'] > 0): ?>
                        <p><strong>Capacity:</strong> <?php echo (int)$shelter_info['capacity']; ?> pets</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h5 style="color: #2c3e50; margin-bottom: 10px;">Contact Information</h5>
                        <?php if (!empty($shelter_info['email'])): ?>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($shelter_info['email']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($shelter_info['phone'])): ?>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($shelter_info['phone']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Tips Section -->
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-lightbulb"></i>
                    Quick Tips for Success
                </h2>
            </div>
            <div class="section-content">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div
                        style="padding: 15px; background: #f8f9fa; border-radius: 10px; border-left: 3px solid #28a745;">
                        <h6 style="color: #28a745; margin-bottom: 8px;">📸 High-Quality Photos</h6>
                        <p style="font-size: 0.9rem; color: #666; margin: 0;">
                            Upload clear, well-lit photos of your pets. Good photos significantly increase adoption
                            rates.
                        </p>
                    </div>
                    <div
                        style="padding: 15px; background: #f8f9fa; border-radius: 10px; border-left: 3px solid #007bff;">
                        <h6 style="color: #007bff; margin-bottom: 8px;">📝 Detailed Descriptions</h6>
                        <p style="font-size: 0.9rem; color: #666; margin: 0;">
                            Write engaging descriptions that highlight each pet's personality and special qualities.
                        </p>
                    </div>
                    <div
                        style="padding: 15px; background: #f8f9fa; border-radius: 10px; border-left: 3px solid #ffc107;">
                        <h6 style="color: #ffc107; margin-bottom: 8px;">⚡ Quick Responses</h6>
                        <p style="font-size: 0.9rem; color: #666; margin: 0;">
                            Respond to adoption applications promptly to maintain adopter interest and trust.
                        </p>
                    </div>
                    <div
                        style="padding: 15px; background: #f8f9fa; border-radius: 10px; border-left: 3px solid #17a2b8;">
                        <h6 style="color: #17a2b8; margin-bottom: 8px;">💊 Health Records</h6>
                        <p style="font-size: 0.9rem; color: #666; margin: 0;">
                            Keep vaccination and medical records up-to-date for all pets in your care.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Floating Button -->
    <div class="quick-actions">
        <button class="action-btn" onclick="navigateToPage('addPet')" title="Add New Pet">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Display Session Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="message success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="message error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <script>
    // Global variables
    const baseUrl = '<?php echo $BASE_URL; ?>';

    // Navigation functions
    function navigateToPage(page, filter = '') {
        const pages = {
            'addPet': 'shelter/addPet.php',
            'viewPets': 'shelter/viewPets.php',
            'adoptionRequests': 'shelter/adoptionRequests.php',
            'vaccinationTracker': 'shelter/vaccinationTracker.php',
            'dashboard': 'shelter/dashboard.php'
        };

        if (pages[page]) {
            let url = baseUrl + pages[page];
            if (filter && page === 'viewPets') {
                url += '?status=' + filter;
            }
            window.location.href = url;
        }
    }

    function navigateToPet(petId) {
        window.location.href = baseUrl + 'shelter/viewPets.php#pet-' + petId;
    }

    // Initialize dashboard
    document.addEventListener('DOMContentLoaded', function() {
        // Animate statistics counters
        const counters = document.querySelectorAll('.stat-number');
        counters.forEach(counter => {
            const target = parseInt(counter.textContent);
            if (target > 0 && target < 1000) { // Only animate reasonable numbers
                let current = 0;
                const increment = Math.ceil(target / 30);
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = current;
                }, 50);
            }
        });

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.transition = 'all 0.3s ease';
                message.style.opacity = '0';
                message.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.remove();
                    }
                }, 300);
            });
        }, 5000);

        // Add hover effects to interactive cards
        document.querySelectorAll('.pet-card, .stat-card, .application-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                if (!this.style.transform.includes('translateY')) {
                    this.style.transform = 'translateY(-5px)';
                }
            });

            card.addEventListener('mouseleave', function() {
                if (this.style.transform.includes('translateY(-5px)')) {
                    this.style.transform = 'translateY(0)';
                }
            });
        });

        // Add staggered animation to cards
        const animatedElements = document.querySelectorAll('.animate');
        animatedElements.forEach((element, index) => {
            element.style.animationDelay = `${index * 0.1}s`;
        });

        // Add click handlers for stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function() {
                // Add click animation
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });

        // Smooth scroll for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading states for navigation
        document.querySelectorAll('a[href], button[onclick]').forEach(element => {
            element.addEventListener('click', function() {
                // Don't add loading to external links or hash links
                if (this.href && (this.href.startsWith('http') || this.href.includes('#'))) {
                    return;
                }

                // Add subtle loading indication
                const originalContent = this.innerHTML;
                if (this.querySelector('i')) {
                    const icon = this.querySelector('i');
                    icon.className = 'fas fa-spinner fa-spin';
                }

                // Reset after a short delay if still on page
                setTimeout(() => {
                    if (this.innerHTML !== originalContent) {
                        this.innerHTML = originalContent;
                    }
                }, 2000);
            });
        });

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            // Alt + N for new pet
            if (e.altKey && e.key === 'n') {
                e.preventDefault();
                navigateToPage('addPet');
            }

            // Alt + V for view pets
            if (e.altKey && e.key === 'v') {
                e.preventDefault();
                navigateToPage('viewPets');
            }

            // Alt + R for adoption requests
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                navigateToPage('adoptionRequests');
            }
        });

        // Add tooltips for keyboard shortcuts
        const addPetBtn = document.querySelector('a[href*="addPet"]');
        if (addPetBtn) {
            addPetBtn.title += ' (Alt+N)';
        }

        const viewPetsBtn = document.querySelector('a[href*="viewPets"]');
        if (viewPetsBtn) {
            viewPetsBtn.title += ' (Alt+V)';
        }

        const requestsBtn = document.querySelector('a[href*="adoptionRequests"]');
        if (requestsBtn) {
            requestsBtn.title += ' (Alt+R)';
        }

        // Initialize any dashboard-specific features
        initializeDashboardFeatures();
    });

    // Dashboard-specific initialization
    function initializeDashboardFeatures() {
        // Add real-time updates (if needed in future)
        // Add chart initialization (if adding charts later)
        // Add any other dashboard-specific functionality

        // Example: Periodic stats refresh (uncomment if needed)
        // setInterval(refreshStats, 300000); // Refresh every 5 minutes
    }

    // Function to refresh statistics (for future use)
    function refreshStats() {
        // This could fetch updated statistics via AJAX
        console.log('Refreshing dashboard statistics...');
        // Implementation would go here
    }

    // Utility function for showing notifications
    function showNotification(message, type = 'success') {
        // Remove existing notifications
        document.querySelectorAll('.message').forEach(msg => msg.remove());

        const notification = document.createElement('div');
        notification.className = `message ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
        `;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            notification.style.transition = 'all 0.3s ease';
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    // Handle offline/online status
    window.addEventListener('online', function() {
        showNotification('Connection restored!', 'success');
    });

    window.addEventListener('offline', function() {
        showNotification('You are currently offline. Some features may not work.', 'error');
    });
    </script>
</body>

</html>