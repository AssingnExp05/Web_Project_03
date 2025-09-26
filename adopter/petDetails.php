<?php
// adopter/petDetails.php - Pet Details Page for Adopters
require_once '../config/db.php';

// Check if user is logged in as adopter
if (!Session::isLoggedIn() || Session::getUserType() !== 'adopter') {
    header('Location: ../auth/login.php');
    exit();
}

$adopter_id = Session::getUserId();
$pet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Initialize variables
$pet = null;
$pet_images = [];
$vaccinations = [];
$medical_records = [];
$has_pending_application = false;
$error_message = null;

// Validate pet_id
if ($pet_id <= 0) {
    Session::set('error_message', 'Invalid pet selection.');
    header('Location: browsePets.php');
    exit();
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
        Session::set('error_message', 'Pet not found or no longer available.');
        header('Location: browsePets.php');
        exit();
    }

    // Get pet images
    $images_query = "
        SELECT image_path, is_primary 
        FROM pet_images 
        WHERE pet_id = ? 
        ORDER BY is_primary DESC, image_id ASC
    ";
    $pet_images = DBHelper::select($images_query, [$pet_id]) ?: [];

    // Get vaccination records
    $vaccinations_query = "
        SELECT 
            vaccine_name,
            vaccination_date,
            next_due_date,
            veterinarian_name,
            notes
        FROM vaccinations 
        WHERE pet_id = ? 
        ORDER BY vaccination_date DESC
    ";
    $vaccinations = DBHelper::select($vaccinations_query, [$pet_id]) ?: [];

    // Get medical records
    $medical_query = "
        SELECT 
            record_type,
            record_date,
            veterinarian_name,
            diagnosis,
            treatment,
            cost
        FROM medical_records 
        WHERE pet_id = ? 
        ORDER BY record_date DESC
        LIMIT 5
    ";
    $medical_records = DBHelper::select($medical_query, [$pet_id]) ?: [];

    // Check if user has pending application for this pet
    $application_check = "
        SELECT application_id 
        FROM adoption_applications 
        WHERE adopter_id = ? AND pet_id = ? AND application_status = 'pending'
    ";
    $pending_app = DBHelper::selectOne($application_check, [$adopter_id, $pet_id]);
    $has_pending_application = (bool)$pending_app;

} catch (Exception $e) {
    error_log("Pet details error: " . $e->getMessage());
    $error_message = "Error loading pet information. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pet['pet_name'] ?? 'Pet Details'); ?> - Pet Adoption Care Guide</title>
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
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Header Section */
    .pet-header {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 30px;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .header-info h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 5px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    .header-info p {
        font-size: 1.1rem;
        opacity: 0.9;
    }

    .header-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 12px 20px;
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
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .btn-primary {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #20c997, #17a2b8);
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .btn-warning {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: white;
    }

    .btn-disabled {
        background: #6c757d;
        color: white;
        opacity: 0.7;
        cursor: not-allowed;
    }

    .btn-disabled:hover {
        transform: none;
        box-shadow: none;
    }

    /* Main Content Layout */
    .content-layout {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 30px;
        align-items: start;
    }

    /* Pet Gallery */
    .pet-gallery {
        background: #fff;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        margin-bottom: 30px;
    }

    .main-image-container {
        position: relative;
        width: 100%;
        height: 500px;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .main-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .main-image:hover {
        transform: scale(1.05);
    }

    .no-image-main {
        color: #6c757d;
        font-size: 5rem;
    }

    .image-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.5);
        color: white;
        border: none;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
        transition: all 0.3s ease;
    }

    .image-nav:hover {
        background: rgba(0, 0, 0, 0.7);
        transform: translateY(-50%) scale(1.1);
    }

    .image-nav.prev {
        left: 15px;
    }

    .image-nav.next {
        right: 15px;
    }

    .thumbnail-strip {
        display: flex;
        gap: 10px;
        padding: 20px;
        background: #f8f9fa;
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .thumbnail-strip::-webkit-scrollbar {
        height: 6px;
    }

    .thumbnail-strip::-webkit-scrollbar-track {
        background: #e9ecef;
        border-radius: 3px;
    }

    .thumbnail-strip::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 3px;
    }

    .thumbnail {
        width: 80px;
        height: 80px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        object-fit: cover;
        flex-shrink: 0;
        border: 3px solid transparent;
    }

    .thumbnail:hover,
    .thumbnail.active {
        border-color: #667eea;
        transform: scale(1.05);
    }

    /* Pet Information Cards */
    .info-card {
        background: #fff;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .card-header h3 {
        font-size: 1.4rem;
        color: #2c3e50;
        font-weight: 700;
    }

    .card-header i {
        color: #667eea;
        font-size: 1.3rem;
    }

    /* Pet Basic Info */
    .pet-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 10px;
        font-size: 0.95rem;
    }

    .info-item i {
        color: #667eea;
        width: 20px;
        text-align: center;
    }

    .info-item strong {
        color: #2c3e50;
        min-width: 80px;
    }

    .pet-description {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 12px;
        border-left: 4px solid #667eea;
        line-height: 1.7;
        font-size: 1rem;
        color: #555;
    }

    /* Adoption Fee */
    .adoption-fee {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
        padding: 20px;
        border-radius: 15px;
        text-align: center;
        margin: 20px 0;
    }

    .fee-label {
        font-size: 1rem;
        opacity: 0.9;
        margin-bottom: 8px;
    }

    .fee-amount {
        font-size: 2rem;
        font-weight: 700;
    }

    /* Shelter Information */
    .shelter-card {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-radius: 15px;
        padding: 20px;
    }

    .shelter-name {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .shelter-contact {
        font-size: 0.95rem;
        margin-bottom: 8px;
        opacity: 0.9;
    }

    .contact-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }

    .contact-btn {
        padding: 8px 15px;
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s ease;
    }

    .contact-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }

    /* Health Records */
    .health-records {
        display: grid;
        gap: 20px;
    }

    .record-item {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        border-left: 4px solid #28a745;
    }

    .record-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 8px;
    }

    .record-title {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.95rem;
    }

    .record-date {
        font-size: 0.85rem;
        color: #666;
    }

    .record-details {
        font-size: 0.9rem;
        color: #555;
        line-height: 1.5;
    }

    .vaccination-item {
        border-left-color: #007bff;
    }

    .medical-item {
        border-left-color: #dc3545;
    }

    /* Status Badges */
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-available {
        background: #d4edda;
        color: #155724;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    /* Empty States */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #666;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    .empty-state h4 {
        margin-bottom: 10px;
        color: #2c3e50;
    }

    /* Error Message */
    .error-message {
        background: rgba(220, 53, 69, 0.1);
        color: #721c24;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        border: 1px solid rgba(220, 53, 69, 0.2);
        display: flex;
        align-items: center;
        gap: 10px;
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
        background-color: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
    }

    .modal.show {
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

    .modal-image {
        max-width: 90%;
        max-height: 90%;
        border-radius: 15px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            transform: scale(0.8);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .modal-close {
        position: absolute;
        top: 20px;
        right: 30px;
        color: white;
        font-size: 2rem;
        cursor: pointer;
        background: rgba(0, 0, 0, 0.5);
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .modal-close:hover {
        background: rgba(0, 0, 0, 0.7);
        transform: scale(1.1);
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .content-layout {
            grid-template-columns: 1fr 350px;
            gap: 25px;
        }
    }

    @media (max-width: 1024px) {
        .content-layout {
            grid-template-columns: 1fr;
            gap: 25px;
        }

        .main-image-container {
            height: 400px;
        }
    }

    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .pet-header {
            flex-direction: column;
            text-align: center;
        }

        .header-info h1 {
            font-size: 2rem;
        }

        .header-actions {
            justify-content: center;
            width: 100%;
        }

        .info-card {
            padding: 20px;
        }

        .pet-info-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .main-image-container {
            height: 300px;
        }

        .thumbnail {
            width: 60px;
            height: 60px;
        }
    }

    @media (max-width: 480px) {
        .header-info h1 {
            font-size: 1.5rem;
        }

        .info-card {
            padding: 15px;
        }

        .btn {
            padding: 10px 15px;
            font-size: 0.9rem;
        }

        .header-actions {
            flex-direction: column;
            gap: 10px;
        }

        .header-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }

    /* Animation for cards */
    .info-card {
        opacity: 0;
        transform: translateY(20px);
        animation: cardSlideIn 0.5s ease forwards;
    }

    .info-card:nth-child(1) {
        animation-delay: 0.1s;
    }

    .info-card:nth-child(2) {
        animation-delay: 0.2s;
    }

    .info-card:nth-child(3) {
        animation-delay: 0.3s;
    }

    .info-card:nth-child(4) {
        animation-delay: 0.4s;
    }

    @keyframes cardSlideIn {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
</head>

<body>
    <!-- Include Adopter Navigation -->
    <?php include '../common/navbar_adopter.php'; ?>

    <div class="container">
        <!-- Error Message -->
        <?php if ($error_message): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Pet Header -->
        <div class="pet-header">
            <div class="header-info">
                <h1><?php echo htmlspecialchars($pet['pet_name']); ?></h1>
                <p>
                    <?php echo htmlspecialchars($pet['category_name']); ?>
                    <?php if ($pet['breed_name']): ?>
                    • <?php echo htmlspecialchars($pet['breed_name']); ?>
                    <?php endif; ?>
                    • <?php echo (int)$pet['age']; ?> years old
                </p>
            </div>
            <div class="header-actions">
                <a href="browsePets.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Browse
                </a>
                <?php if ($has_pending_application): ?>
                <span class="btn btn-warning">
                    <i class="fas fa-clock"></i>
                    Application Pending
                </span>
                <a href="myAdoptions.php" class="btn btn-primary">
                    <i class="fas fa-eye"></i>
                    View Application
                </a>
                <?php else: ?>
                <a href="applyAdoption.php?pet_id=<?php echo (int)$pet['pet_id']; ?>" class="btn btn-primary">
                    <i class="fas fa-heart"></i>
                    Apply for Adoption
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-layout">
            <!-- Main Content (Left Column) -->
            <div class="main-content">
                <!-- Pet Gallery -->
                <div class="pet-gallery">
                    <div class="main-image-container">
                        <?php 
                        $display_images = [];
                        
                        // Add primary image if exists
                        if (!empty($pet['primary_image']) && file_exists("../uploads/" . $pet['primary_image'])) {
                            $display_images[] = $pet['primary_image'];
                        }
                        
                        // Add other images
                        foreach ($pet_images as $img) {
                            if ($img['image_path'] !== $pet['primary_image'] && file_exists("../uploads/" . $img['image_path'])) {
                                $display_images[] = $img['image_path'];
                            }
                        }
                        ?>

                        <?php if (!empty($display_images)): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($display_images[0]); ?>"
                            alt="<?php echo htmlspecialchars($pet['pet_name']); ?>" class="main-image" id="mainImage"
                            onclick="openImageModal(this.src)">

                        <?php if (count($display_images) > 1): ?>
                        <button class="image-nav prev" onclick="previousImage()">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="image-nav next" onclick="nextImage()">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <?php endif; ?>
                        <?php else: ?>
                        <i class="fas fa-paw no-image-main"></i>
                        <?php endif; ?>
                    </div>

                    <?php if (count($display_images) > 1): ?>
                    <div class="thumbnail-strip">
                        <?php foreach ($display_images as $index => $image): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($image); ?>"
                            alt="<?php echo htmlspecialchars($pet['pet_name']); ?>"
                            class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                            onclick="changeMainImage('<?php echo htmlspecialchars($image); ?>', this)">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pet Basic Information -->
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i>
                        <h3>Basic Information</h3>
                    </div>

                    <div class="pet-info-grid">
                        <div class="info-item">
                            <i class="fas fa-tag"></i>
                            <strong>Category:</strong>
                            <span><?php echo htmlspecialchars($pet['category_name']); ?></span>
                        </div>

                        <?php if ($pet['breed_name']): ?>
                        <div class="info-item">
                            <i class="fas fa-dna"></i>
                            <strong>Breed:</strong>
                            <span><?php echo htmlspecialchars($pet['breed_name']); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="info-item">
                            <i class="fas fa-birthday-cake"></i>
                            <strong>Age:</strong>
                            <span><?php echo (int)$pet['age']; ?> years old</span>
                        </div>

                        <div class="info-item">
                            <i class="fas fa-venus-mars"></i>
                            <strong>Gender:</strong>
                            <span><?php echo ucfirst($pet['gender']); ?></span>
                        </div>

                        <?php if ($pet['size']): ?>
                        <div class="info-item">
                            <i class="fas fa-ruler"></i>
                            <strong>Size:</strong>
                            <span><?php echo ucfirst($pet['size']); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="info-item">
                            <i class="fas fa-heart-pulse"></i>
                            <strong>Health:</strong>
                            <span><?php echo htmlspecialchars($pet['health_status'] ?: 'Healthy'); ?></span>
                        </div>
                    </div>

                    <?php if ($pet['description']): ?>
                    <div class="pet-description">
                        <strong>About <?php echo htmlspecialchars($pet['pet_name']); ?>:</strong><br><br>
                        <?php echo nl2br(htmlspecialchars($pet['description'])); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Health Records -->
                <?php if (!empty($vaccinations) || !empty($medical_records)): ?>
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-notes-medical"></i>
                        <h3>Health Records</h3>
                    </div>

                    <div class="health-records">
                        <!-- Vaccinations -->
                        <?php if (!empty($vaccinations)): ?>
                        <h4 style="color: #2c3e50; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-syringe" style="color: #007bff;"></i>
                            Recent Vaccinations
                        </h4>
                        <?php foreach (array_slice($vaccinations, 0, 3) as $vaccination): ?>
                        <div class="record-item vaccination-item">
                            <div class="record-header">
                                <div class="record-title"><?php echo htmlspecialchars($vaccination['vaccine_name']); ?>
                                </div>
                                <div class="record-date">
                                    <?php echo date('M j, Y', strtotime($vaccination['vaccination_date'])); ?></div>
                            </div>
                            <div class="record-details">
                                <?php if ($vaccination['veterinarian_name']): ?>
                                <strong>Veterinarian:</strong>
                                <?php echo htmlspecialchars($vaccination['veterinarian_name']); ?><br>
                                <?php endif; ?>
                                <?php if ($vaccination['next_due_date']): ?>
                                <strong>Next Due:</strong>
                                <?php echo date('M j, Y', strtotime($vaccination['next_due_date'])); ?><br>
                                <?php endif; ?>
                                <?php if ($vaccination['notes']): ?>
                                <strong>Notes:</strong> <?php echo htmlspecialchars($vaccination['notes']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Medical Records -->
                        <?php if (!empty($medical_records)): ?>
                        <h4
                            style="color: #2c3e50; margin: 20px 0 15px 0; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-file-medical" style="color: #dc3545;"></i>
                            Recent Medical Records
                        </h4>
                        <?php foreach (array_slice($medical_records, 0, 3) as $record): ?>
                        <div class="record-item medical-item">
                            <div class="record-header">
                                <div class="record-title">
                                    <?php echo ucfirst(htmlspecialchars($record['record_type'])); ?></div>
                                <div class="record-date">
                                    <?php echo date('M j, Y', strtotime($record['record_date'])); ?></div>
                            </div>
                            <div class="record-details">
                                <?php if ($record['veterinarian_name']): ?>
                                <strong>Veterinarian:</strong>
                                <?php echo htmlspecialchars($record['veterinarian_name']); ?><br>
                                <?php endif; ?>
                                <?php if ($record['diagnosis']): ?>
                                <strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis']); ?><br>
                                <?php endif; ?>
                                <?php if ($record['treatment']): ?>
                                <strong>Treatment:</strong> <?php echo htmlspecialchars($record['treatment']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Empty Health Records -->
                <?php if (empty($vaccinations) && empty($medical_records)): ?>
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-notes-medical"></i>
                        <h3>Health Records</h3>
                    </div>
                    <div class="empty-state">
                        <i class="fas fa-file-medical"></i>
                        <h4>No Health Records Available</h4>
                        <p>Health information will be updated by the shelter staff.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar (Right Column) -->
            <div class="sidebar">
                <!-- Adoption Fee -->
                <?php if ($pet['adoption_fee'] > 0): ?>
                <div class="adoption-fee">
                    <div class="fee-label">Adoption Fee</div>
                    <div class="fee-amount">$<?php echo number_format((float)$pet['adoption_fee'], 2); ?></div>
                </div>
                <?php endif; ?>

                <!-- Adoption Action -->
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-heart"></i>
                        <h3>Adoption Status</h3>
                    </div>

                    <div style="text-align: center;">
                        <div class="status-badge status-available" style="margin-bottom: 15px;">
                            Available for Adoption
                        </div>

                        <?php if ($has_pending_application): ?>
                        <p style="margin-bottom: 20px; color: #666; font-size: 0.95rem;">
                            You have a pending application for this pet. Please wait for the shelter's response.
                        </p>
                        <a href="myAdoptions.php" class="btn btn-warning" style="width: 100%;">
                            <i class="fas fa-eye"></i>
                            View My Application
                        </a>
                        <?php else: ?>
                        <p style="margin-bottom: 20px; color: #666; font-size: 0.95rem;">
                            Ready to give <?php echo htmlspecialchars($pet['pet_name']); ?> a loving home?
                        </p>
                        <a href="applyAdoption.php?pet_id=<?php echo (int)$pet['pet_id']; ?>" class="btn btn-primary"
                            style="width: 100%;">
                            <i class="fas fa-heart"></i>
                            Apply for Adoption
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Shelter Information -->
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-home"></i>
                        <h3>Shelter Information</h3>
                    </div>

                    <div class="shelter-card">
                        <div class="shelter-name">
                            <i class="fas fa-building"></i>
                            <?php echo htmlspecialchars($pet['shelter_name']); ?>
                        </div>

                        <div class="shelter-contact">
                            <i class="fas fa-user"></i>
                            Contact:
                            <?php echo htmlspecialchars($pet['shelter_contact_first_name'] . ' ' . $pet['shelter_contact_last_name']); ?>
                        </div>

                        <?php if ($pet['shelter_phone']): ?>
                        <div class="shelter-contact">
                            <i class="fas fa-phone"></i>
                            <?php echo htmlspecialchars($pet['shelter_phone']); ?>
                        </div>
                        <?php endif; ?>

                        <div class="shelter-contact">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($pet['shelter_email']); ?>
                        </div>

                        <div class="contact-actions">
                            <?php if ($pet['shelter_phone']): ?>
                            <a href="tel:<?php echo htmlspecialchars($pet['shelter_phone']); ?>" class="contact-btn">
                                <i class="fas fa-phone"></i>
                                Call
                            </a>
                            <?php endif; ?>
                            <a href="mailto:<?php echo htmlspecialchars($pet['shelter_email']); ?>?subject=Inquiry about <?php echo urlencode($pet['pet_name']); ?>"
                                class="contact-btn">
                                <i class="fas fa-envelope"></i>
                                Email
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="info-card">
                    <div class="card-header">
                        <i class="fas fa-tools"></i>
                        <h3>Quick Actions</h3>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <button onclick="sharePet()" class="btn btn-secondary" style="width: 100%;">
                            <i class="fas fa-share"></i>
                            Share This Pet
                        </button>

                        <button onclick="printPetDetails()" class="btn btn-secondary" style="width: 100%;">
                            <i class="fas fa-print"></i>
                            Print Details
                        </button>

                        <a href="careGuides.php?category=<?php echo (int)$pet['category_id']; ?>"
                            class="btn btn-secondary" style="width: 100%;">
                            <i class="fas fa-book-open"></i>
                            Care Guides
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <span class="modal-close" onclick="closeImageModal()">&times;</span>
        <img class="modal-image" id="modalImage" src="" alt="">
    </div>

    <script>
    // Store display images for gallery navigation
    const displayImages = <?php echo json_encode($display_images); ?>;
    let currentImageIndex = 0;

    // Image gallery functions
    function changeMainImage(imageSrc, thumbnail) {
        const mainImage = document.getElementById('mainImage');
        mainImage.src = '../uploads/' + imageSrc;

        // Update active thumbnail
        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.classList.remove('active');
        });
        thumbnail.classList.add('active');

        // Update current index
        currentImageIndex = Array.from(document.querySelectorAll('.thumbnail')).indexOf(thumbnail);
    }

    function previousImage() {
        if (displayImages.length <= 1) return;

        currentImageIndex = (currentImageIndex - 1 + displayImages.length) % displayImages.length;
        updateMainImage();
    }

    function nextImage() {
        if (displayImages.length <= 1) return;

        currentImageIndex = (currentImageIndex + 1) % displayImages.length;
        updateMainImage();
    }

    function updateMainImage() {
        if (displayImages.length === 0) return;

        const mainImage = document.getElementById('mainImage');
        const newImageSrc = displayImages[currentImageIndex];
        mainImage.src = '../uploads/' + newImageSrc;

        // Update active thumbnail
        const thumbnails = document.querySelectorAll('.thumbnail');
        thumbnails.forEach((thumb, index) => {
            thumb.classList.toggle('active', index === currentImageIndex);
        });
    }

    // Image modal functions
    function openImageModal(imageSrc) {
        const modal = document.getElementById('imageModal');
        const modalImage = document.getElementById('modalImage');

        modal.classList.add('show');
        modalImage.src = imageSrc;
        modalImage.alt = '<?php echo htmlspecialchars($pet['pet_name']); ?>';
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    // Close modal when clicking outside image
    document.getElementById('imageModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeImageModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeImageModal();
        }
    });

    // Keyboard navigation for image gallery
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('imageModal').classList.contains('show')) {
            return; // Don't navigate gallery when modal is open
        }

        if (e.key === 'ArrowLeft') {
            previousImage();
        } else if (e.key === 'ArrowRight') {
            nextImage();
        }
    });

    // Share pet function
    function sharePet() {
        const petName = '<?php echo htmlspecialchars($pet['pet_name']); ?>';
        const petUrl = window.location.href;

        if (navigator.share) {
            navigator.share({
                title: `Meet ${petName} - Available for Adoption`,
                text: `${petName} is looking for a loving home! Check out this adorable pet available for adoption.`,
                url: petUrl
            }).then(() => {
                console.log('Successfully shared');
            }).catch((error) => {
                console.log('Error sharing:', error);
                fallbackShare(petName, petUrl);
            });
        } else {
            fallbackShare(petName, petUrl);
        }
    }

    function fallbackShare(petName, petUrl) {
        // Copy to clipboard
        const textArea = document.createElement('textarea');
        textArea.value =
            `Meet ${petName} - Available for Adoption!\n\n${petName} is looking for a loving home. Check out this adorable pet:\n${petUrl}`;
        document.body.appendChild(textArea);
        textArea.select();

        try {
            document.execCommand('copy');
            alert('Pet details copied to clipboard! You can now paste and share it.');
        } catch (err) {
            console.error('Unable to copy to clipboard:', err);
            // Show share options modal
            showShareModal(petName, petUrl);
        }

        document.body.removeChild(textArea);
    }

    function showShareModal(petName, petUrl) {
        const shareText = encodeURIComponent(
            `Meet ${petName} - Available for Adoption! ${petName} is looking for a loving home.`);
        const shareUrl = encodeURIComponent(petUrl);

        const shareOptions = [{
                name: 'Facebook',
                url: `https://www.facebook.com/sharer/sharer.php?u=${shareUrl}&quote=${shareText}`,
                icon: 'fab fa-facebook'
            },
            {
                name: 'Twitter',
                url: `https://twitter.com/intent/tweet?text=${shareText}&url=${shareUrl}`,
                icon: 'fab fa-twitter'
            },
            {
                name: 'WhatsApp',
                url: `https://wa.me/?text=${shareText}%20${shareUrl}`,
                icon: 'fab fa-whatsapp'
            },
            {
                name: 'Email',
                url: `mailto:?subject=${encodeURIComponent('Meet ' + petName)}&body=${shareText}%20${shareUrl}`,
                icon: 'fas fa-envelope'
            }
        ];

        let modalHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3000; display: flex; align-items: center; justify-content: center;" onclick="this.remove()">
                    <div style="background: white; padding: 30px; border-radius: 15px; max-width: 400px; width: 90%;" onclick="event.stopPropagation()">
                        <h3 style="margin-bottom: 20px; text-align: center; color: #2c3e50;">Share ${petName}</h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            `;

        shareOptions.forEach(option => {
            modalHTML += `
                    <a href="${option.url}" target="_blank" style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: #2c3e50; font-weight: 500; transition: all 0.3s ease;" onmouseover="this.style.background='#e9ecef'" onmouseout="this.style.background='#f8f9fa'">
                        <i class="${option.icon}" style="font-size: 1.2rem; color: #667eea;"></i>
                        ${option.name}
                    </a>
                `;
        });

        modalHTML += `
                        </div>
                        <button onclick="this.closest('[style*=\"position: fixed\"]').remove()" style="margin-top: 20px; width: 100%; padding: 10px; background: #6c757d; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">Close</button>
                    </div>
                </div>
            `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    // Print pet details function
    function printPetDetails() {
        const petData = {
            name: '<?php echo htmlspecialchars($pet['pet_name']); ?>',
            category: '<?php echo htmlspecialchars($pet['category_name']); ?>',
            breed: '<?php echo htmlspecialchars($pet['breed_name'] ?? 'Mixed Breed'); ?>',
            age: '<?php echo (int)$pet['age']; ?>',
            gender: '<?php echo ucfirst($pet['gender']); ?>',
            size: '<?php echo ucfirst($pet['size'] ?? 'Not specified'); ?>',
            health: '<?php echo htmlspecialchars($pet['health_status'] ?: 'Healthy'); ?>',
            description: '<?php echo htmlspecialchars($pet['description'] ?? ''); ?>',
            adoptionFee: '<?php echo $pet['adoption_fee'] > 0 ? '$' . number_format((float)$pet['adoption_fee'], 2) : 'Free'; ?>',
            shelter: '<?php echo htmlspecialchars($pet['shelter_name']); ?>',
            contact: '<?php echo htmlspecialchars($pet['shelter_contact_first_name'] . ' ' . $pet['shelter_contact_last_name']); ?>',
            phone: '<?php echo htmlspecialchars($pet['shelter_phone'] ?? 'Not provided'); ?>',
            email: '<?php echo htmlspecialchars($pet['shelter_email']); ?>'
        };

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>${petData.name} - Pet Details</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; color: #333; line-height: 1.6; }
                        .header { text-align: center; border-bottom: 2px solid #667eea; padding-bottom: 20px; margin-bottom: 30px; }
                        .header h1 { color: #2c3e50; margin-bottom: 10px; }
                        .section { margin-bottom: 25px; }
                        .section h2 { color: #2c3e50; border-bottom: 1px solid #e9ecef; padding-bottom: 5px; margin-bottom: 15px; }
                        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
                        .info-item { display: flex; }
                        .info-item strong { min-width: 100px; color: #495057; }
                        .description { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
                        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #e9ecef; font-size: 12px; color: #666; text-align: center; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>${petData.name}</h1>
                        <p>Pet Details - Generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                    
                    <div class="section">
                        <h2>Basic Information</h2>
                        <div class="info-grid">
                            <div class="info-item"><strong>Category:</strong> <span>${petData.category}</span></div>
                            <div class="info-item"><strong>Breed:</strong> <span>${petData.breed}</span></div>
                            <div class="info-item"><strong>Age:</strong> <span>${petData.age} years old</span></div>
                            <div class="info-item"><strong>Gender:</strong> <span>${petData.gender}</span></div>
                            <div class="info-item"><strong>Size:</strong> <span>${petData.size}</span></div>
                            <div class="info-item"><strong>Health Status:</strong> <span>${petData.health}</span></div>
                        </div>
                        
                        <div class="info-item" style="margin-bottom: 15px;">
                            <strong>Adoption Fee:</strong> <span>${petData.adoptionFee}</span>
                        </div>
                        
                        ${petData.description ? `
                            <div class="description">
                                <strong>About ${petData.name}:</strong><br><br>
                                ${petData.description.replace(/\n/g, '<br>')}
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="section">
                        <h2>Shelter Information</h2>
                        <div class="info-item"><strong>Shelter:</strong> <span>${petData.shelter}</span></div>
                        <div class="info-item"><strong>Contact Person:</strong> <span>${petData.contact}</span></div>
                        <div class="info-item"><strong>Phone:</strong> <span>${petData.phone}</span></div>
                        <div class="info-item"><strong>Email:</strong> <span>${petData.email}</span></div>
                    </div>
                    
                    <div class="footer">
                        <p>Generated from Pet Adoption Care Guide - ${window.location.origin}</p>
                        <p>For more information, please contact the shelter directly.</p>
                    </div>
                </body>
                </html>
            `);
        printWindow.document.close();
        printWindow.print();
    }

    // Smooth scroll to sections
    function scrollToSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (section) {
            section.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }

    // Lazy loading for images
    function lazyLoadImages() {
        const images = document.querySelectorAll('img[data-src]');
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    }

    // Initialize tooltips
    function initializeTooltips() {
        const elements = document.querySelectorAll('[title]');
        elements.forEach(el => {
            el.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.title;
                tooltip.style.cssText = `
                        position: absolute;
                        background: rgba(0,0,0,0.8);
                        color: white;
                        padding: 5px 10px;
                        border-radius: 4px;
                        font-size: 12px;
                        pointer-events: none;
                        z-index: 1000;
                        white-space: nowrap;
                    `;
                document.body.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + 'px';
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';

                this.removeAttribute('title');
                this._originalTitle = tooltip.textContent;
            });

            el.addEventListener('mouseleave', function() {
                const tooltip = document.querySelector('.tooltip');
                if (tooltip) tooltip.remove();
                if (this._originalTitle) {
                    this.title = this._originalTitle;
                }
            });
        });
    }

    // Auto-refresh pet status (check if still available)
    function checkPetStatus() {
        fetch(`../api/check_pet_status.php?pet_id=<?php echo (int)$pet['pet_id']; ?>`)
            .then(response => response.json())
            .then(data => {
                if (!data.available) {
                    showStatusAlert('This pet is no longer available for adoption.', 'warning');
                    // Disable adoption button
                    const adoptBtn = document.querySelector('a[href*="applyAdoption"]');
                    if (adoptBtn) {
                        adoptBtn.classList.remove('btn-primary');
                        adoptBtn.classList.add('btn-disabled');
                        adoptBtn.innerHTML = '<i class="fas fa-times"></i> No Longer Available';
                        adoptBtn.style.pointerEvents = 'none';
                    }
                }
            })
            .catch(error => {
                console.log('Status check failed:', error);
            });
    }

    // Show status alert
    function showStatusAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                z-index: 1500;
                max-width: 300px;
                animation: slideIn 0.3s ease;
            `;

        const colors = {
            info: {
                bg: 'rgba(23, 162, 184, 0.1)',
                color: '#0c5460',
                border: '#bee5eb'
            },
            warning: {
                bg: 'rgba(255, 193, 7, 0.1)',
                color: '#856404',
                border: '#ffeaa7'
            },
            success: {
                bg: 'rgba(40, 167, 69, 0.1)',
                color: '#155724',
                border: '#c3e6cb'
            }
        };

        const color = colors[type] || colors.info;
        alertDiv.style.background = color.bg;
        alertDiv.style.color = color.color;
        alertDiv.style.border = `1px solid ${color.border}`;

        alertDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; margin-left: 10px;">&times;</button>
                </div>
            `;

        document.body.appendChild(alertDiv);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // Enhanced image loading with error handling
    function handleImageError(img) {
        img.style.display = 'none';
        const container = img.parentElement;
        const fallback = container.querySelector('.no-image-main, .no-image');
        if (fallback) {
            fallback.style.display = 'flex';
        } else {
            const icon = document.createElement('i');
            icon.className = 'fas fa-paw';
            icon.style.cssText = 'color: #6c757d; font-size: 3rem;';
            container.appendChild(icon);
        }
    }

    // Add error handlers to all images
    document.addEventListener('DOMContentLoaded', function() {
        const images = document.querySelectorAll('img');
        images.forEach(img => {
            img.addEventListener('error', function() {
                handleImageError(this);
            });
        });

        // Initialize other features
        lazyLoadImages();
        initializeTooltips();

        // Check pet status every 2 minutes
        // setInterval(checkPetStatus, 120000);

        console.log('Pet details page loaded successfully');
        console.log('Pet ID:', <?php echo (int)$pet['pet_id']; ?>);
        console.log('Available images:', displayImages.length);
    });

    // Handle browser back/forward navigation
    window.addEventListener('popstate', function(event) {
        // Handle any cleanup if needed
    });

    // Performance monitoring
    if (window.performance) {
        window.addEventListener('load', function() {
            const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
            console.log('Page load time:', loadTime + 'ms');
        });
    }

    // Add swipe support for mobile gallery
    let startX = 0;
    let endX = 0;

    document.getElementById('mainImage')?.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
    });

    document.getElementById('mainImage')?.addEventListener('touchend', function(e) {
        endX = e.changedTouches[0].clientX;
        handleSwipe();
    });

    function handleSwipe() {
        const threshold = 50;
        const diff = startX - endX;

        if (Math.abs(diff) > threshold) {
            if (diff > 0) {
                nextImage(); // Swipe left - next image
            } else {
                previousImage(); // Swipe right - previous image
            }
        }
    }

    // Accessibility enhancements
    document.addEventListener('keydown', function(e) {
        // Focus management for modals and navigation
        if (e.key === 'Tab') {
            const activeModal = document.querySelector('.modal.show');
            if (activeModal) {
                const focusableElements = activeModal.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );

                if (focusableElements.length > 0) {
                    const firstElement = focusableElements[0];
                    const lastElement = focusableElements[focusableElements.length - 1];

                    if (e.shiftKey) {
                        if (document.activeElement === firstElement) {
                            lastElement.focus();
                            e.preventDefault();
                        }
                    } else {
                        if (document.activeElement === lastElement) {
                            firstElement.focus();
                            e.preventDefault();
                        }
                    }
                }
            }
        }
    });
    </script>
</body>

</html>