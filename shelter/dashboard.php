<?php
// shelter/dashboard.php - Shelter Dashboard Page (Error-Free Version)

// Start session
session_start();

// Base URL
$BASE_URL = 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'shelter') {
    $_SESSION['error_message'] = 'Please login as a shelter to access this page.';
    header('Location: ' . $BASE_URL . 'auth/login.php');
    exit();
}

// Get user information safely
$user_id = $_SESSION['user_id'];
$user_first_name = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'Shelter User';
$page_title = 'Shelter Dashboard - Pet Adoption Care Guide';

// Initialize all variables with default values
$shelter_info = array(
    'shelter_name' => 'Your Shelter',
    'shelter_id' => 0
);

$stats = array(
    'total_pets' => 0,
    'available_pets' => 0,
    'adopted_pets' => 0,
    'pending_requests' => 0
);

$recent_pets = array();
$recent_requests = array();

// Try database connection
$db_connected = false;
try {
    if (file_exists(__DIR__ . '/../config/db.php')) {
        require_once __DIR__ . '/../config/db.php';
        
        if (function_exists('getDB')) {
            $db = getDB();
            if ($db) {
                $db_connected = true;
                
                // Get shelter information
                try {
                    $stmt = $db->prepare("SELECT s.*, u.first_name, u.last_name FROM shelters s JOIN users u ON s.user_id = u.user_id WHERE s.user_id = ?");
                    $stmt->execute(array($user_id));
                    $shelter_result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($shelter_result) {
                        $shelter_info = $shelter_result;
                    }
                } catch (Exception $e) {
                    // Shelter table might not exist yet
                }
                
                // Get basic statistics if tables exist
                $shelter_id = isset($shelter_info['shelter_id']) ? $shelter_info['shelter_id'] : 0;
                
                if ($shelter_id > 0) {
                    // Try to get pet statistics
                    try {
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ?");
                        $stmt->execute(array($shelter_id));
                        $result = $stmt->fetch();
                        if ($result) {
                            $stats['total_pets'] = (int)$result['count'];
                        }
                    } catch (Exception $e) {
                        // Pets table might not exist
                    }
                    
                    try {
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'available'");
                        $stmt->execute(array($shelter_id));
                        $result = $stmt->fetch();
                        if ($result) {
                            $stats['available_pets'] = (int)$result['count'];
                        }
                    } catch (Exception $e) {
                        // Handle error silently
                    }
                    
                    try {
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'adopted'");
                        $stmt->execute(array($shelter_id));
                        $result = $stmt->fetch();
                        if ($result) {
                            $stats['adopted_pets'] = (int)$result['count'];
                        }
                    } catch (Exception $e) {
                        // Handle error silently
                    }
                    
                    // Get recent pets
                    try {
                        $stmt = $db->prepare("SELECT pet_id, pet_name, status, age, gender, created_at FROM pets WHERE shelter_id = ? ORDER BY created_at DESC LIMIT 5");
                        $stmt->execute(array($shelter_id));
                        $recent_pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (!$recent_pets) {
                            $recent_pets = array();
                        }
                    } catch (Exception $e) {
                        $recent_pets = array();
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    // Database connection failed, continue with defaults
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

    /* Navigation Bar */
    .navbar {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        padding: 1rem 0;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .nav-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .logo {
        color: white;
        font-size: 1.6rem;
        font-weight: 700;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .logo:hover {
        color: #ffd700;
        text-decoration: none;
    }

    .nav-links {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .nav-link {
        color: white;
        text-decoration: none;
        padding: 12px 20px;
        border-radius: 25px;
        transition: all 0.3s ease;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nav-link:hover {
        background: rgba(255, 255, 255, 0.15);
        text-decoration: none;
        color: white;
        transform: translateY(-2px);
    }

    .nav-link.active {
        background: rgba(255, 255, 255, 0.25);
        color: #ffd700;
        font-weight: 600;
    }

    .user-info {
        color: white;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-left: 20px;
    }

    .user-avatar {
        width: 42px;
        height: 42px;
        background: #ffd700;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #28a745;
        font-size: 1.1rem;
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
    }

    .header h1 {
        font-size: 2.5rem;
        margin-bottom: 10px;
        font-weight: 700;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    .header p {
        font-size: 1.2rem;
        margin-bottom: 20px;
        opacity: 0.9;
    }

    .shelter-name {
        background: rgba(255, 255, 255, 0.2);
        padding: 8px 20px;
        border-radius: 25px;
        display: inline-block;
        font-weight: 600;
        margin-top: 10px;
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
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

    .stat-card.total {
        --color: #28a745;
    }

    .stat-card.available {
        --color: #007bff;
    }

    .stat-card.adopted {
        --color: #6f42c1;
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
        color: #28a745;
        font-size: 1.3rem;
    }

    .section-link {
        color: #28a745;
        text-decoration: none;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: color 0.3s ease;
    }

    .section-link:hover {
        color: #20c997;
        text-decoration: none;
    }

    .section-content {
        padding: 30px;
    }

    /* Pet Cards */
    .pet-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
    }

    .pet-card {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 20px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .pet-card:hover {
        background: white;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        border-color: #28a745;
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
    }

    .pet-details h4 {
        color: #2c3e50;
        margin-bottom: 5px;
        font-size: 1.1rem;
    }

    .pet-meta {
        color: #666;
        font-size: 0.9rem;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        margin-top: 8px;
    }

    .status-available {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
    }

    .status-adopted {
        background: rgba(111, 66, 193, 0.2);
        color: #6f42c1;
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

    /* Quick Actions */
    .quick-actions {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
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
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        background: #28a745;
    }

    .action-btn:hover {
        transform: scale(1.1);
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
            font-size: 2rem;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .pet-grid {
            grid-template-columns: 1fr;
        }

        .nav-links {
            display: none;
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

        <!-- Statistics Cards -->
        <div class="stats-grid animate">
            <div class="stat-card total" onclick="navigateToPage('viewPets')">
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

            <div class="stat-card available" onclick="navigateToPage('viewPets')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Available for Adoption</h3>
                        <div class="stat-number"><?php echo $stats['available_pets']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card adopted" onclick="navigateToPage('viewPets')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Successfully Adopted</h3>
                        <div class="stat-number"><?php echo $stats['adopted_pets']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pending" onclick="navigateToPage('adoptionRequests')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Pending Requests</h3>
                        <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
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
                        configured properly.
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
                    <div class="pet-card">
                        <div class="pet-info">
                            <div class="pet-image">
                                <?php echo strtoupper(substr($pet['pet_name'], 0, 1)); ?>
                            </div>
                            <div class="pet-details">
                                <h4><?php echo htmlspecialchars($pet['pet_name']); ?></h4>
                                <div class="pet-meta">
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

        <!-- Getting Started Section -->
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-rocket"></i>
                    Getting Started
                </h2>
            </div>
            <div class="section-content">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
                    <div
                        style="padding: 20px; background: #e8f5e8; border-radius: 15px; border-left: 4px solid #28a745;">
                        <h4 style="color: #28a745; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-plus-circle"></i>
                            Add Your First Pet
                        </h4>
                        <p style="color: #666; margin-bottom: 15px;">
                            Start by adding pets to your shelter database. Include photos, descriptions, and medical
                            information.
                        </p>
                        <a href="<?php echo $BASE_URL; ?>shelter/addPet.php" class="btn btn-primary"
                            style="font-size: 0.9rem; padding: 8px 20px;">
                            Get Started
                        </a>
                    </div>

                    <div
                        style="padding: 20px; background: #e3f2fd; border-radius: 15px; border-left: 4px solid #2196f3;">
                        <h4 style="color: #2196f3; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-heart"></i>
                            Manage Adoptions
                        </h4>
                        <p style="color: #666; margin-bottom: 15px;">
                            Review and process adoption applications from potential pet parents.
                        </p>
                        <a href="<?php echo $BASE_URL; ?>shelter/adoptionRequests.php" class="btn"
                            style="background: #2196f3; color: white; font-size: 0.9rem; padding: 8px 20px;">
                            View Requests
                        </a>
                    </div>

                    <div
                        style="padding: 20px; background: #fff3e0; border-radius: 15px; border-left: 4px solid #ff9800;">
                        <h4 style="color: #ff9800; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-syringe"></i>
                            Track Health Records
                        </h4>
                        <p style="color: #666; margin-bottom: 15px;">
                            Keep track of vaccinations, medical treatments, and health records for all pets.
                        </p>
                        <a href="<?php echo $BASE_URL; ?>shelter/vaccinationTracker.php" class="btn"
                            style="background: #ff9800; color: white; font-size: 0.9rem; padding: 8px 20px;">
                            Health Tracker
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <button class="action-btn" onclick="navigateToPage('addPet')" title="Add New Pet">
            <i class="fas fa-plus"></i>
        </button>
    </div>

    <!-- Display Messages -->
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
    // Navigation function
    function navigateToPage(page) {
        const baseUrl = '<?php echo $BASE_URL; ?>';
        const pages = {
            'addPet': 'shelter/addPet.php',
            'viewPets': 'shelter/viewPets.php',
            'adoptionRequests': 'shelter/adoptionRequests.php',
            'vaccinationTracker': 'shelter/vaccinationTracker.php'
        };

        if (pages[page]) {
            window.location.href = baseUrl + pages[page];
        }
    }

    // Initialize dashboard
    document.addEventListener('DOMContentLoaded', function() {
        // Animate counters
        const counters = document.querySelectorAll('.stat-number');
        counters.forEach(counter => {
            const target = parseInt(counter.textContent);
            if (target > 0) {
                let current = 0;
                const increment = target / 30;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current);
                }, 50);
            }
        });

        // Auto-hide messages
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.opacity = '0';
                message.style.transform = 'translateX(100%)';
                setTimeout(() => message.remove(), 300);
            });
        }, 5000);

        // Add hover effects
        document.querySelectorAll('.pet-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
    });
    </script>
</body>

</html>