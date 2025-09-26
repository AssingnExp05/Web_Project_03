<?php
// admin/profile.php - Admin Profile Management Page
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
$page_title = 'Admin Profile - Dashboard';

// Initialize variables
$user_data = null;
$activity_logs = [];
$user_stats = [
    'total_users' => 0,
    'total_pets' => 0,
    'total_adoptions' => 0,
    'total_shelters' => 0,
    'pending_adoptions' => 0,
    'this_month_users' => 0,
    'overdue_vaccinations' => 0
];
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once __DIR__ . '/../config/db.php';
        $db = getDB();
        
        if ($db) {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'update_profile':
                        $first_name = trim($_POST['first_name'] ?? '');
                        $last_name = trim($_POST['last_name'] ?? '');
                        $email = trim($_POST['email'] ?? '');
                        $phone = trim($_POST['phone'] ?? '');
                        $address = trim($_POST['address'] ?? '');
                        
                        // Validate required fields
                        if (empty($first_name) || empty($last_name) || empty($email)) {
                            $error_message = 'Please fill in all required fields.';
                        } else {
                            // Check if email is already taken by another user
                            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                            $stmt->execute([$email, $user_id]);
                            
                            if ($stmt->fetch()) {
                                $error_message = 'Email address is already taken by another user.';
                            } else {
                                // Update profile
                                $stmt = $db->prepare("
                                    UPDATE users 
                                    SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?
                                    WHERE user_id = ?
                                ");
                                
                                if ($stmt->execute([$first_name, $last_name, $email, $phone, $address, $user_id])) {
                                    $success_message = 'Profile updated successfully!';
                                    // Update session email if changed
                                    $_SESSION['user_email'] = $email;
                                } else {
                                    $error_message = 'Failed to update profile. Please try again.';
                                }
                            }
                        }
                        break;
                        
                    case 'change_password':
                        $current_password = $_POST['current_password'] ?? '';
                        $new_password = $_POST['new_password'] ?? '';
                        $confirm_password = $_POST['confirm_password'] ?? '';
                        
                        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                            $error_message = 'Please fill in all password fields.';
                        } elseif ($new_password !== $confirm_password) {
                            $error_message = 'New password and confirmation do not match.';
                        } elseif (strlen($new_password) < 6) {
                            $error_message = 'New password must be at least 6 characters long.';
                        } else {
                            // Verify current password
                            $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            $stored_hash = $stmt->fetchColumn();
                            
                            if (!$stored_hash || !password_verify($current_password, $stored_hash)) {
                                $error_message = 'Current password is incorrect.';
                            } else {
                                // Update password
                                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                                
                                if ($stmt->execute([$new_hash, $user_id])) {
                                    $success_message = 'Password changed successfully!';
                                } else {
                                    $error_message = 'Failed to change password. Please try again.';
                                }
                            }
                        }
                        break;
                }
            }
        } else {
            $error_message = 'Database connection failed.';
        }
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        $error_message = 'An error occurred. Please try again.';
    }
}

