<?php
// common/footer.php
?>

<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <h3>Pet Adoption Care Guide</h3>
            <p>Connecting loving families with pets in need of forever homes.</p>
            <div class="social-links">
                <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
            </div>
        </div>

        <div class="footer-section">
            <h4>Quick Links</h4>
            <ul class="footer-links">
                <li><a href="<?php echo BASE_URL; ?>">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>about.php">About Us</a></li>
                <li><a href="<?php echo BASE_URL; ?>adopter/browsePets.php">Browse Pets</a></li>
                <li><a href="<?php echo BASE_URL; ?>adopter/careGuides.php">Care Guides</a></li>
                <li><a href="<?php echo BASE_URL; ?>contact.php">Contact</a></li>
            </ul>
        </div>

        <div class="footer-section">
            <h4>For Shelters</h4>
            <ul class="footer-links">
                <li><a href="<?php echo BASE_URL; ?>auth/register.php">Register Shelter</a></li>
                <li><a href="<?php echo BASE_URL; ?>shelter/addPet.php">Add Pet</a></li>
                <li><a href="<?php echo BASE_URL; ?>shelter/dashboard.php">Shelter Dashboard</a></li>
            </ul>
        </div>

        <div class="footer-section">
            <h4>Contact Info</h4>
            <div class="contact-info">
                <p><i class="fas fa-envelope"></i> info@petadoption.com</p>
                <p><i class="fas fa-phone"></i> +1 (555) 123-4567</p>
                <p><i class="fas fa-map-marker-alt"></i> 123 Pet Street, Animal City, AC 12345</p>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="footer-bottom-content">
            <p>&copy; <?php echo date('Y'); ?> Pet Adoption Care Guide. All rights reserved.</p>
            <div class="footer-bottom-links">
                <a href="#" onclick="showPrivacyPolicy()">Privacy Policy</a>
                <a href="#" onclick="showTerms()">Terms of Service</a>
                <a href="#" onclick="showCookiePolicy()">Cookie Policy</a>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top Button -->
<button id="backToTop" class="back-to-top" title="Go to top">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- Modal for Privacy Policy -->
<div id="privacyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Privacy Policy</h2>
            <span class="close" onclick="closeModal('privacyModal')">&times;</span>
        </div>
        <div class="modal-body">
            <p>This Privacy Policy describes how Pet Adoption Care Guide collects, uses, and protects your personal
                information.</p>
            <h3>Information We Collect</h3>
            <p>We collect information you provide directly to us, such as when you create an account, fill out adoption
                applications, or contact us.</p>
            <h3>How We Use Your Information</h3>
            <p>We use the information we collect to provide, maintain, and improve our services, process adoption
                applications, and communicate with you.</p>
            <h3>Information Sharing</h3>
            <p>We do not sell, trade, or otherwise transfer your personal information to third parties without your
                consent, except as described in this policy.</p>
        </div>
    </div>
</div>

<!-- Modal for Terms of Service -->
<div id="termsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Terms of Service</h2>
            <span class="close" onclick="closeModal('termsModal')">&times;</span>
        </div>
        <div class="modal-body">
            <p>By using Pet Adoption Care Guide, you agree to these terms of service.</p>
            <h3>User Responsibilities</h3>
            <p>Users must provide accurate information and use the platform responsibly for legitimate pet adoption
                purposes.</p>
            <h3>Prohibited Uses</h3>
            <p>Users may not use the platform for illegal activities, spam, or any purpose that violates these terms.
            </p>
            <h3>Limitation of Liability</h3>
            <p>Pet Adoption Care Guide is not liable for disputes between users or issues arising from pet adoptions.
            </p>
        </div>
    </div>
</div>

