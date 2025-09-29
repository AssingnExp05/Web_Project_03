<?php
// index.php - Homepage
require_once 'config/db.php';

// Page variables for header
$page_title = "Home";
$page_description = "Find your perfect companion at Pet Adoption Care Guide. Browse adoptable pets from trusted shelters and give a pet a loving forever home.";

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Initialize statistics with default values
$stats = [
    'pets_available' => 0,
    'successful_adoptions' => 0,
    'shelters' => 0,
    'happy_families' => 0
];

try {
    // Get statistics for homepage
    $stats['pets_available'] = DBHelper::count('pets', ['status' => 'available']);
    $stats['successful_adoptions'] = DBHelper::count('adoptions');
    $stats['shelters'] = DBHelper::count('shelters');
    $stats['happy_families'] = $stats['successful_adoptions'];
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

try {
    // Get featured pets with proper image handling
    $featured_pets = DBHelper::select("
        SELECT 
            p.pet_id,
            p.pet_name,
            p.age,
            p.gender,
            p.size,
            p.description,
            p.adoption_fee,
            p.created_at,
            pc.category_name,
            pb.breed_name,
            s.shelter_name,
            COALESCE(pi.image_path, p.primary_image) as pet_image,
            DATEDIFF(CURRENT_DATE, p.created_at) as days_waiting
        FROM pets p 
        LEFT JOIN pet_categories pc ON p.category_id = pc.category_id 
        LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id 
        LEFT JOIN shelters s ON p.shelter_id = s.shelter_id 
        LEFT JOIN (
            SELECT pet_id, image_path 
            FROM pet_images 
            WHERE is_primary = 1 
            GROUP BY pet_id
        ) pi ON p.pet_id = pi.pet_id
        WHERE p.status = 'available' 
        ORDER BY p.created_at DESC 
        LIMIT 8
    ");
} catch (Exception $e) {
    error_log("Error fetching featured pets: " . $e->getMessage());
    $featured_pets = [];
}

try {
    // Get recent success stories
    $success_stories = DBHelper::select("
        SELECT 
            a.adoption_id,
            a.adoption_date,
            p.pet_name,
            COALESCE(pi.image_path, p.primary_image) as pet_image,
            u.first_name,
            u.last_name,
            s.shelter_name,
            pc.category_name
        FROM adoptions a
        INNER JOIN pets p ON a.pet_id = p.pet_id
        INNER JOIN users u ON a.adopter_id = u.user_id
        INNER JOIN shelters s ON a.shelter_id = s.shelter_id
        INNER JOIN pet_categories pc ON p.category_id = pc.category_id
        LEFT JOIN (
            SELECT pet_id, image_path 
            FROM pet_images 
            WHERE is_primary = 1 
            GROUP BY pet_id
        ) pi ON p.pet_id = pi.pet_id
        ORDER BY a.adoption_date DESC
        LIMIT 6
    ");
} catch (Exception $e) {
    error_log("Error fetching success stories: " . $e->getMessage());
    $success_stories = [];
}

try {
    // Get latest care guides
    $latest_guides = DBHelper::select("
        SELECT 
            cg.guide_id,
            cg.title,
            cg.content,
            cg.difficulty_level,
            cg.created_at,
            pc.category_name,
            u.first_name,
            u.last_name
        FROM care_guides cg
        INNER JOIN pet_categories pc ON cg.category_id = pc.category_id
        INNER JOIN users u ON cg.author_id = u.user_id
        WHERE cg.is_published = 1
        ORDER BY cg.created_at DESC
        LIMIT 4
    ");
} catch (Exception $e) {
    error_log("Error fetching care guides: " . $e->getMessage());
    $latest_guides = [];
}

// Ensure arrays if queries failed
$featured_pets = $featured_pets ?: [];
$success_stories = $success_stories ?: [];
$latest_guides = $latest_guides ?: [];

// Helper function to get proper image URL
function getPetImageUrl($imagePath) {
    // Default placeholder
    $placeholder = 'assets/images/pet-placeholder.jpg';
    
    if (empty($imagePath)) {
        return $placeholder;
    }
    
    // Clean the image path
    $imagePath = trim($imagePath);
    
    // Check if it's already a full path
    if (strpos($imagePath, 'uploads/') === 0) {
        $fullPath = $imagePath;
    } else {
        $fullPath = 'uploads/pets/' . $imagePath;
    }
    
    // Check if file exists
    if (file_exists($fullPath)) {
        return $fullPath;
    }
    
    // Check alternative path
    $altPath = 'uploads/pets/' . basename($imagePath);
    if (file_exists($altPath)) {
        return $altPath;
    }
    
    return $placeholder;
}

include 'common/header.php';
?>

<style>
/* Enhanced Homepage Styles */
:root {
    /* Color Palette */
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #818cf8;
    --secondary: #ec4899;
    --secondary-dark: #db2777;
    --accent: #fbbf24;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #3b82f6;

    /* Gradients */
    --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    --gradient-dark: linear-gradient(135deg, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.4) 100%);
    --gradient-light: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);

    /* Spacing */
    --section-padding: 100px 0;
    --card-padding: 30px;

    /* Shadows */
    --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07);
    --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.15);
    --shadow-2xl: 0 25px 50px rgba(0, 0, 0, 0.25);

    /* Animations */
    --transition-fast: 150ms ease;
    --transition-base: 300ms ease;
    --transition-slow: 500ms ease;
}

