<?php
// adopter/dashboard.php - Adopter Dashboard (Complete Fixed Version)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Base URL
$BASE_URL = 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Check if user is logged in and is an adopter
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'adopter') {
    $_SESSION['error_message'] = 'Please login as an adopter to access this page.';
    header('Location: ' . $BASE_URL . 'auth/login.php');
    exit();
}

$adopter_user_id = $_SESSION['user_id'];
$adopter_name = $_SESSION['first_name'] ?? 'User';
$page_title = 'Adopter Dashboard - Pet Adoption Care Guide';

// Initialize variables
$adopter_info = null;
$adoption_stats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'total_applications' => 0
];
$completed_adoptions = 0;
$recent_applications = [];
$recommended_pets = [];
$adopted_pets = [];
$care_guides_count = 0;
$upcoming_reminders = [];
$error_message = '';

try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Get adopter's basic information
    $stmt = $db->prepare("SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as full_name FROM users u WHERE u.user_id = ?");
    $stmt->execute([$adopter_user_id]);
    $adopter_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$adopter_info) {
        throw new Exception("User information not found");
    }

    // Get adoption applications statistics
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ? AND application_status = 'pending'");
    $stmt->execute([$adopter_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $adoption_stats['pending'] = $result ? (int)$result['count'] : 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ? AND application_status = 'approved'");
    $stmt->execute([$adopter_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $adoption_stats['approved'] = $result ? (int)$result['count'] : 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ? AND application_status = 'rejected'");
    $stmt->execute([$adopter_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $adoption_stats['rejected'] = $result ? (int)$result['count'] : 0;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE adopter_id = ?");
    $stmt->execute([$adopter_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $adoption_stats['total_applications'] = $result ? (int)$result['count'] : 0;

    // Get completed adoptions count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoptions WHERE adopter_id = ?");
    $stmt->execute([$adopter_user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $completed_adoptions = $result ? (int)$result['count'] : 0;

    // Get recent adoption applications
    $stmt = $db->prepare("
        SELECT aa.*, p.pet_name, p.primary_image, p.age, p.gender, p.adoption_fee,
                pc.category_name, pb.breed_name, s.shelter_name,
                DATEDIFF(CURDATE(), aa.application_date) as days_ago
        FROM adoption_applications aa
        INNER JOIN pets p ON aa.pet_id = p.pet_id
        INNER JOIN pet_categories pc ON p.category_id = pc.category_id
        LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
        INNER JOIN shelters s ON aa.shelter_id = s.shelter_id
        WHERE aa.adopter_id = ?
        ORDER BY aa.application_date DESC
        LIMIT 5
    ");
    $stmt->execute([$adopter_user_id]);
    $recent_applications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get recommended pets (available pets that might interest the adopter)
    $stmt = $db->prepare("
        SELECT p.*, pc.category_name, pb.breed_name, s.shelter_name,
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
        LIMIT 6
    ");
    $stmt->execute([$adopter_user_id]);
    $recommended_pets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get adopted pets (completed adoptions)
    $stmt = $db->prepare("
        SELECT a.*, p.pet_name, p.primary_image, p.age, p.gender,
                pc.category_name, s.shelter_name,
                DATEDIFF(CURDATE(), a.adoption_date) as days_since_adoption
        FROM adoptions a
        INNER JOIN pets p ON a.pet_id = p.pet_id
        INNER JOIN pet_categories pc ON p.category_id = pc.category_id
        INNER JOIN shelters s ON a.shelter_id = s.shelter_id
        WHERE a.adopter_id = ?
        ORDER BY a.adoption_date DESC
        LIMIT 3
    ");
    $stmt->execute([$adopter_user_id]);
    $adopted_pets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Get care guides count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM care_guides WHERE is_published = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $care_guides_count = $result ? (int)$result['count'] : 0;

    // Generate reminders for adopted pets
    foreach ($adopted_pets as $pet) {
        if ($pet['days_since_adoption'] > 0 && $pet['days_since_adoption'] % 30 == 0) {
            $upcoming_reminders[] = [
                'type' => 'checkup',
                'message' => "Monthly checkup reminder for " . $pet['pet_name'],
                'pet_name' => $pet['pet_name'],
                'days' => 0
            ];
        }
    }

} catch (Exception $e) {
    error_log("Dashboard database error: " . $e->getMessage());
    $error_message = "Unable to load dashboard data. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        color: #333;
        line-height: 1.6;
    }

    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Welcome Section */
    .welcome-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px;
        border-radius: 20px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .welcome-section::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {

        0%,
        100% {
            transform: translateY(0px) rotate(0deg);
        }

        50% {
            transform: translateY(-20px) rotate(5deg);
        }
    }

    .welcome-content {
        position: relative;
        z-index: 2;
    }

    .welcome-title {
        font-size: 2.8rem;
        font-weight: 700;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .welcome-subtitle {
        font-size: 1.3rem;
        opacity: 0.95;
        font-weight: 300;
        margin-bottom: 25px;
    }

    .user-info-card {
        padding: 25px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 15px;
        display: flex;
        align-items: center;
        gap: 20px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .user-avatar-large {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1.8rem;
        box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
        flex-shrink: 0;
    }

    .user-details {
        flex: 1;
    }

    .user-name {
        font-weight: 700;
        font-size: 1.3rem;
        margin-bottom: 8px;
    }

    .user-info-item {
        display: flex;
        align-items: center;
        gap: 8px;
        opacity: 0.9;
        margin-bottom: 5px;
        font-size: 0.95rem;
    }

    /* Statistics Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 30px 25px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        border-top: 5px solid var(--card-color, #667eea);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        transition: left 0.5s;
    }

    .stat-card:hover::before {
        left: 100%;
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
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
        font-size: 3rem;
        margin-bottom: 15px;
        color: var(--card-color, #667eea);
        position: relative;
        z-index: 2;
    }

    .stat-number {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--card-color, #667eea);
        position: relative;
        z-index: 2;
    }

    .stat-label {
        font-size: 1rem;
        color: #666;
        font-weight: 500;
        position: relative;
        z-index: 2;
    }

    /* Main Content Grid */
    .main-content {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .content-section {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .section-header {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 30px;
        border-bottom: 1px solid #dee2e6;
    }

    .section-title {
        color: #495057;
        font-size: 1.5rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 0;
    }

    .section-body {
        padding: 30px;
    }

    /* Application Items */
    .application-item {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 20px;
        border: 2px solid #f8f9fa;
        border-radius: 15px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
        cursor: pointer;
        background: #fff;
    }

    .application-item:hover {
        border-color: #667eea;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }

    .pet-image {
        width: 80px;
        height: 80px;
        border-radius: 15px;
        overflow: hidden;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        font-size: 1.8rem;
    }

    .pet-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .application-info {
        flex: 1;
        min-width: 0;
    }

    .pet-name {
        font-weight: 700;
        font-size: 1.2rem;
        color: #333;
        margin-bottom: 8px;
        line-height: 1.2;
    }

    .pet-details {
        font-size: 0.95rem;
        color: #666;
        margin-bottom: 8px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .application-meta {
        font-size: 0.85rem;
        color: #999;
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .status-badge {
        padding: 8px 16px;
        border-radius: 25px;
        font-size: 0.85rem;
        font-weight: 700;
        text-align: center;
        min-width: 90px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        flex-shrink: 0;
    }

    .status-pending {
        background: rgba(255, 167, 38, 0.15);
        color: #ef6c00;
        border: 2px solid rgba(255, 167, 38, 0.3);
    }

    .status-approved {
        background: rgba(102, 187, 106, 0.15);
        color: #2e7d32;
        border: 2px solid rgba(102, 187, 106, 0.3);
    }

    .status-rejected {
        background: rgba(239, 83, 80, 0.15);
        color: #c62828;
        border: 2px solid rgba(239, 83, 80, 0.3);
    }

    /* Regular Pet Cards */
    .pet-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }

    .pet-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .pet-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    }

    .pet-card-image {
        width: 100%;
        height: 200px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        position: relative;
        overflow: hidden;
    }

    .pet-card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .pet-card:hover .pet-card-image img {
        transform: scale(1.05);
    }

    .pet-card-content {
        padding: 25px;
    }

    .pet-card-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .pet-card-details {
        font-size: 0.95rem;
        color: #666;
        margin-bottom: 20px;
        line-height: 1.6;
    }

    .pet-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .adoption-fee {
        font-size: 1.2rem;
        font-weight: 700;
        color: #2e7d32;
    }

    /* ADOPTED PETS - Special Styling - FULL IMAGE DISPLAY */
    .adopted-pets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 40px;
        margin-top: 20px;
    }

    .adopted-pet-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
        border: 3px solid transparent;
        position: relative;
        min-height: 650px;
    }

    .adopted-pet-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 6px;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        z-index: 1;
    }

    .adopted-pet-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        border-color: rgba(231, 76, 60, 0.3);
    }

    .adopted-pet-image {
        width: 100%;
        height: 400px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 4rem;
        position: relative;
        overflow: hidden;
    }

    .adopted-pet-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        object-position: center;
        transition: transform 0.3s ease;
        background: white;
    }

    .adopted-pet-card:hover .adopted-pet-image img {
        transform: scale(1.02);
    }

    /* Heart overlay for adopted pets */
    .adopted-pet-image::after {
        content: '♥';
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(231, 76, 60, 0.95);
        color: white;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2rem;
        font-weight: bold;
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.5);
        animation: heartbeat 2s infinite;
        z-index: 2;
    }

    @keyframes heartbeat {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
    }

    .adopted-pet-content {
        padding: 35px 30px;
        position: relative;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .adopted-pet-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .adopted-pet-title .heart-icon {
        color: #e74c3c;
        font-size: 1.5rem;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.2);
            opacity: 0.8;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .adopted-pet-details {
        font-size: 1.1rem;
        color: #666;
        margin-bottom: 25px;
        line-height: 1.8;
        flex: 1;
    }

    .adopted-pet-details .detail-item {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
        padding: 10px 0;
    }

    .adopted-pet-details .detail-item i {
        width: 22px;
        color: #667eea;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .adoption-badge {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
        color: #2e7d32;
        padding: 16px 24px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 1.05rem;
        margin-bottom: 25px;
        border: 2px solid rgba(46, 125, 50, 0.2);
        box-shadow: 0 2px 10px rgba(46, 125, 50, 0.1);
    }

    .adopted-pet-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        padding-top: 25px;
        border-top: 2px solid #f8f9fa;
        margin-top: auto;
    }

    .family-status {
        color: #2e7d32;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.2rem;
    }

    /* Buttons */
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        justify-content: center;
        white-space: nowrap;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        text-decoration: none;
        color: white;
    }

    .btn-outline {
        background: transparent;
        color: #667eea;
        border: 2px solid #667eea;
    }

    .btn-outline:hover {
        background: #667eea;
        color: white;
        text-decoration: none;
    }

    .btn-care {
        background: linear-gradient(135deg, #2e7d32, #388e3c);
        color: white;
        box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
    }

    .btn-care:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(46, 125, 50, 0.4);
        text-decoration: none;
        color: white;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 50px 30px;
        color: #6c757d;
    }

    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
        color: #667eea;
    }

    .empty-state h4 {
        font-size: 1.3rem;
        margin-bottom: 10px;
        color: #495057;
    }

    .empty-state p {
        margin-bottom: 25px;
        font-size: 1rem;
        line-height: 1.6;
    }

    /* Quick Actions */
    .quick-actions {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .quick-action {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 20px;
        border-radius: 15px;
        text-decoration: none;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .quick-action:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        text-decoration: none;
        color: white;
    }

    .quick-action-icon {
        font-size: 1.8rem;
        opacity: 0.9;
        flex-shrink: 0;
    }

    .quick-action-content {
        flex: 1;
    }

    .quick-action-title {
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 4px;
    }

    .quick-action-desc {
        font-size: 0.9rem;
        opacity: 0.9;
        line-height: 1.4;
    }

    /* Reminders */
    .reminder-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 18px;
        background: rgba(255, 193, 7, 0.1);
        border-radius: 12px;
        border-left: 4px solid #ffc107;
        margin-bottom: 15px;
    }

    .reminder-icon {
        color: #856404;
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    .reminder-content {
        flex: 1;
        color: #856404;
        font-weight: 500;
    }

    /* Error Message */
    .error-message {
        background: rgba(220, 53, 69, 0.1);
        color: #721c24;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 30px;
        border: 1px solid rgba(220, 53, 69, 0.2);
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .adopted-pets-grid {
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 25px;
        }

        .adopted-pet-image {
            height: 280px;
        }
    }

    @media (max-width: 768px) {
        .adopted-pets-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .adopted-pet-card {
            min-height: 500px;
        }

        .adopted-pet-image {
            height: 250px;
        }

        .adopted-pet-content {
            padding: 25px 20px;
        }

        .adopted-pet-title {
            font-size: 1.5rem;
        }

        .adopted-pet-footer {
            flex-direction: column;
            gap: 12px;
            text-align: center;
        }
    }

    /* Responsive updates for adopted pets */
    @media (max-width: 1400px) {
        .adopted-pets-grid {
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 35px;
        }

        .adopted-pet-image {
            height: 350px;
        }
    }

    @media (max-width: 1200px) {
        .adopted-pets-grid {
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }

        .adopted-pet-image {
            height: 320px;
        }

        .adopted-pet-card {
            min-height: 580px;
        }
    }

    @media (max-width: 900px) {
        .adopted-pets-grid {
            grid-template-columns: 1fr;
            gap: 25px;
        }

        .adopted-pet-image {
            height: 350px;
        }
    }

    @media (max-width: 768px) {
        .adopted-pets-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .adopted-pet-card {
            min-height: 550px;
        }

        .adopted-pet-image {
            height: 300px;
        }

        .adopted-pet-content {
            padding: 25px 20px;
        }

        .adopted-pet-title {
            font-size: 1.6rem;
        }

        .adopted-pet-footer {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
    }

    @media (max-width: 480px) {
        .adopted-pet-card {
            min-height: 500px;
        }

        .adopted-pet-image {
            height: 250px;
        }

        .adopted-pet-content {
            padding: 20px 15px;
        }

        .adopted-pet-title {
            font-size: 1.4rem;
        }

        .adopted-pet-details {
            font-size: 1rem;
        }

        .adopted-pet-details .detail-item {
            margin-bottom: 12px;
            padding: 8px 0;
        }
    }

    /* Animations */
    .fade-in {
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.6s ease;
    }

    .fade-in.visible {
        opacity: 1;
        transform: translateY(0);
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

    /* Utility Classes */
    .text-center {
        text-align: center;
    }

    .mb-20 {
        margin-bottom: 20px;
    }

    .mt-20 {
        margin-top: 20px;
    }

    .mt-30 {
        margin-top: 30px;
    }

    /* Message Styles */
    .message {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 10px;
        z-index: 1001;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        max-width: 400px;
        animation: slideInRight 0.5s ease-out;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .message.success {
        background: #28a745;
        color: white;
    }

    .message.error {
        background: #dc3545;
        color: white;
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }

        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
    </style>
</head>

<body>
    <?php include_once __DIR__ . '/../common/navbar_adopter.php'; ?>

    <div class="container">
        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Welcome Section -->
        <div class="welcome-section fade-in">
            <div class="welcome-content">
                <h1 class="welcome-title">
                    <i class="fas fa-heart"></i>
                    Welcome back, <?php echo htmlspecialchars($adopter_name); ?>!
                </h1>
                <p class="welcome-subtitle">Find your perfect companion and give them a loving home</p>

                <?php if ($adopter_info): ?>
                <div class="user-info-card">
                    <div class="user-avatar-large">
                        <?php echo strtoupper(substr($adopter_name, 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($adopter_info['full_name']); ?></div>
                        <div class="user-info-item">
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($adopter_info['email']); ?></span>
                        </div>
                        <?php if (!empty($adopter_info['phone'])): ?>
                        <div class="user-info-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($adopter_info['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="user-info-item">
                            <i class="fas fa-calendar"></i>
                            <span>Member since <?php echo date('M Y', strtotime($adopter_info['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid fade-in">
            <div class="stat-card pending" onclick="navigateToPage('myAdoptions.php?filter=pending')">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $adoption_stats['pending']; ?></div>
                <div class="stat-label">Pending Applications</div>
            </div>

            <div class="stat-card approved" onclick="navigateToPage('myAdoptions.php?filter=approved')">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo $adoption_stats['approved']; ?></div>
                <div class="stat-label">Approved Applications</div>
            </div>

            <div class="stat-card adopted" onclick="navigateToPage('myAdoptions.php?filter=adopted')">
                <div class="stat-icon"><i class="fas fa-heart"></i></div>
                <div class="stat-number"><?php echo $completed_adoptions; ?></div>
                <div class="stat-label">Adopted Pets</div>
            </div>

            <div class="stat-card guides" onclick="navigateToPage('careGuides.php')">
                <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                <div class="stat-number"><?php echo $care_guides_count; ?></div>
                <div class="stat-label">Care Guides Available</div>
            </div>

            <?php if ($adoption_stats['rejected'] > 0): ?>
            <div class="stat-card rejected" onclick="navigateToPage('myAdoptions.php?filter=rejected')">
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
                    <h3 class="section-title">
                        <i class="fas fa-file-alt"></i>
                        Recent Applications
                    </h3>
                </div>
                <div class="section-body">
                    <?php if (!empty($recent_applications)): ?>
                    <?php foreach ($recent_applications as $app): ?>
                    <div class="application-item"
                        onclick="navigateToPage('petDetails.php?id=<?php echo $app['pet_id']; ?>')">
                        <div class="pet-image">
                            <?php if (!empty($app['primary_image']) && file_exists(__DIR__ . "/../uploads/" . $app['primary_image'])): ?>
                            <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($app['primary_image']); ?>"
                                alt="<?php echo htmlspecialchars($app['pet_name']); ?>" loading="lazy">
                            <?php else: ?>
                            <i class="fas fa-paw"></i>
                            <?php endif; ?>
                        </div>

                        <div class="application-info">
                            <div class="pet-name"><?php echo htmlspecialchars($app['pet_name']); ?></div>
                            <div class="pet-details">
                                <span><?php echo htmlspecialchars($app['category_name']); ?></span>
                                <?php if (!empty($app['breed_name'])): ?>
                                <span>• <?php echo htmlspecialchars($app['breed_name']); ?></span>
                                <?php endif; ?>
                                <span>• <?php echo (int)$app['age']; ?> years old</span>
                                <span>• <?php echo ucfirst($app['gender']); ?></span>
                            </div>
                            <div class="application-meta">
                                <span><i class="fas fa-home"></i>
                                    <?php echo htmlspecialchars($app['shelter_name']); ?></span>
                                <span><i class="fas fa-calendar"></i> Applied <?php echo (int)$app['days_ago']; ?>
                                    day<?php echo $app['days_ago'] != 1 ? 's' : ''; ?> ago</span>
                                <span><i class="fas fa-dollar-sign"></i>
                                    $<?php echo number_format((float)$app['adoption_fee'], 2); ?></span>
                            </div>
                        </div>

                        <div class="status-badge status-<?php echo $app['application_status']; ?>">
                            <i class="fas fa-<?php 
                                    echo $app['application_status'] == 'pending' ? 'clock' : 
                                         ($app['application_status'] == 'approved' ? 'check' : 'times'); 
                                ?>"></i>
                            <span><?php echo ucfirst($app['application_status']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="text-center mt-20">
                        <a href="<?php echo $BASE_URL; ?>adopter/myAdoptions.php" class="btn btn-outline">
                            <i class="fas fa-list"></i>
                            View All Applications
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt empty-state-icon"></i>
                        <h4>No Applications Yet</h4>
                        <p>Start your adoption journey by browsing available pets!</p>
                        <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php" class="btn btn-primary">
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
                <div class="content-section fade-in mb-20">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h3>
                    </div>
                    <div class="section-body">
                        <div class="quick-actions">
                            <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php" class="quick-action">
                                <i class="fas fa-search quick-action-icon"></i>
                                <div class="quick-action-content">
                                    <div class="quick-action-title">Browse Pets</div>
                                    <div class="quick-action-desc">Find your perfect companion</div>
                                </div>
                            </a>

                            <a href="<?php echo $BASE_URL; ?>adopter/myAdoptions.php" class="quick-action">
                                <i class="fas fa-heart quick-action-icon"></i>
                                <div class="quick-action-content">
                                    <div class="quick-action-title">My Adoptions</div>
                                    <div class="quick-action-desc">Track your applications</div>
                                </div>
                            </a>

                            <a href="<?php echo $BASE_URL; ?>adopter/careGuides.php" class="quick-action">
                                <i class="fas fa-book-open quick-action-icon"></i>
                                <div class="quick-action-content">
                                    <div class="quick-action-title">Care Guides</div>
                                    <div class="quick-action-desc">Learn pet care tips</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Reminders -->
                <?php if (!empty($upcoming_reminders)): ?>
                <div class="content-section fade-in">
                    <div class="section-header">
                        <h3 class="section-title">
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
                <h3 class="section-title">
                    <i class="fas fa-star"></i>
                    Recommended for You
                </h3>
            </div>
            <div class="section-body">
                <div class="pet-grid">
                    <?php foreach ($recommended_pets as $pet): ?>
                    <div class="pet-card" onclick="navigateToPage('petDetails.php?id=<?php echo $pet['pet_id']; ?>')">
                        <div class="pet-card-image">
                            <?php if (!empty($pet['primary_image']) && file_exists(__DIR__ . "/../uploads/" . $pet['primary_image'])): ?>
                            <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                                alt="<?php echo htmlspecialchars($pet['pet_name']); ?>" loading="lazy">
                            <?php else: ?>
                            <i class="fas fa-paw"></i>
                            <?php endif; ?>
                        </div>

                        <div class="pet-card-content">
                            <div class="pet-card-title">
                                <?php echo htmlspecialchars($pet['pet_name']); ?>
                            </div>
                            <div class="pet-card-details">
                                <?php echo htmlspecialchars($pet['category_name']); ?>
                                <?php if (!empty($pet['breed_name'])): ?>
                                • <?php echo htmlspecialchars($pet['breed_name']); ?>
                                <?php endif; ?>
                                <br>
                                <?php echo (int)$pet['age']; ?> years old • <?php echo ucfirst($pet['gender']); ?>
                                <?php if (!empty($pet['size'])): ?>
                                • <?php echo ucfirst($pet['size']); ?>
                                <?php endif; ?>
                                <br>
                                <i class="fas fa-home"></i> <?php echo htmlspecialchars($pet['shelter_name']); ?>
                                <?php if ($pet['application_count'] > 0): ?>
                                <br><small style="color: #ffa726;"><i class="fas fa-users"></i>
                                    <?php echo (int)$pet['application_count']; ?>
                                    application<?php echo $pet['application_count'] != 1 ? 's' : ''; ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="pet-card-footer">
                                <div class="adoption-fee">
                                    $<?php echo number_format((float)$pet['adoption_fee'], 2); ?>
                                </div>
                                <button class="btn btn-primary"
                                    onclick="event.stopPropagation(); navigateToPage('petDetails.php?id=<?php echo $pet['pet_id']; ?>')">
                                    <i class="fas fa-heart"></i>
                                    View Details
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-center mt-20">
                    <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php" class="btn btn-outline">
                        <i class="fas fa-search"></i>
                        Browse All Pets
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Adopted Pets Section -->
        <?php if (!empty($adopted_pets)): ?>
        <div class="content-section fade-in mt-30">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-heart"></i>
                    Your Adopted Pets
                </h3>
            </div>
            <div class="section-body">
                <div class="adopted-pets-grid">
                    <?php foreach ($adopted_pets as $pet): ?>
                    <div class="adopted-pet-card">
                        <div class="adopted-pet-image">
                            <?php if (!empty($pet['primary_image']) && file_exists(__DIR__ . "/../uploads/" . $pet['primary_image'])): ?>
                            <img src="<?php echo $BASE_URL; ?>uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                                alt="<?php echo htmlspecialchars($pet['pet_name']); ?>" loading="lazy">
                            <?php else: ?>
                            <i class="fas fa-paw"></i>
                            <?php endif; ?>
                        </div>

                        <div class="adopted-pet-content">
                            <div class="adopted-pet-title">
                                <?php echo htmlspecialchars($pet['pet_name']); ?>
                                <i class="fas fa-heart heart-icon"></i>
                            </div>

                            <div class="adoption-badge">
                                <i class="fas fa-check-circle"></i>
                                <span>Adoption Date:
                                    <?php echo date('M j, Y', strtotime($pet['adoption_date'])); ?></span>
                            </div>

                            <div class="adopted-pet-details">
                                <div class="detail-item">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo htmlspecialchars($pet['category_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-birthday-cake"></i>
                                    <span><?php echo (int)$pet['age']; ?> years old</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-venus-mars"></i>
                                    <span><?php echo ucfirst($pet['gender']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-home"></i>
                                    <span><?php echo htmlspecialchars($pet['shelter_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-calendar-heart"></i>
                                    <span><?php echo (int)$pet['days_since_adoption']; ?>
                                        day<?php echo $pet['days_since_adoption'] != 1 ? 's' : ''; ?> as family
                                        member</span>
                                </div>
                            </div>

                            <div class="adopted-pet-footer">
                                <div class="family-status">
                                    <i class="fas fa-home"></i>
                                    <span>Family Member</span>
                                </div>
                                <button class="btn btn-care" onclick="navigateToPage('careGuides.php')">
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

        <!-- Empty State for New Users -->
        <?php if (empty($recent_applications) && empty($adopted_pets) && $adoption_stats['total_applications'] == 0): ?>
        <div class="content-section fade-in mt-30">
            <div class="section-body">
                <div class="empty-state" style="padding: 60px 30px;">
                    <i class="fas fa-paw empty-state-icon" style="font-size: 5rem; color: #667eea;"></i>
                    <h2 style="font-size: 2rem; margin-bottom: 15px; color: #333;">Welcome to Pet Adoption!</h2>
                    <p
                        style="font-size: 1.1rem; margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto; line-height: 1.7;">
                        You haven't started your adoption journey yet. Browse through hundreds of loving pets waiting
                        for their forever homes!
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <a href="<?php echo $BASE_URL; ?>adopter/browsePets.php" class="btn btn-primary"
                            style="padding: 15px 30px; font-size: 1.1rem;">
                            <i class="fas fa-search"></i>
                            Start Browsing Pets
                        </a>
                        <a href="<?php echo $BASE_URL; ?>adopter/careGuides.php" class="btn btn-outline"
                            style="padding: 15px 30px; font-size: 1.1rem;">
                            <i class="fas fa-book-open"></i>
                            Learn About Pet Care
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Display session messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div id="successMessage" class="message success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div id="errorMessage" class="message error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <script>
    // Global variables
    const BASE_URL = '<?php echo $BASE_URL; ?>';

    // Navigation function
    function navigateToPage(url) {
        if (url.startsWith('http')) {
            window.location.href = url;
        } else if (url.includes('?')) {
            window.location.href = BASE_URL + 'adopter/' + url;
        } else {
            window.location.href = BASE_URL + 'adopter/' + url;
        }
    }

    // Page initialization
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize fade-in animations
        initializeAnimations();

        // Initialize card interactions
        initializeCardEffects();

        // Initialize auto-hide messages
        initializeMessages();

        // Initialize keyboard shortcuts
        initializeKeyboardShortcuts();

        // Log dashboard load
        console.log('Adopter Dashboard loaded successfully');
        console.log('User stats:', {
            pending: <?php echo $adoption_stats['pending']; ?>,
            approved: <?php echo $adoption_stats['approved']; ?>,
            adopted: <?php echo $completed_adoptions; ?>,
            total: <?php echo $adoption_stats['total_applications']; ?>
        });
    });

    // Animation initialization
    function initializeAnimations() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, {
            threshold: 0.1
        });

        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });
    }

    // Card interaction effects
    function initializeCardEffects() {
        const cards = document.querySelectorAll(
            '.stat-card, .pet-card, .adopted-pet-card, .application-item, .quick-action');

        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = this.style.transform.replace('translateY(-8px)', '') +
                    ' translateY(-8px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = this.style.transform.replace('translateY(-8px)', '');
            });

            // Add click ripple effect
            card.addEventListener('click', function(e) {
                createRippleEffect(this, e);
            });
        });
    }

    // Create ripple effect
    function createRippleEffect(element, event) {
        const ripple = document.createElement('div');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;

        ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.3);
            transform: scale(0);
            animation: ripple 0.6s linear;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            pointer-events: none;
            z-index: 1;
        `;

        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);

        setTimeout(() => {
            if (ripple.parentNode) {
                ripple.remove();
            }
        }, 600);
    }

    // Message handling
    function initializeMessages() {
        const messages = document.querySelectorAll('#successMessage, #errorMessage');
        messages.forEach(message => {
            if (message) {
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    message.style.animation = 'slideOutRight 0.5s ease';
                    setTimeout(() => {
                        if (message.parentNode) {
                            message.remove();
                        }
                    }, 500);
                }, 5000);
            }
        });
    }

    // Keyboard shortcuts
    function initializeKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Don't trigger shortcuts if user is typing in an input
            if (e.target.matches('input, textarea, select')) {
                return;
            }

            switch (e.key.toLowerCase()) {
                case 'b':
                    navigateToPage('browsePets.php');
                    break;
                case 'm':
                    navigateToPage('myAdoptions.php');
                    break;
                case 'c':
                    navigateToPage('careGuides.php');
                    break;
                case 'h':
                case '?':
                    showKeyboardHelp();
                    break;
            }
        });
    }

    // Show keyboard shortcuts help
    function showKeyboardHelp() {
        const helpText = `
            Keyboard Shortcuts:
            
            B - Browse Pets
            M - My Adoptions  
            C - Care Guides
            H or ? - Show this help
            
            Use these shortcuts to navigate quickly!
        `;

        alert(helpText);
    }

    // Auto-refresh statistics (every 5 minutes)
    setInterval(function() {
        refreshStatistics();
    }, 300000);

    // Function to refresh statistics via AJAX
    function refreshStatistics() {
        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=refresh_stats'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update statistics without page reload
                    updateStatisticNumbers(data.stats);
                }
            })
            .catch(error => {
                console.log('Statistics refresh failed:', error);
            });
    }

    // Update statistic numbers with animation
    function updateStatisticNumbers(stats) {
        Object.keys(stats).forEach(key => {
            const element = document.querySelector(`.stat-card.${key} .stat-number`);
            if (element) {
                animateNumber(element, parseInt(element.textContent), stats[key]);
            }
        });
    }

    // Animate number changes
    function animateNumber(element, from, to) {
        const duration = 1000;
        const start = Date.now();

        function update() {
            const progress = (Date.now() - start) / duration;
            if (progress < 1) {
                const current = Math.floor(from + (to - from) * progress);
                element.textContent = current;
                requestAnimationFrame(update);
            } else {
                element.textContent = to;
            }
        }

        if (from !== to) {
            update();
        }
    }

    // Add CSS for animations
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

    // Handle online/offline status
    window.addEventListener('online', () => {
        console.log('Connection restored');
    });

    window.addEventListener('offline', () => {
        console.log('Connection lost');
    });

    // Add smooth scrolling for internal links
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

    // Welcome animation for new users
    <?php if (empty($recent_applications) && empty($adopted_pets) && $adoption_stats['total_applications'] == 0): ?>
    setTimeout(() => {
        const welcomeSection = document.querySelector('.empty-state');
        if (welcomeSection) {
            welcomeSection.style.animation = 'pulse 2s infinite';
        }
    }, 2000);
    <?php endif; ?>
    </script>
</body>

</html>