<?php
// auth/login.php - User Login Page
// Put this file at: C:\wamp64\www\pet_care\auth\login.php

// Start session early
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Base URL for redirects and links
$BASE_URL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Load DB connection (path relative to this file)
require_once __DIR__ . '/../config/db.php';

// Helper: sanitize input
if (!function_exists('sanitize_input')) {
    function sanitize_input($v) {
        return trim(htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'));
    }
}

$page_title = 'Login';
$success_message = '';
$error_message = '';

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    $user_type = $_SESSION['user_type'];
    switch ($user_type) {
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

// Get any success message from session (like from registration)
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Basic validation
    if (empty($email)) {
        $error_message = "Email is required.";
    } elseif (empty($password)) {
        $error_message = "Password is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        try {
            // Get database connection
            $db = getDB();
            
            // Check credentials
            $stmt = $db->prepare("SELECT user_id, username, email, password_hash, user_type, first_name, last_name, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Check if account is active
                if (!$user['is_active']) {
                    $error_message = "Your account has been deactivated. Please contact support.";
                } else {
                    // Login successful - set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    
                    // Set remember me cookie if requested
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
                    }
                    
                    // Redirect based on user type
                    switch ($user['user_type']) {
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
            } else {
                $error_message = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = "Login failed. Please try again.";
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = "Login failed. Please try again.";
        }
    }
}

// Include header AFTER any possible redirect
include __DIR__ . '/../common/header.php';
?>

<style>
/* Enhanced Login Page Styles */
.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 0;
    background: #f8f9fa;
    position: relative;
    overflow: hidden;
}

/* Animated Background */
.login-page::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(45deg,
            transparent 30%,
            rgba(99, 102, 241, 0.1) 30%,
            rgba(99, 102, 241, 0.1) 70%,
            transparent 70%);
    background-size: 100px 100px;
    animation: backgroundMove 20s linear infinite;
    pointer-events: none;
}

@keyframes backgroundMove {
    0% {
        transform: translate(0, 0);
    }

    100% {
        transform: translate(50px, 50px);
    }
}

/* Floating Shapes */
.shape {
    position: absolute;
    opacity: 0.1;
}

.shape-1 {
    top: 10%;
    left: 10%;
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
    animation: float1 15s ease-in-out infinite;
}

.shape-2 {
    top: 60%;
    right: 10%;
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    border-radius: 63% 37% 54% 46% / 55% 48% 52% 45%;
    animation: float2 20s ease-in-out infinite;
}

.shape-3 {
    bottom: 10%;
    left: 20%;
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    border-radius: 41% 59% 41% 59% / 41% 59% 41% 59%;
    animation: float3 18s ease-in-out infinite;
}

@keyframes float1 {

    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }

    50% {
        transform: translateY(-30px) rotate(180deg);
    }
}

@keyframes float2 {

    0%,
    100% {
        transform: translateX(0) rotate(0deg);
    }

    50% {
        transform: translateX(-30px) rotate(360deg);
    }
}

@keyframes float3 {

    0%,
    100% {
        transform: translate(0, 0) rotate(0deg);
    }

    33% {
        transform: translate(30px, -30px) rotate(120deg);
    }

    66% {
        transform: translate(-20px, 20px) rotate(240deg);
    }
}

.login-container {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

/* Login Card */
.login-card {
    background: white;
    border-radius: 30px;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.1);
    position: relative;
    animation: slideInLeft 0.8s ease-out;
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-50px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.login-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 50px 40px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.login-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 2px, transparent 2px);
    background-size: 30px 30px;
    animation: headerPattern 30s linear infinite;
}

@keyframes headerPattern {
    0% {
        transform: rotate(0deg);
    }

    100% {
        transform: rotate(360deg);
    }
}

