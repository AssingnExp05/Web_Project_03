<?php
// common/footer.php
?>

<footer class="site-footer">
    <!-- Newsletter Section -->
    <div class="newsletter-bar">
        <div class="container">
            <div class="newsletter-content">
                <h3><i class="fas fa-envelope"></i> Stay Updated</h3>
                <form class="newsletter-form" onsubmit="handleNewsletter(event)">
                    <input type="email" placeholder="Your email address" required>
                    <button type="submit">Subscribe</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Footer -->
    <div class="footer-main">
        <div class="container">
            <div class="footer-grid">
                <!-- Brand -->
                <div class="footer-brand">
                    <div class="logo">
                        <i class="fas fa-paw"></i>
                        <span>Pet Care</span>
                    </div>
                    <p>Connecting loving hearts with furry souls.</p>
                    <div class="social-links">
                        <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" title="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer-column">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>about.php">About</a></li>
                        <li><a href="<?php echo BASE_URL; ?>adopter/browsePets.php">Find Pets</a></li>
                        <li><a href="<?php echo BASE_URL; ?>contact.php">Contact</a></li>
                    </ul>
                </div>

                <!-- Services -->
                <div class="footer-column">
                    <h4>Services</h4>
                    <ul>
                        <li><a href="#">Dog Adoption</a></li>
                        <li><a href="#">Cat Adoption</a></li>
                        <li><a href="#">Care Guides</a></li>
                        <li><a href="#">Vet Services</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div class="footer-column">
                    <h4>Contact Info</h4>
                    <div class="contact-info">
                        <p><i class="fas fa-phone"></i> +1 (555) 123-4567</p>
                        <p><i class="fas fa-envelope"></i> info@petcare.com</p>
                        <p><i class="fas fa-map-marker-alt"></i> 123 Pet Street, AC 12345</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Pet Care. All rights reserved.</p>
            <div class="footer-links">
                <a href="#" onclick="openModal('privacy')">Privacy</a>
                <a href="#" onclick="openModal('terms')">Terms</a>
                <a href="#" onclick="openModal('faq')">FAQ</a>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top -->
<button id="backToTop" class="back-to-top">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- Modals -->
<div id="modalOverlay" class="modal-overlay">
    <div class="modal">
        <button class="modal-close" onclick="closeModal()">×</button>
        <div class="modal-content"></div>
    </div>
</div>

<style>
/* Variables */
:root {
    --footer-bg: #1a1a2e;
    --footer-secondary: #0f0f1e;
    --primary-color: #667eea;
    --text-light: #a0a0a0;
    --text-white: #ffffff;
}

/* Footer Styles */
.site-footer {
    background: var(--footer-bg);
    color: var(--text-light);
    margin-top: 50px;
}

/* Container */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Newsletter Bar */
.newsletter-bar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 25px 0;
}

.newsletter-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}

.newsletter-content h3 {
    color: white;
    margin: 0;
    font-size: 1.3rem;
}

.newsletter-form {
    display: flex;
    gap: 10px;
    flex: 1;
    max-width: 400px;
}

.newsletter-form input {
    flex: 1;
    padding: 10px 15px;
    border: none;
    border-radius: 25px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    backdrop-filter: blur(10px);
}

.newsletter-form input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.newsletter-form button {
    padding: 10px 25px;
    border: none;
    border-radius: 25px;
    background: white;
    color: var(--primary-color);
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.3s;
}

.newsletter-form button:hover {
    transform: scale(1.05);
}

/* Main Footer */
.footer-main {
    padding: 40px 0;
}

.footer-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr 1fr;
    gap: 30px;
}

/* Brand Section */
.footer-brand .logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.footer-brand p {
    margin-bottom: 20px;
}

.social-links {
    display: flex;
    gap: 10px;
}

.social-links a {
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    color: white;
    transition: all 0.3s;
}

.social-links a:hover {
    background: var(--primary-color);
    transform: translateY(-3px);
}

/* Footer Columns */
.footer-column h4 {
    color: white;
    margin-bottom: 20px;
    font-size: 1.1rem;
}

.footer-column ul {
    list-style: none;
    padding: 0;
}

.footer-column ul li {
    margin-bottom: 10px;
}

