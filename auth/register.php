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

    :root {
        --primary-color: #4f46e5;
        --primary-dark: #4338ca;
        --primary-light: #6366f1;
        --secondary-color: #06b6d4;
        --secondary-light: #22d3ee;
        --success-color: #10b981;
        --danger-color: #ef4444;
        --warning-color: #f59e0b;
        --dark-color: #1e293b;
        --gray-color: #6b7280;
        --light-gray: #f3f4f6;
        --lighter-gray: #f9fafb;
        --white: #ffffff;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #f9fafb;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        overflow-x: hidden;
    }

    /* Light Background Pattern */
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image:
            radial-gradient(circle at 20% 80%, rgba(79, 70, 229, 0.05) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(6, 182, 212, 0.05) 0%, transparent 50%),
            radial-gradient(circle at 40% 40%, rgba(34, 211, 238, 0.05) 0%, transparent 50%);
        z-index: -2;
    }

    body::after {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background:
            linear-gradient(to bottom, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.5) 100%),
            url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%239CA3AF" fill-opacity="0.03"><path d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/></g></g></svg>');
        z-index: -1;
    }

    /* Floating Elements - Subtle */
    .floating-element {
        position: fixed;
        pointer-events: none;
        opacity: 0.03;
        z-index: -1;
        color: var(--primary-color);
    }

    .floating-element:nth-child(1) {
        top: 10%;
        left: 5%;
        font-size: 60px;
        animation: float 20s infinite ease-in-out;
    }

    .floating-element:nth-child(2) {
        top: 70%;
        right: 10%;
        font-size: 80px;
        animation: float 25s infinite ease-in-out reverse;
    }

    .floating-element:nth-child(3) {
        bottom: 20%;
        left: 15%;
        font-size: 70px;
        animation: float 22s infinite ease-in-out;
    }

    @keyframes float {

        0%,
        100% {
            transform: translateY(0) rotate(0deg);
        }

        50% {
            transform: translateY(-20px) rotate(5deg);
        }
    }

    /* Main Container */
    .register-container {
        background: var(--white);
        border-radius: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.04), 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        width: 100%;
        max-width: 1000px;
        margin: auto;
        position: relative;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    /* Progress Bar */
    .progress-bar {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--light-gray);
        overflow: hidden;
        z-index: 10;
    }

    .progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        width: 0%;
        transition: width 0.5s ease;
    }

    /* Header */
    .register-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        color: var(--white);
        padding: 50px 40px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .register-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1%, transparent 1%);
        background-size: 50px 50px;
        animation: wave 30s linear infinite;
    }

    .register-header::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 80px;
        background: var(--white);
        clip-path: ellipse(100% 60px at 50% 100%);
    }

    @keyframes wave {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .register-header h1 {
        font-size: 2.5rem;
        margin-bottom: 12px;
        font-weight: 700;
        letter-spacing: -0.5px;
        position: relative;
        z-index: 1;
    }

    .register-header p {
        font-size: 1.125rem;
        opacity: 0.9;
        margin-bottom: 8px;
        position: relative;
        z-index: 1;
    }

    .sinhala-text {
        font-size: 1.25rem;
        margin-top: 12px;
        color: rgba(255, 255, 255, 0.9);
        font-weight: 500;
        position: relative;
        z-index: 1;
    }

    .sri-lanka-flag {
        display: inline-block;
        font-size: 1.5rem;
        margin: 0 8px;
        animation: flagWave 3s ease-in-out infinite;
    }

    @keyframes flagWave {

        0%,
        100% {
            transform: rotate(0deg);
        }

        50% {
            transform: rotate(5deg);
        }
    }

    /* Form Container */
    .register-form {
        padding: 40px;
        background: var(--white);
    }

    /* Form Steps */
    .form-steps {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 40px;
        margin-top: -20px;
        position: relative;
    }

    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        z-index: 2;
    }

    .step-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--white);
        border: 3px solid var(--light-gray);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: var(--gray-color);
        transition: var(--transition);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .step.active .step-circle {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: var(--white);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }

    .step.completed .step-circle {
        background: var(--success-color);
        border-color: var(--success-color);
        color: var(--white);
    }

    .step-label {
        margin-top: 8px;
        font-size: 0.875rem;
        color: var(--gray-color);
        font-weight: 500;
    }

    .step.active .step-label {
        color: var(--primary-color);
        font-weight: 600;
    }

    .step-connector {
        position: absolute;
        top: 24px;
        width: calc(100% - 200px);
        height: 2px;
        background: var(--light-gray);
        z-index: 1;
    }

    .step-connector-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--success-color), var(--primary-color));
        width: 0%;
        transition: width 0.5s ease;
    }

    /* Form Sections */
    .form-section {
        display: none;
        animation: fadeInUp 0.4s ease;
    }

    .form-section.active {
        display: block;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .section-title {
        font-size: 1.375rem;
        font-weight: 600;
        color: var(--dark-color);
        margin-bottom: 24px;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }

    .section-title i {
        color: var(--primary-color);
        font-size: 1.5rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        position: relative;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: var(--dark-color);
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    label i {
        color: var(--primary-color);
        font-size: 0.875rem;
        opacity: 0.7;
    }

    .required {
        color: var(--danger-color);
        font-weight: 500;
    }

    /* Input Styling */
    .input-wrapper {
        position: relative;
    }

    input,
    select,
    textarea {
        width: 100%;
        padding: 12px 16px;
        padding-left: 40px;
        border: 1.5px solid #e5e7eb;
        border-radius: 10px;
        font-size: 0.9375rem;
        transition: var(--transition);
        background: var(--lighter-gray);
        color: var(--dark-color);
    }

    input:hover,
    select:hover,
    textarea:hover {
        border-color: #d1d5db;
        background: var(--white);
    }

    input:focus,
    select:focus,
    textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        background: var(--white);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-color);
        font-size: 1rem;
        transition: var(--transition);
        opacity: 0.6;
    }

    input:focus+.input-icon,
    select:focus+.input-icon {
        color: var(--primary-color);
        opacity: 1;
    }

    .input-suffix {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-color);
        font-size: 1rem;
        cursor: pointer;
        transition: var(--transition);
        opacity: 0.6;
    }

    .input-suffix:hover {
        color: var(--primary-color);
        opacity: 1;
    }

    .input-help {
        font-size: 0.8125rem;
        color: var(--gray-color);
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .input-help i {
        font-size: 0.75rem;
        opacity: 0.7;
    }

    /* User Type Selection */
    .user-type-selector {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
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
        padding: 24px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--lighter-gray);
        position: relative;
        overflow: hidden;
    }

    .user-type-label::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        opacity: 0;
        transition: var(--transition);
    }

    .user-type-label:hover {
        border-color: var(--primary-light);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        background: var(--white);
    }

    .user-type-option input[type="radio"]:checked+.user-type-label {
        border-color: var(--primary-color);
        background: var(--white);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .user-type-option input[type="radio"]:checked+.user-type-label .user-type-icon {
        color: var(--primary-color);
        transform: scale(1.1);
    }

    .user-type-content {
        position: relative;
        z-index: 1;
    }

    .user-type-icon {
        font-size: 2.5rem;
        margin-bottom: 12px;
        display: block;
        transition: var(--transition);
        color: var(--gray-color);
    }

    .user-type-title {
        font-weight: 600;
        margin-bottom: 4px;
        font-size: 1.125rem;
        color: var(--dark-color);
    }

    .user-type-desc {
        font-size: 0.875rem;
        color: var(--gray-color);
    }

    .user-type-desc-si {
        font-size: 0.8125rem;
        color: var(--gray-color);
        margin-top: 4px;
        font-style: italic;
        opacity: 0.8;
    }

    /* Shelter Fields */
    .shelter-fields {
        display: none;
        padding: 24px;
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f0f9ff 100%);
        border-radius: 12px;
        margin-top: 24px;
        border: 1px solid #bae6fd;
        position: relative;
    }

    .shelter-fields::before {
        content: '🏠';
        position: absolute;
        top: -10px;
        right: 20px;
        font-size: 60px;
        opacity: 0.1;
    }

    .shelter-fields.show {
        display: block;
        animation: fadeInUp 0.4s ease;
    }

    .shelter-fields h4 {
        color: var(--primary-dark);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.125rem;
        font-weight: 600;
    }

    /* Buttons */
    .form-navigation {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        margin-top: 32px;
    }

    .btn {
        padding: 14px 28px;
        border: none;
        border-radius: 10px;
        font-size: 0.9375rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        position: relative;
        overflow: hidden;
    }

    .btn-primary {
        background: var(--primary-color);
        color: var(--white);
        flex: 1;
    }

    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }

    .btn-secondary {
        background: var(--white);
        color: var(--dark-color);
        border: 1px solid #e5e7eb;
        min-width: 120px;
    }

    .btn-secondary:hover {
        background: var(--light-gray);
        border-color: #d1d5db;
    }

    .btn:disabled {
        background: #e5e7eb;
        color: #9ca3af;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }

    /* Error Messages */
    .error-container {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .error-container ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .error-container li {
        color: #dc2626;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.875rem;
    }

    .error-container li i {
        font-size: 1rem;
    }

    /* Login Link */
    .login-link {
        text-align: center;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid var(--light-gray);
    }

    .login-link p {
        color: var(--gray-color);
        margin-bottom: 4px;
        font-size: 0.875rem;
    }

    .login-link a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
    }

    .login-link a:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }

    /* Back Home Button */
    .back-home {
        position: fixed;
        top: 20px;
        left: 20px;
        background: var(--white);
        color: var(--primary-color);
        padding: 10px 20px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.875rem;
        transition: var(--transition);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        gap: 6px;
        z-index: 100;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .back-home:hover {
        transform: translateX(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    /* Loading Spinner */
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: var(--white);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Password Strength Indicator */
    .password-strength {
        margin-top: 6px;
        height: 3px;
        background: var(--light-gray);
        border-radius: 2px;
        overflow: hidden;
    }

    .password-strength-bar {
        height: 100%;
        width: 0%;
        transition: width 0.3s, background-color 0.3s;
        border-radius: 2px;
    }

    .password-strength-bar.weak {
        width: 33%;
        background-color: var(--danger-color);
    }

    .password-strength-bar.medium {
        width: 66%;
        background-color: var(--warning-color);
    }

    .password-strength-bar.strong {
        width: 100%;
        background-color: var(--success-color);
    }

    .password-strength-text {
        font-size: 0.75rem;
        margin-top: 4px;
        font-weight: 500;
    }

    .password-strength-text.weak {
        color: var(--danger-color);
    }

    .password-strength-text.medium {
        color: var(--warning-color);
    }

    .password-strength-text.strong {
        color: var(--success-color);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .register-container {
            margin: 10px;
            max-width: 100%;
            border-radius: 16px;
        }

        .register-header {
            padding: 40px 20px;
        }

        .register-header h1 {
            font-size: 2rem;
        }

        .register-form {
            padding: 24px 20px;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-steps {
            transform: scale(0.85);
        }

        .step-connector {
            width: calc(100% - 170px);
        }

        .user-type-selector {
            grid-template-columns: 1fr;
        }

        .back-home {
            position: relative;
            top: auto;
            left: auto;
            margin-bottom: 16px;
        }

        .form-navigation {
            flex-direction: column-reverse;
        }

        .btn {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .register-header h1 {
            font-size: 1.5rem;
        }

        .register-header p {
            font-size: 0.9375rem;
        }

        .sinhala-text {
            font-size: 1rem;
        }

        .form-steps {
            transform: scale(0.75);
            margin-bottom: 24px;
        }
    }

    /* Print Styles */
    @media print {
        body {
            background: white;
        }

        .register-container {
            box-shadow: none;
            border: 1px solid #ddd;
        }

        .back-home,
        .floating-element,
        .progress-bar {
            display: none;
        }
    }

    /* Accessibility */
    *:focus-visible {
        outline: 2px solid var(--primary-color);
        outline-offset: 2px;
    }

    /* Reduced Motion */
    @media (prefers-reduced-motion: reduce) {
        * {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }
    </style>
</head>

<body>
    <!-- Floating Background Elements -->
    <div class="floating-element">🐕</div>
    <div class="floating-element">🐈</div>
    <div class="floating-element">🐾</div>

    <a href="<?php echo $BASE_URL; ?>index.php" class="back-home">
        <i class="fas fa-arrow-left"></i> Back to Home
    </a>

    <div class="register-container">
        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-bar-fill" id="progressBar"></div>
        </div>

        <div class="register-header">
            <h1><i class="fas fa-paw"></i> Join Our Pet-Loving Community <span class="sri-lanka-flag">🇱🇰</span></h1>
            <p>Create your account and help save lives in Sri Lanka</p>
            <div class="sinhala-text">ශ්‍රී ලංකාවේ සුරතල් සත්ව රැකවරණය</div>
        </div>

        <div class="register-form">
            <!-- Form Steps Indicator -->
            <div class="form-steps">
                <div class="step active" data-step="1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Account Type</div>
                </div>
                <div class="step-connector">
                    <div class="step-connector-fill" id="stepConnector1"></div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Personal Info</div>
                </div>
                <div class="step-connector">
                    <div class="step-connector-fill" id="stepConnector2"></div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Location</div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="error-container">
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <!-- Step 1: Account Type -->
                <div class="form-section active" data-section="1">
                    <h3 class="section-title">
                        <i class="fas fa-user-tag"></i>
                        Choose Your Account Type
                    </h3>

                    <div class="user-type-selector">
                        <div class="user-type-option">
                            <input type="radio" id="adopter" name="user_type" value="adopter"
                                <?php echo $form_data['user_type'] === 'adopter' ? 'checked' : ''; ?>>
                            <label for="adopter" class="user-type-label">
                                <div class="user-type-content">
                                    <i class="fas fa-heart user-type-icon"></i>
                                    <div class="user-type-title">Pet Adopter</div>
                                    <div class="user-type-desc">Find your perfect companion</div>
                                    <div class="user-type-desc-si">සුරතල් සතුන් හදාගන්න</div>
                                </div>
                            </label>
                        </div>
                        <div class="user-type-option">
                            <input type="radio" id="shelter" name="user_type" value="shelter"
                                <?php echo $form_data['user_type'] === 'shelter' ? 'checked' : ''; ?>>
                            <label for="shelter" class="user-type-label">
                                <div class="user-type-content">
                                    <i class="fas fa-home user-type-icon"></i>
                                    <div class="user-type-title">Shelter/Rescue</div>
                                    <div class="user-type-desc">Help pets find homes</div>
                                    <div class="user-type-desc-si">සත්ව නවාතැන්</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Shelter-specific fields -->
                    <div class="shelter-fields" id="shelterFields">
                        <h4><i class="fas fa-building"></i> Shelter Information</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="shelter_name">
                                    <i class="fas fa-signature"></i>
                                    Shelter/Rescue Name <span class="required">*</span>
                                </label>
                                <div class="input-wrapper">
                                    <input type="text" id="shelter_name" name="shelter_name"
                                        value="<?php echo htmlspecialchars($form_data['shelter_name']); ?>"
                                        placeholder="e.g., Colombo Animal Shelter">
                                    <i class="fas fa-building input-icon"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="shelter_license">
                                    <i class="fas fa-certificate"></i>
                                    License Number <span class="required">*</span>
                                </label>
                                <div class="input-wrapper">
                                    <input type="text" id="shelter_license" name="shelter_license"
                                        value="<?php echo htmlspecialchars($form_data['shelter_license']); ?>"
                                        placeholder="Government registration">
                                    <i class="fas fa-id-card input-icon"></i>
                                </div>
                            </div>
                            <div class="form-group full-width">
                                <label for="shelter_capacity">
                                    <i class="fas fa-users"></i>
                                    Shelter Capacity <span class="required">*</span>
                                </label>
                                <div class="input-wrapper">
                                    <input type="number" id="shelter_capacity" name="shelter_capacity"
                                        value="<?php echo htmlspecialchars($form_data['shelter_capacity']); ?>"
                                        placeholder="Maximum animals" min="1">
                                    <i class="fas fa-paw input-icon"></i>
                                </div>
                                <div class="input-help">
                                    <i class="fas fa-info-circle"></i>
                                    Maximum number of animals your shelter can accommodate
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Personal Information -->
                <div class="form-section" data-section="2">
                    <h3 class="section-title">
                        <i class="fas fa-user-circle"></i>
                        Personal Information
                    </h3>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">
                                <i class="fas fa-at"></i>
                                Username <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="username" name="username"
                                    value="<?php echo htmlspecialchars($form_data['username']); ?>"
                                    placeholder="Choose a unique username" required>
                                <i class="fas fa-user input-icon"></i>
                            </div>
                            <div class="input-help">
                                <i class="fas fa-info-circle"></i>
                                Letters, numbers, and underscores only (min 3 chars)
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i>
                                Email Address <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="email" id="email" name="email"
                                    value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                    placeholder="your@email.com" required>
                                <i class="fas fa-envelope input-icon"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="first_name">
                                <i class="fas fa-user"></i>
                                First Name <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="first_name" name="first_name"
                                    value="<?php echo htmlspecialchars($form_data['first_name']); ?>"
                                    placeholder="Your first name" required>
                                <i class="fas fa-user input-icon"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="last_name">
                                <i class="fas fa-user"></i>
                                Last Name <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="last_name" name="last_name"
                                    value="<?php echo htmlspecialchars($form_data['last_name']); ?>"
                                    placeholder="Your last name" required>
                                <i class="fas fa-user input-icon"></i>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label for="phone">
                                <i class="fas fa-phone"></i>
                                Phone Number <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="tel" id="phone" name="phone"
                                    value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                    placeholder="071 234 5678" required>
                                <i class="fas fa-phone input-icon"></i>
                            </div>
                            <div class="input-help">
                                <i class="fas fa-info-circle"></i>
                                Format: 071 234 5678 or +94 71 234 5678
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-lock"></i>
                                Password <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="password" id="password" name="password"
                                    placeholder="Choose a strong password" required>
                                <i class="fas fa-lock input-icon"></i>
                                <i class="fas fa-eye input-suffix password-toggle"
                                    onclick="togglePassword('password')"></i>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <div class="password-strength-text" id="passwordStrengthText"></div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-lock"></i>
                                Confirm Password <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password"
                                    placeholder="Confirm your password" required>
                                <i class="fas fa-lock input-icon"></i>
                                <i class="fas fa-eye input-suffix password-toggle"
                                    onclick="togglePassword('confirm_password')"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Location Information -->
                <div class="form-section" data-section="3">
                    <h3 class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Location Details
                    </h3>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="address">
                                <i class="fas fa-road"></i>
                                Street Address <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="address" name="address"
                                    value="<?php echo htmlspecialchars($form_data['address']); ?>"
                                    placeholder="No. 123, Main Street" required>
                                <i class="fas fa-home input-icon"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="city">
                                <i class="fas fa-city"></i>
                                City <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="city" name="city"
                                    value="<?php echo htmlspecialchars($form_data['city']); ?>"
                                    placeholder="e.g., Colombo, Kandy" required>
                                <i class="fas fa-city input-icon"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="district">
                                <i class="fas fa-map"></i>
                                District <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <select id="district" name="district" required>
                                    <option value="">Select District</option>
                                    <optgroup label="Western Province">
                                        <option value="Colombo"
                                            <?php echo $form_data['district'] === 'Colombo' ? 'selected' : ''; ?>>
                                            Colombo</option>
                                        <option value="Gampaha"
                                            <?php echo $form_data['district'] === 'Gampaha' ? 'selected' : ''; ?>>
                                            Gampaha</option>
                                        <option value="Kalutara"
                                            <?php echo $form_data['district'] === 'Kalutara' ? 'selected' : ''; ?>>
                                            Kalutara</option>
                                    </optgroup>
                                    <optgroup label="Central Province">
                                        <option value="Kandy"
                                            <?php echo $form_data['district'] === 'Kandy' ? 'selected' : ''; ?>>Kandy
                                        </option>
                                        <option value="Matale"
                                            <?php echo $form_data['district'] === 'Matale' ? 'selected' : ''; ?>>Matale
                                        </option>
                                        <option value="Nuwara Eliya"
                                            <?php echo $form_data['district'] === 'Nuwara Eliya' ? 'selected' : ''; ?>>
                                            Nuwara Eliya</option>
                                    </optgroup>
                                    <optgroup label="Southern Province">
                                        <option value="Galle"
                                            <?php echo $form_data['district'] === 'Galle' ? 'selected' : ''; ?>>Galle
                                        </option>
                                        <option value="Matara"
                                            <?php echo $form_data['district'] === 'Matara' ? 'selected' : ''; ?>>Matara
                                        </option>
                                        <option value="Hambantota"
                                            <?php echo $form_data['district'] === 'Hambantota' ? 'selected' : ''; ?>>
                                            Hambantota</option>
                                    </optgroup>
                                    <optgroup label="Northern Province">
                                        <option value="Jaffna"
                                            <?php echo $form_data['district'] === 'Jaffna' ? 'selected' : ''; ?>>Jaffna
                                        </option>
                                        <option value="Kilinochchi"
                                            <?php echo $form_data['district'] === 'Kilinochchi' ? 'selected' : ''; ?>>
                                            Kilinochchi</option>
                                        <option value="Mannar"
                                            <?php echo $form_data['district'] === 'Mannar' ? 'selected' : ''; ?>>Mannar
                                        </option>
                                        <option value="Mullaitivu"
                                            <?php echo $form_data['district'] === 'Mullaitivu' ? 'selected' : ''; ?>>
                                            Mullaitivu</option>
                                        <option value="Vavuniya"
                                            <?php echo $form_data['district'] === 'Vavuniya' ? 'selected' : ''; ?>>
                                            Vavuniya</option>
                                    </optgroup>
                                    <optgroup label="Eastern Province">
                                        <option value="Batticaloa"
                                            <?php echo $form_data['district'] === 'Batticaloa' ? 'selected' : ''; ?>>
                                            Batticaloa</option>
                                        <option value="Ampara"
                                            <?php echo $form_data['district'] === 'Ampara' ? 'selected' : ''; ?>>Ampara
                                        </option>
                                        <option value="Trincomalee"
                                            <?php echo $form_data['district'] === 'Trincomalee' ? 'selected' : ''; ?>>
                                            Trincomalee</option>
                                    </optgroup>
                                    <optgroup label="North Western Province">
                                        <option value="Kurunegala"
                                            <?php echo $form_data['district'] === 'Kurunegala' ? 'selected' : ''; ?>>
                                            Kurunegala</option>
                                        <option value="Puttalam"
                                            <?php echo $form_data['district'] === 'Puttalam' ? 'selected' : ''; ?>>
                                            Puttalam</option>
                                    </optgroup>
                                    <optgroup label="North Central Province">
                                        <option value="Anuradhapura"
                                            <?php echo $form_data['district'] === 'Anuradhapura' ? 'selected' : ''; ?>>
                                            Anuradhapura</option>
                                        <option value="Polonnaruwa"
                                            <?php echo $form_data['district'] === 'Polonnaruwa' ? 'selected' : ''; ?>>
                                            Polonnaruwa</option>
                                    </optgroup>
                                    <optgroup label="Uva Province">
                                        <option value="Badulla"
                                            <?php echo $form_data['district'] === 'Badulla' ? 'selected' : ''; ?>>
                                            Badulla</option>
                                        <option value="Monaragala"
                                            <?php echo $form_data['district'] === 'Monaragala' ? 'selected' : ''; ?>>
                                            Monaragala</option>
                                    </optgroup>
                                    <optgroup label="Sabaragamuwa Province">
                                        <option value="Ratnapura"
                                            <?php echo $form_data['district'] === 'Ratnapura' ? 'selected' : ''; ?>>
                                            Ratnapura</option>
                                        <option value="Kegalle"
                                            <?php echo $form_data['district'] === 'Kegalle' ? 'selected' : ''; ?>>
                                            Kegalle</option>
                                    </optgroup>
                                </select>
                                <i class="fas fa-map input-icon"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="province">
                                <i class="fas fa-flag"></i>
                                Province <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <select id="province" name="province" required>
                                    <option value="">Select Province</option>
                                    <option value="Western"
                                        <?php echo $form_data['province'] === 'Western' ? 'selected' : ''; ?>>Western
                                        Province</option>
                                    <option value="Central"
                                        <?php echo $form_data['province'] === 'Central' ? 'selected' : ''; ?>>Central
                                        Province</option>
                                    <option value="Southern"
                                        <?php echo $form_data['province'] === 'Southern' ? 'selected' : ''; ?>>Southern
                                        Province</option>
                                    <option value="Northern"
                                        <?php echo $form_data['province'] === 'Northern' ? 'selected' : ''; ?>>Northern
                                        Province</option>
                                    <option value="Eastern"
                                        <?php echo $form_data['province'] === 'Eastern' ? 'selected' : ''; ?>>Eastern
                                        Province</option>
                                    <option value="North Western"
                                        <?php echo $form_data['province'] === 'North Western' ? 'selected' : ''; ?>>
                                        North Western Province</option>
                                    <option value="North Central"
                                        <?php echo $form_data['province'] === 'North Central' ? 'selected' : ''; ?>>
                                        North Central Province</option>
                                    <option value="Uva"
                                        <?php echo $form_data['province'] === 'Uva' ? 'selected' : ''; ?>>Uva Province
                                    </option>
                                    <option value="Sabaragamuwa"
                                        <?php echo $form_data['province'] === 'Sabaragamuwa' ? 'selected' : ''; ?>>
                                        Sabaragamuwa Province</option>
                                </select>
                                <i class="fas fa-flag input-icon"></i>
                            </div>
                            <div class="input-help">
                                <i class="fas fa-info-circle"></i>
                                Province will be auto-selected based on district
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="form-navigation">
                    <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeStep(-1)">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" style="display:none;">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </div>
            </form>

            <div class="login-link">
                <p>Already have an account?</p>
                <a href="<?php echo $BASE_URL; ?>auth/login.php">Sign in here</a>
                <p>දැනටමත් ගිණුමක් තිබේද? <a href="<?php echo $BASE_URL; ?>auth/login.php">මෙතනින් ඇතුල් වන්න</a></p>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let currentStep = 1;
        const totalSteps = 3;
        const form = document.getElementById('registerForm');
        const submitBtn = document.getElementById('submitBtn');
        const nextBtn = document.getElementById('nextBtn');
        const prevBtn = document.getElementById('prevBtn');
        const progressBar = document.getElementById('progressBar');
        const userTypeInputs = document.querySelectorAll('input[name="user_type"]');
        const shelterFields = document.getElementById('shelterFields');
        const districtSelect = document.getElementById('district');
        const provinceSelect = document.getElementById('province');

        // Initialize
        updateStep();

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

        // Change step function
        window.changeStep = function(direction) {
            // Validate current step before moving forward
            if (direction > 0 && !validateStep(currentStep)) {
                return;
            }

            currentStep += direction;

            if (currentStep < 1) {
                currentStep = 1;
            } else if (currentStep > totalSteps) {
                currentStep = totalSteps;
            }

            updateStep();
        };

        // Update step display
        function updateStep() {
            // Update sections
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            document.querySelector(`.form-section[data-section="${currentStep}"]`).classList.add('active');

            // Update step indicators
            document.querySelectorAll('.step').forEach((step, index) => {
                const stepNum = index + 1;
                step.classList.remove('active', 'completed');
                if (stepNum === currentStep) {
                    step.classList.add('active');
                } else if (stepNum < currentStep) {
                    step.classList.add('completed');
                    step.querySelector('.step-circle').innerHTML = '<i class="fas fa-check"></i>';
                }
            });

            // Update progress bar
            const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressBar.style.width = progress + '%';

            // Update step connectors
            for (let i = 1; i < totalSteps; i++) {
                const connector = document.getElementById(`stepConnector${i}`);
                if (connector) {
                    if (i < currentStep) {
                        connector.style.width = '100%';
                    } else {
                        connector.style.width = '0%';
                    }
                }
            }

            // Update buttons
            prevBtn.style.display = currentStep === 1 ? 'none' : 'block';
            if (currentStep === totalSteps) {
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'block';
            } else {
                nextBtn.style.display = 'block';
                submitBtn.style.display = 'none';
            }
        }

        // Validate step
        function validateStep(step) {
            let isValid = true;
            const section = document.querySelector(`.form-section[data-section="${step}"]`);
            const inputs = section.querySelectorAll('input[required], select[required]');

            // Clear previous error styling
            inputs.forEach(input => {
                input.style.borderColor = '#e5e7eb';
            });

            // Special validation for step 1 (user type)
            if (step === 1) {
                const userType = document.querySelector('input[name="user_type"]:checked');
                if (!userType) {
                    alert('Please select an account type');
                    return false;
                }

                // If shelter is selected, validate shelter fields
                if (userType.value === 'shelter') {
                    const shelterInputs = shelterFields.querySelectorAll('input[required]');
                    shelterInputs.forEach(input => {
                        if (!input.value.trim()) {
                            input.style.borderColor = '#ef4444';
                            isValid = false;
                        }
                    });
                }
            }

            // Validate required fields
            inputs.forEach(input => {
                if (input.type === 'radio') {
                    const radioGroup = document.querySelectorAll(`input[name="${input.name}"]`);
                    const isChecked = Array.from(radioGroup).some(radio => radio.checked);
                    if (!isChecked) {
                        isValid = false;
                    }
                } else if (!input.value.trim()) {
                    input.style.borderColor = '#ef4444';
                    isValid = false;
                }
            });

            // Step-specific validation
            if (step === 2) {
                // Username validation
                const username = document.getElementById('username');
                if (username.value.length < 3 || !/^[a-zA-Z0-9_]+$/.test(username.value)) {
                    username.style.borderColor = '#ef4444';
                    isValid = false;
                }

                // Email validation
                const email = document.getElementById('email');
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                    email.style.borderColor = '#ef4444';
                    isValid = false;
                }

                // Password validation
                const password = document.getElementById('password');
                const confirmPassword = document.getElementById('confirm_password');
                if (password.value.length < 6) {
                    password.style.borderColor = '#ef4444';
                    isValid = false;
                }
                if (password.value !== confirmPassword.value) {
                    confirmPassword.style.borderColor = '#ef4444';
                    isValid = false;
                }

                // Phone validation
                const phone = document.getElementById('phone');
                const cleanPhone = phone.value.replace(/[\s\-\(\)]/g, '');

                // Allow formats: 0XXXXXXXXX, XXXXXXXXX, +94XXXXXXXXX, 94XXXXXXXXX
                const phoneRegex = /^(?:(?:\+?94)|0)?[0-9]{9}$/;

                if (!phoneRegex.test(cleanPhone)) {
                    phone.style.borderColor = '#ef4444';
                    isValid = false;
                }
            }

            if (!isValid) {
                alert('Please fill in all required fields correctly');
            }

            return isValid;
        }

        // Password strength checker
        const passwordInput = document.getElementById('password');
        const passwordStrengthBar = document.getElementById('passwordStrengthBar');
        const passwordStrengthText = document.getElementById('passwordStrengthText');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            // Length
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;

            // Contains number
            if (/\d/.test(password)) strength++;

            // Contains lowercase
            if (/[a-z]/.test(password)) strength++;

            // Contains uppercase
            if (/[A-Z]/.test(password)) strength++;

            // Contains special character
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            // Update UI
            passwordStrengthBar.className = 'password-strength-bar';
            passwordStrengthText.className = 'password-strength-text';

            if (password.length === 0) {
                passwordStrengthBar.style.width = '0%';
                passwordStrengthText.textContent = '';
            } else if (strength <= 2) {
                passwordStrengthBar.classList.add('weak');
                passwordStrengthText.classList.add('weak');
                passwordStrengthText.textContent = 'Weak password';
            } else if (strength <= 4) {
                passwordStrengthBar.classList.add('medium');
                passwordStrengthText.classList.add('medium');
                passwordStrengthText.textContent = 'Medium strength';
            } else {
                passwordStrengthBar.classList.add('strong');
                passwordStrengthText.classList.add('strong');
                passwordStrengthText.textContent = 'Strong password';
            }
        });

        // Phone number formatting
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');

            if (value.startsWith('94')) {
                if (value.length <= 11) {
                    value = value.replace(/(\d{2})(\d{2})(\d{3})(\d+)/, '+$1 $2 $3 $4');
                }
            } else if (value.startsWith('0')) {
                if (value.length <= 10) {
                    value = value.replace(/(\d{3})(\d{3})(\d+)/, '$1 $2 $3');
                }
            } else if (value.length >= 9) {
                value = value.replace(/(\d{2})(\d{3})(\d+)/, '$1 $2 $3');
            }

            e.target.value = value;
        });

        // Real-time validation
        const confirmPasswordInput = document.getElementById('confirm_password');
        confirmPasswordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = this.value;

            if (confirmPassword.length > 0) {
                if (password !== confirmPassword) {
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = '#10b981';
                }
            }
        });

        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate all steps
            for (let i = 1; i <= totalSteps; i++) {
                currentStep = i;
                if (!validateStep(i)) {
                    updateStep();
                    return false;
                }
            }

            // Show loading state
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Creating Account...';
            submitBtn.disabled = true;

            // Submit the form
            setTimeout(() => {
                form.submit();
            }, 500);
        });

        // Auto-save form data to localStorage
        const formInputs = document.querySelectorAll('input, select');

        // Load saved data
        formInputs.forEach(input => {
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
        });

        // Save data on change
        formInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.type !== 'password') {
                    localStorage.setItem(`register_${this.name}`, this.value);
                }
            });
        });

        // Clear saved data on successful submission
        form.addEventListener('submit', function() {
            setTimeout(() => {
                formInputs.forEach(input => {
                    localStorage.removeItem(`register_${input.name}`);
                });
            }, 1000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Enter to go next
            if (e.key === 'Enter' && !e.shiftKey && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                if (currentStep < totalSteps) {
                    changeStep(1);
                } else if (currentStep === totalSteps) {
                    form.dispatchEvent(new Event('submit'));
                }
            }

            // Shift + Enter to go previous
            if (e.key === 'Enter' && e.shiftKey) {
                e.preventDefault();
                if (currentStep > 1) {
                    changeStep(-1);
                }
            }
        });

        // Focus management
        function focusFirstInput() {
            const activeSection = document.querySelector('.form-section.active');
            const firstInput = activeSection.querySelector('input:not([type="radio"]), select');
            if (firstInput) {
                firstInput.focus();
            }
        }

        // Focus on step change
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const target = mutation.target;
                    if (target.classList.contains('form-section') && target.classList.contains(
                            'active')) {
                        setTimeout(focusFirstInput, 100);
                    }
                }
            });
        });

        document.querySelectorAll('.form-section').forEach(section => {
            observer.observe(section, {
                attributes: true
            });
        });

        // Initial focus
        focusFirstInput();
    });

    // Password visibility toggle
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = field.parentElement.querySelector('.password-toggle');

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

    // Add smooth scrolling
    function smoothScroll(target, duration = 500) {
        const targetPosition = target.getBoundingClientRect().top + window.pageYOffset;
        const startPosition = window.pageYOffset;
        const distance = targetPosition - startPosition;
        let startTime = null;

        function animation(currentTime) {
            if (startTime === null) startTime = currentTime;
            const timeElapsed = currentTime - startTime;
            const run = ease(timeElapsed, startPosition, distance, duration);
            window.scrollTo(0, run);
            if (timeElapsed < duration) requestAnimationFrame(animation);
        }

        function ease(t, b, c, d) {
            t /= d / 2;
            if (t < 1) return c / 2 * t * t + b;
            t--;
            return -c / 2 * (t * (t - 2) - 1) + b;
        }

        requestAnimationFrame(animation);
    }

    // Add animation on scroll
    const animateOnScroll = () => {
        const elements = document.querySelectorAll('.form-section, .user-type-option');

        elements.forEach(element => {
            const elementTop = element.getBoundingClientRect().top;
            const elementBottom = element.getBoundingClientRect().bottom;

            if (elementTop < window.innerHeight && elementBottom > 0) {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }
        });
    };

    window.addEventListener('scroll', animateOnScroll);
    animateOnScroll();

    // Input field animations
    document.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('focus', function() {
            const label = this.closest('.form-group')?.querySelector('label');
            if (label) {
                label.style.color = 'var(--primary-color)';
            }
        });

        field.addEventListener('blur', function() {
            const label = this.closest('.form-group')?.querySelector('label');
            if (label) {
                label.style.color = 'var(--dark-color)';
            }
        });
    });

    // Add hover effects
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-1px)';
        });

        button.addEventListener('mouseleave', function() {
            if (!this.disabled) {
                this.style.transform = 'translateY(0)';
            }
        });
    });

    // Add loading animation to page
    window.addEventListener('load', function() {
        document.body.style.opacity = '1';

        // Remove any loading screens
        const loader = document.querySelector('.page-loader');
        if (loader) {
            loader.style.display = 'none';
        }

        // Trigger initial animations
        document.querySelectorAll('.form-section, .step').forEach((el, index) => {
            setTimeout(() => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });

    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    // Add tooltip functionality
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        const tooltipText = element.getAttribute('data-tooltip');

        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip-popup';
            tooltip.textContent = tooltipText;
            document.body.appendChild(tooltip);

            const rect = this.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();

            tooltip.style.top = (rect.top - tooltipRect.height - 10) + 'px';
            tooltip.style.left = (rect.left + (rect.width - tooltipRect.width) / 2) + 'px';

            // Ensure tooltip stays within viewport
            if (tooltip.getBoundingClientRect().left < 0) {
                tooltip.style.left = '10px';
            }
            if (tooltip.getBoundingClientRect().right > window.innerWidth) {
                tooltip.style.left = (window.innerWidth - tooltipRect.width - 10) + 'px';
            }
        });

        element.addEventListener('mouseleave', function() {
            const tooltip = document.querySelector('.tooltip-popup');
            if (tooltip) {
                tooltip.remove();
            }
        });
    });

    // Session timeout warning (optional)
    let sessionTimeout;
    let warningTimeout;

    function resetSessionTimer() {
        clearTimeout(sessionTimeout);
        clearTimeout(warningTimeout);

        // Show warning after 25 minutes
        warningTimeout = setTimeout(() => {
            if (confirm('Your session is about to expire. Do you want to continue?')) {
                resetSessionTimer();
            }
        }, 25 * 60 * 1000);

        // Logout after 30 minutes
        sessionTimeout = setTimeout(() => {
            window.location.href = '<?php echo $BASE_URL; ?>auth/logout.php';
        }, 30 * 60 * 1000);
    }

    // Reset timer on user activity
    ['mousedown', 'keypress', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, resetSessionTimer, true);
    });

    resetSessionTimer();

    // Handle browser back button
    window.addEventListener('popstate', function(e) {
        if (currentStep > 1) {
            currentStep--;
            updateStep();
        }
    });

    // Add form progress to URL (optional)
    function updateURL() {
        const url = new URL(window.location);
        url.searchParams.set('step', currentStep);
        window.history.replaceState({
            step: currentStep
        }, '', url);
    }

    // Restore form state from URL
    const urlParams = new URLSearchParams(window.location.search);
    const savedStep = urlParams.get('step');
    if (savedStep && !isNaN(savedStep)) {
        currentStep = Math.min(Math.max(1, parseInt(savedStep)), totalSteps);
        updateStep();
    }
    </script>

    <!-- Additional styles for animations and improvements -->
    <style>
    /* Loading state */
    body {
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    body.loaded {
        opacity: 1;
    }

    /* Tooltip styles */
    .tooltip-popup {
        position: fixed;
        background: var(--dark-color);
        color: var(--white);
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 0.8125rem;
        z-index: 10000;
        pointer-events: none;
        animation: tooltipFadeIn 0.2s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    @keyframes tooltipFadeIn {
        from {
            opacity: 0;
            transform: translateY(5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Page loader */
    .page-loader {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--white);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    /* Success animation overlay */
    .success-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }

    .success-modal {
        background: var(--white);
        padding: 40px;
        border-radius: 20px;
        text-align: center;
        animation: scaleIn 0.5s ease;
    }

    @keyframes scaleIn {
        from {
            transform: scale(0.8);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    /* Input autofill styles */
    input:-webkit-autofill,
    input:-webkit-autofill:hover,
    input:-webkit-autofill:focus,
    input:-webkit-autofill:active {
        -webkit-box-shadow: 0 0 0 30px var(--lighter-gray) inset !important;
        -webkit-text-fill-color: var(--dark-color) !important;
        transition: background-color 5000s ease-in-out 0s;
    }

    /* Smooth transitions for all interactive elements */
    .form-section,
    .step,
    .btn,
    input,
    select,
    textarea {
        transition: var(--transition);
    }

    /* Enhanced focus states for accessibility */
    input:focus,
    select:focus,
    textarea:focus,
    button:focus {
        outline: 2px solid transparent;
        outline-offset: 2px;
    }

    input:focus-visible,
    select:focus-visible,
    textarea:focus-visible,
    button:focus-visible {
        outline: 2px solid var(--primary-color);
        outline-offset: 2px;
    }

    /* Dark mode support (optional) */
    @media (prefers-color-scheme: dark) {
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary-color: #22d3ee;
            --secondary-light: #67e8f9;
            --dark-color: #f1f5f9;
            --gray-color: #94a3b8;
            --light-gray: #1e293b;
            --lighter-gray: #0f172a;
            --white: #0f172a;
        }

        body {
            background: #0f172a;
        }

        body::after {
            background:
                linear-gradient(to bottom, rgba(15, 23, 42, 0) 0%, rgba(15, 23, 42, 0.5) 100%),
                url('data:image/svg+xml,<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd"><g fill="%23334155" fill-opacity="0.05"><path d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/></g></g></svg>');
        }

        .register-container {
            background: #1e293b;
            border-color: #334155;
        }

        .register-header::after {
            background: #1e293b;
        }

        input,
        select,
        textarea {
            background: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }

        input:hover,
        select:hover,
        textarea:hover {
            background: #1e293b;
            border-color: #475569;
        }

        input:focus,
        select:focus,
        textarea:focus {
            background: #1e293b;
        }

        .user-type-label {
            background: #0f172a;
            border-color: #334155;
        }

        .user-type-label:hover {
            background: #1e293b;
        }

        .shelter-fields {
            background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #1e293b 100%);
            border-color: #475569;
        }

        .btn-secondary {
            background: #334155;
            color: #f1f5f9;
            border-color: #475569;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .error-container {
            background: #7f1d1d;
            border-color: #991b1b;
        }

        .tooltip-popup {
            background: #475569;
        }
    }

    /* Final animation states */
    .form-section {
        opacity: 0;
        transform: translateY(20px);
    }

    .form-section.active {
        opacity: 1;
        transform: translateY(0);
    }

    /* Print optimization */
    @media print {

        .back-home,
        .form-navigation,
        .floating-element,
        .progress-bar {
            display: none !important;
        }

        .register-container {
            box-shadow: none;
            max-width: 100%;
        }

        .form-section {
            display: block !important;
            opacity: 1 !important;
            transform: none !important;
            page-break-inside: avoid;
        }

        .step-circle {
            background: #fff !important;
            color: #000 !important;
        }
    }

    /* High contrast mode support */
    @media (prefers-contrast: high) {
        .register-container {
            border: 2px solid currentColor;
        }

        input,
        select,
        textarea {
            border-width: 2px;
        }

        .btn {
            border: 2px solid currentColor;
        }
    }
    </style>
</body>

</html>