<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Check if user ID is provided
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($user_id <= 0) {
    header("Location: manageUsers.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pet_adoption_care_guide";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Fetch user details
$user_query = "SELECT u.*, s.shelter_name, s.license_number, s.capacity
               FROM users u
               LEFT JOIN shelters s ON u.user_id = s.user_id
               WHERE u.user_id = ?";

$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: manageUsers.php");
    exit();
}

// Initialize statistics
$stats = [
    'total_adoptions' => 0,
    'pending_applications' => 0,
    'approved_applications' => 0,
    'rejected_applications' => 0,
    'total_pets' => 0,
    'available_pets' => 0,
    'adopted_pets' => 0,
    'last_activity' => null
];

// Get user-specific statistics
if ($user['user_type'] === 'adopter') {
    // Adopter statistics
    $stats_query = "SELECT 
                    (SELECT COUNT(*) FROM adoptions WHERE adopter_id = ?) as total_adoptions,
                    (SELECT COUNT(*) FROM adoption_applications WHERE adopter_id = ? AND application_status = 'pending') as pending_applications,
                    (SELECT COUNT(*) FROM adoption_applications WHERE adopter_id = ? AND application_status = 'approved') as approved_applications,
                    (SELECT COUNT(*) FROM adoption_applications WHERE adopter_id = ? AND application_status = 'rejected') as rejected_applications";
    
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $adopter_stats = $result->fetch_assoc();
    $stats = array_merge($stats, $adopter_stats);
    $stmt->close();
    
} elseif ($user['user_type'] === 'shelter') {
    // Shelter statistics
    $shelter_query = "SELECT shelter_id FROM shelters WHERE user_id = ?";
    $stmt = $conn->prepare($shelter_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $shelter_data = $result->fetch_assoc();
    $stmt->close();
    
    if ($shelter_data) {
        $shelter_id = $shelter_data['shelter_id'];
        
        $stats_query = "SELECT 
                        (SELECT COUNT(*) FROM pets WHERE shelter_id = ?) as total_pets,
                        (SELECT COUNT(*) FROM pets WHERE shelter_id = ? AND status = 'available') as available_pets,
                        (SELECT COUNT(*) FROM pets WHERE shelter_id = ? AND status = 'adopted') as adopted_pets,
                        (SELECT COUNT(*) FROM adoption_applications WHERE shelter_id = ? AND application_status = 'pending') as pending_applications";
        
        $stmt = $conn->prepare($stats_query);
        $stmt->bind_param("iiii", $shelter_id, $shelter_id, $shelter_id, $shelter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $shelter_stats = $result->fetch_assoc();
        $stats = array_merge($stats, $shelter_stats);
        $stmt->close();
    }
}

// Get recent activity
$recent_activities = [];
if ($user['user_type'] === 'adopter') {
    // Get recent adoptions
    $activity_query = "SELECT 
                       'adoption' as activity_type,
                       p.pet_name,
                       pc.category_name,
                       ad.adoption_date as activity_date,
                       s.shelter_name
                       FROM adoptions ad
                       JOIN pets p ON ad.pet_id = p.pet_id
                       JOIN pet_categories pc ON p.category_id = pc.category_id
                       JOIN shelters s ON ad.shelter_id = s.shelter_id
                       WHERE ad.adopter_id = ?
                       ORDER BY ad.adoption_date DESC
                       LIMIT 10";
    
    $stmt = $conn->prepare($activity_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
    $stmt->close();
    
    // Get recent applications
    $app_query = "SELECT 
                  'application' as activity_type,
                  p.pet_name,
                  pc.category_name,
                  aa.application_date as activity_date,
                  aa.application_status,
                  s.shelter_name
                  FROM adoption_applications aa
                  JOIN pets p ON aa.pet_id = p.pet_id
                  JOIN pet_categories pc ON p.category_id = pc.category_id
                  JOIN shelters s ON aa.shelter_id = s.shelter_id
                  WHERE aa.adopter_id = ?
                  ORDER BY aa.application_date DESC
                  LIMIT 10";
    
    $stmt = $conn->prepare($app_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
    $stmt->close();
    
} elseif ($user['user_type'] === 'shelter' && isset($shelter_id)) {
    // Get recent pets added
    $activity_query = "SELECT 
                       'pet_added' as activity_type,
                       p.pet_name,
                       pc.category_name,
                       p.created_at as activity_date,
                       p.status
                       FROM pets p
                       JOIN pet_categories pc ON p.category_id = pc.category_id
                       WHERE p.shelter_id = ?
                       ORDER BY p.created_at DESC
                       LIMIT 10";
    
    $stmt = $conn->prepare($activity_query);
    $stmt->bind_param("i", $shelter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
    $stmt->close();
    
    // Get recent adoptions from shelter
    $adopt_query = "SELECT 
                    'shelter_adoption' as activity_type,
                    p.pet_name,
                    pc.category_name,
                    ad.adoption_date as activity_date,
                    CONCAT(u.first_name, ' ', u.last_name) as adopter_name
                    FROM adoptions ad
                    JOIN pets p ON ad.pet_id = p.pet_id
                    JOIN pet_categories pc ON p.category_id = pc.category_id
                    JOIN users u ON ad.adopter_id = u.user_id
                    WHERE ad.shelter_id = ?
                    ORDER BY ad.adoption_date DESC
                    LIMIT 10";
    
    $stmt = $conn->prepare($adopt_query);
    $stmt->bind_param("i", $shelter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
    $stmt->close();
}

// Sort activities by date
usort($recent_activities, function($a, $b) {
    return strtotime($b['activity_date']) - strtotime($a['activity_date']);
});

// Take only the most recent 10
$recent_activities = array_slice($recent_activities, 0, 10);

// Check dependencies for delete warning
$dependencies = [];
if ($user['user_type'] === 'adopter') {
    // Check adoptions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM adoptions WHERE adopter_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $dep_result = $result->fetch_assoc();
    if ($dep_result['count'] > 0) {
        $dependencies[] = $dep_result['count'] . " adoption(s)";
    }
    $stmt->close();
    
    // Check applications
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $dep_result = $result->fetch_assoc();
    if ($dep_result['count'] > 0) {
        $dependencies[] = $dep_result['count'] . " application(s)";
    }
    $stmt->close();
    
} elseif ($user['user_type'] === 'shelter') {
    // Check pets
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pets p JOIN shelters s ON p.shelter_id = s.shelter_id WHERE s.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $dep_result = $result->fetch_assoc();
    if ($dep_result['count'] > 0) {
        $dependencies[] = $dep_result['count'] . " pet(s)";
    }
    $stmt->close();
}

$has_dependencies = !empty($dependencies);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f0f2f5;
        min-height: 100vh;
        padding-top: 70px;
    }

    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: #6c757d;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }

    .back-button:hover {
        background: #5a6268;
        transform: translateX(-5px);
    }

    /* User Header */
    .user-header {
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 30px;
        align-items: center;
    }

    .user-avatar-large {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        font-weight: bold;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
    }

    .user-header-info h1 {
        color: #2c3e50;
        margin-bottom: 10px;
        font-size: 2rem;
    }

    .user-header-info p {
        color: #6c757d;
        margin: 5px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .user-badges {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }

    .badge {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .badge-admin {
        background: #f093fb;
        color: #7b2cbf;
    }

    .badge-shelter {
        background: #4facfe;
        color: #0066cc;
    }

    .badge-adopter {
        background: #fa709a;
        color: #c41e3a;
    }

    .badge-active {
        background: #d4edda;
        color: #155724;
    }

    .badge-inactive {
        background: #f8d7da;
        color: #721c24;
    }

    /* Actions */
    .header-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        text-align: center;
        justify-content: center;
    }

    .btn-primary {
        background: #3498db;
        color: white;
    }

    .btn-primary:hover {
        background: #2980b9;
        transform: translateY(-2px);
    }

    .btn-warning {
        background: #f39c12;
        color: white;
    }

    .btn-warning:hover {
        background: #d68910;
    }

    .btn-danger {
        background: #dc3545;
        color: white;
    }

    .btn-danger:hover {
        background: #c0392b;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .btn-success:hover {
        background: #218838;
    }

    /* Content Grid */
    .content-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 25px;
        margin-bottom: 25px;
    }

    /* Info Card */
    .info-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .info-card h3 {
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f8f9fa;
    }

    .info-card h3 i {
        color: #3498db;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f8f9fa;
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        font-weight: 600;
        color: #495057;
    }

    .info-value {
        color: #6c757d;
        text-align: right;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        text-align: center;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        border-top: 4px solid var(--accent-color, #3498db);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        font-size: 2.5rem;
        color: var(--accent-color, #3498db);
        margin-bottom: 15px;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: #2c3e50;
        line-height: 1;
        margin-bottom: 5px;
    }

    .stat-card h4 {
        color: #6c757d;
        font-size: 0.9rem;
        font-weight: 600;
        margin: 0;
    }

    /* Activity Timeline */
    .activity-section {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .activity-timeline {
        position: relative;
        padding-left: 30px;
    }

    .activity-timeline::before {
        content: '';
        position: absolute;
        left: 9px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }

    .activity-item {
        position: relative;
        padding-bottom: 25px;
    }

    .activity-item:last-child {
        padding-bottom: 0;
    }

    .activity-icon {
        position: absolute;
        left: -21px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: white;
        border: 2px solid var(--activity-color, #3498db);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        color: var(--activity-color, #3498db);
    }

    .activity-content {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-left: 10px;
    }

    .activity-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 8px;
    }

    .activity-title {
        font-weight: 600;
        color: #2c3e50;
    }

    .activity-date {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .activity-details {
        font-size: 0.9rem;
        color: #6c757d;
    }

    /* Dependencies Warning */
    .warning-box {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .warning-box i {
        font-size: 2rem;
        color: #856404;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.3;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .container {
            padding: 10px;
        }

        .user-header {
            grid-template-columns: 1fr;
            text-align: center;
        }

        .user-avatar-large {
            margin: 0 auto;
        }

        .header-actions {
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
        }

        .content-grid {
            grid-template-columns: 1fr;
        }

        .info-item {
            flex-direction: column;
            text-align: left;
            gap: 5px;
        }

        .info-value {
            text-align: left;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    /* Toast Messages */
    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 15px 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        display: none;
        align-items: center;
        gap: 12px;
        min-width: 300px;
        animation: slideInRight 0.3s ease;
        z-index: 1000;
    }

    .toast.show {
        display: flex;
    }

    .toast.success {
        border-left: 4px solid #28a745;
    }

    .toast.error {
        border-left: 4px solid #dc3545;
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
    <?php include '../common/navbar_admin.php'; ?>

    <div class="container">
        <a href="manageUsers.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Users
        </a>

        <!-- User Header -->
        <div class="user-header">
            <div class="user-avatar-large">
                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
            </div>
            <div class="user-header-info">
                <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                <p><i class="fas fa-at"></i> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                <?php if ($user['phone']): ?>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></p>
                <?php endif; ?>
                <div class="user-badges">
                    <span class="badge badge-<?php echo $user['user_type']; ?>">
                        <?php echo ucfirst($user['user_type']); ?>
                    </span>
                    <span class="badge badge-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
            <div class="header-actions">
                <button onclick="editUser(<?php echo $user['user_id']; ?>)" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit User
                </button>
                <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                <?php if ($user['is_active']): ?>
                <button onclick="toggleUserStatus(<?php echo $user['user_id']; ?>, 0)" class="btn btn-primary">
                    <i class="fas fa-ban"></i> Deactivate
                </button>
                <?php else: ?>
                <button onclick="toggleUserStatus(<?php echo $user['user_id']; ?>, 1)" class="btn btn-success">
                    <i class="fas fa-check"></i> Activate
                </button>
                <?php endif; ?>
                <button onclick="deleteUser(<?php echo $user['user_id']; ?>)" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete User
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dependencies Warning -->
        <?php if ($has_dependencies): ?>
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Warning:</strong> This user cannot be deleted because they have associated records:
                <?php echo implode(', ', $dependencies); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <?php if ($user['user_type'] === 'adopter'): ?>
        <div class="stats-grid">
            <div class="stat-card" style="--accent-color: #28a745;">
                <i class="fas fa-heart stat-icon"></i>
                <div class="stat-number"><?php echo $stats['total_adoptions']; ?></div>
                <h4>Total Adoptions</h4>
            </div>
            <div class="stat-card" style="--accent-color: #ffc107;">
                <i class="fas fa-hourglass-half stat-icon"></i>
                <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                <h4>Pending Applications</h4>
            </div>
            <div class="stat-card" style="--accent-color: #17a2b8;">
                <i class="fas fa-check-circle stat-icon"></i>
                <div class="stat-number"><?php echo $stats['approved_applications']; ?></div>
                <h4>Approved Applications</h4>
            </div>
            <div class="stat-card" style="--accent-color: #dc3545;">
                <i class="fas fa-times-circle stat-icon"></i>
                <div class="stat-number"><?php echo $stats['rejected_applications']; ?></div>
                <h4>Rejected Applications</h4>
            </div>
        </div>
        <?php elseif ($user['user_type'] === 'shelter'): ?>
        <div class="stats-grid">
            <div class="stat-card" style="--accent-color: #6f42c1;">
                <i class="fas fa-paw stat-icon"></i>
                <div class="stat-number"><?php echo $stats['total_pets']; ?></div>
                <h4>Total Pets</h4>
            </div>
            <div class="stat-card" style="--accent-color: #28a745;">
                <i class="fas fa-home stat-icon"></i>
                <div class="stat-number"><?php echo $stats['available_pets']; ?></div>
                <h4>Available Pets</h4>
            </div>
            <div class="stat-card" style="--accent-color: #e83e8c;">
                <i class="fas fa-heart stat-icon"></i>
                <div class="stat-number"><?php echo $stats['adopted_pets']; ?></div>
                <h4>Adopted Pets</h4>
            </div>
            <div class="stat-card" style="--accent-color: #ffc107;">
                <i class="fas fa-clipboard-list stat-icon"></i>
                <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                <h4>Pending Applications</h4>
            </div>
        </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Basic Information -->
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                <div class="info-item">
                    <span class="info-label">User ID</span>
                    <span class="info-value">#<?php echo $user['user_id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Username</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Address</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['address'] ?: 'Not provided'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Joined Date</span>
                    <span class="info-value"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>

            <!-- Type-specific Information -->
            <?php if ($user['user_type'] === 'shelter'): ?>
            <div class="info-card">
                <h3><i class="fas fa-home"></i> Shelter Information</h3>
                <div class="info-item">
                    <span class="info-label">Shelter Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['shelter_name'] ?: 'Not set'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">License Number</span>
                    <span
                        class="info-value"><?php echo htmlspecialchars($user['license_number'] ?: 'Not provided'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Capacity</span>
                    <span class="info-value"><?php echo $user['capacity'] ?: '0'; ?> pets</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Current Occupancy</span>
                    <span class="info-value"><?php echo $stats['total_pets']; ?> pets
                        (<?php echo $stats['available_pets']; ?> available)</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="activity-section">
            <h3 style="margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-clock" style="color: #3498db;"></i> Recent Activity
            </h3>
            <?php if (!empty($recent_activities)): ?>
            <div class="activity-timeline">
                <?php foreach ($recent_activities as $activity): ?>
                <?php
                        $activity_color = '#3498db';
                        $activity_icon = 'fa-circle';
                        $activity_text = '';
                        
                        switch ($activity['activity_type']) {
                            case 'adoption':
                                $activity_color = '#28a745';
                                $activity_icon = 'fa-heart';
                                $activity_text = "Adopted <strong>{$activity['pet_name']}</strong> ({$activity['category_name']}) from {$activity['shelter_name']}";
                                break;
                            case 'application':
                                $status_colors = [
                                    'pending' => '#ffc107',
                                    'approved' => '#28a745',
                                    'rejected' => '#dc3545'
                                ];
                                $activity_color = $status_colors[$activity['application_status']] ?? '#3498db';
                                $activity_icon = 'fa-clipboard-list';
                                $activity_text = "Applied for <strong>{$activity['pet_name']}</strong> ({$activity['category_name']}) - Status: " . ucfirst($activity['application_status']);
                                break;
                            case 'pet_added':
                                $activity_color = '#17a2b8';
                                $activity_icon = 'fa-plus';
                                $activity_text = "Added <strong>{$activity['pet_name']}</strong> ({$activity['category_name']}) - Status: " . ucfirst($activity['status']);
                                break;
                            case 'shelter_adoption':
                                $activity_color = '#e83e8c';
                                $activity_icon = 'fa-handshake';
                                $activity_text = "<strong>{$activity['pet_name']}</strong> ({$activity['category_name']}) adopted by {$activity['adopter_name']}";
                                break;
                        }
                        ?>
                <div class="activity-item">
                    <div class="activity-icon" style="--activity-color: <?php echo $activity_color; ?>">
                        <i class="fas <?php echo $activity_icon; ?>"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-header">
                            <div class="activity-title"><?php echo $activity_text; ?></div>
                            <div class="activity-date">
                                <?php echo date('M j, Y', strtotime($activity['activity_date'])); ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No recent activity to display</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i class="toast-icon"></i>
        <div>
            <strong id="toast-title"></strong>
            <p id="toast-message" style="margin: 0; font-size: 0.875rem;"></p>
        </div>
    </div>

    <script>
    function showToast(type, title, message) {
        const toast = document.getElementById('toast');
        const toastIcon = toast.querySelector('.toast-icon');

        toast.className = 'toast ' + type;
        toastIcon.className = 'toast-icon fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle');
        document.getElementById('toast-title').textContent = title;
        document.getElementById('toast-message').textContent = message;

        toast.classList.add('show');

        setTimeout(() => {
            toast.classList.remove('show');
        }, 5000);
    }

    function editUser(userId) {
        window.location.href = `manageUsers.php?action=edit&id=${userId}`;
    }

    function toggleUserStatus(userId, status) {
        const action = status ? 'activate' : 'deactivate';
        if (confirm(`Are you sure you want to ${action} this user?`)) {
            fetch('manageUsers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggle_status&user_id=${userId}&ajax=1`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', 'Success', data.message);
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showToast('error', 'Error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'Error', 'An error occurred while updating user status');
                });
        }
    }

    function deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            fetch('manageUsers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&user_id=${userId}&ajax=1`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', 'Success', data.message);
                        setTimeout(() => {
                            window.location.href = 'manageUsers.php';
                        }, 1500);
                    } else {
                        showToast('error', 'Error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'Error', 'An error occurred while deleting user');
                });
        }
    }

    // Add smooth scroll behavior
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

    // Add loading state to buttons
    document.querySelectorAll('button').forEach(button => {
        button.addEventListener('click', function() {
            if (this.onclick) {
                const originalContent = this.innerHTML;
                const originalDisabled = this.disabled;

                // Don't add loading to confirm/cancel dialog buttons
                if (!this.onclick.toString().includes('confirm')) {
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

                    // Reset after action completes
                    setTimeout(() => {
                        this.innerHTML = originalContent;
                        this.disabled = originalDisabled;
                    }, 3000);
                }
            }
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Press 'b' to go back
        if (e.key === 'b' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
            const activeElement = document.activeElement;
            if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
                window.location.href = 'manageUsers.php';
            }
        }

        // Press 'e' to edit
        if (e.key === 'e' && !e.ctrlKey && !e.altKey && !e.shiftKey) {
            const activeElement = document.activeElement;
            if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
                editUser(<?php echo $user['user_id']; ?>);
            }
        }
    });

    // Add hover effects to stat cards
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px) scale(1.02)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-5px) scale(1)';
        });
    });

    // Initialize tooltips
    function initTooltips() {
        const tooltipTriggers = document.querySelectorAll('[data-tooltip]');

        tooltipTriggers.forEach(trigger => {
            trigger.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.dataset.tooltip;
                tooltip.style.cssText = `
                        position: absolute;
                        background: #333;
                        color: white;
                        padding: 5px 10px;
                        border-radius: 4px;
                        font-size: 0.875rem;
                        z-index: 1000;
                        pointer-events: none;
                        opacity: 0;
                        transition: opacity 0.3s;
                    `;

                document.body.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';

                setTimeout(() => tooltip.style.opacity = '1', 10);

                this.addEventListener('mouseleave', function() {
                    tooltip.style.opacity = '0';
                    setTimeout(() => tooltip.remove(), 300);
                }, {
                    once: true
                });
            });
        });
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        initTooltips();

        // Add fade-in animation to cards
        const cards = document.querySelectorAll('.info-card, .stat-card, .activity-section');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';

            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });

    // Add print functionality
    function printUserDetails() {
        window.print();
    }

    // Add export functionality
    function exportUserDetails() {
        const userData = {
            name: '<?php echo addslashes($user['first_name'] . ' ' . $user['last_name']); ?>',
            username: '<?php echo addslashes($user['username']); ?>',
            email: '<?php echo addslashes($user['email']); ?>',
            userType: '<?php echo $user['user_type']; ?>',
            status: '<?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>',
            joinedDate: '<?php echo $user['created_at']; ?>'
        };

        const dataStr = JSON.stringify(userData, null, 2);
        const dataUri = 'data:application/json;charset=utf-8,' + encodeURIComponent(dataStr);

        const exportFileDefaultName = 'user_' + userData.username + '_details.json';

        const linkElement = document.createElement('a');
        linkElement.setAttribute('href', dataUri);
        linkElement.setAttribute('download', exportFileDefaultName);
        linkElement.click();

        showToast('success', 'Success', 'User details exported successfully');
    }

    // Check for URL parameters for actions
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('message')) {
        const message = urlParams.get('message');
        const type = urlParams.get('type') || 'info';
        showToast(type, type.charAt(0).toUpperCase() + type.slice(1), message);

        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname +
            '?id=<?php echo $user['user_id']; ?>');
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
            @keyframes slideInRight {
                from { opacity: 0; transform: translateX(100%); }
                to { opacity: 1; transform: translateX(0); }
            }
            
            @keyframes slideOutRight {
                from { opacity: 1; transform: translateX(0); }
                to { opacity: 0; transform: translateX(100%); }
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            .tooltip {
                animation: fadeIn 0.3s ease;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @media print {
                .btn, .back-button, .header-actions, nav {
                    display: none !important;
                }
                
                .container {
                    max-width: 100%;
                    padding: 0;
                }
                
                .info-card, .stat-card {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }
                
                body {
                    padding-top: 0;
                }
            }
        `;
    document.head.appendChild(style);

    // Auto-refresh data every 60 seconds
    let autoRefreshInterval = setInterval(() => {
        fetch(`getUserDetails.php?id=<?php echo $user['user_id']; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update statistics if they've changed
                    updateStatistics(data.user.stats);
                }
            })
            .catch(error => {
                console.error('Auto-refresh error:', error);
            });
    }, 60000);

    // Clear interval when page is hidden
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(autoRefreshInterval);
        } else {
            // Restart auto-refresh when page becomes visible again
            autoRefreshInterval = setInterval(() => {
                // Same refresh logic
            }, 60000);
        }
    });

    function updateStatistics(newStats) {
        // Update stat cards with new values
        document.querySelectorAll('.stat-number').forEach(element => {
            const statType = element.parentElement.querySelector('h4').textContent;
            let newValue = 0;

            switch (statType) {
                case 'Total Adoptions':
                    newValue = newStats.total_adoptions || 0;
                    break;
                case 'Pending Applications':
                    newValue = newStats.pending_applications || 0;
                    break;
                case 'Approved Applications':
                    newValue = newStats.approved_applications || 0;
                    break;
                case 'Rejected Applications':
                    newValue = newStats.rejected_applications || 0;
                    break;
                case 'Total Pets':
                    newValue = newStats.total_pets || 0;
                    break;
                case 'Available Pets':
                    newValue = newStats.available_pets || 0;
                    break;
                case 'Adopted Pets':
                    newValue = newStats.adopted_pets || 0;
                    break;
            }

            if (parseInt(element.textContent) !== newValue) {
                element.textContent = newValue;
                element.parentElement.style.animation = 'pulse 0.5s ease';
                setTimeout(() => {
                    element.parentElement.style.animation = '';
                }, 500);
            }
        });
    }
    </script>
</body>

</html>

<?php
// Close database connection
$conn->close();
?>