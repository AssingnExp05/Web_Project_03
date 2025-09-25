<?php
require_once '../config/db.php';
$page_title = 'Pet Care Guides';

// Get care guides
$filter_species = isset($_GET['species']) ? $_GET['species'] : '';

$where_conditions = ["status = 'active'"];
$params = [];

if ($filter_species) {
    $where_conditions[] = "species = ?";
    $params[] = $filter_species;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

try {
    $sql = "SELECT cg.*, u.first_name, u.last_name 
            FROM care_guides cg 
            JOIN users u ON cg.author_id = u.id 
            $where_clause 
            ORDER BY cg.species, cg.title";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $guides = $stmt->fetchAll();
    
    // Group guides by species
    $guides_by_species = [];
    foreach ($guides as $guide) {
        $guides_by_species[$guide['species']][] = $guide;
    }
    
} catch(PDOException $e) {
    $guides = [];
    $guides_by_species = [];
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
                <h1 class="page-title">Pet Care Guides</h1>
                <p style="color: #6b7280;">Essential information to help you care for your pet</p>
            </div>
        </div>

        <!-- Species Filter -->
        <div class="card mb-6">
            <div class="card-body">
                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <span style="font-weight: 500; color: #374151;">Filter by Species:</span>
                    <a href="/adopter/careGuides.php" 
                       class="<?php echo empty($filter_species) ? 'btn' : 'btn btn-secondary'; ?>" 
                       style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                        All Guides
                    </a>
                    <a href="?species=general" 
                       class="<?php echo $filter_species === 'general' ? 'btn' : 'btn btn-secondary'; ?>" 
                       style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                        🏠 General
                    </a>
                    <a href="?species=dog" 
                       class="<?php echo $filter_species === 'dog' ? 'btn' : 'btn btn-secondary'; ?>" 
                       style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                        🐕 Dogs
                    </a>
                    <a href="?species=cat" 
                       class="<?php echo $filter_species === 'cat' ? 'btn' : 'btn btn-secondary'; ?>" 
                       style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                        🐱 Cats
                    </a>
                    <a href="?species=bird" 
                       class="<?php echo $filter_species === 'bird' ? 'btn' : 'btn btn-secondary'; ?>" 
                       style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                        🐦 Birds
                    </a>
                    <a href="?species=rabbit" 
                       class="<?php echo $filter_species === 'rabbit' ? 'btn' : 'btn btn-secondary'; ?>" 
                       style="font-size: 0.875rem; padding: 0.5rem 1rem;">
                        🐰 Rabbits
                    </a>
                </div>
            </div>
        </div>

        <!-- Care Guides -->
        <?php if (!empty($guides_by_species)): ?>
            <?php foreach ($guides_by_species as $species => $species_guides): ?>
                <div class="mb-6">
                    <h2 style="font-size: 1.5rem; font-weight: bold; color: #1f2937; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <?php 
                        echo $species === 'general' ? '🏠' : ($species === 'dog' ? '🐕' : ($species === 'cat' ? '🐱' : ($species === 'bird' ? '🐦' : ($species === 'rabbit' ? '🐰' : '🐾')))); 
                        ?>
                        <?php echo ucfirst($species); ?> Care Guides
                    </h2>
                    
                    <div class="grid grid-cols-2" style="gap: 1.5rem;">
                        <?php foreach ($species_guides as $guide): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h3 style="font-size: 1.1rem; color: #1f2937; margin-bottom: 0.5rem;">
                                        <?php echo htmlspecialchars($guide['title']); ?>
                                    </h3>
                                    <p style="color: #6b7280; font-size: 0.875rem;">
                                        By <?php echo htmlspecialchars($guide['first_name'] . ' ' . $guide['last_name']); ?> • 
                                        <?php echo date('M j, Y', strtotime($guide['created_at'])); ?>
                                    </p>
                                </div>
                                <div class="card-body">
                                    <div style="color: #6b7280; line-height: 1.6; font-size: 0.875rem;">
                                        <?php 
                                        $content = htmlspecialchars($guide['content']);
                                        $preview = substr($content, 0, 300);
                                        if (strlen($content) > 300) {
                                            $preview .= '...';
                                        }
                                        echo nl2br($preview);
                                        ?>
                                    </div>
                                    
                                    <?php if (strlen($guide['content']) > 300): ?>
                                        <div style="margin-top: 1rem;">
                                            <button onclick="toggleContent(<?php echo $guide['id']; ?>)" 
                                                    class="btn btn-secondary" 
                                                    style="font-size: 0.875rem; padding: 0.5rem 1rem;"
                                                    id="toggle-btn-<?php echo $guide['id']; ?>">
                                                Read More
                                            </button>
                                        </div>
                                        
                                        <div id="full-content-<?php echo $guide['id']; ?>" style="display: none; margin-top: 1rem; color: #6b7280; line-height: 1.6; font-size: 0.875rem;">
                                            <?php echo nl2br(htmlspecialchars($guide['content'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center" style="padding: 4rem;">
                    <div style="font-size: 5rem; margin-bottom: 2rem; opacity: 0.5;">📚</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1f2937;">No Care Guides Found</h3>
                    <p style="color: #6b7280; margin-bottom: 2rem; font-size: 1.1rem;">
                        <?php if ($filter_species): ?>
                            No care guides found for <?php echo ucfirst($filter_species); ?>. Try viewing all guides or check back later.
                        <?php else: ?>
                            Care guides are being prepared. Please check back soon for helpful pet care information.
                        <?php endif; ?>
                    </p>
                    <?php if ($filter_species): ?>
                        <a href="/adopter/careGuides.php" class="btn">View All Guides</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Tips Section -->
        <div class="card" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7);">
            <div class="card-header">
                <h2 style="font-size: 1.25rem; color: #1f2937;">🌟 Quick Pet Care Tips</h2>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-3">
                    <div>
                        <h4 style="color: #059669; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            🍽️ Nutrition
                        </h4>
                        <ul style="color: #6b7280; font-size: 0.875rem; line-height: 1.6; list-style: none; padding: 0;">
                            <li style="margin-bottom: 0.5rem;">• Provide fresh water daily</li>
                            <li style="margin-bottom: 0.5rem;">• Feed age-appropriate food</li>
                            <li style="margin-bottom: 0.5rem;">• Maintain regular feeding schedule</li>
                            <li>• Monitor weight and adjust portions</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="color: #059669; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            🏥 Health
                        </h4>
                        <ul style="color: #6b7280; font-size: 0.875rem; line-height: 1.6; list-style: none; padding: 0;">
                            <li style="margin-bottom: 0.5rem;">• Schedule regular vet checkups</li>
                            <li style="margin-bottom: 0.5rem;">• Keep vaccinations up to date</li>
                            <li style="margin-bottom: 0.5rem;">• Watch for behavior changes</li>
                            <li>• Maintain dental hygiene</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="color: #059669; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            ❤️ Wellbeing
                        </h4>
                        <ul style="color: #6b7280; font-size: 0.875rem; line-height: 1.6; list-style: none; padding: 0;">
                            <li style="margin-bottom: 0.5rem;">• Provide daily exercise</li>
                            <li style="margin-bottom: 0.5rem;">• Create safe, comfortable space</li>
                            <li style="margin-bottom: 0.5rem;">• Offer mental stimulation</li>
                            <li>• Show love and patience</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emergency Contacts -->
        <div class="card" style="margin-top: 2rem; border-left: 4px solid #dc2626;">
            <div class="card-header">
                <h2 style="font-size: 1.25rem; color: #dc2626;">🚨 Emergency Information</h2>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2">
                    <div>
                        <h4 style="color: #1f2937; margin-bottom: 1rem;">When to Seek Emergency Care</h4>
                        <ul style="color: #6b7280; font-size: 0.875rem; line-height: 1.6;">
                            <li>Difficulty breathing or choking</li>
                            <li>Severe bleeding or trauma</li>
                            <li>Loss of consciousness</li>
                            <li>Seizures or convulsions</li>
                            <li>Suspected poisoning</li>
                            <li>Inability to urinate or defecate</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="color: #1f2937; margin-bottom: 1rem;">Emergency Contacts</h4>
                        <div style="color: #6b7280; font-size: 0.875rem; line-height: 1.6;">
                            <p style="margin-bottom: 0.5rem;"><strong>ASPCA Poison Control:</strong> (888) 426-4435</p>
                            <p style="margin-bottom: 0.5rem;"><strong>Pet Poison Helpline:</strong> (855) 764-7661</p>
                            <p style="margin-bottom: 0.5rem;"><strong>Local Emergency Vet:</strong> Contact your veterinarian</p>
                            <p style="color: #dc2626; font-weight: 500; margin-top: 1rem;">
                                ⚠️ Keep your vet's emergency number easily accessible
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function toggleContent(guideId) {
    const fullContent = document.getElementById('full-content-' + guideId);
    const toggleBtn = document.getElementById('toggle-btn-' + guideId);
    
    if (fullContent.style.display === 'none') {
        fullContent.style.display = 'block';
        toggleBtn.textContent = 'Read Less';
    } else {
        fullContent.style.display = 'none';
        toggleBtn.textContent = 'Read More';
    }
}
</script>

<?php include '../common/footer.php'; ?>