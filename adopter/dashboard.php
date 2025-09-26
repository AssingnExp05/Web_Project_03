<?php
// Start session and include database
require_once '../config/db.php';

// Check if user is logged in and is an adopter
if (!Session::isLoggedIn() || Session::getUserType() !== 'adopter') {
    header('Location: ../auth/login.php');
    exit();
}

$adopter_user_id = Session::getUserId();
$adopter_name = Session::get('first_name', 'User');

// Get adopter's basic information
$adopter_info = DBHelper::selectOne(
    "SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as full_name 
     FROM users u 
     WHERE u.user_id = ?",
    [$adopter_user_id]
);

if (!$adopter_info) {
    die("Error: User information not found.");
}

// Get adoption applications statistics
$adoption_stats = [
    'pending' => DBHelper::count('adoption_applications', ['adopter_id' => $adopter_user_id, 'application_status' => 'pending']),
    'approved' => DBHelper::count('adoption_applications', ['adopter_id' => $adopter_user_id, 'application_status' => 'approved']),
    'rejected' => DBHelper::count('adoption_applications', ['adopter_id' => $adopter_user_id, 'application_status' => 'rejected']),
    'total_applications' => DBHelper::count('adoption_applications', ['adopter_id' => $adopter_user_id])
];

// Get completed adoptions count
$completed_adoptions = DBHelper::count('adoptions', ['adopter_id' => $adopter_user_id]);

// Get recent adoption applications
$recent_applications = DBHelper::select(
    "SELECT aa.*, p.pet_name, p.primary_image, p.age, p.gender, p.adoption_fee,
            pc.category_name, s.shelter_name,
            DATEDIFF(CURDATE(), aa.application_date) as days_ago
     FROM adoption_applications aa
     INNER JOIN pets p ON aa.pet_id = p.pet_id
     INNER JOIN pet_categories pc ON p.category_id = pc.category_id
     INNER JOIN shelters s ON aa.shelter_id = s.shelter_id
     WHERE aa.adopter_id = ?
     ORDER BY aa.application_date DESC
     LIMIT 5",
    [$adopter_user_id]
);

// Get recommended pets (available pets that might interest the adopter)
$recommended_pets = DBHelper::select(
    "SELECT p.*, pc.category_name, pb.breed_name, s.shelter_name,
            (SELECT COUNT(*) FROM adoption_applications aa2 WHERE aa2.pet_id = p.pet_id) as application_count
     FROM pets p
     INNER JOIN pet_categories pc ON p.category_id = pc.category_id
     LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
     INNER JOIN shelters s ON p.shelter_id = s.shelter_id
     WHERE p.status = 'available'
     AND p.pet_id NOT IN (
         SELECT DISTINCT pet_id 
         FROM adoption_applications 
         WHERE adopter_id = ?
     )
     ORDER BY RAND()
     LIMIT 6",
    [$adopter_user_id]
);

// Get adopted pets (completed adoptions)
$adopted_pets = DBHelper::select(
    "SELECT a.*, p.pet_name, p.primary_image, p.age, p.gender,
            pc.category_name, s.shelter_name,
            DATEDIFF(CURDATE(), a.adoption_date) as days_since_adoption
     FROM adoptions a
     INNER JOIN pets p ON a.pet_id = p.pet_id
     INNER JOIN pet_categories pc ON p.category_id = pc.category_id
     INNER JOIN shelters s ON a.shelter_id = s.shelter_id
     WHERE a.adopter_id = ?
     ORDER BY a.adoption_date DESC
     LIMIT 3",
    [$adopter_user_id]
);

// Get care guides count (for quick stats)
$care_guides_count = DBHelper::count('care_guides', ['is_published' => 1]);

