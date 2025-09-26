<?php
// shelter/addCareGuide.php - Add Care Guide Page for Shelters
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
$page_title = 'Add Care Guide - Shelter Dashboard';

// Initialize variables
$shelter_info = null;
$categories = [];
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $difficulty_level = $_POST['difficulty_level'] ?? '';
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Guide title is required.';
    } elseif (strlen($title) > 200) {
        $errors[] = 'Guide title must be less than 200 characters.';
    }
    
    if (empty($content)) {
        $errors[] = 'Guide content is required.';
    } elseif (strlen($content) < 50) {
        $errors[] = 'Guide content must be at least 50 characters long.';
    }
    
    if ($category_id <= 0) {
        $errors[] = 'Please select a valid category.';
    }
    
    if (!in_array($difficulty_level, ['beginner', 'intermediate', 'advanced'])) {
        $errors[] = 'Please select a valid difficulty level.';
    }
    
    if (empty($errors)) {
        try {
            require_once __DIR__ . '/../config/db.php';
            $db = getDB();
            
            if ($db) {
                // Get shelter information
                $stmt = $db->prepare("SELECT shelter_id FROM shelters WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $shelter_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($shelter_data) {
                    // Check if category exists
                    $stmt = $db->prepare("SELECT category_id FROM pet_categories WHERE category_id = ?");
                    $stmt->execute([$category_id]);
                    
                    if ($stmt->fetch()) {
                        // Insert care guide
                        $stmt = $db->prepare("
                            INSERT INTO care_guides (category_id, title, content, author_id, difficulty_level, is_published, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        if ($stmt->execute([$category_id, $title, $content, $user_id, $difficulty_level, $is_published])) {
                            $_SESSION['success_message'] = 'Care guide added successfully!';
                            header('Location: ' . $BASE_URL . 'shelter/addCareGuide.php');
                            exit();
                        } else {
                            $error_message = 'Failed to add care guide. Please try again.';
                        }
                    } else {
                        $error_message = 'Invalid category selected.';
                    }
                } else {
                    $error_message = 'Shelter information not found.';
                }
            } else {
                $error_message = 'Database connection failed.';
            }
        } catch (Exception $e) {
            error_log("Add Care Guide error: " . $e->getMessage());
            $error_message = 'An error occurred while adding the care guide.';
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Fetch data for form
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
        
        // Get categories
        $stmt = $db->prepare("SELECT * FROM pet_categories ORDER BY category_name");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
    } else {
        throw new Exception("Database connection failed");
    }
    
} catch (Exception $e) {
    error_log("Add Care Guide database error: " . $e->getMessage());
    $error_message = "Database error occurred. Please try again later.";
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
        max-width: 1000px;
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
        box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
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
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        text-decoration: none;
    }

    .btn-primary {
        background: #ffd700;
        color: #28a745;
    }

    .btn-primary:hover {
        background: #ffed4e;
        color: #20c997;
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .btn-success:hover {
        background: #218838;
    }

    .btn-lg {
        padding: 15px 30px;
        font-size: 1.1rem;
    }

    /* Form Container */
    .form-container {
        background: white;
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .form-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f8f9fa;
    }

    .form-header h2 {
        font-size: 1.8rem;
        color: #2c3e50;
        margin-bottom: 10px;
    }

    .form-header p {
        color: #666;
        font-size: 1.1rem;
    }

    /* Form Styles */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 25px;
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
        border-color: #28a745;
        box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 200px;
        line-height: 1.6;
    }

    .form-group textarea#content {
        min-height: 350px;
        font-family: 'Georgia', serif;
    }

    /* Checkbox Styling */
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 5px;
    }

    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .checkbox-group label {
        margin: 0;
        cursor: pointer;
        font-weight: 500;
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

    /* Form Actions */
    .form-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding-top: 25px;
        border-top: 2px solid #f8f9fa;
        flex-wrap: wrap;
        gap: 15px;
    }

    .preview-info {
        color: #666;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .action-buttons {
        display: flex;
        gap: 15px;
    }

    /* Messages */
    .message {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        animation: slideIn 0.3s ease-out;
    }

    .message.success {
        background: rgba(40, 167, 69, 0.1);
        color: #155724;
        border: 1px solid rgba(40, 167, 69, 0.2);
    }

    .message.error {
        background: rgba(220, 53, 69, 0.1);
        color: #721c24;
        border: 1px solid rgba(220, 53, 69, 0.2);
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

    /* Preview Section */
    .preview-section {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-top: 30px;
        display: none;
    }

    .preview-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f8f9fa;
    }

    .preview-title {
        font-size: 1.5rem;
        color: #2c3e50;
        font-weight: 600;
    }

    .preview-content {
        line-height: 1.8;
        color: #333;
        font-size: 1rem;
    }

    .preview-meta {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #666;
        font-size: 0.9rem;
    }

    /* Help Text */
    .help-text {
        background: rgba(40, 167, 69, 0.05);
        border-left: 4px solid #28a745;
        padding: 15px 20px;
        margin-bottom: 25px;
        border-radius: 0 10px 10px 0;
    }

    .help-text h4 {
        color: #28a745;
        margin-bottom: 10px;
        font-size: 1rem;
    }

    .help-text ul {
        margin-left: 20px;
        color: #666;
    }

    .help-text li {
        margin-bottom: 5px;
    }

    /* Loading State */
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }

    .spinner {
        border: 2px solid #f3f3f3;
        border-top: 2px solid #28a745;
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
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .page-header {
            flex-direction: column;
            text-align: center;
            padding: 25px;
        }

        .page-header h1 {
            font-size: 1.8rem;
        }

        .form-container {
            padding: 25px;
        }

        .form-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .form-actions {
            flex-direction: column;
            text-align: center;
        }

        .action-buttons {
            justify-content: center;
            width: 100%;
        }

        .preview-section {
            padding: 20px;
        }
    }

    @media (max-width: 480px) {
        .page-header h1 {
            font-size: 1.5rem;
        }

        .form-container {
            padding: 20px;
        }

        .btn {
            padding: 8px 16px;
            font-size: 0.8rem;
        }

        .btn-lg {
            padding: 12px 24px;
            font-size: 1rem;
        }

        .action-buttons {
            flex-direction: column;
            width: 100%;
        }

        .action-buttons .btn {
            justify-content: center;
        }
    }
    </style>
