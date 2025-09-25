<?php
// common/navbar.php - Dynamic Navigation Bar
// This file should be included in header.php

// Ensure session is started and user info is available
if (!isset($_SESSION)) {
    session_start();
}

// Get current page info
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
$user_type = $_SESSION['user_type'] ?? '';
$user_name = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');
$user_email = $_SESSION['email'] ?? '';

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    define('BASE_URL', $protocol . $host . $path);
}
?>

<style>
/* Navbar Specific Styles */
.navbar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.navbar.scrolled {
    background: rgba(102, 126, 234, 0.95);
    backdrop-filter: blur(15px);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
}

.navbar-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 70px;
    position: relative;
}

/* Logo */
.navbar-brand {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: white;
    font-weight: 700;
    font-size: 1.5rem;
    transition: all 0.3s ease;
    z-index: 1001;
}

.navbar-brand:hover {
    color: #ffd700;
    transform: scale(1.05);
    text-decoration: none;
}

.brand-icon {
    font-size: 2rem;
    margin-right: 10px;
    color: #ffd700;
    animation: pulse 2s infinite;
}

/* Navigation Menu */
.navbar-nav {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    align-items: center;
    gap: 5px;
}

.nav-item {
    position: relative;
}

.nav-link {
    color: white;
    text-decoration: none;
    padding: 12px 18px;
    border-radius: 25px;
    font-weight: 500;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
}

.nav-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.nav-link:hover::before {
    left: 100%;
}

.nav-link:hover,
.nav-link.active {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    text-decoration: none;
    color: white;
}

.nav-link.active {
    background: rgba(255, 215, 0, 0.3);
    color: #ffd700;
    font-weight: 600;
}

.nav-icon {
    font-size: 1.1rem;
}

/* Dropdown Menu */
.dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    min-width: 220px;
    border-radius: 15px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    transform: translateX(-50%) translateY(-10px);
    transition: all 0.3s ease;
    z-index: 1002;
    overflow: hidden;
    margin-top: 10px;
}

.dropdown:hover .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.dropdown-menu::before {
    content: '';
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%);
    width: 16px;
    height: 16px;
    background: white;
    border-radius: 3px;
    transform: translateX(-50%) rotate(45deg);
}

.dropdown-item {
    display: block;
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    border-bottom: 1px solid #f8f9fa;
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item:hover {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    text-decoration: none;
    padding-left: 25px;
}

.dropdown-item i {
    width: 20px;
    margin-right: 10px;
    text-align: center;
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
    gap: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #ffd700, #ffed4e);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #667eea;
    font-size: 1.1rem;
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
    transition: all 0.3s ease;
}

.user-avatar:hover {
    transform: scale(1.1);
    box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-name {
    font-weight: 600;
    font-size: 0.95rem;
}

.user-role {
    font-size: 0.8rem;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Auth Buttons */
.auth-buttons {
    display: flex;
    gap: 12px;
}

.btn-nav {
    padding: 10px 20px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
}

.btn-login {
    background: transparent;
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.5);
}

.btn-login:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: white;
    text-decoration: none;
    color: white;
    transform: translateY(-2px);
}

.btn-register {
    background: #ffd700;
    color: #667eea;
    border: 2px solid #ffd700;
}

.btn-register:hover {
    background: #ffed4e;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 215, 0, 0.3);
    text-decoration: none;
    color: #5a67d8;
}

.btn-logout {
    background: rgba(231, 76, 60, 0.2);
    color: white;
    border: 2px solid rgba(231, 76, 60, 0.5);
}

.btn-logout:hover {
    background: #e74c3c;
    border-color: #e74c3c;
    text-decoration: none;
    color: white;
}

/* Mobile Menu Toggle */
.mobile-menu-toggle {
    display: none;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    width: 45px;
    height: 45px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.mobile-menu-toggle:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: scale(1.05);
}

.mobile-menu-toggle.active {
    background: rgba(231, 76, 60, 0.8);
    border-color: #e74c3c;
}

/* Notifications */
.notification-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #e74c3c;
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: bounce 2s infinite;
}

/* Search Bar (if needed) */
.navbar-search {
    position: relative;
    margin: 0 20px;
}

