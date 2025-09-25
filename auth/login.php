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
/* Login Page Specific Styles */
.login-page {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 0;
    position: relative;
    overflow: hidden;
}

.login-page::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image:
        radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 2px, transparent 2px),
        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 2px, transparent 2px);
    background-size: 60px 60px;
    pointer-events: none;
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
    border-radius: 25px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.login-header {
    background: linear-gradient(135deg, #2c3e50, #34495e);
    color: white;
    padding: 40px 30px;
    text-align: center;
    position: relative;
}

.login-header::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}

.login-header h1 {
    font-size: 2.2rem;
    font-weight: 700;
    margin: 0 0 10px 0;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}

.login-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.login-body {
    padding: 40px;
}

/* Demo Credentials */
.demo-section {
    background: linear-gradient(135deg, #fff3cd, #ffeaa7);
    border: 2px solid #f39c12;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.demo-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #f39c12, #e67e22);
}

.demo-title {
    font-weight: 700;
    color: #856404;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.2rem;
}

.demo-title i {
    font-size: 1.4rem;
    animation: pulse 2s infinite;
}

.demo-grid {
    display: grid;
    gap: 15px;
}

.demo-item {
    background: rgba(255, 255, 255, 0.8);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.demo-item:hover {
    background: rgba(255, 255, 255, 0.95);
    border-color: #f39c12;
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
}

.demo-item-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.demo-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
    font-weight: bold;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.admin-icon {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
}

.shelter-icon {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
}

.adopter-icon {
    background: linear-gradient(135deg, #3498db, #2980b9);
}

.demo-info h4 {
    margin: 0 0 5px 0;
    color: #2c3e50;
    font-weight: 700;
    font-size: 1.1rem;
}

.demo-email {
    font-size: 0.9rem;
    color: #6c757d;
    font-family: 'Courier New', monospace;
    background: rgba(108, 117, 125, 0.1);
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-block;
}

.demo-password {
    background: #2c3e50;
    color: white;
    padding: 10px 18px;
    border-radius: 25px;
    font-family: 'Courier New', monospace;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 4px 12px rgba(44, 62, 80, 0.3);
}

.demo-password:hover {
    background: #34495e;
    transform: scale(1.05);
}

/* Form Styles */
.form-group {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    margin-bottom: 10px;
    font-weight: 700;
    color: #2c3e50;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-label i {
    color: #667eea;
    font-size: 1.1rem;
}

.form-input {
    width: 100%;
    padding: 16px 20px;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    font-size: 1.05rem;
    font-family: inherit;
    transition: all 0.3s ease;
    background: #fafbfc;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    transform: translateY(-2px);
}

.form-input.error {
    border-color: #e74c3c;
    background: #fef5f5;
    animation: shake 0.5s ease-in-out;
}

.form-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    flex-wrap: wrap;
    gap: 15px;
}

.remember-me {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #6c757d;
    font-size: 0.95rem;
    cursor: pointer;
    transition: color 0.3s ease;
}

.remember-me:hover {
    color: #2c3e50;
}

.remember-me input {
    transform: scale(1.3);
    accent-color: #667eea;
}

.forgot-password {
    color: #667eea;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
}

.forgot-password:hover {
    color: #5a67d8;
    text-decoration: none;
}

.forgot-password::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    bottom: -2px;
    left: 0;
    background-color: #5a67d8;
    transition: width 0.3s ease;
}

.forgot-password:hover::after {
    width: 100%;
}

.login-btn {
    width: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 18px 30px;
    border: none;
    border-radius: 12px;
    font-size: 1.2rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.4s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.login-btn:hover {
    background: linear-gradient(135deg, #5a67d8, #6b46c1);
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
}

.login-btn:active {
    transform: translateY(0);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

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
    margin: auto;
    border: 2px solid transparent;
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.register-link {
    text-align: center;
    margin-top: 30px;
    padding-top: 30px;
    border-top: 2px solid #f8f9fa;
}

.register-link p {
    color: #6c757d;
    margin-bottom: 15px;
    font-size: 1.05rem;
}

.register-link .btn-secondary {
    background: #6c757d;
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-block;
}

.register-link .btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
    text-decoration: none;
    color: white;
}

/* Info Panel */
.info-panel {
    color: white;
    padding: 40px;
}

.info-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 20px;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}

.info-subtitle {
    font-size: 1.3rem;
    margin-bottom: 40px;
    opacity: 0.95;
    line-height: 1.6;
}

.feature-list {
    list-style: none;
    padding: 0;
    margin: 40px 0;
}

.feature-list li {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    font-size: 1.1rem;
    opacity: 0.9;
    padding: 15px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.feature-list li:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateX(10px);
}

.feature-list i {
    color: #ffd700;
    font-size: 1.4rem;
    margin-right: 15px;
    width: 25px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-top: 30px;
}

.stat-item {
    text-align: center;
    padding: 20px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.stat-item:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-5px);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: #ffd700;
    display: block;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}

.stat-label {
    opacity: 0.9;
    font-weight: 500;
}

/* Alerts */
.alert {
    padding: 20px 25px;
    border-radius: 12px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    animation: slideInDown 0.5s ease;
}

.alert i {
    font-size: 1.3rem;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
    border: 2px solid #b8dacd;
}

.alert-error {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
    border: 2px solid #f1b0b7;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .login-container {
        grid-template-columns: 1fr;
        gap: 30px;
        padding: 15px;
    }

    .info-panel {
        order: -1;
        text-align: center;
        padding: 30px 20px;
    }

    .login-card {
        margin: 0 auto;
    }

    .login-body {
        padding: 30px 25px;
    }

    .demo-grid {
        gap: 12px;
    }

    .demo-item {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }

    .demo-item-left {
        justify-content: center;
        flex-direction: column;
        gap: 10px;
    }

    .form-options {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .info-title {
        font-size: 2rem;
    }

    .info-subtitle {
        font-size: 1.1rem;
    }
}

/* Animations */
@keyframes spin {
    0% {
        transform: rotate(0deg);
    }

    100% {
        transform: rotate(360deg);
    }
}

@keyframes shake {

    0%,
    100% {
        transform: translateX(0);
    }

    25% {
        transform: translateX(-5px);
    }

    75% {
        transform: translateX(5px);
    }
}

@keyframes pulse {

    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }

    50% {
        transform: scale(1.1);
        opacity: 0.8;
    }
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in-up {
    animation: fadeInUp 0.6s ease-out;
}
</style>

<div class="login-page">
    <div class="login-container">
        <!-- Login Card -->
        <div class="login-card fade-in-up">
            <div class="login-header">
                <h1>Welcome Back</h1>
                <p>Sign in to your account to continue</p>
            </div>
            <div class="login-body">
                <!-- Demo Credentials -->
                <div class="demo-section">
                    <div class="demo-title">
                        <i class="fas fa-key"></i>
                        Demo Login Credentials
                    </div>
                    <div class="demo-grid">
                        <div class="demo-item" onclick="quickLogin('admin@petcare.com', 'admin123')">
                            <div class="demo-item-left">
                                <div class="demo-icon admin-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="demo-info">
                                    <h4>Administrator</h4>
                                    <div class="demo-email">admin@petcare.com</div>
                                </div>
                            </div>
                            <button type="button" class="demo-password">admin123</button>
                        </div>

                        <div class="demo-item" onclick="quickLogin('shelter@demo.com', 'shelter123')">
                            <div class="demo-item-left">
                                <div class="demo-icon shelter-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="demo-info">
                                    <h4>Shelter Manager</h4>
                                    <div class="demo-email">shelter@demo.com</div>
                                </div>
                            </div>
                            <button type="button" class="demo-password">shelter123</button>
                        </div>

                        <div class="demo-item" onclick="quickLogin('adopter@demo.com', 'adopter123')">
                            <div class="demo-item-left">
                                <div class="demo-icon adopter-icon">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <div class="demo-info">
                                    <h4>Pet Adopter</h4>
                                    <div class="demo-email">adopter@demo.com</div>
                                </div>
                            </div>
                            <button type="button" class="demo-password">adopter123</button>
                        </div>
                    </div>
                </div>

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
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i>
                            Email Address
                        </label>
                        <input type="email" id="email" name="email" class="form-input" required
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            placeholder="Enter your email address">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <input type="password" id="password" name="password" class="form-input" required
                            placeholder="Enter your password">
                    </div>

                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember_me" id="remember_me">
                            Remember me for 30 days
                        </label>
                        <a href="#" class="forgot-password" onclick="showForgotPassword()">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="login-btn" id="loginBtn">
                        Sign In
                    </button>
                </form>

                <div class="register-link">
                    <p>Don't have an account?</p>
                    <a href="<?php echo $BASE_URL; ?>auth/register.php" class="btn-secondary">
                        Create Account Now
                    </a>
                </div>
            </div>
        </div>

        <!-- Info Panel -->
        <div class="info-panel fade-in-up">
            <h1 class="info-title">Find Your Perfect Companion</h1>
            <p class="info-subtitle">
                Join thousands of families who have found their new best friend through our pet adoption platform.
            </p>

            <ul class="feature-list">
                <li>
                    <i class="fas fa-search"></i>
                    Browse thousands of pets looking for homes
                </li>
                <li>
                    <i class="fas fa-heart"></i>
                    Connect with trusted shelters and rescues
                </li>
                <li>
                    <i class="fas fa-shield-alt"></i>
                    Safe and secure adoption process
                </li>
                <li>
                    <i class="fas fa-users"></i>
                    Join a community of pet lovers
                </li>
                <li>
                    <i class="fas fa-book"></i>
                    Access expert care guides and tips
                </li>
            </ul>

            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number">5000+</span>
                    <span class="stat-label">Pets Adopted</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">200+</span>
                    <span class="stat-label">Partner Shelters</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">10K+</span>
                    <span class="stat-label">Happy Families</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">24/7</span>
                    <span class="stat-label">Support Available</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Login page JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    setupFormValidation();
    setupQuickLogin();
    addAnimations();
});

