<?php
// common/navbar_admin.php - Admin Navigation Bar
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE_URL = 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="navbar">
    <div class="nav-container">
        <a href="<?php echo $BASE_URL; ?>index.php" class="logo">
            <i class="fas fa-shield-alt"></i>
            Pet Care Admin
            <span class="admin-badge">ADMIN</span>
        </a>
        <div class="nav-links" id="navLinks">
            <a href="<?php echo $BASE_URL; ?>admin/dashboard.php"
                class="nav-link <?php echo ($current_page === 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?php echo $BASE_URL; ?>admin/manageUsers.php"
                class="nav-link <?php echo ($current_page === 'manageUsers') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
                <?php
                // Show notification for pending user approvals (optional)
                try {
                    require_once __DIR__ . '/../config/db.php';
                    $db = getDB();
                    if ($db) {
                        $stmt = $db->prepare("SELECT COUNT(*) as pending_count FROM users WHERE is_active = 0");
                        $stmt->execute();
                        $result = $stmt->fetch();
                        $pending_users = $result ? (int)$result['pending_count'] : 0;
                        
                        if ($pending_users > 0) {
                            echo '<span class="notification-badge">' . $pending_users . '</span>';
                        }
                    }
                } catch (Exception $e) {
                    // Silently handle database errors
                }
                ?>
            </a>
            <a href="<?php echo $BASE_URL; ?>admin/managePets.php"
                class="nav-link <?php echo ($current_page === 'managePets') ? 'active' : ''; ?>">
                <i class="fas fa-paw"></i>
                <span>Manage Pets</span>
            </a>
            <a href="<?php echo $BASE_URL; ?>admin/manageAdoptions.php"
                class="nav-link <?php echo ($current_page === 'manageAdoptions') ? 'active' : ''; ?>">
                <i class="fas fa-heart"></i>
                <span>Manage Adoptions</span>
                <?php
                // Show notification for pending adoptions
                try {
                    if (isset($db)) {
                        $stmt = $db->prepare("SELECT COUNT(*) as pending_adoptions FROM adoption_applications WHERE application_status = 'pending'");
                        $stmt->execute();
                        $result = $stmt->fetch();
                        $pending_adoptions = $result ? (int)$result['pending_adoptions'] : 0;
                        
                        if ($pending_adoptions > 0) {
                            echo '<span class="notification-badge adoption-badge">' . $pending_adoptions . '</span>';
                        }
                    }
                } catch (Exception $e) {
                    // Handle silently
                }
                ?>
            </a>
            <a href="<?php echo $BASE_URL; ?>admin/manageVaccinations.php"
                class="nav-link <?php echo ($current_page === 'manageVaccinations') ? 'active' : ''; ?>">
                <i class="fas fa-syringe"></i>
                <span>Vaccinations</span>
            </a>
            <a href="<?php echo $BASE_URL; ?>admin/reports.php"
                class="nav-link <?php echo ($current_page === 'reports') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>

            <!-- Admin User Info Section -->
            <div class="admin-user-info">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr(($_SESSION['first_name'] ?? 'A'), 0, 1)); ?>
                </div>
                <div class="admin-details">
                    <span class="admin-name"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?></span>
                    <span class="admin-role">System Administrator</span>
                </div>
                <div class="admin-dropdown">
                    <button class="admin-dropdown-toggle" onclick="toggleAdminDropdown()">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="admin-dropdown-menu" id="adminDropdown">
                        <a href="<?php echo $BASE_URL; ?>admin/profile.php" class="dropdown-item">
                            <i class="fas fa-user-cog"></i>
                            Admin Profile
                        </a>
                        <a href="<?php echo $BASE_URL; ?>admin/settings.php" class="dropdown-item">
                            <i class="fas fa-cogs"></i>
                            System Settings
                        </a>
                        <a href="<?php echo $BASE_URL; ?>admin/logs.php" class="dropdown-item">
                            <i class="fas fa-file-alt"></i>
                            System Logs
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo $BASE_URL; ?>admin/backup.php" class="dropdown-item">
                            <i class="fas fa-database"></i>
                            Backup Data
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo $BASE_URL; ?>auth/logout.php" class="dropdown-item logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Toggle -->
        <div class="mobile-menu-toggle" onclick="toggleMobileMenu()">
            <i class="fas fa-bars"></i>
        </div>
    </div>
</nav>

<style>
/* Admin Navigation Bar Styles */
.navbar {
    background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
    padding: 1rem 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    position: sticky;
    top: 0;
    z-index: 1000;
    backdrop-filter: blur(10px);
}

.nav-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    color: white;
    font-size: 1.7rem;
    font-weight: 700;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
}

.logo:hover {
    color: #ffd700;
    text-decoration: none;
    transform: scale(1.05);
}

