<?php
require_once '../config/db.php';
check_role('adopter');
$page_title = 'My Adoptions';

try {
    $user_id = $_SESSION['user_id'];
    
    // Get adoption applications
    $stmt = $pdo->prepare("SELECT a.*, p.name as pet_name, p.species, p.breed, p.age, p.gender, p.adoption_fee,
                          s.shelter_name, u.first_name, u.last_name, u.email, u.phone
                          FROM adoptions a 
                          JOIN pets p ON a.pet_id = p.id 
                          JOIN shelters sh ON p.shelter_id = sh.id 
                          JOIN users u ON sh.user_id = u.id 
                          JOIN users s ON sh.user_id = s.id
                          WHERE a.adopter_id = ? 
                          ORDER BY a.application_date DESC");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll();
    
    // Count applications by status
    $stats = [];
    foreach ($applications as $app) {
        $status = $app['status'];
        $stats[$status] = isset($stats[$status]) ? $stats[$status] + 1 : 1;
    }
    
} catch(PDOException $e) {
    $applications = [];
    $stats = [];
}

include '../common/header.php';
include '../common/navbar_adopter.php';
?>

<main class="main-content">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 class="page-title">My Adoption Applications</h1>
                <p style="color: #6b7280;">Track the status of your pet adoption applications</p>
            </div>
            <a href="/adopter/browsePets.php" class="btn">🔍 Browse More Pets</a>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-4 mb-6">
            <div class="card">
                <div class="card-body text-center">
                    <h3 style="font-size: 2rem; color: #f59e0b; margin-bottom: 0.5rem;">
                        <?php echo $stats['pending'] ?? 0; ?>
                    </h3>
                    <p style="color: #6b7280;">Pending</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <h3 style="font-size: 2rem; color: #059669; margin-bottom: 0.5rem;">
                        <?php echo $stats['approved'] ?? 0; ?>
                    </h3>
                    <p style="color: #6b7280;">Approved</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <h3 style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem;">
                        <?php echo $stats['completed'] ?? 0; ?>
                    </h3>
                    <p style="color: #6b7280;">Completed</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <h3 style="font-size: 2rem; color: #3b82f6; margin-bottom: 0.5rem;">
                        <?php echo count($applications); ?>
                    </h3>
                    <p style="color: #6b7280;">Total Applications</p>
                </div>
            </div>
        </div>

        <!-- Applications List -->
        <?php if (!empty($applications)): ?>
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <?php foreach ($applications as $app): ?>
                    <div class="card">
                        <div class="card-body">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1.5rem;">
                                <div style="display: flex; gap: 1.5rem; align-items: start;">
                                    <div style="font-size: 4rem;">
                                        <?php echo $app['species'] === 'dog' ? '🐕' : ($app['species'] === 'cat' ? '🐱' : ($app['species'] === 'bird' ? '🐦' : ($app['species'] === 'rabbit' ? '🐰' : '🐾'))); ?>
                                    </div>
                                    <div>
                                        <h3 style="font-size: 1.5rem; font-weight: bold; margin-bottom: 0.5rem; color: #1f2937;">
                                            <?php echo htmlspecialchars($app['pet_name']); ?>
                                        </h3>
                                        <p style="color: #6b7280; font-size: 1rem; margin-bottom: 0.5rem;">
                                            <?php echo ucfirst($app['species']); ?> • <?php echo $app['age']; ?> years • <?php echo ucfirst($app['gender']); ?>
                                            <?php if ($app['breed']): ?>
                                                • <?php echo htmlspecialchars($app['breed']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <p style="color: #059669; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">
                                            🏠 <?php echo htmlspecialchars($app['shelter_name']); ?>
                                        </p>
                                        <p style="color: #6b7280; font-size: 0.875rem;">
                                            Applied on <?php echo date('F j, Y \a\t g:i A', strtotime($app['application_date'])); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div style="text-align: right;">
                                    <span style="padding: 0.5rem 1rem; font-size: 0.875rem; border-radius: 9999px; font-weight: 600; 
                                                 background: <?php echo $app['status'] === 'pending' ? '#fef3c7' : ($app['status'] === 'approved' ? '#d1fae5' : ($app['status'] === 'rejected' ? '#fee2e2' : '#dbeafe')); ?>; 
                                                 color: <?php echo $app['status'] === 'pending' ? '#92400e' : ($app['status'] === 'approved' ? '#065f46' : ($app['status'] === 'rejected' ? '#991b1b' : '#1e40af')); ?>;">
                                        <?php 
                                        switch($app['status']) {
                                            case 'pending': echo '⏳ Pending Review'; break;
                                            case 'approved': echo '✅ Approved'; break;
                                            case 'rejected': echo '❌ Not Approved'; break;
                                            case 'completed': echo '🎉 Completed'; break;
                                        }
                                        ?>
                                    </span>
                                    <div style="font-size: 1.1rem; font-weight: bold; color: #059669; margin-top: 0.5rem;">
                                        $<?php echo number_format($app['adoption_fee'], 2); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Application Details -->
                            <div style="background: #f8fafc; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1.5rem;">
                                <h4 style="font-size: 1rem; font-weight: 600; color: #374151; margin-bottom: 1rem;">Application Details</h4>
                                
                                <div class="grid grid-cols-2" style="gap: 1.5rem; margin-bottom: 1rem;">
                                    <div>
                                        <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                            <strong>Experience Level:</strong> <?php echo ucfirst($app['experience_level']); ?>
                                        </p>
                                        <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                            <strong>Housing Type:</strong> <?php echo ucfirst($app['housing_type']); ?>
                                        </p>
                                        <p style="color: #6b7280; font-size: 0.875rem;">
                                            <strong>Has Yard:</strong> <?php echo $app['has_yard'] ? 'Yes' : 'No'; ?>
                                        </p>
                                    </div>
                                    
                                    <div>
                                        <p style="color: #6b7280; font-size: 0.875rem;">
                                            <strong>Shelter Contact:</strong><br>
                                            <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?><br>
                                            <?php echo htmlspecialchars($app['email']); ?>
                                            <?php if ($app['phone']): ?>
                                                <br><?php echo htmlspecialchars($app['phone']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if ($app['reason_for_adoption']): ?>
                                    <div style="margin-bottom: 1rem;">
                                        <p style="color: #374151; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Reason for Adoption:</p>
                                        <p style="color: #6b7280; font-size: 0.875rem; line-height: 1.4;">
                                            <?php echo nl2br(htmlspecialchars($app['reason_for_adoption'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($app['other_pets']): ?>
                                    <div style="margin-bottom: 1rem;">
                                        <p style="color: #374151; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Other Pets:</p>
                                        <p style="color: #6b7280; font-size: 0.875rem; line-height: 1.4;">
                                            <?php echo nl2br(htmlspecialchars($app['other_pets'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($app['references']): ?>
                                    <div>
                                        <p style="color: #374151; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">References:</p>
                                        <p style="color: #6b7280; font-size: 0.875rem; line-height: 1.4;">
                                            <?php echo nl2br(htmlspecialchars($app['references'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Admin Notes -->
                            <?php if ($app['admin_notes']): ?>
                                <div style="padding: 1rem; background: <?php echo $app['status'] === 'approved' ? '#d1fae5' : ($app['status'] === 'rejected' ? '#fee2e2' : '#dbeafe'); ?>; border-radius: 0.375rem; margin-bottom: 1rem;">
                                    <p style="color: #374151; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">
                                        <?php echo $app['status'] === 'approved' ? '✅ Approval Notes:' : ($app['status'] === 'rejected' ? '❌ Rejection Reason:' : '📝 Notes:'); ?>
                                    </p>
                                    <p style="color: <?php echo $app['status'] === 'approved' ? '#065f46' : ($app['status'] === 'rejected' ? '#991b1b' : '#1e40af'); ?>; font-size: 0.875rem; line-height: 1.4;">
                                        <?php echo nl2br(htmlspecialchars($app['admin_notes'])); ?>
                                    </p>
                                    <?php if ($app['approved_date']): ?>
                                        <p style="color: #9ca3af; font-size: 0.75rem; margin-top: 0.5rem;">
                                            Updated on <?php echo date('M j, Y g:i A', strtotime($app['approved_date'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Status-specific Actions -->
                            <div style="display: flex; gap: 1rem; justify-content: end;">
                                <?php if ($app['status'] === 'pending'): ?>
                                    <div style="color: #6b7280; font-size: 0.875rem; font-style: italic;">
                                        Your application is being reviewed by the shelter. They will contact you soon!
                                    </div>
                                <?php elseif ($app['status'] === 'approved'): ?>
                                    <div style="color: #059669; font-size: 0.875rem; font-weight: 500;">
                                        🎉 Congratulations! Please contact the shelter to complete the adoption process.
                                    </div>
                                <?php elseif ($app['status'] === 'completed'): ?>
                                    <div style="color: #10b981; font-size: 0.875rem; font-weight: 500;">
                                        🎉 Adoption completed! Welcome to your new family member!
                                    </div>
                                <?php elseif ($app['status'] === 'rejected'): ?>
                                    <div style="color: #6b7280; font-size: 0.875rem;">
                                        Don't give up! There are many other wonderful pets waiting for homes.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center" style="padding: 4rem;">
                    <div style="font-size: 5rem; margin-bottom: 2rem; opacity: 0.5;">📝</div>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem; color: #1f2937;">No Applications Yet</h3>
                    <p style="color: #6b7280; margin-bottom: 2rem; font-size: 1.1rem;">
                        You haven't submitted any adoption applications yet. Start browsing our available pets to find your perfect companion!
                    </p>
                    <a href="/adopter/browsePets.php" class="btn" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        🔍 Browse Available Pets
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tips Section -->
        <div class="card" style="margin-top: 2rem; background: linear-gradient(135deg, #f0f9ff, #e0f2fe);">
            <div class="card-header">
                <h2 style="font-size: 1.25rem; color: #1f2937;">💡 Adoption Tips</h2>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2">
                    <div>
                        <h4 style="color: #3b82f6; margin-bottom: 0.5rem;">While You Wait</h4>
                        <ul style="color: #6b7280; font-size: 0.875rem; line-height: 1.6;">
                            <li>Research pet care for your chosen species</li>
                            <li>Prepare your home with necessary supplies</li>
                            <li>Consider pet insurance options</li>
                            <li>Find a local veterinarian</li>
                        </ul>
                    </div>
                    <div>
                        <h4 style="color: #3b82f6; margin-bottom: 0.5rem;">After Approval</h4>
                        <ul style="color: #6b7280; font-size: 0.875rem; line-height: 1.6;">
                            <li>Schedule a meet-and-greet with the pet</li>
                            <li>Ask about the pet's routine and preferences</li>
                            <li>Discuss transition plans with the shelter</li>
                            <li>Prepare for an adjustment period</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../common/footer.php'; ?>