<?php
// index.php - Homepage
require_once 'config/db.php';

// Page variables for header
$page_title = "Home";
$page_description = "Find your perfect companion at Pet Adoption Care Guide. Browse adoptable pets from trusted shelters and give a pet a loving forever home.";

// Get statistics for homepage
$total_pets_available = DBHelper::count('pets', ['status' => 'available']);
$total_successful_adoptions = DBHelper::count('adoptions');
$total_shelters = DBHelper::count('shelters');
$total_happy_families = DBHelper::count('adoptions'); // Same as successful adoptions

// Get featured pets (latest 8 available pets)
$featured_pets = DBHelper::select("
    SELECT p.*, pc.category_name, pb.breed_name, s.shelter_name 
    FROM pets p 
    LEFT JOIN pet_categories pc ON p.category_id = pc.category_id 
    LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id 
    LEFT JOIN shelters s ON p.shelter_id = s.shelter_id 
    WHERE p.status = 'available' 
    ORDER BY p.created_at DESC 
    LIMIT 8
");

// Get recent success stories (recent adoptions)
$success_stories = DBHelper::select("
    SELECT a.*, p.pet_name, p.primary_image, u.first_name, u.last_name,
           s.shelter_name, pc.category_name
    FROM adoptions a
    JOIN pets p ON a.pet_id = p.pet_id
    JOIN users u ON a.adopter_id = u.user_id
    JOIN shelters s ON a.shelter_id = s.shelter_id
    JOIN pet_categories pc ON p.category_id = pc.category_id
    ORDER BY a.adoption_date DESC
    LIMIT 6
");

// Get latest care guides
$latest_guides = DBHelper::select("
    SELECT cg.*, pc.category_name, u.first_name, u.last_name
    FROM care_guides cg
    JOIN pet_categories pc ON cg.category_id = pc.category_id
    JOIN users u ON cg.author_id = u.user_id
    WHERE cg.is_published = 1
    ORDER BY cg.created_at DESC
    LIMIT 4
");

include 'common/header.php';
?>

<style>
/* Homepage Specific Styles */
.hero-section {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9)),
        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><defs><pattern id="pets" patternUnits="userSpaceOnUse" width="100" height="100"><circle cx="25" cy="25" r="2" fill="white" opacity="0.1"/><path d="M75 75 Q85 65 95 75 Q85 85 75 75" fill="white" opacity="0.05"/></pattern></defs><rect width="100%" height="100%" fill="url(%23pets)"/></svg>');
    background-size: cover;
    background-position: center;
    min-height: 600px;
    display: flex;
    align-items: center;
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.hero-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 20px;
    z-index: 2;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 700;
    margin-bottom: 20px;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    animation: fadeInUp 1s ease-out;
}

.hero-subtitle {
    font-size: 1.3rem;
    margin-bottom: 30px;
    opacity: 0.95;
    line-height: 1.6;
    animation: fadeInUp 1s ease-out 0.3s both;
}

.hero-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
    animation: fadeInUp 1s ease-out 0.6s both;
}

.hero-btn {
    padding: 15px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 180px;
    justify-content: center;
}

.hero-btn-primary {
    background: #ff6b6b;
    color: white;
    box-shadow: 0 8px 25px rgba(255, 107, 107, 0.3);
}

.hero-btn-primary:hover {
    background: #ff5252;
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(255, 107, 107, 0.4);
}

.hero-btn-secondary {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
}

.hero-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.25);
    border-color: white;
    transform: translateY(-3px);
}

.stats-section {
    background: white;
    padding: 60px 0;
    margin-top: -50px;
    position: relative;
    z-index: 3;
}

.stats-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.stat-card {
    text-align: center;
    padding: 30px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}

.stat-icon {
    font-size: 3rem;
    color: #667eea;
    margin-bottom: 15px;
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

.section {
    padding: 80px 0;
}

.section-title {
    text-align: center;
    font-size: 2.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 15px;
}

.section-subtitle {
    text-align: center;
    font-size: 1.2rem;
    color: #6c757d;
    margin-bottom: 50px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.pets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.pet-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    position: relative;
}

.pet-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.pet-image {
    height: 250px;
    background: #f8f9fa;
    position: relative;
    overflow: hidden;
}

.pet-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.pet-card:hover .pet-image img {
    transform: scale(1.1);
}

.pet-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #ff6b6b;
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}

.pet-info {
    padding: 25px;
}

.pet-name {
    font-size: 1.4rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 8px;
}

.pet-details {
    color: #6c757d;
    margin-bottom: 15px;
    font-size: 0.95rem;
}