/* Reset and base styles */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Hero Section with Image Background */
.hero-section {
    position: relative;
    height: 100vh;
    min-height: 600px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: url('assets/images/paw1.jpg') center center/cover no-repeat;
    background-attachment: fixed;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--gradient-dark);
    opacity: 0.7;
    z-index: 1;
}

.hero-content {
    position: relative;
    z-index: 2;
    max-width: 900px;
    margin: 0 auto;
    padding: 0 20px;
    text-align: center;
    color: white;
}

.hero-badge {
    display: inline-block;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 8px 20px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 30px;
    backdrop-filter: blur(10px);
    animation: fadeInDown 0.8s ease;
}

.hero-title {
    font-size: clamp(2.5rem, 7vw, 4.5rem);
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 25px;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    animation: fadeInUp 0.8s ease 0.2s both;
}

.hero-subtitle {
    font-size: clamp(1.1rem, 2.5vw, 1.5rem);
    font-weight: 300;
    line-height: 1.6;
    margin-bottom: 40px;
    opacity: 0.9;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
    animation: fadeInUp 0.8s ease 0.4s both;
}

.hero-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
    animation: fadeInUp 0.8s ease 0.6s both;
}

.hero-btn {
    position: relative;
    padding: 18px 40px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all var(--transition-base);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    overflow: hidden;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    cursor: pointer;
}

.hero-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(-100%);
    transition: transform var(--transition-base);
}

.hero-btn:hover::before {
    transform: translateX(0);
}

.hero-btn-primary {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.hero-btn-primary:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
    color: white;
    text-decoration: none;
}

.hero-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(10px);
}

.hero-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-3px);
    color: white;
    text-decoration: none;
}

.hero-scroll {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    animation: bounce 2s infinite;
    z-index: 2;
}

.hero-scroll a {
    color: white;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    opacity: 0.7;
    transition: opacity var(--transition-base);
}

.hero-scroll a:hover {
    opacity: 1;
    text-decoration: none;
    color: white;
}

/* Statistics Section */
.stats-section {
    background: white;
    padding: 80px 0;
    position: relative;
    overflow: hidden;
    margin-top: -100px;
    padding-top: 120px;
}

.stats-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 100px;
    background: white;
    transform: skewY(-2deg);
    transform-origin: top left;
}

.stats-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 40px;
    position: relative;
    z-index: 2;
}

.stat-card {
    text-align: center;
    padding: 40px 30px;
    background: linear-gradient(145deg, #ffffff, #f8f9fa);
    border-radius: 20px;
    box-shadow: var(--shadow-lg);
    transition: all var(--transition-base);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: var(--gradient-primary);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform var(--transition-base);
}

.stat-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-2xl);
}

.stat-card:hover::before {
    transform: scaleX(1);
}

.stat-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: var(--gradient-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: white;
    position: relative;
}

.stat-icon::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background: var(--gradient-primary);
    border-radius: 50%;
    opacity: 0.3;
    animation: pulse 2s infinite;
}

.stat-number {
    font-size: 3rem;
    font-weight: 800;
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 10px;
    display: block;
}

