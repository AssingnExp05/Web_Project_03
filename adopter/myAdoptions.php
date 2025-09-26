<?php
// Start session and include necessary files
require_once '../config/db.php';

// Check if user is logged in as adopter
if (!Session::isLoggedIn() || Session::getUserType() !== 'adopter') {
    header('Location: ../auth/login.php');
    exit();
}

$adopter_id = Session::getUserId();
$applications = [];
$adoptions = [];
$error_message = null;

// Handle application withdrawal
if (isset($_POST['withdraw_application'])) {
    $application_id = Security::sanitize($_POST['application_id']);
    
    try {
        $withdraw_query = "DELETE FROM adoption_applications WHERE application_id = ? AND adopter_id = ? AND application_status = 'pending'";
        $affected_rows = DBHelper::execute($withdraw_query, [$application_id, $adopter_id]);
        
        if ($affected_rows > 0) {
            Session::set('success_message', "Application withdrawn successfully.");
        } else {
            Session::set('error_message', "Failed to withdraw application or application is no longer pending.");
        }
        
        // Redirect to prevent form resubmission
        header('Location: myAdoptions.php');
        exit();
        
    } catch (Exception $e) {
        Session::set('error_message', "Error withdrawing application: " . $e->getMessage());
        header('Location: myAdoptions.php');
        exit();
    }
}

try {
    // Get adoption applications with pet and shelter information
    $applications_query = "
        SELECT 
            aa.application_id,
            aa.pet_id,
            aa.application_status,
            aa.housing_type,
            aa.has_experience,
            aa.reason_for_adoption,
            aa.application_date,
            p.pet_name,
            p.age,
            p.gender,
            p.size,
            p.primary_image,
            p.adoption_fee,
            pc.category_name,
            COALESCE(pb.breed_name, 'Mixed Breed') as breed_name,
            s.shelter_name,
            u.first_name as shelter_contact_first_name,
            u.last_name as shelter_contact_last_name,
            COALESCE(u.phone, 'Not provided') as shelter_phone,
            u.email as shelter_email
        FROM adoption_applications aa
        JOIN pets p ON aa.pet_id = p.pet_id
        JOIN shelters s ON aa.shelter_id = s.shelter_id
        JOIN users u ON s.user_id = u.user_id
        JOIN pet_categories pc ON p.category_id = pc.category_id
        LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
        WHERE aa.adopter_id = ?
        ORDER BY aa.application_date DESC
    ";
    
    $applications = DBHelper::select($applications_query, [$adopter_id]);
    
    if ($applications === false) {
        $applications = [];
        $error_message = "Error fetching application data.";
    }

    // Get completed adoptions
    $adoptions_query = "
        SELECT 
            ad.adoption_id,
            ad.pet_id,
            ad.adoption_date,
            ad.adoption_fee_paid,
            p.pet_name,
            p.age,
            p.gender,
            p.size,
            p.primary_image,
            pc.category_name,
            COALESCE(pb.breed_name, 'Mixed Breed') as breed_name,
            s.shelter_name,
            u.first_name as shelter_contact_first_name,
            u.last_name as shelter_contact_last_name,
            COALESCE(u.phone, 'Not provided') as shelter_phone,
            u.email as shelter_email
        FROM adoptions ad
        JOIN pets p ON ad.pet_id = p.pet_id
        JOIN shelters s ON ad.shelter_id = s.shelter_id
        JOIN users u ON s.user_id = u.user_id
        JOIN pet_categories pc ON p.category_id = pc.category_id
        LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
        WHERE ad.adopter_id = ?
        ORDER BY ad.adoption_date DESC
    ";
    
    $adoptions = DBHelper::select($adoptions_query, [$adopter_id]);
    
    if ($adoptions === false) {
        $adoptions = [];
        if (!$error_message) {
            $error_message = "Error fetching adoption data.";
        }
    }

} catch (Exception $e) {
    $error_message = "Error fetching data: " . $e->getMessage();
    error_log($error_message);
}

