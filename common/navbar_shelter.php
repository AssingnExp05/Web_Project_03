<?php
// common/navbar_shelter.php - Shelter Navigation Bar
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
            <i class="fas fa-paw"></i>
            Pet Care Guide
            <span class="user-type-badge">Shelter</span>
        </a>
        <div class="nav-links" id="navLinks">
            <a href="<?php echo $BASE_URL; ?>shelter/dashboard.php"
                class="nav-link <?php echo ($current_page === 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?php echo $BASE_URL; ?>shelter/addPet.php"
                class="nav-link <?php echo ($current_page === 'addPet') ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Add Pet</span>
            </a>
            <a href="<?php echo $BASE_URL; ?>shelter/viewPets.php"
                class="nav-link <?php echo ($current_page === 'viewPets') ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>View Pets</span>
            </a>
            <a href="<?php echo $BASE_URL; ?>shelter/adoptionRequests.php"
                class="nav-link <?php echo ($current_page === 'adoptionRequests') ? 'active' : ''; ?>">
                <i class="fas fa-heart"></i>
                <span>Adoption Requests</span>
                <?php
                // Show notification badge for pending requests
                try {
                    require_once __DIR__ . '/../config/db.php';
                    $db = getDB();
                    if ($db && isset($_SESSION['user_id'])) {
                        $stmt = $db->prepare("
                            SELECT COUNT(*) as pending_count 
                            FROM adoption_applications aa 
                            JOIN pets p ON aa.pet_id = p.pet_id 
                            JOIN shelters s ON p.shelter_id = s.shelter_id 
                            WHERE s.user_id = ? AND aa.application_status = 'pending'
                        ");
                        $stmt->execute([$_SESSION['user_id']]);
                        $result = $stmt->fetch();
                        $pending_count = $result ? (int)$result['pending_count'] : 0;
                        
                        if ($pending_count > 0) {
                            echo '<span class="notification-badge">' . $pending_count . '</span>';
                        }
                    }
                } catch (Exception $e) {
                    // Silently handle database errors
                }
                ?>
            </a>
            <a href="<?php echo $BASE_URL; ?>shelter/vaccinationTracker.php"
                class="nav-link <?php echo ($current_page === 'vaccinationTracker') ? 'active' : ''; ?>">
                <i class="fas fa-syringe"></i>
                <span>Vaccination Tracker</span>
            </a>
            <a href="<?php echo $BASE_URL; ?>shelter/addCareGuide.php"
                class="nav-link <?php echo ($current_page === 'addCareGuide') ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i>
                <span>Add Care Guide</span>
            </a>

            <!-- User Info Section -->
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr(($_SESSION['first_name'] ?? 'S'), 0, 1)); ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Shelter'); ?></span>
                    <span class="user-role">Shelter Manager</span>
                </div>
                <div class="user-dropdown">
                    <button class="dropdown-toggle" onclick="toggleUserDropdown()">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="userDropdown">
                        <a href="<?php echo $BASE_URL; ?>shelter/profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            Profile Settings
                        </a>
                        <a href="<?php echo $BASE_URL; ?>shelter/settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            Shelter Settings
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
/* Navigation Bar Styles */
.navbar {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    padding: 1rem 0;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
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
    font-size: 1.6rem;
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

.user-type-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.nav-links {
    display: flex;
    gap: 8px;
    align-items: center;
    flex: 1;
    justify-content: center;
}

.nav-link {
    color: white;
    text-decoration: none;
    padding: 12px 18px;
    border-radius: 25px;
    transition: all 0.3s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    position: relative;
    font-size: 0.9rem;
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

/* Notification Badge */
.notification-badge {
    position: absolute;
    top: 8px;
    right: 12px;
    background: #dc3545;
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

/* User Info Section */
.user-info {
    color: white;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: 20px;
    position: relative;
}

.user-avatar {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #ffd700, #ffed4e);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #28a745;
    font-size: 1.1rem;
    border: 3px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
}

.user-avatar:hover {
    transform: scale(1.1);
    border-color: rgba(255, 255, 255, 0.5);
}

.user-details {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.user-name {
    font-weight: 600;
    font-size: 1rem;
    line-height: 1.2;
}

.user-role {
    font-size: 0.8rem;
    opacity: 0.8;
    color: #ffd700;
}

/* Dropdown Menu */
.user-dropdown {
    position: relative;
}

.dropdown-toggle {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.dropdown-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: rotate(180deg);
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    min-width: 220px;
    padding: 10px 0;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    z-index: 1001;
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.dropdown-menu.show {
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
    color: #28a745;
    text-decoration: none;
    padding-left: 25px;
}

.dropdown-item.logout-btn:hover {
    background: #fff5f5;
    color: #dc3545;
}

.dropdown-divider {
    height: 1px;
    background: #e9ecef;
    margin: 8px 0;
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
        gap: 6px;
    }

    .nav-link {
        padding: 10px 14px;
        font-size: 0.85rem;
    }

    .nav-link span {
        display: none;
    }

    .user-details {
        display: none;
    }
}

@media (max-width: 1024px) {
    .nav-links {
        gap: 4px;
    }

    .nav-link {
        padding: 10px 12px;
        font-size: 0.9rem;
    }
}

@media (max-width: 768px) {
    .nav-container {
        padding: 0 15px;
    }

    .logo {
        font-size: 1.4rem;
    }

    .user-type-badge {
        display: none;
    }

    .nav-links {
        position: fixed;
        top: 80px;
        right: -100%;
        width: 85%;
        height: calc(100vh - 80px);
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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

    .user-info {
        margin-left: 0;
        margin-top: 30px;
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
        padding-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        width: 100%;
    }

    .user-details {
        display: flex;
    }

    .dropdown-menu {
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

    .dropdown-toggle {
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
        transform: translateY(-10px);
    }

    80% {
        transform: translateY(-5px);
    }
}

/* Focus states for accessibility */
.nav-link:focus,
.dropdown-toggle:focus {
    outline: 2px solid #ffd700;
    outline-offset: 2px;
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

function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const userDropdown = document.getElementById('userDropdown');
    const dropdownToggle = document.querySelector('.dropdown-toggle');

    if (!dropdownToggle.contains(event.target) && !userDropdown.contains(event.target)) {
        userDropdown.classList.remove('show');
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
        document.getElementById('userDropdown').classList.remove('show');
        const navLinks = document.getElementById('navLinks');
        if (navLinks.classList.contains('active')) {
            toggleMobileMenu();
        }
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
</script>