// Fetch user data and statistics
try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if ($db) {
        // Get user profile data
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_data) {
            header('Location: ' . $BASE_URL . 'auth/logout.php');
            exit();
        }
        
        // Get user statistics
        try {
            // Total users
            $stmt = $db->prepare("SELECT COUNT(*) FROM users");
            $stmt->execute();
            $user_stats['total_users'] = (int)$stmt->fetchColumn();
            
            // Total pets
            $stmt = $db->prepare("SELECT COUNT(*) FROM pets");
            $stmt->execute();
            $user_stats['total_pets'] = (int)$stmt->fetchColumn();
            
            // Total adoptions
            $stmt = $db->prepare("SELECT COUNT(*) FROM adoption_applications");
            $stmt->execute();
            $user_stats['total_adoptions'] = (int)$stmt->fetchColumn();
            
            // Total shelters
            $stmt = $db->prepare("SELECT COUNT(*) FROM shelters");
            $stmt->execute();
            $user_stats['total_shelters'] = (int)$stmt->fetchColumn();
            
            // Pending adoptions
            $stmt = $db->prepare("SELECT COUNT(*) FROM adoption_applications WHERE application_status = 'pending'");
            $stmt->execute();
            $user_stats['pending_adoptions'] = (int)$stmt->fetchColumn();
            
            // This month users
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
            $stmt->execute();
            $user_stats['this_month_users'] = (int)$stmt->fetchColumn();
            
            // Overdue vaccinations
            $stmt = $db->prepare("SELECT COUNT(*) FROM vaccinations WHERE vaccination_date IS NULL AND next_due_date < CURDATE()");
            $stmt->execute();
            $user_stats['overdue_vaccinations'] = (int)$stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Stats error: " . $e->getMessage());
        }
        
        // Sample activity logs
        $activity_logs = [
            [
                'action' => 'Profile Updated',
                'description' => 'Updated personal information',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ],
            [
                'action' => 'Login',
                'description' => 'Successful admin login',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-5 hours')),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ],
            [
                'action' => 'User Management',
                'description' => 'Viewed user management dashboard',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]
        ];
        
    } else {
        $error_message = 'Failed to connect to database.';
    }
} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    $error_message = 'Failed to load profile data.';
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
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Header Section */
    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

    .user-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 700;
        color: #667eea;
        border: 4px solid rgba(255, 255, 255, 0.3);
    }

    /* Main Content Grid */
    .profile-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 30px;
    }

    .profile-card {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .profile-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .profile-card h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #2c3e50;
    }

    .form-group.required label::after {
        content: ' *';
        color: #dc3545;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e1e8ed;
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-control[readonly] {
        background: #f8f9fa;
        color: #666;
    }

    /* Buttons */
    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        text-align: center;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-2px);
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .btn-success:hover {
        background: #218838;
        transform: translateY(-2px);
    }

    .btn-danger {
        background: #dc3545;
        color: white;
    }

    .btn-danger:hover {
        background: #c82333;
        transform: translateY(-2px);
    }

    .btn-block {
        width: 100%;
        justify-content: center;
    }

    /* Statistics Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

    .stat-card.users {
        --color: #17a2b8;
    }

    .stat-card.pets {
        --color: #28a745;
    }

    .stat-card.adoptions {
        --color: #fd7e14;
    }

    .stat-card.shelters {
        --color: #6f42c1;
    }

    .stat-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .stat-info h4 {
        color: #666;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color);
        line-height: 1;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        background: var(--color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        opacity: 0.9;
    }

    /* Activity Log */
    .activity-section {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .activity-header {
        background: #f8f9fa;
        padding: 20px 25px;
        border-bottom: 1px solid #eee;
    }

    .activity-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
    }

    .activity-item {
        padding: 20px 25px;
        border-bottom: 1px solid #f1f1f1;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: background-color 0.3s ease;
    }

    .activity-item:hover {
        background: #f8f9fa;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
    }

    .activity-content {
        flex: 1;
    }

    .activity-action {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 2px;
    }

    .activity-description {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 5px;
    }

    .activity-meta {
        font-size: 0.8rem;
        color: #999;
        display: flex;
        gap: 15px;
    }

    /* Messages */
    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Animations */
    .fade-in {
        animation: fadeIn 0.6s ease-out;
    }

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

    /* Responsive Design */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .page-header {
            flex-direction: column;
            text-align: center;
        }

        .profile-grid {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
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
    <!-- Include Admin Navbar -->
    <?php include_once __DIR__ . '/../common/navbar_admin.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <div>
                <h1><i class="fas fa-user-shield"></i> Admin Profile</h1>
                <p>Manage your account settings and view your administrative dashboard</p>
            </div>
            <div class="user-avatar">
                <?php 
                if ($user_data) {
                    echo strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1)); 
                } else {
                    echo 'AD';
                }
                ?>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success fade-in">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger fade-in">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="stats-grid fade-in">
            <div class="stat-card users">
                <div class="stat-content">
                    <div class="stat-info">
                        <h4>Total Users</h4>
                        <div class="stat-number"><?php echo number_format($user_stats['total_users']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pets">
                <div class="stat-content">
                    <div class="stat-info">
                        <h4>Total Pets</h4>
                        <div class="stat-number"><?php echo number_format($user_stats['total_pets']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card adoptions">
                <div class="stat-content">
                    <div class="stat-info">
                        <h4>Adoptions</h4>
                        <div class="stat-number"><?php echo number_format($user_stats['total_adoptions']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card shelters">
                <div class="stat-content">
                    <div class="stat-info">
                        <h4>Shelters</h4>
                        <div class="stat-number"><?php echo number_format($user_stats['total_shelters']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($user_data): ?>
        <!-- Profile Forms -->
        <div class="profile-grid fade-in">
            <!-- Profile Information Form -->
            <div class="profile-card">
                <h3>
                    <i class="fas fa-user-edit"></i>
                    Profile Information
                </h3>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-group required">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control"
                            value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                    </div>

                    <div class="form-group required">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control"
                            value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                    </div>

                    <div class="form-group required">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control"
                            value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control"
                            value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control"
                            rows="3"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="user_type">Account Type</label>
                        <input type="text" id="user_type" class="form-control"
                            value="<?php echo ucfirst($user_data['user_type']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="created_at">Member Since</label>
                        <input type="text" id="created_at" class="form-control"
                            value="<?php echo date('F j, Y', strtotime($user_data['created_at'])); ?>" readonly>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i>
                        Update Profile
                    </button>
                </form>
            </div>

            <!-- Password Change Form -->
            <div class="profile-card">
                <h3>
                    <i class="fas fa-lock"></i>
                    Change Password
                </h3>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group required">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control"
                            required>
                    </div>

                    <div class="form-group required">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" minlength="6"
                            required>
                        <small style="color: #666; font-size: 0.85rem;">Password must be at least 6 characters
                            long</small>
                    </div>

                    <div class="form-group required">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                            minlength="6" required>
                    </div>

                    <div
                        style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 20px 0;">
                        <p style="margin: 0; color: #856404; font-size: 0.9rem;">
                            <strong><i class="fas fa-info-circle"></i> Security Tips:</strong>
                        </p>
                        <ul style="margin: 10px 0 0 20px; color: #856404; font-size: 0.85rem;">
                            <li>Use a strong, unique password</li>
                            <li>Include uppercase, lowercase, numbers, and symbols</li>
                            <li>Don't reuse passwords from other accounts</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn btn-danger btn-block">
                        <i class="fas fa-key"></i>
                        Change Password
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="activity-section fade-in">
            <div class="activity-header">
                <h3 class="activity-title">
                    <i class="fas fa-history"></i>
                    Recent Activity
                </h3>
            </div>

            <div>
                <?php foreach ($activity_logs as $log): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-action"><?php echo htmlspecialchars($log['action']); ?></div>
                        <div class="activity-description"><?php echo htmlspecialchars($log['description']); ?></div>
                        <div class="activity-meta">
                            <span><i class="fas fa-clock"></i>
                                <?php echo date('M j, Y g:i A', strtotime($log['timestamp'])); ?></span>
                            <span><i class="fas fa-map-marker-alt"></i> IP:
                                <?php echo htmlspecialchars($log['ip_address']); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="profile-card fade-in" style="margin-bottom: 30px;">
            <h3>
                <i class="fas fa-bolt"></i>
                Quick Actions
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="<?php echo $BASE_URL; ?>admin/dashboard.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="<?php echo $BASE_URL; ?>admin/manageUsers.php" class="btn btn-secondary">
                    <i class="fas fa-users-cog"></i>
                    Manage Users
                </a>
                <a href="<?php echo $BASE_URL; ?>admin/managePets.php" class="btn btn-success">
                    <i class="fas fa-paw"></i>
                    Manage Pets
                </a>
                <a href="<?php echo $BASE_URL; ?>admin/reports.php" class="btn btn-secondary">
                    <i class="fas fa-chart-line"></i>
                    View Reports
                </a>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password confirmation validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');

        if (newPassword && confirmPassword) {
            function validatePassword() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }

            newPassword.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validatePassword);
        }

        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 300);
            }, 5000);
        });
    });
    </script>
</body>

</html>