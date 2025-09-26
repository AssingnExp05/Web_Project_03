<?php
// admin/reports.php - Admin Reports and Analytics Page
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Base URL
$BASE_URL = 'http://' . $_SERVER['HTTP_HOST'] . '/pet_care/';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    $_SESSION['error_message'] = 'Please login as an admin to access this page.';
    header('Location: ' . $BASE_URL . 'auth/login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$page_title = 'Reports & Analytics - Admin Dashboard';

// Initialize variables
$stats = [
    'total_pets' => 0,
    'total_users' => 0,
    'total_adoptions' => 0,
    'total_shelters' => 0,
    'adopted_pets' => 0,
    'pending_adoptions' => 0,
    'total_vaccinations' => 0,
    'overdue_vaccinations' => 0,
    'active_users' => 0,
    'this_month_adoptions' => 0,
    'this_month_registrations' => 0,
    'this_month_pets' => 0
];

$charts_data = [];
$recent_activities = [];
$shelter_stats = [];
$adoption_trends = [];
$vaccination_stats = [];

// Date filter parameters
$date_filter = $_GET['date_filter'] ?? '30'; // 7, 30, 90, 365 days
$custom_from = $_GET['custom_from'] ?? '';
$custom_to = $_GET['custom_to'] ?? '';

// Calculate date range
$date_condition = '';
$date_params = [];

switch ($date_filter) {
    case '7':
        $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case '30':
        $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case '90':
        $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
        break;
    case '365':
        $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
        break;
    case 'custom':
        if (!empty($custom_from) && !empty($custom_to)) {
            $date_condition = "DATE(created_at) BETWEEN ? AND ?";
            $date_params = [$custom_from, $custom_to];
        }
        break;
}

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="reports_' . date('Y-m-d') . '.pdf"');
    echo "PDF export functionality will be implemented";
    exit();
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reports_' . date('Y-m-d') . '.csv"');
    // Simple CSV export
    $csv_data = "Metric,Value\n";
    $csv_data .= "Generated," . date('Y-m-d H:i:s') . "\n";
    echo $csv_data;
    exit();
}

