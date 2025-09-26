<?php
// adopter/applyAdoption.php - Pet Adoption Application Page
require_once '../config/db.php';

// Check if user is logged in as adopter
if (!Session::isLoggedIn() || Session::getUserType() !== 'adopter') {
    header('Location: ../auth/login.php');
    exit();
}

$adopter_id = Session::getUserId();
$pet_id = isset($_GET['pet_id']) ? (int)$_GET['pet_id'] : 0;

// Initialize variables
$pet = null;
$shelter = null;
$error_message = null;
$success_message = null;

// Validate pet_id
if ($pet_id <= 0) {
    Session::set('error_message', 'Invalid pet selection.');
    header('Location: browsePets.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $housing_type = Security::sanitize($_POST['housing_type'] ?? '');
    $has_experience = isset($_POST['has_experience']) ? 1 : 0;
    $reason_for_adoption = Security::sanitize($_POST['reason_for_adoption'] ?? '');
    $has_other_pets = isset($_POST['has_other_pets']) ? 1 : 0;
    $other_pets_details = Security::sanitize($_POST['other_pets_details'] ?? '');
    $has_yard = Security::sanitize($_POST['has_yard'] ?? '');
    $employment_status = Security::sanitize($_POST['employment_status'] ?? '');
    $household_members = (int)($_POST['household_members'] ?? 1);
    $veterinarian_info = Security::sanitize($_POST['veterinarian_info'] ?? '');
    $emergency_contact = Security::sanitize($_POST['emergency_contact'] ?? '');
    $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;

    // Validation
    $errors = [];
    
    if (empty($housing_type)) {
        $errors[] = 'Housing type is required.';
    }
    
    if (empty($reason_for_adoption)) {
        $errors[] = 'Reason for adoption is required.';
    } elseif (strlen($reason_for_adoption) < 20) {
        $errors[] = 'Please provide a more detailed reason for adoption (at least 20 characters).';
    }
    
    if (empty($employment_status)) {
        $errors[] = 'Employment status is required.';
    }
    
    if ($household_members < 1 || $household_members > 20) {
        $errors[] = 'Please enter a valid number of household members (1-20).';
    }
    
    if (!$agree_terms) {
        $errors[] = 'You must agree to the adoption terms and conditions.';
    }

    if (empty($errors)) {
        try {
            // Check if user already has a pending application for this pet
            $existing_check = "SELECT application_id FROM adoption_applications 
                             WHERE adopter_id = ? AND pet_id = ? AND application_status = 'pending'";
            $existing = DBHelper::selectOne($existing_check, [$adopter_id, $pet_id]);
            
            if ($existing) {
                $error_message = 'You already have a pending application for this pet.';
            } else {
                // Get shelter_id from pet
                $pet_shelter_query = "SELECT s.shelter_id FROM pets p 
                                    JOIN shelters s ON p.shelter_id = s.shelter_id 
                                    WHERE p.pet_id = ? AND p.status = 'available'";
                $pet_shelter = DBHelper::selectOne($pet_shelter_query, [$pet_id]);
                
                if (!$pet_shelter) {
                    $error_message = 'This pet is no longer available for adoption.';
                } else {
                    // Insert adoption application
                    $insert_query = "
                        INSERT INTO adoption_applications (
                            pet_id, adopter_id, shelter_id, application_status,
                            housing_type, has_experience, reason_for_adoption,
                            application_date
                        ) VALUES (?, ?, ?, 'pending', ?, ?, ?, NOW())
                    ";
                    
                    $application_id = DBHelper::insert($insert_query, [
                        $pet_id,
                        $adopter_id,
                        $pet_shelter['shelter_id'],
                        $housing_type,
                        $has_experience,
                        $reason_for_adoption
                    ]);
                    
                    if ($application_id) {
                        Session::set('success_message', 'Your adoption application has been submitted successfully! The shelter will contact you soon.');
                        header('Location: myAdoptions.php');
                        exit();
                    } else {
                        $error_message = 'Failed to submit your application. Please try again.';
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Adoption application error: " . $e->getMessage());
            $error_message = 'An error occurred while submitting your application. Please try again.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

try {
    // Get pet details with shelter information
    $pet_query = "
        SELECT 
            p.*,
            pc.category_name,
            pb.breed_name,
            s.shelter_name,
            s.shelter_id,
            u.first_name as shelter_contact_first_name,
            u.last_name as shelter_contact_last_name,
            u.phone as shelter_phone,
            u.email as shelter_email
        FROM pets p
        JOIN pet_categories pc ON p.category_id = pc.category_id
        LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
        JOIN shelters s ON p.shelter_id = s.shelter_id
        JOIN users u ON s.user_id = u.user_id
        WHERE p.pet_id = ? AND p.status = 'available'
    ";
    
    $pet = DBHelper::selectOne($pet_query, [$pet_id]);
    
    if (!$pet) {
        Session::set('error_message', 'The selected pet is not available for adoption.');
        header('Location: browsePets.php');
        exit();
    }

} catch (Exception $e) {
    error_log("Pet fetch error: " . $e->getMessage());
    $error_message = "Error loading pet information. Please try again.";
}

// Get user information for pre-filling form
$user_info = [
    'phone' => Session::get('phone', ''),
    'address' => Session::get('address', '')
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adopt <?php echo htmlspecialchars($pet['pet_name'] ?? 'Pet'); ?> - Pet Adoption Care Guide</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        line-height: 1.6;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Page Header */
    .page-header {
        text-align: center;
        margin-bottom: 30px;
        color: #fff;
    }

    .page-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 10px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    .page-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        font-weight: 300;
    }

    /* Content Layout */
    .content-layout {
        display: grid;
        grid-template-columns: 400px 1fr;
        gap: 30px;
        align-items: start;
    }

    /* Pet Info Card */
    .pet-info-card {
        background: #fff;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        position: sticky;
        top: 20px;
    }

    .pet-image {
        width: 100%;
        height: 250px;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }

    .pet-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .pet-image .no-image {
        color: #6c757d;
        font-size: 4rem;
    }

    .pet-details {
        padding: 25px;
    }

    .pet-name {
        font-size: 1.8rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 15px;
    }

    .pet-meta {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 20px;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #666;
        font-size: 0.95rem;
    }

    .meta-item i {
        width: 20px;
        color: #667eea;
        text-align: center;
    }

    .adoption-fee {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        padding: 15px;
        border-radius: 12px;
        text-align: center;
        margin: 20px 0;
    }

    .fee-label {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 5px;
    }

    .fee-amount {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .shelter-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 12px;
        margin-top: 20px;
    }

    .shelter-name {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .shelter-contact {
        font-size: 0.9rem;
        color: #666;
    }

    /* Application Form */
    .application-form {
        background: #fff;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
    }

    .form-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f0f0f0;
    }

    .form-title {
        font-size: 1.8rem;
        color: #2c3e50;
        margin-bottom: 10px;
        font-weight: 700;
    }

    .form-subtitle {
        color: #666;
        font-size: 1rem;
    }

    /* Form Sections */
    .form-section {
        margin-bottom: 30px;
    }

    .section-title {
        font-size: 1.3rem;
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-title i {
        color: #667eea;
    }

    /* Form Elements */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-group.required label::after {
        content: ' *';
        color: #dc3545;
        font-weight: bold;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 12px 15px;
        border: 2px solid #e1e8ed;
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        font-family: inherit;
        background: white;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 120px;
        line-height: 1.6;
    }

    /* Checkbox and Radio Groups */
    .checkbox-group,
    .radio-group {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 5px;
    }

    .checkbox-item,
    .radio-item {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .checkbox-item input,
    .radio-item input {
        width: auto;
        margin: 0;
    }

    /* Character Counter */
    .char-counter {
        font-size: 0.8rem;
        color: #666;
        text-align: right;
        margin-top: 5px;
    }

    .char-counter.warning {
        color: #ffc107;
    }

    .char-counter.error {
        color: #dc3545;
    }

    /* Help Text */
    .help-text {
        background: rgba(102, 126, 234, 0.05);
        border-left: 4px solid #667eea;
        padding: 15px;
        margin-top: 10px;
        border-radius: 0 8px 8px 0;
        font-size: 0.9rem;
        color: #555;
    }

    /* Terms Section */
    .terms-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 12px;
        margin: 20px 0;
    }

    .terms-content {
        max-height: 150px;
        overflow-y: auto;
        padding: 10px;
        background: white;
        border-radius: 8px;
        font-size: 0.9rem;
        line-height: 1.6;
        margin-bottom: 15px;
    }

    .terms-agreement {
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .terms-agreement input[type="checkbox"] {
        margin-top: 3px;
    }

    .terms-agreement label {
        font-size: 0.95rem;
        line-height: 1.5;
        cursor: pointer;
    }

    /* Messages */
    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.1);
        color: #155724;
        border: 1px solid rgba(40, 167, 69, 0.2);
    }

    .alert-error {
        background: rgba(220, 53, 69, 0.1);
        color: #721c24;
        border: 1px solid rgba(220, 53, 69, 0.2);
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding-top: 25px;
        border-top: 2px solid #f0f0f0;
        gap: 15px;
    }

    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        justify-content: center;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 15px 30px;
        font-size: 1.1rem;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #764ba2, #667eea);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background: #5a6268;
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }

    /* Loading State */
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }

    .spinner {
        border: 2px solid #f3f3f3;
        border-top: 2px solid #667eea;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        animation: spin 1s linear infinite;
        display: inline-block;
        margin-left: 8px;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .content-layout {
            grid-template-columns: 1fr;
            gap: 25px;
        }

        .pet-info-card {
            position: relative;
            top: 0;
        }
    }

    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .page-title {
            font-size: 2rem;
        }

        .pet-info-card,
        .application-form {
            padding: 20px;
        }

        .form-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .form-actions {
            flex-direction: column;
            text-align: center;
        }

        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .page-title {
            font-size: 1.5rem;
        }

        .pet-info-card,
        .application-form {
            padding: 15px;
        }

        .pet-name {
            font-size: 1.5rem;
        }

        .form-title {
            font-size: 1.5rem;
        }

        .section-title {
            font-size: 1.2rem;
        }
    }

    /* Progress Indicator */
    .progress-indicator {
        background: rgba(255, 255, 255, 0.1);
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        color: #fff;
        text-align: center;
    }

    .progress-steps {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
    }

    .step {
        padding: 5px 10px;
        border-radius: 15px;
        background: rgba(255, 255, 255, 0.2);
    }

    .step.active {
        background: rgba(255, 255, 255, 0.3);
        font-weight: 600;
    }
    </style>
</head>

<body>
    <!-- Include Adopter Navigation -->
    <?php include '../common/navbar_adopter.php'; ?>

    <div class="container">
        <!-- Progress Indicator -->
        <div class="progress-indicator">
            <div class="progress-steps">
                <span class="step">1. Browse Pets</span>
                <i class="fas fa-arrow-right"></i>
                <span class="step active">2. Apply for Adoption</span>
                <i class="fas fa-arrow-right"></i>
                <span class="step">3. Review & Approval</span>
            </div>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Adoption Application</h1>
            <p class="page-subtitle">Apply to adopt <?php echo htmlspecialchars($pet['pet_name']); ?></p>
        </div>

        <!-- Display Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <div class="content-layout">
            <!-- Pet Information Card -->
            <div class="pet-info-card">
                <div class="pet-image">
                    <?php if (!empty($pet['primary_image']) && file_exists("../uploads/" . $pet['primary_image'])): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                        alt="<?php echo htmlspecialchars($pet['pet_name']); ?>">
                    <?php else: ?>
                    <i class="fas fa-paw no-image"></i>
                    <?php endif; ?>
                </div>

                <div class="pet-details">
                    <h2 class="pet-name"><?php echo htmlspecialchars($pet['pet_name']); ?></h2>

                    <div class="pet-meta">
                        <div class="meta-item">
                            <i class="fas fa-tag"></i>
                            <span><?php echo htmlspecialchars($pet['category_name']); ?></span>
                        </div>
                        <?php if ($pet['breed_name']): ?>
                        <div class="meta-item">
                            <i class="fas fa-dna"></i>
                            <span><?php echo htmlspecialchars($pet['breed_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="meta-item">
                            <i class="fas fa-birthday-cake"></i>
                            <span><?php echo (int)$pet['age']; ?> years old</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-venus-mars"></i>
                            <span><?php echo ucfirst($pet['gender']); ?></span>
                        </div>
                        <?php if ($pet['size']): ?>
                        <div class="meta-item">
                            <i class="fas fa-ruler"></i>
                            <span><?php echo ucfirst($pet['size']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="meta-item">
                            <i class="fas fa-heart-pulse"></i>
                            <span><?php echo htmlspecialchars($pet['health_status'] ?: 'Healthy'); ?></span>
                        </div>
                    </div>

                    <?php if ($pet['adoption_fee'] > 0): ?>
                    <div class="adoption-fee">
                        <div class="fee-label">Adoption Fee</div>
                        <div class="fee-amount">$<?php echo number_format((float)$pet['adoption_fee'], 2); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($pet['description']): ?>
                    <div class="pet-description"
                        style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 0.9rem; line-height: 1.6;">
                        <strong>About <?php echo htmlspecialchars($pet['pet_name']); ?>:</strong><br>
                        <?php echo nl2br(htmlspecialchars($pet['description'])); ?>
                    </div>
                    <?php endif; ?>

                    <div class="shelter-info">
                        <div class="shelter-name">
                            <i class="fas fa-home"></i>
                            <?php echo htmlspecialchars($pet['shelter_name']); ?>
                        </div>
                        <div class="shelter-contact">
                            Contact:
                            <?php echo htmlspecialchars($pet['shelter_contact_first_name'] . ' ' . $pet['shelter_contact_last_name']); ?>
                        </div>
                        <?php if ($pet['shelter_phone']): ?>
                        <div class="shelter-contact">
                            <i class="fas fa-phone"></i>
                            <?php echo htmlspecialchars($pet['shelter_phone']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Application Form -->
            <div class="application-form">
                <div class="form-header">
                    <h2 class="form-title">Adoption Application Form</h2>
                    <p class="form-subtitle">Please provide detailed information to help us find the perfect match</p>
                </div>

                <form id="adoptionForm" method="POST" action="">
                    <input type="hidden" name="pet_id" value="<?php echo (int)$pet_id; ?>">

                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </h3>

                        <div class="form-grid">
                            <div class="form-group required">
                                <label for="housing_type">
                                    <i class="fas fa-home"></i>
                                    Housing Type
                                </label>
                                <select id="housing_type" name="housing_type" required>
                                    <option value="">Select Housing Type</option>
                                    <option value="house"
                                        <?php echo (($_POST['housing_type'] ?? '') === 'house') ? 'selected' : ''; ?>>
                                        House</option>
                                    <option value="apartment"
                                        <?php echo (($_POST['housing_type'] ?? '') === 'apartment') ? 'selected' : ''; ?>>
                                        Apartment</option>
                                    <option value="condo"
                                        <?php echo (($_POST['housing_type'] ?? '') === 'condo') ? 'selected' : ''; ?>>
                                        Condominium</option>
                                    <option value="townhouse"
                                        <?php echo (($_POST['housing_type'] ?? '') === 'townhouse') ? 'selected' : ''; ?>>
                                        Townhouse</option>
                                    <option value="other"
                                        <?php echo (($_POST['housing_type'] ?? '') === 'other') ? 'selected' : ''; ?>>
                                        Other</option>
                                </select>
                            </div>

                            <div class="form-group required">
                                <label for="employment_status">
                                    <i class="fas fa-briefcase"></i>
                                    Employment Status
                                </label>
                                <select id="employment_status" name="employment_status" required>
                                    <option value="">Select Employment Status</option>
                                    <option value="employed"
                                        <?php echo (($_POST['employment_status'] ?? '') === 'employed') ? 'selected' : ''; ?>>
                                        Employed Full-time</option>
                                    <option value="part_time"
                                        <?php echo (($_POST['employment_status'] ?? '') === 'part_time') ? 'selected' : ''; ?>>
                                        Employed Part-time</option>
                                    <option value="self_employed"
                                        <?php echo (($_POST['employment_status'] ?? '') === 'self_employed') ? 'selected' : ''; ?>>
                                        Self-employed</option>
                                    <option value="student"
                                        <?php echo (($_POST['employment_status'] ?? '') === 'student') ? 'selected' : ''; ?>>
                                        Student</option>
                                    <option value="retired"
                                        <?php echo (($_POST['employment_status'] ?? '') === 'retired') ? 'selected' : ''; ?>>
                                        Retired</option>
                                    <option value="unemployed"
                                        <?php echo (($_POST['employment_status'] ?? '') === 'unemployed') ? 'selected' : ''; ?>>
                                        Unemployed</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="household_members">
                                    <i class="fas fa-users"></i>
                                    Number of Household Members
                                </label>
                                <input type="number" id="household_members" name="household_members" min="1" max="20"
                                    value="<?php echo (int)($_POST['household_members'] ?? 1); ?>">
                            </div>

                            <div class="form-group">
                                <label for="has_yard">
                                    <i class="fas fa-tree"></i>
                                    Do you have a yard?
                                </label>
                                <select id="has_yard" name="has_yard">
                                    <option value="">Select Option</option>
                                    <option value="yes_fenced"
                                        <?php echo (($_POST['has_yard'] ?? '') === 'yes_fenced') ? 'selected' : ''; ?>>
                                        Yes, fenced</option>
                                    <option value="yes_unfenced"
                                        <?php echo (($_POST['has_yard'] ?? '') === 'yes_unfenced') ? 'selected' : ''; ?>>
                                        Yes, unfenced</option>
                                    <option value="no"
                                        <?php echo (($_POST['has_yard'] ?? '') === 'no') ? 'selected' : ''; ?>>No
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Pet Experience Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-paw"></i>
                            Pet Experience
                        </h3>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-check-circle"></i>
                                Do you have experience with pets?
                            </label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="has_experience" name="has_experience" value="1"
                                        <?php echo (isset($_POST['has_experience'])) ? 'checked' : ''; ?>>
                                    <label for="has_experience">Yes, I have experience with pets</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-heart"></i>
                                Do you currently have other pets?
                            </label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="has_other_pets" name="has_other_pets" value="1"
                                        <?php echo (isset($_POST['has_other_pets'])) ? 'checked' : ''; ?>>
                                    <label for="has_other_pets">Yes, I have other pets</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" id="other_pets_details_group" style="display: none;">
                            <label for="other_pets_details">
                                <i class="fas fa-info-circle"></i>
                                Please describe your other pets
                            </label>
                            <textarea id="other_pets_details" name="other_pets_details"
                                placeholder="Please describe the type, age, and temperament of your other pets..."><?php echo htmlspecialchars($_POST['other_pets_details'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="veterinarian_info">
                                <i class="fas fa-user-md"></i>
                                Veterinarian Information (if any)
                            </label>
                            <input type="text" id="veterinarian_info" name="veterinarian_info"
                                placeholder="Veterinarian name and clinic (optional)"
                                value="<?php echo htmlspecialchars($_POST['veterinarian_info'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Adoption Details Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-heart"></i>
                            Adoption Details
                        </h3>

                        <div class="form-group required full-width">
                            <label for="reason_for_adoption">
                                <i class="fas fa-comment"></i>
                                Why do you want to adopt <?php echo htmlspecialchars($pet['pet_name']); ?>?
                            </label>
                            <textarea id="reason_for_adoption" name="reason_for_adoption" required minlength="20"
                                placeholder="Please explain your reasons for wanting to adopt this pet, your expectations, and how you plan to care for them..."><?php echo htmlspecialchars($_POST['reason_for_adoption'] ?? ''); ?></textarea>
                            <div class="char-counter" id="reasonCounter">0 characters (minimum 20)</div>
                            <div class="help-text">
                                <i class="fas fa-lightbulb"></i>
                                <strong>Tip:</strong> Be specific about why this particular pet caught your attention
                                and how you plan to provide for their needs, including exercise, training, medical care,
                                and companionship.
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="emergency_contact">
                                <i class="fas fa-phone"></i>
                                Emergency Contact Information
                            </label>
                            <input type="text" id="emergency_contact" name="emergency_contact"
                                placeholder="Emergency contact name and phone number (optional)"
                                value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="form-section">
                        <div class="terms-section">
                            <h3 class="section-title">
                                <i class="fas fa-file-contract"></i>
                                Terms and Conditions
                            </h3>

                            <div class="terms-content">
                                <h4>Adoption Agreement</h4>
                                <p>By submitting this application, I agree to the following terms:</p>
                                <ul>
                                    <li>I understand that this is an application to adopt and does not guarantee
                                        adoption.</li>
                                    <li>I agree to provide proper veterinary care, including vaccinations and
                                        spaying/neutering if not already done.</li>
                                    <li>I will provide a safe, loving, and permanent home for this pet.</li>
                                    <li>I understand that if I can no longer care for this pet, I must return it to the
                                        shelter.</li>
                                    <li>I agree to allow a home visit or phone interview as part of the adoption
                                        process.</li>
                                    <li>I understand that providing false information may result in denial of adoption
                                        or removal of the pet.</li>
                                    <li>I agree to pay the adoption fee and any applicable taxes.</li>
                                    <li>I understand that adopted pets must be kept as indoor pets or in a securely
                                        fenced area.</li>
                                </ul>
                            </div>

                            <div class="terms-agreement">
                                <input type="checkbox" id="agree_terms" name="agree_terms" value="1" required
                                    <?php echo (isset($_POST['agree_terms'])) ? 'checked' : ''; ?>>
                                <label for="agree_terms">
                                    I have read and agree to all the terms and conditions listed above. I understand
                                    that providing false information may result in denial of adoption.
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="petDetails.php?id=<?php echo (int)$pet_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Pet Details
                        </a>

                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane"></i>
                            Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Character counter for reason for adoption
    function updateCharCounter() {
        const textarea = document.getElementById('reason_for_adoption');
        const counter = document.getElementById('reasonCounter');
        const length = textarea.value.length;

        counter.textContent = `${length} characters${length < 20 ? ' (minimum 20)' : ''}`;

        if (length < 20) {
            counter.className = 'char-counter warning';
        } else {
            counter.className = 'char-counter';
        }
    }

    // Initialize character counter
    document.getElementById('reason_for_adoption').addEventListener('input', updateCharCounter);
    updateCharCounter(); // Initial count

    // Show/hide other pets details
    function toggleOtherPetsDetails() {
        const checkbox = document.getElementById('has_other_pets');
        const detailsGroup = document.getElementById('other_pets_details_group');
        const textarea = document.getElementById('other_pets_details');

        if (checkbox.checked) {
            detailsGroup.style.display = 'block';
            textarea.setAttribute('required', 'required');
        } else {
            detailsGroup.style.display = 'none';
            textarea.removeAttribute('required');
            textarea.value = '';
        }
    }

    // Initialize other pets details visibility
    document.getElementById('has_other_pets').addEventListener('change', toggleOtherPetsDetails);
    toggleOtherPetsDetails(); // Initial state

    // Form validation
    function validateForm() {
        const form = document.getElementById('adoptionForm');
        const formData = new FormData(form);
        let isValid = true;
        let errors = [];

        // Required field validation
        const requiredFields = {
            'housing_type': 'Housing type',
            'employment_status': 'Employment status',
            'reason_for_adoption': 'Reason for adoption'
        };

        for (const [field, label] of Object.entries(requiredFields)) {
            const value = formData.get(field);
            if (!value || value.trim() === '') {
                errors.push(`${label} is required.`);
                isValid = false;
            }
        }

        // Reason for adoption length validation
        const reason = formData.get('reason_for_adoption');
        if (reason && reason.trim().length < 20) {
            errors.push('Please provide a more detailed reason for adoption (at least 20 characters).');
            isValid = false;
        }

        // Household members validation
        const householdMembers = parseInt(formData.get('household_members'));
        if (isNaN(householdMembers) || householdMembers < 1 || householdMembers > 20) {
            errors.push('Please enter a valid number of household members (1-20).');
            isValid = false;
        }

        // Terms agreement validation
        if (!formData.get('agree_terms')) {
            errors.push('You must agree to the terms and conditions to proceed.');
            isValid = false;
        }

        // Other pets details validation
        const hasOtherPets = formData.get('has_other_pets');
        const otherPetsDetails = formData.get('other_pets_details');
        if (hasOtherPets && (!otherPetsDetails || otherPetsDetails.trim().length < 10)) {
            errors.push('Please provide details about your other pets (at least 10 characters).');
            isValid = false;
        }

        if (!isValid) {
            alert('Please fix the following errors:\n\n• ' + errors.join('\n• '));
        }

        return isValid;
    }

    // Form submission
    document.getElementById('adoptionForm').addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            return;
        }

        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting Application...';
        submitBtn.disabled = true;

        // Add loading class to form
        this.classList.add('loading');

        // Re-enable button after timeout (in case of server errors)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            this.classList.remove('loading');
        }, 10000);
    });

    // Auto-resize textareas
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.max(textarea.scrollHeight, 120) + 'px';
    }

    // Initialize auto-resize for textareas
    document.querySelectorAll('textarea').forEach(textarea => {
        textarea.addEventListener('input', function() {
            autoResize(this);
        });
        // Initial resize
        autoResize(textarea);
    });

    // Smooth scrolling to form sections
    function scrollToFirstError() {
        const firstInvalidField = document.querySelector('input:invalid, select:invalid, textarea:invalid');
        if (firstInvalidField) {
            firstInvalidField.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            firstInvalidField.focus();
        }
    }

    // Enhanced form validation with visual feedback
    function addFieldValidation() {
        const fields = document.querySelectorAll('input[required], select[required], textarea[required]');

        fields.forEach(field => {
            field.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.style.borderColor = '#dc3545';
                    this.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
                } else {
                    this.style.borderColor = '#28a745';
                    this.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.1)';
                }
            });

            field.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.style.borderColor = '#e1e8ed';
                    this.style.boxShadow = 'none';
                }
            });
        });
    }

    // Initialize field validation
    addFieldValidation();

    // Save form data to localStorage (auto-save)
    function saveFormData() {
        const form = document.getElementById('adoptionForm');
        const formData = new FormData(form);
        const data = {};

        for (let [key, value] of formData.entries()) {
            if (key !== 'pet_id') { // Don't save pet_id
                data[key] = value;
            }
        }

        localStorage.setItem('adoptionForm_<?php echo (int)$pet_id; ?>', JSON.stringify(data));
    }

    // Load form data from localStorage
    function loadFormData() {
        const savedData = localStorage.getItem('adoptionForm_<?php echo (int)$pet_id; ?>');

        if (savedData) {
            try {
                const data = JSON.parse(savedData);

                for (const [key, value] of Object.entries(data)) {
                    const field = document.querySelector(`[name="${key}"]`);

                    if (field) {
                        if (field.type === 'checkbox') {
                            field.checked = value === '1';
                        } else {
                            field.value = value;
                        }

                        // Trigger change event for dependent fields
                        field.dispatchEvent(new Event('change'));
                    }
                }

                console.log('Form data restored from auto-save');
            } catch (e) {
                console.error('Error loading saved form data:', e);
            }
        }
    }

    // Auto-save form data every 30 seconds
    setInterval(saveFormData, 30000);

    // Save form data on input changes
    document.getElementById('adoptionForm').addEventListener('input', function() {
        clearTimeout(this.autoSaveTimeout);
        this.autoSaveTimeout = setTimeout(saveFormData, 2000);
    });

    // Clear saved data on successful submission
    window.addEventListener('beforeunload', function() {
        if (document.getElementById('adoptionForm').classList.contains('loading')) {
            localStorage.removeItem('adoptionForm_<?php echo (int)$pet_id; ?>');
        }
    });

    // Load saved data when page loads (only if form is empty)
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('adoptionForm');
        const isEmpty = Array.from(form.elements).every(element => {
            if (element.type === 'checkbox') {
                return !element.checked;
            } else {
                return !element.value || element.value.trim() === '';
            }
        });

        if (isEmpty) {
            loadFormData();
        }

        // Initialize character counters and visibility
        updateCharCounter();
        toggleOtherPetsDetails();

        // Focus on first field
        document.getElementById('housing_type').focus();
    });

    // Confirmation before leaving page with unsaved changes
    let formChanged = false;

    document.getElementById('adoptionForm').addEventListener('input', function() {
        formChanged = true;
    });

    window.addEventListener('beforeunload', function(e) {
        if (formChanged && !document.getElementById('adoptionForm').classList.contains('loading')) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });

    // Clear form changed flag on successful submission
    document.getElementById('adoptionForm').addEventListener('submit', function() {
        formChanged = false;
    });

    // Add ripple effect to buttons
    function createRipple(event) {
        const button = event.currentTarget;
        const circle = document.createElement('span');
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;

        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
        circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
        circle.classList.add('ripple');

        const ripple = button.getElementsByClassName('ripple')[0];
        if (ripple) {
            ripple.remove();
        }

        button.appendChild(circle);
    }

    // Apply ripple effect to buttons
    document.querySelectorAll('.btn').forEach(btn => {
        btn.style.position = 'relative';
        btn.style.overflow = 'hidden';
        btn.addEventListener('click', createRipple);
    });

    // Add CSS for ripple effect
    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = `
            .ripple {
                position: absolute;
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 600ms linear;
                background-color: rgba(255, 255, 255, 0.6);
            }
            
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
    document.head.appendChild(rippleStyle);

    // Pet availability check (optional enhancement)
    function checkPetAvailability() {
        fetch(`../api/check_pet_availability.php?pet_id=<?php echo (int)$pet_id; ?>`)
            .then(response => response.json())
            .then(data => {
                if (!data.available) {
                    alert(
                        'Sorry, this pet is no longer available for adoption. You will be redirected to browse other pets.'
                    );
                    window.location.href = 'browsePets.php';
                }
            })
            .catch(error => {
                console.error('Error checking pet availability:', error);
            });
    }

    // Check pet availability when page loads
    // checkPetAvailability(); // Uncomment if you implement the API endpoint

    // Smooth scroll to top on form submission
    document.getElementById('adoptionForm').addEventListener('submit', function() {
        setTimeout(() => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }, 100);
    });

    console.log('Adoption application form loaded successfully');
    console.log('Pet ID:', <?php echo (int)$pet_id; ?>);
    console.log('Pet Name:', '<?php echo htmlspecialchars($pet['pet_name']); ?>');
    </script>
</body>

</html>