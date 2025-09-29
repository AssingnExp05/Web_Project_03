<?php
// common/footer.php
?>

<footer class="enhanced-footer">
    <!-- Newsletter Section -->
    <div class="newsletter-section">
        <div class="newsletter-container">
            <div class="newsletter-content">
                <div class="newsletter-text">
                    <h3><i class="fas fa-envelope-open-text"></i> Stay Connected</h3>
                    <p>Get updates on new pets, adoption events, and care tips</p>
                </div>
                <form class="newsletter-form" onsubmit="handleNewsletterSubmit(event)">
                    <div class="newsletter-input-group">
                        <input type="email" placeholder="Enter your email address" required>
                        <button type="submit">
                            <span>Subscribe</span>
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Footer Content -->
    <div class="footer-main">
        <div class="footer-container">
            <div class="footer-grid">
                <!-- Brand Column -->
                <div class="footer-brand">
                    <div class="brand-logo">
                        <i class="fas fa-paw"></i>
                        <span>Pet Care</span>
                    </div>
                    <p class="brand-description">
                        Connecting loving hearts with furry souls. Your trusted partner in pet adoption.
                    </p>
                    <div class="social-links">
                        <a href="#" class="social-link facebook" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link twitter" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-link instagram" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-link youtube" title="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                    <div class="trust-badges">
                        <div class="trust-badge">
                            <i class="fas fa-shield-alt"></i>
                            <span>Verified Shelters</span>
                        </div>
                        <div class="trust-badge">
                            <i class="fas fa-heart"></i>
                            <span>5000+ Adoptions</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer-column">
                    <h4>Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="<?php echo BASE_URL; ?>"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>about.php"><i class="fas fa-info-circle"></i> About Us</a>
                        </li>
                        <li><a href="<?php echo BASE_URL; ?>adopter/browsePets.php"><i class="fas fa-search"></i> Find
                                Pets</a></li>
                        <li><a href="<?php echo BASE_URL; ?>adopter/careGuides.php"><i class="fas fa-book"></i> Care
                                Guides</a></li>
                        <li><a href="<?php echo BASE_URL; ?>contact.php"><i class="fas fa-envelope"></i> Contact</a>
                        </li>
                    </ul>
                </div>

                <!-- Services -->
                <div class="footer-column">
                    <h4>Our Services</h4>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-dog"></i> Dog Adoption</a></li>
                        <li><a href="#"><i class="fas fa-cat"></i> Cat Adoption</a></li>
                        <li><a href="#"><i class="fas fa-dove"></i> Bird Adoption</a></li>
                        <li><a href="#"><i class="fas fa-clinic-medical"></i> Vet Services</a></li>
                        <li><a href="#"><i class="fas fa-graduation-cap"></i> Training Programs</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div class="footer-column">
                    <h4>Get in Touch</h4>
                    <div class="contact-items">
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <p>123 Pet Street</p>
                                <p>Animal City, AC 12345</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone-alt"></i>
                            <div>
                                <p>+1 (555) 123-4567</p>
                                <p>Mon-Sat 9AM-6PM</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <p>info@petcare.com</p>
                                <p>24/7 Support</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- App Download Section -->
            <div class="app-download">
                <div class="app-content">
                    <h4>Get Our Mobile App</h4>
                    <p>Adopt pets on the go!</p>
                </div>
                <div class="app-buttons">
                    <a href="#" class="app-button">
                        <i class="fab fa-apple"></i>
                        <div>
                            <span>Download on the</span>
                            <strong>App Store</strong>
                        </div>
                    </a>
                    <a href="#" class="app-button">
                        <i class="fab fa-google-play"></i>
                        <div>
                            <span>Get it on</span>
                            <strong>Google Play</strong>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="footer-bottom-container">
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> Pet Adoption Care Guide. Made with <i class="fas fa-heart"></i> for
                    pets and their humans.</p>
            </div>
            <div class="footer-bottom-links">
                <a href="#" onclick="showModal('privacyModal')">Privacy Policy</a>
                <a href="#" onclick="showModal('termsModal')">Terms of Service</a>
                <a href="#" onclick="showModal('cookieModal')">Cookie Policy</a>
                <a href="#" onclick="showModal('faqModal')">FAQ</a>
            </div>
        </div>
    </div>

    <!-- Decorative Elements -->
    <div class="footer-decoration">
        <div class="paw-print paw-1"><i class="fas fa-paw"></i></div>
        <div class="paw-print paw-2"><i class="fas fa-paw"></i></div>
        <div class="paw-print paw-3"><i class="fas fa-paw"></i></div>
        <div class="paw-print paw-4"><i class="fas fa-paw"></i></div>
    </div>