<style>
/* Footer Styles */
.footer {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: #ecf0f1;
    padding: 40px 0 0;
    margin-top: 50px;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.footer-section h3 {
    color: #3498db;
    margin-bottom: 15px;
    font-size: 24px;
}

.footer-section h4 {
    color: #e74c3c;
    margin-bottom: 15px;
    font-size: 18px;
}

.footer-section p {
    line-height: 1.6;
    margin-bottom: 15px;
}

.footer-links {
    list-style: none;
    padding: 0;
}

.footer-links li {
    margin-bottom: 8px;
}

.footer-links a {
    color: #bdc3c7;
    text-decoration: none;
    transition: color 0.3s ease;
}

.footer-links a:hover {
    color: #3498db;
}

.social-links {
    margin-top: 15px;
}

.social-link {
    display: inline-block;
    width: 40px;
    height: 40px;
    background: #3498db;
    color: white;
    text-align: center;
    line-height: 40px;
    margin-right: 10px;
    border-radius: 50%;
    transition: all 0.3s ease;
    text-decoration: none;
}

.social-link:hover {
    background: #e74c3c;
    transform: translateY(-2px);
}

.contact-info p {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.contact-info i {
    width: 20px;
    margin-right: 10px;
    color: #3498db;
}

.footer-bottom {
    background: #1a252f;
    padding: 20px 0;
    margin-top: 30px;
    border-top: 1px solid #34495e;
}

.footer-bottom-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.footer-bottom-links a {
    color: #bdc3c7;
    text-decoration: none;
    margin-left: 20px;
    cursor: pointer;
    transition: color 0.3s ease;
}

.footer-bottom-links a:hover {
    color: #3498db;
}

/* Back to Top Button */
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: #3498db;
    color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    cursor: pointer;
    display: none;
    justify-content: center;
    align-items: center;
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
}

.back-to-top:hover {
    background: #e74c3c;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
}

.back-to-top.show {
    display: flex;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s ease;
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border: none;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
    animation: slideIn 0.3s ease;
}

.modal-header {
    padding: 20px;
    background: #3498db;
    color: white;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
}

.modal-body {
    padding: 20px;
    line-height: 1.6;
}

.modal-body h3 {
    color: #2c3e50;
    margin-top: 20px;
    margin-bottom: 10px;
}

.close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s ease;
}

.close:hover {
    color: #e74c3c;
}

/* Responsive Design */
@media (max-width: 768px) {
    .footer-content {
        grid-template-columns: 1fr;
        gap: 20px;
        text-align: center;
    }

    .footer-bottom-content {
        flex-direction: column;
        text-align: center;
    }

    .footer-bottom-links {
        margin-top: 10px;
    }

    .footer-bottom-links a {
        margin: 0 10px;
    }

    .back-to-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
    }
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
// Footer JavaScript functionality

// Back to top button functionality
window.onscroll = function() {
    const backToTopButton = document.getElementById('backToTop');
    if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
        backToTopButton.classList.add('show');
    } else {
        backToTopButton.classList.remove('show');
    }
};

document.getElementById('backToTop').addEventListener('click', function() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

// Modal functionality
function showPrivacyPolicy() {
    document.getElementById('privacyModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function showTerms() {
    document.getElementById('termsModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function showCookiePolicy() {
    alert(
        'Cookie Policy: We use cookies to enhance your browsing experience and provide personalized content. By continuing to use our site, you agree to our use of cookies.'
    );
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        if (event.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// Smooth scrolling for footer links
document.querySelectorAll('.footer-links a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});

// Add loading animation to external links
document.querySelectorAll('.footer-links a[href^="http"]').forEach(link => {
    link.addEventListener('click', function(e) {
        const icon = document.createElement('i');
        icon.className = 'fas fa-spinner fa-spin';
        icon.style.marginLeft = '5px';
        this.appendChild(icon);

        setTimeout(() => {
            if (icon.parentNode) {
                icon.parentNode.removeChild(icon);
            }
        }, 2000);
    });
});

// Console log for development
console.log('Pet Adoption Care Guide Footer Loaded Successfully');
</script>

<?php
// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    define('BASE_URL', $protocol . $host . $path . '/');
}
?>

</body>

</html>