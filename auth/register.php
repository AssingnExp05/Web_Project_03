<?php
// auth/register.php - User Registration Page (Sri Lanka)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE_URL = 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_type']) {
        case 'admin':
            header('Location: ' . $BASE_URL . 'admin/dashboard.php');
            break;
        case 'shelter':
            header('Location: ' . $BASE_URL . 'shelter/dashboard.php');
            break;
        case 'adopter':
            header('Location: ' . $BASE_URL . 'adopter/dashboard.php');
            break;
        default:
            header('Location: ' . $BASE_URL . 'index.php');
    }
    exit();
}

// Initialize variables
$errors = [];
$success_message = '';
$form_data = [
    'user_type' => '',
    'username' => '',
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'city' => '',
    'district' => '',
    'province' => '',
    'shelter_name' => '',
    'shelter_license' => '',
    'shelter_capacity' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $form_data['user_type'] = trim($_POST['user_type'] ?? '');
    $form_data['username'] = trim($_POST['username'] ?? '');
    $form_data['first_name'] = trim($_POST['first_name'] ?? '');
    $form_data['last_name'] = trim($_POST['last_name'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['address'] = trim($_POST['address'] ?? '') . ', ' . trim($_POST['city'] ?? '') . ', ' . trim($_POST['district'] ?? '') . ', ' . trim($_POST['province'] ?? '');
    $form_data['city'] = trim($_POST['city'] ?? '');
    $form_data['district'] = trim($_POST['district'] ?? '');
    $form_data['province'] = trim($_POST['province'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Shelter specific fields
    if ($form_data['user_type'] === 'shelter') {
        $form_data['shelter_name'] = trim($_POST['shelter_name'] ?? '');
        $form_data['shelter_license'] = trim($_POST['shelter_license'] ?? '');
        $form_data['shelter_capacity'] = intval($_POST['shelter_capacity'] ?? 0);
    }

    // Validation
    if (empty($form_data['user_type']) || !in_array($form_data['user_type'], ['adopter', 'shelter'])) {
        $errors[] = 'Please select a valid account type.';
    }

    if (empty($form_data['username'])) {
        $errors[] = 'Username is required.';
    } elseif (strlen($form_data['username']) < 3) {
        $errors[] = 'Username must be at least 3 characters long.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $form_data['username'])) {
        $errors[] = 'Username can only contain letters, numbers, and underscores.';
    }

    if (empty($form_data['first_name'])) {
        $errors[] = 'First name is required.';
    }

    if (empty($form_data['last_name'])) {
        $errors[] = 'Last name is required.';
    }

    if (empty($form_data['email'])) {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (empty($form_data['phone'])) {
        $errors[] = 'Phone number is required.';
    } elseif (!preg_match('/^(?:\+94|0)?[0-9]{9}$/', preg_replace('/[\s\-\(\)]/', '', $form_data['phone']))) {
        $errors[] = 'Please enter a valid Sri Lankan phone number.';
    }

    if (empty($form_data['city'])) {
        $errors[] = 'City is required.';
    }

    if (empty($form_data['district'])) {
        $errors[] = 'District is required.';
    }

    if (empty($form_data['province'])) {
        $errors[] = 'Province is required.';
    }

    // Shelter specific validation
    if ($form_data['user_type'] === 'shelter') {
        if (empty($form_data['shelter_name'])) {
            $errors[] = 'Shelter name is required.';
        }
        if (empty($form_data['shelter_license'])) {
            $errors[] = 'Shelter license number is required.';
        }
        if ($form_data['shelter_capacity'] <= 0) {
            $errors[] = 'Shelter capacity must be greater than 0.';
        }
    }

    // If no errors, try to register the user
    if (empty($errors)) {
        try {
            require_once __DIR__ . '/../config/db.php';
            $db = getDB();

            if ($db) {
                // Check if username already exists
                $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->execute([$form_data['username']]);
                if ($stmt->fetch()) {
                    $errors[] = 'This username is already taken. Please choose another.';
                }

                // Check if email already exists
                $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$form_data['email']]);
                if ($stmt->fetch()) {
                    $errors[] = 'An account with this email address already exists.';
                }

                if (empty($errors)) {
                    // Hash the password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Clean phone number
                    $clean_phone = preg_replace('/[\s\-\(\)]/', '', $form_data['phone']);
                    if (substr($clean_phone, 0, 1) === '0') {
                        $clean_phone = '+94' . substr($clean_phone, 1);
                    } elseif (substr($clean_phone, 0, 3) !== '+94') {
                        $clean_phone = '+94' . $clean_phone;
                    }
                    
                    // Start transaction
                    $db->beginTransaction();
                    
                    try {
                        // Insert user
                        $stmt = $db->prepare("
                            INSERT INTO users (username, email, password_hash, user_type, first_name, last_name, phone, address, is_active, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                        ");
                        
                        if ($stmt->execute([
                            $form_data['username'],
                            $form_data['email'],
                            $password_hash,
                            $form_data['user_type'],
                            $form_data['first_name'],
                            $form_data['last_name'],
                            $clean_phone,
                            $form_data['address']
                        ])) {
                            $user_id = $db->lastInsertId();
                            
                            // If shelter, insert shelter record
                            if ($form_data['user_type'] === 'shelter') {
                                $stmt = $db->prepare("
                                    INSERT INTO shelters (user_id, shelter_name, license_number, capacity) 
                                    VALUES (?, ?, ?, ?)
                                ");
                                
                                if (!$stmt->execute([
                                    $user_id,
                                    $form_data['shelter_name'],
                                    $form_data['shelter_license'],
                                    $form_data['shelter_capacity']
                                ])) {
                                    throw new Exception('Failed to create shelter record.');
                                }
                            }
                            
                            // Commit transaction
                            $db->commit();
                            
                            // Registration successful
                            $_SESSION['success_message'] = 'ලියාපදිංචිය සාර්ථකයි! Registration successful! You can now log in to your account.';
                            header('Location: ' . $BASE_URL . 'auth/login.php');
                            exit();
                        } else {
                            throw new Exception('Failed to create user account.');
                        }
                    } catch (Exception $e) {
                        // Rollback transaction
                        $db->rollback();
                        $errors[] = 'Registration failed: ' . $e->getMessage();
                    }
                }
            } else {
                $errors[] = 'Database connection failed. Please try again later.';
            }
        } catch (Exception $e) {
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Pet Adoption Care Guide Sri Lanka</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px 0;
    }

    .register-container {
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        width: 100%;
        max-width: 900px;
        margin: 20px;
    }

    .register-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px;
        text-align: center;
    }

    .register-header h1 {
        font-size: 2.5rem;
        margin-bottom: 10px;
        font-weight: 700;
    }

    .register-header p {
        font-size: 1.1rem;
        opacity: 0.9;
    }

    .sinhala-text {
        font-size: 1.2rem;
        margin-top: 10px;
        color: #ffd700;
        font-weight: 600;
    }

    .register-form {
        padding: 40px;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-group.full-width {
        grid-column: span 2;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.95rem;
    }

    .required {
        color: #e74c3c;
    }

    input,
    select,
    textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e1e8ed;
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }

    input:focus,
    select:focus,
    textarea:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .input-group {
        position: relative;
    }

    .input-icon {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #666;
        cursor: pointer;
        font-size: 1.1rem;
    }

    .password-toggle {
        cursor: pointer;
    }

    .password-toggle:hover {
        color: #667eea;
    }

    .user-type-selector {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }

    .user-type-option {
        position: relative;
    }

    .user-type-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .user-type-label {
        display: block;
        padding: 25px 20px;
        border: 2px solid #e1e8ed;
        border-radius: 15px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }

    .user-type-label:hover {
        border-color: #667eea;
        background: white;
    }

    .user-type-option input[type="radio"]:checked+.user-type-label {
        border-color: #667eea;
        background: #667eea;
        color: white;
    }

    .user-type-icon {
        font-size: 2.5rem;
        margin-bottom: 15px;
        display: block;
    }

    .user-type-title {
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 1.1rem;
    }

    .user-type-desc {
        font-size: 0.9rem;
        opacity: 0.8;
    }

    .user-type-desc-si {
        font-size: 0.85rem;
        opacity: 0.7;
        margin-top: 5px;
        font-style: italic;
    }

    .shelter-fields {
        display: none;
        padding: 25px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 15px;
        margin-top: 20px;
        border-left: 4px solid #667eea;
    }

    .shelter-fields.show {
        display: block;
    }

    .shelter-fields h4 {
        color: #667eea;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.2rem;
    }

    .btn {
        width: 100%;
        padding: 15px;
        border: none;
        border-radius: 10px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .error-messages {
        background: #fff5f5;
        border: 1px solid #feb2b2;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .error-messages ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .error-messages li {
        color: #e53e3e;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .login-link {
        text-align: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e1e8ed;
    }

    .login-link a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
    }

    .login-link a:hover {
        text-decoration: underline;
    }

    .back-home {
        position: fixed;
        top: 20px;
        left: 20px;
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }

    .back-home:hover {
        background: rgba(255, 255, 255, 0.3);
        text-decoration: none;
        color: white;
    }

    .sri-lanka-flag {
        display: inline-block;
        margin: 0 5px;
        font-size: 1.2rem;
    }

    .location-help {
        font-size: 0.85rem;
        color: #666;
        margin-top: 5px;
        font-style: italic;
    }

    .phone-format {
        font-size: 0.8rem;
        color: #666;
        margin-top: 3px;
    }

    @media (max-width: 768px) {
        .register-container {
            margin: 10px;
        }

        .register-header {
            padding: 30px 20px;
        }

        .register-header h1 {
            font-size: 2rem;
        }

        .register-form {
            padding: 30px 20px;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .form-group.full-width {
            grid-column: span 1;
        }

        .user-type-selector {
            grid-template-columns: 1fr;
        }

        .back-home {
            position: relative;
            top: auto;
            left: auto;
            display: inline-block;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.2);
        }
    }

    /* Animation */
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(50px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .register-container {
        animation: slideInUp 0.6s ease-out;
    }
    </style>
</head>

<body>
    <a href="<?php echo $BASE_URL; ?>index.php" class="back-home">
        <i class="fas fa-arrow-left"></i> Back to Home
    </a>

    <div class="register-container">
        <div class="register-header">
            <h1><i class="fas fa-paw"></i> Join Our Community <span class="sri-lanka-flag">🇱🇰</span></h1>
            <p>Create your account to start your pet adoption journey in Sri Lanka</p>
            <div class="sinhala-text">ශ්‍රී ලංකාවේ සුරක්ෂිත සත්ව දරුකම්</div>
        </div>

        <div class="register-form">
            <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <!-- Account Type Selection -->
                <div class="form-group">
                    <label>Account Type <span class="required">*</span></label>
                    <div class="user-type-selector">
                        <div class="user-type-option">
                            <input type="radio" id="adopter" name="user_type" value="adopter"
                                <?php echo $form_data['user_type'] === 'adopter' ? 'checked' : ''; ?>>
                            <label for="adopter" class="user-type-label">
                                <i class="fas fa-heart user-type-icon"></i>
                                <div class="user-type-title">Pet Adopter</div>
                                <div class="user-type-desc">Looking to adopt a pet</div>
                                <div class="user-type-desc-si">සුරතල් සත්ව දරුකම්</div>
                            </label>
                        </div>
                        <div class="user-type-option">
                            <input type="radio" id="shelter" name="user_type" value="shelter"
                                <?php echo $form_data['user_type'] === 'shelter' ? 'checked' : ''; ?>>
                            <label for="shelter" class="user-type-label">
                                <i class="fas fa-home user-type-icon"></i>
                                <div class="user-type-title">Shelter/Rescue</div>
                                <div class="user-type-desc">Managing pet adoptions</div>
                                <div class="user-type-desc-si">සත්ව නවාතැන්</div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Username and Personal Information -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username <span class="required">*</span></label>
                        <input type="text" id="username" name="username"
                            value="<?php echo htmlspecialchars($form_data['username']); ?>"
                            placeholder="Choose a unique username" required>
                        <div class="phone-format">Only letters, numbers, and underscores allowed</div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email"
                            value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name"
                            value="<?php echo htmlspecialchars($form_data['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name"
                            value="<?php echo htmlspecialchars($form_data['last_name']); ?>" required>
                    </div>
                </div>

                <!-- Phone Number -->
                <div class="form-group">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone"
                        value="<?php echo htmlspecialchars($form_data['phone']); ?>" placeholder="071 234 5678"
                        required>
                    <div class="phone-format">Format: 071 234 5678 or +94 71 234 5678</div>
                </div>

                <!-- Password Fields -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" required>
                            <i class="fas fa-eye input-icon password-toggle" onclick="togglePassword('password')"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <i class="fas fa-eye input-icon password-toggle"
                                onclick="togglePassword('confirm_password')"></i>
                        </div>
                    </div>
                </div>

                <!-- Location Information -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City <span class="required">*</span></label>
                        <input type="text" id="city" name="city"
                            value="<?php echo htmlspecialchars($form_data['city']); ?>"
                            placeholder="e.g., Colombo, Kandy" required>
                    </div>
                    <div class="form-group">
                        <label for="district">District <span class="required">*</span></label>
                        <select id="district" name="district" required>
                            <option value="">Select District</option>
                            <!-- Western Province -->
                            <option value="Colombo"
                                <?php echo $form_data['district'] === 'Colombo' ? 'selected' : ''; ?>>Colombo</option>
                            <option value="Gampaha"
                                <?php echo $form_data['district'] === 'Gampaha' ? 'selected' : ''; ?>>Gampaha</option>
                            <option value="Kalutara"
                                <?php echo $form_data['district'] === 'Kalutara' ? 'selected' : ''; ?>>Kalutara</option>
                            <!-- Central Province -->
                            <option value="Kandy" <?php echo $form_data['district'] === 'Kandy' ? 'selected' : ''; ?>>
                                Kandy</option>
                            <option value="Matale" <?php echo $form_data['district'] === 'Matale' ? 'selected' : ''; ?>>
                                Matale</option>
                            <option value="Nuwara Eliya"
                                <?php echo $form_data['district'] === 'Nuwara Eliya' ? 'selected' : ''; ?>>Nuwara Eliya
                            </option>
                            <!-- Southern Province -->
                            <option value="Galle" <?php echo $form_data['district'] === 'Galle' ? 'selected' : ''; ?>>
                                Galle</option>
                            <option value="Matara" <?php echo $form_data['district'] === 'Matara' ? 'selected' : ''; ?>>
                                Matara</option>
                            <option value="Hambantota"
                                <?php echo $form_data['district'] === 'Hambantota' ? 'selected' : ''; ?>>Hambantota
                            </option>
                            <!-- Northern Province -->
                            <option value="Jaffna" <?php echo $form_data['district'] === 'Jaffna' ? 'selected' : ''; ?>>
                                Jaffna</option>
                            <option value="Kilinochchi"
                                <?php echo $form_data['district'] === 'Kilinochchi' ? 'selected' : ''; ?>>Kilinochchi
                            </option>
                            <option value="Mannar" <?php echo $form_data['district'] === 'Mannar' ? 'selected' : ''; ?>>
                                Mannar</option>
                            <option value="Mullaitivu"
                                <?php echo $form_data['district'] === 'Mullaitivu' ? 'selected' : ''; ?>>Mullaitivu
                            </option>
                            <option value="Vavuniya"
                                <?php echo $form_data['district'] === 'Vavuniya' ? 'selected' : ''; ?>>Vavuniya</option>
                            <!-- Eastern Province -->
                            <option value="Batticaloa"
                                <?php echo $form_data['district'] === 'Batticaloa' ? 'selected' : ''; ?>>Batticaloa
                            </option>
                            <option value="Ampara" <?php echo $form_data['district'] === 'Ampara' ? 'selected' : ''; ?>>
                                Ampara</option>
                            <option value="Trincomalee"
                                <?php echo $form_data['district'] === 'Trincomalee' ? 'selected' : ''; ?>>Trincomalee
                            </option>
                            <!-- North Western Province -->
                            <option value="Kurunegala"
                                <?php echo $form_data['district'] === 'Kurunegala' ? 'selected' : ''; ?>>Kurunegala
                            </option>
                            <option value="Puttalam"
                                <?php echo $form_data['district'] === 'Puttalam' ? 'selected' : ''; ?>>Puttalam</option>
                            <!-- North Central Province -->
                            <option value="Anuradhapura"
                                <?php echo $form_data['district'] === 'Anuradhapura' ? 'selected' : ''; ?>>Anuradhapura
                            </option>
                            <option value="Polonnaruwa"
                                <?php echo $form_data['district'] === 'Polonnaruwa' ? 'selected' : ''; ?>>Polonnaruwa
                            </option>
                            <!-- Uva Province -->
                            <option value="Badulla"
                                <?php echo $form_data['district'] === 'Badulla' ? 'selected' : ''; ?>>Badulla</option>
                            <option value="Monaragala"
                                <?php echo $form_data['district'] === 'Monaragala' ? 'selected' : ''; ?>>Monaragala
                            </option>
                            <!-- Sabaragamuwa Province -->
                            <option value="Ratnapura"
                                <?php echo $form_data['district'] === 'Ratnapura' ? 'selected' : ''; ?>>Ratnapura
                            </option>
                            <option value="Kegalle"
                                <?php echo $form_data['district'] === 'Kegalle' ? 'selected' : ''; ?>>Kegalle</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="province">Province <span class="required">*</span></label>
                        <select id="province" name="province" required>
                            <option value="">Select Province</option>
                            <option value="Western"
                                <?php echo $form_data['province'] === 'Western' ? 'selected' : ''; ?>>Western Province
                                (බස්නාහිර පළාත)</option>
                            <option value="Central"
                                <?php echo $form_data['province'] === 'Central' ? 'selected' : ''; ?>>Central Province
                                (මධ්‍යම පළාත)</option>
                            <option value="Southern"
                                <?php echo $form_data['province'] === 'Southern' ? 'selected' : ''; ?>>Southern Province
                                (දකුණු පළාත)</option>
                            <option value="Northern"
                                <?php echo $form_data['province'] === 'Northern' ? 'selected' : ''; ?>>Northern Province
                                (උතුරු පළාත)</option>
                            <option value="Eastern"
                                <?php echo $form_data['province'] === 'Eastern' ? 'selected' : ''; ?>>Eastern Province
                                (නැගෙනහිර පළාත)</option>
                            <option value="North Western"
                                <?php echo $form_data['province'] === 'North Western' ? 'selected' : ''; ?>>North
                                Western Province (වයඹ පළාත)</option>
                            <option value="North Central"
                                <?php echo $form_data['province'] === 'North Central' ? 'selected' : ''; ?>>North
                                Central Province (උතුරු මැද පළාත)</option>
                            <option value="Uva" <?php echo $form_data['province'] === 'Uva' ? 'selected' : ''; ?>>Uva
                                Province (ඌව පළාත)</option>
                            <option value="Sabaragamuwa"
                                <?php echo $form_data['province'] === 'Sabaragamuwa' ? 'selected' : ''; ?>>Sabaragamuwa
                                Province (සබරගමුව පළාත)</option>
                        </select>
                        <div class="location-help">Province will be auto-selected based on your district</div>
                    </div>
                    <div class="form-group">
                        <label for="address">Street Address <span class="required">*</span></label>
                        <input type="text" id="address" name="address"
                            value="<?php echo htmlspecialchars($form_data['address']); ?>"
                            placeholder="No. 123, Main Street" required>
                    </div>
                </div>

                <!-- Shelter-specific fields -->
                <div class="shelter-fields" id="shelterFields">
                    <h4><i class="fas fa-home"></i> Shelter Information / සත්ව නවාතැන් තොරතුරු</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="shelter_name">Shelter/Rescue Name <span class="required">*</span></label>
                            <input type="text" id="shelter_name" name="shelter_name"
                                value="<?php echo htmlspecialchars($form_data['shelter_name']); ?>"
                                placeholder="e.g., Colombo Animal Shelter">
                        </div>
                        <div class="form-group">
                            <label for="shelter_license">License/Registration Number <span
                                    class="required">*</span></label>
                            <input type="text" id="shelter_license" name="shelter_license"
                                value="<?php echo htmlspecialchars($form_data['shelter_license']); ?>"
                                placeholder="Government registration number">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="shelter_capacity">Shelter Capacity <span class="required">*</span></label>
                        <input type="number" id="shelter_capacity" name="shelter_capacity"
                            value="<?php echo htmlspecialchars($form_data['shelter_capacity']); ?>"
                            placeholder="Maximum number of animals" min="1">
                        <div class="location-help">Maximum number of animals your shelter can accommodate</div>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-user-plus"></i>
                        Create Account / ගිණුම නිර්මාණය කරන්න
                    </button>
                </div>
            </form>

            <div class="login-link">
                <p>Already have an account? <a href="<?php echo $BASE_URL; ?>auth/login.php">Sign in here</a></p>
                <p>දැනටමත් ගිණුමක් තිබේද? <a href="<?php echo $BASE_URL; ?>auth/login.php">මෙතනින් ඇතුල් වන්න</a></p>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registerForm');
        const submitBtn = document.getElementById('submitBtn');
        const userTypeInputs = document.querySelectorAll('input[name="user_type"]');
        const shelterFields = document.getElementById('shelterFields');
        const districtSelect = document.getElementById('district');
        const provinceSelect = document.getElementById('province');

        // District to Province mapping
        const districtProvinceMap = {
            'Colombo': 'Western',
            'Gampaha': 'Western',
            'Kalutara': 'Western',
            'Kandy': 'Central',
            'Matale': 'Central',
            'Nuwara Eliya': 'Central',
            'Galle': 'Southern',
            'Matara': 'Southern',
            'Hambantota': 'Southern',
            'Jaffna': 'Northern',
            'Kilinochchi': 'Northern',
            'Mannar': 'Northern',
            'Mullaitivu': 'Northern',
            'Vavuniya': 'Northern',
            'Batticaloa': 'Eastern',
            'Ampara': 'Eastern',
            'Trincomalee': 'Eastern',
            'Kurunegala': 'North Western',
            'Puttalam': 'North Western',
            'Anuradhapura': 'North Central',
            'Polonnaruwa': 'North Central',
            'Badulla': 'Uva',
            'Monaragala': 'Uva',
            'Ratnapura': 'Sabaragamuwa',
            'Kegalle': 'Sabaragamuwa'
        };

        // Auto-select province based on district
        districtSelect.addEventListener('change', function() {
            const selectedDistrict = this.value;
            if (selectedDistrict && districtProvinceMap[selectedDistrict]) {
                provinceSelect.value = districtProvinceMap[selectedDistrict];
            }
        });

        // Handle user type selection
        function toggleShelterFields() {
            const selectedType = document.querySelector('input[name="user_type"]:checked');
            if (selectedType && selectedType.value === 'shelter') {
                shelterFields.classList.add('show');
                document.getElementById('shelter_name').required = true;
                document.getElementById('shelter_license').required = true;
                document.getElementById('shelter_capacity').required = true;
            } else {
                shelterFields.classList.remove('show');
                document.getElementById('shelter_name').required = false;
                document.getElementById('shelter_license').required = false;
                document.getElementById('shelter_capacity').required = false;
            }
        }

        // Check initial state
        toggleShelterFields();

        // Add event listeners for user type
        userTypeInputs.forEach(input => {
            input.addEventListener('change', toggleShelterFields);
        });

        // Username validation
        const usernameInput = document.getElementById('username');
        usernameInput.addEventListener('input', function() {
            const username = this.value;
            const isValid = /^[a-zA-Z0-9_]+$/.test(username);

            if (username.length > 0 && !isValid) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#e1e8ed';
            }
        });

        // Phone number formatting for Sri Lankan numbers
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');

            // Handle different formats
            if (value.startsWith('94')) {
                // +94 format
                if (value.length <= 11) {
                    value = value.replace(/(\d{2})(\d{2})(\d{3})(\d+)/, '+$1 $2 $3 $4');
                }
            } else if (value.startsWith('0')) {
                // 0XX format
                if (value.length <= 10) {
                    value = value.replace(/(\d{3})(\d{3})(\d+)/, '$1 $2 $3');
                }
            } else if (value.length >= 9) {
                // Without leading zero
                value = value.replace(/(\d{2})(\d{3})(\d+)/, '$1 $2 $3');
            }

            e.target.value = value;
        });

        // Email validation
        const emailInput = document.getElementById('email');
        emailInput.addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (email.length > 0 && !emailRegex.test(email)) {
                this.style.borderColor = '#e74c3c';
            } else {
                this.style.borderColor = '#e1e8ed';
            }
        });

        // Real-time password validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        function validatePasswords() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            // Password strength indicator
            if (password.length > 0) {
                if (password.length < 6) {
                    passwordInput.style.borderColor = '#e74c3c';
                } else {
                    passwordInput.style.borderColor = '#27ae60';
                }
            }

            // Confirm password matching
            if (confirmPassword.length > 0) {
                if (password !== confirmPassword) {
                    confirmPasswordInput.style.borderColor = '#e74c3c';
                } else {
                    confirmPasswordInput.style.borderColor = '#27ae60';
                }
            }
        }

        passwordInput.addEventListener('input', validatePasswords);
        confirmPasswordInput.addEventListener('input', validatePasswords);

        // Form validation before submit
        form.addEventListener('submit', function(e) {
            let hasErrors = false;
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const username = usernameInput.value;
            const email = emailInput.value;

            // Clear previous error styling
            document.querySelectorAll('input, select').forEach(input => {
                input.style.borderColor = '#e1e8ed';
            });

            // Username validation
            if (username.length < 3) {
                usernameInput.style.borderColor = '#e74c3c';
                hasErrors = true;
            }

            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                usernameInput.style.borderColor = '#e74c3c';
                hasErrors = true;
            }

            // Password validation
            if (password.length < 6) {
                passwordInput.style.borderColor = '#e74c3c';
                hasErrors = true;
            }

            if (password !== confirmPassword) {
                confirmPasswordInput.style.borderColor = '#e74c3c';
                hasErrors = true;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                emailInput.style.borderColor = '#e74c3c';
                hasErrors = true;
            }

            // Phone validation
            const phone = phoneInput.value.replace(/\D/g, '');
            if (phone.length < 9) {
                phoneInput.style.borderColor = '#e74c3c';
                hasErrors = true;
            }

            // Required field validation
            const requiredFields = ['user_type', 'first_name', 'last_name', 'city', 'district',
                'province', 'address'
            ];
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field && !field.value.trim()) {
                    field.style.borderColor = '#e74c3c';
                    hasErrors = true;
                }
            });

            // Shelter specific validation
            const selectedType = document.querySelector('input[name="user_type"]:checked');
            if (selectedType && selectedType.value === 'shelter') {
                const shelterName = document.getElementById('shelter_name');
                const shelterLicense = document.getElementById('shelter_license');
                const shelterCapacity = document.getElementById('shelter_capacity');

                if (!shelterName.value.trim()) {
                    shelterName.style.borderColor = '#e74c3c';
                    hasErrors = true;
                }

                if (!shelterLicense.value.trim()) {
                    shelterLicense.style.borderColor = '#e74c3c';
                    hasErrors = true;
                }

                if (!shelterCapacity.value || parseInt(shelterCapacity.value) <= 0) {
                    shelterCapacity.style.borderColor = '#e74c3c';
                    hasErrors = true;
                }
            }

            if (hasErrors) {
                e.preventDefault();
                alert('Please correct the highlighted errors before submitting.');
                return false;
            }

            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.8';

            // Prevent double submission
            form.style.pointerEvents = 'none';
        });

        // Shelter capacity validation
        const capacityInput = document.getElementById('shelter_capacity');
        if (capacityInput) {
            capacityInput.addEventListener('input', function() {
                const value = parseInt(this.value);
                if (value <= 0 || isNaN(value)) {
                    this.style.borderColor = '#e74c3c';
                } else {
                    this.style.borderColor = '#27ae60';
                }
            });
        }

        // Auto-complete suggestions for cities (optional enhancement)
        const cityInput = document.getElementById('city');
        const popularCities = [
            'Colombo', 'Kandy', 'Galle', 'Negombo', 'Kurunegala', 'Anuradhapura',
            'Ratnapura', 'Batticaloa', 'Jaffna', 'Matara', 'Badulla', 'Trincomalee',
            'Kalutara', 'Gampaha', 'Matale', 'Nuwara Eliya', 'Hambantota'
        ];

        cityInput.addEventListener('input', function() {
            const value = this.value.toLowerCase();
            if (value.length > 1) {
                const suggestions = popularCities.filter(city =>
                    city.toLowerCase().includes(value)
                );

                // You could implement a dropdown here if needed
                console.log('City suggestions:', suggestions);
            }
        });

        // Form field highlighting on focus
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('focus', function() {
                this.style.borderColor = '#667eea';
                this.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.1)';
            });

            field.addEventListener('blur', function() {
                if (!this.matches(':focus')) {
                    this.style.boxShadow = 'none';
                    if (this.style.borderColor === 'rgb(102, 126, 234)') {
                        this.style.borderColor = '#e1e8ed';
                    }
                }
            });
        });

        // Prevent form submission on Enter key in input fields (except submit button)
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.type !== 'submit') {
                    e.preventDefault();
                    // Move to next input field
                    const inputs = Array.from(document.querySelectorAll('input, select'));
                    const currentIndex = inputs.indexOf(e.target);
                    const nextInput = inputs[currentIndex + 1];
                    if (nextInput) {
                        nextInput.focus();
                    }
                }
            });
        });

        // Auto-save form data to localStorage (optional - for better UX)
        const formInputs = document.querySelectorAll('input, select');
        formInputs.forEach(input => {
            // Load saved data
            const savedValue = localStorage.getItem(`register_${input.name}`);
            if (savedValue && input.type !== 'password') {
                if (input.type === 'radio') {
                    if (input.value === savedValue) {
                        input.checked = true;
                    }
                } else {
                    input.value = savedValue;
                }
            }

            // Save data on change
            input.addEventListener('change', function() {
                if (this.type !== 'password') {
                    localStorage.setItem(`register_${this.name}`, this.value);
                }
            });
        });

        // Clear saved data on successful submission
        form.addEventListener('submit', function() {
            // Only clear if form is valid
            setTimeout(() => {
                formInputs.forEach(input => {
                    localStorage.removeItem(`register_${input.name}`);
                });
            }, 1000);
        });
    });

    // Password visibility toggle function
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = field.nextElementSibling;

        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Page visibility change handler (pause validation when tab is not active)
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Pause any ongoing validation timers
            console.log('Page hidden - pausing validations');
        } else {
            // Resume validations
            console.log('Page visible - resuming validations');
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + Enter to submit form
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('submitBtn').click();
        }

        // Escape to clear form
        if (e.key === 'Escape') {
            if (confirm('Are you sure you want to clear all form data?')) {
                document.getElementById('registerForm').reset();
                // Clear localStorage
                const formInputs = document.querySelectorAll('input, select');
                formInputs.forEach(input => {
                    localStorage.removeItem(`register_${input.name}`);
                });
            }
        }
    });
    </script>
</body>

</html>