function setupFormValidation() {
    const form = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');

    if (!form) return;

    form.addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();

        // Clear previous errors
        document.querySelectorAll('.form-input').forEach(input => {
            input.classList.remove('error');
        });

        let hasErrors = false;

        if (!email) {
            showInputError('email');
            hasErrors = true;
        } else if (!isValidEmail(email)) {
            showInputError('email');
            hasErrors = true;
        }

        if (!password) {
            showInputError('password');
            hasErrors = true;
        }

        if (hasErrors) {
            e.preventDefault();
            return false;
        }

        // Show loading state
        loginBtn.classList.add('loading');
        loginBtn.disabled = true;
        loginBtn.textContent = 'Signing In...';

        return true;
    });

    // Real-time validation
    document.getElementById('email').addEventListener('input', function() {
        this.classList.remove('error');
        if (this.value.trim() && isValidEmail(this.value.trim())) {
            this.style.borderColor = '#27ae60';
        }
    });

    document.getElementById('password').addEventListener('input', function() {
        this.classList.remove('error');
        if (this.value.trim()) {
            this.style.borderColor = '#27ae60';
        }
    });
}

function showInputError(fieldId) {
    const field = document.getElementById(fieldId);
    field.classList.add('error');
    field.focus();
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function quickLogin(email, password) {
    // Add ripple effect to clicked demo item
    const clickedItem = event.currentTarget;
    addRippleEffect(clickedItem);

    // Fill form fields with animation
    setTimeout(() => {
        const emailField = document.getElementById('email');
        const passwordField = document.getElementById('password');

        // Clear fields first
        emailField.value = '';
        passwordField.value = '';

        // Type animation effect
        typeInField(emailField, email, () => {
            typeInField(passwordField, password, () => {
                // Highlight the login button
                const loginBtn = document.getElementById('loginBtn');
                loginBtn.style.animation = 'pulse 1s ease-in-out 3';

                // Auto-focus login button
                setTimeout(() => {
                    loginBtn.focus();
                    loginBtn.style.animation = '';
                }, 1000);
            });
        });
    }, 300);
}

function typeInField(field, text, callback) {
    let i = 0;
    const typeInterval = setInterval(() => {
        if (i < text.length) {
            field.value += text.charAt(i);
            field.style.borderColor = '#667eea';
            field.style.background = '#f0f8ff';
            i++;
        } else {
            clearInterval(typeInterval);
            setTimeout(() => {
                field.style.borderColor = '#27ae60';
                field.style.background = 'white';
            }, 500);
            if (callback) callback();
        }
    }, 50);
}

function addRippleEffect(element) {
    const ripple = document.createElement('div');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;

    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    ripple.classList.add('ripple-effect');

    ripple.style.position = 'absolute';
    ripple.style.borderRadius = '50%';
    ripple.style.background = 'rgba(102, 126, 234, 0.4)';
    ripple.style.transform = 'scale(0)';
    ripple.style.animation = 'ripple 0.6s linear';
    ripple.style.pointerEvents = 'none';

    element.style.position = 'relative';
    element.style.overflow = 'hidden';
    element.appendChild(ripple);

    setTimeout(() => {
        ripple.remove();
    }, 600);
}

function setupQuickLogin() {
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.altKey) {
            switch (e.key) {
                case 'a':
                    e.preventDefault();
                    quickLogin('admin@petcare.com', 'admin123');
                    break;
                case 's':
                    e.preventDefault();
                    quickLogin('shelter@demo.com', 'shelter123');
                    break;
                case 'd':
                    e.preventDefault();
                    quickLogin('adopter@demo.com', 'adopter123');
                    break;
            }
        }
    });
}

