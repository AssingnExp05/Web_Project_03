<?php
// shelter/profile.php - Shelter Profile Management Page
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
$page_title = 'Profile Settings - Shelter Dashboard';

// Initialize variables
$user_data = null;
$shelter_data = null;
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        require_once __DIR__ . '/../config/db.php';
        $db = getDB();
        
        if ($db) {
            if ($action === 'update_profile') {
                // Personal Information Update
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $address = trim($_POST['address'] ?? '');
                
                // Validation
                $errors = [];
                
                if (empty($first_name)) {
                    $errors[] = 'First name is required.';
                }
                
                if (empty($last_name)) {
                    $errors[] = 'Last name is required.';
                }
                
                if (empty($email)) {
                    $errors[] = 'Email is required.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Please enter a valid email address.';
                }
                
                if (!empty($phone) && !preg_match('/^[\+\-\(\)\d\s]+$/', $phone)) {
                    $errors[] = 'Please enter a valid phone number.';
                }
                
                if (empty($errors)) {
                    // Check if email is already taken by another user
                    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                    $stmt->execute([$email, $user_id]);
                    
                    if ($stmt->fetch()) {
                        $error_message = 'Email address is already in use by another account.';
                    } else {
                        // Update user information
                        $stmt = $db->prepare("
                            UPDATE users SET 
                                first_name = ?, last_name = ?, email = ?, phone = ?, address = ?
                            WHERE user_id = ?
                        ");
                        
                        if ($stmt->execute([$first_name, $last_name, $email, $phone, $address, $user_id])) {
                            // Update session data
                            $_SESSION['first_name'] = $first_name;
                            $_SESSION['last_name'] = $last_name;
                            $_SESSION['email'] = $email;
                            
                            $success_message = 'Profile updated successfully!';
                        } else {
                            $error_message = 'Failed to update profile. Please try again.';
                        }
                    }
                } else {
                    $error_message = implode('<br>', $errors);
                }
                
            } elseif ($action === 'update_shelter') {
                // Shelter Information Update
                $shelter_name = trim($_POST['shelter_name'] ?? '');
                $license_number = trim($_POST['license_number'] ?? '');
                $capacity = intval($_POST['capacity'] ?? 0);
                
                // Validation
                $errors = [];
                
                if (empty($shelter_name)) {
                    $errors[] = 'Shelter name is required.';
                }
                
                if ($capacity < 0) {
                    $errors[] = 'Capacity must be a positive number.';
                }
                
                if (empty($errors)) {
                    // Update shelter information
                    $stmt = $db->prepare("
                        UPDATE shelters SET 
                            shelter_name = ?, license_number = ?, capacity = ?
                        WHERE user_id = ?
                    ");
                    
                    if ($stmt->execute([$shelter_name, $license_number, $capacity, $user_id])) {
                        $success_message = 'Shelter information updated successfully!';
                    } else {
                        $error_message = 'Failed to update shelter information. Please try again.';
                    }
                } else {
                    $error_message = implode('<br>', $errors);
                }
                
            } elseif ($action === 'change_password') {
                // Password Change
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                // Validation
                $errors = [];
                
                if (empty($current_password)) {
                    $errors[] = 'Current password is required.';
                }
                
                if (empty($new_password)) {
                    $errors[] = 'New password is required.';
                } elseif (strlen($new_password) < 6) {
                    $errors[] = 'New password must be at least 6 characters long.';
                }
                
                if ($new_password !== $confirm_password) {
                    $errors[] = 'New password and confirmation do not match.';
                }
                
                if (empty($errors)) {
                    // Verify current password
                    $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $user_password = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user_password && password_verify($current_password, $user_password['password_hash'])) {
                        // Update password
                        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                        
                        if ($stmt->execute([$new_password_hash, $user_id])) {
                            $success_message = 'Password changed successfully!';
                        } else {
                            $error_message = 'Failed to change password. Please try again.';
                        }
                    } else {
                        $error_message = 'Current password is incorrect.';
                    }
                } else {
                    $error_message = implode('<br>', $errors);
                }
            }
        } else {
            $error_message = 'Database connection failed.';
        }
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        $error_message = 'An error occurred while updating your profile.';
    }
}