.pet-description {
    color: #495057;
    line-height: 1.6;
    margin-bottom: 20px;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.pet-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pet-shelter {
    font-size: 0.9rem;
    color: #6c757d;
    display: flex;
    align-items: center;
}

.pet-shelter i {
    margin-right: 5px;
}

.pet-btn {
    background: #667eea;
    color: white;
    padding: 10px 20px;
    border-radius: 20px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.pet-btn:hover {
    background: #5a67d8;
    transform: translateY(-2px);
}

.success-stories {
    background: #f8f9fa;
}

.stories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.story-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    position: relative;
}

.story-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.story-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.story-pet-image {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 15px;
    border: 3px solid #667eea;
}

.story-info h4 {
    color: #2c3e50;
    margin-bottom: 5px;
    font-weight: 600;
}

.story-info p {
    color: #6c757d;
    font-size: 0.9rem;
    margin: 0;
}

.story-quote {
    font-style: italic;
    color: #495057;
    line-height: 1.6;
    position: relative;
    padding-left: 20px;
}

.story-quote::before {
    content: '"';
    position: absolute;
    left: 0;
    top: -10px;
    font-size: 3rem;
    color: #667eea;
    opacity: 0.3;
}

.care-guides-section {
    background: white;
}

.guides-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.guide-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.guide-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.guide-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 20px;
    position: relative;
}

.guide-category {
    font-size: 0.85rem;
    opacity: 0.9;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.guide-title {
    font-size: 1.2rem;
    font-weight: 600;
    line-height: 1.4;
    margin-bottom: 10px;
}

.guide-author {
    font-size: 0.9rem;
    opacity: 0.8;
}

.guide-content {
    padding: 20px;
}

.guide-excerpt {
    color: #6c757d;
    line-height: 1.6;
    margin-bottom: 15px;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.guide-link {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.guide-link:hover {
    color: #5a67d8;
}

.cta-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-align: center;
    padding: 80px 0;
}

.cta-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 20px;
}

.cta-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 20px;
}

.cta-text {
    font-size: 1.2rem;
    margin-bottom: 40px;
    opacity: 0.95;
    line-height: 1.6;
}

.cta-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.cta-btn {
    padding: 15px 30px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.cta-btn-primary {
    background: white;
    color: #667eea;
}

.cta-btn-primary:hover {
    background: #f8f9fa;
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(255, 255, 255, 0.3);
}

.cta-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.5);
}