.admin-badge {
    background: rgba(255, 255, 255, 0.25);
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.4);
    letter-spacing: 1px;
}

.nav-links {
    display: flex;
    gap: 5px;
    align-items: center;
    flex: 1;
    justify-content: center;
}

.nav-link {
    color: white;
    text-decoration: none;
    padding: 12px 18px;
    border-radius: 20px;
    transition: all 0.3s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
    font-size: 0.95rem;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.15);
    text-decoration: none;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.nav-link.active {
    background: rgba(255, 255, 255, 0.25);
    color: #ffd700;
    font-weight: 600;
    box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.3);
}

.nav-link.active::before {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 80%;
    height: 3px;
    background: #ffd700;
    border-radius: 2px;
}

/* Notification Badges */
.notification-badge {
    position: absolute;
    top: 6px;
    right: 10px;
    background: #28a745;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 2s infinite;
    border: 2px solid white;
}

.adoption-badge {
    background: #ff6b6b;
}

@keyframes pulse {
    0% {
        transform: scale(1);
    }

    50% {
        transform: scale(1.1);
    }

    100% {
        transform: scale(1);
    }
}

/* Admin User Info Section */
.admin-user-info {
    color: white;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: 20px;
    position: relative;
}

.admin-avatar {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #ffd700, #ffed4e);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #dc3545;
    font-size: 1.2rem;
    border: 3px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.3);
}

.admin-avatar:hover {
    transform: scale(1.1);
    border-color: rgba(255, 255, 255, 0.5);
    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.5);
}

.admin-details {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.admin-name {
    font-weight: 600;
    font-size: 1rem;
    line-height: 1.2;
}

.admin-role {
    font-size: 0.75rem;
    opacity: 0.85;
    color: #ffd700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Admin Dropdown Menu */
.admin-dropdown {
    position: relative;
}

.admin-dropdown-toggle {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.admin-dropdown-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: rotate(180deg);
}

.admin-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 15px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
    min-width: 250px;
    padding: 15px 0;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    z-index: 1001;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.admin-dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 0.95rem;
}

.dropdown-item:hover {
    background: #f8f9fa;
    color: #dc3545;
    text-decoration: none;
    padding-left: 25px;
}

.dropdown-item.logout-btn:hover {
    background: #fff5f5;
    color: #dc3545;
}

.dropdown-item i {
    width: 16px;
    text-align: center;
}

.dropdown-divider {
    height: 1px;
    background: #e9ecef;
    margin: 10px 0;
}

/* Mobile Menu */
.mobile-menu-toggle {
    display: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.mobile-menu-toggle:hover {
    background: rgba(255, 255, 255, 0.2);
}

/* Mobile Responsiveness */
@media (max-width: 1200px) {
    .nav-links {
        gap: 2px;
    }

    .nav-link {
        padding: 10px 12px;
        font-size: 0.9rem;
    }

    .nav-link span {
        display: none;
    }

    .admin-details {
        display: none;
    }
}

@media (max-width: 768px) {
    .nav-container {
        padding: 0 15px;
    }

    .logo {
        font-size: 1.5rem;
    }

    .admin-badge {
        display: none;
    }

    .nav-links {
        position: fixed;
        top: 80px;
        right: -100%;
        width: 85%;
        height: calc(100vh - 80px);
        background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
        flex-direction: column;
        justify-content: flex-start;
        padding: 30px 20px;
        transition: right 0.3s ease;
        z-index: 1000;
        overflow-y: auto;
        box-shadow: -5px 0 20px rgba(0, 0, 0, 0.3);
    }

    .nav-links.active {
        right: 0;
    }

    .mobile-menu-toggle {
        display: block;
    }

    .nav-link {
        width: 100%;
        text-align: left;
        margin-bottom: 8px;
        padding: 15px 20px;
        border-radius: 12px;
        justify-content: flex-start;
    }

    .nav-link span {
        display: inline;
    }

    .admin-user-info {
        margin-left: 0;
        margin-top: 30px;
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
        padding-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        width: 100%;
    }

    .admin-details {
        display: flex;
    }

    .admin-dropdown-menu {
        position: static;
        opacity: 1;
        visibility: visible;
        transform: none;
        box-shadow: none;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        margin-top: 10px;
    }

    .dropdown-item {
        color: white;
    }

    .dropdown-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #ffd700;
    }

    .admin-dropdown-toggle {
        display: none;
    }
}

/* Animations */
@keyframes slideInFromTop {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.navbar {
    animation: slideInFromTop 0.6s ease-out;
}

/* Hover effects for icons */
.nav-link i {
    transition: all 0.3s ease;
}

.nav-link:hover i {
    transform: scale(1.1);
}

/* Active link animation */
.nav-link.active i {
    animation: bounce 0.6s ease-out;
}

@keyframes bounce {

    0%,
    20%,
    60%,
    100% {
        transform: translateY(0);
    }

    40% {
        transform: translateY(-8px);
    }

    80% {
        transform: translateY(-4px);
    }
}

/* Focus states for accessibility */
.nav-link:focus,
.admin-dropdown-toggle:focus {
    outline: 2px solid #ffd700;
    outline-offset: 2px;
}

/* System status indicator */
.system-status {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 12px;
    height: 12px;
    background: #28a745;
    border: 2px solid white;
    border-radius: 50%;
    animation: statusPulse 3s infinite;
}

@keyframes statusPulse {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.5;
    }
}
</style>

<script>
function toggleMobileMenu() {
    const navLinks = document.getElementById('navLinks');
    navLinks.classList.toggle('active');

    // Change icon
    const toggleIcon = document.querySelector('.mobile-menu-toggle i');
    if (navLinks.classList.contains('active')) {
        toggleIcon.classList.replace('fa-bars', 'fa-times');
    } else {
        toggleIcon.classList.replace('fa-times', 'fa-bars');
    }
}

function toggleAdminDropdown() {
    const dropdown = document.getElementById('adminDropdown');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const adminDropdown = document.getElementById('adminDropdown');
    const dropdownToggle = document.querySelector('.admin-dropdown-toggle');

    if (!dropdownToggle.contains(event.target) && !adminDropdown.contains(event.target)) {
        adminDropdown.classList.remove('show');
    }

    // Close mobile menu when clicking outside
    const navLinks = document.getElementById('navLinks');
    const menuToggle = document.querySelector('.mobile-menu-toggle');

    if (!navLinks.contains(event.target) && !menuToggle.contains(event.target)) {
        navLinks.classList.remove('active');
        const toggleIcon = document.querySelector('.mobile-menu-toggle i');
        toggleIcon.classList.replace('fa-times', 'fa-bars');
    }
});

