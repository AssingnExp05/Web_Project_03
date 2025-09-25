<?php
// about.php - About Us Page
require_once 'config/db.php';

// Page variables for header
$page_title = "About Us";
$page_description = "Learn about Pet Adoption Care Guide's mission to connect loving families with pets in need. Discover our story, values, and commitment to animal welfare.";

// Get statistics for about page with error handling
try {
    $total_pets_rescued = DBHelper::count('pets') ?: 0;
    $successful_adoptions = DBHelper::count('adoptions') ?: 0;
    $partner_shelters = DBHelper::count('shelters') ?: 0;
    $years_active = date('Y') - 2020; // Assuming platform started in 2020
    
    // Get some recent success stories for testimonials with fallback
    $testimonials = DBHelper::select("
        SELECT a.adoption_date, p.pet_name, p.primary_image, u.first_name, u.last_name,
               s.shelter_name, pc.category_name
        FROM adoptions a
        LEFT JOIN pets p ON a.pet_id = p.pet_id
        LEFT JOIN users u ON a.adopter_id = u.user_id
        LEFT JOIN shelters s ON a.shelter_id = s.shelter_id
        LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
        WHERE p.pet_name IS NOT NULL AND u.first_name IS NOT NULL
        ORDER BY a.adoption_date DESC
        LIMIT 6
    ");
    
    // If no testimonials, create empty array
    if (!$testimonials) {
        $testimonials = [];
    }
} catch (Exception $e) {
    // Fallback values if database queries fail
    $total_pets_rescued = 150;
    $successful_adoptions = 120;
    $partner_shelters = 25;
    $years_active = 4;
    $testimonials = [];
    error_log("About page database error: " . $e->getMessage());
}

include 'common/header.php';
?>

<style>
/* About Page Specific Styles */
.hero-about {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.9), rgba(118, 75, 162, 0.9));
    background-size: cover;
    background-position: center;
    padding: 100px 0;
    color: white;
    text-align: center;
    position: relative;
}

.hero-content {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 20px;
}

.hero-title {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 20px;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}

.hero-subtitle {
    font-size: 1.3rem;
    opacity: 0.95;
    line-height: 1.6;
    margin-bottom: 30px;
}

.mission-section {
    padding: 80px 0;
    background: white;
}

.mission-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.mission-content h2 {
    font-size: 2.5rem;
    color: #2c3e50;
    margin-bottom: 20px;
    font-weight: 700;
}

.mission-content p {
    font-size: 1.1rem;
    line-height: 1.8;
    color: #5a6c7d;
    margin-bottom: 20px;
}

.mission-highlights {
    list-style: none;
    padding: 0;
}

.mission-highlights li {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    font-size: 1.1rem;
    color: #2c3e50;
}

.mission-highlights i {
    color: #667eea;
    font-size: 1.2rem;
    margin-right: 15px;
    width: 20px;
}

.mission-image {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    background: linear-gradient(135deg, #667eea, #764ba2);
    height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-align: center;
}

.mission-image-content {
    padding: 20px;
}

.mission-image h3 {
    font-size: 1.8rem;
    margin-bottom: 10px;
}

.mission-image p {
    font-size: 1rem;
    opacity: 0.9;
}

.stats-about {
    background: #f8f9fa;
    padding: 80px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 40px;
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 20px;
}

.stat-item {
    text-align: center;
    background: white;
    padding: 40px 20px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2);
}

.stat-icon {
    font-size: 2.5rem;
    color: #667eea;
    margin-bottom: 15px;
}

.stat-number {
    font-size: 2.8rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 10px;
    display: block;
}

.stat-label {
    color: #6c757d;
    font-weight: 600;
    font-size: 1rem;
}

.values-section {
    padding: 80px 0;
    background: white;
}

.section-title {
    text-align: center;
    font-size: 2.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 50px;
}

.values-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 40px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.value-card {
    text-align: center;
    padding: 40px 30px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    position: relative;
    border: 2px solid transparent;
}

.value-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    border-color: #667eea;
}

.value-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2rem;
    color: white;
}

.value-card h3 {
    font-size: 1.5rem;
    color: #2c3e50;
    margin-bottom: 15px;
    font-weight: 600;
}

.value-card p {
    color: #6c757d;
    line-height: 1.6;
}

.team-section {
    padding: 80px 0;
    background: #f8f9fa;
}

.team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 40px;
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 20px;
}

.team-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    text-align: center;
}

.team-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.team-image {
    height: 250px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 4rem;
}

.team-info {
    padding: 30px;
}

.team-info h3 {
    color: #2c3e50;
    margin-bottom: 5px;
    font-weight: 600;
}

.team-role {
    color: #667eea;
    font-weight: 500;
    margin-bottom: 15px;
}

.team-info p {
    color: #6c757d;
    line-height: 1.6;
    font-size: 0.95rem;
}

.testimonials-section {
    padding: 80px 0;
    background: white;
}

