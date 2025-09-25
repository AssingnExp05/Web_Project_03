<?php
require_once 'config/db.php';
$page_title = 'Contact Us';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $subject = sanitize_input($_POST['subject']);
    $message = sanitize_input($_POST['message']);
    
    if (!empty($name) && !empty($email) && !empty($subject) && !empty($message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $subject, $message]);
            $success_message = "Thank you for your message! We'll get back to you soon.";
        } catch(PDOException $e) {
            $error_message = "Sorry, there was an error sending your message. Please try again.";
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

include 'common/header.php';
?>

<main class="main-content">
    <div class="container">
        <div class="page-title text-center">Contact Us</div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-2" style="gap: 3rem;">
            <!-- Contact Form -->
            <div class="card">
                <div class="card-header">
                    <h2 style="font-size: 1.5rem; color: #1f2937;">Send us a Message</h2>
                </div>
                <div class="card-body">
                    <form method="POST" id="contactForm">
                        <div class="form-group">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" id="name" name="name" class="form-input" required 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-input" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="subject" class="form-label">Subject *</label>
                            <select id="subject" name="subject" class="form-input form-select" required>
                                <option value="">Select a subject</option>
                                <option value="General Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="Adoption Support" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Adoption Support') ? 'selected' : ''; ?>>Adoption Support</option>
                                <option value="Shelter Registration" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Shelter Registration') ? 'selected' : ''; ?>>Shelter Registration</option>
                                <option value="Technical Support" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Technical Support') ? 'selected' : ''; ?>>Technical Support</option>
                                <option value="Partnership" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Partnership') ? 'selected' : ''; ?>>Partnership Opportunity</option>
                                <option value="Other" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="message" class="form-label">Message *</label>
                            <textarea id="message" name="message" class="form-input form-textarea" required 
                                      placeholder="Please describe your inquiry in detail..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>

                        <button type="submit" class="btn" style="width: 100%;" onclick="handleSubmit(this)">
                            Send Message
                        </button>
                    </form>
                </div>
            </div>

            <!-- Contact Information -->
            <div>
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 style="font-size: 1.5rem; color: #1f2937;">Get in Touch</h2>
                    </div>
                    <div class="card-body">
                        <div style="margin-bottom: 1.5rem;">
                            <h3 style="color: #3b82f6; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                📧 Email Support
                            </h3>
                            <p style="color: #6b7280; margin-bottom: 0.25rem;">General Inquiries:</p>
                            <a href="mailto:info@petadoption.com" style="color: #059669; text-decoration: none;">info@petadoption.com</a>
                            <p style="color: #6b7280; margin-bottom: 0.25rem; margin-top: 0.5rem;">Adoption Support:</p>
                            <a href="mailto:adoptions@petadoption.com" style="color: #059669; text-decoration: none;">adoptions@petadoption.com</a>
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <h3 style="color: #3b82f6; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                📞 Phone Support
                            </h3>
                            <p style="color: #6b7280;">
                                <strong>(555) 123-PETS</strong><br>
                                Monday - Friday: 9:00 AM - 6:00 PM<br>
                                Saturday: 10:00 AM - 4:00 PM<br>
                                Sunday: Closed
                            </p>
                        </div>

                        <div>
                            <h3 style="color: #3b82f6; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                📍 Office Location
                            </h3>
                            <p style="color: #6b7280;">
                                Pet Adoption Care Guide<br>
                                123 Animal Welfare Street<br>
                                Pet City, PC 12345<br>
                                United States
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 style="font-size: 1.5rem; color: #1f2937;">Frequently Asked Questions</h2>
                    </div>
                    <div class="card-body">
                        <div style="margin-bottom: 1rem;">
                            <h4 style="color: #059669; margin-bottom: 0.5rem;">How do I register my shelter?</h4>
                            <p style="color: #6b7280; font-size: 0.9rem;">
                                Visit our registration page and select "Shelter" as your account type. We'll verify your credentials before activation.
                            </p>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <h4 style="color: #059669; margin-bottom: 0.5rem;">Is there a fee to adopt pets?</h4>
                            <p style="color: #6b7280; font-size: 0.9rem;">
                                Adoption fees vary by shelter and help cover medical care, vaccinations, and shelter operations.
                            </p>
                        </div>

                        <div>
                            <h4 style="color: #059669; margin-bottom: 0.5rem;">How long does the adoption process take?</h4>
                            <p style="color: #6b7280; font-size: 0.9rem;">
                                Most applications are reviewed within 24-48 hours. Some shelters may require meet-and-greets before final approval.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function handleSubmit(button) {
    if (validateForm('contactForm')) {
        showLoading(button);
    }
}
</script>

<?php include 'common/footer.php'; ?>