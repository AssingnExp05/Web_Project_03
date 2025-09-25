<?php
// shelter/addPet.php - Add Pet Page for Shelters
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Base URL
$BASE_URL = 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Check if user is logged in and is a shelter
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'shelter') {
    $_SESSION['error_message'] = 'Please login as a shelter to access this page.';
    header('Location: ' . $BASE_URL . 'auth/login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$page_title = 'Add New Pet - Shelter Dashboard';

// Initialize variables
$categories = [];
$breeds = [];
$shelter_info = null;
$success_message = '';
$error_message = '';

// Database operations
try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if ($db) {
        // Get shelter information
        $stmt = $db->prepare("SELECT * FROM shelters WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $shelter_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$shelter_info) {
            $_SESSION['error_message'] = 'Shelter information not found.';
            header('Location: ' . $BASE_URL . 'auth/login.php');
            exit();
        }
        
        // Get pet categories
        $stmt = $db->prepare("SELECT * FROM pet_categories ORDER BY category_name");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Get all breeds (will be filtered by JavaScript)
        $stmt = $db->prepare("
            SELECT pb.breed_id, pb.breed_name, pb.category_id, pc.category_name 
            FROM pet_breeds pb 
            JOIN pet_categories pc ON pb.category_id = pc.category_id 
            ORDER BY pc.category_name, pb.breed_name
        ");
        $stmt->execute();
        $breeds = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Exception $e) {
    error_log("Add Pet database error: " . $e->getMessage());
    $error_message = "Database connection error.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pet'])) {
    try {
        // Validate required fields
        $required_fields = ['pet_name', 'category_id', 'age', 'gender', 'description'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            $error_message = "Please fill in all required fields: " . implode(', ', $missing_fields);
        } else {
            // Get form data
            $pet_name = trim($_POST['pet_name']);
            $category_id = intval($_POST['category_id']);
            $breed_id = !empty($_POST['breed_id']) ? intval($_POST['breed_id']) : null;
            $age = intval($_POST['age']);
            $gender = $_POST['gender'];
            $size = $_POST['size'] ?? null;
            $description = trim($_POST['description']);
            $health_status = trim($_POST['health_status']) ?: 'Good';
            $adoption_fee = !empty($_POST['adoption_fee']) ? floatval($_POST['adoption_fee']) : 0.00;
            $status = 'available'; // Default status for new pets
            
            // Handle file upload
            $primary_image = null;
            $upload_error = false;
            
            if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/';
                
                // Create uploads directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_type = $_FILES['primary_image']['type'];
                
                if (!in_array($file_type, $allowed_types)) {
                    $error_message = "Only JPEG, PNG, and GIF images are allowed.";
                    $upload_error = true;
                } else {
                    // Generate unique filename
                    $file_extension = pathinfo($_FILES['primary_image']['name'], PATHINFO_EXTENSION);
                    $primary_image = 'pet_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $upload_path = $upload_dir . $primary_image;
                    
                    // Move uploaded file
                    if (!move_uploaded_file($_FILES['primary_image']['tmp_name'], $upload_path)) {
                        $error_message = "Failed to upload image.";
                        $upload_error = true;
                    }
                }
            }
            
            if (!$upload_error) {
                // Start database transaction
                $db->beginTransaction();
                
                try {
                    // Insert pet record
                    $stmt = $db->prepare("
                        INSERT INTO pets (shelter_id, category_id, breed_id, pet_name, age, gender, size, 
                                         description, health_status, adoption_fee, status, primary_image, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $result = $stmt->execute([
                        $shelter_info['shelter_id'],
                        $category_id,
                        $breed_id,
                        $pet_name,
                        $age,
                        $gender,
                        $size,
                        $description,
                        $health_status,
                        $adoption_fee,
                        $status,
                        $primary_image
                    ]);
                    
                    if ($result) {
                        $pet_id = $db->lastInsertId();
                        
                        // If there's a primary image, also add it to pet_images table
                        if ($primary_image) {
                            $stmt = $db->prepare("
                                INSERT INTO pet_images (pet_id, image_path, is_primary)
                                VALUES (?, ?, TRUE)
                            ");
                            $stmt->execute([$pet_id, $primary_image]);
                        }
                        
                        // Handle additional images
                        if (isset($_FILES['additional_images'])) {
                            $additional_files = $_FILES['additional_images'];
                            
                            for ($i = 0; $i < count($additional_files['name']); $i++) {
                                if ($additional_files['error'][$i] === UPLOAD_ERR_OK) {
                                    $file_type = $additional_files['type'][$i];
                                    
                                    if (in_array($file_type, $allowed_types)) {
                                        $file_extension = pathinfo($additional_files['name'][$i], PATHINFO_EXTENSION);
                                        $additional_image = 'pet_' . $pet_id . '_' . time() . '_' . $i . '.' . $file_extension;
                                        $additional_path = $upload_dir . $additional_image;
                                        
                                        if (move_uploaded_file($additional_files['tmp_name'][$i], $additional_path)) {
                                            $stmt = $db->prepare("
                                                INSERT INTO pet_images (pet_id, image_path, is_primary)
                                                VALUES (?, ?, FALSE)
                                            ");
                                            $stmt->execute([$pet_id, $additional_image]);
                                        }
                                    }
                                }
                            }
                        }
                        
                        $db->commit();
                        $success_message = "Pet '{$pet_name}' has been added successfully!";
                        
                        // Clear form data on success
                        $_POST = [];
                        
                    } else {
                        throw new Exception("Failed to insert pet record");
                    }
                    
                } catch (Exception $e) {
                    $db->rollback();
                    // Delete uploaded image if database insert fails
                    if ($primary_image && file_exists($upload_dir . $primary_image)) {
                        unlink($upload_dir . $primary_image);
                    }
                    throw $e;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Add Pet error: " . $e->getMessage());
        $error_message = "An error occurred while adding the pet. Please try again.";
    }
}

// Get current user info for navbar
$current_user = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $db->prepare("SELECT u.*, s.shelter_name FROM users u LEFT JOIN shelters s ON u.user_id = s.user_id WHERE u.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("User fetch error: " . $e->getMessage());
    }
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

    /* Navbar Styles */
    .navbar {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        padding: 1rem 0;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .navbar-container {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 20px;
    }

    .navbar-brand {
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .navbar-brand:hover {
        color: #ffd700;
        text-decoration: none;
    }

    .navbar-nav {
        display: flex;
        list-style: none;
        gap: 30px;
        align-items: center;
    }

    .nav-link {
        color: white;
        text-decoration: none;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nav-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #ffd700;
        text-decoration: none;
    }

    .dropdown {
        position: relative;
    }

    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        min-width: 200px;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        display: none;
        z-index: 1001;
        overflow: hidden;
    }

    .dropdown:hover .dropdown-menu {
        display: block;
    }

    .dropdown-item {
        display: block;
        padding: 12px 20px;
        color: #333;
        text-decoration: none;
        transition: background 0.3s ease;
        border-bottom: 1px solid #f1f1f1;
    }

    .dropdown-item:hover {
        background: #f8f9fa;
        color: #28a745;
        text-decoration: none;
    }

    .dropdown-item:last-child {
        border-bottom: none;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Header Section */
    .page-header {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        border-radius: 20px;
        padding: 30px 40px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .page-header h1 {
        font-size: 2.2rem;
        font-weight: 700;
        margin: 0;
    }

    .page-header p {
        font-size: 1.1rem;
        opacity: 0.9;
        margin: 5px 0 0 0;
    }

    .header-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .btn-primary {
        background: #ffd700;
        color: #28a745;
    }

    .btn-primary:hover {
        background: #ffed4e;
        transform: translateY(-2px);
        text-decoration: none;
        color: #20c997;
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.3);
        text-decoration: none;
        color: white;
    }

    .btn-success {
        background: #28a745;
        color: white;
        font-size: 1rem;
        padding: 12px 30px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-success:hover {
        background: #218838;
        transform: translateY(-2px);
    }

    .btn-outline {
        background: transparent;
        color: #28a745;
        border: 2px solid #28a745;
    }

    .btn-outline:hover {
        background: #28a745;
        color: white;
    }

    /* Messages */
    .message {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
    }

    .message.success {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
        border: 1px solid rgba(40, 167, 69, 0.3);
    }

    .message.error {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.3);
    }

    /* Form Section */
    .form-section {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .form-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 25px 30px;
        border-bottom: 1px solid #dee2e6;
    }

    .form-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .form-subtitle {
        color: #6c757d;
        margin: 5px 0 0 0;
        font-size: 1rem;
    }

    .form-body {
        padding: 30px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 25px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .form-group label.required::after {
        content: '*';
        color: #dc3545;
        margin-left: 3px;
    }

    .form-control {
        padding: 12px 16px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
    }

    .form-control:focus {
        outline: none;
        border-color: #28a745;
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
    }

    .form-control:invalid {
        border-color: #dc3545;
    }

    .form-control[readonly] {
        background: #f8f9fa;
        color: #6c757d;
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    select.form-control {
        cursor: pointer;
    }

    /* File Upload Styles */
    .file-upload-group {
        display: flex;
        flex-direction: column;
    }

    .file-upload {
        position: relative;
        display: inline-block;
        cursor: pointer;
        width: 100%;
    }

    .file-upload input[type=file] {
        position: absolute;
        left: -9999px;
    }

    .file-upload-label {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        border: 2px dashed #28a745;
        border-radius: 10px;
        background: rgba(40, 167, 69, 0.05);
        color: #28a745;
        font-weight: 600;
        transition: all 0.3s ease;
        cursor: pointer;
        min-height: 80px;
        text-align: center;
        gap: 10px;
    }

    .file-upload-label:hover {
        background: rgba(40, 167, 69, 0.1);
        border-color: #218838;
    }

    .file-upload-label i {
        font-size: 1.5rem;
    }

    .file-upload.has-file .file-upload-label {
        border-color: #20c997;
        background: rgba(32, 201, 151, 0.05);
        color: #20c997;
    }

    /* Multiple File Upload */
    .multiple-file-upload {
        border: 2px dashed #6c757d;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        background: #f8f9fa;
        color: #6c757d;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .multiple-file-upload:hover {
        border-color: #28a745;
        background: rgba(40, 167, 69, 0.05);
        color: #28a745;
    }

    .multiple-file-upload input[type=file] {
        display: none;
    }

    /* Preview Images */
    .image-preview {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .image-preview img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid #e9ecef;
        animation: fadeInScale 0.3s ease-out;
    }

    @keyframes fadeInScale {
        from {
            opacity: 0;
            transform: scale(0.8);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Form Actions */
    .form-actions {
        background: #f8f9fa;
        padding: 25px 30px;
        border-top: 1px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .form-actions-left {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .form-actions-right {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    /* Help Text */
    .help-text {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Custom Radio Styles */
    .custom-radio {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        margin-bottom: 10px;
    }

    .custom-radio input {
        margin: 0;
        transform: scale(1.2);
    }

    /* Validation Styles */
    .form-group.error .form-control {
        border-color: #dc3545;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
    }

    .form-group.success .form-control {
        border-color: #28a745;
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
    }

    .validation-message {
        color: #dc3545;
        font-size: 0.85rem;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Loading States */
    .loading {
        opacity: 0.7;
        pointer-events: none;
    }

    .spinner {
        border: 2px solid #f3f3f3;
        border-top: 2px solid #28a745;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        animation: spin 1s linear infinite;
        display: inline-block;
        margin-left: 10px;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-in {
        animation: fadeIn 0.6s ease-out;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .page-header {
            flex-direction: column;
            text-align: center;
        }

        .form-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .form-body {
            padding: 20px;
        }

        .form-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .form-actions-left,
        .form-actions-right {
            justify-content: center;
        }

        .navbar-nav {
            display: none;
        }

        .navbar-container {
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .form-grid {
            gap: 15px;
        }

        .file-upload-label,
        .multiple-file-upload {
            padding: 15px;
            min-height: 60px;
        }

        .image-preview img {
            width: 60px;
            height: 60px;
        }
    }

    /* Character Counter */
    .char-counter {
        font-size: 0.8rem;
        color: #6c757d;
        text-align: right;
        margin-top: 5px;
    }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="<?php echo $BASE_URL; ?>shelter/dashboard.php" class="navbar-brand">
                <i class="fas fa-home"></i>
                PetCare Shelter
            </a>

            <ul class="navbar-nav">
                <li><a href="<?php echo $BASE_URL; ?>shelter/dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a></li>
                <li><a href="<?php echo $BASE_URL; ?>shelter/addPet.php" class="nav-link">
                        <i class="fas fa-plus-circle"></i> Add Pet
                    </a></li>
                <li><a href="<?php echo $BASE_URL; ?>shelter/viewPets.php" class="nav-link">
                        <i class="fas fa-list"></i> My Pets
                    </a></li>
                <li><a href="<?php echo $BASE_URL; ?>shelter/adoptionRequests.php" class="nav-link">
                        <i class="fas fa-heart"></i> Adoption Requests
                    </a></li>
                <li><a href="<?php echo $BASE_URL; ?>shelter/vaccinationTracker.php" class="nav-link">
                        <i class="fas fa-syringe"></i> Vaccinations
                    </a></li>

                <li class="dropdown">
                    <a href="#" class="nav-link">
                        <i class="fas fa-user-circle"></i>
                        <?php echo htmlspecialchars($current_user['first_name'] ?? 'User'); ?>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a href="<?php echo $BASE_URL; ?>shelter/profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                        <a href="<?php echo $BASE_URL; ?>shelter/settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a href="<?php echo $BASE_URL; ?>auth/logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <div>
                <h1><i class="fas fa-plus-circle"></i> Add New Pet</h1>
                <p>Add a new pet to your shelter for adoption</p>
            </div>
            <div class="header-actions">
                <a href="<?php echo $BASE_URL; ?>shelter/viewPets.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> View All Pets
                </a>
                <a href="<?php echo $BASE_URL; ?>shelter/dashboard.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="message success fade-in">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="message error fade-in">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Add Pet Form -->
        <div class="form-section fade-in">
            <div class="form-header">
                <h2 class="form-title">
                    <i class="fas fa-paw"></i>
                    Pet Information
                </h2>
                <p class="form-subtitle">Please provide detailed information about the pet</p>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" id="addPetForm">
                <input type="hidden" name="add_pet" value="1">

                <div class="form-body">
                    <!-- Basic Information -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="pet_name" class="required">
                                <i class="fas fa-tag"></i>
                                Pet Name
                            </label>
                            <input type="text" id="pet_name" name="pet_name" class="form-control"
                                placeholder="Enter pet's name"
                                value="<?php echo htmlspecialchars($_POST['pet_name'] ?? ''); ?>" required>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i>
                                Choose a friendly, memorable name
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="age" class="required">
                                <i class="fas fa-birthday-cake"></i>
                                Age (in years)
                            </label>
                            <input type="number" id="age" name="age" class="form-control" placeholder="Enter age"
                                min="0" max="30" value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>" required>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i>
                                Approximate age if exact age unknown
                            </div>
                        </div>
                    </div>

                    <!-- Category and Breed -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="category_id" class="required">
                                <i class="fas fa-layer-group"></i>
                                Pet Category
                            </label>
                            <select id="category_id" name="category_id" class="form-control" required
                                onchange="updateBreeds()">
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>"
                                    <?php echo (($_POST['category_id'] ?? '') == $category['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i>
                                Select the type of animal
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="breed_id">
                                <i class="fas fa-dna"></i>
                                Breed (Optional)
                            </label>
                            <select id="breed_id" name="breed_id" class="form-control">
                                <option value="">Select breed (optional)</option>
                                <!-- Breeds will be populated by JavaScript -->
                            </select>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i>
                                Select a breed or leave blank for mixed/unknown
                            </div>
                        </div>
                    </div>

                    <!-- Gender and Size -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="required">
                                <i class="fas fa-venus-mars"></i>
                                Gender
                            </label>
                            <div style="display: flex; gap: 20px; margin-top: 8px;">
                                <div class="custom-radio">
                                    <input type="radio" id="male" name="gender" value="male"
                                        <?php echo (($_POST['gender'] ?? '') === 'male') ? 'checked' : ''; ?> required>
                                    <label for="male">Male</label>
                                </div>
                                <div class="custom-radio">
                                    <input type="radio" id="female" name="gender" value="female"
                                        <?php echo (($_POST['gender'] ?? '') === 'female') ? 'checked' : ''; ?>
                                        required>
                                    <label for="female">Female</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="size">
                                <i class="fas fa-ruler"></i>
                                Size
                            </label>
                            <select id="size" name="size" class="form-control">
                                <option value="">Select size (optional)</option>
                                <option value="small"
                                    <?php echo (($_POST['size'] ?? '') === 'small') ? 'selected' : ''; ?>>Small</option>
                                <option value="medium"
                                    <?php echo (($_POST['size'] ?? '') === 'medium') ? 'selected' : ''; ?>>Medium
                                </option>
                                <option value="large"
                                    <?php echo (($_POST['size'] ?? '') === 'large') ? 'selected' : ''; ?>>Large</option>
                            </select>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i>
                                General size category
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="description" class="required">
                                <i class="fas fa-file-alt"></i>
                                Description
                            </label>
                            <textarea id="description" name="description" class="form-control"
                                placeholder="Describe the pet's personality, behavior, special needs, etc."
                                required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i>
                                Provide detailed information to help potential adopters
                            </div>
                        </div>
                    </div>

                    <!-- Health and Fee -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="health_status">
                                <i class="fas fa-heartbeat"></i>
                                Health Status
                            </label>
                            <input type="text" id="health_status" name="health_status" class="form-control"
                                placeholder="e.g., Good, Excellent, Needs attention"
                                value="<?php echo htmlspecialchars($_POST['health_status'] ?? 'Good'); ?>">
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i>
                                Current health condition
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="adoption_fee">
                                <i class="fas fa-dollar-sign"></i>
                                Adoption Fee ($)
                            </label>
                            <input type="number" id="adoption_fee" name="adoption_fee" class="form-control"
                                placeholder="0.00" min="0" step="0.01"
                                value="<?php echo htmlspecialchars($_POST['adoption_fee'] ?? ''); ?>">
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i>
                                Leave blank for free adoption
                            </div>
                        </div>
                    </div>

                    <!-- Image Uploads -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="primary_image">
                                <i class="fas fa-camera"></i>
                                Primary Photo
                            </label>
                            <div class="file-upload" id="primaryImageUpload">
                                <input type="file" id="primary_image" name="primary_image" accept="image/*"
                                    onchange="handlePrimaryImageUpload(this)">
                                <label for="primary_image" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>
                                        <div>Choose Primary Photo</div>
                                        <small>JPEG, PNG, or GIF (Max 5MB)</small>
                                    </div>
                                </label>
                            </div>
                            <div id="primaryImagePreview" class="image-preview"></div>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i>
                                This will be the main photo shown in listings
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="additional_images">
                                <i class="fas fa-images"></i>
                                Additional Photos (Optional)
                            </label>
                            <div class="multiple-file-upload"
                                onclick="document.getElementById('additional_images').click()">
                                <i class="fas fa-plus-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p>Click to add more photos</p>
                                <small>You can select multiple images at once</small>
                                <input type="file" id="additional_images" name="additional_images[]" accept="image/*"
                                    multiple onchange="handleAdditionalImagesUpload(this)">
                            </div>
                            <div id="additionalImagesPreview" class="image-preview"></div>
                            <div class="help-text">
                                <i class="fas fa-info-circle"></i>
                                Additional photos help show the pet's personality
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <div class="form-actions-left">
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i>
                            All fields marked with * are required
                        </div>
                    </div>
                    <div class="form-actions-right">
                        <button type="button" class="btn btn-outline" onclick="resetForm()">
                            <i class="fas fa-undo"></i>
                            Reset Form
                        </button>
                        <button type="submit" class="btn btn-success" id="submitBtn">
                            <i class="fas fa-plus-circle"></i>
                            Add Pet
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Breeds data from PHP
    const breedsData = <?php echo json_encode($breeds); ?>;

    // Update breeds dropdown based on selected category
    function updateBreeds() {
        const categorySelect = document.getElementById('category_id');
        const breedSelect = document.getElementById('breed_id');
        const selectedCategoryId = parseInt(categorySelect.value);

        // Clear existing options
        breedSelect.innerHTML = '<option value="">Select breed (optional)</option>';

        if (selectedCategoryId) {
            // Filter breeds by category
            const categoryBreeds = breedsData.filter(breed => breed.category_id == selectedCategoryId);

            // Add breed options
            categoryBreeds.forEach(breed => {
                const option = document.createElement('option');
                option.value = breed.breed_id;
                option.textContent = breed.breed_name;
                breedSelect.appendChild(option);
            });

            // Restore selected breed if it exists
            const savedBreedId = '<?php echo $_POST['breed_id'] ?? ''; ?>';
            if (savedBreedId) {
                breedSelect.value = savedBreedId;
            }
        }
    }

    // Handle primary image upload preview
    function handlePrimaryImageUpload(input) {
        const preview = document.getElementById('primaryImagePreview');
        const uploadDiv = document.getElementById('primaryImageUpload');

        preview.innerHTML = '';

        if (input.files && input.files[0]) {
            const file = input.files[0];

            // Validate file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                input.value = '';
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.width = '100px';
                img.style.height = '100px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '8px';
                img.style.border = '2px solid #28a745';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);

            // Update upload label
            uploadDiv.classList.add('has-file');
            const label = uploadDiv.querySelector('.file-upload-label');
            label.innerHTML = `
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <div>Photo Selected</div>
                        <small>${file.name}</small>
                    </div>
                `;
        }
    }

    // Handle additional images upload preview
    function handleAdditionalImagesUpload(input) {
        const preview = document.getElementById('additionalImagesPreview');
        preview.innerHTML = '';

        if (input.files && input.files.length > 0) {
            // Limit to 5 additional images
            const maxFiles = Math.min(input.files.length, 5);

            for (let i = 0; i < maxFiles; i++) {
                const file = input.files[i];

                // Validate file size
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File ${file.name} is too large. Maximum size is 5MB.`);
                    continue;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.width = '80px';
                    img.style.height = '80px';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '8px';
                    img.style.border = '2px solid #20c997';
                    img.style.margin = '5px';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            }

            // Show file count info
            if (input.files.length > 5) {
                const info = document.createElement('div');
                info.style.cssText = 'color: #ffc107; font-size: 0.85rem; margin-top: 5px;';
                info.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Only first 5 images will be uploaded.`;
                preview.appendChild(info);
            }
        }
    }

    // Reset form
    function resetForm() {
        if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
            document.getElementById('addPetForm').reset();
            document.getElementById('primaryImagePreview').innerHTML = '';
            document.getElementById('additionalImagesPreview').innerHTML = '';

            // Reset primary image upload styling
            const uploadDiv = document.getElementById('primaryImageUpload');
            uploadDiv.classList.remove('has-file');
            const label = uploadDiv.querySelector('.file-upload-label');
            label.innerHTML = `
                    <i class="fas fa-cloud-upload-alt"></i>
                    <div>
                        <div>Choose Primary Photo</div>
                        <small>JPEG, PNG, or GIF (Max 5MB)</small>
                    </div>
                `;

            // Reset breeds dropdown
            document.getElementById('breed_id').innerHTML = '<option value="">Select breed (optional)</option>';
        }
    }

    // Form validation
    function validateForm() {
        const form = document.getElementById('addPetForm');
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            const formGroup = field.closest('.form-group');

            if (field.type === 'radio') {
                const radioGroup = form.querySelectorAll(`[name="${field.name}"]`);
                const isChecked = Array.from(radioGroup).some(radio => radio.checked);

                if (!isChecked) {
                    formGroup.classList.add('error');
                    isValid = false;
                } else {
                    formGroup.classList.remove('error');
                    formGroup.classList.add('success');
                }
            } else {
                if (!field.value.trim()) {
                    formGroup.classList.add('error');
                    isValid = false;
                } else {
                    formGroup.classList.remove('error');
                    formGroup.classList.add('success');
                }
            }
        });

        return isValid;
    }

    // Show message function
    function showMessage(message, type) {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.message');
        existingMessages.forEach(msg => {
            if (!msg.classList.contains('fade-in')) {
                msg.remove();
            }
        });

        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;

        // Insert at the top of the container
        const container = document.querySelector('.container');
        const firstChild = container.firstElementChild.nextElementSibling;
        container.insertBefore(messageDiv, firstChild);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    // Age validation
    function validateAge() {
        const ageInput = document.getElementById('age');
        const age = parseInt(ageInput.value);
        const helpText = ageInput.nextElementSibling;

        if (age < 0) {
            ageInput.value = 0;
        } else if (age > 30) {
            ageInput.value = 30;
            helpText.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Maximum age is 30 years';
            helpText.style.color = '#ffc107';
        } else {
            helpText.innerHTML = '<i class="fas fa-info-circle"></i> Approximate age if exact age unknown';
            helpText.style.color = '#6c757d';
        }
    }

    // Adoption fee validation
    function validateAdoptionFee() {
        const feeInput = document.getElementById('adoption_fee');
        const fee = parseFloat(feeInput.value);
        const helpText = feeInput.nextElementSibling;

        if (fee < 0) {
            feeInput.value = 0;
        } else if (fee > 10000) {
            feeInput.value = 10000;
            helpText.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Maximum fee is $10,000';
            helpText.style.color = '#ffc107';
        } else {
            helpText.innerHTML = '<i class="fas fa-info-circle"></i> Leave blank for free adoption';
            helpText.style.color = '#6c757d';
        }
    }

    // Auto-resize textarea
    function autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }

    // Character counter for description
    function updateCharacterCounter(textarea) {
        const maxLength = 1000;
        const currentLength = textarea.value.length;
        const formGroup = textarea.closest('.form-group');

        // Remove existing counter
        const existingCounter = formGroup.querySelector('.char-counter');
        if (existingCounter) {
            existingCounter.remove();
        }

        // Add character counter
        if (currentLength > 0) {
            const counter = document.createElement('div');
            counter.className = 'char-counter';
            counter.style.cssText = `
                    font-size: 0.8rem; 
                    color: ${currentLength > maxLength ? '#dc3545' : '#6c757d'};
                    text-align: right;
                    margin-top: 5px;
                `;
            counter.textContent = `${currentLength}/${maxLength} characters`;
            formGroup.appendChild(counter);

            if (currentLength > maxLength) {
                textarea.style.borderColor = '#dc3545';
            } else {
                textarea.style.borderColor = '#e9ecef';
            }
        }
    }

    // Initialize page functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize breeds if category is already selected
        updateBreeds();

        // Add event listeners
        const form = document.getElementById('addPetForm');
        const inputs = form.querySelectorAll('input, select, textarea');

        // Real-time validation
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                const formGroup = this.closest('.form-group');

                if (this.hasAttribute('required')) {
                    if (this.type === 'radio') {
                        const radioGroup = form.querySelectorAll(`[name="${this.name}"]`);
                        const isChecked = Array.from(radioGroup).some(radio => radio.checked);

                        if (isChecked) {
                            formGroup.classList.remove('error');
                            formGroup.classList.add('success');
                        }
                    } else {
                        if (this.value.trim()) {
                            formGroup.classList.remove('error');
                            formGroup.classList.add('success');
                        }
                    }
                }
            });

            input.addEventListener('input', function() {
                const formGroup = this.closest('.form-group');
                formGroup.classList.remove('error');
            });
        });

        // Age input validation
        document.getElementById('age').addEventListener('input', validateAge);

        // Adoption fee validation
        document.getElementById('adoption_fee').addEventListener('input', validateAdoptionFee);

        // Description textarea functionality
        const descriptionTextarea = document.getElementById('description');
        descriptionTextarea.addEventListener('input', function() {
            autoResizeTextarea(this);
            updateCharacterCounter(this);
        });

        // Form submission handling
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                showMessage('Please fill in all required fields correctly.', 'error');

                // Scroll to first error
                const firstError = form.querySelector('.form-group.error');
                if (firstError) {
                    firstError.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
                return;
            }

            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Pet...';

            // Add loading class to form
            form.classList.add('loading');

            // Re-enable button after 10 seconds (safety measure)
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                form.classList.remove('loading');
            }, 10000);
        });

        // Prevent form submission on Enter key (except for submit button)
        form.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'BUTTON' && e.target.type !== 'submit') {
                e.preventDefault();
            }
        });
    });

    // Drag and drop functionality
    function initializeDragAndDrop() {
        const primaryUpload = document.querySelector('.file-upload-label');
        const additionalUpload = document.querySelector('.multiple-file-upload');

        [primaryUpload, additionalUpload].forEach(element => {
            element.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.backgroundColor = 'rgba(40, 167, 69, 0.15)';
                this.style.borderColor = '#218838';
            });

            element.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.backgroundColor = '';
                this.style.borderColor = '';
            });

            element.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.backgroundColor = '';
                this.style.borderColor = '';

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    if (this === primaryUpload) {
                        // Primary image upload
                        const input = document.getElementById('primary_image');
                        const dt = new DataTransfer();
                        dt.items.add(files[0]); // Only take first file for primary
                        input.files = dt.files;
                        handlePrimaryImageUpload(input);
                    } else {
                        // Additional images upload
                        const input = document.getElementById('additional_images');
                        input.files = files;
                        handleAdditionalImagesUpload(input);
                    }
                }
            });
        });
    }

    // Initialize drag and drop
    initializeDragAndDrop();

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+S to save
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            if (validateForm()) {
                document.getElementById('addPetForm').submit();
            }
        }

        // Escape to reset form
        if (e.key === 'Escape') {
            resetForm();
        }
    });

    // Auto-hide success messages
    setTimeout(() => {
        const successMessages = document.querySelectorAll('.message.success');
        successMessages.forEach(message => {
            message.style.opacity = '0';
            setTimeout(() => {
                if (message.parentNode) {
                    message.remove();
                }
            }, 500);
        });
    }, 5000);

    // Form change tracking
    let formChanged = false;

    document.getElementById('addPetForm').addEventListener('change', function() {
        formChanged = true;
    });

    document.getElementById('addPetForm').addEventListener('input', function() {
        formChanged = true;
    });

    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            const message = 'You have unsaved changes. Are you sure you want to leave?';
            e.returnValue = message;
            return message;
        }
    });

    // Remove warning on form submission
    document.getElementById('addPetForm').addEventListener('submit', function() {
        formChanged = false;
    });
    </script>
</body>

</html>