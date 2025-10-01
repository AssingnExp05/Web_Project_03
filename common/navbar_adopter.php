<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as adopter
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'adopter') {
    // Redirect to login if not logged in as adopter
    header('Location: ../auth/login.php');
    exit();
}

// Get user information
$user_name = $_SESSION['first_name'] ?? 'User';
$user_email = $_SESSION['email'] ?? '';

// Determine current page for active navigation
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Helper function to check if nav item is active
function isActive($page, $current_page) {
    return $page === $current_page ? 'active' : '';
}

// Helper function to get correct path based on current directory
function getPath($path, $current_dir) {
    if ($current_dir === 'adopter') {
        return $path; // Already in adopter directory
    } else {
        return 'adopter/' . $path; // From root or other directory
    }
}

// Define base path for navigation
$base_path = ($current_dir === 'adopter') ? '' : 'adopter/';
$auth_path = ($current_dir === 'adopter') ? '../auth/' : 'auth/';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    .adopter-navbar {
        background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
        border-bottom: 3px solid #3498db;
    }

    .navbar-container {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 20px;
        height: 70px;
    }

    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #fff;
        text-decoration: none;
        font-size: 1.5rem;
        font-weight: 700;
        transition: all 0.3s ease;
    }

    .navbar-brand:hover {
        color: #3498db;
        transform: scale(1.05);
    }

    .brand-icon {
        background: linear-gradient(135deg, #3498db, #2980b9);
        padding: 10px;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }

    .navbar-nav {
        display: flex;
        list-style: none;
        margin: 0;
        padding: 0;
        gap: 5px;
    }

    .nav-item {
        position: relative;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 18px;
        color: #ecf0f1;
        text-decoration: none;
        border-radius: 25px;
        transition: all 0.3s ease;
        font-weight: 500;
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
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.5s ease;
    }

    .nav-link:hover::before {
        left: 100%;
    }

    .nav-link:hover,
    .nav-link.active {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
    }

    .nav-link.active {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
    }

    .nav-icon {
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
    }

    .navbar-user {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #ecf0f1;
        position: relative;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: bold;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.2);
    }

    .user-avatar:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
    }

    .user-details {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .user-name {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .user-role {
        font-size: 0.8rem;
        color: #bdc3c7;
        background: rgba(52, 152, 219, 0.2);
        padding: 2px 8px;
        border-radius: 10px;
    }

    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 10px;
        background: #fff;
        min-width: 200px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        border-radius: 10px;
        overflow: hidden;
        z-index: 1001;
        border: 1px solid #e9ecef;
    }

    .dropdown-content.show {
        display: block;
        animation: dropdownFadeIn 0.3s ease;
    }

    @keyframes dropdownFadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        color: #495057;
        text-decoration: none;
        transition: all 0.3s ease;
        border-bottom: 1px solid #f8f9fa;
    }

    .dropdown-item:last-child {
        border-bottom: none;
    }

    .dropdown-item:hover {
        background: #f8f9fa;
        color: #3498db;
        padding-left: 20px;
    }

    .dropdown-item.logout:hover {
        background: #fff5f5;
        color: #e74c3c;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #e74c3c;
        color: #fff;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 50%;
        min-width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(231, 76, 60, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(231, 76, 60, 0);
        }
    }

    .mobile-menu-toggle {
        display: none;
        background: none;
        border: none;
        color: #fff;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 5px;
        border-radius: 5px;
        transition: all 0.3s ease;
    }

    .mobile-menu-toggle:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    /* Profile Modal Styles */
    .profile-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;
        backdrop-filter: blur(5px);
    }

    .profile-modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
        animation: modalFadeIn 0.3s ease;
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .profile-modal-content {
        background: #fff;
        border-radius: 15px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        position: relative;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .profile-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f8f9fa;
    }

    .profile-modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #95a5a6;
        cursor: pointer;
        padding: 5px;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .close-modal:hover {
        color: #e74c3c;
        background: #f8f9fa;
    }

    .profile-form-group {
        margin-bottom: 20px;
    }

    .profile-form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #2c3e50;
    }

    .profile-form-group input {
        width: 100%;
        padding: 12px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color 0.3s ease;
    }

    .profile-form-group input:focus {
        outline: none;
        border-color: #3498db;
    }

    .profile-form-group input:disabled {
        background: #f8f9fa;
        cursor: not-allowed;
    }

    .profile-actions {
        display: flex;
        gap: 10px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 2px solid #f8f9fa;
    }

    .btn-profile {
        padding: 12px 20px;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        flex: 1;
    }

    .btn-save {
        background: linear-gradient(135deg, #27ae60, #2ecc71);
        color: #fff;
    }

    .btn-save:hover {
        background: linear-gradient(135deg, #2ecc71, #27ae60);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
    }

    .btn-cancel {
        background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        color: #fff;
    }

    .btn-cancel:hover {
        background: linear-gradient(135deg, #7f8c8d, #95a5a6);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(127, 140, 141, 0.3);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .navbar-container {
            flex-wrap: wrap;
            height: auto;
            padding: 10px 15px;
        }

        .navbar-brand {
            font-size: 1.3rem;
        }

        .brand-icon {
            width: 35px;
            height: 35px;
            padding: 8px;
        }

        .mobile-menu-toggle {
            display: block;
            order: 3;
        }

        .navbar-nav {
            display: none;
            flex-direction: column;
            width: 100%;
            background: rgba(52, 73, 94, 0.95);
            border-radius: 10px;
            margin-top: 15px;
            padding: 15px;
            gap: 8px;
            order: 4;
        }

        .navbar-nav.show {
            display: flex;
            animation: mobileMenuSlide 0.3s ease;
        }

        @keyframes mobileMenuSlide {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .nav-link {
            padding: 12px 15px;
            border-radius: 8px;
            justify-content: flex-start;
        }

        .user-details {
            display: none;
        }

        .dropdown-content {
            right: -10px;
        }

        .profile-modal-content {
            padding: 20px;
            margin: 10px;
        }

        .profile-actions {
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        .navbar-container {
            padding: 8px 10px;
        }

        .navbar-brand {
            font-size: 1.1rem;
        }

        .brand-icon {
            width: 30px;
            height: 30px;
            padding: 6px;
        }

        .nav-link {
            font-size: 0.9rem;
            padding: 10px 12px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            font-size: 1rem;
        }
    }

    /* Smooth scrolling */
    html {
        scroll-behavior: smooth;
    }

    /* Loading animation for page transitions */
    .page-loading {
        position: fixed;
        top: 70px;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #3498db, #e74c3c, #3498db);
        background-size: 200% 100%;
        animation: loading 1s infinite;
        z-index: 1002;
        display: none;
    }

    @keyframes loading {
        0% {
            background-position: 200% 0;
        }

        100% {
            background-position: -200% 0;
        }
    }
    </style>
</head>

<body>
    <nav class="adopter-navbar">
        <div class="navbar-container">
            <!-- Brand -->
            <a href="<?php echo $base_path; ?>dashboard.php" class="navbar-brand">
                <div class="brand-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <span>PetCare</span>
            </a>

            <!-- Navigation Links -->
            <ul class="navbar-nav" id="navbarNav">
                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>dashboard.php"
                        class="nav-link <?php echo isActive('dashboard', $current_page); ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>browsePets.php"
                        class="nav-link <?php echo isActive('browsePets', $current_page); ?>">
                        <i class="nav-icon fas fa-search"></i>
                        <span>Browse Pets</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>myAdoptions.php"
                        class="nav-link <?php echo isActive('myAdoptions', $current_page); ?>">
                        <i class="nav-icon fas fa-heart"></i>
                        <span>My Adoptions</span>
                        <?php
                        // Example: Check for adoption updates
                        $has_updates = false; // Replace with actual logic
                        if ($has_updates): ?>
                        <span class="notification-badge">!</span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>vaccinationTracker.php"
                        class="nav-link <?php echo isActive('vaccinationTracker', $current_page); ?>">
                        <i class="nav-icon fas fa-syringe"></i>
                        <span>Vaccinations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $base_path; ?>careGuides.php"
                        class="nav-link <?php echo isActive('careGuides', $current_page); ?>">
                        <i class="nav-icon fas fa-book-open"></i>
                        <span>Care Guides</span>
                    </a>
                </li>
            </ul>

            <!-- User Info -->
            <div class="navbar-user">
                <div class="dropdown">
                    <div class="user-info" onclick="toggleDropdown()">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="user-role">Adopter</div>
                        </div>
                        <i class="fas fa-chevron-down"
                            style="font-size: 0.8rem; margin-left: 5px; transition: transform 0.3s ease;"
                            id="dropdownArrow"></i>
                    </div>

                    <div class="dropdown-content" id="userDropdown">
                        <a href="#" class="dropdown-item" onclick="viewProfile()">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </a>
                        <a href="<?php echo $base_path; ?>myAdoptions.php" class="dropdown-item">
                            <i class="fas fa-heart"></i>
                            <span>My Adoptions</span>
                        </a>
                        <a href="<?php echo $base_path; ?>vaccinationTracker.php" class="dropdown-item">
                            <i class="fas fa-syringe"></i>
                            <span>Vaccination Tracker</span>
                        </a>
                        <a href="#" class="dropdown-item" onclick="viewSettings()">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        <a href="#" class="dropdown-item" onclick="viewNotifications()">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                        <hr style="margin: 5px 0; border: none; border-top: 1px solid #e9ecef;">
                        <a href="<?php echo $auth_path; ?>logout.php" class="dropdown-item logout"
                            onclick="return confirmLogout()">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>

                <!-- Mobile Menu Toggle -->
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars" id="mobileMenuIcon"></i>
                </button>
            </div>
        </div>

        <!-- Loading Bar -->
        <div class="page-loading" id="pageLoading"></div>
    </nav>

    <!-- Profile Modal -->
    <div class="profile-modal" id="profileModal">
        <div class="profile-modal-content">
            <div class="profile-modal-header">
                <h2 class="profile-modal-title">My Profile</h2>
                <button class="close-modal" onclick="closeProfile()">&times;</button>
            </div>

            <form id="profileForm" onsubmit="return saveProfile(event)">
                <div class="profile-form-group">
                    <label for="profileUsername">Username</label>
                    <input type="text" id="profileUsername"
                        value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" disabled>
                </div>

                <div class="profile-form-group">
                    <label for="profileEmail">Email</label>
                    <input type="email" id="profileEmail" value="<?php echo htmlspecialchars($user_email); ?>" required>
                </div>

                <div class="profile-form-group">
                    <label for="profileFirstName">First Name</label>
                    <input type="text" id="profileFirstName"
                        value="<?php echo htmlspecialchars($_SESSION['first_name'] ?? ''); ?>" required>
                </div>

                <div class="profile-form-group">
                    <label for="profileLastName">Last Name</label>
                    <input type="text" id="profileLastName"
                        value="<?php echo htmlspecialchars($_SESSION['last_name'] ?? ''); ?>" required>
                </div>

                <div class="profile-form-group">
                    <label for="profilePhone">Phone</label>
                    <input type="tel" id="profilePhone"
                        value="<?php echo htmlspecialchars($_SESSION['phone'] ?? ''); ?>">
                </div>

                <div class="profile-form-group">
                    <label for="profileAddress">Address</label>
                    <input type="text" id="profileAddress"
                        value="<?php echo htmlspecialchars($_SESSION['address'] ?? ''); ?>">
                </div>

                <div class="profile-actions">
                    <button type="submit" class="btn-profile btn-save">Save Changes</button>
                    <button type="button" class="btn-profile btn-cancel" onclick="closeProfile()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Dropdown functionality
    function toggleDropdown() {
        const dropdown = document.getElementById('userDropdown');
        const arrow = document.getElementById('dropdownArrow');

        dropdown.classList.toggle('show');

        if (dropdown.classList.contains('show')) {
            arrow.style.transform = 'rotate(180deg)';
        } else {
            arrow.style.transform = 'rotate(0deg)';
        }
    }

    // Mobile menu functionality
    function toggleMobileMenu() {
        const navbarNav = document.getElementById('navbarNav');
        const icon = document.getElementById('mobileMenuIcon');

        navbarNav.classList.toggle('show');

        if (navbarNav.classList.contains('show')) {
            icon.className = 'fas fa-times';
        } else {
            icon.className = 'fas fa-bars';
        }
    }

    // Profile modal functionality
    function viewProfile() {
        const modal = document.getElementById('profileModal');
        modal.classList.add('show');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling

        // Close dropdown
        document.getElementById('userDropdown').classList.remove('show');
        document.getElementById('dropdownArrow').style.transform = 'rotate(0deg)';
    }

    function closeProfile() {
        const modal = document.getElementById('profileModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto'; // Restore scrolling
    }

    function saveProfile(event) {
        event.preventDefault();

        const formData = {
            email: document.getElementById('profileEmail').value,
            first_name: document.getElementById('profileFirstName').value,
            last_name: document.getElementById('profileLastName').value,
            phone: document.getElementById('profilePhone').value,
            address: document.getElementById('profileAddress').value
        };

        // Show loading
        const saveBtn = event.target.querySelector('.btn-save');
        const originalText = saveBtn.textContent;
        saveBtn.textContent = 'Saving...';
        saveBtn.disabled = true;

        // Send AJAX request to update profile
        fetch('<?php echo ($current_dir === "adopter") ? "../" : ""; ?>api/update_profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Profile updated successfully!');
                    // Update session data display
                    document.querySelector('.user-name').textContent = formData.first_name;
                    closeProfile();

                    // Optionally reload the page to reflect changes
                    // window.location.reload();
                } else {
                    alert('Error updating profile: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating your profile. Please try again.');
            })
            .finally(() => {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            });

        return false;
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('userDropdown');
        const userInfo = document.querySelector('.user-info');
        const arrow = document.getElementById('dropdownArrow');

        if (!userInfo.contains(event.target)) {
            dropdown.classList.remove('show');
            arrow.style.transform = 'rotate(0deg)';
        }
    });

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const navbarNav = document.getElementById('navbarNav');
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const navbar = document.querySelector('.adopter-navbar');

        if (!navbar.contains(event.target)) {
            navbarNav.classList.remove('show');
            document.getElementById('mobileMenuIcon').className = 'fas fa-bars';
        }
    });

    // Close profile modal when clicking outside
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('profileModal');
        const modalContent = document.querySelector('.profile-modal-content');

        if (event.target === modal && !modalContent.contains(event.target)) {
            closeProfile();
        }
    });

    // Page loading animation
    function showPageLoading() {
        document.getElementById('pageLoading').style.display = 'block';
    }

    function hidePageLoading() {
        document.getElementById('pageLoading').style.display = 'none';
    }

    // Add loading animation to navigation links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            // Don't show loading for current page
            if (!this.classList.contains('active')) {
                showPageLoading();

                // Hide loading after 2 seconds if page doesn't load
                setTimeout(hidePageLoading, 2000);
            }
        });
    });

    // Hide loading when page loads
    window.addEventListener('load', hidePageLoading);

    // User action functions
    function viewSettings() {
        alert('Settings functionality to be implemented');
    }

    function viewNotifications() {
        alert('Notifications functionality to be implemented');
    }

    function confirmLogout() {
        return confirm('Are you sure you want to logout?');
    }

    // Add smooth scrolling to hash links
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

    // Responsive navigation adjustments
    window.addEventListener('resize', function() {
        const navbarNav = document.getElementById('navbarNav');
        const mobileMenuIcon = document.getElementById('mobileMenuIcon');

        if (window.innerWidth > 768) {
            navbarNav.classList.remove('show');
            mobileMenuIcon.className = 'fas fa-bars';
        }
    });

    // Add keyboard navigation support
    document.addEventListener('keydown', function(e) {
        // Escape key closes dropdowns, mobile menu, and modals
        if (e.key === 'Escape') {
            document.getElementById('userDropdown').classList.remove('show');
            document.getElementById('dropdownArrow').style.transform = 'rotate(0deg)';
            document.getElementById('navbarNav').classList.remove('show');
            document.getElementById('mobileMenuIcon').className = 'fas fa-bars';
            closeProfile();
        }
    });

    // Add focus management for accessibility
    document.querySelectorAll('.nav-link, .dropdown-item').forEach(element => {
        element.addEventListener('focus', function() {
            this.style.outline = '2px solid #3498db';
            this.style.outlineOffset = '2px';
        });

        element.addEventListener('blur', function() {
            this.style.outline = 'none';
        });
    });
    </script>
</body>

</html>