.stat-label {
    color: #6b7280;
    font-weight: 600;
    font-size: 1.1rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Section Styles */
.section {
    padding: var(--section-padding);
    position: relative;
}

.section-header {
    text-align: center;
    max-width: 800px;
    margin: 0 auto 60px;
}

.section-badge {
    display: inline-block;
    background: var(--gradient-primary);
    color: white;
    padding: 8px 20px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 20px;
}

.section-title {
    font-size: clamp(2rem, 5vw, 3rem);
    font-weight: 800;
    color: #111827;
    margin-bottom: 20px;
    line-height: 1.2;
}

.section-subtitle {
    font-size: 1.25rem;
    color: #6b7280;
    line-height: 1.6;
}

/* Featured Pets Section */
.featured-pets {
    background: #f9fafb;
}

.pets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.pet-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    transition: all var(--transition-base);
    position: relative;
    cursor: pointer;
}

.pet-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-2xl);
}

.pet-image {
    height: 280px;
    position: relative;
    overflow: hidden;
    background: #f3f4f6;
}

.pet-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform var(--transition-slow);
    display: block;
}

.pet-card:hover .pet-image img {
    transform: scale(1.05);
}

.pet-image-placeholder {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #9ca3af;
    font-size: 4rem;
}

/* Login overlay for pet images */
.login-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all var(--transition-base);
    z-index: 10;
}

.pet-card:hover .login-overlay {
    opacity: 1;
    visibility: visible;
}

.login-overlay-content {
    text-align: center;
    color: white;
    padding: 20px;
}

.login-overlay-icon {
    font-size: 3rem;
    margin-bottom: 15px;
    animation: pulse 2s infinite;
}

.login-overlay-text {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 15px;
}

.login-overlay-link {
    background: white;
    color: var(--primary);
    padding: 10px 25px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    transition: all var(--transition-base);
    display: inline-block;
}

.login-overlay-link:hover {
    background: var(--primary);
    color: white;
    transform: scale(1.05);
    text-decoration: none;
}

.pet-badges {
    position: absolute;
    top: 15px;
    left: 15px;
    right: 15px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    z-index: 2;
}

.pet-category {
    background: var(--gradient-primary);
    color: white;
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}

.pet-status {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.pet-status.new {
    color: var(--success);
}

.pet-info {
    padding: 30px;
}

.pet-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.pet-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.pet-age {
    background: #f3f4f6;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #6b7280;
}

.pet-details {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.pet-detail {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #6b7280;
    font-size: 0.95rem;
}

.pet-detail i {
    color: var(--primary);
}

.pet-description {
    color: #4b5563;
    line-height: 1.6;
    margin-bottom: 25px;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pet-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 25px;
    border-top: 1px solid #e5e7eb;
}

.pet-shelter {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6b7280;
    font-size: 0.9rem;
}

.pet-action {
    background: var(--gradient-primary);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    transition: all var(--transition-base);
    display: flex;
    align-items: center;
    gap: 8px;
}

.pet-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
    color: white;
    text-decoration: none;
}

/* Blur effect for non-logged in users */
.blur-content {
    filter: blur(3px);
    user-select: none;
    pointer-events: none;
}

/* Empty state styles */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 20px;
}

.empty-state i {
    font-size: 5rem;
    color: #e5e7eb;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #6b7280;
    font-size: 1.5rem;
    margin-bottom: 10px;
}

.empty-state p {
    color: #9ca3af;
}

/* Success Stories Section */
.success-stories {
    background: white;
    position: relative;
}

.stories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.story-card {
    background: linear-gradient(145deg, #ffffff, #f8f9fa);
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.story-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.story-card::before {
    content: '"';
    position: absolute;
    top: 20px;
    left: 20px;
    font-size: 6rem;
    color: #6366f1;
    opacity: 0.1;
    font-family: serif;
    line-height: 1;
}

.story-content {
    position: relative;
    z-index: 1;
}

.story-text {
    font-size: 1.25rem;
    color: #374151;
    line-height: 1.8;
    margin-bottom: 30px;
    font-style: italic;
}

.story-author {
    display: flex;
    align-items: center;
    gap: 20px;
}

.story-avatar {
    width: 60px;
    height: 60px;
    min-width: 60px;
    min-height: 60px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #6366f1;
    position: relative;
    flex-shrink: 0;
    background: linear-gradient(135deg, #6366f1, #ec4899);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.story-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.story-info h4 {
    font-size: 1.1rem;
    color: #111827;
    margin-bottom: 5px;
    font-weight: 600;
}

.story-info p {
    color: #6b7280;
    font-size: 0.9rem;
}

/* Care Guides Section */
.care-guides {
    background: #f9fafb;
}

.guides-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.guide-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    transition: all var(--transition-base);
    position: relative;
}

.guide-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-2xl);
}

