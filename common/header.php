<?php
// common/header.php
// Include database connection
require_once __DIR__ . '/../config/db.php';

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $path = str_replace(basename($scriptName), '', $scriptName);
    define('BASE_URL', $protocol . $host . $path);
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Check if user is logged in and get user info
$is_logged_in = Session::isLoggedIn();
$user_type = Session::getUserType();
$user_id = Session::getUserId();
$user_info = null;

if ($is_logged_in) {
    $user_info = DBHelper::selectOne("SELECT first_name, last_name, email FROM users WHERE user_id = ?", [$user_id]);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <!-- Dynamic Title -->
    <title>
        <?php echo isset($page_title) ? $page_title . ' - Pet Adoption Care Guide' : 'Pet Adoption Care Guide - Find Your Perfect Pet'; ?>
    </title>

    <!-- Meta Description -->
    <meta name="description"
        content="<?php echo isset($page_description) ? $page_description : 'Connect loving families with pets in need of forever homes. Browse adoptable pets, find care guides, and give a pet a second chance at happiness.'; ?>">

    <!-- Meta Keywords -->
    <meta name="keywords" content="pet adoption, animal shelter, adopt pets, dogs, cats, rescue animals, pet care">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo isset($page_title) ? $page_title : 'Pet Adoption Care Guide'; ?>">
    <meta property="og:description" content="Find your perfect companion and give a pet a loving home.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo BASE_URL . $current_page; ?>">
    <meta property="og:image" content="<?php echo BASE_URL; ?>uploads/logo-og.jpg">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>assets/favicon.ico">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/apple-touch-icon.png">

    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Custom CSS -->
    <style>
    /* Reset and Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        line-height: 1.6;
        color: #333;
        background-color: #f8f9fa;
    }

    /* Header Styles */
    .header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
        transition: all 0.3s ease;
    }

    .header.scrolled {
        background: rgba(102, 126, 234, 0.95);
        backdrop-filter: blur(10px);
    }

    .header-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        min-height: 70px;
    }

    .logo {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: white;
        font-weight: 700;
        font-size: 24px;
        font-family: 'Poppins', sans-serif;
    }

    .logo i {
        font-size: 32px;
        margin-right: 10px;
        color: #ffd700;
    }

    .logo:hover {
        color: #ffd700;
        transform: scale(1.05);
        transition: all 0.3s ease;
    }

    /* Navigation Styles */
    .nav-menu {
        display: flex;
        list-style: none;
        align-items: center;
    }

    .nav-menu li {
        margin: 0 10px;
        position: relative;
    }

    .nav-menu a {
        color: white;
        text-decoration: none;
        padding: 10px 15px;
        border-radius: 25px;
        transition: all 0.3s ease;
        font-weight: 500;
        display: flex;
        align-items: center;
    }

    .nav-menu a i {
        margin-right: 5px;
    }

    .nav-menu a:hover,
    .nav-menu a.active {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        transform: translateY(-2px);
    }

    /* Dropdown Menu */
    .dropdown {
        position: relative;
    }

    .dropdown-menu {
        position: absolute;
        top: 100%;
        left: 0;
        background: white;
        min-width: 200px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 1001;
    }

    .dropdown:hover .dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-menu li {
        margin: 0;
    }

    .dropdown-menu a {
        color: #333;
        padding: 12px 20px;
        border-radius: 0;
        display: block;
    }

    .dropdown-menu a:hover {
        background: #f8f9fa;
        color: #667eea;
        transform: none;
    }

    /* User Menu */
    .user-menu {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .user-info {
        display: flex;
        align-items: center;
        color: white;
        font-weight: 500;
    }

    .user-avatar {
        width: 35px;
        height: 35px;
        background: #ffd700;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 8px;
        font-weight: 700;
        color: #667eea;
    }

    .auth-buttons {
        display: flex;
        gap: 10px;
    }

    .btn {
        padding: 10px 20px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
    }

    .btn-login {
        background: transparent;
        color: white;
        border: 2px solid white;
    }

    .btn-login:hover {
        background: white;
        color: #667eea;
    }

    .btn-register {
        background: #ffd700;
        color: #667eea;
    }

    .btn-register:hover {
        background: #ffed4e;
        transform: translateY(-2px);
    }

    .btn-logout {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .btn-logout:hover {
        background: #e74c3c;
        border-color: #e74c3c;
    }

    /* Mobile Menu Toggle */
    .mobile-menu-toggle {
        display: none;
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
    }

    /* Mobile Styles */
    @media (max-width: 768px) {
        .header-container {
            padding: 0 15px;
        }

        .logo {
            font-size: 20px;
        }

        .logo i {
            font-size: 28px;
        }

        .nav-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: rgba(102, 126, 234, 0.95);
            backdrop-filter: blur(10px);
            flex-direction: column;
            padding: 20px 0;
        }

        .nav-menu.active {
            display: flex;
        }

        .nav-menu li {
            margin: 5px 0;
            width: 100%;
            text-align: center;
        }

        .nav-menu a {
            border-radius: 0;
            padding: 15px 20px;
            width: 100%;
            justify-content: center;
        }

        .mobile-menu-toggle {
            display: block;
        }

        .user-menu {
            gap: 10px;
        }

        .user-info span {
            display: none;
        }

        .auth-buttons {
            flex-direction: column;
            gap: 5px;
        }

        .btn {
            padding: 8px 15px;
            font-size: 14px;
        }
    }

    /* Notification Bar */
    .notification-bar {
        background: #ffd700;
        color: #333;
        text-align: center;
        padding: 8px 0;
        font-weight: 500;
        position: relative;
    }

    .notification-bar .close-notification {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
    }

    /* Loading Bar */
    .loading-bar {
        position: fixed;
        top: 0;
        left: 0;
        width: 0%;
        height: 3px;
        background: #ffd700;
        z-index: 9999;
        transition: width 0.3s ease;
    }

    /* Utility Classes */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .text-center {
        text-align: center;
    }

    .mt-20 {
        margin-top: 20px;
    }

    .mb-20 {
        margin-bottom: 20px;
    }

    /* Alert Messages */
    .alert {
        padding: 12px 20px;
        border-radius: 8px;
        margin: 15px 0;
        display: flex;
        align-items: center;
        font-weight: 500;
    }

    .alert i {
        margin-right: 10px;
        font-size: 18px;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .alert-warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .alert-info {
        background: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }
    </style>

    <!-- Page-specific CSS -->
    <?php if (isset($page_css)): ?>
    <style>
    <?php echo $page_css;
    ?>
    </style>
    <?php endif; ?>
</head>

<body>
    <!-- Loading Bar -->
    <div class="loading-bar" id="loadingBar"></div>

    <!-- Notification Bar -->
    <?php if (!isset($_COOKIE['hide_notification'])): ?>
    <div class="notification-bar" id="notificationBar">
        <span><i class="fas fa-heart"></i> Help us save more lives - Every adoption makes a difference!</span>
        <button class="close-notification" onclick="closeNotification()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <header class="header" id="header">
        <div class="header-container">
            <!-- Logo -->
            <a href="<?php echo BASE_URL; ?>index.php" class="logo">
                <i class="fas fa-paw"></i>
                <span>Pet Care Guide</span>
            </a>

            <!-- Navigation Menu -->
            <nav>
                <ul class="nav-menu" id="navMenu">
                    <li><a href="<?php echo BASE_URL; ?>index.php"
                            class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i> Home
                        </a></li>

                    <li><a href="<?php echo BASE_URL; ?>about.php"
                            class="<?php echo ($current_page == 'about.php') ? 'active' : ''; ?>">
                            <i class="fas fa-info-circle"></i> About
                        </a></li>

                    <li class="dropdown">
                        <a href="<?php echo BASE_URL; ?>adopter/browsePets.php"
                            class="<?php echo ($current_dir == 'adopter' || $current_page == 'browsePets.php') ? 'active' : ''; ?>">
                            <i class="fas fa-search"></i> Find Pets <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="<?php echo BASE_URL; ?>adopter/browsePets.php">Browse All Pets</a></li>
                            <li><a href="<?php echo BASE_URL; ?>adopter/browsePets.php?category=dog">Dogs</a></li>
                            <li><a href="<?php echo BASE_URL; ?>adopter/browsePets.php?category=cat">Cats</a></li>
                            <li><a href="<?php echo BASE_URL; ?>adopter/browsePets.php?category=other">Other Pets</a>
                            </li>
                        </ul>
                    </li>

                    <li><a href="<?php echo BASE_URL; ?>adopter/careGuides.php"
                            class="<?php echo ($current_page == 'careGuides.php') ? 'active' : ''; ?>">
                            <i class="fas fa-book"></i> Care Guides
                        </a></li>

                    <li><a href="<?php echo BASE_URL; ?>contact.php"
                            class="<?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>">
                            <i class="fas fa-envelope"></i> Contact
                        </a></li>
                </ul>
            </nav>

            <!-- User Menu -->
            <div class="user-menu">
                <?php if ($is_logged_in && $user_info): ?>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user_info['first_name'], 0, 1)); ?>
                    </div>
                    <span>Hello, <?php echo htmlspecialchars($user_info['first_name']); ?></span>
                </div>
                <div class="dropdown">
                    <a href="#" class="btn btn-logout">
                        <i class="fas fa-user"></i> Menu <i class="fas fa-chevron-down"></i>
                    </a>
                    <ul class="dropdown-menu">
                        <?php if ($user_type == 'admin'): ?>
                        <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php"><i class="fas fa-tachometer-alt"></i>
                                Admin Dashboard</a></li>
                        <?php elseif ($user_type == 'shelter'): ?>
                        <li><a href="<?php echo BASE_URL; ?>shelter/dashboard.php"><i class="fas fa-tachometer-alt"></i>
                                Shelter Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>shelter/addPet.php"><i class="fas fa-plus"></i> Add Pet</a>
                        </li>
                        <li><a href="<?php echo BASE_URL; ?>shelter/viewPets.php"><i class="fas fa-list"></i> My
                                Pets</a></li>
                        <?php else: ?>
                        <li><a href="<?php echo BASE_URL; ?>adopter/dashboard.php"><i class="fas fa-tachometer-alt"></i>
                                My Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>adopter/myAdoptions.php"><i class="fas fa-heart"></i> My
                                Adoptions</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo BASE_URL; ?>auth/logout.php"><i class="fas fa-sign-out-alt"></i>
                                Logout</a></li>
                    </ul>
                </div>
                <?php else: ?>
                <div class="auth-buttons">
                    <a href="<?php echo BASE_URL; ?>auth/login.php" class="btn btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="<?php echo BASE_URL; ?>auth/register.php" class="btn btn-register">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <!-- Alert Messages -->
    <?php
    // Display session messages
    if (Session::has('success_message')):
    ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php 
            echo htmlspecialchars(Session::get('success_message'));
            Session::remove('success_message');
            ?>
    </div>
    <?php endif; ?>

    <?php if (Session::has('error_message')): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php 
            echo htmlspecialchars(Session::get('error_message'));
            Session::remove('error_message');
            ?>
    </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <main class="main-content">

        <script>
        // Header JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const navMenu = document.getElementById('navMenu');

            if (mobileMenuToggle && navMenu) {
                mobileMenuToggle.addEventListener('click', function() {
                    navMenu.classList.toggle('active');
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-bars');
                    icon.classList.toggle('fa-times');
                });
            }

            // Header scroll effect
            const header = document.getElementById('header');
            window.addEventListener('scroll', function() {
                if (window.scrollY > 100) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });

            // Loading bar animation
            const loadingBar = document.getElementById('loadingBar');
            let progress = 0;
            const interval = setInterval(function() {
                progress += Math.random() * 10;
                loadingBar.style.width = Math.min(progress, 90) + '%';
                if (progress >= 90) {
                    clearInterval(interval);
                }
            }, 100);

            window.addEventListener('load', function() {
                loadingBar.style.width = '100%';
                setTimeout(function() {
                    loadingBar.style.display = 'none';
                }, 500);
            });
        });

        // Close notification bar
        function closeNotification() {
            const notificationBar = document.getElementById('notificationBar');
            if (notificationBar) {
                notificationBar.style.display = 'none';
                // Set cookie to remember user closed notification
                document.cookie = "hide_notification=1; expires=" + new Date(Date.now() + 7 * 24 * 60 * 60 * 1000)
                    .toUTCString() + "; path=/";
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
        </script>