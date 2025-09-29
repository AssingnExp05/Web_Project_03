<?php
// adopter/dashboard.php - Adopter Dashboard (Restructured Version)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Base URL
$BASE_URL = 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Check if user is logged in and is an adopter
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'adopter') {
    $_SESSION['error_message'] = 'Please login as an adopter to access this page.';
    header('Location: ' . $BASE_URL . 'auth/login.php');
    exit();
}

$adopter_user_id = $_SESSION['user_id'];
$adopter_name = $_SESSION['first_name'] ?? 'User';
$page_title = 'Adopter Dashboard - Pet Adoption Care Guide';

// Initialize variables
$adopter_info = null;
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

try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Get adopter's basic information
    $stmt = $db->prepare("SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as full_name FROM users u WHERE u.user_id = ?");
    $stmt->execute([$adopter_user_id]);
    $adopter_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$adopter_info) {
        throw new Exception("User information not found");
    }

    // Get adoption applications statistics
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ? AND application_status = 'pending'");
    $stmt->execute([$adopter_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $adoption_stats['pending'] = $result ? (int)$result['count'] : 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ? AND application_status = 'approved'");
    $stmt->execute([$adopter_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $adoption_stats['approved'] = $result ? (int)$result['count'] : 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ? AND application_status = 'rejected'");
    $stmt->execute([$adopter_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $adoption_stats['rejected'] = $result ? (int)$result['count'] : 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ?");
    $stmt->execute([$adopter_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $adoption_stats['total_applications'] = $result ? (int)$result['count'] : 0;

    // Get completed adoptions count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoptions WHERE adopter_id = ?");
    $stmt->execute([$adopter_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $completed_adoptions = $result ? (int)$result['count'] : 0;

    // Get recent adoption applications
    $stmt = $db->prepare("
        SELECT aa.*, p.pet_name, p.primary_image, p.age, p.gender, p.adoption_fee,
                pc.category_name, pb.breed_name, s.shelter_name,
                DATEDIFF(CURDATE(), aa.application_date) as days_ago
        FROM adoption_applications aa
        INNER JOIN pets p ON aa.pet_id = p.pet_id
        INNER JOIN pet_categories pc ON p.category_id = pc.category_id
        LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
        INNER JOIN shelters s ON aa.shelter_id = s.shelter_id
        WHERE aa.adopter_id = ?
        ORDER BY aa.application_date DESC
        LIMIT 5
    ");
    $stmt->execute([$adopter_user_id]);
    $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get recommended pets (available pets that might interest the adopter)
    $stmt = $db->prepare("
        SELECT p.*, pc.category_name, pb.breed_name, s.shelter_name,
                (SELECT COUNT(*) FROM adoption_applications aa2 WHERE aa2.pet_id = p.pet_id) as application_count
        FROM pets p
        INNER JOIN pet_categories pc ON p.category_id = pc.category_id
        LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
        INNER JOIN shelters s ON p.shelter_id = s.shelter_id
        WHERE p.status = 'available'
        AND p.pet_id NOT IN (
            SELECT DISTINCT pet_id 
            FROM adoption_applications 
            WHERE adopter_id = ?
        )
        ORDER BY RAND()
        LIMIT 6
    ");
    $stmt->execute([$adopter_user_id]);
    $recommended_pets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get adopted pets (completed adoptions)
    $stmt = $db->prepare("
        SELECT a.*, p.pet_name, p.primary_image, p.age, p.gender,
                pc.category_name, s.shelter_name,
                DATEDIFF(CURDATE(), a.adoption_date) as days_since_adoption
        FROM adoptions a
        INNER JOIN pets p ON a.pet_id = p.pet_id
        INNER JOIN pet_categories pc ON p.category_id = pc.category_id
        INNER JOIN shelters s ON a.shelter_id = s.shelter_id
        WHERE a.adopter_id = ?
        ORDER BY a.adoption_date DESC
        LIMIT 3
    ");
    $stmt->execute([$adopter_user_id]);
    $adopted_pets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get care guides count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM care_guides WHERE is_published = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $care_guides_count = $result ? (int)$result['count'] : 0;

    // Generate reminders for adopted pets
    foreach ($adopted_pets as $pet) {
        if ($pet['days_since_adoption'] > 0 && $pet['days_since_adoption'] % 30 == 0) {
            $upcoming_reminders[] = [
                'type' => 'checkup',
                'message' => "Monthly checkup reminder for " . $pet['pet_name'],
                'pet_name' => $pet['pet_name'],
                'days' => 0
            ];
        }
    }

} catch (Exception $e) {
    error_log("Dashboard database error: " . $e->getMessage());
    $error_message = "Unable to load dashboard data. Please try again later.";
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
        --border-radius: 16px;
        --transition: all 0.3s ease;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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

    /* Welcome Header - Simplified */
    .welcome-header {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 30px;
        border-radius: var(--border-radius);
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .welcome-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .welcome-text h1 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .welcome-text p {
        font-size: 1.1rem;
        opacity: 0.9;
    }

    .user-quick-info {
        display: flex;
        align-items: center;
        gap: 15px;
        background: rgba(255, 255, 255, 0.15);
        padding: 15px 25px;
        border-radius: var(--border-radius);
        backdrop-filter: blur(10px);
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
    }

    /* Statistics Cards - Improved Layout */
    .stats-section {
        margin-bottom: 30px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .stat-card {
        background: white;
        padding: 25px;
        border-radius: var(--border-radius);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: var(--transition);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 20px;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--stat-color, var(--primary-color));
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    .stat-card.pending {
        --stat-color: var(--warning-color);
    }

    .stat-card.approved {
        --stat-color: var(--success-color);
    }

    .stat-card.rejected {
        --stat-color: var(--danger-color);
    }

    .stat-card.adopted {
        --stat-color: var(--info-color);
    }

    .stat-card.guides {
        --stat-color: var(--secondary-color);
    }

    .stat-icon {
        font-size: 2.5rem;
        color: var(--stat-color, var(--primary-color));
        opacity: 0.8;
    }

    .stat-content {
        flex: 1;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--dark-color);
        line-height: 1;
        margin-bottom: 4px;
    }

    .stat-label {
        font-size: 0.9rem;
        color: #666;
        font-weight: 500;
    }

    /* Main Content Layout */
    .dashboard-main {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 30px;
        margin-bottom: 30px;
    }

    /* Content Sections */
    .content-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .content-header {
        padding: 20px 25px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .content-header h3 {
        font-size: 1.3rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .content-body {
        padding: 25px;
    }

    /* Application List Items */
    .application-item {
        display: flex;
        gap: 15px;
        padding: 15px;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        margin-bottom: 15px;
        transition: var(--transition);
        cursor: pointer;
    }

    .application-item:hover {
        border-color: var(--primary-color);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
    }

    .application-pet-image {
        width: 60px;
        height: 60px;
        border-radius: 8px;
        overflow: hidden;
        flex-shrink: 0;
        background: #f0f2f5;
    }

    .application-pet-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .application-info {
        flex: 1;
        min-width: 0;
    }

    .application-pet-name {
        font-weight: 600;
        margin-bottom: 4px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .application-details {
        font-size: 0.85rem;
        color: #666;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        align-self: center;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-approved {
        background: #d4edda;
        color: #155724;
    }

    .status-rejected {
        background: #f8d7da;
        color: #721c24;
    }

    /* Pet Cards Grid - Fixed Image Sizing */
    .pets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 25px;
    }

    .pet-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        transition: var(--transition);
        cursor: pointer;
        display: flex;
        flex-direction: column;
    }

    .pet-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    .pet-card-image {
        width: 100%;
        height: 220px;
        background: #f0f2f5;
        position: relative;
        overflow: hidden;
    }

    .pet-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .pet-card-image .no-image {
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
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .pet-name {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--dark-color);
    }

    .pet-info {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 15px;
        line-height: 1.5;
    }

    .pet-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: auto;
        padding-top: 15px;
        border-top: 1px solid #e9ecef;
    }

    .pet-price {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--success-color);
    }

    /* Adopted Pets Cards - Improved Layout */
    .adopted-pets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
    }

    .adopted-pet-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        position: relative;
        display: flex;
        flex-direction: column;
    }

    .adopted-pet-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--success-color), #4caf50);
    }

    .adopted-pet-image {
        width: 100%;
        height: 250px;
        position: relative;
        overflow: hidden;
        background: #f0f2f5;
    }

    .adopted-pet-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .adopted-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        background: var(--success-color);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .adopted-pet-body {
        padding: 25px;
    }

    .adopted-pet-name {
        font-size: 1.4rem;
        font-weight: 600;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .adopted-pet-info {
        display: grid;
        gap: 10px;
        margin-bottom: 20px;
    }

    .adopted-info-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
        color: #555;
    }

    .adopted-info-item i {
        width: 20px;
        color: var(--primary-color);
    }

    /* Quick Actions Sidebar */
    .quick-actions-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .quick-action-item {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 15px 20px;
        border-radius: 12px;
        text-decoration: none;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .quick-action-item:hover {
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .quick-action-item i {
        font-size: 1.2rem;
        opacity: 0.9;
    }

    /* Buttons */
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: var(--primary-color);
        color: white;
    }

    .btn-primary:hover {
        background: #5a67d8;
        transform: translateY(-2px);
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

    /* Empty States */
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #666;
    }

    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.3;
    }

    .empty-state h4 {
        margin-bottom: 10px;
        color: var(--dark-color);
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .dashboard-main {
            grid-template-columns: 1fr;
        }

        .pets-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .welcome-content {
            flex-direction: column;
            text-align: center;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .pets-grid,
        .adopted-pets-grid {
            grid-template-columns: 1fr;
        }

        .dashboard-container {
            padding: 10px;
        }

        .welcome-header {
            padding: 20px;
        }

        .welcome-text h1 {
            font-size: 1.5rem;
        }
    }

    /* Utility Classes */
    .mt-4 {
        margin-top: 2rem;
    }

    .mb-3 {
        margin-bottom: 1rem;
    }

    .text-center {
        text-align: center;
    }

    /* Loading Animation */
    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    .loading {
        animation: pulse 1.5s ease-in-out infinite;
    }

    /* Smooth Scrolling */
    html {
        scroll-behavior: smooth;
    }
    </style>
</head>

<body>
    <?php include_once __DIR__ . '/../common/navbar_adopter.php'; ?>

    <div class="dashboard-container">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h1>Welcome back, <?php echo htmlspecialchars($adopter_name); ?>!</h1>
                    <p>Find your perfect companion today</p>
                </div>
                <div class="user-quick-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($adopter_name, 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($adopter_info['full_name'] ?? ''); ?>
                        </div>
                        <div style="font-size: 0.85rem; opacity: 0.9;">
                            <?php echo htmlspecialchars($adopter_info['email'] ?? ''); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Section -->
        <section class="stats-section">
            <div class="stats-grid">
                <div class="stat-card pending"
                    onclick="window.location.href='<?php echo $BASE_URL; ?>adopter/myAdoptions.php?filter=pending'">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $adoption_stats['pending']; ?></div>
                        <div class="stat-label">Pending Applications</div>
                    </div>
                </div>

                <div class="stat-card approved"
                    onclick="window.location.href='<?php echo $BASE_URL; ?>adopter/myAdoptions.php?filter=approved'">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $adoption_stats['approved']; ?></div>
                        <div class="stat-label">Approved Applications</div>
                    </div>
                </div>

                <div class="stat-card adopted"
                    onclick="window.location.href='<?php echo $BASE_URL; ?>adopter/myAdoptions.php?filter=adopted'">
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $completed_adoptions; ?></div>
                        <div class="stat-label">Adopted Pets</div>
                    </div>
                </div>

                <div class="stat-card guides"
                    onclick="window.location.href='<?php echo $BASE_URL; ?>adopter/careGuides.php'">
                    <div class="stat-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $care_guides_count; ?></div>
                        <div class="stat-label">Care Guides</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Dashboard Content -->
        <div class="dashboard-main">
            <!-- Recent Applications -->
            <div class="content-card">
                <div class="content-header">
                    <h3><i class="fas fa-file-alt"></i> Recent Applications</h3>
                    <?php if (!empty($recent_applications)): ?>
                    <a href="<?php echo $BASE_URL; ?>adopter/myAdoptions.php" class="btn btn-outline btn-sm">View
                        All</a>
                    <?php endif; ?>
                </div>
                <div class="content-body">
                    <?php if (!empty($recent_applications)): ?>
                    <?php foreach (array_slice($recent_applications, 0, 3) as $app): ?>
                    <div class="application-item"
                        onclick="window.location.href='<?php echo $BASE_URL; ?>adopter/petDetails.php?id=<?php echo $app['pet_id']; ?>'">
                        <div class="application-pet-image">
                            <?php if (!empty($app['primary_image']) && file_exists(__DIR__ . "/../uploads/" . $app['primary_image'])): ?>
                            <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($app['primary_image']); ?>"
                                alt="<?php echo htmlspecialchars($app['pet_name']); ?>">
                            <?php else: ?>
                            <div
                                style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f2f5;">
                                <i class="fas fa-paw" style="font-size: 1.5rem; color: #a0a5b8;"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="application-info">
                            <div class="application-pet-name"><?php echo htmlspecialchars($app['pet_name']); ?></div>
                            <div class="application-details">
                                <span><i class="fas fa-home"></i>
                                    <?php echo htmlspecialchars($app['shelter_name']); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo $app['days_ago']; ?> days ago</span>
                            </div>
                        </div>
                        <div class="status-badge status-<?php echo $app['application_status']; ?>">
                            <?php echo ucfirst($app['application_status']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt empty-state-icon"></i>
                        <h4>No Applications Yet</h4>
                        <p>Start browsing pets to find your perfect companion!</p>
                        <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php" class="btn btn-primary">
                            <i class="fas fa-search"></i> Browse Pets
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Quick Actions -->
                <div class="content-card mb-3">
                    <div class="content-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="content-body">
                        <div class="quick-actions-list">
                            <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php" class="quick-action-item">
                                <i class="fas fa-search"></i>
                                <span>Browse Available Pets</span>
                            </a>
                            <a href="<?php echo $BASE_URL; ?>adopter/myAdoptions.php" class="quick-action-item">
                                <i class="fas fa-heart"></i>
                                <span>My Adoptions</span>
                            </a>
                            <a href="<?php echo $BASE_URL; ?>adopter/careGuides.php" class="quick-action-item">
                                <i class="fas fa-book"></i>
                                <span>Pet Care Guides</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Reminders -->
                <?php if (!empty($upcoming_reminders)): ?>
                <div class="content-card">
                    <div class="content-header">
                        <h3><i class="fas fa-bell"></i> Reminders</h3>
                    </div>
                    <div class="content-body">
                        <?php foreach ($upcoming_reminders as $reminder): ?>
                        <div style="padding: 12px; background: #fff8dc; border-radius: 8px; margin-bottom: 10px;">
                            <i class="fas fa-bell" style="color: #ff9800; margin-right: 8px;"></i>
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
        <section class="mt-4">
            <div class="content-card">
                <div class="content-header">
                    <h3><i class="fas fa-star"></i> Recommended for You</h3>
                    <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php" class="btn btn-outline btn-sm">Browse
                        All</a>
                </div>
                <div class="content-body">
                    <div class="pets-grid">
                        <?php foreach (array_slice($recommended_pets, 0, 3) as $pet): ?>
                        <div class="pet-card"
                            onclick="window.location.href='<?php echo $BASE_URL; ?>adopter/petDetails.php?id=<?php echo $pet['pet_id']; ?>'">
                            <div class="pet-card-image">
                                <?php if (!empty($pet['primary_image']) && file_exists(__DIR__ . "/../uploads/" . $pet['primary_image'])): ?>
                                <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                                    alt="<?php echo htmlspecialchars($pet['pet_name']); ?>">
                                <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-paw"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="pet-card-body">
                                <h4 class="pet-name"><?php echo htmlspecialchars($pet['pet_name']); ?></h4>
                                <div class="pet-info">
                                    <?php echo htmlspecialchars($pet['category_name']); ?>
                                    <?php if (!empty($pet['breed_name'])): ?>
                                    • <?php echo htmlspecialchars($pet['breed_name']); ?>
                                    <?php endif; ?><br>
                                    <?php echo $pet['age']; ?> years • <?php echo ucfirst($pet['gender']); ?><br>
                                    <small><i class="fas fa-home"></i>
                                        <?php echo htmlspecialchars($pet['shelter_name']); ?></small>
                                </div>
                                <div class="pet-footer">
                                    <span
                                        class="pet-price">$<?php echo number_format($pet['adoption_fee'], 2); ?></span>
                                    <button class="btn btn-primary btn-sm">View Details</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Adopted Pets -->
        <?php if (!empty($adopted_pets)): ?>
        <section class="mt-4">
            <div class="content-card">
                <div class="content-header">
                    <h3><i class="fas fa-heart"></i> Your Adopted Pets</h3>
                </div>
                <div class="content-body">
                    <div class="adopted-pets-grid">
                        <?php foreach ($adopted_pets as $pet): ?>
                        <div class="adopted-pet-card">
                            <div class="adopted-pet-image">
                                <?php if (!empty($pet['primary_image']) && file_exists(__DIR__ . "/../uploads/" . $pet['primary_image'])): ?>
                                <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                                    alt="<?php echo htmlspecialchars($pet['pet_name']); ?>">
                                <?php else: ?>
                                <div
                                    style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f2f5;">
                                    <i class="fas fa-paw" style="font-size: 3rem; color: #a0a5b8;"></i>
                                </div>
                                <?php endif; ?>
                                <div class="adopted-badge">
                                    <i class="fas fa-check"></i> Adopted
                                </div>
                            </div>
                            <div class="adopted-pet-body">
                                <h4 class="adopted-pet-name">
                                    <?php echo htmlspecialchars($pet['pet_name']); ?>
                                    <i class="fas fa-heart" style="color: #e74c3c; font-size: 1.1rem;"></i>
                                </h4>
                                <div class="adopted-info-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>Adopted on
                                        <?php echo date('M j, Y', strtotime($pet['adoption_date'])); ?></span>
                                </div>
                                <div class="adopted-info-item">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo htmlspecialchars($pet['category_name']); ?></span>
                                </div>
                                <div class="adopted-info-item">
                                    <i class="fas fa-birthday-cake"></i>
                                    <span><?php echo $pet['age']; ?> years old</span>
                                </div>
                                <div class="adopted-info-item">
                                    <i class="fas fa-home"></i>
                                    <span><?php echo htmlspecialchars($pet['shelter_name']); ?></span>
                                </div>
                            </div>
                            <div style="margin-top: 15px;">
                                <a href="<?php echo $BASE_URL; ?>adopter/careGuides.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-book"></i> View Care Guides
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
    </div>
    </section>
    <?php endif; ?>

    <!-- Empty State for New Users -->
    <?php if (empty($recent_applications) && empty($adopted_pets) && $adoption_stats['total_applications'] == 0): ?>
    <section class="mt-4">
        <div class="content-card">
            <div class="content-body" style="padding: 60px;">
                <div class="empty-state">
                    <i class="fas fa-paw empty-state-icon" style="color: var(--primary-color); font-size: 4rem;"></i>
                    <h2 style="font-size: 1.8rem; margin-bottom: 15px;">Welcome to Pet Adoption!</h2>
                    <p
                        style="font-size: 1.1rem; margin-bottom: 25px; max-width: 500px; margin-left: auto; margin-right: auto;">
                        You haven't started your adoption journey yet. Browse through hundreds of loving pets waiting
                        for their forever homes!
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php" class="btn btn-primary">
                            <i class="fas fa-search"></i> Start Browsing Pets
                        </a>
                        <a href="<?php echo $BASE_URL; ?>adopter/careGuides.php" class="btn btn-outline">
                            <i class="fas fa-book"></i> Learn About Pet Care
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div id="successMessage"
        style="position: fixed; top: 20px; right: 20px; background: var(--success-color); color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 1000; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message']) && !$error_message): ?>
    <div id="errorMessage"
        style="position: fixed; top: 20px; right: 20px; background: var(--danger-color); color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 1000; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <script>
    // Auto-hide messages after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const messages = document.querySelectorAll('#successMessage, #errorMessage');
        messages.forEach(message => {
            if (message) {
                setTimeout(() => {
                    message.style.transition = 'opacity 0.5s ease';
                    message.style.opacity = '0';
                    setTimeout(() => message.remove(), 500);
                }, 5000);
            }
        });

        // Add smooth scroll behavior for all internal links
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

        // Add hover effects to cards
        const cards = document.querySelectorAll('.pet-card, .adopted-pet-card, .stat-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.target.matches('input, textarea, select')) return;

            switch (e.key.toLowerCase()) {
                case 'b':
                    window.location.href = '<?php echo $BASE_URL; ?>adopter/browsePets.php';
                    break;
                case 'm':
                    window.location.href = '<?php echo $BASE_URL; ?>adopter/myAdoptions.php';
                    break;
                case 'c':
                    window.location.href = '<?php echo $BASE_URL; ?>adopter/careGuides.php';
                    break;
            }
        });

        // Image loading optimization
        const images = document.querySelectorAll('img');
        images.forEach(img => {
            img.addEventListener('error', function() {
                const parent = this.parentElement;
                if (parent.classList.contains('pet-card-image') ||
                    parent.classList.contains('adopted-pet-image') ||
                    parent.classList.contains('application-pet-image')) {
                    parent.innerHTML = '<div class="no-image"><i class="fas fa-paw"></i></div>';
                }
            });
        });

        // Add loading state for slow connections
        let loadingTimeout = setTimeout(() => {
            document.body.classList.add('loading');
        }, 1000);

        window.addEventListener('load', function() {
            clearTimeout(loadingTimeout);
            document.body.classList.remove('loading');
        });

        // Stats counter animation
        function animateValue(element, start, end, duration) {
            const range = end - start;
            const increment = end > start ? 1 : -1;
            const stepTime = Math.abs(Math.floor(duration / range));
            let current = start;

            const timer = setInterval(() => {
                current += increment;
                element.textContent = current;
                if (current === end) {
                    clearInterval(timer);
                }
            }, stepTime);
        }

        // Animate statistics on page load
        const statNumbers = document.querySelectorAll('.stat-number');
        statNumbers.forEach(stat => {
            const finalValue = parseInt(stat.textContent);
            stat.textContent = '0';
            animateValue(stat, 0, finalValue, 1000);
        });

        // Refresh page data every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);

        // Print dashboard info
        console.log('Dashboard loaded successfully', {
            adopterId: <?php echo json_encode($adopter_user_id); ?>,
            stats: <?php echo json_encode($adoption_stats); ?>
        });
    });
    </script>
</body>

</html>