.guide-image {
    height: 200px;
    background: var(--gradient-primary);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.guide-icon {
    font-size: 4rem;
    color: white;
    opacity: 0.3;
}

.guide-category {
    position: absolute;
    top: 15px;
    left: 15px;
    background: rgba(255, 255, 255, 0.9);
    color: var(--primary);
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.guide-content {
    padding: 30px;
}

.guide-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 15px;
    line-height: 1.3;
}

.guide-excerpt {
    color: #4b5563;
    line-height: 1.6;
    margin-bottom: 20px;
}

.guide-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.guide-author {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #6b7280;
    font-size: 0.9rem;
}

.guide-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: gap var(--transition-base);
}

.guide-link:hover {
    gap: 10px;
    text-decoration: none;
}

/* How It Works Section */
.how-it-works {
    background: white;
}

.process-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 40px;
    max-width: 1200px;
    margin: 60px auto 0;
}

.process-step {
    text-align: center;
    position: relative;
    padding: 40px 30px;
    background: white;
    border-radius: 20px;
    box-shadow: var(--shadow-lg);
    transition: all var(--transition-base);
}

.process-step:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-2xl);
}

.step-number {
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--gradient-primary);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
}

.step-icon {
    width: 80px;
    height: 80px;
    margin: 20px auto;
    background: rgba(99, 102, 241, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--primary);
}

.process-step h3 {
    font-size: 1.3rem;
    color: #111827;
    margin-bottom: 15px;
    font-weight: 700;
}

.process-step p {
    color: #6b7280;
    line-height: 1.6;
}

/* CTA Section */
.cta-section {
    background: var(--gradient-primary);
    padding: 100px 0;
    position: relative;
    overflow: hidden;
}

.cta-section::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: repeating-linear-gradient(45deg,
            transparent,
            transparent 10px,
            rgba(255, 255, 255, 0.05) 10px,
            rgba(255, 255, 255, 0.05) 20px);
    animation: slide 20s linear infinite;
}

.cta-content {
    position: relative;
    z-index: 1;
    max-width: 800px;
    margin: 0 auto;
    text-align: center;
    color: white;
    padding: 0 20px;
}

.cta-title {
    font-size: clamp(2rem, 5vw, 3rem);
    font-weight: 800;
    margin-bottom: 20px;
}

.cta-text {
    font-size: 1.25rem;
    margin-bottom: 40px;
    opacity: 0.95;
    line-height: 1.6;
}

/* Container */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Animations */
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

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes bounce {

    0%,
    20%,
    50%,
    80%,
    100% {
        transform: translateY(0) translateX(-50%);
    }

    40% {
        transform: translateY(-15px) translateX(-50%);
    }

    60% {
        transform: translateY(-10px) translateX(-50%);
    }
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 0.3;
    }

    50% {
        transform: scale(1.1);
        opacity: 0.1;
    }

    100% {
        transform: scale(1.2);
        opacity: 0;
    }
}

@keyframes slide {
    0% {
        transform: translate(0, 0);
    }

    100% {
        transform: translate(50px, 50px);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-section {
        background-attachment: scroll;
    }

    .hero-title {
        font-size: 2.5rem;
    }

    .hero-subtitle {
        font-size: 1.1rem;
    }

    .hero-buttons {
        flex-direction: column;
        width: 100%;
        max-width: 300px;
        margin: 0 auto;
    }

    .hero-btn {
        width: 100%;
        justify-content: center;
    }

    .section {
        padding: 60px 0;
    }

    .stats-container,
    .pets-grid,
    .guides-grid,
    .stories-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .stat-card {
        padding: 30px 20px;
    }

    .stat-number {
        font-size: 2.5rem;
    }

    .story-card {
        padding: 30px;
    }

    .story-card::before {
        font-size: 4rem;
    }

    .story-text {
        font-size: 1.1rem;
    }
}

/* Loading states */
.loading {
    opacity: 0.7;
    pointer-events: none;
}

/* Image loading indicator */
.pet-image.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 40px;
    height: 40px;
    border: 3px solid #f3f4f6;
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: translate(-50%, -50%) rotate(0deg);
    }

    100% {
        transform: translate(-50%, -50%) rotate(360deg);
    }
}
</style>

