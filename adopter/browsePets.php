<?php
require_once '../config/db.php';
$page_title = 'Browse Pets';

// Get pets with filters
$filter_species = isset($_GET['species']) ? $_GET['species'] : '';
$filter_age = isset($_GET['age']) ? $_GET['age'] : '';
$filter_size = isset($_GET['size']) ? $_GET['size'] : '';
$filter_gender = isset($_GET['gender']) ? $_GET['gender'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where_conditions = ["p.status = 'available'"];
$params = [];

if ($filter_species) {
    $where_conditions[] = "p.species = ?";
    $params[] = $filter_species;
}

if ($filter_age) {
    switch ($filter_age) {
        case 'young':
            $where_conditions[] = "p.age <= 2";
            break;
        case 'adult':
            $where_conditions[] = "p.age BETWEEN 3 AND 7";
            break;
        case 'senior':
            $where_conditions[] = "p.age >= 8";
            break;
    }
}

if ($filter_size) {
    $where_conditions[] = "p.size = ?";
    $params[] = $filter_size;
}

if ($filter_gender) {
    $where_conditions[] = "p.gender = ?";
    $params[] = $filter_gender;
}

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.breed LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

try {
    $sql = "SELECT p.*, s.shelter_name, u.first_name, u.last_name 
            FROM pets p 
            JOIN shelters sh ON p.shelter_id = sh.id 
            JOIN users u ON sh.user_id = u.id 
            JOIN users s ON sh.user_id = s.id 
            $where_clause 
            ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pets = $stmt->fetchAll();
    
    // Get statistics for filters
    $stmt = $pdo->query("SELECT species, COUNT(*) as count FROM pets WHERE status = 'available' GROUP BY species");
    $species_stats = [];
    while ($row = $stmt->fetch()) {
        $species_stats[$row['species']] = $row['count'];
    }
    
} catch(PDOException $e) {
    $pets = [];
    $species_stats = [];
}

include '../common/header.php';
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'adopter') {
    include '../common/navbar_adopter.php';
}
?>