</footer>

<!-- Back to Top Button -->
<button id="backToTop" class="enhanced-back-to-top" title="Back to top">
    <i class="fas fa-chevron-up"></i>
    <span>Top</span>
</button>

<!-- Modals -->
<div id="privacyModal" class="enhanced-modal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2><i class="fas fa-user-shield"></i> Privacy Policy</h2>
            <button class="modal-close" onclick="closeModal('privacyModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="policy-section">
                <h3>1. Information Collection</h3>
                <p>We collect information you provide directly to us when creating an account, adopting a pet, or
                    contacting us.</p>
            </div>
            <div class="policy-section">
                <h3>2. Information Use</h3>
                <p>We use your information to facilitate pet adoptions, improve our services, and communicate with you.
                </p>
            </div>
            <div class="policy-section">
                <h3>3. Information Sharing</h3>
                <p>We do not sell your personal information. We share information only with shelters for adoption
                    purposes.</p>
            </div>
            <div class="policy-section">
                <h3>4. Data Security</h3>
                <p>We implement industry-standard security measures to protect your personal information.</p>
            </div>
        </div>
    </div>
</div>

<div id="termsModal" class="enhanced-modal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2><i class="fas fa-file-contract"></i> Terms of Service</h2>
            <button class="modal-close" onclick="closeModal('termsModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="policy-section">
                <h3>1. Acceptance of Terms</h3>
                <p>By using our platform, you agree to these terms of service.</p>
            </div>
            <div class="policy-section">
                <h3>2. User Responsibilities</h3>
                <p>You must provide accurate information and use the platform responsibly.</p>
            </div>
            <div class="policy-section">
                <h3>3. Adoption Process</h3>
                <p>All adoptions are subject to shelter approval and verification processes.</p>
            </div>
            <div class="policy-section">
                <h3>4. Limitation of Liability</h3>
                <p>We facilitate connections but are not responsible for individual adoption outcomes.</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced Footer Styles */
.enhanced-footer {
    background: #1a1a2e;
    color: #eee;
    position: relative;
    overflow: hidden;
    margin-top: 80px;
}

/* Newsletter Section */
.newsletter-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 50px 0;
    position: relative;
    overflow: hidden;
}

.newsletter-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.newsletter-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    position: relative;
    z-index: 1;
}

.newsletter-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 30px;
}

.newsletter-text h3 {
    font-size: 2rem;
    margin-bottom: 10px;
    color: white;
    display: flex;
    align-items: center;
    gap: 15px;
}

.newsletter-text p {
    font-size: 1.1rem;
    opacity: 0.9;
    color: white;
}

.newsletter-form {
    flex: 1;
    max-width: 500px;
}

.newsletter-input-group {
    display: flex;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50px;
    padding: 5px;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
}

.newsletter-input-group:focus-within {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.4);
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.newsletter-input-group input {
    flex: 1;
    background: transparent;
    border: none;
    padding: 15px 25px;
    color: white;
    font-size: 1rem;
    outline: none;
}

.newsletter-input-group input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.newsletter-input-group button {
    background: white;
    color: #667eea;
    border: none;
    padding: 15px 30px;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.newsletter-input-group button:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(255, 255, 255, 0.3);
}

/* Main Footer */
.footer-main {
    padding: 60px 0 40px;
    background: #1a1a2e;
    position: relative;
}

.footer-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.footer-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
    gap: 40px;
    margin-bottom: 50px;
}

/* Brand Column */
.footer-brand {
    padding-right: 30px;
}

.brand-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.8rem;
    font-weight: 800;
    margin-bottom: 20px;
    color: #667eea;
}

.brand-logo i {
    font-size: 2.5rem;
    animation: pawBounce 2s ease-in-out infinite;
}

@keyframes pawBounce {

    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }

    25% {
        transform: translateY(-5px) rotate(-5deg);
    }

    75% {
        transform: translateY(-5px) rotate(5deg);
    }
}