// Fetch current data
try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if ($db) {
        // Get user information
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get shelter information
        $stmt = $db->prepare("SELECT * FROM shelters WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $shelter_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_data) {
            $_SESSION['error_message'] = 'User information not found.';
            header('Location: ' . $BASE_URL . 'auth/login.php');
            exit();
        }
        
    } else {
        throw new Exception("Database connection failed");
    }
    
} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
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
        max-width: 1200px;
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

    .btn-lg {
        padding: 15px 30px;
        font-size: 1.1rem;
    }

    /* Profile Grid Layout */
    .profile-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .profile-card {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    .profile-card:hover {
        transform: translateY(-5px);
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f8f9fa;
    }

    .card-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #28a745, #20c997);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.3rem;
    }

    .card-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
    }

    .card-subtitle {
        font-size: 0.95rem;
        color: #666;
        margin: 0;
    }

    /* Form Styles */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-group.required label::after {
        content: ' *';
        color: #dc3545;
        font-weight: bold;
    }

    .form-group input,
    .form-group textarea {
        padding: 12px 15px;
        border: 2px solid #e1e8ed;
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        font-family: inherit;
        background: white;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #28a745;
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 2px solid #f8f9fa;
    }

    /* Full Width Section */
    .full-width-section {
        grid-column: 1 / -1;
    }

    .password-section {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-top: 30px;
    }

    /* Messages */
    .message {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        animation: slideIn 0.3s ease-out;
    }

    .message.success {
        background: rgba(40, 167, 69, 0.1);
        color: #155724;
        border: 1px solid rgba(40, 167, 69, 0.2);
    }

    .message.error {
        background: rgba(220, 53, 69, 0.1);
        color: #721c24;
        border: 1px solid rgba(220, 53, 69, 0.2);
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Stats Section */
    .stats-section {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .stat-item {
        text-align: center;
        padding: 20px;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 15px;
        transition: transform 0.3s ease;
    }

    .stat-item:hover {
        transform: translateY(-3px);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: #28a745;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 0.9rem;
        color: #666;
        font-weight: 500;
    }

    /* Loading State */
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }

    .spinner {
        border: 2px solid #f3f3f3;
        border-top: 2px solid #28a745;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        animation: spin 1s linear infinite;
        display: inline-block;
        margin-left: 8px;
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
    @media (max-width: 1024px) {
        .profile-grid {
            grid-template-columns: 1fr;
            gap: 25px;
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

        .profile-card {
            padding: 25px;
        }

        .form-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .form-actions {
            flex-direction: column;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
    }

    @media (max-width: 480px) {
        .page-header h1 {
            font-size: 1.5rem;
        }

        .profile-card {
            padding: 20px;
        }

        .card-header {
            flex-direction: column;
            text-align: center;
            gap: 10px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .btn {
            padding: 8px 16px;
            font-size: 0.8rem;
        }

        .btn-lg {
            padding: 12px 24px;
            font-size: 1rem;
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
                <h1><i class="fas fa-user-cog"></i> Profile Settings</h1>
                <p>Manage your personal information and shelter details</p>
            </div>
            <div class="header-actions">
                <a href="<?php echo $BASE_URL; ?>shelter/dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="<?php echo $BASE_URL; ?>shelter/viewPets.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> My Pets
                </a>
            </div>
        </div>

        <!-- Display Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Account Statistics -->
        <div class="stats-section">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div>
                    <h3 class="card-title">Account Overview</h3>
                    <p class="card-subtitle">Your shelter activity summary</p>
                </div>
            </div>
            <div class="stats-grid">
                <?php
                // Fetch statistics
                $stats = [
                    'total_pets' => 0,
                    'active_pets' => 0,
                    'adopted_pets' => 0,
                    'pending_applications' => 0
                ];
                
                try {
                    if ($db && $shelter_data) {
                        // Total pets
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ?");
                        $stmt->execute([$shelter_data['shelter_id']]);
                        $result = $stmt->fetch();
                        $stats['total_pets'] = $result ? $result['count'] : 0;
                        
                        // Active pets
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'available'");
                        $stmt->execute([$shelter_data['shelter_id']]);
                        $result = $stmt->fetch();
                        $stats['active_pets'] = $result ? $result['count'] : 0;
                        
                        // Adopted pets
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE shelter_id = ? AND status = 'adopted'");
                        $stmt->execute([$shelter_data['shelter_id']]);
                        $result = $stmt->fetch();
                        $stats['adopted_pets'] = $result ? $result['count'] : 0;
                        
                        // Pending applications
                        $stmt = $db->prepare("
                            SELECT COUNT(*) as count 
                            FROM adoption_applications aa 
                            JOIN pets p ON aa.pet_id = p.pet_id 
                            WHERE p.shelter_id = ? AND aa.application_status = 'pending'
                        ");
                        $stmt->execute([$shelter_data['shelter_id']]);
                        $result = $stmt->fetch();
                        $stats['pending_applications'] = $result ? $result['count'] : 0;
                    }
                } catch (Exception $e) {
                    // Silently handle errors
                }
                ?>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total_pets']; ?></div>
                    <div class="stat-label">Total Pets</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['active_pets']; ?></div>
                    <div class="stat-label">Available Pets</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['adopted_pets']; ?></div>
                    <div class="stat-label">Adopted Pets</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                    <div class="stat-label">Pending Applications</div>
                </div>
            </div>
        </div>

        <!-- Profile Forms Grid -->
        <div class="profile-grid">
            <!-- Personal Information -->
            <div class="profile-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <h3 class="card-title">Personal Information</h3>
                        <p class="card-subtitle">Update your personal details</p>
                    </div>
                </div>

                <form method="POST" action="" id="profileForm">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid">
                        <div class="form-group required">
                            <label for="first_name">
                                <i class="fas fa-user"></i> First Name
                            </label>
                            <input type="text" id="first_name" name="first_name" required
                                value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group required">
                            <label for="last_name">
                                <i class="fas fa-user"></i> Last Name
                            </label>
                            <input type="text" id="last_name" name="last_name" required
                                value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group required full-width">
                            <label for="email">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <input type="email" id="email" name="email" required
                                value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone">
                                <i class="fas fa-phone"></i> Phone Number
                            </label>
                            <input type="tel" id="phone" name="phone"
                                value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>"
                                placeholder="e.g., (555) 123-4567">
                        </div>

                        <div class="form-group full-width">
                            <label for="address">
                                <i class="fas fa-map-marker-alt"></i> Address
                            </label>
                            <textarea id="address" name="address" rows="3"
                                placeholder="Enter your full address"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- Shelter Information -->
            <div class="profile-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h3 class="card-title">Shelter Information</h3>
                        <p class="card-subtitle">Manage your shelter details</p>
                    </div>
                </div>

                <form method="POST" action="" id="shelterForm">
                    <input type="hidden" name="action" value="update_shelter">
                    <div class="form-grid">
                        <div class="form-group required full-width">
                            <label for="shelter_name">
                                <i class="fas fa-building"></i> Shelter Name
                            </label>
                            <input type="text" id="shelter_name" name="shelter_name" required
                                value="<?php echo htmlspecialchars($shelter_data['shelter_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="license_number">
                                <i class="fas fa-certificate"></i> License Number
                            </label>
                            <input type="text" id="license_number" name="license_number"
                                value="<?php echo htmlspecialchars($shelter_data['license_number'] ?? ''); ?>"
                                placeholder="e.g., SH-2024-001">
                        </div>

                        <div class="form-group">
                            <label for="capacity">
                                <i class="fas fa-home"></i> Capacity
                            </label>
                            <input type="number" id="capacity" name="capacity" min="0"
                                value="<?php echo htmlspecialchars($shelter_data['capacity'] ?? '0'); ?>"
                                placeholder="Maximum number of pets">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Shelter Info
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password Change Section -->
        <div class="password-section">
            <div class="card-header">
                <div class="card-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <div>
                    <h3 class="card-title">Change Password</h3>
                    <p class="card-subtitle">Update your account security</p>
                </div>
            </div>

            <form method="POST" action="" id="passwordForm">
                <input type="hidden" name="action" value="change_password">
                <div class="form-grid">
                    <div class="form-group required">
                        <label for="current_password">
                            <i class="fas fa-key"></i> Current Password
                        </label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group required">
                        <label for="new_password">
                            <i class="fas fa-lock"></i> New Password
                        </label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                        <small style="color: #666;">Minimum 6 characters</small>
                    </div>

                    <div class="form-group required">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i> Confirm New Password
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-shield-alt"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Form validation and enhancement
    document.addEventListener('DOMContentLoaded', function() {
        // Profile form validation
        const profileForm = document.getElementById('profileForm');
        const shelterForm = document.getElementById('shelterForm');
        const passwordForm = document.getElementById('passwordForm');

        // Email validation
        const emailInput = document.getElementById('email');
        emailInput.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value && !emailRegex.test(this.value)) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#e1e8ed';
            }
        });

        // Phone validation
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function() {
            // Remove non-phone characters
            this.value = this.value.replace(/[^\d\s\-\(\)\+]/g, '');
        });

        // Password confirmation validation
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        function validatePasswordMatch() {
            if (confirmPasswordInput.value && newPasswordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.style.borderColor = '#dc3545';
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.style.borderColor = '#e1e8ed';
                confirmPasswordInput.setCustomValidity('');
            }
        }

        newPasswordInput.addEventListener('input', validatePasswordMatch);
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);

        // Form submission handling with loading states
        function handleFormSubmit(form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;

                // Show loading state
                submitBtn.innerHTML = originalText.replace(/fa-[a-z-]+/, 'fa-spinner fa-spin');
                submitBtn.disabled = true;

                // Re-enable after delay if there are validation errors
                setTimeout(() => {
                    if (submitBtn.disabled) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                }, 5000);
            });
        }

        handleFormSubmit(profileForm);
        handleFormSubmit(shelterForm);
        handleFormSubmit(passwordForm);

        // Auto-hide success messages
        const successMessages = document.querySelectorAll('.message.success');
        successMessages.forEach(message => {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.remove();
                    }
                }, 500);
            }, 5000);
        });

        // Capacity input validation
        const capacityInput = document.getElementById('capacity');
        capacityInput.addEventListener('input', function() {
            if (this.value < 0) {
                this.value = 0;
            }
        });

        // Character counter for textarea
        const addressTextarea = document.getElementById('address');
        if (addressTextarea) {
            addressTextarea.addEventListener('input', function() {
                const maxLength = 500;
                const currentLength = this.value.length;

                if (currentLength > maxLength) {
                    this.value = this.value.substring(0, maxLength);
                }
            });
        }
    });

    // Smooth scrolling for form validation errors
    function scrollToElement(element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }

    // Focus management for accessibility
    document.addEventListener('keydown', function(e) {
        // Tab navigation enhancement
        if (e.key === 'Tab') {
            const focusableElements = document.querySelectorAll(
                'input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }
    });
    </script>
</body>

</html>