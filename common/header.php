<?php
// common/header.php
// Include database connection
require_once __DIR__ . '/../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $path = str_replace(basename($scriptName), '', $scriptName);
    define('BASE_URL', $protocol . $host . $path);
}

// Get current page information
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Check if user is logged in and get user info
$is_logged_in = isset($_SESSION['user_id']);
$user_type = $_SESSION['user_type'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;
$user_info = null;

if ($is_logged_in && $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Header user fetch error: " . $e->getMessage());
    }
}

// Function to check if a menu item is active
function isActive($page, $current) {
    return strpos($current, $page) !== false ? 'active' : '';
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
        <?php echo isset($page_title) ? $page_title . ' - Pet Adoption Care Guide' : 'Pet Adoption Care Guide - Find Your Perfect Companion'; ?>
    </title>

    <!-- Meta Tags -->
    <meta name="description"
        content="<?php echo isset($page_description) ? $page_description : 'Connect loving families with pets in need. Browse adoptable pets, learn about pet care, and give a furry friend a second chance at happiness.'; ?>">
    <meta name="keywords"
        content="pet adoption, animal shelter, adopt pets, dogs, cats, rescue animals, pet care, animal welfare">
    <meta name="author" content="Pet Adoption Care Guide">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo isset($page_title) ? $page_title : 'Pet Adoption Care Guide'; ?>">
    <meta property="og:description" content="Find your perfect companion and give a pet a loving home.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo BASE_URL . $current_page; ?>">
    <meta property="og:image" content="<?php echo BASE_URL; ?>uploads/og-image.jpg">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>assets/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>assets/apple-touch-icon.png">

    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Custom CSS -->
    <style>
    /* ===== CSS Variables ===== */
    :root {
        /* Colors */
        --primary-color: #6366f1;
        --primary-dark: #4f46e5;
        --primary-light: #818cf8;
        --secondary-color: #ec4899;
        --secondary-dark: #db2777;
        --accent-color: #fbbf24;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --info-color: #3b82f6;

        /* Neutral Colors */
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
        --gray-900: #111827;

        /* Typography */
        --font-primary: 'Inter', sans-serif;
        --font-display: 'Poppins', sans-serif;

        /* Spacing */
        --container-width: 1280px;
        --header-height: 70px;

        /* Shadows */
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);

        /* Transitions */
        --transition-fast: 150ms ease;
        --transition-base: 200ms ease;
        --transition-slow: 300ms ease;
    }

    /* ===== Global Styles ===== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: var(--font-primary);
        font-size: 16px;
        line-height: 1.6;
        color: var(--gray-800);
        background-color: var(--gray-50);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* ===== Header Styles ===== */
    .header {
        background: white;
        box-shadow: var(--shadow-md);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        transition: all var(--transition-slow);
    }

    .header.scrolled {
        box-shadow: var(--shadow-lg);
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(20px);
    }

    .header-container {
        max-width: var(--container-width);
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: var(--header-height);
    }

    /* Logo */
    .logo {
        display: flex;
        align-items: center;
        text-decoration: none;
        gap: 12px;
        transition: transform var(--transition-base);
    }

    .logo:hover {
        transform: translateY(-2px);
    }

    .logo-icon {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }

    .logo-icon::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.3) 50%, transparent 70%);
        transform: rotate(45deg);
        transition: transform 0.6s;
    }

    .logo:hover .logo-icon::before {
        transform: rotate(45deg) translateX(100%);
    }

    .logo-icon i {
        font-size: 24px;
        color: white;
        position: relative;
        z-index: 1;
    }

    .logo-text {
        font-family: var(--font-display);
        font-size: 22px;
        font-weight: 700;
        color: var(--gray-800);
    }

    .logo-text span {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* Navigation */
    .nav {
        display: flex;
        align-items: center;
        gap: 40px;
    }

    .nav-menu {
        display: flex;
        list-style: none;
        gap: 5px;
        align-items: center;
    }

    .nav-item {
        position: relative;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        color: var(--gray-600);
        text-decoration: none;
        font-weight: 500;
        border-radius: 10px;
        transition: all var(--transition-base);
        position: relative;
        overflow: hidden;
    }

    .nav-link::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        width: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        transform: translateX(-50%);
        transition: width var(--transition-base);
    }

    .nav-link:hover {
        color: var(--primary-color);
        background: var(--gray-50);
    }

    .nav-link:hover::before,
    .nav-link.active::before {
        width: 80%;
    }

    .nav-link.active {
        color: var(--primary-color);
        background: var(--primary-color);
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(236, 72, 153, 0.1));
    }

    /* Dropdown Menu */
    .dropdown {
        position: relative;
    }

    .dropdown-toggle::after {
        content: '\f107';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        margin-left: 4px;
        transition: transform var(--transition-base);
    }

    .dropdown:hover .dropdown-toggle::after {
        transform: rotate(180deg);
    }

    .dropdown-menu {
        position: absolute;
        top: calc(100% + 10px);
        left: 0;
        background: white;
        min-width: 220px;
        border-radius: 12px;
        box-shadow: var(--shadow-xl);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all var(--transition-base);
        border: 1px solid var(--gray-100);
        padding: 8px;
    }

    .dropdown:hover .dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .dropdown-item {
        display: block;
        padding: 10px 16px;
        color: var(--gray-700);
        text-decoration: none;
        border-radius: 8px;
        transition: all var(--transition-fast);
        font-weight: 400;
    }

    .dropdown-item:hover {
        background: var(--gray-50);
        color: var(--primary-color);
        padding-left: 20px;
    }

    .dropdown-item i {
        margin-right: 10px;
        width: 16px;
        text-align: center;
    }

    /* User Menu */
    .user-section {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 6px;
        border-radius: 50px;
        background: var(--gray-50);
        transition: all var(--transition-base);
    }

    .user-profile:hover {
        background: var(--gray-100);
    }

    .user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 16px;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        position: relative;
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    .user-avatar.online::after {
        content: '';
        position: absolute;
        bottom: 2px;
        right: 2px;
        width: 10px;
        height: 10px;
        background: var(--success-color);
        border: 2px solid white;
        border-radius: 50%;
    }

    .user-name {
        font-weight: 600;
        color: var(--gray-700);
        margin-right: 8px;
    }

    /* Auth Buttons */
    .auth-buttons {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 500;
        text-decoration: none;
        transition: all var(--transition-base);
        border: none;
        cursor: pointer;
        font-family: var(--font-primary);
        font-size: 15px;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
    }

    .btn-outline {
        background: transparent;
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
    }

    .btn-outline:hover {
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
    }

    /* Mobile Menu */
    .mobile-menu-btn {
        display: none;
        width: 40px;
        height: 40px;
        align-items: center;
        justify-content: center;
        border: none;
        background: var(--gray-100);
        border-radius: 10px;
        cursor: pointer;
        transition: all var(--transition-base);
    }

    .mobile-menu-btn:hover {
        background: var(--gray-200);
    }

    .hamburger {
        position: relative;
        width: 20px;
        height: 2px;
        background: var(--gray-700);
        transition: all var(--transition-base);
    }

    .hamburger::before,
    .hamburger::after {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        background: var(--gray-700);
        left: 0;
        transition: all var(--transition-base);
    }

    .hamburger::before {
        top: -6px;
    }

    .hamburger::after {
        bottom: -6px;
    }

    .mobile-menu-btn.active .hamburger {
        background: transparent;
    }

    .mobile-menu-btn.active .hamburger::before {
        top: 0;
        transform: rotate(45deg);
    }

    .mobile-menu-btn.active .hamburger::after {
        bottom: 0;
        transform: rotate(-45deg);
    }

    /* Notification Bar */
    .notification-bar {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        padding: 12px 0;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .notification-bar::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: repeating-linear-gradient(45deg,
                transparent,
                transparent 20px,
                rgba(255, 255, 255, 0.05) 20px,
                rgba(255, 255, 255, 0.05) 40px);
        animation: slide 20s linear infinite;
    }

    @keyframes slide {
        0% {
            transform: translate(0, 0);
        }

        100% {
            transform: translate(50px, 50px);
        }
    }

    .notification-content {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-weight: 500;
    }

    .notification-bar .close-btn {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, 0.2);
        border: none;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all var(--transition-base);
    }

    .notification-bar .close-btn:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    /* Progress Bar */
    .progress-bar {
        position: fixed;
        top: 0;
        left: 0;
        width: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--accent-color));
        z-index: 2000;
        transition: width 0.3s ease;
    }

    /* Alert Messages */
    .alerts-container {
        position: fixed;
        top: calc(var(--header-height) + 20px);
        right: 20px;
        z-index: 1500;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: 400px;
    }

    .alert {
        padding: 16px 20px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        box-shadow: var(--shadow-lg);
        animation: slideIn 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .alert::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
    }

    .alert-success {
        background: white;
        color: var(--gray-800);
        border: 1px solid var(--gray-100);
    }

    .alert-success::before {
        background: var(--success-color);
    }

    .alert-success i {
        color: var(--success-color);
    }

    .alert-error {
        background: white;
        color: var(--gray-800);
        border: 1px solid var(--gray-100);
    }

    .alert-error::before {
        background: var(--danger-color);
    }

    .alert-error i {
        color: var(--danger-color);
    }

    .alert-warning {
        background: white;
        color: var(--gray-800);
        border: 1px solid var(--gray-100);
    }

    .alert-warning::before {
        background: var(--warning-color);
    }

    .alert-warning i {
        color: var(--warning-color);
    }

    .alert-info {
        background: white;
        color: var(--gray-800);
        border: 1px solid var(--gray-100);
    }

    .alert-info::before {
        background: var(--info-color);
    }

    .alert-info i {
        color: var(--info-color);
    }

    .alert-close {
        margin-left: auto;
        background: none;
        border: none;
        color: var(--gray-400);
        cursor: pointer;
        font-size: 18px;
        transition: color var(--transition-base);
    }

    .alert-close:hover {
        color: var(--gray-600);
    }

    /* Main Content Spacing */
    .main-content {
        margin-top: var(--header-height);
        min-height: calc(100vh - var(--header-height));
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .nav-menu {
            gap: 0;
        }

        .nav-link {
            padding: 10px 12px;
            font-size: 15px;
        }
    }

    @media (max-width: 768px) {
        .header-container {
            padding: 0 16px;
        }

        .logo-text {
            font-size: 18px;
        }

        .nav {
            position: fixed;
            top: var(--header-height);
            left: -100%;
            width: 100%;
            height: calc(100vh - var(--header-height));
            background: white;
            flex-direction: column;
            padding: 20px;
            transition: left var(--transition-slow);
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
        }

        .nav.active {
            left: 0;
        }

        .nav-menu {
            flex-direction: column;
            width: 100%;
            gap: 10px;
        }

        .nav-item {
            width: 100%;
        }

        .nav-link {
            width: 100%;
            padding: 15px 20px;
            font-size: 16px;
        }

        .dropdown-menu {
            position: static;
            opacity: 1;
            visibility: visible;
            transform: none;
            box-shadow: none;
            background: var(--gray-50);
            margin-top: 10px;
            display: none;
        }

        .dropdown.active .dropdown-menu {
            display: block;
        }

        .user-section {
            width: 100%;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-200);
            flex-direction: column;
            gap: 15px;
        }

        .auth-buttons {
            width: 100%;
            flex-direction: column;
        }

        .btn {
            width: 100%;
            justify-content: center;
        }

        .mobile-menu-btn {
            display: flex;
        }

        .notification-bar .close-btn {
            right: 10px;
        }

        .alerts-container {
            right: 10px;
            left: 10px;
            max-width: none;
        }
    }

    /* Utility Classes */
    .container {
        max-width: var(--container-width);
        margin: 0 auto;
        padding: 0 20px;
    }

    @media (max-width: 640px) {
        .container {
            padding: 0 16px;
        }
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
    <!-- Progress Bar -->
    <div class="progress-bar" id="progressBar"></div>

    <!-- Notification Bar -->
    <?php if (!isset($_COOKIE['hide_notification'])): ?>
    <div class="notification-bar" id="notificationBar">
        <div class="notification-content">
            <i class="fas fa-heart"></i>
            <span>Help us save more lives - Every adoption makes a difference!</span>
        </div>
        <button class="close-btn" onclick="closeNotification()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <header class="header" id="header">
        <div class="header-container">
            <!-- Logo -->
            <a href="<?php echo BASE_URL; ?>index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-paw"></i>
                </div>
                <div class="logo-text">
                    Pet <span>Care Guide</span>
                </div>
            </a>

            <!-- Navigation -->
            <nav class="nav" id="nav">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>index.php"
                            class="nav-link <?php echo isActive('index.php', $current_page); ?>">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>about.php"
                            class="nav-link <?php echo isActive('about.php', $current_page); ?>">
                            <i class="fas fa-info-circle"></i> About
                        </a>
                    </li>

                    <li class="nav-item dropdown">
                        <a href="<?php echo BASE_URL; ?>adopter/browsePets.php"
                            class="nav-link dropdown-toggle <?php echo isActive('browsePets', $current_page); ?>">
                            <i class="fas fa-search"></i> Find Pets
                        </a>
                        <div class="dropdown-menu">
                            <a href="<?php echo BASE_URL; ?>adopter/browsePets.php" class="dropdown-item">
                                <i class="fas fa-list"></i> Browse All Pets
                            </a>
                            <a href="<?php echo BASE_URL; ?>adopter/browsePets.php?category=dog" class="dropdown-item">
                                <i class="fas fa-dog"></i> Dogs
                            </a>
                            <a href="<?php echo BASE_URL; ?>adopter/browsePets.php?category=cat" class="dropdown-item">
                                <i class="fas fa-cat"></i> Cats
                            </a>
                            <a href="<?php echo BASE_URL; ?>adopter/browsePets.php?category=bird" class="dropdown-item">
                                <i class="fas fa-dove"></i> Birds
                            </a>
                            <a href="<?php echo BASE_URL; ?>adopter/browsePets.php?category=other"
                                class="dropdown-item">
                                <i class="fas fa-otter"></i> Other Pets
                            </a>
                        </div>
                    </li>

                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>adopter/careGuides.php"
                            class="nav-link <?php echo isActive('careGuides', $current_page); ?>">
                            <i class="fas fa-book"></i> Care Guides
                        </a>
                    </li>

                    <?php if ($is_logged_in && $user_type === 'shelter'): ?>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>shelter/dashboard.php"
                            class="nav-link <?php echo isActive('shelter', $current_dir); ?>">
                            <i class="fas fa-building"></i> Shelter Portal
                        </a>
                    </li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>contact.php"
                            class="nav-link <?php echo isActive('contact.php', $current_page); ?>">
                            <i class="fas fa-envelope"></i> Contact
                        </a>
                    </li>
                </ul>

                <!-- User Section -->
                <div class="user-section">
                    <?php if ($is_logged_in && $user_info): ?>
                    <div class="dropdown">
                        <div class="user-profile">
                            <div class="user-avatar online">
                                <?php echo strtoupper(substr($user_info['first_name'], 0, 1)); ?>
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($user_info['first_name']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="dropdown-menu">
                            <?php if ($user_type === 'admin'): ?>
                            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="dropdown-item">
                                <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                            </a>
                            <a href="<?php echo BASE_URL; ?>admin/manageUsers.php" class="dropdown-item">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                            <a href="<?php echo BASE_URL; ?>admin/reports.php" class="dropdown-item">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                            <?php elseif ($user_type === 'shelter'): ?>
                            <a href="<?php echo BASE_URL; ?>shelter/dashboard.php" class="dropdown-item">
                                <i class="fas fa-tachometer-alt"></i> Shelter Dashboard
                            </a>
                            <a href="<?php echo BASE_URL; ?>shelter/viewPets.php" class="dropdown-item">
                                <i class="fas fa-paw"></i> My Pets
                            </a>
                            <a href="<?php echo BASE_URL; ?>shelter/adoptionRequests.php" class="dropdown-item">
                                <i class="fas fa-file-alt"></i> Adoption Requests
                            </a>
                            <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>adopter/dashboard.php" class="dropdown-item">
                                <i class="fas fa-tachometer-alt"></i> My Dashboard
                            </a>
                            <a href="<?php echo BASE_URL; ?>adopter/myAdoptions.php" class="dropdown-item">
                                <i class="fas fa-heart"></i> My Adoptions
                            </a>
                            <?php endif; ?>
                            <hr style="margin: 8px 0; border: none; border-top: 1px solid var(--gray-200);">
                            <a href="<?php echo BASE_URL; ?>auth/logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="auth-buttons">
                        <a href="<?php echo BASE_URL; ?>auth/login.php" class="btn btn-outline">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="<?php echo BASE_URL; ?>auth/register.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </nav>

            <!-- Mobile Menu Button -->
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <span class="hamburger"></span>
            </button>
        </div>
    </header>

    <!-- Alerts Container -->
    <div class="alerts-container" id="alertsContainer">
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['success_message']); endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['error_message']); endif; ?>

        <?php if (isset($_SESSION['warning_message'])): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo htmlspecialchars($_SESSION['warning_message']); ?></span>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['warning_message']); endif; ?>

        <?php if (isset($_SESSION['info_message'])): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['info_message']); ?></span>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['info_message']); endif; ?>
    </div>

    <!-- Main Content -->
    <main class="main-content">

        <!-- JavaScript -->
        <script>
        // Progress Bar
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.getElementById('progressBar');
            let progress = 0;

            const interval = setInterval(() => {
                progress += Math.random() * 30;
                progressBar.style.width = Math.min(progress, 90) + '%';

                if (progress >= 90) {
                    clearInterval(interval);
                }
            }, 100);

            window.addEventListener('load', () => {
                progressBar.style.width = '100%';
                setTimeout(() => {
                    progressBar.style.opacity = '0';
                    setTimeout(() => {
                        progressBar.style.display = 'none';
                    }, 300);
                }, 500);
            });
        });

        // Mobile Menu
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const nav = document.getElementById('nav');
        const body = document.body;

        mobileMenuBtn.addEventListener('click', function() {
            this.classList.toggle('active');
            nav.classList.toggle('active');
            body.style.overflow = nav.classList.contains('active') ? 'hidden' : '';
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!nav.contains(e.target) && !mobileMenuBtn.contains(e.target) && nav.classList.contains(
                    'active')) {
                mobileMenuBtn.classList.remove('active');
                nav.classList.remove('active');
                body.style.overflow = '';
            }
        });

        // Header scroll effect
        const header = document.getElementById('header');
        let lastScroll = 0;

        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;

            if (currentScroll > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }

            lastScroll = currentScroll;
        });

        // Dropdown toggle for mobile
        const dropdowns = document.querySelectorAll('.dropdown');

        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');

            toggle.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    dropdown.classList.toggle('active');
                }
            });
        });


        // Close notification
        function closeNotification() {
            const notificationBar = document.getElementById('notificationBar');
            if (notificationBar) {
                notificationBar.style.transform = 'translateY(-100%)';
                notificationBar.style.opacity = '0';

                setTimeout(() => {
                    notificationBar.style.display = 'none';
                }, 300);

                // Set cookie to remember user closed notification for 7 days
                const date = new Date();
                date.setTime(date.getTime() + (7 * 24 * 60 * 60 * 1000));
                document.cookie = "hide_notification=1; expires=" + date.toUTCString() + "; path=/";
            }
        }

        // Auto-hide alerts
        function autoHideAlerts() {
            const alerts = document.querySelectorAll('.alert');

            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.animation = 'slideOut 0.3s ease forwards';

                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        }

        // Add slideOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Call auto-hide alerts on page load
        autoHideAlerts();

        // Show alert function (can be called from anywhere)
        function showAlert(type, message, duration = 5000) {
            const alertsContainer = document.getElementById('alertsContainer');

            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <i class="fas fa-${getAlertIcon(type)}"></i>
                <span>${message}</span>
                <button class="alert-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;

            alertsContainer.appendChild(alert);

            // Auto-hide after specified duration
            setTimeout(() => {
                if (alert.parentElement) {
                    alert.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => alert.remove(), 300);
                }
            }, duration);
        }

        // Get alert icon based on type
        function getAlertIcon(type) {
            const icons = {
                'success': 'check-circle',
                'error': 'exclamation-circle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };
            return icons[type] || 'info-circle';
        }

        // Smooth scroll for anchor links
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

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                // Close mobile menu on resize to desktop
                if (window.innerWidth > 768 && nav.classList.contains('active')) {
                    mobileMenuBtn.classList.remove('active');
                    nav.classList.remove('active');
                    body.style.overflow = '';
                }

                // Reset dropdowns
                dropdowns.forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }, 250);
        });

        // Add loading state to links
        document.querySelectorAll('a:not([href^="#"]):not([href^="javascript"]):not([target="_blank"])').forEach(
            link => {
                link.addEventListener('click', function(e) {
                    if (!e.ctrlKey && !e.metaKey) {
                        const progressBar = document.getElementById('progressBar');
                        progressBar.style.display = 'block';
                        progressBar.style.opacity = '1';
                        progressBar.style.width = '0%';

                        let progress = 0;
                        const interval = setInterval(() => {
                            progress += Math.random() * 30;
                            progressBar.style.width = Math.min(progress, 90) + '%';
                        }, 100);
                    }
                });
            });

        // Keyboard accessibility
        document.addEventListener('keydown', function(e) {
            // Close mobile menu on Escape
            if (e.key === 'Escape' && nav.classList.contains('active')) {
                mobileMenuBtn.classList.remove('active');
                nav.classList.remove('active');
                body.style.overflow = '';
            }
        });

        // Add ARIA attributes for accessibility
        mobileMenuBtn.setAttribute('aria-label', 'Toggle mobile menu');
        mobileMenuBtn.setAttribute('aria-expanded', 'false');

        mobileMenuBtn.addEventListener('click', function() {
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !isExpanded);
        });

        // Performance optimization: Debounce scroll events
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Optimized scroll handler
        const optimizedScroll = debounce(() => {
            const currentScroll = window.pageYOffset;

            if (currentScroll > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }, 10);

        window.addEventListener('scroll', optimizedScroll, {
            passive: true
        });

        // Initialize tooltips if needed
        function initTooltips() {
            const tooltips = document.querySelectorAll('[data-tooltip]');

            tooltips.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = this.getAttribute('data-tooltip');

                    document.body.appendChild(tooltip);

                    const rect = this.getBoundingClientRect();
                    tooltip.style.position = 'absolute';
                    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
                    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) +
                        'px';

                    this.addEventListener('mouseleave', function() {
                        tooltip.remove();
                    }, {
                        once: true
                    });
                });
            });
        }

        // Call tooltip initialization if needed
        if (document.querySelector('[data-tooltip]')) {
            initTooltips();
        }

        // Page visibility API for performance
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Pause any animations or intervals when page is hidden
                console.log('Page is hidden');
            } else {
                // Resume animations when page is visible
                console.log('Page is visible');
            }
        });

        // Error handling for broken images
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function() {
                this.src = '<?php echo BASE_URL; ?>assets/placeholder.jpg';
                this.alt = 'Image not available';
            });
        });

        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

        function init() {
            // Any additional initialization code
            console.log('Header initialized successfully');
        }
        </script>

        <!-- End of header.php -->