.search-input {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 25px;
    padding: 10px 45px 10px 20px;
    color: white;
    font-size: 0.9rem;
    width: 250px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.search-input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.search-input:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.3);
    border-color: #ffd700;
    width: 300px;
}

.search-btn {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    background: #ffd700;
    border: none;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    color: #667eea;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-btn:hover {
    background: #ffed4e;
    transform: translateY(-50%) scale(1.1);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .navbar-container {
        padding: 0 15px;
    }

    .mobile-menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .navbar-nav {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: rgba(102, 126, 234, 0.98);
        backdrop-filter: blur(15px);
        flex-direction: column;
        padding: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        gap: 10px;
    }

    .navbar-nav.active {
        display: flex;
    }

    .nav-item {
        width: 100%;
    }

    .nav-link {
        width: 100%;
        justify-content: center;
        padding: 15px 20px;
        border-radius: 12px;
    }

    .dropdown-menu {
        position: static;
        transform: none;
        opacity: 1;
        visibility: visible;
        background: rgba(255, 255, 255, 0.1);
        box-shadow: none;
        margin: 10px 0;
    }

    .dropdown-menu::before {
        display: none;
    }

    .dropdown-item {
        color: white;
        border-color: rgba(255, 255, 255, 0.1);
    }

    .dropdown-item:hover {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .user-details {
        display: none;
    }

    .navbar-search {
        display: none;
    }

    .auth-buttons {
        flex-direction: column;
        gap: 8px;
        width: 100%;
    }

    .btn-nav {
        width: 100%;
        justify-content: center;
    }
}

/* Animations */
@keyframes pulse {

    0%,
    100% {
        transform: scale(1);
    }

    50% {
        transform: scale(1.1);
    }
}

@keyframes bounce {

    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-5px);
    }
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.navbar {
    animation: fadeInDown 0.6s ease-out;
}

/* Scroll Progress Bar */
.scroll-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: linear-gradient(90deg, #ffd700, #ffed4e);
    transition: width 0.1s ease;
    z-index: 1003;
}
</style>

