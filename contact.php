<?php
// contact.php - Enhanced Contact Us Page
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Base URL
$BASE_URL = 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Page variables
$page_title = "Contact Us - Pet Adoption Care Guide";
$page_description = "Get in touch with Pet Adoption Care Guide. We're here to help with adoptions, shelter partnerships, and any questions about our platform.";

// Initialize variables
$success_message = '';
$error_message = '';
$form_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Store form data for repopulation on error
    $form_data = [
        'name' => $name,
        'email' => $email,
        'subject' => $subject,
        'message' => $message
    ];
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    } elseif (strlen($name) < 2) {
        $errors[] = "Name must be at least 2 characters long";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required";
    } elseif (strlen($subject) < 5) {
        $errors[] = "Subject must be at least 5 characters long";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    } elseif (strlen($message) < 10) {
        $errors[] = "Message must be at least 10 characters long";
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        try {
            require_once __DIR__ . '/config/db.php';
            $db = getDB();
            
            if ($db) {
                $stmt = $db->prepare("INSERT INTO contact_messages (name, email, subject, message, status, created_at) VALUES (?, ?, ?, ?, 'new', NOW())");
                $result = $stmt->execute([$name, $email, $subject, $message]);
                
                if ($result) {
                    $_SESSION['success_message'] = 'Thank you for contacting us! We\'ll get back to you within 24 hours.';
                    header('Location: ' . $BASE_URL . 'contact.php');
                    exit();
                } else {
                    $error_message = "Sorry, there was an error sending your message. Please try again.";
                }
            } else {
                $error_message = "Database connection failed. Please try again later.";
            }
        } catch (Exception $e) {
            error_log("Contact form error: " . $e->getMessage());
            $error_message = "An error occurred while sending your message. Please try again.";
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Get contact statistics
$total_messages = 0;
$response_time = '< 24 hours';
$satisfaction_rate = '98%';

try {
    require_once __DIR__ . '/config/db.php';
    $db = getDB();
    
    if ($db) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM contact_messages");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_messages = $result ? (int)$result['count'] : 0;
    }
} catch (Exception $e) {
    error_log("Contact stats error: " . $e->getMessage());
}

// Get success message from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        color: #333;
        background: #f8f9fa;
    }

    /* Navigation */
    .navbar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 1rem 0;
        box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .nav-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .logo {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .nav-links {
        display: flex;
        gap: 30px;
        align-items: center;
    }

    .nav-link {
        color: white;
        text-decoration: none;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 20px;
        transition: all 0.3s ease;
    }

    .nav-link:hover,
    .nav-link.active {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }

    /* Hero Section - Enhanced */
    .hero-contact {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.95), rgba(118, 75, 162, 0.95)),
            linear-gradient(45deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        padding: 120px 0;
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .hero-contact::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><defs><pattern id="grid" patternUnits="userSpaceOnUse" width="100" height="100"><circle cx="20" cy="20" r="2" fill="white" opacity="0.1"/><circle cx="80" cy="80" r="1.5" fill="white" opacity="0.08"/><path d="M50 20 Q60 10 70 20 Q60 30 50 20" fill="white" opacity="0.05"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
        animation: float 20s ease-in-out infinite;
    }

    @keyframes float {

        0%,
        100% {
            transform: translateX(0) translateY(0);
        }

        33% {
            transform: translateX(30px) translateY(-30px);
        }

        66% {
            transform: translateX(-20px) translateY(20px);
        }
    }

    .hero-content {
        max-width: 900px;
        margin: 0 auto;
        padding: 0 20px;
        position: relative;
        z-index: 2;
    }

    .hero-title {
        font-size: 4rem;
        font-weight: 800;
        margin-bottom: 25px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        background: linear-gradient(45deg, #fff, #f0f8ff);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        animation: slideInUp 1s ease-out;
    }

    .hero-subtitle {
        font-size: 1.4rem;
        opacity: 0.95;
        line-height: 1.8;
        margin-bottom: 40px;
        animation: slideInUp 1s ease-out 0.2s both;
    }

    .hero-stats {
        display: flex;
        justify-content: center;
        gap: 50px;
        margin-top: 50px;
        animation: slideInUp 1s ease-out 0.4s both;
    }

    .hero-stat {
        text-align: center;
    }

    .hero-stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        display: block;
        margin-bottom: 5px;
    }

    .hero-stat-label {
        font-size: 1rem;
        opacity: 0.8;
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(50px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Contact Section - Enhanced */
    .contact-section {
        padding: 100px 0;
        background: white;
        position: relative;
    }

    .contact-container {
        max-width: 1300px;
        margin: 0 auto;
        padding: 0 20px;
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 80px;
        align-items: start;
    }

    .contact-form-container {
        background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
        border-radius: 25px;
        padding: 50px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(102, 126, 234, 0.1);
        position: relative;
        overflow: hidden;
    }

    .contact-form-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .form-title {
        font-size: 2.5rem;
        color: #2c3e50;
        margin-bottom: 15px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .form-subtitle {
        color: #6c757d;
        margin-bottom: 40px;
        line-height: 1.7;
        font-size: 1.1rem;
    }

    .contact-form {
        display: flex;
        flex-direction: column;
        gap: 25px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .form-label {
        color: #2c3e50;
        font-weight: 600;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1rem;
    }

    .form-label .required {
        color: #e74c3c;
        font-size: 1.2rem;
    }

    .form-input,
    .form-select,
    .form-textarea {
        padding: 18px 20px;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        font-size: 1.05rem;
        transition: all 0.3s ease;
        background: white;
        font-family: inherit;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        transform: translateY(-2px);
    }

    .form-textarea {
        resize: vertical;
        min-height: 140px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }

    .submit-btn {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 20px 40px;
        border: none;
        border-radius: 12px;
        font-size: 1.2rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        margin-top: 20px;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }

    .submit-btn:hover {
        background: linear-gradient(135deg, #5a67d8, #6b46c1);
        transform: translateY(-3px);
        box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
    }

    .submit-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    /* Contact Information - Enhanced */
    .contact-info {
        padding: 30px 0;
    }

    .info-title {
        font-size: 2.2rem;
        color: #2c3e50;
        margin-bottom: 40px;
        font-weight: 700;
    }

    .info-cards {
        display: flex;
        flex-direction: column;
        gap: 30px;
        margin-bottom: 50px;
    }

    .info-card {
        display: flex;
        align-items: flex-start;
        gap: 25px;
        padding: 30px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .info-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 5px;
        height: 100%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        transform: scaleY(0);
        transition: transform 0.3s ease;
    }

    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        border-color: rgba(102, 126, 234, 0.2);
    }

    .info-card:hover::before {
        transform: scaleY(1);
    }

    .info-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        flex-shrink: 0;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }

    .info-content h3 {
        color: #2c3e50;
        margin-bottom: 12px;
        font-weight: 600;
        font-size: 1.3rem;
    }

    .info-content p {
        color: #6c757d;
        line-height: 1.7;
        margin-bottom: 0;
        font-size: 1.05rem;
    }

    .info-content a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .info-content a:hover {
        color: #5a67d8;
        text-decoration: underline;
    }

    /* Quick Contact Buttons */
    .quick-contact {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }

    .quick-btn {
        flex: 1;
        padding: 15px 20px;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
        font-size: 1rem;
    }

    .quick-btn.call {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
    }

    .quick-btn.email {
        background: linear-gradient(135deg, #17a2b8, #138496);
        color: white;
        box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
    }

    .quick-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    /* Statistics Section - Enhanced */
    .stats-section {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 80px 0;
        position: relative;
    }

    .stats-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 0 20px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 40px;
    }

    .stat-card {
        text-align: center;
        background: white;
        padding: 40px 30px;
        border-radius: 20px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
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
        background: linear-gradient(135deg, #667eea, #764ba2);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
    }

    .stat-card:hover::before {
        transform: scaleX(1);
    }

    .stat-icon {
        font-size: 3rem;
        color: #667eea;
        margin-bottom: 20px;
        display: block;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 10px;
        display: block;
    }

    .stat-label {
        color: #6c757d;
        font-weight: 500;
        font-size: 1.1rem;
    }

    /* FAQ Section - Enhanced */
    .faq-section {
        padding: 100px 0;
        background: white;
    }

    .section-title {
        text-align: center;
        font-size: 3rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 20px;
    }

    .section-subtitle {
        text-align: center;
        font-size: 1.2rem;
        color: #6c757d;
        margin-bottom: 60px;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
        line-height: 1.6;
    }

    .faq-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .faq-item {
        margin-bottom: 25px;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    .faq-item:hover {
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    .faq-question {
        background: #f8f9fa;
        padding: 25px 30px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
        border: none;
        width: 100%;
        text-align: left;
    }

    .faq-question:hover {
        background: #e9ecef;
    }

    .faq-question.active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }

    .faq-question h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
    }

    .faq-icon {
        font-size: 1.3rem;
        transition: transform 0.3s ease;
        color: inherit;
    }

    .faq-question.active .faq-icon {
        transform: rotate(45deg);
    }

    .faq-answer {
        background: white;
        padding: 0 30px;
        max-height: 0;
        overflow: hidden;
        transition: all 0.4s ease;
    }

    .faq-answer.active {
        padding: 30px;
        max-height: 300px;
    }

    .faq-answer p {
        color: #6c757d;
        line-height: 1.7;
        margin: 0;
        font-size: 1.05rem;
    }

    /* Alert Messages - Enhanced */
    .alert {
        padding: 20px 25px;
        border-radius: 12px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        font-size: 1.05rem;
        animation: slideInDown 0.5s ease-out;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
        border: 2px solid #b8dacd;
        box-shadow: 0 5px 15px rgba(21, 87, 36, 0.1);
    }

    .alert-error {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
        border: 2px solid #f1b0b7;
        box-shadow: 0 5px 15px rgba(114, 28, 36, 0.1);
    }

    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Form validation styles */
    .form-input.error,
    .form-select.error,
    .form-textarea.error {
        border-color: #e74c3c;
        box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
    }

    .error-text {
        color: #e74c3c;
        font-size: 0.9rem;
        margin-top: 8px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Loading state */
    .loading {
        position: relative;
        pointer-events: none;
    }

    .loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: inherit;
    }

    /* Footer */
    .footer {
        background: #2c3e50;
        color: white;
        padding: 40px 0;
        text-align: center;
    }

    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    /* Mobile Responsive */
    @media (max-width: 1024px) {
        .contact-container {
            grid-template-columns: 1fr;
            gap: 60px;
        }
    }

    @media (max-width: 768px) {
        .hero-title {
            font-size: 2.8rem;
        }

        .hero-stats {
            flex-direction: column;
            gap: 30px;
        }

        .contact-form-container {
            padding: 30px 25px;
        }

        .form-row {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .stats-container {
            grid-template-columns: 1fr;
            gap: 30px;
        }

        .info-card {
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }

        .section-title {
            font-size: 2.2rem;
        }

        .quick-contact {
            flex-direction: column;
        }

        .nav-links {
            display: none;
        }
    }

    @media (max-width: 480px) {
        .hero-title {
            font-size: 2.2rem;
        }

        .contact-form-container {
            padding: 25px 20px;
        }

        .faq-question {
            padding: 20px;
        }

        .faq-answer.active {
            padding: 20px;
        }
    }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="<?php echo $BASE_URL; ?>index.php" class="logo">
                <i class="fas fa-heart"></i>
                Pet Adoption Care Guide
            </a>
            <div class="nav-links">
                <a href="<?php echo $BASE_URL; ?>index.php" class="nav-link">Home</a>
                <a href="<?php echo $BASE_URL; ?>about.php" class="nav-link">About</a>
                <a href="<?php echo $BASE_URL; ?>contact.php" class="nav-link active">Contact</a>
                <a href="<?php echo $BASE_URL; ?>auth/login.php" class="nav-link">Login</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-contact">
        <div class="hero-content">
            <h1 class="hero-title">Get in Touch</h1>
            <p class="hero-subtitle">
                We're here to help! Whether you have questions about adoption, need support
                with our platform, or want to partner with us, we'd love to hear from you.
                Our dedicated team is committed to making pet adoption a wonderful experience for everyone.
            </p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-number"><?php echo number_format($total_messages); ?>+</span>
                    <span class="hero-stat-label">Messages Handled</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-number"><?php echo $response_time; ?></span>
                    <span class="hero-stat-label">Response Time</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-number"><?php echo $satisfaction_rate; ?></span>
                    <span class="hero-stat-label">Satisfaction Rate</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="contact-container">
            <!-- Contact Form -->
            <div class="contact-form-container">
                <h2 class="form-title">
                    <i class="fas fa-envelope"></i>
                    Send us a Message
                </h2>
                <p class="form-subtitle">
                    Fill out the form below and we'll get back to you as soon as possible.
                    All fields marked with <span style="color: #e74c3c;">*</span> are required.
                </p>

                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
                <?php endif; ?>

                <form class="contact-form" method="POST" action="<?php echo $BASE_URL; ?>contact.php" id="contactForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="name">
                                <i class="fas fa-user"></i>
                                Full Name <span class="required">*</span>
                            </label>
                            <input type="text" id="name" name="name" class="form-input"
                                value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>"
                                placeholder="Enter your full name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="email">
                                <i class="fas fa-envelope"></i>
                                Email Address <span class="required">*</span>
                            </label>
                            <input type="email" id="email" name="email" class="form-input"
                                value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                                placeholder="Enter your email address" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="subject">
                            <i class="fas fa-tag"></i>
                            Subject <span class="required">*</span>
                        </label>
                        <input type="text" id="subject" name="subject" class="form-input"
                            value="<?php echo htmlspecialchars($form_data['subject'] ?? ''); ?>"
                            placeholder="Brief description of your inquiry" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="message">
                            <i class="fas fa-comment-alt"></i>
                            Message <span class="required">*</span>
                        </label>
                        <textarea id="message" name="message" class="form-textarea"
                            placeholder="Please provide details about your inquiry, question, or how we can help you..."
                            required><?php echo htmlspecialchars($form_data['message'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        Send Message
                    </button>
                </form>
            </div>

            <!-- Contact Information -->
            <div class="contact-info">
                <h2 class="info-title">Contact Information</h2>

                <div class="info-cards">
                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <h3>Email Us</h3>
                            <p>
                                General inquiries: <a href="mailto:info@petadoption.com">info@petadoption.com</a><br>
                                Adoption support: <a href="mailto:adopt@petadoption.com">adopt@petadoption.com</a><br>
                                Shelter partnerships: <a
                                    href="mailto:shelters@petadoption.com">shelters@petadoption.com</a>
                            </p>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="info-content">
                            <h3>Call Us</h3>
                            <p>
                                Main line: <a href="tel:+15551234567">+1 (555) 123-4567</a><br>
                                Adoption hotline: <a href="tel:+15551234568">+1 (555) 123-4568</a><br>
                                Emergency: <a href="tel:+15551234569">+1 (555) 123-4569</a>
                            </p>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-content">
                            <h3>Visit Us</h3>
                            <p>
                                123 Pet Adoption Street<br>
                                Animal Care District<br>
                                Pet City, PC 12345<br>
                                <strong>Hours:</strong> Mon-Fri 9AM-6PM, Sat 10AM-4PM
                            </p>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="info-content">
                            <h3>Response Time</h3>
                            <p>
                                We typically respond to all inquiries within 24 hours during business days.
                                For urgent adoption matters, we aim to respond within 4 hours.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="quick-contact">
                    <a href="tel:+15551234567" class="quick-btn call">
                        <i class="fas fa-phone"></i>
                        Call Now
                    </a>
                    <a href="mailto:info@petadoption.com" class="quick-btn email">
                        <i class="fas fa-envelope"></i>
                        Email Us
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="stats-container">
            <div class="stat-card">
                <i class="fas fa-envelope-open stat-icon"></i>
                <span class="stat-number"><?php echo number_format($total_messages); ?>+</span>
                <span class="stat-label">Messages Received</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock stat-icon"></i>
                <span class="stat-number"><?php echo $response_time; ?></span>
                <span class="stat-label">Average Response Time</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-thumbs-up stat-icon"></i>
                <span class="stat-number"><?php echo $satisfaction_rate; ?></span>
                <span class="stat-label">Satisfaction Rate</span>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="faq-container">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">
                Find answers to common questions about our pet adoption platform and services.
            </p>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(this)">
                    <h3>How do I start the adoption process?</h3>
                    <i class="fas fa-plus faq-icon"></i>
                </button>
                <div class="faq-answer">
                    <p>To start adopting, create an account on our platform, browse available pets, and submit an
                        adoption
                        application for the pet you're interested in. Our team will review your application and contact
                        you
                        within 24-48 hours to discuss next steps.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(this)">
                    <h3>What are the adoption fees?</h3>
                    <i class="fas fa-plus faq-icon"></i>
                </button>
                <div class="faq-answer">
                    <p>Adoption fees vary by pet and shelter, typically ranging from $50-$300. These fees help cover
                        vaccinations, spaying/neutering, microchipping, and other medical care the pet has received.
                        The exact fee is displayed on each pet's profile.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(this)">
                    <h3>How can shelters join your platform?</h3>
                    <i class="fas fa-plus faq-icon"></i>
                </button>
                <div class="faq-answer">
                    <p>Shelters can register on our platform by creating a shelter account. We'll verify your shelter's
                        credentials and provide training on using our platform to manage pet listings and adoption
                        applications. Contact us at shelters@petadoption.com for more information.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(this)">
                    <h3>Do you provide post-adoption support?</h3>
                    <i class="fas fa-plus faq-icon"></i>
                </button>
                <div class="faq-answer">
                    <p>Yes! We provide ongoing support through our care guides, 24/7 helpline, and connection to local
                        veterinarians and pet training resources. We're here to help ensure successful, long-term
                        adoptions
                        for both pets and families.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(this)">
                    <h3>What if I have an emergency with my adopted pet?</h3>
                    <i class="fas fa-plus faq-icon"></i>
                </button>
                <div class="faq-answer">
                    <p>For medical emergencies, contact your local veterinarian immediately. For adoption-related
                        emergencies or urgent questions, call our emergency hotline at (555) 123-4569, available 24/7.
                        We also have a network of emergency veterinary clinics we can recommend.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFAQ(this)">
                    <h3>Can I return a pet if it doesn't work out?</h3>
                    <i class="fas fa-plus faq-icon"></i>
                </button>
                <div class="faq-answer">
                    <p>We understand that sometimes adoptions don't work out despite best intentions. Most shelters have
                        return policies, and we encourage you to contact the original shelter first. We also provide
                        behavioral support to help resolve common issues before considering return.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> Pet Adoption Care Guide. All rights reserved.</p>
            <p>Connecting pets with loving families since 2020</p>
        </div>
    </footer>

    <script>
    // Contact page JavaScript functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation and submission
        setupFormValidation();

        // Auto-resize textarea
        setupTextareaResize();

        // Initialize animations
        initializeAnimations();

        // Auto-hide success messages
        autoHideMessages();
    });

    function setupFormValidation() {
        const form = document.getElementById('contactForm');
        const submitBtn = document.getElementById('submitBtn');

        form.addEventListener('submit', function(e) {
            // Clear previous errors
            clearFormErrors();

            // Basic client-side validation
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const subject = document.getElementById('subject').value.trim();
            const message = document.getElementById('message').value.trim();

            let isValid = true;
            let errors = [];

            if (name.length < 2) {
                showFieldError('name', 'Name must be at least 2 characters');
                errors.push('name');
                isValid = false;
            }

            if (!isValidEmail(email)) {
                showFieldError('email', 'Please enter a valid email address');
                errors.push('email');
                isValid = false;
            }

            if (subject.length < 5) {
                showFieldError('subject', 'Subject must be at least 5 characters');
                errors.push('subject');
                isValid = false;
            }

            if (message.length < 10) {
                showFieldError('message', 'Message must be at least 10 characters');
                errors.push('message');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();

                // Focus on first error field
                if (errors.length > 0) {
                    document.getElementById(errors[0]).focus();
                }
            } else {
                // Add loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                form.classList.add('loading');
            }
        });

        // Real-time validation
        ['name', 'email', 'subject', 'message'].forEach(fieldId => {
            const field = document.getElementById(fieldId);
            field.addEventListener('blur', function() {
                validateField(fieldId);
            });

            field.addEventListener('input', function() {
                if (field.classList.contains('error')) {
                    validateField(fieldId);
                }
            });
        });
    }

    function validateField(fieldId) {
        const field = document.getElementById(fieldId);
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        switch (fieldId) {
            case 'name':
                if (value.length < 2) {
                    isValid = false;
                    errorMessage = 'Name must be at least 2 characters';
                }
                break;
            case 'email':
                if (!isValidEmail(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid email address';
                }
                break;
            case 'subject':
                if (value.length < 5) {
                    isValid = false;
                    errorMessage = 'Subject must be at least 5 characters';
                }
                break;
            case 'message':
                if (value.length < 10) {
                    isValid = false;
                    errorMessage = 'Message must be at least 10 characters';
                }
                break;
        }

        if (isValid) {
            clearFieldError(fieldId);
        } else {
            showFieldError(fieldId, errorMessage);
        }

        return isValid;
    }

    function showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const formGroup = field.parentNode;

        field.classList.add('error');

        // Remove existing error message
        const existingError = formGroup.querySelector('.error-text');
        if (existingError) {
            existingError.remove();
        }

        // Add new error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-text';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        formGroup.appendChild(errorDiv);
    }

    function clearFieldError(fieldId) {
        const field = document.getElementById(fieldId);
        const formGroup = field.parentNode;

        field.classList.remove('error');

        const errorText = formGroup.querySelector('.error-text');
        if (errorText) {
            errorText.remove();
        }
    }

    function clearFormErrors() {
        document.querySelectorAll('.form-input, .form-textarea').forEach(el => {
            el.classList.remove('error');
        });

        document.querySelectorAll('.error-text').forEach(el => {
            el.remove();
        });
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function setupTextareaResize() {
        const textarea = document.getElementById('message');

        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 300) + 'px';
        });
    }

    function toggleFAQ(button) {
        const faqItem = button.parentNode;
        const answer = faqItem.querySelector('.faq-answer');
        const icon = button.querySelector('.faq-icon');

        // Close all other FAQ items
        document.querySelectorAll('.faq-question').forEach(q => {
            if (q !== button) {
                q.classList.remove('active');
                q.parentNode.querySelector('.faq-answer').classList.remove('active');
            }
        });

        // Toggle current FAQ item
        button.classList.toggle('active');
        answer.classList.toggle('active');
    }

    function initializeAnimations() {
        // Intersection Observer for scroll animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        // Observe elements for animation
        document.querySelectorAll('.stat-card, .info-card, .faq-item').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Counter animation for stats
        animateCounters();
    }

    function animateCounters() {
        const counters = document.querySelectorAll('.stat-number, .hero-stat-number');

        counters.forEach(counter => {
            const target = counter.textContent;
            const number = parseInt(target.replace(/[^\d]/g, ''));

            if (number && number > 0 && number < 10000) {
                let current = 0;
                const increment = number / 100;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= number) {
                        current = number;
                        clearInterval(timer);
                    }

                    if (target.includes('+')) {
                        counter.textContent = Math.floor(current).toLocaleString() + '+';
                    } else if (target.includes('%')) {
                        counter.textContent = Math.floor(current) + '%';
                    } else if (target.includes('<')) {
                        counter.textContent = target; // Keep original for "< 24 hours"
                    } else {
                        counter.textContent = Math.floor(current).toLocaleString();
                    }
                }, 20);
            }
        });
    }

    function autoHideMessages() {
        setTimeout(function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.opacity = '0';
                successAlert.style.transform = 'translateY(-20px)';
                setTimeout(function() {
                    successAlert.style.display = 'none';
                }, 300);
            }
        }, 5000);
    }

    // Add click-to-call and email tracking
    document.querySelectorAll('a[href^="tel:"]').forEach(link => {
        link.addEventListener('click', function() {
            console.log('Phone call initiated:', this.getAttribute('href'));
            // You can add analytics tracking here
        });
    });

    document.querySelectorAll('a[href^="mailto:"]').forEach(link => {
        link.addEventListener('click', function() {
            console.log('Email initiated:', this.getAttribute('href'));
            // You can add analytics tracking here
        });
    });

    // Smooth scrolling for anchor links
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

    // Form character counter for message
    const messageField = document.getElementById('message');
    const maxLength = 1000;

    function updateCharCounter() {
        const current = messageField.value.length;
        const remaining = maxLength - current;

        let counter = messageField.parentNode.querySelector('.char-counter');
        if (!counter) {
            counter = document.createElement('div');
            counter.className = 'char-counter';
            counter.style.cssText = 'font-size: 0.8rem; color: #6c757d; margin-top: 5px; text-align: right;';
            messageField.parentNode.appendChild(counter);
        }

        counter.textContent = `${current}/${maxLength} characters`;

        if (remaining < 50) {
            counter.style.color = '#e74c3c';
        } else if (remaining < 100) {
            counter.style.color = '#ffc107';
        } else {
            counter.style.color = '#6c757d';
        }
    }

    messageField.addEventListener('input', updateCharCounter);
    messageField.setAttribute('maxlength', maxLength);

    // Initialize page
    console.log('Enhanced Contact page loaded successfully');

    // Add some visual feedback for form interactions
    document.querySelectorAll('.form-input, .form-textarea, .form-select').forEach(field => {
        field.addEventListener('focus', function() {
            this.parentNode.style.transform = 'scale(1.02)';
        });

        field.addEventListener('blur', function() {
            this.parentNode.style.transform = 'scale(1)';
        });
    });
    </script>
</body>

</html>