<!-- Hero Section with Image Background -->
<section class="hero-section">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-heart"></i> Save a Life Today
        </div>
        <h1 class="hero-title">Find Your Perfect Companion</h1>
        <p class="hero-subtitle">
            Every pet deserves a loving home. Join thousands of families who have found
            their best friends through our platform. Your new family member is waiting for you.
        </p>
        <div class="hero-buttons">
            <?php if ($isLoggedIn): ?>
            <a href="adopter/browsePets.php" class="hero-btn hero-btn-primary">
                <i class="fas fa-paw"></i>
                Browse Pets
            </a>
            <?php else: ?>
            <a href="auth/login.php" class="hero-btn hero-btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                Login to Browse
            </a>
            <?php endif; ?>
            <a href="#how-it-works" class="hero-btn hero-btn-secondary">
                <i class="fas fa-info-circle"></i>
                Learn More
            </a>
        </div>
    </div>

    <div class="hero-scroll">
        <a href="#stats">
            <span>Scroll to explore</span>
            <i class="fas fa-chevron-down"></i>
        </a>
    </div>
</section>

<!-- Statistics Section -->
<section class="stats-section" id="stats">
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-paw"></i>
            </div>
            <span class="stat-number" data-count="<?php echo intval($stats['pets_available']); ?>">0</span>
            <span class="stat-label">Pets Available</span>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-heart"></i>
            </div>
            <span class="stat-number" data-count="<?php echo intval($stats['successful_adoptions']); ?>">0</span>
            <span class="stat-label">Happy Adoptions</span>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-home"></i>
            </div>
            <span class="stat-number" data-count="<?php echo intval($stats['shelters']); ?>">0</span>
            <span class="stat-label">Partner Shelters</span>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <span class="stat-number" data-count="<?php echo intval($stats['happy_families']); ?>">0</span>
            <span class="stat-label">Happy Families</span>
        </div>
    </div>
</section>