.brand-description {
    line-height: 1.8;
    margin-bottom: 25px;
    color: #a0a0a0;
}

.social-links {
    display: flex;
    gap: 12px;
    margin-bottom: 25px;
}

.social-link {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    text-decoration: none;
    color: white;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.social-link:hover {
    transform: translateY(-5px) rotate(360deg);
    border-color: currentColor;
}

.social-link.facebook:hover {
    background: #3b5998;
    box-shadow: 0 5px 20px rgba(59, 89, 152, 0.5);
}

.social-link.twitter:hover {
    background: #1da1f2;
    box-shadow: 0 5px 20px rgba(29, 161, 242, 0.5);
}

.social-link.instagram:hover {
    background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
    box-shadow: 0 5px 20px rgba(225, 48, 108, 0.5);
}

.social-link.youtube:hover {
    background: #ff0000;
    box-shadow: 0 5px 20px rgba(255, 0, 0, 0.5);
}

.trust-badges {
    display: flex;
    gap: 15px;
}

.trust-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 25px;
    font-size: 0.9rem;
    color: #667eea;
    border: 1px solid rgba(102, 126, 234, 0.3);
}

/* Footer Columns */
.footer-column h4 {
    color: #667eea;
    margin-bottom: 25px;
    font-size: 1.2rem;
    position: relative;
    padding-bottom: 15px;
}

.footer-column h4::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 40px;
    height: 3px;
    background: linear-gradient(90deg, #667eea, transparent);
}

.footer-links {
    list-style: none;
    padding: 0;
}

.footer-links li {
    margin-bottom: 15px;
}

.footer-links a {
    color: #a0a0a0;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    position: relative;
}

.footer-links a::before {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 20px;
    right: 100%;
    height: 2px;
    background: #667eea;
    transition: right 0.3s ease;
}

.footer-links a:hover {
    color: #667eea;
    transform: translateX(5px);
}

.footer-links a:hover::before {
    right: 0;
}

.footer-links i {
    width: 20px;
    text-align: center;
    opacity: 0.7;
}