.login-logo {
    width: 80px;
    height: 80px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    position: relative;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.login-logo i {
    font-size: 2.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.login-header h1 {
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 10px 0;
    color: white;
    position: relative;
}

.login-header p {
    margin: 0;
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    position: relative;
}

.login-body {
    padding: 50px 40px;
}

/* Form Styles */
.form-group {
    margin-bottom: 30px;
    position: relative;
}

.form-label {
    display: block;
    margin-bottom: 12px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 1rem;
    transition: color 0.3s ease;
}

.input-wrapper {
    position: relative;
}

.form-input {
    width: 100%;
    padding: 16px 20px 16px 50px;
    border: 2px solid #e1e8ed;
    border-radius: 15px;
    font-size: 1.05rem;
    transition: all 0.3s ease;
    background: #f8f9fa;
    box-sizing: border-box;
}

.input-icon {
    position: absolute;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #95a5a6;
    font-size: 1.2rem;
    transition: color 0.3s ease;
}

.form-input:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.form-input:focus~.input-icon {
    color: #667eea;
}

.form-input:valid~.input-icon {
    color: #10b981;
}

.form-input.error {
    border-color: #ef4444;
    background: #fef2f2;
}

.form-input.error~.input-icon {
    color: #ef4444;
}

/* Enhanced Form Options */
.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 25px 0 35px;
    flex-wrap: wrap;
    gap: 15px;
}

.remember-me {
    display: flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
    position: relative;
}

.remember-me input[type="checkbox"] {
    display: none;
}

.custom-checkbox {
    width: 22px;
    height: 22px;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    margin-right: 10px;
    position: relative;
    transition: all 0.3s ease;
}

.remember-me input[type="checkbox"]:checked~.custom-checkbox {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
}

.custom-checkbox::after {
    content: '\f00c';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0);
    color: white;
    font-size: 0.8rem;
    transition: transform 0.3s ease;
}

.remember-me input[type="checkbox"]:checked~.custom-checkbox::after {
    transform: translate(-50%, -50%) scale(1);
}

.remember-me-text {
    color: #6b7280;
    font-size: 0.95rem;
}

.forgot-password {
    color: #667eea;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 600;
    position: relative;
    transition: color 0.3s ease;
}

.forgot-password::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    bottom: -3px;
    left: 0;
    background: #667eea;
    transition: width 0.3s ease;
}

.forgot-password:hover {
    color: #5a67d8;
}

.forgot-password:hover::after {
    width: 100%;
}

/* Enhanced Login Button */
.login-btn {
    width: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 18px 30px;
    border: none;
    border-radius: 15px;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
}

.login-btn:active {
    transform: translateY(0);
}

.login-btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.login-btn:hover::before {
    width: 300px;
    height: 300px;
}

/* Divider */
.divider {
    text-align: center;
    margin: 40px 0 30px;
    position: relative;
}

.divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e1e8ed;
}

.divider span {
    background: white;
    padding: 0 20px;
    position: relative;
    color: #95a5a6;
    font-size: 0.95rem;
}

/* Register Link */
.register-link {
    text-align: center;
}

.register-link p {
    color: #6b7280;
    margin-bottom: 20px;
    font-size: 1.05rem;
}