<main class="main-content">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 class="page-title">Browse Available Pets</h1>
                <p style="color: #6b7280;">Find your perfect companion from our verified shelters</p>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <span style="color: #6b7280;">Found: <?php echo count($pets); ?> pets</span>
            </div>
        </div>

        <!-- Species Quick Filter -->
        <div class="card mb-6">
            <div class="card-body">
                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <span style="font-weight: 500; color: #374151;">Quick Filter:</span>
                    <a href="/adopter/browsePets.php" 
                       class="<?php echo empty($filter_species) ? 'btn' : 'btn btn-secondary'; ?>" 
                       style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                        All (<?php echo array_sum($species_stats); ?>)
                    </a>
                    <?php foreach ($species_stats as $species => $count): ?>
                        <a href="?species=<?php echo $species; ?>" 
                           class="<?php echo $filter_species === $species ? 'btn' : 'btn btn-secondary'; ?>" 
                           style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                            <?php echo $species === 'dog' ? '🐕' : ($species === 'cat' ? '🐱' : ($species === 'bird' ? '🐦' : ($species === 'rabbit' ? '🐰' : '🐾'))); ?>
                            <?php echo ucfirst($species); ?> (<?php echo $count; ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Advanced Filters -->
        <div class="card mb-6">
            <div class="card-header">
                <h2 style="font-size: 1.1rem; color: #1f2937;">Advanced Filters</h2>
            </div>
            <div class="card-body">
                <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" id="search" name="search" class="form-input" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Pet name, breed, or description">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="species" class="form-label">Species</label>
                        <select id="species" name="species" class="form-input form-select">
                            <option value="">All Species</option>
                            <option value="dog" <?php echo $filter_species === 'dog' ? 'selected' : ''; ?>>Dog</option>
                            <option value="cat" <?php echo $filter_species === 'cat' ? 'selected' : ''; ?>>Cat</option>
                            <option value="bird" <?php echo $filter_species === 'bird' ? 'selected' : ''; ?>>Bird</option>
                            <option value="rabbit" <?php echo $filter_species === 'rabbit' ? 'selected' : ''; ?>>Rabbit</option>
                            <option value="other" <?php echo $filter_species === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="age" class="form-label">Age Group</label>
                        <select id="age" name="age" class="form-input form-select">
                            <option value="">All Ages</option>
                            <option value="young" <?php echo $filter_age === 'young' ? 'selected' : ''; ?>>Young (0-2 years)</option>
                            <option value="adult" <?php echo $filter_age === 'adult' ? 'selected' : ''; ?>>Adult (3-7 years)</option>
                            <option value="senior" <?php echo $filter_age === 'senior' ? 'selected' : ''; ?>>Senior (8+ years)</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="size" class="form-label">Size</label>
                        <select id="size" name="size" class="form-input form-select">
                            <option value="">All Sizes</option>
                            <option value="small" <?php echo $filter_size === 'small' ? 'selected' : ''; ?>>Small</option>
                            <option value="medium" <?php echo $filter_size === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="large" <?php echo $filter_size === 'large' ? 'selected' : ''; ?>>Large</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="gender" class="form-label">Gender</label>
                        <select id="gender" name="gender" class="form-input form-select">
                            <option value="">All Genders</option>
                            <option value="male" <?php echo $filter_gender === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $filter_gender === 'female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Filter</button>
                    <a href="/adopter/browsePets.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>
        </div>

        <!-- Pets Grid -->
        <div class="grid grid-cols-3">
            <?php if (!empty($pets)): ?>
                <?php foreach ($pets as $pet): ?>
                    <div class="card">
                        <div style="height: 200px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; font-size: 4rem;">
                            <?php echo $pet['species'] === 'dog' ? '🐕' : ($pet['species'] === 'cat' ? '🐱' : ($pet['species'] === 'bird' ? '🐦' : ($pet['species'] === 'rabbit' ? '🐰' : '🐾'))); ?>
                        </div>
                        <div class="card-body">
                            <div style="margin-bottom: 1rem;">
                                <h3 style="font-size: 1.25rem; font-weight: bold; margin-bottom: 0.25rem;">
                                    <?php echo htmlspecialchars($pet['name']); ?>
                                </h3>
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                    <?php echo ucfirst($pet['species']); ?> • <?php echo $pet['age']; ?> years • <?php echo ucfirst($pet['gender']); ?>
                                </p>
                                <?php if ($pet['breed']): ?>
                                    <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                        <?php echo htmlspecialchars($pet['breed']); ?>
                                    </p>
                                <?php endif; ?>
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                    Size: <?php echo ucfirst($pet['size']); ?>
                                    <?php if ($pet['color']): ?>
                                        • Color: <?php echo htmlspecialchars($pet['color']); ?>
                                    <?php endif; ?>
                                </p>
                                <p style="color: #059669; font-size: 0.875rem; font-weight: 500;">
                                    🏠 <?php echo htmlspecialchars($pet['shelter_name']); ?>
                                </p>
                            </div>
                            
                            <?php if ($pet['description']): ?>
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 1rem; line-height: 1.4;">
                                    <?php echo htmlspecialchars(substr($pet['description'], 0, 120)) . (strlen($pet['description']) > 120 ? '...' : ''); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <div style="font-size: 1.1rem; font-weight: bold; color: #059669;">
                                    $<?php echo number_format($pet['adoption_fee'], 2); ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #9ca3af;">
                                    Added <?php echo date('M j', strtotime($pet['created_at'])); ?>
                                </div>
                            </div>
                            
                            <?php if ($pet['special_needs']): ?>
                                <div style="margin-bottom: 1rem; padding: 0.5rem; background: #fef3c7; border-radius: 0.375rem;">
                                    <p style="font-size: 0.75rem; color: #92400e; font-weight: 500;">
                                        ⚠️ Special Needs
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'adopter'): ?>
                                    <a href="/adopter/petDetails.php?id=<?php echo $pet['id']; ?>" class="btn" style="flex: 1; text-align: center;">
                                        View Details
                                    </a>
                                <?php else: ?>
                                    <a href="/auth/login.php" class="btn" style="flex: 1; text-align: center;">
                                        Login to Adopt
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #6b7280;">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">🔍</div>
                    <h3 style="font-size: 1.25rem; margin-bottom: 1rem;">No pets found</h3>
                    <p style="margin-bottom: 2rem;">Try adjusting your search criteria or check back later for new arrivals.</p>
                    <a href="/adopter/browsePets.php" class="btn">Clear All Filters</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Call to Action -->
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="card" style="margin-top: 3rem; background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white;">
                <div class="card-body text-center" style="padding: 3rem;">
                    <h2 style="font-size: 1.75rem; margin-bottom: 1rem;">Ready to Adopt?</h2>
                    <p style="font-size: 1.1rem; margin-bottom: 2rem; opacity: 0.9;">
                        Create an account to apply for adoption and get access to detailed pet profiles.
                    </p>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <a href="/auth/register.php" class="btn" style="background: white; color: #3b82f6; font-size: 1.1rem; padding: 1rem 2rem;">
                            Create Account
                        </a>
                        <a href="/auth/login.php" class="btn btn-secondary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                            Sign In
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../common/footer.php'; ?>