// Get upcoming events or reminders (you can expand this)
$upcoming_reminders = [];
foreach ($adopted_pets as $pet) {
    if ($pet['days_since_adoption'] % 30 == 0 && $pet['days_since_adoption'] > 0) {
        $upcoming_reminders[] = [
            'type' => 'checkup',
            'message' => "Monthly checkup reminder for {$pet['pet_name']}",
            'pet_name' => $pet['pet_name'],
            'days' => 0
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pet Adoption Care Guide</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        color: #333;
    }

    .dashboard-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        background: rgba(255, 255, 255, 0.95);
        min-height: calc(100vh - 70px);
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.1);
    }

    .welcome-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }

    .welcome-section::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        transform: translate(50px, -50px);
    }

    .welcome-content {
        position: relative;
        z-index: 2;
    }

    .welcome-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .welcome-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        font-weight: 300;
    }

    .user-info-card {
        margin-top: 20px;
        padding: 20px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .user-avatar-large {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 1.5rem;
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--card-color, #667eea);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .stat-card.pending {
        --card-color: #ffa726;
    }

    .stat-card.approved {
        --card-color: #66bb6a;
    }

    .stat-card.rejected {
        --card-color: #ef5350;
    }

    .stat-card.adopted {
        --card-color: #42a5f5;
    }

    .stat-card.guides {
        --card-color: #ab47bc;
    }

    .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 15px;
        color: var(--card-color, #667eea);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 5px;
        color: var(--card-color, #667eea);
    }

    .stat-label {
        font-size: 0.95rem;
        color: #666;
        font-weight: 500;
    }

    .main-content {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .content-section {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .section-header {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 25px;
        border-bottom: 1px solid #e9ecef;
    }

    .section-header h3 {
        color: #495057;
        font-size: 1.4rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-body {
        padding: 25px;
    }

    .application-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        border: 2px solid #f8f9fa;
        border-radius: 12px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .application-item:hover {
        border-color: #667eea;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
    }

    .pet-image {
        width: 60px;
        height: 60px;
        border-radius: 10px;
        object-fit: cover;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }

    .application-info {
        flex: 1;
    }

    .pet-name {
        font-weight: 600;
        font-size: 1.1rem;
        color: #333;
        margin-bottom: 5px;
    }

    .pet-details {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 5px;
    }

    .application-meta {
        font-size: 0.8rem;
        color: #999;
    }

    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-align: center;
        min-width: 80px;
    }

    .status-pending {
        background: #fff3e0;
        color: #ef6c00;
        border: 1px solid #ffcc02;
    }

    .status-approved {
        background: #e8f5e8;
        color: #2e7d32;
        border: 1px solid #c8e6c9;
    }

    .status-rejected {
        background: #ffebee;
        color: #c62828;
        border: 1px solid #ffcdd2;
    }

    .pet-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .pet-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .pet-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
    }

    .pet-card-image {
        width: 100%;
        height: 180px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        position: relative;
    }

    .pet-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .pet-card-content {
        padding: 20px;
    }

    .pet-card-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
    }

    .pet-card-details {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 15px;
    }

    .pet-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .adoption-fee {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2e7d32;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        justify-content: center;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    }

    .btn-outline {
        background: transparent;
        color: #667eea;
        border: 2px solid #667eea;
    }

    .btn-outline:hover {
        background: #667eea;
        color: white;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #6c757d;
    }

    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }

    .quick-action {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 20px;
        border-radius: 12px;
        text-decoration: none;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .quick-action:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .quick-action-icon {
        font-size: 2rem;
        opacity: 0.8;
    }

    .reminder-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: #fff3cd;
        border-radius: 8px;
        border-left: 4px solid #ffc107;
        margin-bottom: 10px;
    }

    .reminder-icon {
        color: #856404;
        font-size: 1.2rem;
    }

    .reminder-content {
        flex: 1;
        color: #856404;
    }

    @media (max-width: 768px) {
        .dashboard-container {
            padding: 10px;
        }

        .welcome-title {
            font-size: 2rem;
        }

        .main-content {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .pet-grid {
            grid-template-columns: 1fr;
        }

        .quick-actions {
            grid-template-columns: 1fr;
        }
    }

    /* Loading animation */
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255, 255, 255, .3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Fade in animation */
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }

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
    </style>
</head>

<body>
    <?php include '../common/navbar_adopter.php'; ?>

    <div class="dashboard-container">
        <!-- Welcome Section -->
        <div class="welcome-section fade-in">
            <div class="welcome-content">
                <h1 class="welcome-title">
                    <i class="fas fa-heart"></i>
                    Welcome back, <?php echo htmlspecialchars($adopter_name); ?>!
                </h1>
                <p class="welcome-subtitle">Find your perfect companion and give them a loving home</p>

                <div class="user-info-card">
                    <div class="user-avatar-large">
                        <?php echo strtoupper(substr($adopter_name, 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1rem;">
                            <?php echo htmlspecialchars($adopter_info['full_name']); ?>
                        </div>
                        <div style="opacity: 0.8;">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($adopter_info['email']); ?>
                        </div>
                        <div style="opacity: 0.8;">
                            <i class="fas fa-calendar"></i>
                            Member since <?php echo date('M Y', strtotime($adopter_info['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid fade-in">
            <div class="stat-card pending" onclick="window.location.href='myAdoptions.php?filter=pending'">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $adoption_stats['pending']; ?></div>
                <div class="stat-label">Pending Applications</div>
            </div>

            <div class="stat-card approved" onclick="window.location.href='myAdoptions.php?filter=approved'">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo $adoption_stats['approved']; ?></div>
                <div class="stat-label">Approved Applications</div>
            </div>

            <div class="stat-card adopted" onclick="window.location.href='myAdoptions.php?filter=adopted'">
                <div class="stat-icon"><i class="fas fa-heart"></i></div>
                <div class="stat-number"><?php echo $completed_adoptions; ?></div>
                <div class="stat-label">Adopted Pets</div>
            </div>

            <div class="stat-card guides" onclick="window.location.href='careGuides.php'">
                <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                <div class="stat-number"><?php echo $care_guides_count; ?></div>
                <div class="stat-label">Care Guides Available</div>
            </div>

            <?php if ($adoption_stats['rejected'] > 0): ?>
            <div class="stat-card rejected" onclick="window.location.href='myAdoptions.php?filter=rejected'">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-number"><?php echo $adoption_stats['rejected']; ?></div>
                <div class="stat-label">Declined Applications</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Main Content Grid -->
        <div class="main-content">
            <!-- Recent Applications -->
            <div class="content-section fade-in">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-file-alt"></i>
                        Recent Applications
                    </h3>
                </div>
                <div class="section-body">
                    <?php if (!empty($recent_applications)): ?>
                    <?php foreach ($recent_applications as $app): ?>
                    <div class="application-item"
                        onclick="window.location.href='petDetails.php?id=<?php echo $app['pet_id']; ?>'">
                        <div class="pet-image">
                            <?php if ($app['primary_image']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($app['primary_image']); ?>"
                                alt="<?php echo htmlspecialchars($app['pet_name']); ?>">
                            <?php else: ?>
                            <i class="fas fa-paw"></i>
                            <?php endif; ?>
                        </div>

                        <div class="application-info">
                            <div class="pet-name"><?php echo htmlspecialchars($app['pet_name']); ?></div>
                            <div class="pet-details">
                                <?php echo htmlspecialchars($app['category_name']); ?> •
                                <?php echo $app['age']; ?> years old •
                                <?php echo ucfirst($app['gender']); ?>
                            </div>
                            <div class="application-meta">
                                <i class="fas fa-home"></i>
                                <?php echo htmlspecialchars($app['shelter_name']); ?> •
                                Applied <?php echo $app['days_ago']; ?>
                                day<?php echo $app['days_ago'] != 1 ? 's' : ''; ?> ago
                            </div>
                        </div>

                        <div class="status-badge status-<?php echo $app['application_status']; ?>">
                            <i class="fas fa-<?php 
                                        echo $app['application_status'] == 'pending' ? 'clock' : 
                                             ($app['application_status'] == 'approved' ? 'check' : 'times'); 
                                    ?>"></i>
                            <?php echo ucfirst($app['application_status']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div style="text-align: center; margin-top: 20px;">
                        <a href="myAdoptions.php" class="btn btn-outline">
                            <i class="fas fa-list"></i>
                            View All Applications
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt empty-state-icon"></i>
                        <h4>No Applications Yet</h4>
                        <p>Start your adoption journey by browsing available pets!</p>
                        <a href="browsePets.php" class="btn btn-primary" style="margin-top: 15px;">
                            <i class="fas fa-search"></i>
                            Browse Pets
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Quick Actions -->
                <div class="content-section fade-in" style="margin-bottom: 20px;">
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h3>
                    </div>
                    <div class="section-body">
                        <div class="quick-actions">
                            <a href="browsePets.php" class="quick-action">
                                <i class="fas fa-search quick-action-icon"></i>
                                <div>
                                    <div style="font-weight: 600;">Browse Pets</div>
                                    <div style="font-size: 0.85rem; opacity: 0.8;">Find your perfect companion</div>
                                </div>
                            </a>

                            <a href="myAdoptions.php" class="quick-action">
                                <i class="fas fa-heart quick-action-icon"></i>
                                <div>
                                    <div style="font-weight: 600;">My Adoptions</div>
                                    <div style="font-size: 0.85rem; opacity: 0.8;">Track your applications</div>
                                </div>
                            </a>

                            <a href="careGuides.php" class="quick-action">
                                <i class="fas fa-book-open quick-action-icon"></i>
                                <div>
                                    <div style="font-weight: 600;">Care Guides</div>
                                    <div style="font-size: 0.85rem; opacity: 0.8;">Learn pet care tips</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Reminders -->
                <?php if (!empty($upcoming_reminders)): ?>
                <div class="content-section fade-in">
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-bell"></i>
                            Reminders
                        </h3>
                    </div>
                    <div class="section-body">
                        <?php foreach ($upcoming_reminders as $reminder): ?>
                        <div class="reminder-item">
                            <i class="fas fa-bell reminder-icon"></i>
                            <div class="reminder-content">
                                <strong><?php echo htmlspecialchars($reminder['message']); ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recommended Pets Section -->
        <?php if (!empty($recommended_pets)): ?>
        <div class="content-section fade-in">
            <div class="section-header">
                <h3>
                    <i class="fas fa-star"></i>
                    Recommended for You
                </h3>
            </div>
            <div class="section-body">
                <div class="pet-grid">
                    <?php foreach ($recommended_pets as $pet): ?>
                    <div class="pet-card"
                        onclick="window.location.href='petDetails.php?id=<?php echo $pet['pet_id']; ?>'">
                        <div class="pet-card-image">
                            <?php if ($pet['primary_image']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                                alt="<?php echo htmlspecialchars($pet['pet_name']); ?>">
                            <?php else: ?>
                            <i class="fas fa-paw"></i>
                            <?php endif; ?>
                        </div>

                        <div class="pet-card-content">
                            <div class="pet-card-title"><?php echo htmlspecialchars($pet['pet_name']); ?></div>
                            <div class="pet-card-details">
                                <?php echo htmlspecialchars($pet['category_name']); ?>
                                <?php if ($pet['breed_name']): ?>
                                • <?php echo htmlspecialchars($pet['breed_name']); ?>
                                <?php endif; ?>
                                <br>
                                <?php echo $pet['age']; ?> years old • <?php echo ucfirst($pet['gender']); ?>
                                <br>
                                <i class="fas fa-home"></i> <?php echo htmlspecialchars($pet['shelter_name']); ?>
                            </div>

                            <div class="pet-card-footer">
                                <div class="adoption-fee">
                                    $<?php echo number_format($pet['adoption_fee'], 2); ?>
                                </div>
                                <button class="btn btn-primary">
                                    <i class="fas fa-heart"></i>
                                    View Details
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Adopted Pets Section -->
        <?php if (!empty($adopted_pets)): ?>
        <div class="content-section fade-in" style="margin-top: 30px;">
            <div class="section-header">
                <h3>
                    <i class="fas fa-heart"></i>
                    Your Adopted Pets
                </h3>
            </div>
            <div class="section-body">
                <div class="pet-grid">
                    <?php foreach ($adopted_pets as $pet): ?>
                    <div class="pet-card">
                        <div class="pet-card-image">
                            <?php if ($pet['primary_image']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                                alt="<?php echo htmlspecialchars($pet['pet_name']); ?>">
                            <?php else: ?>
                            <i class="fas fa-paw"></i>
                            <?php endif; ?>
                        </div>

                        <div class="pet-card-content">
                            <div class="pet-card-title">
                                <?php echo htmlspecialchars($pet['pet_name']); ?>
                                <i class="fas fa-heart" style="color: #e74c3c; margin-left: 5px;"></i>
                            </div>
                            <div class="pet-card-details">
                                <?php echo htmlspecialchars($pet['category_name']); ?> •
                                <?php echo $pet['age']; ?> years old •
                                <?php echo ucfirst($pet['gender']); ?>
                                <br>
                                <i class="fas fa-calendar"></i>
                                Adopted <?php echo $pet['days_since_adoption']; ?>
                                day<?php echo $pet['days_since_adoption'] != 1 ? 's' : ''; ?> ago
                            </div>

                            <div class="pet-card-footer">
                                <div style="color: #2e7d32; font-weight: 600;">
                                    <i class="fas fa-home"></i>
                                    Family Member
                                </div>
                                <button class="btn btn-outline" onclick="window.location.href='careGuides.php'">
                                    <i class="fas fa-book-open"></i>
                                    Care Guide
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // Page loading and animations
    document.addEventListener('DOMContentLoaded', function() {
        // Add fade-in animation to elements
        const elements = document.querySelectorAll('.fade-in');
        elements.forEach((el, index) => {
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';

                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 50);
            }, index * 100);
        });

        // Add click effects to cards
        const cards = document.querySelectorAll('.stat-card, .pet-card, .application-item');
        cards.forEach(card => {
            card.addEventListener('click', function(e) {
                // Add ripple effect
                const ripple = document.createElement('div');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);

                ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(102, 126, 234, 0.3);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${e.clientX - rect.left - size/2}px;
                        top: ${e.clientY - rect.top - size/2}px;
                        pointer-events: none;
                    `;

                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);

                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Auto-refresh stats every 5 minutes
        setInterval(refreshStats, 300000);
    });

    // Add ripple animation CSS
    const style = document.createElement('style');
    style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
    document.head.appendChild(style);

    // Function to refresh statistics
    function refreshStats() {
        // You can implement AJAX call to refresh stats without page reload
        console.log('Refreshing stats...');
    }

    // Add smooth scrolling for anchor links
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

    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        // Press 'B' to go to Browse Pets
        if (e.key === 'b' || e.key === 'B') {
            if (!e.target.matches('input, textarea, select')) {
                window.location.href = 'browsePets.php';
            }
        }

        // Press 'M' to go to My Adoptions
        if (e.key === 'm' || e.key === 'M') {
            if (!e.target.matches('input, textarea, select')) {
                window.location.href = 'myAdoptions.php';
            }
        }
    });

    // Show keyboard shortcuts on help
    function showKeyboardShortcuts() {
        alert('Keyboard Shortcuts:\nB - Browse Pets\nM - My Adoptions\nG - Care Guides');
    }

    // Add help shortcut
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === '/') {
            e.preventDefault();
            showKeyboardShortcuts();
        }
    });
    </script>
</body>

</html>