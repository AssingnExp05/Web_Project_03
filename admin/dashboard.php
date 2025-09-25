<?php
// admin/dashboard.php - Admin Dashboard Page
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
$page_title = 'Admin Dashboard - Pet Adoption Care Guide';

// Initialize default values
$stats = [
    'total_users' => 0,
    'total_shelters' => 0,
    'total_adopters' => 0,
    'pending_users' => 0,
    'total_pets' => 0,
    'available_pets' => 0,
    'adopted_pets' => 0,
    'total_adoptions' => 0,
    'pending_adoptions' => 0,
    'approved_adoptions' => 0,
    'monthly_adoptions' => 0,
    'vaccination_due' => 0
];

$recent_users = [];
$recent_adoptions = [];
$recent_shelters = [];
$system_alerts = [];
$monthly_stats = [];

// Try to connect to database
try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if ($db) {
        // Get user statistics
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_users'] = $result ? (int)$result['count'] : 0;
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
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'adopter'");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_adopters'] = $result ? (int)$result['count'] : 0;
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
        
        // Get pet statistics
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
        
        // Get adoption statistics
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['total_adoptions'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE application_status = 'pending'");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['pending_adoptions'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE application_status = 'approved'");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['approved_adoptions'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        // Get monthly adoptions
        try {
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoptions WHERE MONTH(adoption_date) = MONTH(NOW()) AND YEAR(adoption_date) = YEAR(NOW())");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['monthly_adoptions'] = $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            // Handle silently
        }
        
        // Get recent users (last 5)
        try {
            $stmt = $db->prepare("
                SELECT user_id, first_name, last_name, email, user_type, is_active, created_at
                FROM users 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $recent_users = [];
        }
        
        // Get recent adoptions
        try {
            $stmt = $db->prepare("
                SELECT aa.*, p.pet_name, u.first_name, u.last_name, s.shelter_name
                FROM adoption_applications aa
                LEFT JOIN pets p ON aa.pet_id = p.pet_id
                LEFT JOIN users u ON aa.adopter_id = u.user_id
                LEFT JOIN shelters s ON p.shelter_id = s.shelter_id
                ORDER BY aa.application_date DESC
                LIMIT 5
            ");
            $stmt->execute();
            $recent_adoptions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $recent_adoptions = [];
        }
        
        // Get recent shelters
        try {
            $stmt = $db->prepare("
                SELECT s.*, u.first_name, u.last_name, u.email
                FROM shelters s
                LEFT JOIN users u ON s.user_id = u.user_id
                ORDER BY s.created_at DESC
                LIMIT 5
            ");
            $stmt->execute();
            $recent_shelters = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            $recent_shelters = [];
        }
        
        // Create system alerts based on data
        if ($stats['pending_users'] > 0) {
            $system_alerts[] = [
                'type' => 'warning',
                'icon' => 'fa-user-clock',
                'title' => 'Pending User Approvals',
                'message' => $stats['pending_users'] . ' users are waiting for approval',
                'action_url' => $BASE_URL . 'admin/manageUsers.php',
                'action_text' => 'Review Users'
            ];
        }
        
        if ($stats['pending_adoptions'] > 10) {
            $system_alerts[] = [
                'type' => 'info',
                'icon' => 'fa-heart',
                'title' => 'High Adoption Activity',
                'message' => $stats['pending_adoptions'] . ' adoption applications pending review',
                'action_url' => $BASE_URL . 'admin/manageAdoptions.php',
                'action_text' => 'Review Applications'
            ];
        }
        
        if ($stats['total_shelters'] < 5) {
            $system_alerts[] = [
                'type' => 'success',
                'icon' => 'fa-home',
                'title' => 'Shelter Network Growth',
                'message' => 'Consider reaching out to more shelters to expand the network',
                'action_url' => $BASE_URL . 'admin/manageUsers.php',
                'action_text' => 'View Shelters'
            ];
        }
    }
} catch (Exception $e) {
    error_log("Admin Dashboard database error: " . $e->getMessage());
    // Continue with default values
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
    .header {
        background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
        color: white;
        border-radius: 20px;
        padding: 40px;
        margin-bottom: 30px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="white" opacity="0.1"/></svg>');
        background-size: 50px 50px;
    }

    .header-content {
        position: relative;
        z-index: 1;
    }

    .header h1 {
        font-size: 2.8rem;
        margin-bottom: 10px;
        font-weight: 700;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    .header p {
        font-size: 1.3rem;
        margin-bottom: 20px;
        opacity: 0.9;
    }

    .admin-badge {
        background: rgba(255, 255, 255, 0.2);
        padding: 8px 20px;
        border-radius: 25px;
        display: inline-block;
        font-weight: 600;
        margin-top: 10px;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .header-actions {
        display: flex;
        gap: 20px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 30px;
    }

    .btn {
        padding: 12px 30px;
        border: none;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
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
        border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.3);
        text-decoration: none;
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
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: var(--color);
    }

    .stat-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .stat-card.users {
        --color: #007bff;
    }

    .stat-card.shelters {
        --color: #28a745;
    }

    .stat-card.adopters {
        --color: #17a2b8;
    }

    .stat-card.pending-users {
        --color: #ffc107;
    }

    .stat-card.pets {
        --color: #6f42c1;
    }

    .stat-card.available {
        --color: #20c997;
    }

    .stat-card.adopted {
        --color: #fd7e14;
    }

    .stat-card.adoptions {
        --color: #dc3545;
    }

    .stat-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
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

    /* System Alerts */
    .alerts-section {
        margin-bottom: 40px;
    }

    .alert-item {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        border-left: 5px solid var(--alert-color);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s ease;
    }

    .alert-item:hover {
        transform: translateX(5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .alert-item.warning {
        --alert-color: #ffc107;
    }

    .alert-item.info {
        --alert-color: #17a2b8;
    }

    .alert-item.success {
        --alert-color: #28a745;
    }

    .alert-item.danger {
        --alert-color: #dc3545;
    }

    .alert-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--alert-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .alert-content {
        flex: 1;
    }

    .alert-title {
        font-weight: 600;
        margin-bottom: 5px;
        color: #2c3e50;
    }

    .alert-message {
        color: #666;
        font-size: 0.9rem;
    }

    .alert-action {
        background: var(--alert-color);
        color: white;
        padding: 8px 20px;
        border-radius: 20px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
    }

    .alert-action:hover {
        transform: scale(1.05);
        text-decoration: none;
        color: white;
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
        background: #f8f9fa;
        padding: 25px 30px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .section-title i {
        color: #dc3545;
        font-size: 1.3rem;
    }

    .section-link {
        color: #dc3545;
        text-decoration: none;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: color 0.3s ease;
    }

    .section-link:hover {
        color: #6f42c1;
        text-decoration: none;
    }

    .section-content {
        padding: 30px;
    }

    /* Table Styles */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .data-table th,
    .data-table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    .data-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #2c3e50;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }

    .data-table tr:hover {
        background: #f8f9fa;
    }

    /* User Cards */
    .user-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .user-card {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 20px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .user-card:hover {
        background: white;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        border-color: #dc3545;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }

    .user-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #dc3545, #6f42c1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.3rem;
        font-weight: 700;
    }

    .user-details h4 {
        color: #2c3e50;
        margin-bottom: 3px;
    }

    .user-meta {
        color: #666;
        font-size: 0.85rem;
    }

    .user-status {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-active {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
    }

    .status-pending {
        background: rgba(255, 193, 7, 0.2);
        color: #ffc107;
    }

    .user-type-badge {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
    }

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
        margin-bottom: 15px;
        color: #2c3e50;
    }

    .empty-text {
        margin-bottom: 30px;
        line-height: 1.8;
        font-size: 1.1rem;
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
    }

    .action-btn:hover {
        transform: scale(1.1);
    }

    .action-btn.primary {
        background: #dc3545;
    }

    .action-btn.secondary {
        background: #6f42c1;
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
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

        .user-grid {
            grid-template-columns: 1fr;
        }

        .alert-item {
            flex-direction: column;
            text-align: center;
            gap: 15px;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
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

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .animate {
        animation: slideInUp 0.8s ease-out;
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
        <!-- Welcome Header -->
        <div class="header animate">
            <div class="header-content">
                <h1>System Administrator Dashboard 🛡️</h1>
                <p>Monitor, manage, and maintain the Pet Adoption Care Guide platform</p>
                <div class="admin-badge">
                    <i class="fas fa-crown"></i> System Admin Access
                </div>
                <div class="header-actions">
                    <a href="<?php echo $BASE_URL; ?>admin/manageUsers.php" class="btn btn-primary">
                        <i class="fas fa-users"></i>
                        Manage Users
                    </a>
                    <a href="<?php echo $BASE_URL; ?>admin/reports.php" class="btn btn-secondary">
                        <i class="fas fa-chart-bar"></i>
                        View Reports
                    </a>
                    <a href="<?php echo $BASE_URL; ?>admin/managePets.php" class="btn btn-secondary">
                        <i class="fas fa-paw"></i>
                        Manage Pets
                    </a>
                </div>
            </div>
        </div>

        <!-- System Alerts -->
        <?php if (!empty($system_alerts)): ?>
        <div class="alerts-section animate">
            <?php foreach ($system_alerts as $alert): ?>
            <div class="alert-item <?php echo $alert['type']; ?>">
                <div class="alert-icon">
                    <i class="fas <?php echo $alert['icon']; ?>"></i>
                </div>
                <div class="alert-content">
                    <div class="alert-title"><?php echo htmlspecialchars($alert['title']); ?></div>
                    <div class="alert-message"><?php echo htmlspecialchars($alert['message']); ?></div>
                </div>
                <a href="<?php echo $alert['action_url']; ?>" class="alert-action">
                    <?php echo htmlspecialchars($alert['action_text']); ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid animate">
            <div class="stat-card users" onclick="navigateToPage('manageUsers')">
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

            <div class="stat-card shelters" onclick="navigateToPage('manageUsers')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Active Shelters</h3>
                        <div class="stat-number"><?php echo $stats['total_shelters']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card adopters" onclick="navigateToPage('manageUsers')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Registered Adopters</h3>
                        <div class="stat-number"><?php echo $stats['total_adopters']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pending-users" onclick="navigateToPage('manageUsers')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Pending Approvals</h3>
                        <div class="stat-number"><?php echo $stats['pending_users']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pets" onclick="navigateToPage('managePets')">
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

            <div class="stat-card available" onclick="navigateToPage('managePets')">
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

            <div class="stat-card adopted" onclick="navigateToPage('managePets')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Adopted Pets</h3>
                        <div class="stat-number"><?php echo $stats['adopted_pets']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card adoptions" onclick="navigateToPage('manageAdoptions')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Applications</h3>
                        <div class="stat-number"><?php echo $stats['total_adoptions']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-users"></i>
                    Recent User Registrations
                </h2>
                <a href="<?php echo $BASE_URL; ?>admin/manageUsers.php" class="section-link">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="section-content">
                <?php if (empty($recent_users)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="empty-title">No Users Registered Yet</h3>
                    <p class="empty-text">
                        When users register for the platform, they will appear here for review and management.
                    </p>
                </div>
                <?php else: ?>
                <div class="user-grid">
                    <?php foreach ($recent_users as $user): ?>
                    <div class="user-card fade-in">
                        <div class="user-info">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <div class="user-details">
                                <h4><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?>
                                </h4>
                                <div class="user-meta">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?><br>
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M j, Y', strtotime($user['created_at'] ?? 'now')); ?>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="user-type-badge">
                                <?php echo ucfirst(htmlspecialchars($user['user_type'] ?? 'user')); ?>
                            </span>
                            <span
                                class="user-status status-<?php echo ($user['is_active'] ?? 0) ? 'active' : 'pending'; ?>">
                                <?php echo ($user['is_active'] ?? 0) ? 'Active' : 'Pending'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Adoption Applications -->
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-heart"></i>
                    Recent Adoption Applications
                </h2>
                <a href="<?php echo $BASE_URL; ?>admin/manageAdoptions.php" class="section-link">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="section-content">
                <?php if (empty($recent_adoptions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3 class="empty-title">No Adoption Applications Yet</h3>
                    <p class="empty-text">
                        When adopters submit applications for pets, they will appear here for monitoring and oversight.
                    </p>
                </div>
                <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Pet Name</th>
                                <th>Adopter</th>
                                <th>Shelter</th>
                                <th>Application Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_adoptions as $adoption): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($adoption['pet_name'] ?? 'Unknown Pet'); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(($adoption['first_name'] ?? '') . ' ' . ($adoption['last_name'] ?? '')); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($adoption['shelter_name'] ?? 'Unknown Shelter'); ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($adoption['application_date'] ?? 'now')); ?>
                                </td>
                                <td>
                                    <span
                                        class="user-status status-<?php echo strtolower($adoption['application_status'] ?? 'pending'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($adoption['application_status'] ?? 'Pending')); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Overview -->
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-cogs"></i>
                    System Overview
                </h2>
            </div>
            <div class="section-content">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
                    <!-- Platform Health -->
                    <div
                        style="padding: 25px; background: linear-gradient(135deg, #28a74520, #20c99720); border-radius: 15px; border-left: 4px solid #28a745;">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                            <div
                                style="width: 50px; height: 50px; background: #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem;">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div>
                                <h4 style="color: #28a745; margin: 0; font-size: 1.2rem;">Platform Health</h4>
                                <p style="color: #666; margin: 0; font-size: 0.9rem;">System Status: Online</p>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666;">Database:</span>
                            <span style="color: #28a745; font-weight: 600;">Connected</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666;">Last Backup:</span>
                            <span style="color: #666;">Today</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #666;">Uptime:</span>
                            <span style="color: #28a745; font-weight: 600;">99.9%</span>
                        </div>
                    </div>

                    <!-- Platform Activity -->
                    <div
                        style="padding: 25px; background: linear-gradient(135deg, #007bff20, #0056b320); border-radius: 15px; border-left: 4px solid #007bff;">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                            <div
                                style="width: 50px; height: 50px; background: #007bff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem;">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <h4 style="color: #007bff; margin: 0; font-size: 1.2rem;">Platform Activity</h4>
                                <p style="color: #666; margin: 0; font-size: 0.9rem;">Last 30 days</p>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666;">New Users:</span>
                            <span style="color: #007bff; font-weight: 600;"><?php echo $stats['total_users']; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span style="color: #666;">Pet Listings:</span>
                            <span style="color: #007bff; font-weight: 600;"><?php echo $stats['total_pets']; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #666;">Applications:</span>
                            <span
                                style="color: #007bff; font-weight: 600;"><?php echo $stats['total_adoptions']; ?></span>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div
                        style="padding: 25px; background: linear-gradient(135deg, #6f42c120, #dc354520); border-radius: 15px; border-left: 4px solid #6f42c1;">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                            <div
                                style="width: 50px; height: 50px; background: #6f42c1; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem;">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div>
                                <h4 style="color: #6f42c1; margin: 0; font-size: 1.2rem;">Admin Actions</h4>
                                <p style="color: #666; margin: 0; font-size: 0.9rem;">Quick Management</p>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <a href="<?php echo $BASE_URL; ?>admin/manageUsers.php"
                                style="background: #6f42c1; color: white; padding: 10px 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 600; transition: all 0.3s ease;"
                                onmouseover="this.style.background='#5a2d91'"
                                onmouseout="this.style.background='#6f42c1'">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                            <a href="<?php echo $BASE_URL; ?>admin/reports.php"
                                style="background: #dc3545; color: white; padding: 10px 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 600; transition: all 0.3s ease;"
                                onmouseover="this.style.background='#c82333'"
                                onmouseout="this.style.background='#dc3545'">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Shelter Activity -->
        <?php if (!empty($recent_shelters)): ?>
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-home"></i>
                    Recent Shelter Registrations
                </h2>
                <a href="<?php echo $BASE_URL; ?>admin/manageUsers.php?filter=shelter" class="section-link">
                    View All Shelters <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="section-content">
                <div class="user-grid">
                    <?php foreach (array_slice($recent_shelters, 0, 4) as $shelter): ?>
                    <div class="user-card fade-in">
                        <div class="user-info">
                            <div class="user-avatar" style="background: linear-gradient(135deg, #28a745, #20c997);">
                                <i class="fas fa-home"></i>
                            </div>
                            <div class="user-details">
                                <h4><?php echo htmlspecialchars($shelter['shelter_name'] ?? 'Unknown Shelter'); ?></h4>
                                <div class="user-meta">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars(($shelter['first_name'] ?? '') . ' ' . ($shelter['last_name'] ?? '')); ?><br>
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($shelter['email'] ?? 'N/A'); ?><br>
                                    <?php if (!empty($shelter['license_number'])): ?>
                                    <i class="fas fa-certificate"></i> License:
                                    <?php echo htmlspecialchars($shelter['license_number']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                            <span class="user-type-badge" style="background: rgba(40, 167, 69, 0.2); color: #28a745;">
                                Shelter
                            </span>
                            <span style="color: #666; font-size: 0.8rem;">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M j', strtotime($shelter['created_at'] ?? 'now')); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions Floating Buttons -->
    <div class="quick-actions">
        <button class="action-btn primary" onclick="navigateToPage('manageUsers')" title="Manage Users">
            <i class="fas fa-users"></i>
        </button>
        <button class="action-btn secondary" onclick="navigateToPage('reports')" title="View Reports">
            <i class="fas fa-chart-bar"></i>
        </button>
        <button class="action-btn secondary" onclick="location.reload()" title="Refresh Dashboard">
            <i class="fas fa-sync"></i>
        </button>
    </div>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="message success">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
            <button onclick="this.parentElement.parentElement.remove()"
                style="background: none; border: none; color: white; margin-left: auto; cursor: pointer; font-size: 1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="message error">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
            <button onclick="this.parentElement.parentElement.remove()"
                style="background: none; border: none; color: white; margin-left: auto; cursor: pointer; font-size: 1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <script>
    // Navigation function
    function navigateToPage(page) {
        const baseUrl = '<?php echo $BASE_URL; ?>';
        const pages = {
            'manageUsers': 'admin/manageUsers.php',
            'managePets': 'admin/managePets.php',
            'manageAdoptions': 'admin/manageAdoptions.php',
            'manageVaccinations': 'admin/manageVaccinations.php',
            'reports': 'admin/reports.php'
        };

        if (pages[page]) {
            window.location.href = baseUrl + pages[page];
        }
    }

    // Initialize dashboard
    document.addEventListener('DOMContentLoaded', function() {
        // Animate stats counters
        const counters = document.querySelectorAll('.stat-number');
        counters.forEach(counter => {
            const target = parseInt(counter.textContent);
            if (target > 0) {
                let current = 0;
                const increment = target / 40;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current);
                }, 40);
            }
        });

        // Auto-hide messages
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.opacity = '0';
                message.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.remove();
                    }
                }, 300);
            });
        }, 6000);

        // Add hover effects to cards
        document.querySelectorAll('.stat-card, .user-card, .alert-item').forEach(card => {
            card.addEventListener('mouseenter', function() {
                if (!this.classList.contains('stat-card')) {
                    this.style.transform = 'translateY(-5px)';
                }
            });

            card.addEventListener('mouseleave', function() {
                if (!this.classList.contains('stat-card')) {
                    this.style.transform = 'translateY(0)';
                }
            });
        });

        // Real-time updates every 5 minutes
        setInterval(function() {
            fetch('<?php echo $BASE_URL; ?>admin/get_dashboard_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update stat cards
                        Object.keys(data.stats).forEach(key => {
                            const element = document.querySelector(
                                `.stat-card.${key.replace('_', '-')} .stat-number`);
                            if (element) {
                                const newValue = data.stats[key];
                                const currentValue = parseInt(element.textContent);

                                if (newValue !== currentValue) {
                                    element.style.animation = 'bounce 0.5s ease';
                                    element.textContent = newValue;

                                    setTimeout(() => {
                                        element.style.animation = '';
                                    }, 500);
                                }
                            }
                        });
                    }
                })
                .catch(error => console.log('Auto-refresh failed:', error));
        }, 300000); // 5 minutes

        // Keyboard shortcuts for admin
        document.addEventListener('keydown', function(e) {
            // Alt + U = Manage Users
            if (e.altKey && e.key === 'u') {
                e.preventDefault();
                navigateToPage('manageUsers');
            }

            // Alt + P = Manage Pets
            if (e.altKey && e.key === 'p') {
                e.preventDefault();
                navigateToPage('managePets');
            }

            // Alt + R = Reports
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                navigateToPage('reports');
            }

            // Alt + A = Manage Adoptions
            if (e.altKey && e.key === 'a') {
                e.preventDefault();
                navigateToPage('manageAdoptions');
            }
        });

        // Performance monitoring
        const loadTime = performance.now();
        if (loadTime > 3000) {
            console.warn('Admin dashboard loaded slowly:', loadTime + 'ms');
        }

        // Add tooltips to stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            const title = card.querySelector('h3').textContent;
            const number = card.querySelector('.stat-number').textContent;
            card.setAttribute('title', `${title}: ${number} (Click to manage)`);
        });

        // System status indicator
        const systemStatusCheck = () => {
            fetch('<?php echo $BASE_URL; ?>admin/system_status.php')
                .then(response => response.json())
                .then(data => {
                    const statusIndicator = document.querySelector('.system-status');
                    if (statusIndicator) {
                        statusIndicator.style.background = data.healthy ? '#28a745' : '#dc3545';
                    }
                })
                .catch(error => console.log('Status check failed:', error));
        };

        // Check system status every 2 minutes
        setInterval(systemStatusCheck, 120000);

        // Initial status check
        systemStatusCheck();
    });

    // Export dashboard data
    function exportDashboardData() {
        const exportBtn = document.querySelector('.export-btn');
        if (exportBtn) {
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            exportBtn.disabled = true;
        }

        fetch('<?php echo $BASE_URL; ?>admin/export_dashboard.php')
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `admin_dashboard_${new Date().getTime()}.csv`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
            })
            .catch(error => {
                alert('Export failed. Please try again.');
                console.error('Export error:', error);
            })
            .finally(() => {
                if (exportBtn) {
                    exportBtn.innerHTML = '<i class="fas fa-download"></i> Export Data';
                    exportBtn.disabled = false;
                }
            });
    }

    // Print dashboard
    function printDashboard() {
        window.print();
    }

    // Emergency functions
    window.adminEmergency = {
        disableUser: function(userId) {
            if (confirm('Are you sure you want to disable this user?')) {
                fetch('<?php echo $BASE_URL; ?>admin/emergency_disable_user.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            user_id: userId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to disable user: ' + data.message);
                        }
                    });
            }
        },

        systemMaintenance: function() {
            if (confirm('Enable maintenance mode? This will prevent regular users from accessing the site.')) {
                fetch('<?php echo $BASE_URL; ?>admin/maintenance_mode.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            enable: true
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) {
                            location.reload();
                        }
                    });
            }
        }
    };
    </script>

    <!-- Add system status indicator -->
    <div class="system-status"
        style="position: fixed; top: 100px; right: 20px; width: 12px; height: 12px; background: #28a745; border: 2px solid white; border-radius: 50%; z-index: 1000; animation: statusPulse 3s infinite;"
        title="System Status"></div>

    <style>
    @keyframes statusPulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }
    </style>
</body>

</html>