<nav class="navbar" id="navbar">
    <div class="navbar-container">
        <!-- Brand/Logo -->
        <a href="<?php echo BASE_URL; ?>index.php" class="navbar-brand">
            <i class="fas fa-paw brand-icon"></i>
            <span>Pet Care Guide</span>
        </a>

        <!-- Navigation Menu -->
        <ul class="navbar-nav" id="navbarNav">
            <!-- Home -->
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>index.php"
                    class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                    <i class="fas fa-home nav-icon"></i>
                    <span>Home</span>
                </a>
            </li>

            <!-- About -->
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>about.php"
                    class="nav-link <?php echo ($current_page == 'about.php') ? 'active' : ''; ?>">
                    <i class="fas fa-info-circle nav-icon"></i>
                    <span>About</span>
                </a>
            </li>

            <!-- Browse Pets Dropdown -->
            <li class="nav-item dropdown">
                <a href="<?php echo BASE_URL; ?>adopter/browsePets.php"
                    class="nav-link <?php echo ($current_page == 'browsePets.php' || $current_dir == 'adopter') ? 'active' : ''; ?>">
                    <i class="fas fa-search nav-icon"></i>
                    <span>Find Pets</span>
                    <i class="fas fa-chevron-down" style="font-size: 0.8rem; margin-left: 5px;"></i>
                </a>
                <div class="dropdown-menu">
                    <a href="<?php echo BASE_URL; ?>adopter/browsePets.php" class="dropdown-item">
                        <i class="fas fa-list"></i> All Pets
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
                    <a href="<?php echo BASE_URL; ?>adopter/browsePets.php?category=other" class="dropdown-item">
                        <i class="fas fa-paw"></i> Other Pets
                    </a>
                </div>
            </li>

            <!-- Care Guides -->
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>adopter/careGuides.php"
                    class="nav-link <?php echo ($current_page == 'careGuides.php') ? 'active' : ''; ?>">
                    <i class="fas fa-book nav-icon"></i>
                    <span>Care Guides</span>
                </a>
            </li>

            <!-- Contact -->
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>contact.php"
                    class="nav-link <?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>">
                    <i class="fas fa-envelope nav-icon"></i>
                    <span>Contact</span>
                </a>
            </li>

            <!-- User-specific Navigation -->
            <?php if ($is_logged_in): ?>
            <?php if ($user_type === 'admin'): ?>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php"
                    class="nav-link <?php echo ($current_dir == 'admin') ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt nav-icon"></i>
                    <span>Admin</span>
                    <?php 
                        // Example: Get pending items count
                        try {
                            $db = getDB();
                            $pending_count = $db->query("SELECT COUNT(*) FROM adoption_applications WHERE application_status = 'pending'")->fetchColumn();
                            if ($pending_count > 0): 
                        ?>
                    <span class="notification-badge"><?php echo $pending_count; ?></span>
                    <?php endif; } catch (Exception $e) {} ?>
                </a>
            </li>
            <?php elseif ($user_type === 'shelter'): ?>
            <li class="nav-item dropdown">
                <a href="<?php echo BASE_URL; ?>shelter/dashboard.php"
                    class="nav-link <?php echo ($current_dir == 'shelter') ? 'active' : ''; ?>">
                    <i class="fas fa-home nav-icon"></i>
                    <span>My Shelter</span>
                    <i class="fas fa-chevron-down" style="font-size: 0.8rem; margin-left: 5px;"></i>
                </a>
                <div class="dropdown-menu">
                    <a href="<?php echo BASE_URL; ?>shelter/dashboard.php" class="dropdown-item">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="<?php echo BASE_URL; ?>shelter/addPet.php" class="dropdown-item">
                        <i class="fas fa-plus"></i> Add Pet
                    </a>
                    <a href="<?php echo BASE_URL; ?>shelter/viewPets.php" class="dropdown-item">
                        <i class="fas fa-list"></i> My Pets
                    </a>
                    <a href="<?php echo BASE_URL; ?>shelter/adoptionRequests.php" class="dropdown-item">
                        <i class="fas fa-heart"></i> Applications
                    </a>
                </div>
            </li>
            <?php else: ?>
            <li class="nav-item dropdown">
                <a href="<?php echo BASE_URL; ?>adopter/dashboard.php"
                    class="nav-link <?php echo ($current_dir == 'adopter' && $current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-heart nav-icon"></i>
                    <span>My Account</span>
                    <i class="fas fa-chevron-down" style="font-size: 0.8rem; margin-left: 5px;"></i>
                </a>
                <div class="dropdown-menu">
                    <a href="<?php echo BASE_URL; ?>adopter/dashboard.php" class="dropdown-item">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="<?php echo BASE_URL; ?>adopter/myAdoptions.php" class="dropdown-item">
                        <i class="fas fa-heart"></i> My Adoptions
                    </a>
                    <a href="<?php echo BASE_URL; ?>adopter/favorites.php" class="dropdown-item">
                        <i class="fas fa-star"></i> Favorites
                    </a>
                </div>
            </li>
            <?php endif; ?>
            <?php endif; ?>
        </ul>

        <!-- Search Bar (Optional) -->
        <div class="navbar-search" style="display: none;">
            <input type="text" class="search-input" placeholder="Search pets..." id="navbarSearch">
            <button type="button" class="search-btn" onclick="performSearch()">
                <i class="fas fa-search"></i>
            </button>
        </div>

        <!-- User Menu / Auth Buttons -->
        <div class="user-menu">
            <?php if ($is_logged_in): ?>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr(($_SESSION['first_name'] ?? 'U'), 0, 1)); ?>
                </div>
                <div class="user-details">
                    <span class="user-name">
                        <?php echo htmlspecialchars(trim($user_name)); ?>
                    </span>
                    <span class="user-role">
                        <?php echo htmlspecialchars($user_type); ?>
                    </span>
                </div>
            </div>
            <a href="<?php echo BASE_URL; ?>auth/logout.php" class="btn-nav btn-logout"
                onclick="return confirmLogout()">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
            <?php else: ?>
            <div class="auth-buttons">
                <a href="<?php echo BASE_URL; ?>auth/login.php" class="btn-nav btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>
                <a href="<?php echo BASE_URL; ?>auth/register.php" class="btn-nav btn-register">
                    <i class="fas fa-user-plus"></i>
                    <span>Register</span>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Mobile Menu Toggle -->
        <button class="mobile-menu-toggle" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Scroll Progress Bar -->
    <div class="scroll-progress" id="scrollProgress"></div>
</nav>