// Close mobile menu on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        document.getElementById('navLinks').classList.remove('active');
        const toggleIcon = document.querySelector('.mobile-menu-toggle i');
        toggleIcon.classList.replace('fa-times', 'fa-bars');
    }
});

// Add keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('adminDropdown').classList.remove('show');
        const navLinks = document.getElementById('navLinks');
        if (navLinks.classList.contains('active')) {
            toggleMobileMenu();
        }
    }
});

// Admin shortcuts
document.addEventListener('keydown', function(e) {
    if (e.altKey && e.key === 'd') {
        e.preventDefault();
        window.location.href = '<?php echo $BASE_URL; ?>admin/dashboard.php';
    }
    if (e.altKey && e.key === 'u') {
        e.preventDefault();
        window.location.href = '<?php echo $BASE_URL; ?>admin/manageUsers.php';
    }
    if (e.altKey && e.key === 'r') {
        e.preventDefault();
        window.location.href = '<?php echo $BASE_URL; ?>admin/reports.php';
    }
});

// Smooth scroll to top when logo is clicked
document.querySelector('.logo').addEventListener('click', function(e) {
    if (this.href.includes('#')) {
        e.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
});

// Real-time notification updates
setInterval(function() {
    fetch('<?php echo $BASE_URL; ?>admin/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            // Update notification badges
            const usersBadge = document.querySelector('.nav-link[href*="manageUsers"] .notification-badge');
            const adoptionsBadge = document.querySelector(
                '.nav-link[href*="manageAdoptions"] .notification-badge');

            if (data.pending_users > 0) {
                if (!usersBadge) {
                    // Create badge if it doesn't exist
                    const usersLink = document.querySelector('.nav-link[href*="manageUsers"]');
                    const badge = document.createElement('span');
                    badge.className = 'notification-badge';
                    badge.textContent = data.pending_users;
                    usersLink.appendChild(badge);
                } else {
                    usersBadge.textContent = data.pending_users;
                }
            } else if (usersBadge) {
                usersBadge.remove();
            }

            if (data.pending_adoptions > 0) {
                if (!adoptionsBadge) {
                    const adoptionsLink = document.querySelector('.nav-link[href*="manageAdoptions"]');
                    const badge = document.createElement('span');
                    badge.className = 'notification-badge adoption-badge';
                    badge.textContent = data.pending_adoptions;
                    adoptionsLink.appendChild(badge);
                } else {
                    adoptionsBadge.textContent = data.pending_adoptions;
                }
            } else if (adoptionsBadge) {
                adoptionsBadge.remove();
            }
        })
        .catch(error => console.log('Notification update failed:', error));
}, 30000); // Update every 30 seconds
</script>