<!-- Featured Pets Section -->
<section class="section featured-pets">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">Available for Adoption</span>
            <h2 class="section-title">Meet Our Featured Pets</h2>
            <p class="section-subtitle">
                These adorable friends are looking for their forever homes.
                Could you be their perfect match?
            </p>
        </div>

        <div class="pets-grid">
            <?php if (empty($featured_pets)): ?>
            <div class="empty-state">
                <i class="fas fa-paw"></i>
                <h3>No pets available at the moment</h3>
                <p>Check back soon for new arrivals!</p>
            </div>
            <?php else: ?>
            <?php foreach ($featured_pets as $pet): ?>
            <div class="pet-card"
                <?php echo $isLoggedIn ? 'onclick="window.location.href=\'adopter/petDetails.php?id=' . intval($pet['pet_id']) . '\'"' : ''; ?>>
                <div class="pet-image">
                    <?php 
                            $imageUrl = getPetImageUrl($pet['pet_image']);
                            ?>
                    <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                        alt="<?php echo htmlspecialchars($pet['pet_name']); ?>" loading="lazy"
                        onerror="this.src='assets/images/pet-placeholder.jpg';"
                        <?php echo !$isLoggedIn ? 'class="blur-content"' : ''; ?>>

                    <?php if (!$isLoggedIn): ?>
                    <div class="login-overlay">
                        <div class="login-overlay-content">
                            <i class="fas fa-lock login-overlay-icon"></i>
                            <p class="login-overlay-text">Login to see pet details</p>
                            <a href="auth/login.php" class="login-overlay-link" onclick="event.stopPropagation();">
                                Login Now
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="pet-badges">
                        <span
                            class="pet-category"><?php echo htmlspecialchars($pet['category_name'] ?? 'Pet'); ?></span>
                        <?php if (isset($pet['days_waiting']) && $pet['days_waiting'] < 7): ?>
                        <span class="pet-status new">
                            <i class="fas fa-sparkles"></i> New
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pet-info <?php echo !$isLoggedIn ? 'blur-content' : ''; ?>">
                    <div class="pet-header">
                        <h3 class="pet-name"><?php echo htmlspecialchars($pet['pet_name']); ?></h3>
                        <span class="pet-age"><?php echo intval($pet['age'] ?? 0); ?> years</span>
                    </div>

                    <div class="pet-details">
                        <span class="pet-detail">
                            <i class="fas fa-venus-mars"></i>
                            <?php echo ucfirst($pet['gender'] ?? 'Unknown'); ?>
                        </span>
                        <span class="pet-detail">
                            <i class="fas fa-dog"></i>
                            <?php echo htmlspecialchars($pet['breed_name'] ?: 'Mixed'); ?>
                        </span>
                        <span class="pet-detail">
                            <i class="fas fa-weight"></i>
                            <?php echo ucfirst($pet['size'] ?: 'Medium'); ?>
                        </span>
                    </div>

                    <p class="pet-description">
                        <?php 
                                $description = !empty($pet['description']) ? $pet['description'] : 'A wonderful pet looking for a loving home!';
                                echo htmlspecialchars(substr($description, 0, 150)) . (strlen($description) > 150 ? '...' : '');
                                ?>
                    </p>

                    <div class="pet-footer">
                        <div class="pet-shelter">
                            <i class="fas fa-building"></i>
                            <?php echo htmlspecialchars($pet['shelter_name'] ?? 'Pet Shelter'); ?>
                        </div>
                        <?php if ($isLoggedIn): ?>
                        <a href="adopter/petDetails.php?id=<?php echo intval($pet['pet_id']); ?>" class="pet-action"
                            onclick="event.stopPropagation();">
                            Meet <?php echo htmlspecialchars($pet['pet_name']); ?>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <?php else: ?>
                        <a href="auth/login.php" class="pet-action" onclick="event.stopPropagation();">
                            Login to Adopt
                            <i class="fas fa-lock"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($featured_pets)): ?>
        <div style="text-align: center; margin-top: 60px;">
            <?php if ($isLoggedIn): ?>
            <a href="adopter/browsePets.php" class="hero-btn hero-btn-primary">
                <i class="fas fa-search"></i>
                View All Available Pets
            </a>
            <?php else: ?>
            <a href="auth/login.php" class="hero-btn hero-btn-primary">
                <i class="fas fa-lock"></i>
                Login to View All Pets
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Success Stories Section -->
<?php if (!empty($success_stories)): ?>
<section class="section success-stories">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">Happy Endings</span>
            <h2 class="section-title">Success Stories</h2>
            <p class="section-subtitle">
                Real stories from families who found their perfect companions through our platform
            </p>
        </div>

        <div class="stories-grid">
            <?php 
            $testimonials = [
                "has brought so much joy to our family. We can't imagine life without our furry friend!",
                "was exactly what our family needed. Thank you for helping us find our perfect match!",
                "has filled our home with love and laughter. Best decision we ever made!",
                "is the most loving companion. We're so grateful for this platform!",
                "completed our family. The adoption process was smooth and wonderful!",
                "brought happiness we didn't know we were missing. Thank you for everything!"
            ];
            ?>

            <?php foreach (array_slice($success_stories, 0, 3) as $index => $story): ?>
            <div class="story-card">
                <div class="story-content">
                    <p class="story-text">
                        "<?php echo htmlspecialchars($story['pet_name']) . ' ' . $testimonials[$index % count($testimonials)]; ?>"
                    </p>

                    <div class="story-author">
                        <?php 
                            $storyImageUrl = getPetImageUrl($story['pet_image'] ?? '');
                            $hasValidImage = !empty($story['pet_image']);
                            ?>

                        <div class="story-avatar">
                            <?php if ($hasValidImage): ?>
                            <img src="<?php echo htmlspecialchars($storyImageUrl); ?>"
                                alt="<?php echo htmlspecialchars($story['pet_name']); ?>" loading="lazy"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <i class="fas fa-paw" style="display:none;"></i>
                            <?php else: ?>
                            <i class="fas fa-paw"></i>
                            <?php endif; ?>
                        </div>

                        <div class="story-info">
                            <h4><?php echo htmlspecialchars(($story['first_name'] ?? '') . ' ' . ($story['last_name'] ?? '')); ?>
                            </h4>
                            <p>Adopted <?php echo htmlspecialchars($story['pet_name']); ?> •
                                <?php echo date('F Y', strtotime($story['adoption_date'] ?? 'now')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Care Guides Section -->
<?php if (!empty($latest_guides)): ?>
<section class="section care-guides">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">Knowledge Hub</span>
            <h2 class="section-title">Pet Care Guides</h2>
            <p class="section-subtitle">
                Expert advice and tips to help you provide the best care for your furry friends
            </p>
        </div>

        <div class="guides-grid">
            <?php 
            $guide_icons = [
                'Dog' => 'fa-dog',
                'Cat' => 'fa-cat',
                'Bird' => 'fa-dove',
                'Rabbit' => 'fa-rabbit',
                'Fish' => 'fa-fish',
                'Other' => 'fa-paw'
            ];
            ?>

            <?php foreach ($latest_guides as $guide): ?>
            <div class="guide-card">
                <div class="guide-image">
                    <i
                        class="fas <?php echo $guide_icons[$guide['category_name'] ?? 'Other'] ?? 'fa-paw'; ?> guide-icon"></i>
                    <span class="guide-category"><?php echo htmlspecialchars($guide['category_name'] ?? 'Pet'); ?>
                        Care</span>
                </div>

                <div class="guide-content">
                    <h3 class="guide-title"><?php echo htmlspecialchars($guide['title'] ?? 'Care Guide'); ?></h3>
                    <p class="guide-excerpt">
                        <?php 
                            $content = $guide['content'] ?? 'Learn how to take care of your pet with expert advice and tips.';
                            echo htmlspecialchars(substr(strip_tags($content), 0, 150)) . '...'; 
                            ?>
                    </p>

                    <div class="guide-footer">
                        <span class="guide-author">
                            <i class="fas fa-user-circle"></i>
                            <?php 
                                $firstName = $guide['first_name'] ?? 'Expert';
                                $lastName = $guide['last_name'] ?? 'Author';
                                echo htmlspecialchars($firstName . ' ' . substr($lastName, 0, 1) . '.'); 
                                ?>
                        </span>
                        <a href="adopter/careGuides.php?id=<?php echo intval($guide['guide_id'] ?? 0); ?>"
                            class="guide-link">
                            Read More <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 60px;">
            <a href="adopter/careGuides.php" class="hero-btn hero-btn-primary">
                <i class="fas fa-book"></i>
                Explore All Guides
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- How It Works Section -->
<section class="section how-it-works" id="how-it-works">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">Simple Process</span>
            <h2 class="section-title">How Pet Adoption Works</h2>
            <p class="section-subtitle">
                We've made the adoption process simple, transparent, and stress-free
            </p>
        </div>

        <div class="process-grid">
            <div class="process-step">
                <div class="step-number">01</div>
                <div class="step-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>Browse Pets</h3>
                <p>Search through our database of adorable pets waiting for homes. Use filters to find your perfect
                    match.</p>
            </div>

            <div class="process-step">
                <div class="step-number">02</div>
                <div class="step-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <h3>Fall in Love</h3>
                <p>Read pet profiles, view photos, and learn about their personalities to find the one that steals your
                    heart.</p>
            </div>

            <div class="process-step">
                <div class="step-number">03</div>
                <div class="step-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3>Apply to Adopt</h3>
                <p>Submit an adoption application. Our shelters will review it to ensure a great match for both you and
                    the pet.</p>
            </div>

            <div class="process-step">
                <div class="step-number">04</div>
                <div class="step-icon">
                    <i class="fas fa-home"></i>
                </div>
                <h3>Welcome Home</h3>
                <p>Once approved, bring your new family member home and start your journey together!</p>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="cta-section">
    <div class="cta-content">
        <h2 class="cta-title">Ready to Find Your New Best Friend?</h2>
        <p class="cta-text">
            Join thousands of happy families who have found their perfect companions through our platform.
            Your new family member is just a click away!
        </p>

        <div class="hero-buttons">
            <?php if (!$isLoggedIn): ?>
            <a href="auth/register.php?type=adopter" class="hero-btn hero-btn-primary">
                <i class="fas fa-user-plus"></i>
                Create Account
            </a>
            <a href="auth/register.php?type=shelter" class="hero-btn hero-btn-secondary">
                <i class="fas fa-building"></i>
                Register as Shelter
            </a>
            <?php else: ?>
            <a href="adopter/browsePets.php" class="hero-btn hero-btn-primary">
                <i class="fas fa-paw"></i>
                Find Your Pet
            </a>
            <a href="adopter/dashboard.php" class="hero-btn hero-btn-secondary">
                <i class="fas fa-tachometer-alt"></i>
                My Dashboard
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
// Homepage JavaScript Enhancement
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all animations and interactions
    initCounters();
    initScrollAnimations();
    initPetCards();
    initLazyLoading();
    initLoginOverlays();
});