// Ensure arrays are not null
$applications = $applications ?: [];
$adoptions = $adoptions ?: [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Adoptions - Pet Adoption Care Guide</title>
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

    .main-content {
        padding: 30px 20px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .page-header {
        text-align: center;
        margin-bottom: 40px;
        color: #fff;
    }

    .page-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 10px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    .page-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        font-weight: 300;
    }

    .alert {
        padding: 15px 20px;
        margin-bottom: 25px;
        border-radius: 10px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease;
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

    .alert-success {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-error {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .tabs-container {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        overflow: hidden;
    }

    .tabs-header {
        display: flex;
        background: linear-gradient(135deg, #2c3e50, #34495e);
    }

    .tab-btn {
        flex: 1;
        padding: 20px;
        background: none;
        border: none;
        color: #bdc3c7;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .tab-btn.active {
        color: #fff;
        background: linear-gradient(135deg, #3498db, #2980b9);
    }

    .tab-btn:not(.active):hover {
        background: rgba(255, 255, 255, 0.1);
        color: #ecf0f1;
    }

    .tab-content {
        display: none;
        padding: 30px;
        background: #fff;
    }

    .tab-content.active {
        display: block;
    }

    .applications-grid,
    .adoptions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }

    .application-card,
    .adoption-card {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid #e9ecef;
    }

    .application-card:hover,
    .adoption-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
    }

    .card-image {
        width: 100%;
        height: 200px;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }

    .card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .card-image:hover img {
        transform: scale(1.05);
    }

    .card-image .no-image {
        color: #6c757d;
        font-size: 3rem;
    }

    .status-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-pending {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: #fff;
    }

    .status-approved {
        background: linear-gradient(135deg, #27ae60, #2ecc71);
        color: #fff;
    }

    .status-rejected {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: #fff;
    }

    .status-adopted {
        background: linear-gradient(135deg, #8e44ad, #9b59b6);
        color: #fff;
    }

    .card-content {
        padding: 25px;
    }

    .pet-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 8px;
    }

    .pet-details {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 15px;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.9rem;
        color: #6c757d;
    }

    .detail-item i {
        width: 16px;
        color: #3498db;
    }

    .shelter-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin: 15px 0;
    }

    .shelter-name {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .shelter-contact {
        font-size: 0.9rem;
        color: #6c757d;
    }

    .application-date,
    .adoption-date {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .card-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
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
        flex: 1;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: #fff;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #2980b9, #21618c);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
    }

    .btn-danger {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: #fff;
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, #c0392b, #a93226);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, #27ae60, #2ecc71);
        color: #fff;
    }

    .btn-success:hover {
        background: linear-gradient(135deg, #2ecc71, #58d68d);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 10px;
        color: #495057;
    }

    .empty-state p {
        font-size: 1.1rem;
        margin-bottom: 25px;
    }

    .fee-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        background: linear-gradient(135deg, #e8f5e8, #d4edda);
        border-radius: 8px;
        margin: 10px 0;
    }

    .fee-label {
        font-weight: 600;
        color: #155724;
    }

    .fee-amount {
        font-size: 1.1rem;
        font-weight: 700;
        color: #155724;
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
        background-color: rgba(0, 0, 0, 0.5);
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

    .modal-content {
        background: #fff;
        padding: 30px;
        border-radius: 15px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        position: relative;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e9ecef;
    }

    .modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #2c3e50;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #6c757d;
        cursor: pointer;
        padding: 5px;
        transition: color 0.3s ease;
    }

    .close-modal:hover {
        color: #e74c3c;
    }

    .modal-body {
        padding: 0;
    }

    .modal-body h3 {
        color: #2c3e50;
        margin-bottom: 15px;
        margin-top: 25px;
        font-size: 1.2rem;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 8px;
    }

    .modal-body h3:first-child {
        margin-top: 0;
    }

    .modal-body p {
        margin-bottom: 10px;
        line-height: 1.6;
    }

    .modal-body strong {
        color: #495057;
        display: inline-block;
        min-width: 120px;
    }

    .reason-text {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-top: 8px;
        border-left: 4px solid #3498db;
        font-style: italic;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .main-content {
            padding: 20px 10px;
        }

        .page-title {
            font-size: 2rem;
        }

        .tabs-header {
            flex-direction: column;
        }

        .tab-btn {
            padding: 15px;
        }

        .applications-grid,
        .adoptions-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .card-actions {
            flex-direction: column;
        }

        .pet-details {
            flex-direction: column;
            gap: 8px;
        }

        .tab-content {
            padding: 20px 15px;
        }
    }

    @media (max-width: 480px) {
        .page-title {
            font-size: 1.5rem;
        }

        .card-content {
            padding: 15px;
        }

        .modal-content {
            padding: 20px;
            margin: 10px;
        }
    }
    </style>
</head>

<body>
    <!-- Include Adopter Navigation -->
    <?php include '../common/navbar_adopter.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">My Adoptions</h1>
            <p class="page-subtitle">Track your adoption applications and manage your adopted pets</p>
        </div>

        <!-- Display Messages -->
        <?php if (Session::has('success_message')): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo Security::sanitize(Session::get('success_message')); Session::remove('success_message'); ?>
        </div>
        <?php endif; ?>

        <?php if (Session::has('error_message')): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo Security::sanitize(Session::get('error_message')); Session::remove('error_message'); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo Security::sanitize($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Tabs Container -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-btn active" onclick="showTab('applications', this)">
                    <i class="fas fa-file-alt"></i>
                    Applications (<?php echo count($applications); ?>)
                </button>
                <button class="tab-btn" onclick="showTab('adoptions', this)">
                    <i class="fas fa-heart"></i>
                    Adopted Pets (<?php echo count($adoptions); ?>)
                </button>
            </div>

            <!-- Applications Tab -->
            <div id="applications" class="tab-content active">
                <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Applications Yet</h3>
                    <p>You haven't submitted any adoption applications.</p>
                    <a href="browsePets.php" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Browse Available Pets
                    </a>
                </div>
                <?php else: ?>
                <div class="applications-grid">
                    <?php foreach ($applications as $app): ?>
                    <div class="application-card">
                        <div class="card-image">
                            <?php if (!empty($app['primary_image']) && file_exists("../uploads/" . $app['primary_image'])): ?>
                            <img src="../uploads/<?php echo Security::sanitize($app['primary_image']); ?>"
                                alt="<?php echo Security::sanitize($app['pet_name']); ?>" loading="lazy">
                            <?php else: ?>
                            <i class="fas fa-paw no-image"></i>
                            <?php endif; ?>

                            <div class="status-badge status-<?php echo $app['application_status']; ?>">
                                <?php echo ucfirst($app['application_status']); ?>
                            </div>
                        </div>

                        <div class="card-content">
                            <h3 class="pet-name"><?php echo Security::sanitize($app['pet_name']); ?></h3>

                            <div class="pet-details">
                                <div class="detail-item">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo Security::sanitize($app['category_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-dna"></i>
                                    <span><?php echo Security::sanitize($app['breed_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-birthday-cake"></i>
                                    <span><?php echo (int)$app['age']; ?> years old</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-venus-mars"></i>
                                    <span><?php echo ucfirst($app['gender']); ?></span>
                                </div>
                                <?php if (!empty($app['size'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-ruler"></i>
                                    <span><?php echo ucfirst($app['size']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="shelter-info">
                                <div class="shelter-name">
                                    <i class="fas fa-home"></i>
                                    <?php echo Security::sanitize($app['shelter_name']); ?>
                                </div>
                                <div class="shelter-contact">
                                    Contact:
                                    <?php echo Security::sanitize($app['shelter_contact_first_name'] . ' ' . $app['shelter_contact_last_name']); ?>
                                </div>
                            </div>

                            <?php if ($app['adoption_fee'] > 0): ?>
                            <div class="fee-info">
                                <span class="fee-label">Adoption Fee:</span>
                                <span
                                    class="fee-amount">$<?php echo number_format((float)$app['adoption_fee'], 2); ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="application-date">
                                <i class="fas fa-calendar"></i>
                                Applied on <?php echo date('M j, Y', strtotime($app['application_date'])); ?>
                            </div>

                            <div class="card-actions">
                                <button class="btn btn-primary"
                                    onclick="viewApplication(<?php echo (int)$app['application_id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </button>

                                <?php if ($app['application_status'] === 'pending'): ?>
                                <button class="btn btn-danger"
                                    onclick="confirmWithdraw(<?php echo (int)$app['application_id']; ?>)">
                                    <i class="fas fa-times"></i>
                                    Withdraw
                                </button>
                                <?php endif; ?>

                                <?php if ($app['application_status'] === 'approved' && $app['shelter_phone'] !== 'Not provided'): ?>
                                <a href="tel:<?php echo Security::sanitize($app['shelter_phone']); ?>"
                                    class="btn btn-success">
                                    <i class="fas fa-phone"></i>
                                    Call Shelter
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Adoptions Tab -->
            <div id="adoptions" class="tab-content">
                <?php if (empty($adoptions)): ?>
                <div class="empty-state">
                    <i class="fas fa-heart"></i>
                    <h3>No Adopted Pets Yet</h3>
                    <p>You haven't completed any adoptions yet.</p>
                    <a href="browsePets.php" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                        Find Your Perfect Pet
                    </a>
                </div>
                <?php else: ?>
                <div class="adoptions-grid">
                    <?php foreach ($adoptions as $adoption): ?>
                    <div class="adoption-card">
                        <div class="card-image">
                            <?php if (!empty($adoption['primary_image']) && file_exists("../uploads/" . $adoption['primary_image'])): ?>
                            <img src="../uploads/<?php echo Security::sanitize($adoption['primary_image']); ?>"
                                alt="<?php echo Security::sanitize($adoption['pet_name']); ?>" loading="lazy">
                            <?php else: ?>
                            <i class="fas fa-paw no-image"></i>
                            <?php endif; ?>

                            <div class="status-badge status-adopted">
                                Adopted
                            </div>
                        </div>

                        <div class="card-content">
                            <h3 class="pet-name"><?php echo Security::sanitize($adoption['pet_name']); ?></h3>

                            <div class="pet-details">
                                <div class="detail-item">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo Security::sanitize($adoption['category_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-dna"></i>
                                    <span><?php echo Security::sanitize($adoption['breed_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-birthday-cake"></i>
                                    <span><?php echo (int)$adoption['age']; ?> years old</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-venus-mars"></i>
                                    <span><?php echo ucfirst($adoption['gender']); ?></span>
                                </div>
                                <?php if (!empty($adoption['size'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-ruler"></i>
                                    <span><?php echo ucfirst($adoption['size']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="shelter-info">
                                <div class="shelter-name">
                                    <i class="fas fa-home"></i>
                                    <?php echo Security::sanitize($adoption['shelter_name']); ?>
                                </div>
                            </div>

                            <?php if ($adoption['adoption_fee_paid'] > 0): ?>
                            <div class="fee-info">
                                <span class="fee-label">Fee Paid:</span>
                                <span
                                    class="fee-amount">$<?php echo number_format((float)$adoption['adoption_fee_paid'], 2); ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="adoption-date">
                                <i class="fas fa-heart"></i>
                                Adopted on <?php echo date('M j, Y', strtotime($adoption['adoption_date'])); ?>
                            </div>

                            <div class="card-actions">
                                <a href="careGuides.php" class="btn btn-primary">
                                    <i class="fas fa-book-open"></i>
                                    Care Guides
                                </a>

                                <?php if ($adoption['shelter_phone'] !== 'Not provided'): ?>
                                <a href="tel:<?php echo Security::sanitize($adoption['shelter_phone']); ?>"
                                    class="btn btn-success">
                                    <i class="fas fa-phone"></i>
                                    Call Shelter
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Application Details Modal -->
    <div id="applicationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Application Details</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalBody" class="modal-body">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Withdraw Confirmation Modal -->
    <div id="withdrawModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Withdrawal</h2>
                <button class="close-modal" onclick="closeWithdrawModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p><strong>Are you sure you want to withdraw this application?</strong></p>
                <p>This action cannot be undone, and you will need to reapply if you change your mind.</p>
                <div class="card-actions" style="margin-top: 20px;">
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="application_id" id="withdrawApplicationId">
                        <button type="submit" name="withdraw_application" class="btn btn-danger" style="width: 100%;">
                            <i class="fas fa-times"></i>
                            Yes, Withdraw Application
                        </button>
                    </form>
                    <button class="btn btn-primary" onclick="closeWithdrawModal()" style="flex: 1;">
                        <i class="fas fa-arrow-left"></i>
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Store applications data for JavaScript access
    const applications = <?php echo json_encode($applications); ?>;

    // Tab functionality
    function showTab(tabName, element) {
        // Hide all tab contents
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => {
            content.classList.remove('active');
        });

        // Remove active class from all tab buttons
        const tabBtns = document.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => {
            btn.classList.remove('active');
        });

        // Show selected tab content
        document.getElementById(tabName).classList.add('active');

        // Add active class to clicked tab button
        element.classList.add('active');
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? String(text).replace(/[&<>"']/g, function(m) {
            return map[m];
        }) : '';
    }

    // View application details
    function viewApplication(applicationId) {
        // Find the application data
        const application = applications.find(app => app.application_id == applicationId);

        if (application) {
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                    <h3>Pet Information</h3>
                    <p><strong>Name:</strong> ${escapeHtml(application.pet_name)}</p>
                    <p><strong>Category:</strong> ${escapeHtml(application.category_name)}</p>
                    <p><strong>Breed:</strong> ${escapeHtml(application.breed_name)}</p>
                    <p><strong>Age:</strong> ${escapeHtml(application.age)} years old</p>
                    <p><strong>Gender:</strong> ${escapeHtml(application.gender)}</p>
                    <p><strong>Size:</strong> ${escapeHtml(application.size || 'Not specified')}</p>
                    ${application.adoption_fee > 0 ? `<p><strong>Adoption Fee:</strong> $${parseFloat(application.adoption_fee).toFixed(2)}</p>` : ''}
                    
                    <h3>Application Details</h3>
                    <p><strong>Housing Type:</strong> ${escapeHtml(application.housing_type || 'Not specified')}</p>
                    <p><strong>Has Pet Experience:</strong> ${application.has_experience ? 'Yes' : 'No'}</p>
                    <p><strong>Reason for Adoption:</strong></p>
                    <div class="reason-text">
                        ${escapeHtml(application.reason_for_adoption || 'No reason provided')}
                    </div>
                    
                    <h3>Shelter Information</h3>
                    <p><strong>Shelter:</strong> ${escapeHtml(application.shelter_name)}</p>
                    <p><strong>Contact Person:</strong> ${escapeHtml(application.shelter_contact_first_name + ' ' + application.shelter_contact_last_name)}</p>
                    <p><strong>Phone:</strong> ${escapeHtml(application.shelter_phone)}</p>
                    <p><strong>Email:</strong> ${escapeHtml(application.shelter_email)}</p>
                    
                    <h3>Status Information</h3>
                    <p><strong>Applied on:</strong> ${new Date(application.application_date).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    })}</p>
                    <p><strong>Current Status:</strong> <span class="status-badge status-${application.application_status}">${application.application_status.charAt(0).toUpperCase() + application.application_status.slice(1)}</span></p>
                `;

            document.getElementById('applicationModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        } else {
            alert('Application details not found.');
        }
    }

    // Close modal
    function closeModal() {
        document.getElementById('applicationModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    // Confirm withdrawal
    function confirmWithdraw(applicationId) {
        document.getElementById('withdrawApplicationId').value = applicationId;
        document.getElementById('withdrawModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    // Close withdraw modal
    function closeWithdrawModal() {
        document.getElementById('withdrawModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        const applicationModal = document.getElementById('applicationModal');
        const withdrawModal = document.getElementById('withdrawModal');

        if (event.target === applicationModal) {
            closeModal();
        }

        if (event.target === withdrawModal) {
            closeWithdrawModal();
        }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
            closeWithdrawModal();
        }
    });

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.3s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 300);
            }, 5000);
        });
    });

    // Image error handling
    document.addEventListener('DOMContentLoaded', function() {
        const images = document.querySelectorAll('.card-image img');
        images.forEach(img => {
            img.addEventListener('error', function() {
                this.style.display = 'none';
                const noImageIcon = this.parentNode.querySelector('.no-image');
                if (noImageIcon) {
                    noImageIcon.style.display = 'flex';
                } else {
                    // Create and show fallback icon if it doesn't exist
                    const fallbackIcon = document.createElement('i');
                    fallbackIcon.className = 'fas fa-paw no-image';
                    this.parentNode.appendChild(fallbackIcon);
                }
            });
        });
    });

    // Smooth scrolling for mobile tabs
    function smoothScrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // Add click event to tab buttons for mobile smooth scroll
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Small delay to allow tab switch animation
            setTimeout(smoothScrollToTop, 100);
        });
    });

    // Loading state for buttons
    function addLoadingState(button) {
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

        return function() {
            button.disabled = false;
            button.innerHTML = originalText;
        };
    }

    // Enhanced form submission with loading state
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                addLoadingState(submitBtn);
            }
        });
    });

    // Add tooltip functionality for truncated text
    function addTooltips() {
        const elements = document.querySelectorAll('.pet-name, .shelter-name');
        elements.forEach(el => {
            if (el.scrollWidth > el.clientWidth) {
                el.title = el.textContent;
            }
        });
    }

    // Initialize tooltips when DOM is loaded
    document.addEventListener('DOMContentLoaded', addTooltips);

    // Refresh tooltips when window is resized
    window.addEventListener('resize', addTooltips);

    // Intersection Observer for animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Apply animation to cards when they come into view
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.application-card, .adoption-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition =
                `opacity 0.5s ease ${index * 0.1}s, transform 0.5s ease ${index * 0.1}s`;
            observer.observe(card);
        });
    });

    // Add keyboard navigation for accessibility
    document.addEventListener('keydown', function(event) {
        // Tab navigation for modals
        if (event.key === 'Tab') {
            const activeModal = document.querySelector('.modal.show');
            if (activeModal) {
                const focusableElements = activeModal.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );

                if (focusableElements.length === 0) return;

                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];

                if (event.shiftKey) {
                    if (document.activeElement === firstElement) {
                        lastElement.focus();
                        event.preventDefault();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        firstElement.focus();
                        event.preventDefault();
                    }
                }
            }
        }
    });

    // Focus management for modals
    function focusModal(modalId) {
        const modal = document.getElementById(modalId);
        const firstFocusable = modal.querySelector(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (firstFocusable) {
            firstFocusable.focus();
        }
    }

    // Update view application function to include focus management
    const originalViewApplication = viewApplication;
    viewApplication = function(applicationId) {
        originalViewApplication(applicationId);
        setTimeout(() => focusModal('applicationModal'), 100);
    };

    // Update confirm withdraw function to include focus management
    const originalConfirmWithdraw = confirmWithdraw;
    confirmWithdraw = function(applicationId) {
        originalConfirmWithdraw(applicationId);
        setTimeout(() => focusModal('withdrawModal'), 100);
    };

    // Add ripple effect to buttons
    function createRipple(event) {
        const button = event.currentTarget;
        const circle = document.createElement('span');
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;

        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
        circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
        circle.classList.add('ripple');

        const ripple = button.getElementsByClassName('ripple')[0];
        if (ripple) {
            ripple.remove();
        }

        button.appendChild(circle);
    }

    // Apply ripple effect to buttons
    document.addEventListener('DOMContentLoaded', function() {
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', createRipple);
        });
    });

    // Add CSS for ripple effect
    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = `
            .btn {
                position: relative;
                overflow: hidden;
            }
            
            .ripple {
                position: absolute;
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 600ms linear;
                background-color: rgba(255, 255, 255, 0.6);
            }
            
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
    document.head.appendChild(rippleStyle);

    // Performance optimization: Lazy load images
    if ('IntersectionObserver' in window) {
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

        // Apply lazy loading to images (if implemented in PHP)
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }

    // Add success animation for actions
    function showSuccessAnimation(element) {
        element.style.transform = 'scale(1.05)';
        element.style.transition = 'transform 0.2s ease';

        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 200);
    }

    // Error handling for network issues
    window.addEventListener('online', function() {
        const alerts = document.querySelectorAll('.alert-error');
        alerts.forEach(alert => {
            if (alert.textContent.includes('network') || alert.textContent.includes('connection')) {
                alert.style.display = 'none';
            }
        });
    });

    window.addEventListener('offline', function() {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-error';
        alertDiv.innerHTML =
            '<i class="fas fa-wifi"></i> You are currently offline. Some features may not work properly.';

        const mainContent = document.querySelector('.main-content');
        mainContent.insertBefore(alertDiv, mainContent.firstChild);
    });

    // Print functionality
    function printApplicationDetails(applicationId) {
        const application = applications.find(app => app.application_id == applicationId);
        if (application) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Application Details - ${escapeHtml(application.pet_name)}</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
                            .info-section { margin-bottom: 20px; }
                            .status-badge { padding: 5px 10px; border-radius: 15px; font-weight: bold; }
                            .status-pending { background: #f39c12; color: white; }
                            .status-approved { background: #27ae60; color: white; }
                            .status-rejected { background: #e74c3c; color: white; }
                        </style>
                    </head>
                    <body>
                        <h1>Adoption Application Details</h1>
                        
                        <div class="info-section">
                            <h2>Pet Information</h2>
                            <p><strong>Name:</strong> ${escapeHtml(application.pet_name)}</p>
                            <p><strong>Category:</strong> ${escapeHtml(application.category_name)}</p>
                            <p><strong>Breed:</strong> ${escapeHtml(application.breed_name)}</p>
                            <p><strong>Age:</strong> ${escapeHtml(application.age)} years old</p>
                            <p><strong>Gender:</strong> ${escapeHtml(application.gender)}</p>
                            <p><strong>Size:</strong> ${escapeHtml(application.size || 'Not specified')}</p>
                        </div>
                        
                        <div class="info-section">
                            <h2>Application Details</h2>
                            <p><strong>Applied on:</strong> ${new Date(application.application_date).toLocaleDateString()}</p>
                            <p><strong>Status:</strong> <span class="status-badge status-${application.application_status}">${application.application_status.charAt(0).toUpperCase() + application.application_status.slice(1)}</span></p>
                            <p><strong>Housing Type:</strong> ${escapeHtml(application.housing_type || 'Not specified')}</p>
                            <p><strong>Has Pet Experience:</strong> ${application.has_experience ? 'Yes' : 'No'}</p>
                            <p><strong>Reason for Adoption:</strong></p>
                            <p style="margin-left: 20px; font-style: italic;">${escapeHtml(application.reason_for_adoption || 'No reason provided')}</p>
                        </div>
                        
                        <div class="info-section">
                            <h2>Shelter Information</h2>
                            <p><strong>Shelter:</strong> ${escapeHtml(application.shelter_name)}</p>
                            <p><strong>Contact Person:</strong> ${escapeHtml(application.shelter_contact_first_name + ' ' + application.shelter_contact_last_name)}</p>
                            <p><strong>Phone:</strong> ${escapeHtml(application.shelter_phone)}</p>
                            <p><strong>Email:</strong> ${escapeHtml(application.shelter_email)}</p>
                        </div>
                        
                        <p style="margin-top: 40px; font-size: 12px; color: #666;">
                            Generated on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}
                        </p>
                    </body>
                    </html>
                `);
            printWindow.document.close();
            printWindow.print();
        }
    }

    console.log('My Adoptions page loaded successfully');
    console.log(`Applications: ${applications.length}`);
    </script>
</body>

</html>