</head>

<body>
    <!-- Include Shelter Navbar -->
    <?php include_once __DIR__ . '/../common/navbar_shelter.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-book-open"></i> Add Care Guide</h1>
                <p>Share your expertise with pet care knowledge and tips</p>
            </div>
            <div class="header-actions">
                <a href="<?php echo $BASE_URL; ?>shelter/dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="<?php echo $BASE_URL; ?>shelter/viewPets.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> My Pets
                </a>
            </div>
        </div>

        <!-- Display Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Help Text -->
        <div class="help-text">
            <h4><i class="fas fa-lightbulb"></i> Writing Tips</h4>
            <ul>
                <li>Choose a clear, descriptive title that explains what your guide covers</li>
                <li>Write detailed, step-by-step instructions that are easy to follow</li>
                <li>Include important safety tips and precautions</li>
                <li>Use simple language that pet owners of all experience levels can understand</li>
                <li>Consider adding troubleshooting tips for common problems</li>
                <li>Set the appropriate difficulty level to help readers choose suitable guides</li>
            </ul>
        </div>

        <!-- Care Guide Form -->
        <div class="form-container">
            <div class="form-header">
                <h2>Create New Care Guide</h2>
                <p>Help other pet owners with your knowledge and experience</p>
            </div>

            <form id="careGuideForm" method="POST" action="">
                <div class="form-grid">
                    <div class="form-group required full-width">
                        <label for="title">
                            <i class="fas fa-heading"></i> Guide Title
                        </label>
                        <input type="text" id="title" name="title" required maxlength="200"
                            placeholder="e.g., How to Train Your Puppy: Basic Commands"
                            value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                        <div class="char-counter" id="titleCounter">0 / 200 characters</div>
                    </div>

                    <div class="form-group required">
                        <label for="category_id">
                            <i class="fas fa-tag"></i> Pet Category
                        </label>
                        <select id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>"
                                <?php echo (($_POST['category_id'] ?? '') == $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group required">
                        <label for="difficulty_level">
                            <i class="fas fa-signal"></i> Difficulty Level
                        </label>
                        <select id="difficulty_level" name="difficulty_level" required>
                            <option value="">Select Difficulty</option>
                            <option value="beginner"
                                <?php echo (($_POST['difficulty_level'] ?? '') === 'beginner') ? 'selected' : ''; ?>>
                                <i class="fas fa-seedling"></i> Beginner - Perfect for first-time pet owners
                            </option>
                            <option value="intermediate"
                                <?php echo (($_POST['difficulty_level'] ?? '') === 'intermediate') ? 'selected' : ''; ?>>
                                <i class="fas fa-leaf"></i> Intermediate - For those with some experience
                            </option>
                            <option value="advanced"
                                <?php echo (($_POST['difficulty_level'] ?? '') === 'advanced') ? 'selected' : ''; ?>>
                                <i class="fas fa-tree"></i> Advanced - For experienced pet owners
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-group required full-width">
                    <label for="content">
                        <i class="fas fa-file-text"></i> Guide Content
                    </label>
                    <textarea id="content" name="content" required minlength="50"
                        placeholder="Write your comprehensive care guide here. Include step-by-step instructions, tips, safety precautions, and any other helpful information..."><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                    <div class="char-counter" id="contentCounter">0 characters (minimum 50)</div>
                </div>

                <div class="form-group full-width">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_published" name="is_published" value="1"
                            <?php echo (isset($_POST['is_published'])) ? 'checked' : ''; ?>>
                        <label for="is_published">
                            <i class="fas fa-eye"></i> Publish immediately (make visible to all users)
                        </label>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        <i class="fas fa-info-circle"></i> If unchecked, the guide will be saved as draft and can be
                        published later
                    </small>
                </div>

                <div class="form-actions">
                    <div class="preview-info">
                        <i class="fas fa-info-circle"></i>
                        You can preview your guide before submitting
                    </div>
                    <div class="action-buttons">
                        <button type="button" id="previewBtn" class="btn btn-secondary">
                            <i class="fas fa-eye"></i> Preview Guide
                        </button>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> Save Care Guide
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Preview Section -->
        <div class="preview-section" id="previewSection">
            <div class="preview-header">
                <h3 class="preview-title">Guide Preview</h3>
                <button type="button" id="closePreview" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Close Preview
                </button>
            </div>
            <div id="previewContent">
                <!-- Preview content will be inserted here -->
            </div>
        </div>
    </div>

    <script>
    // Character counters
    function updateCharCounter(inputId, counterId, maxLength = null) {
        const input = document.getElementById(inputId);
        const counter = document.getElementById(counterId);

        const updateCount = () => {
            const length = input.value.length;

            if (maxLength) {
                counter.textContent = `${length} / ${maxLength} characters`;
                if (length > maxLength * 0.8) {
                    counter.className = 'char-counter warning';
                }
                if (length > maxLength * 0.95) {
                    counter.className = 'char-counter error';
                }
                if (length <= maxLength * 0.8) {
                    counter.className = 'char-counter';
                }
            } else {
                counter.textContent = `${length} characters${length < 50 ? ' (minimum 50)' : ''}`;
                if (length < 50) {
                    counter.className = 'char-counter warning';
                } else {
                    counter.className = 'char-counter';
                }
            }
        };

        input.addEventListener('input', updateCount);
        updateCount(); // Initial count
    }

    // Initialize character counters
    updateCharCounter('title', 'titleCounter', 200);
    updateCharCounter('content', 'contentCounter');

    // Form validation
    function validateForm() {
        const title = document.getElementById('title').value.trim();
        const content = document.getElementById('content').value.trim();
        const category = document.getElementById('category_id').value;
        const difficulty = document.getElementById('difficulty_level').value;

        let isValid = true;
        let errors = [];

        if (!title) {
            errors.push('Guide title is required');
            isValid = false;
        } else if (title.length > 200) {
            errors.push('Guide title must be less than 200 characters');
            isValid = false;
        }

        if (!content) {
            errors.push('Guide content is required');
            isValid = false;
        } else if (content.length < 50) {
            errors.push('Guide content must be at least 50 characters long');
            isValid = false;
        }

        if (!category) {
            errors.push('Please select a pet category');
            isValid = false;
        }

        if (!difficulty) {
            errors.push('Please select a difficulty level');
            isValid = false;
        }

        if (!isValid) {
            alert('Please fix the following errors:\n• ' + errors.join('\n• '));
        }

        return isValid;
    }

    // Preview functionality
    function showPreview() {
        const title = document.getElementById('title').value.trim();
        const content = document.getElementById('content').value.trim();
        const categorySelect = document.getElementById('category_id');
        const difficultySelect = document.getElementById('difficulty_level');
        const isPublished = document.getElementById('is_published').checked;

        const categoryName = categorySelect.options[categorySelect.selectedIndex].text;
        const difficultyLevel = difficultySelect.options[difficultySelect.selectedIndex].text;

        if (!title || !content) {
            alert('Please fill in the title and content fields to preview the guide.');
            return;
        }

        const previewHTML = `
                <h2 style="color: #2c3e50; margin-bottom: 15px;">${title}</h2>
                <div class="preview-meta">
                    <div class="meta-item">
                        <i class="fas fa-tag"></i>
                        <span>Category: ${categoryName !== 'Select Category' ? categoryName : 'Not selected'}</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-signal"></i>
                        <span>Difficulty: ${difficultyLevel !== 'Select Difficulty' ? difficultyLevel.split(' - ')[0] : 'Not selected'}</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-${isPublished ? 'eye' : 'eye-slash'}"></i>
                        <span>Status: ${isPublished ? 'Published' : 'Draft'}</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <span>Author: <?php echo htmlspecialchars($_SESSION['username'] ?? 'You'); ?></span>
                    </div>
                </div>
                <div class="preview-content">
                    ${content.replace(/\n/g, '<br>')}
                </div>
            `;

        document.getElementById('previewContent').innerHTML = previewHTML;
        document.getElementById('previewSection').style.display = 'block';
        document.getElementById('previewSection').scrollIntoView({
            behavior: 'smooth'
        });
    }

    // Event listeners
    document.getElementById('previewBtn').addEventListener('click', showPreview);

    document.getElementById('closePreview').addEventListener('click', function() {
        document.getElementById('previewSection').style.display = 'none';
    });

    // Form submission
    document.getElementById('careGuideForm').addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            return;
        }

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;

        // Re-enable the button after a delay (in case of server-side validation errors)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 5000);
    });

    // Auto-resize textarea
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.max(textarea.scrollHeight, 350) + 'px';
    }

    document.getElementById('content').addEventListener('input', function() {
        autoResize(this);
    });

    // Initialize textarea height
    document.addEventListener('DOMContentLoaded', function() {
        autoResize(document.getElementById('content'));

        // Focus on title field
        document.getElementById('title').focus();
    });

    // Auto-save functionality (optional)
    let autoSaveTimeout;

    function autoSave() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(() => {
            const formData = new FormData(document.getElementById('careGuideForm'));
            // You could implement auto-save to localStorage here
            console.log('Auto-save triggered');
        }, 30000); // Auto-save every 30 seconds
    }

    // Trigger auto-save on input
    ['title', 'content'].forEach(id => {
        document.getElementById(id).addEventListener('input', autoSave);
    });
    </script>
</body>

</html>