.footer-column ul li a {
    color: var(--text-light);
    text-decoration: none;
    transition: color 0.3s;
}

.footer-column ul li a:hover {
    color: var(--primary-color);
}

/* Contact Info */
.contact-info p {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.contact-info i {
    color: var(--primary-color);
    width: 20px;
}

/* Footer Bottom */
.footer-bottom {
    background: var(--footer-secondary);
    padding: 20px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.footer-bottom .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.footer-links {
    display: flex;
    gap: 20px;
}

.footer-links a {
    color: var(--text-light);
    text-decoration: none;
}

.footer-links a:hover {
    color: var(--primary-color);
}

/* Back to Top Button */
.back-to-top {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 45px;
    height: 45px;
    background: var(--primary-color);
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
    z-index: 1000;
}

.back-to-top.show {
    opacity: 1;
    visibility: visible;
}

.back-to-top:hover {
    transform: translateY(-5px);
    background: #764ba2;
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 2000;
}

.modal {
    position: relative;
    background: white;
    max-width: 600px;
    margin: 50px auto;
    border-radius: 10px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    font-size: 30px;
    cursor: pointer;
    color: #666;
}

.modal-content {
    padding: 30px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .footer-grid {
        grid-template-columns: 1fr;
        gap: 25px;
        text-align: center;
    }

    .newsletter-content {
        flex-direction: column;
        text-align: center;
    }

    .newsletter-form {
        width: 100%;
    }

    .footer-bottom .container {
        flex-direction: column;
        text-align: center;
    }

    .social-links {
        justify-content: center;
    }

    .contact-info p {
        justify-content: center;
    }
}
</style>

<script>
// Back to top functionality
window.addEventListener('scroll', function() {
    const backToTop = document.getElementById('backToTop');
    if (window.pageYOffset > 300) {
        backToTop.classList.add('show');
    } else {
        backToTop.classList.remove('show');
    }
});

document.getElementById('backToTop').addEventListener('click', function() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

// Newsletter submission
function handleNewsletter(event) {
    event.preventDefault();
    const form = event.target;
    const button = form.querySelector('button');
    const originalText = button.textContent;

    button.textContent = 'Subscribing...';
    button.disabled = true;

    setTimeout(() => {
        button.textContent = 'Subscribed!';
        setTimeout(() => {
            button.textContent = originalText;
            button.disabled = false;
            form.reset();
        }, 2000);
    }, 1000);
}

// Modal functionality
const modalContent = {
    privacy: {
        title: 'Privacy Policy',
        content: `
            <h3>Privacy Policy</h3>
            <p>We respect your privacy and are committed to protecting your personal information.</p>
            <h4>Information We Collect</h4>
            <p>We collect information you provide when registering, adopting pets, or contacting us.</p>
            <h4>How We Use Information</h4>
            <p>Your information helps us facilitate adoptions and improve our services.</p>
        `
    },
    terms: {
        title: 'Terms of Service',
        content: `
            <h3>Terms of Service</h3>
            <p>By using our platform, you agree to these terms.</p>
            <h4>User Responsibilities</h4>
            <p>Users must provide accurate information and use the platform responsibly.</p>
            <h4>Adoption Process</h4>
            <p>All adoptions are subject to shelter approval.</p>
        `
    },
    faq: {
        title: 'Frequently Asked Questions',
        content: `
            <h3>FAQ</h3>
            <h4>How do I adopt a pet?</h4>
            <p>Browse pets, submit an application, and wait for approval.</p>
            <h4>What are the fees?</h4>
            <p>Fees vary by shelter and cover medical care.</p>
            <h4>Can I return a pet?</h4>
            <p>Contact the shelter to discuss their return policy.</p>
        `
    }
};

function openModal(type) {
    const overlay = document.getElementById('modalOverlay');
    const content = document.querySelector('.modal-content');

    if (modalContent[type]) {
        content.innerHTML = modalContent[type].content;
        overlay.style.display = 'block';
    }
}

function closeModal() {
    document.getElementById('modalOverlay').style.display = 'none';
}

// Close modal on outside click
document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Define BASE_URL if not defined
if (typeof BASE_URL === 'undefined') {
    window.BASE_URL = window.location.origin + '/pet_care/';
}
</script>

<?php
// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/pet_care/');
}
?>

</body>

</html>