function addAnimations() {
    // Animate stats on scroll
    const statNumbers = document.querySelectorAll('.stat-number');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateNumber(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.5
    });

    statNumbers.forEach(stat => observer.observe(stat));

    // Add floating animation to feature icons
    const featureIcons = document.querySelectorAll('.feature-list i');
    featureIcons.forEach((icon, index) => {
        icon.style.animation = `float 3s ease-in-out infinite ${index * 0.2}s`;
    });
}

function animateNumber(element) {
    const text = element.textContent;
    const number = parseInt(text.replace(/[^0-9]/g, ''));

    if (isNaN(number)) return;

    const suffix = text.replace(/[0-9]/g, '');
    let current = 0;
    const increment = number / 50;

    const timer = setInterval(() => {
        current += increment;
        if (current >= number) {
            element.textContent = number.toLocaleString() + suffix;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current).toLocaleString() + suffix;
        }
    }, 30);
}

function showForgotPassword() {
    alert(
        'Password Reset:\n\nFor demo purposes, use the provided credentials above.\n\nIn a real application, this would:\n• Ask for your email address\n• Send a reset link to your email\n• Allow you to set a new password\n\nContact: admin@petcare.com');
}

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Add ripple animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }
`;
document.head.appendChild(style);

console.log('Login page loaded successfully');
console.log('Demo credentials:');
console.log('Admin: admin@petcare.com / admin123 (Ctrl+Alt+A)');
console.log('Shelter: shelter@demo.com / shelter123 (Ctrl+Alt+S)');
console.log('Adopter: adopter@demo.com / adopter123 (Ctrl+Alt+D)');
</script>

<?php include __DIR__ . '/../common/footer.php'; ?>