/* Contact Items */
.contact-items {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.contact-item {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.contact-item i {
    width: 40px;
    height: 40px;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
    flex-shrink: 0;
    border: 1px solid rgba(102, 126, 234, 0.3);
}

.contact-item p {
    margin: 3px 0;
    color: #a0a0a0;
    line-height: 1.4;
}

.contact-item p:first-child {
    color: #eee;
}

/* App Download Section */
.app-download {
    background: rgba(102, 126, 234, 0.1);
    border-radius: 20px;
    padding: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid rgba(102, 126, 234, 0.2);
}

.app-content h4 {
    color: #667eea;
    margin-bottom: 5px;
}

.app-content p {
    color: #a0a0a0;
}

.app-buttons {
    display: flex;
    gap: 15px;
}

.app-button {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #1a1a2e;
    padding: 12px 24px;
    border-radius: 12px;
    text-decoration: none;
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.app-button:hover {
    background: rgba(102, 126, 234, 0.2);
    border-color: #667eea;
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.app-button i {
    font-size: 2rem;
}

.app-button div {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.app-button span {
    font-size: 0.85rem;
    opacity: 0.8;
}

.app-button strong {
    font-size: 1.1rem;
}

/* Footer Bottom */
.footer-bottom {
    background: #0f0f1e;
    padding: 30px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.footer-bottom-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.copyright {
    color: #a0a0a0;
}

.copyright i {
    color: #e74c3c;
    animation: heartbeat 1.5s ease-in-out infinite;
}

@keyframes heartbeat {

    0%,
    100% {
        transform: scale(1);
    }

    50% {
        transform: scale(1.2);
    }
}

.footer-bottom-links {
    display: flex;
    gap: 25px;
}

.footer-bottom-links a {
    color: #a0a0a0;
    text-decoration: none;
    transition: color 0.3s ease;
    position: relative;
}

.footer-bottom-links a::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    right: 0;
    height: 2px;
    background: #667eea;
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.footer-bottom-links a:hover {
    color: #667eea;
}

.footer-bottom-links a:hover::after {
    transform: scaleX(1);
}

/* Footer Decorations */
.footer-decoration {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    pointer-events: none;
}

.paw-print {
    position: absolute;
    color: rgba(102, 126, 234, 0.05);
    font-size: 3rem;
    animation: float 20s ease-in-out infinite;
}

.paw-1 {
    top: 20%;
    left: 10%;
    animation-delay: 0s;
}

.paw-2 {
    top: 60%;
    left: 80%;
    animation-delay: 5s;
}

.paw-3 {
    top: 80%;
    left: 30%;
    animation-delay: 10s;
}

.paw-4 {
    top: 30%;
    left: 60%;
    animation-delay: 15s;
}

@keyframes float {

    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }

    33% {
        transform: translateY(-20px) rotate(10deg);
    }

    66% {
        transform: translateY(20px) rotate(-10deg);
    }
}

/* Enhanced Back to Top Button */
.enhanced-back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 15px 20px;
    border-radius: 50px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 0 5px 25px rgba(102, 126, 234, 0.4);
}

.enhanced-back-to-top.show {
    opacity: 1;
    visibility: visible;
}

.enhanced-back-to-top:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 35px rgba(102, 126, 234, 0.5);
}

.enhanced-back-to-top i {
    font-size: 1.2rem;
    animation: bounce 2s infinite;
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

/* Enhanced Modals */
.enhanced-modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
    animation: fadeIn 0.3s ease;
}

.modal-dialog {
    background: white;
    margin: 50px auto;
    padding: 0;
    border-radius: 20px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    animation: slideUp 0.3s ease;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    padding: 25px 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.5rem;
}

.modal-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body {
    padding: 30px;
    max-height: calc(90vh - 100px);
    overflow-y: auto;
}

.policy-section {
    margin-bottom: 25px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 15px;
    border-left: 4px solid #667eea;
}

.policy-section h3 {
    color: #1a1a2e;
    margin-bottom: 10px;
}

.policy-section p {
    color: #666;
    line-height: 1.6;
}

/* Responsive Design */
@media (max-width: 992px) {
    .footer-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 30px;
    }

    .footer-brand {
        grid-column: 1 / -1;
        padding-right: 0;
    }

    .newsletter-content {
        flex-direction: column;
        text-align: center;
    }

    .newsletter-form {
        width: 100%;
    }
}

@media (max-width: 576px) {
    .footer-grid {
        grid-template-columns: 1fr;
        gap: 25px;
    }

    .app-download {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }

    .app-buttons {
        flex-direction: column;
        width: 100%;
    }

    .app-button {
        justify-content: center;
    }

    .footer-bottom-container {
        flex-direction: column;
        text-align: center;
    }

    .footer-bottom-links {
        flex-wrap: wrap;
        justify-content: center;
    }

    .enhanced-back-to-top {
        bottom: 20px;
        right: 20px;
        padding: 12px;
        border-radius: 50%;
    }

    .enhanced-back-to-top span {
        display: none;
    }

    .newsletter-input-group {
        flex-direction: column;
        padding: 5px;
    }

    .newsletter-input-group input {
        padding: 15px 20px;
    }

    .newsletter-input-group button {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// Enhanced Footer JavaScript

// Back to top button functionality
let lastScrollTop = 0;
window.addEventListener('scroll', function() {
    const backToTopButton = document.getElementById('backToTop');
    const st = window.pageYOffset || document.documentElement.scrollTop;

    if (st > 300) {
        backToTopButton.classList.add('show');
    } else {
        backToTopButton.classList.remove('show');
    }

    lastScrollTop = st <= 0 ? 0 : st;
});

document.getElementById('backToTop').addEventListener('click', function() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

// Newsletter form submission
function handleNewsletterSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const email = form.querySelector('input[type="email"]').value;
    const button = form.querySelector('button');
    const originalContent = button.innerHTML;

    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';
    button.disabled = true;

    // Simulate API call
    setTimeout(() => {
        // Reset button
        button.innerHTML = '<i class="fas fa-check"></i> Subscribed!';
        button.style.background = '#10b981';

        setTimeout(() => {
            button.innerHTML = originalContent;
            button.disabled = false;
            button.style.background = '';
            form.reset();
        }, 2000);

        // Show success notification
        showNotification('Successfully subscribed to our newsletter!', 'success');
    }, 1500);
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;

    // Add styles
    const style = document.createElement('style');
    style.textContent = `
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 3000;
            animation: slideInRight 0.3s ease;
        }
        
        .notification-success {
            border-left: 5px solid #10b981;
        }
        
        .notification-success i {
            color: #10b981;
            font-size: 1.5rem;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    `;

    document.head.appendChild(style);
    document.body.appendChild(notification);

    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Modal functionality
function showModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.querySelector('.modal-dialog').style.animation = 'slideUp 0.3s ease reverse';

    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }, 300);
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('.enhanced-modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            closeModal(modal.id);
        }
    });
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.enhanced-modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                closeModal(modal.id);
            }
        });
    }
});