.btn-register {
    display: inline-block;
    background: transparent;
    color: #667eea;
    padding: 14px 30px;
    border: 2px solid #667eea;
    border-radius: 15px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-register:hover {
    color: white;
    background: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

/* Info Panel */
.info-panel {
    padding: 40px;
    animation: slideInRight 0.8s ease-out;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(50px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.info-content {
    text-align: center;
}

.info-image {
    width: 100%;
    max-width: 500px;
    margin: 0 auto 40px;
    position: relative;
}

.info-image img {
    width: 100%;
    height: auto;
    border-radius: 30px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
}

.info-title {
    font-size: 3rem;
    font-weight: 800;
    margin-bottom: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.2;
}

.info-subtitle {
    font-size: 1.3rem;
    color: #6b7280;
    margin-bottom: 40px;
    line-height: 1.8;
}

.feature-list {
    list-style: none;
    padding: 0;
    margin: 0;
    text-align: left;
    max-width: 400px;
    margin: 0 auto;
}

.feature-list li {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-radius: 15px;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.feature-list li:hover {
    transform: translateX(10px);
    border-color: #e1e8ed;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.feature-list i {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    margin-right: 15px;
    flex-shrink: 0;
}

.feature-list span {
    color: #4b5563;
    font-size: 1.05rem;
}

/* Alerts */
.alert {
    padding: 18px 24px;
    border-radius: 15px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
    font-weight: 500;
    animation: slideDown 0.5s ease;
    position: relative;
    overflow: hidden;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.alert i {
    font-size: 1.3rem;
    flex-shrink: 0;
}

.alert-success {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #6ee7b7;
}

.alert-success::before {
    background: #10b981;
}

.alert-error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-error::before {
    background: #ef4444;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .login-container {
        grid-template-columns: 1fr;
        gap: 40px;
        padding: 20px;
    }

    .info-panel {
        display: none;
    }

    .login-card {
        max-width: 450px;
        margin: 0 auto;
    }

    .login-header {
        padding: 40px 30px;
    }

    .login-body {
        padding: 40px 30px;
    }

    .login-header h1 {
        font-size: 2rem;
    }

    .form-options {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .shape {
        display: none;
    }
}

/* Loading State */
.login-btn.loading {
    opacity: 0.8;
    cursor: not-allowed;
    pointer-events: none;
}

.login-btn.loading::after {
    content: "";
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: 10px;
    margin-top: -10px;
    border: 2px solid transparent;
    border-top-color: #ffffff;
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
</style>

<div class="login-page">
    <!-- Animated Background Shapes -->
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>

    <div class="login-container">
        <!-- Login Card -->
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-paw"></i>
                </div>
                <h1>Welcome Back</h1>
                <p>Sign in to continue to Pet Care Platform</p>
            </div>

            <div class="login-body">
                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success_message); ?></div>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" id="loginForm" novalidate>
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-wrapper">
                            <input type="email" id="email" name="email" class="form-input" required
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                placeholder="Enter your email address">
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" class="form-input" required
                                placeholder="Enter your password">
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember_me" id="remember_me">
                            <span class="custom-checkbox"></span>
                            <span class="remember-me-text">Remember me</span>
                        </label>
                        <a href="#" class="forgot-password" onclick="showForgotPassword()">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="login-btn" id="loginBtn">
                        Sign In
                    </button>
                </form>

                <div class="divider">
                    <span>OR</span>
                </div>

                <div class="register-link">
                    <p>Don't have an account yet?</p>
                    <a href="<?php echo $BASE_URL; ?>auth/register.php" class="btn-register">
                        <i class="fas fa-user-plus"></i> Create New Account
                    </a>
                </div>
            </div>
        </div>

        <!-- Info Panel -->
        <div class="info-panel">
            <div class="info-content">
                <div class="info-image">
                    <img src="<?php echo $BASE_URL; ?>assets/images/login-illustration.svg" alt="Pet Adoption"
                        onerror="this.style.display='none'">
                </div>

                <h2 class="info-title">Find Your Perfect Companion</h2>
                <p class="info-subtitle">
                    Join our community of pet lovers and give a furry friend their forever home.
                </p>

                <ul class="feature-list">
                    <li>
                        <i class="fas fa-heart"></i>
                        <span>Connect with trusted shelters</span>
                    </li>
                    <li>
                        <i class="fas fa-search"></i>
                        <span>Browse thousands of pets</span>
                    </li>
                    <li>
                        <i class="fas fa-shield-alt"></i>
                        <span>Safe & secure adoption process</span>
                    </li>
                    <li>
                        <i class="fas fa-users"></i>
                        <span>Join a caring community</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced Login Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    // Form validation
    form.addEventListener('submit', function(e) {
        const email = emailInput.value.trim();
        const password = passwordInput.value.trim();

        // Clear previous errors
        document.querySelectorAll('.form-input').forEach(input => {
            input.classList.remove('error');
        });

        let hasErrors = false;

        if (!email) {
            showError(emailInput, 'Email is required');
            hasErrors = true;
        } else if (!isValidEmail(email)) {
            showError(emailInput, 'Please enter a valid email');
            hasErrors = true;
        }

        if (!password) {
            showError(passwordInput, 'Password is required');
            hasErrors = true;
        }

        if (hasErrors) {
            e.preventDefault();
            return false;
        }

        // Show loading state
        loginBtn.classList.add('loading');
        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
    });

    // Real-time validation
    emailInput.addEventListener('input', function() {
        this.classList.remove('error');
    });

    passwordInput.addEventListener('input', function() {
        this.classList.remove('error');
    });

    // Show password toggle
    const togglePassword = document.createElement('i');
    togglePassword.className = 'fas fa-eye-slash';
    togglePassword.style.cssText =
        'position: absolute; right: 20px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #95a5a6; transition: color 0.3s ease;';

    passwordInput.parentElement.appendChild(togglePassword);

    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.className = type === 'password' ? 'fas fa-eye-slash' : 'fas fa-eye';
    });

    // Helper functions
    function showError(input, message) {
        input.classList.add('error');
        input.focus();
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
});

function showForgotPassword() {
    alert(
        'Password reset functionality would be implemented here.\n\nTypically this would:\n• Ask for your email\n• Send a reset link\n• Allow you to set a new password'
    );
}
</script>

<?php include __DIR__ . '/../common/footer.php'; ?>