.cta-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: white;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .hero-title {
        font-size: 2.5rem;
    }

    .hero-subtitle {
        font-size: 1.1rem;
    }

    .hero-buttons {
        flex-direction: column;
        align-items: center;
    }

    .stats-container {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .pets-grid,
    .stories-grid,
    .guides-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .section-title {
        font-size: 2rem;
    }

    .cta-title {
        font-size: 2rem;
    }

    .cta-buttons {
        flex-direction: column;
        align-items: center;
    }
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

@keyframes countUp {
    from {
        opacity: 0;
        transform: scale(0.5);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Loading states */
.pet-card.loading {
    background: #f8f9fa;
    position: relative;
    overflow: hidden;
}

.pet-card.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    animation: loading 1.5s infinite;
}

@keyframes loading {
    100% {
        left: 100%;
    }
}
</style>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-content">
        <h1 class="hero-title">Find Your Perfect Companion</h1>
        <p class="hero-subtitle">
            Connect with loving pets from trusted shelters and give a deserving animal
            a forever home filled with love, care, and happiness.
        </p>
        <div class="hero-buttons">
            <a href="<?php echo BASE_URL; ?>adopter/browsePets.php" class="hero-btn hero-btn-primary">
                <i class="fas fa-search"></i>
                Browse Pets
            </a>
            <a href="<?php echo BASE_URL; ?>auth/register.php?type=shelter" class="hero-btn hero-btn-secondary">
                <i class="fas fa-home"></i>
                Register Shelter
            </a>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="stats-section">
    <div class="stats-container">
        <div class="stat-card">
            <i class="fas fa-paw stat-icon"></i>
            <span class="stat-number" data-count="<?php echo $total_pets_available; ?>">0</span>
            <span class="stat-label">Pets Available</span>
        </div>
        <div class="stat-card">
            <i class="fas fa-heart stat-icon"></i>
            <span class="stat-number" data-count="<?php echo $total_successful_adoptions; ?>">0</span>
            <span class="stat-label">Successful Adoptions</span>
        </div>
        <div class="stat-card">
            <i class="fas fa-home stat-icon"></i>
            <span class="stat-number" data-count="<?php echo $total_shelters; ?>">0</span>
            <span class="stat-label">Partner Shelters</span>
        </div>
        <div class="stat-card">
            <i class="fas fa-smile stat-icon"></i>
            <span class="stat-number" data-count="<?php echo $total_happy_families; ?>">0</span>
            <span class="stat-label">Happy Families</span>
        </div>
    </div>
</section>

<!-- Featured Pets Section -->
<section class="section featured-pets">
    <div class="container">
        <h2 class="section-title">Featured Pets</h2>
        <p class="section-subtitle">
            Meet some of our adorable pets who are looking for their forever homes
        </p>

        <div class="pets-grid">
            <?php if (empty($featured_pets)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 50px;">
                <i class="fas fa-paw" style="font-size: 4rem; color: #e9ecef; margin-bottom: 20px;"></i>
                <h3 style="color: #6c757d;">No pets available right now</h3>
                <p style="color: #6c757d;">Check back soon for new arrivals!</p>
            </div>
            <?php else: ?>
            <?php foreach ($featured_pets as $pet): ?>
            <div class="pet-card">
                <div class="pet-image">
                    <?php if ($pet['primary_image']): ?>
                    <img src="<?php echo BASE_URL; ?>uploads/pets/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                        alt="<?php echo htmlspecialchars($pet['pet_name']); ?>"
                        onerror="this.src='<?php echo BASE_URL; ?>assets/images/pet-placeholder.jpg'">
                    <?php else: ?>
                    <img src="<?php echo BASE_URL; ?>assets/images/pet-placeholder.jpg"
                        alt="<?php echo htmlspecialchars($pet['pet_name']); ?>">
                    <?php endif; ?>
                    <div class="pet-badge"><?php echo htmlspecialchars($pet['category_name']); ?></div>
                </div>
                <div class="pet-info">
                    <h3 class="pet-name"><?php echo htmlspecialchars($pet['pet_name']); ?></h3>
                    <div class="pet-details">
                        <?php echo htmlspecialchars($pet['breed_name'] ?? 'Mixed Breed'); ?> •
                        <?php echo htmlspecialchars($pet['age']); ?> years old •
                        <?php echo ucfirst(htmlspecialchars($pet['gender'])); ?> •
                        <?php echo ucfirst(htmlspecialchars($pet['size'] ?? 'Medium')); ?>
                    </div>
                    <p class="pet-description">
                        <?php echo htmlspecialchars(substr($pet['description'], 0, 150)) . (strlen($pet['description']) > 150 ? '...' : ''); ?>
                    </p>
                    <div class="pet-footer">
                        <div class="pet-shelter">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($pet['shelter_name']); ?>
                        </div>
                        <a href="<?php echo BASE_URL; ?>adopter/petDetails.php?id=<?php echo $pet['pet_id']; ?>"
                            class="pet-btn">
                            Learn More
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 50px;">
            <a href="<?php echo BASE_URL; ?>adopter/browsePets.php" class="hero-btn hero-btn-primary">
                <i class="fas fa-search"></i>
                View All Pets
            </a>
        </div>
    </div>
</section>

<!-- Success Stories Section -->
<?php if (!empty($success_stories)): ?>
<section class="section success-stories">
    <div class="container">
        <h2 class="section-title">Success Stories</h2>
        <p class="section-subtitle">
            Read heartwarming stories from families who found their perfect companions
        </p>

        <div class="stories-grid">
            <?php foreach (array_slice($success_stories, 0, 3) as $story): ?>
            <div class="story-card">
                <div class="story-header">
                    <?php if ($story['primary_image']): ?>
                    <img src="<?php echo BASE_URL; ?>uploads/pets/<?php echo htmlspecialchars($story['primary_image']); ?>"
                        alt="<?php echo htmlspecialchars($story['pet_name']); ?>" class="story-pet-image"
                        onerror="this.src='<?php echo BASE_URL; ?>assets/images/pet-placeholder.jpg'">
                    <?php else: ?>
                    <img src="<?php echo BASE_URL; ?>assets/images/pet-placeholder.jpg"
                        alt="<?php echo htmlspecialchars($story['pet_name']); ?>" class="story-pet-image">
                    <?php endif; ?>
                    <div class="story-info">
                        <h4><?php echo htmlspecialchars($story['pet_name']); ?> &
                            <?php echo htmlspecialchars($story['first_name']); ?></h4>
                        <p><i class="fas fa-calendar"></i>
                            <?php echo date('F Y', strtotime($story['adoption_date'])); ?></p>
                        <p><i class="fas fa-home"></i> <?php echo htmlspecialchars($story['shelter_name']); ?></p>
                    </div>
                </div>
                <div class="story-quote">
                    <?php 
                    $quotes = [
                        "Adopting {$story['pet_name']} was the best decision we ever made. Our family feels complete now!",
                        "{$story['pet_name']} brought so much joy and love into our home. We can't imagine life without our furry friend!",
                        "From day one, {$story['pet_name']} fit right in with our family. It was meant to be!",
                        "The love and companionship {$story['pet_name']} provides is immeasurable. Thank you for helping us find our perfect match!"
                    ];
                    echo $quotes[array_rand($quotes)];
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Care Guides Section -->
<?php if (!empty($latest_guides)): ?>
<section class="section care-guides-section">
    <div class="container">
        <h2 class="section-title">Pet Care Guides</h2>
        <p class="section-subtitle">
            Learn how to provide the best care for your new companion with our expert guides
        </p>

        <div class="guides-grid">
            <?php foreach ($latest_guides as $guide): ?>
            <div class="guide-card">
                <div class="guide-header">
                    <div class="guide-category"><?php echo htmlspecialchars($guide['category_name']); ?> Care</div>
                    <h3 class="guide-title"><?php echo htmlspecialchars($guide['title']); ?></h3>
                    <div class="guide-author">
                        <i class="fas fa-user"></i>
                        By <?php echo htmlspecialchars($guide['first_name'] . ' ' . $guide['last_name']); ?>
                    </div>
                </div>
                <div class="guide-content">
                    <div class="guide-excerpt">
                        <?php echo htmlspecialchars(substr(strip_tags($guide['content']), 0, 120)) . '...'; ?>
                    </div>
                    <a href="<?php echo BASE_URL; ?>adopter/careGuides.php?id=<?php echo $guide['guide_id']; ?>"
                        class="guide-link">
                        Read More <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 50px;">
            <a href="<?php echo BASE_URL; ?>adopter/careGuides.php" class="hero-btn hero-btn-primary">
                <i class="fas fa-book"></i>
                View All Guides
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Call to Action Section -->
<section class="cta-section">
    <div class="cta-content">
        <h2 class="cta-title">Ready to Make a Difference?</h2>
        <p class="cta-text">
            Every pet deserves a loving home. Whether you're looking to adopt,
            volunteer, or support our mission, you can help us save lives today.
        </p>
        <div class="cta-buttons">
            <?php if (!Session::isLoggedIn()): ?>
            <a href="<?php echo BASE_URL; ?>auth/register.php?type=adopter" class="cta-btn cta-btn-primary">
                <i class="fas fa-heart"></i>
                Start Adopting
            </a>
            <a href="<?php echo BASE_URL; ?>auth/register.php?type=shelter" class="cta-btn cta-btn-secondary">
                <i class="fas fa-home"></i>
                Register Shelter
            </a>
            <?php else: ?>
            <a href="<?php echo BASE_URL; ?>adopter/browsePets.php" class="cta-btn cta-btn-primary">
                <i class="fas fa-search"></i>
                Browse Pets
            </a>
            <a href="<?php echo BASE_URL; ?>contact.php" class="cta-btn cta-btn-secondary">
                <i class="fas fa-envelope"></i>
                Contact Us
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
// Homepage JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Animated counter for statistics
    animateCounters();

    // Lazy loading for pet images
    lazyLoadImages();

    // Auto-refresh featured pets every 5 minutes
    setInterval(refreshFeaturedPets, 300000);
});

function animateCounters() {
    const counters = document.querySelectorAll('.stat-number');
    const options = {
        threshold: 0.5,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                const target = parseInt(counter.getAttribute('data-count'));
                const increment = target / 50;
                let current = 0;

                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target.toLocaleString();
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current).toLocaleString();
                    }
                }, 30);

                observer.unobserve(counter);
            }
        });
    }, options);

    counters.forEach(counter => {
        observer.observe(counter);
    });
}

function lazyLoadImages() {
    const images = document.querySelectorAll('img[data-src]');
    const imageOptions = {
        threshold: 0,
        rootMargin: '50px 0px'
    };

    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    }, imageOptions);

    images.forEach(img => imageObserver.observe(img));
}

function refreshFeaturedPets() {
    // AJAX call to refresh featured pets
    fetch('<?php echo BASE_URL; ?>ajax/featured_pets.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateFeaturedPets(data.pets);
            }
        })
        .catch(error => console.log('Failed to refresh pets:', error));
}

function updateFeaturedPets(pets) {
    const petsGrid = document.querySelector('.pets-grid');
    if (petsGrid && pets.length > 0) {
        // Update pets display with new data
        // Implementation would rebuild the pet cards
    }
}

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

// Add loading states to buttons
document.querySelectorAll('.hero-btn, .pet-btn, .cta-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!this.classList.contains('loading')) {
            const originalText = this.innerHTML;
            this.classList.add('loading');
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

            // Remove loading state after page navigation
            setTimeout(() => {
                this.classList.remove('loading');
                this.innerHTML = originalText;
            }, 2000);
        }
    });
});
</script>

<?php include 'common/footer.php'; ?>