<script>
// Navbar JavaScript Functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeNavbar();
    setupScrollEffects();
    setupMobileMenu();
    setupSearch();
});

function initializeNavbar() {
    console.log('Navbar initialized successfully');

    // Add smooth hover effects
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });

        link.addEventListener('mouseleave', function() {
            if (!this.classList.contains('active')) {
                this.style.transform = 'translateY(0)';
            }
        });
    });
}

function setupScrollEffects() {
    const navbar = document.getElementById('navbar');
    const scrollProgress = document.getElementById('scrollProgress');

    window.addEventListener('scroll', function() {
        // Navbar scroll effect
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }

        // Progress bar
        const windowHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrolled = (window.scrollY / windowHeight) * 100;
        scrollProgress.style.width = Math.min(scrolled, 100) + '%';
    });
}

function setupMobileMenu() {
    const mobileToggle = document.getElementById('mobileMenuToggle');
    const navbarNav = document.getElementById('navbarNav');

    if (mobileToggle && navbarNav) {
        mobileToggle.addEventListener('click', function() {
            navbarNav.classList.toggle('active');
            mobileToggle.classList.toggle('active');

            const icon = mobileToggle.querySelector('i');
            if (navbarNav.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!navbar.contains(e.target)) {
                navbarNav.classList.remove('active');
                mobileToggle.classList.remove('active');
                const icon = mobileToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }
}

function setupSearch() {
    const searchInput = document.getElementById('navbarSearch');
    if (!searchInput) return;

    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });
}

function performSearch() {
    const searchInput = document.getElementById('navbarSearch');
    if (!searchInput) return;

    const query = searchInput.value.trim();
    if (query) {
        window.location.href = `<?php echo BASE_URL; ?>adopter/browsePets.php?search=${encodeURIComponent(query)}`;
    }
}

function confirmLogout() {
    return confirm('Are you sure you want to logout?');
}

// Add keyboard navigation
document.addEventListener('keydown', function(e) {
    // Alt + H for Home
    if (e.altKey && e.key === 'h') {
        e.preventDefault();
        window.location.href = '<?php echo BASE_URL; ?>index.php';
    }

    // Alt + A for About
    if (e.altKey && e.key === 'a') {
        e.preventDefault();
        window.location.href = '<?php echo BASE_URL; ?>about.php';
    }

    // Alt + P for Pets
    if (e.altKey && e.key === 'p') {
        e.preventDefault();
        window.location.href = '<?php echo BASE_URL; ?>adopter/browsePets.php';
    }

    // Escape to close mobile menu
    if (e.key === 'Escape') {
        const navbarNav = document.getElementById('navbarNav');
        const mobileToggle = document.getElementById('mobileMenuToggle');
        if (navbarNav && navbarNav.classList.contains('active')) {
            navbarNav.classList.remove('active');
            mobileToggle.classList.remove('active');
            const icon = mobileToggle.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }
});

// Auto-hide mobile menu on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        const navbarNav = document.getElementById('navbarNav');
        const mobileToggle = document.getElementById('mobileMenuToggle');
        if (navbarNav && navbarNav.classList.contains('active')) {
            navbarNav.classList.remove('active');
            mobileToggle.classList.remove('active');
            const icon = mobileToggle.querySelector('i');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }
});

// Add loading states to navigation links
document.querySelectorAll('.nav-link, .btn-nav').forEach(link => {
    link.addEventListener('click', function(e) {
        // Don't add loading to dropdown toggles or external links
        if (this.querySelector('.fa-chevron-down') ||
            this.getAttribute('href').startsWith('http') ||
            this.getAttribute('onclick')) {
            return;
        }

        const icon = this.querySelector('i');
        if (icon && !icon.classList.contains('fa-spin')) {
            const originalClasses = icon.className;
            icon.className = 'fas fa-spinner fa-spin';

            // Restore original icon after navigation
            setTimeout(() => {
                icon.className = originalClasses;
            }, 2000);
        }
    });
});

console.log('Navbar features loaded:');
console.log('- Responsive mobile menu');
console.log('- Scroll effects and progress bar');
console.log('- Keyboard shortcuts (Alt+H, Alt+A, Alt+P)');
console.log('- Dynamic user authentication');
console.log('- Dropdown menus with hover effects');
</script>