// Add loading animation to social links
document.querySelectorAll('.social-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        this.style.animation = 'spin 0.5s ease';

        setTimeout(() => {
            this.style.animation = '';
            // In a real app, this would open the social media page
            showNotification('Social media link clicked!', 'success');
        }, 500);
    });
});

// Add ripple effect to footer links
document.querySelectorAll('.footer-links a').forEach(link => {
    link.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        ripple.className = 'ripple';
        this.appendChild(ripple);

        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;

        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';

        setTimeout(() => ripple.remove(), 600);
    });
});

// Add FAQ modal content
document.addEventListener('DOMContentLoaded', function() {
    // Create FAQ modal
    const faqModal = document.createElement('div');
    faqModal.id = 'faqModal';
    faqModal.className = 'enhanced-modal';
    faqModal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-header">
                <h2><i class="fas fa-question-circle"></i> Frequently Asked Questions</h2>
                <button class="modal-close" onclick="closeModal('faqModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="policy-section">
                    <h3>How do I adopt a pet?</h3>
                    <p>Browse available pets, submit an application, and wait for shelter approval.</p>
                </div>
                <div class="policy-section">
                    <h3>What are the adoption fees?</h3>
                    <p>Fees vary by shelter and typically cover vaccinations and medical care.</p>
                </div>
                                <div class="policy-section">
                    <h3>What are the adoption fees?</h3>
                    <p>Fees vary by shelter and typically cover vaccinations and medical care.</p>
                </div>
                <div class="policy-section">
                    <h3>How long does the adoption process take?</h3>
                    <p>The process usually takes 2-5 business days, depending on the shelter's verification process.</p>
                </div>
                <div class="policy-section">
                    <h3>Can I return a pet if it doesn't work out?</h3>
                    <p>Most shelters have a return policy. Please discuss this with the shelter before adoption.</p>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(faqModal);

    // Create Cookie modal
    const cookieModal = document.createElement('div');
    cookieModal.id = 'cookieModal';
    cookieModal.className = 'enhanced-modal';
    cookieModal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-header">
                <h2><i class="fas fa-cookie-bite"></i> Cookie Policy</h2>
                <button class="modal-close" onclick="closeModal('cookieModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="policy-section">
                    <h3>What are cookies?</h3>
                    <p>Cookies are small text files stored on your device to enhance your browsing experience.</p>
                </div>
                <div class="policy-section">
                    <h3>How we use cookies</h3>
                    <p>We use cookies to remember your preferences, analyze site traffic, and personalize content.</p>
                </div>
                <div class="policy-section">
                    <h3>Types of cookies we use</h3>
                    <p>Essential cookies for site functionality, analytics cookies for usage data, and preference cookies for your settings.</p>
                </div>
                <div class="policy-section">
                    <h3>Managing cookies</h3>
                    <p>You can control cookies through your browser settings. Disabling cookies may affect site functionality.</p>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(cookieModal);
});

// Add spin animation
const spinStyle = document.createElement('style');
spinStyle.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        transform: scale(0);
        animation: rippleEffect 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes rippleEffect {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(spinStyle);

// Initialize on page load
console.log('Enhanced Footer loaded successfully');

// Add intersection observer for footer animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -100px 0px'
};

const footerObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate-in');

            // Animate trust badges
            if (entry.target.classList.contains('trust-badges')) {
                entry.target.querySelectorAll('.trust-badge').forEach((badge, index) => {
                    setTimeout(() => {
                        badge.style.animation = 'fadeInUp 0.6s ease forwards';
                    }, index * 100);
                });
            }

            // Animate social links
            if (entry.target.classList.contains('social-links')) {
                entry.target.querySelectorAll('.social-link').forEach((link, index) => {
                    setTimeout(() => {
                        link.style.animation = 'fadeInUp 0.6s ease forwards';
                    }, index * 100);
                });
            }
        }
    });
}, observerOptions);