// Animated Counters
function initCounters() {
    const counters = document.querySelectorAll('.stat-number');
    const observerOptions = {
        threshold: 0.7,
        rootMargin: '0px 0px -50px 0px'
    };

    const counterObserver = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.classList.contains('counted')) {
                const counter = entry.target;
                const target = parseInt(counter.getAttribute('data-count')) || 0;
                const duration = 2000; // 2 seconds
                const increment = target / (duration / 16); // 60 FPS
                let current = 0;

                counter.classList.add('counted');

                const updateCounter = () => {
                    current += increment;
                    if (current < target) {
                        counter.textContent = Math.floor(current).toLocaleString();
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target.toLocaleString();
                    }
                };

                updateCounter();
            }
        });
    }, observerOptions);

    counters.forEach(counter => counterObserver.observe(counter));
}

// Scroll-triggered Animations
function initScrollAnimations() {
    const animatedElements = document.querySelectorAll('.pet-card, .story-card, .guide-card, .process-step');

    const scrollObserver = new IntersectionObserver(function(entries) {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting && !entry.target.classList.contains('animated')) {
                entry.target.classList.add('animated');
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    });

    animatedElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
        element.style.transition = 'all 0.6s ease';
        scrollObserver.observe(element);
    });
}

// Pet Card Interactions
function initPetCards() {
    const petCards = document.querySelectorAll('.pet-card');
    const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;

    petCards.forEach(card => {
        // Only add hover effects if user is logged in
        if (isLoggedIn) {
            card.addEventListener('mouseenter', function() {
                const img = this.querySelector('.pet-image img');
                if (img && !img.classList.contains('error')) {
                    img.style.transform = 'scale(1.05)';
                }
            });

            card.addEventListener('mouseleave', function() {
                const img = this.querySelector('.pet-image img');
                if (img && !img.classList.contains('error')) {
                    img.style.transform = 'scale(1)';
                }
            });
        } else {
            // Add cursor style for non-logged in users
            card.style.cursor = 'default';
        }
    });
}