// Database operations
try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if ($db) {
        // Get basic statistics
        try {
            // Total pets
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_pets'] = $result ? (int)$result['count'] : 0;
            
            // Total users
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_users'] = $result ? (int)$result['count'] : 0;
            
            // Total adoptions
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_adoptions'] = $result ? (int)$result['count'] : 0;
            
            // Total shelters
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM shelters");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_shelters'] = $result ? (int)$result['count'] : 0;
            
            // Adopted pets
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE status = 'adopted'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['adopted_pets'] = $result ? (int)$result['count'] : 0;
            
            // Pending adoptions
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE application_status = 'pending'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['pending_adoptions'] = $result ? (int)$result['count'] : 0;
            
            // Total vaccinations
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM vaccinations");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_vaccinations'] = $result ? (int)$result['count'] : 0;
            
            // Overdue vaccinations
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM vaccinations WHERE vaccination_date IS NULL AND next_due_date < CURDATE()");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['overdue_vaccinations'] = $result ? (int)$result['count'] : 0;
            
            // Active users
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['active_users'] = $result ? (int)$result['count'] : 0;
            
            // This month adoptions
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM adoption_applications WHERE MONTH(application_date) = MONTH(CURDATE()) AND YEAR(application_date) = YEAR(CURDATE())");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['this_month_adoptions'] = $result ? (int)$result['count'] : 0;
            
            // This month registrations
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['this_month_registrations'] = $result ? (int)$result['count'] : 0;
            
            // This month pets added
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM pets WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['this_month_pets'] = $result ? (int)$result['count'] : 0;
            
        } catch (Exception $e) {
            error_log("Stats error: " . $e->getMessage());
        }
        
        // Get adoption trends (last 6 months)
        try {
            $stmt = $db->prepare("
                SELECT 
                    DATE_FORMAT(application_date, '%Y-%m') as month,
                    COUNT(*) as count
                FROM adoption_applications 
                WHERE application_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(application_date, '%Y-%m')
                ORDER BY month ASC
            ");
            $stmt->execute();
            $adoption_trends = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Adoption trends error: " . $e->getMessage());
            $adoption_trends = [];
        }
        
        // Get shelter statistics
        try {
            $stmt = $db->prepare("
                SELECT 
                    s.shelter_name,
                    COUNT(DISTINCT p.pet_id) as total_pets,
                    COUNT(DISTINCT aa.application_id) as total_applications,
                    COUNT(DISTINCT CASE WHEN p.status = 'adopted' THEN p.pet_id END) as adopted_pets,
                    COUNT(DISTINCT CASE WHEN aa.application_status = 'approved' THEN aa.application_id END) as approved_applications
                FROM shelters s
                LEFT JOIN pets p ON s.shelter_id = p.shelter_id
                LEFT JOIN adoption_applications aa ON p.pet_id = aa.pet_id
                GROUP BY s.shelter_id, s.shelter_name
                ORDER BY total_pets DESC
                LIMIT 10
            ");
            $stmt->execute();
            $shelter_stats = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Shelter stats error: " . $e->getMessage());
            $shelter_stats = [];
        }
        
        // Get vaccination statistics
        try {
            $stmt = $db->prepare("
                SELECT 
                    'All Vaccines' as vaccine_type,
                    COUNT(*) as count,
                    COUNT(CASE WHEN vaccination_date IS NOT NULL THEN 1 END) as completed,
                    COUNT(CASE WHEN vaccination_date IS NULL AND next_due_date < CURDATE() THEN 1 END) as overdue
                FROM vaccinations
            ");
            $stmt->execute();
            $vaccination_stats = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Vaccination stats error: " . $e->getMessage());
            $vaccination_stats = [];
        }
        
        // Get recent activities
        try {
            $recent_activities = [];
            
            // Get recent user registrations
            $stmt = $db->prepare("
                SELECT 'user_registration' as type, 
                       CONCAT(first_name, ' ', last_name) as description, 
                       created_at, 
                       user_type as extra_info 
                FROM users 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $user_activities = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $recent_activities = array_merge($recent_activities, $user_activities);
            
            // Get recent pets added
            $stmt = $db->prepare("
                SELECT 'pet_added' as type, 
                       CONCAT('Pet: ', pet_name) as description, 
                       created_at, 
                       status as extra_info 
                FROM pets 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $pet_activities = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $recent_activities = array_merge($recent_activities, $pet_activities);
            
            // Get recent adoption applications
            $stmt = $db->prepare("
                SELECT 'adoption_application' as type, 
                       CONCAT('Application for pet ID: ', pet_id) as description, 
                       application_date as created_at, 
                       application_status as extra_info 
                FROM adoption_applications 
                ORDER BY application_date DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $adoption_activities = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $recent_activities = array_merge($recent_activities, $adoption_activities);
            
            // Get recent vaccinations
            $stmt = $db->prepare("
                SELECT 'vaccination' as type, 
                       CONCAT(vaccine_name, ' for pet ID: ', pet_id) as description, 
                       created_at, 
                       'vaccine' as extra_info 
                FROM vaccinations 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            $stmt->execute();
            $vaccination_activities = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $recent_activities = array_merge($recent_activities, $vaccination_activities);
            
            // Sort all activities by date and limit to 15
            usort($recent_activities, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            $recent_activities = array_slice($recent_activities, 0, 15);
            
        } catch (Exception $e) {
            error_log("Recent activities error: " . $e->getMessage());
            $recent_activities = [];
        }
        
        // Prepare chart data
        $charts_data = [
            'pets_by_category' => [],
            'pets_by_status' => [],
            'users_by_type' => [],
            'adoptions_by_month' => $adoption_trends
        ];
        
        // Pets by category
        try {
            $stmt = $db->prepare("
                SELECT pc.category_name, COUNT(p.pet_id) as count 
                FROM pet_categories pc
                LEFT JOIN pets p ON pc.category_id = p.category_id
                GROUP BY pc.category_id, pc.category_name 
                ORDER BY count DESC
            ");
            $stmt->execute();
            $charts_data['pets_by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Pets by category error: " . $e->getMessage());
            $charts_data['pets_by_category'] = [];
        }
        
        // Pets by status
        try {
            $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM pets GROUP BY status ORDER BY count DESC");
            $stmt->execute();
            $charts_data['pets_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Pets by status error: " . $e->getMessage());
            $charts_data['pets_by_status'] = [];
        }
        
        // Users by type
        try {
            $stmt = $db->prepare("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type ORDER BY count DESC");
            $stmt->execute();
            $charts_data['users_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Users by type error: " . $e->getMessage());
            $charts_data['users_by_type'] = [];
        }
    }
} catch (Exception $e) {
    error_log("Reports database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        max-width: 1600px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Header Section */
    .page-header {
        background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
        color: white;
        border-radius: 20px;
        padding: 30px 40px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
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
    }

    .btn-primary {
        background: #ffd700;
        color: #6f42c1;
    }

    .btn-primary:hover {
        background: #ffed4e;
        transform: translateY(-2px);
        text-decoration: none;
        color: #e83e8c;
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.3);
        text-decoration: none;
        color: white;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .btn-success:hover {
        background: #218838;
    }

    .btn-info {
        background: #17a2b8;
        color: white;
    }

    .btn-info:hover {
        background: #138496;
    }

    /* Date Filter Section */
    .date-filter {
        background: white;
        border-radius: 15px;
        padding: 20px 25px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    .date-filter label {
        font-weight: 600;
        color: #2c3e50;
    }

    .date-filter select,
    .date-filter input {
        padding: 8px 12px;
        border: 2px solid #e1e8ed;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.3s ease;
    }

    .date-filter select:focus,
    .date-filter input:focus {
        outline: none;
        border-color: #6f42c1;
    }

    /* Stats Overview */
    .stats-overview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--color);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .stat-card.pets {
        --color: #28a745;
    }

    .stat-card.users {
        --color: #17a2b8;
    }

    .stat-card.adoptions {
        --color: #fd7e14;
    }

    .stat-card.shelters {
        --color: #6f42c1;
    }

    .stat-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .stat-info h3 {
        color: #666;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--color);
        line-height: 1;
    }

    .stat-change {
        font-size: 0.8rem;
        margin-top: 5px;
        color: #28a745;
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        background: var(--color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        opacity: 0.9;
    }

    /* Charts Section */
    .charts-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .chart-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .chart-card:hover {
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .chart-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .chart-container {
        position: relative;
        height: 300px;
    }

    /* Data Tables */
    .data-section {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 25px;
        margin-bottom: 30px;
    }

    .data-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .data-header {
        background: #f8f9fa;
        padding: 20px 25px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .data-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th,
    .data-table td {
        padding: 15px 25px;
        text-align: left;
        border-bottom: 1px solid #f1f1f1;
    }

    .data-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .data-table tr {
        transition: background-color 0.3s ease;
    }

    .data-table tr:hover {
        background: #f8f9fa;
    }

    /* Recent Activities */
    .activity-item {
        padding: 15px 25px;
        border-bottom: 1px solid #f1f1f1;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: background-color 0.3s ease;
    }

    .activity-item:hover {
        background: #f8f9fa;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        color: white;
        flex-shrink: 0;
    }

    .activity-icon.user {
        background: #17a2b8;
    }

    .activity-icon.pet {
        background: #28a745;
    }

    .activity-icon.adoption {
        background: #fd7e14;
    }

    .activity-icon.vaccination {
        background: #6f42c1;
    }

    .activity-content {
        flex: 1;
    }

    .activity-description {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 2px;
    }

    .activity-meta {
        font-size: 0.8rem;
        color: #666;
    }

    .activity-time {
        font-size: 0.8rem;
        color: #999;
        flex-shrink: 0;
    }

    /* System Health Section */
    .system-health {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .progress-bar {
        background: #f8f9fa;
        border-radius: 10px;
        height: 8px;
        overflow: hidden;
        margin: 8px 0;
    }

    .progress-fill {
        background: var(--color);
        height: 100%;
        transition: width 0.5s ease;
    }

    /* Empty State */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #666;
        padding: 40px;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.3;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .data-section {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .page-header {
            flex-direction: column;
            text-align: center;
        }

        .stats-overview {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .charts-section {
            grid-template-columns: 1fr;
        }

        .date-filter {
            flex-direction: column;
            align-items: stretch;
            gap: 15px;
        }
    }

    @media (max-width: 480px) {
        .stats-overview {
            grid-template-columns: 1fr;
        }
    }

    /* Animations */
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

    .fade-in {
        animation: fadeIn 0.6s ease-out;
    }

    /* Messages */
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
    </style>
</head>

<body>
    <!-- Include Admin Navbar -->
    <?php include_once __DIR__ . '/../common/navbar_admin.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <div>
                <h1><i class="fas fa-chart-line"></i> Reports & Analytics</h1>
                <p>Comprehensive insights and analytics for the pet adoption platform</p>
            </div>
            <div class="header-actions">
                <button onclick="exportToPDF()" class="btn btn-secondary">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button onclick="exportToCSV()" class="btn btn-secondary">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button onclick="printReport()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <button onclick="refreshData()" class="btn btn-primary">
                    <i class="fas fa-sync"></i> Refresh Data
                </button>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="date-filter fade-in">
            <label><i class="fas fa-calendar-alt"></i> Time Period:</label>
            <form method="GET" action="" id="dateFilterForm"
                style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <select name="date_filter"
                    onchange="toggleCustomDates(); document.getElementById('dateFilterForm').submit();">
                    <option value="30" <?php echo $date_filter === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="7" <?php echo $date_filter === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="90" <?php echo $date_filter === '90' ? 'selected' : ''; ?>>Last 3 Months</option>
                    <option value="365" <?php echo $date_filter === '365' ? 'selected' : ''; ?>>Last Year</option>
                    <option value="custom" <?php echo $date_filter === 'custom' ? 'selected' : ''; ?>>Custom Range
                    </option>
                </select>

                <div id="customDates"
                    style="display: <?php echo $date_filter === 'custom' ? 'flex' : 'none'; ?>; gap: 10px; align-items: center;">
                    <input type="date" name="custom_from" value="<?php echo htmlspecialchars($custom_from); ?>"
                        placeholder="From">
                    <span>to</span>
                    <input type="date" name="custom_to" value="<?php echo htmlspecialchars($custom_to); ?>"
                        placeholder="To">
                    <button type="submit" class="btn btn-info" style="padding: 8px 15px;">Apply</button>
                </div>
            </form>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-overview fade-in">
            <div class="stat-card pets">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Pets</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_pets']); ?></div>
                        <div class="stat-change">
                            <i class="fas fa-arrow-up"></i> <?php echo $stats['this_month_pets']; ?> this month
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card users">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Users</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-change">
                            <i class="fas fa-arrow-up"></i> <?php echo $stats['this_month_registrations']; ?> this month
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card adoptions">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Adoptions</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_adoptions']); ?></div>
                        <div class="stat-change">
                            <i class="fas fa-arrow-up"></i> <?php echo $stats['this_month_adoptions']; ?> this month
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card shelters">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Active Shelters</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_shelters']); ?></div>
                        <div class="stat-change">
                            <i class="fas fa-home"></i> Across the platform
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section fade-in">
            <!-- Pets by Category Chart -->
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-pie"></i>
                    Pets by Category
                </h3>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>

            <!-- Pets by Status Chart -->
            <div class="chart-card">
                <h3 class="chart-title">
                    <i class="fas fa-chart-donut"></i>
                    Pets by Status
                </h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Adoption Trends Chart -->
            <div class="chart-card" style="grid-column: 1 / -1;">
                <h3 class="chart-title">
                    <i class="fas fa-chart-line"></i>
                    Adoption Trends (Last 6 Months)
                </h3>
                <div class="chart-container">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Data Tables Section -->
        <div class="data-section fade-in">
            <!-- Shelter Performance -->
            <div class="data-card">
                <div class="data-header">
                    <h3 class="data-title">Shelter Performance</h3>
                    <span style="font-size: 0.9rem; color: #666;"><?php echo count($shelter_stats); ?> shelters</span>
                </div>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Shelter Name</th>
                                <th>Total Pets</th>
                                <th>Adopted</th>
                                <th>Applications</th>
                                <th>Success Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shelter_stats as $shelter): ?>
                            <tr>
                                <td style="font-weight: 600; color: #2c3e50;">
                                    <?php echo htmlspecialchars($shelter['shelter_name']); ?>
                                </td>
                                <td><?php echo number_format($shelter['total_pets']); ?></td>
                                <td style="color: #28a745; font-weight: 600;">
                                    <?php echo number_format($shelter['adopted_pets']); ?>
                                </td>
                                <td><?php echo number_format($shelter['total_applications']); ?></td>
                                <td>
                                    <?php 
                                        $success_rate = $shelter['total_pets'] > 0 ? 
                                            round(($shelter['adopted_pets'] / $shelter['total_pets']) * 100, 1) : 0;
                                        $color = $success_rate >= 50 ? '#28a745' : ($success_rate >= 25 ? '#ffc107' : '#dc3545');
                                    ?>
                                    <span style="color: <?php echo $color; ?>; font-weight: 600;">
                                        <?php echo $success_rate; ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($shelter_stats)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #666; padding: 30px;">
                                    No shelter data available
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="data-card">
                <div class="data-header">
                    <h3 class="data-title">Recent Activities</h3>
                    <span style="font-size: 0.9rem; color: #666;">Latest updates</span>
                </div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <?php
                            $icon_class = [
                                'user_registration' => 'user',
                                'pet_added' => 'pet', 
                                'adoption_application' => 'adoption',
                                'vaccination' => 'vaccination'
                            ][$activity['type']] ?? 'user';
                            
                            $icon = [
                                'user_registration' => 'fa-user-plus',
                                'pet_added' => 'fa-paw',
                                'adoption_application' => 'fa-heart', 
                                'vaccination' => 'fa-syringe'
                            ][$activity['type']] ?? 'fa-circle';
                        ?>
                        <div class="activity-icon <?php echo $icon_class; ?>">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-description">
                                <?php
                                    $type_labels = [
                                        'user_registration' => 'New User Registration',
                                        'pet_added' => 'Pet Added',
                                        'adoption_application' => 'Adoption Application',
                                        'vaccination' => 'Vaccination Record'
                                    ];
                                    echo $type_labels[$activity['type']] ?? 'Activity';
                                ?>
                            </div>
                            <div class="activity-meta">
                                <?php echo htmlspecialchars($activity['description']); ?>
                                <?php if (!empty($activity['extra_info'])): ?>
                                • <span
                                    style="color: #17a2b8;"><?php echo htmlspecialchars($activity['extra_info']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="activity-time">
                            <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($recent_activities)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #666;">
                        <i class="fas fa-clock" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.3;"></i>
                        <p>No recent activities</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- System Health Section -->
        <div class="system-health fade-in">
            <h3 class="chart-title">
                <i class="fas fa-heartbeat"></i>
                System Health Overview
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
                <!-- Vaccination Statistics -->
                <div style="background: #f8f9fa; border-radius: 10px; padding: 20px;">
                    <h4 style="margin-bottom: 15px; color: #2c3e50;">
                        <i class="fas fa-syringe"></i> Vaccination Overview
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; text-align: center;">
                        <div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #6f42c1; margin-bottom: 5px;">
                                <?php echo number_format($stats['total_vaccinations']); ?>
                            </div>
                            <div style="font-size: 0.85rem; color: #666;">Total Records</div>
                        </div>
                        <div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #dc3545; margin-bottom: 5px;">
                                <?php echo number_format($stats['overdue_vaccinations']); ?>
                            </div>
                            <div style="font-size: 0.85rem; color: #666;">Overdue</div>
                        </div>
                    </div>

                    <?php if ($stats['total_vaccinations'] > 0): ?>
                    <div style="margin-top: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-weight: 600; color: #2c3e50; font-size: 0.9rem;">Vaccination Status</span>
                            <span style="font-weight: 600; color: #28a745; font-size: 0.9rem;">
                                <?php 
                                $completion_rate = round((($stats['total_vaccinations'] - $stats['overdue_vaccinations']) / $stats['total_vaccinations']) * 100, 1);
                                echo $completion_rate . '%';
                                ?>
                            </span>
                        </div>
                        <div class="progress-bar" style="--color: #28a745;">
                            <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%;"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Adoption Statistics -->
                <div style="background: #f8f9fa; border-radius: 10px; padding: 20px;">
                    <h4 style="margin-bottom: 15px; color: #2c3e50;">
                        <i class="fas fa-heart"></i> Adoption Overview
                    </h4>

                    <!-- Active Users Percentage -->
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-weight: 600; color: #2c3e50; font-size: 0.9rem;">Active Users</span>
                            <span style="font-weight: 600; color: #17a2b8; font-size: 0.9rem;">
                                <?php 
                                $active_percentage = $stats['total_users'] > 0 ? 
                                    round(($stats['active_users'] / $stats['total_users']) * 100, 1) : 0;
                                echo $active_percentage . '%';
                                ?>
                            </span>
                        </div>
                        <div class="progress-bar" style="--color: #17a2b8;">
                            <div class="progress-fill" style="width: <?php echo $active_percentage; ?>%;"></div>
                        </div>
                    </div>

                    <!-- Adoption Success Rate -->
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-weight: 600; color: #2c3e50; font-size: 0.9rem;">Pets Adopted</span>
                            <span style="font-weight: 600; color: #28a745; font-size: 0.9rem;">
                                <?php 
                                $adoption_percentage = $stats['total_pets'] > 0 ? 
                                    round(($stats['adopted_pets'] / $stats['total_pets']) * 100, 1) : 0;
                                echo $adoption_percentage . '%';
                                ?>
                            </span>
                        </div>
                        <div class="progress-bar" style="--color: #28a745;">
                            <div class="progress-fill" style="width: <?php echo $adoption_percentage; ?>%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div style="background: #f8f9fa; border-radius: 10px; padding: 20px;">
                    <h4 style="margin-bottom: 15px; color: #2c3e50;">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </h4>
                    <div style="display: grid; gap: 10px;">
                        <a href="<?php echo $BASE_URL; ?>admin/manageAdoptions.php?status=pending" class="btn btn-info"
                            style="font-size: 0.85rem; padding: 10px 15px; text-align: center; justify-content: center;">
                            <i class="fas fa-clock"></i> Review Pending Applications
                            (<?php echo $stats['pending_adoptions']; ?>)
                        </a>
                        <a href="<?php echo $BASE_URL; ?>admin/manageVaccinations.php?status=overdue"
                            class="btn btn-secondary"
                            style="font-size: 0.85rem; padding: 10px 15px; text-align: center; justify-content: center;">
                            <i class="fas fa-exclamation-triangle"></i> Check Overdue Vaccinations
                            (<?php echo $stats['overdue_vaccinations']; ?>)
                        </a>
                        <a href="<?php echo $BASE_URL; ?>admin/managePets.php?status=available" class="btn btn-success"
                            style="font-size: 0.85rem; padding: 10px 15px; text-align: center; justify-content: center;">
                            <i class="fas fa-paw"></i> View Available Pets
                            (<?php echo $stats['total_pets'] - $stats['adopted_pets']; ?>)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
    // Chart colors
    const colors = {
        primary: ['#6f42c1', '#e83e8c', '#17a2b8', '#28a745', '#ffc107', '#fd7e14', '#dc3545', '#6c757d'],
        gradients: {
            purple: 'linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%)',
            blue: 'linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%)',
            green: 'linear-gradient(135deg, #28a745 0%, #17a2b8 100%)'
        }
    };

    // Chart configuration
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    Chart.defaults.plugins.legend.position = 'bottom';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.padding = 20;

    // Initialize charts when page loads
    document.addEventListener('DOMContentLoaded', function() {
        initializeCharts();
        animateCounters();
    });

    function initializeCharts() {
        // Pets by Category Chart
        const categoryData = <?php echo json_encode($charts_data['pets_by_category']); ?>;
        if (categoryData.length > 0 && categoryData.some(item => item.count > 0)) {
            createPieChart('categoryChart', {
                labels: categoryData.map(item => item.category_name || 'Unknown'),
                data: categoryData.map(item => parseInt(item.count) || 0),
                colors: colors.primary
            });
        } else {
            showNoDataMessage('categoryChart');
        }

        // Pets by Status Chart
        const statusData = <?php echo json_encode($charts_data['pets_by_status']); ?>;
        if (statusData.length > 0) {
            createDoughnutChart('statusChart', {
                labels: statusData.map(item => item.status ? item.status.charAt(0).toUpperCase() + item
                    .status.slice(1) : 'Unknown'),
                data: statusData.map(item => parseInt(item.count) || 0),
                colors: colors.primary
            });
        } else {
            showNoDataMessage('statusChart');
        }

        // Adoption Trends Chart
        const trendsData = <?php echo json_encode($charts_data['adoptions_by_month']); ?>;
        if (trendsData.length > 0) {
            createLineChart('trendsChart', {
                labels: trendsData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', {
                        month: 'short',
                        year: 'numeric'
                    });
                }),
                data: trendsData.map(item => parseInt(item.count) || 0)
            });
        } else {
            showNoDataMessage('trendsChart');
        }
    }

    function createPieChart(canvasId, data) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.data,
                    backgroundColor: data.colors,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((sum, value) => sum +
                                    value, 0);
                                const percentage = total > 0 ? ((context.raw / total) * 100)
                                    .toFixed(1) : 0;
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    function createDoughnutChart(canvasId, data) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.data,
                    backgroundColor: data.colors,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((sum, value) => sum +
                                    value, 0);
                                const percentage = total > 0 ? ((context.raw / total) * 100)
                                    .toFixed(1) : 0;
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    function createLineChart(canvasId, data) {
        const ctx = document.getElementById(canvasId).getContext('2d');

        // Create gradient
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(111, 66, 193, 0.3)');
        gradient.addColorStop(1, 'rgba(111, 66, 193, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Adoptions',
                    data: data.data,
                    borderColor: '#6f42c1',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#6f42c1',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return Number.isInteger(value) ? value : '';
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8,
                        displayColors: false
                    }
                }
            }
        });
    }

    function showNoDataMessage(canvasId) {
        const canvas = document.getElementById(canvasId);
        const container = canvas.parentElement;
        container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <p style="margin: 0; font-size: 1.1rem;">No data available</p>
                    <p style="margin: 5px 0 0 0; font-size: 0.9rem; opacity: 0.7;">Data will appear as it becomes available</p>
                </div>
            `;
    }

    // Utility functions
    function toggleCustomDates() {
        const select = document.querySelector('select[name="date_filter"]');
        const customDates = document.getElementById('customDates');

        if (select.value === 'custom') {
            customDates.style.display = 'flex';
        } else {
            customDates.style.display = 'none';
        }
    }

    function refreshData() {
        window.location.reload();
    }

    function exportToPDF() {
        showMessage('PDF export functionality would be implemented here', 'info');
        window.print();
    }

    function exportToCSV() {
        // Create CSV data from the statistics
        let csvContent = "data:text/csv;charset=utf-8,";

        // Add headers
        csvContent += "Metric,Value\n";

        // Add statistics
        csvContent += `Total Pets,${<?php echo $stats['total_pets']; ?>}\n`;
        csvContent += `Total Users,${<?php echo $stats['total_users']; ?>}\n`;
        csvContent += `Total Adoptions,${<?php echo $stats['total_adoptions']; ?>}\n`;
        csvContent += `Active Shelters,${<?php echo $stats['total_shelters']; ?>}\n`;
        csvContent += `Adopted Pets,${<?php echo $stats['adopted_pets']; ?>}\n`;
        csvContent += `Pending Adoptions,${<?php echo $stats['pending_adoptions']; ?>}\n`;
        csvContent += `Total Vaccinations,${<?php echo $stats['total_vaccinations']; ?>}\n`;
        csvContent += `Overdue Vaccinations,${<?php echo $stats['overdue_vaccinations']; ?>}\n`;
        csvContent += `Active Users,${<?php echo $stats['active_users']; ?>}\n`;

        // Create and trigger download
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `reports_${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showMessage('Report exported to CSV successfully', 'success');
    }

    function printReport() {
        window.print();
    }

    function showMessage(message, type) {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.message');
        existingMessages.forEach(msg => msg.remove());

        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message';
        messageDiv.style.background = type === 'success' ? '#28a745' :
            type === 'error' ? '#dc3545' : '#17a2b8';
        messageDiv.style.color = 'white';

        messageDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;

        document.body.appendChild(messageDiv);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    // Animate counters on page load
    function animateCounters() {
        const counters = document.querySelectorAll('.stat-number');
        counters.forEach(counter => {
            const target = parseInt(counter.textContent.replace(/,/g, ''));
            if (target > 0) {
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target.toLocaleString();
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current).toLocaleString();
                    }
                }, 20);
            }
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(event) {
        // Ctrl+P to print
        if (event.ctrlKey && event.key === 'p') {
            event.preventDefault();
            printReport();
        }

        // Ctrl+R to refresh
        if (event.ctrlKey && event.key === 'r') {
            event.preventDefault();
            refreshData();
        }

        // Ctrl+E to export
        if (event.ctrlKey && event.key === 'e') {
            event.preventDefault();
            exportToCSV();
        }
    });
    </script>
</body>

</html>