// Observe footer elements
document.querySelectorAll('.trust-badges, .social-links, .footer-column').forEach(el => {
    footerObserver.observe(el);
});

// Add fadeInUp animation
const animationStyle = document.createElement('style');
animationStyle.textContent = `
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
    
    .trust-badge, .social-link {
        opacity: 0;
    }
    
    .animate-in {
        animation: fadeInUp 0.8s ease forwards;
    }
`;
document.head.appendChild(animationStyle);

// Mobile menu toggle for footer links (optional)
if (window.innerWidth <= 768) {
    document.querySelectorAll('.footer-column h4').forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            const links = this.nextElementSibling;
            if (links && links.classList.contains('footer-links')) {
                links.style.display = links.style.display === 'none' ? 'block' : 'none';
                this.classList.toggle('active');
            }
        });
    });
}

// Add hover effect to app buttons
document.querySelectorAll('.app-button').forEach(button => {
    button.addEventListener('mouseenter', function() {
        this.querySelector('i').style.animation = 'bounce 0.5s ease';
    });

    button.addEventListener('mouseleave', function() {
        this.querySelector('i').style.animation = '';
    });
});

// Footer paw prints animation enhancement
let mouseX = 0;
let mouseY = 0;

document.addEventListener('mousemove', (e) => {
    mouseX = e.clientX / window.innerWidth;
    mouseY = e.clientY / window.innerHeight;

    document.querySelectorAll('.paw-print').forEach((paw, index) => {
        const speed = (index + 1) * 0.02;
        const x = (mouseX - 0.5) * 20 * speed;
        const y = (mouseY - 0.5) * 20 * speed;

        paw.style.transform = `translate(${x}px, ${y}px)`;
    });
});

// Cookie consent banner (optional)
if (!localStorage.getItem('cookieConsent')) {
    setTimeout(() => {
        const cookieBanner = document.createElement('div');
        cookieBanner.className = 'cookie-consent';
        cookieBanner.innerHTML = `
            <div class="cookie-content">
                <p><i class="fas fa-cookie-bite"></i> We use cookies to enhance your experience. By continuing, you agree to our <a href="#" onclick="showModal('cookieModal'); return false;">Cookie Policy</a>.</p>
                <button onclick="acceptCookies()">Accept</button>
            </div>
        `;

        const cookieStyle = document.createElement('style');
        cookieStyle.textContent = `
            .cookie-consent {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: #1a1a2e;
                color: white;
                padding: 20px;
                box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.2);
                z-index: 2000;
                animation: slideUp 0.5s ease;
            }
            
            .cookie-content {
                max-width: 1200px;
                margin: 0 auto;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 20px;
            }
            
            .cookie-content p {
                margin: 0;
                flex: 1;
            }
            
            .cookie-content a {
                color: #667eea;
                text-decoration: underline;
            }
            
            .cookie-content button {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 10px 30px;
                border-radius: 25px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            
            .cookie-content button:hover {
                transform: scale(1.05);
                box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            }
            
            @keyframes slideUp {
                from { transform: translateY(100%); }
                to { transform: translateY(0); }
            }
        `;

        document.head.appendChild(cookieStyle);
        document.body.appendChild(cookieBanner);
    }, 2000);
}

function acceptCookies() {
    localStorage.setItem('cookieConsent', 'true');
    const banner = document.querySelector('.cookie-consent');
    banner.style.animation = 'slideUp 0.5s ease reverse';
    setTimeout(() => banner.remove(), 500);
}

// Smooth scrolling for internal links
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

// Performance optimization: Lazy load images in footer
const lazyImages = document.querySelectorAll('.footer img[data-src]');
const imageObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.src;
            img.classList.add('loaded');
            imageObserver.unobserve(img);
        }
    });
});

lazyImages.forEach(img => imageObserver.observe(img));

// Define BASE_URL if not already defined
if (typeof BASE_URL === 'undefined') {
    window.BASE_URL = window.location.origin + '/pet_care/';
}
</script>

<?php
// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    define('BASE_URL', $protocol . $host . $path . '/');
}
?>

</body>

</html>