// Login Overlay Interactions
function initLoginOverlays() {
    const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;

    if (!isLoggedIn) {
        // Prevent clicks on pet cards for non-logged in users
        document.querySelectorAll('.pet-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (!isLoggedIn) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });
    }
}

// Lazy Loading for Images
function initLazyLoading() {
    const images = document.querySelectorAll('img[loading="lazy"]');

    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.classList.add('loaded');
                    imageObserver.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px 0px'
        });

        images.forEach(img => imageObserver.observe(img));
    }
}

// Smooth Scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        const target = document.querySelector(targetId);

        if (target) {
            const headerHeight = 70;
            const targetPosition = target.offsetTop - headerHeight - 20;

            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
        }
    });
});

// Handle button loading states
document.querySelectorAll('.hero-btn, .pet-action').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (this.classList.contains('loading')) {
            e.preventDefault();
            return;
        }

        // Don't add loading state for hash links
        if (this.getAttribute('href').startsWith('#')) {
            return;
        }

        this.classList.add('loading');
        const originalContent = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

        // Reset after a timeout (in case navigation fails)
        setTimeout(() => {
            this.classList.remove('loading');
            this.innerHTML = originalContent;
        }, 3000);
    });
});

// Add parallax effect to hero section
let ticking = false;

function updateParallax() {
    const scrolled = window.pageYOffset;
    const heroSection = document.querySelector('.hero-section');

    if (heroSection && scrolled < window.innerHeight) {
        heroSection.style.transform = `translateY(${scrolled * 0.5}px)`;
    }

    ticking = false;
}

function requestTick() {
    if (!ticking) {
        window.requestAnimationFrame(updateParallax);
        ticking = true;
    }
}

window.addEventListener('scroll', requestTick);

// Console log for debugging
console.log('Homepage loaded successfully');
console.log('User logged in:', <?php echo json_encode($isLoggedIn); ?>);
console.log('Featured pets:', <?php echo count($featured_pets); ?>);
console.log('Success stories:', <?php echo count($success_stories); ?>);
console.log('Care guides:', <?php echo count($latest_guides); ?>);
</script>

<?php include 'common/footer.php'; ?>