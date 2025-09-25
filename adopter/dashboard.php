<?php
// adopter/dashboard.php - Adopter Dashboard Page
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Base URL
$BASE_URL = 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Check if user is logged in and is an adopter
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'adopter') {
    header('Location: ' . $BASE_URL . 'auth/login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$page_title = 'Adopter Dashboard - Pet Adoption Care Guide';

// Initialize default values
$applications_count = 0;
$pending_count = 0;
$approved_count = 0;
$adoptions_count = 0;
$applications = [];
$my_pets = [];
$recommendations = [];

// Try to connect to database
try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if ($db) {
        // Get statistics
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $applications_count = $result ? (int)$result['count'] : 0;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ? AND application_status = 'pending'");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $pending_count = $result ? (int)$result['count'] : 0;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ? AND application_status = 'approved'");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $approved_count = $result ? (int)$result['count'] : 0;
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoptions WHERE adopter_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $adoptions_count = $result ? (int)$result['count'] : 0;
    }
} catch (Exception $e) {
    // Database connection failed, continue with default values
    error_log("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        color: #333;
        line-height: 1.6;
        min-height: 100vh;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 20px;
        padding: 40px;
        margin-bottom: 30px;
        text-align: center;
    }

    .header h1 {
        font-size: 2.5rem;
        margin-bottom: 10px;
        font-weight: 700;
    }

    .header p {
        font-size: 1.2rem;
        margin-bottom: 30px;
        opacity: 0.9;
    }

    .header-buttons {
        display: flex;
        gap: 20px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .btn {
        padding: 12px 30px;
        border: none;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
    }

    .btn-primary {
        background: #ffd700;
        color: #667eea;
    }

    .btn-primary:hover {
        background: #ffed4e;
        transform: translateY(-2px);
        text-decoration: none;
        color: #5a67d8;
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.3);
        text-decoration: none;
        color: white;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: var(--color);
    }

    .stat-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .stat-card.applications {
        --color: #667eea;
    }

    .stat-card.pending {
        --color: #f39c12;
    }

    .stat-card.approved {
        --color: #27ae60;
    }

    .stat-card.adoptions {
        --color: #e74c3c;
    }

    .stat-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .stat-info h3 {
        color: #666;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
    }

    .stat-number {
        font-size: 3rem;
        font-weight: 700;
        color: var(--color);
        line-height: 1;
    }

    .stat-icon {
        width: 70px;
        height: 70px;
        border-radius: 15px;
        background: var(--color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        opacity: 0.9;
    }

    .section {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .section-header {
        background: #f8f9fa;
        padding: 25px 30px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .section-title i {
        color: #667eea;
        font-size: 1.3rem;
    }

    .section-link {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: color 0.3s ease;
    }

    .section-link:hover {
        color: #5a67d8;
        text-decoration: none;
    }

    .section-content {
        padding: 30px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 30px;
        color: #666;
    }

    .empty-icon {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
        color: #667eea;
    }

    .empty-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 15px;
        color: #2c3e50;
    }

    .empty-text {
        margin-bottom: 30px;
        line-height: 1.8;
        font-size: 1.1rem;
    }

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
    }

    .action-card {
        background: #f8f9fa;
        padding: 30px;
        border-radius: 15px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        border: 2px solid transparent;
    }

    .action-card:hover {
        background: white;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        transform: translateY(-5px);
        border-color: var(--hover-color);
    }

    .action-card.find {
        --hover-color: #667eea;
    }

    .action-card.track {
        --hover-color: #f39c12;
    }

    .action-card.guides {
        --hover-color: #27ae60;
    }

    .action-card.help {
        --hover-color: #e74c3c;
    }

    .action-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        color: white;
        font-size: 2rem;
        transition: all 0.3s ease;
    }

    .action-card.find .action-icon {
        background: #667eea;
    }

    .action-card.track .action-icon {
        background: #f39c12;
    }

    .action-card.guides .action-icon {
        background: #27ae60;
    }

    .action-card.help .action-icon {
        background: #e74c3c;
    }

    .action-title {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 15px;
        color: #2c3e50;
    }

    .action-text {
        color: #666;
        margin-bottom: 25px;
        line-height: 1.6;
    }

    .tips-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 25px;
    }

    .tip-card {
        padding: 25px;
        border-radius: 15px;
        border-left: 5px solid var(--tip-color);
        background: linear-gradient(135deg, var(--tip-bg), var(--tip-bg2));
        transition: all 0.3s ease;
    }

    .tip-card:hover {
        transform: translateX(10px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .tip-card.home {
        --tip-color: #667eea;
        --tip-bg: #667eea15;
        --tip-bg2: #764ba215;
    }

    .tip-card.patience {
        --tip-color: #f39c12;
        --tip-bg: #f39c1215;
        --tip-bg2: #e67e2215;
    }

    .tip-card.vet {
        --tip-color: #27ae60;
        --tip-bg: #27ae6015;
        --tip-bg2: #2ecc7115;
    }

    .tip-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }

    .tip-icon {
        color: var(--tip-color);
        font-size: 1.5rem;
    }

    .tip-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
    }

    .tip-text {
        color: #666;
        line-height: 1.7;
    }

    .footer {
        background: #2c3e50;
        color: white;
        padding: 50px 0;
        margin-top: 50px;
    }

    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 40px;
    }

    .footer-section h4 {
        color: #ffd700;
        margin-bottom: 20px;
        font-size: 1.2rem;
    }

    .footer-section p {
        color: #bdc3c7;
        line-height: 1.8;
        margin-bottom: 20px;
    }

    .footer-links {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .footer-links a {
        color: #bdc3c7;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .footer-links a:hover {
        color: #ffd700;
    }

    .social-links {
        display: flex;
        gap: 15px;
    }

    .social-links a {
        color: #ffd700;
        font-size: 1.5rem;
        transition: color 0.3s ease;
    }

    .social-links a:hover {
        color: white;
    }

    .footer-bottom {
        border-top: 1px solid #34495e;
        padding-top: 20px;
        margin-top: 30px;
        text-align: center;
        color: #bdc3c7;
    }

    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .header {
            padding: 30px 20px;
        }

        .header h1 {
            font-size: 2rem;
        }

        .header-buttons {
            flex-direction: column;
            align-items: center;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .quick-actions {
            grid-template-columns: 1fr;
        }

        .tips-grid {
            grid-template-columns: 1fr;
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate {
        animation: fadeIn 0.8s ease-out;
    }
    </style>
</head>

<body>
    <?php include_once __DIR__ . '/../common/navbar_adopter.php'; ?>

    <div class="container">
        <!-- Welcome Header -->
        <div class="header animate">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Friend'); ?>! 👋</h1>
            <p>Ready to find your perfect companion or check on your applications?</p>
            <div class="header-buttons">
                <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Browse Available Pets
                </a>
                <a href="<?php echo $BASE_URL; ?>adopter/careGuides.php" class="btn btn-secondary">
                    <i class="fas fa-book"></i>
                    Care Guides
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid animate">
            <div class="stat-card applications">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Applications</h3>
                        <div class="stat-number"><?php echo $applications_count; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card pending">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Pending Reviews</h3>
                        <div class="stat-number"><?php echo $pending_count; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card approved">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Approved Applications</h3>
                        <div class="stat-number"><?php echo $approved_count; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card adoptions">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Successful Adoptions</h3>
                        <div class="stat-number"><?php echo $adoptions_count; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Applications -->
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-file-alt"></i>
                    Recent Applications
                </h2>
                <a href="<?php echo $BASE_URL; ?>adopter/myAdoptions.php" class="section-link">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="section-content">
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3 class="empty-title">No Applications Yet</h3>
                    <p class="empty-text">
                        You haven't submitted any adoption applications yet. Start browsing our available pets to find
                        your perfect companion!
                    </p>
                    <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Browse Available Pets
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-bolt"></i>
                    Quick Actions
                </h2>
            </div>
            <div class="section-content">
                <div class="quick-actions">
                    <div class="action-card find"
                        onclick="window.location.href='<?php echo $BASE_URL; ?>adopter/browsePets.php'">
                        <div class="action-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 class="action-title">Find a Pet</h3>
                        <p class="action-text">Browse through our available pets and find your perfect companion</p>
                        <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php" class="btn btn-primary">
                            Browse Now
                        </a>
                    </div>

                    <div class="action-card track"
                        onclick="window.location.href='<?php echo $BASE_URL; ?>adopter/myAdoptions.php'">
                        <div class="action-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3 class="action-title">Track Applications</h3>
                        <p class="action-text">Check the status of your adoption applications and follow up</p>
                        <a href="<?php echo $BASE_URL; ?>adopter/myAdoptions.php" class="btn btn-primary"
                            style="background: #f39c12;">
                            View Applications
                        </a>
                    </div>

                    <div class="action-card guides"
                        onclick="window.location.href='<?php echo $BASE_URL; ?>adopter/careGuides.php'">
                        <div class="action-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3 class="action-title">Pet Care Guides</h3>
                        <p class="action-text">Learn how to properly care for your new furry family member</p>
                        <a href="<?php echo $BASE_URL; ?>adopter/careGuides.php" class="btn btn-primary"
                            style="background: #27ae60;">
                            Read Guides
                        </a>
                    </div>

                    <div class="action-card help" onclick="window.location.href='<?php echo $BASE_URL; ?>contact.php'">
                        <div class="action-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h3 class="action-title">Get Help</h3>
                        <p class="action-text">Need assistance with the adoption process? Contact our support team</p>
                        <a href="<?php echo $BASE_URL; ?>contact.php" class="btn btn-primary"
                            style="background: #e74c3c;">
                            Contact Us
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Adoption Tips -->
        <div class="section animate">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-lightbulb"></i>
                    Adoption Tips & Advice
                </h2>
            </div>
            <div class="section-content">
                <div class="tips-grid">
                    <div class="tip-card home">
                        <div class="tip-header">
                            <i class="fas fa-home tip-icon"></i>
                            <h4 class="tip-title">Prepare Your Home</h4>
                        </div>
                        <p class="tip-text">
                            Pet-proof your home by removing hazards, setting up a comfortable space, and gathering
                            necessary supplies like food bowls, toys, and bedding before your new pet arrives.
                        </p>
                    </div>

                    <div class="tip-card patience">
                        <div class="tip-header">
                            <i class="fas fa-clock tip-icon"></i>
                            <h4 class="tip-title">Be Patient</h4>
                        </div>
                        <p class="tip-text">
                            Give your new pet time to adjust to their new environment. It may take weeks or even months
                            for them to feel completely comfortable and show their true personality.
                        </p>
                    </div>

                    <div class="tip-card vet">
                        <div class="tip-header">
                            <i class="fas fa-user-md tip-icon"></i>
                            <h4 class="tip-title">Find a Veterinarian</h4>
                        </div>
                        <p class="tip-text">
                            Locate a trusted veterinarian in your area and schedule a comprehensive check-up within the
                            first week of bringing your new pet home to ensure they're healthy.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4><i class="fas fa-paw"></i> Pet Care Guide</h4>
                <p>Connecting loving families with pets in need of homes. Together, we're making a difference one
                    adoption at a time.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <div class="footer-links">
                    <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php">Browse Pets</a>
                    <a href="<?php echo $BASE_URL; ?>adopter/careGuides.php">Care Guides</a>
                    <a href="<?php echo $BASE_URL; ?>about.php">About Us</a>
                    <a href="<?php echo $BASE_URL; ?>contact.php">Contact</a>
                </div>
            </div>
            <div class="footer-section">
                <h4>Support</h4>
                <div class="footer-links">
                    <a href="#">Help Center</a>
                    <a href="#">Adoption Process</a>
                    <a href="#">FAQ</a>
                    <a href="#">Privacy Policy</a>
                </div>
            </div>
            <div class="footer-section">
                <h4>Contact Info</h4>
                <p><i class="fas fa-envelope"></i> support@petcareguide.com</p>
                <p><i class="fas fa-phone"></i> (555) 123-4567</p>
                <p><i class="fas fa-map-marker-alt"></i> 123 Pet Street, Animal City</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Pet Adoption Care Guide. All rights reserved. Made with <i
                    class="fas fa-heart" style="color: #e74c3c;"></i> for pets and their families.</p>
        </div>
    </footer>

    <script>
    // Add animations on scroll
    document.addEventListener('DOMContentLoaded', function() {
        // Animate stats counters
        const counters = document.querySelectorAll('.stat-number');
        counters.forEach(counter => {
            const target = parseInt(counter.textContent);
            if (target > 0) {
                let current = 0;
                const increment = target / 30;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current);
                }, 50);
            }
        });

        // Add hover effects
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.tagName.toLowerCase() !== 'a') {
                    const link = this.querySelector('a');
                    if (link) {
                        window.location.href = link.href;
                    }
                }
            });
        });

        // Auto-hide messages
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 300);
            });
        }, 5000);
    });
    </script>

    <!-- Display messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div style="position: fixed; top: 20px; right: 20px; background: #27ae60; color: white; padding: 15px 20px; border-radius: 10px; z-index: 1001; box-shadow: 0 5px 15px rgba(0,0,0,0.2);"
        class="message">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div style="position: fixed; top: 20px; right: 20px; background: #e74c3c; color: white; padding: 15px 20px; border-radius: 10px; z-index: 1001; box-shadow: 0 5px 15px rgba(0,0,0,0.2);"
        class="message">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>
</body>

</html>