.testimonials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.testimonial-card {
    background: #f8f9fa;
    border-radius: 20px;
    padding: 30px;
    position: relative;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.testimonial-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
}

.testimonial-card::before {
    content: '"';
    position: absolute;
    top: 15px;
    left: 20px;
    font-size: 4rem;
    color: #667eea;
    opacity: 0.3;
    font-family: Georgia, serif;
}

.testimonial-content {
    margin-bottom: 20px;
    font-style: italic;
    color: #495057;
    line-height: 1.6;
    padding-left: 30px;
}

.testimonial-author {
    display: flex;
    align-items: center;
    gap: 15px;
}

.author-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.2rem;
}

.author-info h4 {
    color: #2c3e50;
    margin-bottom: 5px;
    font-weight: 600;
}

.author-details {
    color: #6c757d;
    font-size: 0.9rem;
}

.cta-about {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 80px 0;
    text-align: center;
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

.no-data-message {
    text-align: center;
    padding: 50px 20px;
    color: #6c757d;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .hero-title {
        font-size: 2.5rem;
    }

    .mission-container {
        grid-template-columns: 1fr;
        gap: 40px;
        text-align: center;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .values-grid,
    .team-grid,
    .testimonials-grid {
        grid-template-columns: 1fr;
        gap: 30px;
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
</style>

<!-- Hero Section -->
<section class="hero-about">
    <div class="hero-content">
        <h1 class="hero-title">Our Mission</h1>
        <p class="hero-subtitle">
            Connecting loving families with pets in need, one adoption at a time.
            We believe every animal deserves a chance at happiness and every family
            deserves the joy that comes with pet companionship.
        </p>
    </div>
</section>

<!-- Mission Section -->
<section class="mission-section">
    <div class="mission-container">
        <div class="mission-content">
            <h2>Who We Are</h2>
            <p>
                Pet Adoption Care Guide was founded with a simple yet powerful vision:
                to create a world where no pet goes without a loving home. We serve as
                a bridge between compassionate families and animals in need of rescue.
            </p>
            <p>
                Our platform connects trusted shelters with potential adopters,
                streamlining the adoption process while ensuring the best possible
                matches between pets and families.
            </p>
            <ul class="mission-highlights">
                <li><i class="fas fa-heart"></i> Facilitating meaningful connections</li>
                <li><i class="fas fa-shield-alt"></i> Ensuring pet welfare and safety</li>
                <li><i class="fas fa-users"></i> Supporting local shelter communities</li>
                <li><i class="fas fa-leaf"></i> Promoting responsible pet ownership</li>
            </ul>
        </div>
        <div class="mission-image">
            <div class="mission-image-content">
                <h3><i class="fas fa-heart"></i> Bringing Joy Home</h3>
                <p>Every pet deserves a loving family and every family deserves the unconditional love of a pet.</p>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="stats-about">
    <div class="container">
        <h2 class="section-title">Our Impact</h2>
        <div class="stats-grid">
            <div class="stat-item">
                <i class="fas fa-paw stat-icon"></i>
                <span class="stat-number"
                    data-count="<?php echo $total_pets_rescued; ?>"><?php echo $total_pets_rescued; ?></span>
                <span class="stat-label">Pets Rescued</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-home stat-icon"></i>
                <span class="stat-number"
                    data-count="<?php echo $successful_adoptions; ?>"><?php echo $successful_adoptions; ?></span>
                <span class="stat-label">Successful Adoptions</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-handshake stat-icon"></i>
                <span class="stat-number"
                    data-count="<?php echo $partner_shelters; ?>"><?php echo $partner_shelters; ?></span>
                <span class="stat-label">Partner Shelters</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-calendar-alt stat-icon"></i>
                <span class="stat-number" data-count="<?php echo $years_active; ?>"><?php echo $years_active; ?></span>
                <span class="stat-label">Years Active</span>
            </div>
        </div>
    </div>
</section>

<!-- Values Section -->
<section class="values-section">
    <div class="container">
        <h2 class="section-title">Our Core Values</h2>
        <div class="values-grid">
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <h3>Compassion</h3>
                <p>We approach every interaction with empathy and understanding, recognizing that both pets and families
                    deserve care and respect throughout the adoption journey.</p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Trust & Safety</h3>
                <p>We maintain the highest standards of safety and transparency, ensuring all pets are healthy and all
                    adopters are prepared for responsible pet ownership.</p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <h3>Community</h3>
                <p>We believe in the power of community support, working closely with local shelters and volunteers to
                    create a network of care for animals in need.</p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-leaf"></i>
                </div>
                <h3>Responsibility</h3>
                <p>We promote sustainable pet ownership practices and provide ongoing support to ensure every adoption
                    leads to a lifelong, loving relationship.</p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h3>Innovation</h3>
                <p>We continuously improve our platform and services, using technology to make pet adoption more
                    accessible and efficient for everyone involved.</p>
            </div>
            <div class="value-card">
                <div class="value-icon">
                    <i class="fas fa-globe"></i>
                </div>
                <h3>Impact</h3>
                <p>We measure our success by the positive impact we have on animal welfare and the happiness we bring to
                    families through successful adoptions.</p>
            </div>
        </div>
    </div>
</section>

<!-- Team Section -->
<section class="team-section">
    <div class="container">
        <h2 class="section-title">Meet Our Team</h2>
        <div class="team-grid">
            <div class="team-card">
                <div class="team-image">
                    <i class="fas fa-user"></i>
                </div>
                <div class="team-info">
                    <h3>Sarah Johnson</h3>
                    <div class="team-role">Founder & CEO</div>
                    <p>A lifelong animal advocate with over 10 years of experience in animal welfare. Sarah founded Pet
                        Adoption Care Guide after adopting three rescue pets herself.</p>
                </div>
            </div>
            <div class="team-card">
                <div class="team-image">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="team-info">
                    <h3>Dr. Michael Chen</h3>
                    <div class="team-role">Veterinary Advisor</div>
                    <p>Licensed veterinarian specializing in shelter medicine. Dr. Chen ensures our health and wellness
                        guidelines meet the highest standards of care.</p>
                </div>
            </div>
            <div class="team-card">
                <div class="team-image">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="team-info">
                    <h3>Emily Rodriguez</h3>
                    <div class="team-role">Adoption Coordinator</div>
                    <p>With a background in social work, Emily specializes in matching pets with the perfect families
                        and providing ongoing adoption support.</p>
                </div>
            </div>
            <div class="team-card">
                <div class="team-image">
                    <i class="fas fa-code"></i>
                </div>
                <div class="team-info">
                    <h3>Alex Thompson</h3>
                    <div class="team-role">Technology Lead</div>
                    <p>Full-stack developer passionate about using technology for social good. Alex ensures our platform
                        is user-friendly and constantly improving.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<?php if (!empty($testimonials)): ?>
<section class="testimonials-section">
    <div class="container">
        <h2 class="section-title">What Families Say</h2>
        <div class="testimonials-grid">
            <?php 
            $sample_testimonials = [
                "Adopting through Pet Adoption Care Guide was an amazing experience. The process was smooth, and we found our perfect companion!",
                "The support we received throughout the adoption process was incredible. Our new family member has brought us so much joy.",
                "Thanks to this platform, we were able to give a loving home to a pet in need. The entire team was helpful and caring.",
                "The detailed profiles and health information helped us make an informed decision. We couldn't be happier with our adoption!",
                "From application to bringing our pet home, everything was handled professionally. Highly recommend this service!",
                "Our adopted pet has become such an important part of our family. The matching process was perfect!"
            ];
            
            foreach (array_slice($testimonials, 0, 3) as $index => $testimonial): 
                $first_name = htmlspecialchars($testimonial['first_name'] ?? 'Anonymous');
                $last_name = htmlspecialchars($testimonial['last_name'] ?? '');
                $pet_name = htmlspecialchars($testimonial['pet_name'] ?? 'Pet');
                $adoption_date = $testimonial['adoption_date'] ?? date('Y-m-d');
            ?>
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <?php echo $sample_testimonials[$index] ?? $sample_testimonials[0]; ?>
                </div>
                <div class="testimonial-author">
                    <div class="author-avatar">
                        <?php echo strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)); ?>
                    </div>
                    <div class="author-info">
                        <h4><?php echo $first_name . ' ' . $last_name; ?></h4>
                        <div class="author-details">
                            Adopted <?php echo $pet_name; ?> •
                            <?php echo date('F Y', strtotime($adoption_date)); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php else: ?>
<section class="testimonials-section">
    <div class="container">
        <h2 class="section-title">Success Stories Coming Soon</h2>
        <div class="no-data-message">
            <i class="fas fa-heart" style="font-size: 3rem; margin-bottom: 20px; color: #667eea;"></i>
            <h3>We're just getting started!</h3>
            <p>As more families adopt pets through our platform, we'll share their heartwarming stories here.</p>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Call to Action -->
<section class="cta-about">
    <div class="cta-content">
        <h2 class="cta-title">Join Our Mission</h2>
        <p class="cta-text">
            Whether you're looking to adopt, volunteer, or partner with us as a shelter,
            there are many ways to get involved in our mission to save lives and create
            lasting bonds between pets and families.
        </p>
        <div class="cta-buttons">
            <a href="<?php echo BASE_URL; ?>adopter/browsePets.php" class="cta-btn cta-btn-primary">
                <i class="fas fa-paw"></i>
                Start Adopting
            </a>
            <a href="<?php echo BASE_URL; ?>contact.php" class="cta-btn cta-btn-secondary">
                <i class="fas fa-envelope"></i>
                Get Involved
            </a>
        </div>
    </div>
</section>

<script>
// About page JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('About page loaded successfully');
});
</script>

<?php include 'common/footer.php'; ?>