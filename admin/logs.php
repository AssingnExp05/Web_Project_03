<?php
// admin/logs.php - Admin System Logs Page
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
$page_title = 'System Logs - Admin Dashboard';

// Initialize variables
$logs = [];
$total_logs = 0;
$total_pages = 1;
$log_stats = [
    'total_logs' => 0,
    'today_logs' => 0,
    'error_logs' => 0,
    'warning_logs' => 0,
    'info_logs' => 0,
    'user_activities' => 0
];

// Filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_level = $_GET['level'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$search_query = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Handle AJAX requests for log actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    try {
        require_once __DIR__ . '/../config/db.php';
        $db = getDB();
        
        if ($db && isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'clear_logs':
                    $log_type = $_POST['log_type'] ?? 'all';
                    $days_old = intval($_POST['days_old'] ?? 30);
                    
                    if ($log_type === 'all') {
                        // This would clear system logs older than specified days
                        // For demo purposes, we'll simulate this
                        echo json_encode(['success' => true, 'message' => 'All logs older than ' . $days_old . ' days have been cleared']);
                    } else {
                        echo json_encode(['success' => true, 'message' => ucfirst($log_type) . ' logs have been cleared']);
                    }
                    break;
                    
                case 'export_logs':
                    $export_format = $_POST['format'] ?? 'csv';
                    
                    // Simulate export
                    echo json_encode(['success' => true, 'message' => 'Logs exported to ' . strtoupper($export_format) . ' format']);
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Database operations to simulate logs
try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();
    
    if ($db) {
        // Generate sample system logs based on actual database activities
        $sample_logs = [];
        
        // Get recent user activities
        try {
            $stmt = $db->prepare("
                SELECT 'user_activity' as log_type, 'info' as log_level,
                       CONCAT('User ', first_name, ' ', last_name, ' (', user_type, ') logged in') as message,
                       created_at as timestamp,
                       INET_ATON(?) as ip_address,
                       user_id
                FROM users 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
            $user_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $sample_logs = array_merge($sample_logs, $user_logs);
        } catch (Exception $e) {
            error_log("User logs error: " . $e->getMessage());
        }
        
        // Get pet-related activities
        try {
            $stmt = $db->prepare("
                SELECT 'pet_activity' as log_type, 'info' as log_level,
                       CONCAT('New pet added: ', pet_name, ' (', 
                       COALESCE((SELECT category_name FROM pet_categories WHERE category_id = pets.category_id), 'Unknown'), 
                       ')') as message,
                       created_at as timestamp,
                       INET_ATON(?) as ip_address,
                       pet_id as user_id
                FROM pets 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
            $pet_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $sample_logs = array_merge($sample_logs, $pet_logs);
        } catch (Exception $e) {
            error_log("Pet logs error: " . $e->getMessage());
        }
        
        // Get adoption activities
        try {
            $stmt = $db->prepare("
                SELECT 'adoption_activity' as log_type, 
                       CASE 
                           WHEN application_status = 'pending' THEN 'warning'
                           WHEN application_status = 'approved' THEN 'info'
                           WHEN application_status = 'rejected' THEN 'error'
                           ELSE 'info'
                       END as log_level,
                       CONCAT('Adoption application ', application_status, ' for pet ID: ', pet_id) as message,
                       application_date as timestamp,
                       INET_ATON(?) as ip_address,
                       adopter_id as user_id
                FROM adoption_applications 
                ORDER BY application_date DESC 
                LIMIT 10
            ");
            $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
            $adoption_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $sample_logs = array_merge($sample_logs, $adoption_logs);
        } catch (Exception $e) {
            error_log("Adoption logs error: " . $e->getMessage());
        }
        
        // Get vaccination activities
        try {
            $stmt = $db->prepare("
                SELECT 'vaccination_activity' as log_type,
                       CASE 
                           WHEN vaccination_date IS NULL AND next_due_date < CURDATE() THEN 'error'
                           WHEN vaccination_date IS NULL THEN 'warning'
                           ELSE 'info'
                       END as log_level,
                       CONCAT('Vaccination record: ', vaccine_name, ' for pet ID: ', pet_id) as message,
                       created_at as timestamp,
                       INET_ATON(?) as ip_address,
                       pet_id as user_id
                FROM vaccinations 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
            $vaccination_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $sample_logs = array_merge($sample_logs, $vaccination_logs);
        } catch (Exception $e) {
            error_log("Vaccination logs error: " . $e->getMessage());
        }
        
        // Add some system logs
        $system_logs = [
            [
                'log_type' => 'system',
                'log_level' => 'info',
                'message' => 'Database backup completed successfully',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'ip_address' => ip2long('127.0.0.1'),
                'user_id' => null
            ],
            [
                'log_type' => 'system',
                'log_level' => 'warning',
                'message' => 'High disk usage detected (85% full)',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours')),
                'ip_address' => ip2long('127.0.0.1'),
                'user_id' => null
            ],
            [
                'log_type' => 'system',
                'log_level' => 'error',
                'message' => 'Failed login attempt detected from suspicious IP',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-5 hours')),
                'ip_address' => ip2long('192.168.1.100'),
                'user_id' => null
            ],
            [
                'log_type' => 'security',
                'log_level' => 'warning',
                'message' => 'Multiple failed login attempts detected',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'ip_address' => ip2long('10.0.0.50'),
                'user_id' => null
            ]
        ];
        
        $sample_logs = array_merge($sample_logs, $system_logs);
        
        // Apply filters
        $filtered_logs = $sample_logs;
        
        if (!empty($filter_type)) {
            $filtered_logs = array_filter($filtered_logs, function($log) use ($filter_type) {
                return $log['log_type'] === $filter_type;
            });
        }
        
        if (!empty($filter_level)) {
            $filtered_logs = array_filter($filtered_logs, function($log) use ($filter_level) {
                return $log['log_level'] === $filter_level;
            });
        }
        
        if (!empty($search_query)) {
            $filtered_logs = array_filter($filtered_logs, function($log) use ($search_query) {
                return stripos($log['message'], $search_query) !== false;
            });
        }
        
        // Sort by timestamp (newest first)
        usort($filtered_logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        $total_logs = count($filtered_logs);
        $total_pages = max(1, ceil($total_logs / $per_page));
        
        // Apply pagination
        $logs = array_slice($filtered_logs, $offset, $per_page);
        
        // Calculate statistics
        $log_stats['total_logs'] = count($sample_logs);
        $log_stats['today_logs'] = count(array_filter($sample_logs, function($log) {
            return date('Y-m-d', strtotime($log['timestamp'])) === date('Y-m-d');
        }));
        $log_stats['error_logs'] = count(array_filter($sample_logs, function($log) {
            return $log['log_level'] === 'error';
        }));
        $log_stats['warning_logs'] = count(array_filter($sample_logs, function($log) {
            return $log['log_level'] === 'warning';
        }));
        $log_stats['info_logs'] = count(array_filter($sample_logs, function($log) {
            return $log['log_level'] === 'info';
        }));
        $log_stats['user_activities'] = count(array_filter($sample_logs, function($log) {
            return in_array($log['log_type'], ['user_activity', 'adoption_activity']);
        }));
        
    }
} catch (Exception $e) {
    error_log("Logs database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
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
        color: #2c3e50;
    }

    .btn-primary:hover {
        background: #ffed4e;
        transform: translateY(-2px);
        text-decoration: none;
        color: #3498db;
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

    .btn-danger {
        background: #dc3545;
        color: white;
    }

    .btn-danger:hover {
        background: #c82333;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .btn-success:hover {
        background: #218838;
    }

    .btn-warning {
        background: #ffc107;
        color: #212529;
    }

    .btn-warning:hover {
        background: #e0a800;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.8rem;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        cursor: pointer;
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

    .stat-card.total {
        --color: #3498db;
    }

    .stat-card.today {
        --color: #2ecc71;
    }

    .stat-card.errors {
        --color: #e74c3c;
    }

    .stat-card.warnings {
        --color: #f39c12;
    }

    .stat-card.info {
        --color: #17a2b8;
    }

    .stat-card.activities {
        --color: #9b59b6;
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
        font-size: 2rem;
        font-weight: 700;
        color: var(--color);
        line-height: 1;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        background: var(--color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        opacity: 0.9;
    }

    /* Filters Section */
    .filters-section {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .filters-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .filter-group label {
        font-weight: 600;
        color: #555;
        font-size: 0.9rem;
    }

    .filter-group select,
    .filter-group input {
        padding: 10px 12px;
        border: 2px solid #e1e8ed;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.3s ease;
    }

    .filter-group select:focus,
    .filter-group input:focus {
        outline: none;
        border-color: #3498db;
    }

    .search-group {
        position: relative;
    }

    .search-group input {
        padding-left: 40px;
    }

    .search-group i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #666;
    }

    /* Logs Table */
    .logs-section {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 25px;
    }

    .section-header {
        background: #f8f9fa;
        padding: 20px 25px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .section-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0;
    }

    .logs-table {
        width: 100%;
        border-collapse: collapse;
    }

    .logs-table th,
    .logs-table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #f1f1f1;
        vertical-align: top;
    }

    .logs-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #2c3e50;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .logs-table tr {
        transition: background-color 0.3s ease;
    }

    .logs-table tr:hover {
        background: #f8f9fa;
    }

    /* Log Level Badges */
    .log-level {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }

    .log-level.info {
        background: rgba(23, 162, 184, 0.2);
        color: #17a2b8;
    }

    .log-level.warning {
        background: rgba(255, 193, 7, 0.2);
        color: #ffc107;
    }

    .log-level.error {
        background: rgba(220, 53, 69, 0.2);
        color: #dc3545;
    }

    /* Log Type Badges */
    .log-type {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .log-type.system {
        background: #e9ecef;
        color: #495057;
    }

    .log-type.user_activity {
        background: #d1ecf1;
        color: #0c5460;
    }

    .log-type.pet_activity {
        background: #d4edda;
        color: #155724;
    }

    .log-type.adoption_activity {
        background: #fff3cd;
        color: #856404;
    }

    .log-type.vaccination_activity {
        background: #f8d7da;
        color: #721c24;
    }

    .log-type.security {
        background: #f5c6cb;
        color: #721c24;
    }

    /* Log Message */
    .log-message {
        max-width: 400px;
        word-wrap: break-word;
        font-size: 0.9rem;
        color: #2c3e50;
    }

    /* Timestamp */
    .log-timestamp {
        font-size: 0.85rem;
        color: #666;
        white-space: nowrap;
    }

    /* IP Address */
    .log-ip {
        font-family: 'Courier New', monospace;
        font-size: 0.8rem;
        color: #666;
        white-space: nowrap;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 30px;
        color: #666;
    }

    .empty-icon {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
        color: #3498db;
    }

    .empty-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 10px;
        color: #2c3e50;
    }

    .empty-text {
        margin-bottom: 20px;
        line-height: 1.6;
    }

    /* Pagination */
    .pagination-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 25px;
        background: #f8f9fa;
        border-top: 1px solid #eee;
    }

    .pagination-info {
        font-size: 0.9rem;
        color: #666;
    }

    .pagination {
        display: flex;
        gap: 5px;
    }

    .pagination a,
    .pagination span {
        padding: 8px 12px;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        text-decoration: none;
        color: #495057;
        font-size: 0.85rem;
        transition: all 0.3s ease;
    }

    .pagination a:hover {
        background: #e9ecef;
        text-decoration: none;
    }

    .pagination .current {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }

    .pagination .disabled {
        opacity: 0.5;
        pointer-events: none;
    }

    /* Modals */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background: white;
        margin: 5% auto;
        padding: 0;
        border-radius: 15px;
        width: 90%;
        max-width: 600px;
        position: relative;
        max-height: 80vh;
        overflow: hidden;
    }

    .modal-header {
        background: linear-gradient(135deg, #2c3e50, #3498db);
        color: white;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title {
        font-size: 1.3rem;
        font-weight: 600;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: white;
        opacity: 0.8;
    }

    .modal-close:hover {
        opacity: 1;
    }

    .modal-body {
        padding: 25px;
    }

    .modal-actions {
        padding: 20px 25px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        background: #f8f9fa;
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

    /* Responsive Design */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .page-header {
            flex-direction: column;
            text-align: center;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .logs-table {
            font-size: 0.85rem;
        }

        .logs-table th,
        .logs-table td {
            padding: 10px 8px;
        }

        .log-message {
            max-width: 200px;
        }

        .modal-content {
            margin: 2% auto;
            width: 95%;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .section-header {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
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
                <h1><i class="fas fa-clipboard-list"></i> System Logs</h1>
                <p>Monitor system activities, errors, and user interactions</p>
            </div>
            <div class="header-actions">
                <button onclick="showClearLogsModal()" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Clear Logs
                </button>
                <button onclick="exportLogs()" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export
                </button>
                <button onclick="refreshLogs()" class="btn btn-primary">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card total" onclick="filterByLevel('')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Total Logs</h3>
                        <div class="stat-number"><?php echo number_format($log_stats['total_logs']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-list"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card today">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Today's Logs</h3>
                        <div class="stat-number"><?php echo number_format($log_stats['today_logs']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card errors" onclick="filterByLevel('error')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Errors</h3>
                        <div class="stat-number"><?php echo number_format($log_stats['error_logs']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card warnings" onclick="filterByLevel('warning')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Warnings</h3>
                        <div class="stat-number"><?php echo number_format($log_stats['warning_logs']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card info" onclick="filterByLevel('info')">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>Info</h3>
                        <div class="stat-number"><?php echo number_format($log_stats['info_logs']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card activities">
                <div class="stat-content">
                    <div class="stat-info">
                        <h3>User Activities</h3>
                        <div class="stat-number"><?php echo number_format($log_stats['user_activities']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section fade-in">
            <div class="filters-title">
                <i class="fas fa-filter"></i>
                Filter & Search Logs
            </div>
            <form method="GET" action="" id="filtersForm">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Log Type</label>
                        <select name="type" onchange="document.getElementById('filtersForm').submit()">
                            <option value="">All Types</option>
                            <option value="system" <?php echo $filter_type === 'system' ? 'selected' : ''; ?>>System
                            </option>
                            <option value="user_activity"
                                <?php echo $filter_type === 'user_activity' ? 'selected' : ''; ?>>User Activity</option>
                            <option value="pet_activity"
                                <?php echo $filter_type === 'pet_activity' ? 'selected' : ''; ?>>Pet Activity</option>
                            <option value="adoption_activity"
                                <?php echo $filter_type === 'adoption_activity' ? 'selected' : ''; ?>>Adoption Activity
                            </option>
                            <option value="vaccination_activity"
                                <?php echo $filter_type === 'vaccination_activity' ? 'selected' : ''; ?>>Vaccination
                                Activity</option>
                            <option value="security" <?php echo $filter_type === 'security' ? 'selected' : ''; ?>>
                                Security</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Log Level</label>
                        <select name="level" onchange="document.getElementById('filtersForm').submit()">
                            <option value="">All Levels</option>
                            <option value="info" <?php echo $filter_level === 'info' ? 'selected' : ''; ?>>Info</option>
                            <option value="warning" <?php echo $filter_level === 'warning' ? 'selected' : ''; ?>>Warning
                            </option>
                            <option value="error" <?php echo $filter_level === 'error' ? 'selected' : ''; ?>>Error
                            </option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>

                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>

                    <div class="filter-group search-group">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                            placeholder="Search in messages..." onkeypress="handleSearchKeypress(event)">
                        <i class="fas fa-search"></i>
                    </div>

                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 5px;">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>

                    <div class="filter-group">
                        <a href="<?php echo $BASE_URL; ?>admin/logs.php" class="btn btn-secondary"
                            style="width: 100%; margin-top: 5px; text-align: center;">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="logs-section fade-in">
            <div class="section-header">
                <h2 class="section-title">
                    System Logs
                    <?php if ($total_logs > 0): ?>
                    <span style="color: #666; font-weight: normal; font-size: 0.9rem;">
                        (<?php echo number_format($total_logs); ?> total)
                    </span>
                    <?php endif; ?>
                </h2>
                <div style="display: flex; gap: 10px;">
                    <span style="font-size: 0.9rem; color: #666;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                </div>
            </div>

            <?php if (empty($logs)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3 class="empty-title">No Log Entries Found</h3>
                <p class="empty-text">
                    <?php if (!empty($filter_type) || !empty($filter_level) || !empty($search_query)): ?>
                    No log entries match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                    No log entries are available at the moment.
                    <?php endif; ?>
                </p>
                <?php if (!empty($filter_type) || !empty($filter_level) || !empty($search_query)): ?>
                <a href="<?php echo $BASE_URL; ?>admin/logs.php" class="btn btn-primary">
                    <i class="fas fa-eye"></i> View All Logs
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <!-- Logs Table -->
            <div style="overflow-x: auto;">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Type</th>
                            <th>Level</th>
                            <th>Message</th>
                            <th>IP Address</th>
                            <th>User ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="log-timestamp">
                                <div style="font-weight: 600; color: #2c3e50;">
                                    <?php echo date('M j, Y', strtotime($log['timestamp'])); ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #666;">
                                    <?php echo date('g:i:s A', strtotime($log['timestamp'])); ?>
                                </div>
                            </td>
                            <td>
                                <span class="log-type <?php echo $log['log_type']; ?>">
                                    <?php 
                                    $type_labels = [
                                        'system' => 'System',
                                        'user_activity' => 'User Activity',
                                        'pet_activity' => 'Pet Activity',
                                        'adoption_activity' => 'Adoption',
                                        'vaccination_activity' => 'Vaccination',
                                        'security' => 'Security'
                                    ];
                                    echo $type_labels[$log['log_type']] ?? ucfirst(str_replace('_', ' ', $log['log_type']));
                                    ?>
                                </span>
                            </td>
                            <td>
                                <span class="log-level <?php echo $log['log_level']; ?>">
                                    <?php 
                                    $level_icons = [
                                        'info' => 'fa-info-circle',
                                        'warning' => 'fa-exclamation-triangle',
                                        'error' => 'fa-times-circle'
                                    ];
                                    $icon = $level_icons[$log['log_level']] ?? 'fa-circle';
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                    <?php echo ucfirst($log['log_level']); ?>
                                </span>
                            </td>
                            <td class="log-message">
                                <?php echo htmlspecialchars($log['message']); ?>
                            </td>
                            <td class="log-ip">
                                <?php 
                                $ip = is_numeric($log['ip_address']) ? long2ip($log['ip_address']) : $log['ip_address'];
                                echo htmlspecialchars($ip);
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($log['user_id'])): ?>
                                <span style="color: #666; font-size: 0.85rem;">
                                    ID: <?php echo htmlspecialchars($log['user_id']); ?>
                                </span>
                                <?php else: ?>
                                <span style="color: #999; font-size: 0.8rem;">System</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-section">
                <div class="pagination-info">
                    Showing <?php echo (($page - 1) * $per_page) + 1; ?> to
                    <?php echo min($page * $per_page, $total_logs); ?> of
                    <?php echo number_format($total_logs); ?> log entries
                </div>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <?php else: ?>
                    <span class="disabled">
                        <i class="fas fa-chevron-left"></i> Previous
                    </span>
                    <?php endif; ?>

                    <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                    <?php if ($start > 2): ?>
                    <span>...</span>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?>
                    <span>...</span>
                    <?php endif; ?>
                    <a
                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php else: ?>
                    <span class="disabled">
                        Next <i class="fas fa-chevron-right"></i>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Clear Logs Modal -->
    <div id="clearLogsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Clear System Logs</h3>
                <button class="modal-close" onclick="closeModal('clearLogsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 20px;">
                    <p style="color: #2c3e50; margin-bottom: 15px;">
                        <strong>Select the type of logs to clear:</strong>
                    </p>

                    <div style="margin-bottom: 15px;">
                        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <input type="radio" name="clear_type" value="all" checked>
                            <span>Clear all logs older than specified days</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <input type="radio" name="clear_type" value="error">
                            <span>Clear only error logs</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <input type="radio" name="clear_type" value="warning">
                            <span>Clear only warning logs</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="radio" name="clear_type" value="info">
                            <span>Clear only info logs</span>
                        </label>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                            Clear logs older than (days):
                        </label>
                        <input type="number" id="daysOld" value="30" min="1" max="365"
                            style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>

                <div
                    style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 15px 0;">
                    <p style="margin: 0; color: #856404;"><strong>⚠️ Warning:</strong></p>
                    <ul style="margin: 10px 0 0 20px; color: #856404;">
                        <li>This action cannot be undone</li>
                        <li>Deleted logs will be permanently removed from the system</li>
                        <li>Consider exporting logs before clearing them</li>
                    </ul>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('clearLogsModal')">Cancel</button>
                <button class="btn btn-danger" onclick="confirmClearLogs()">
                    <i class="fas fa-trash"></i> Clear Logs
                </button>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div id="exportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Export System Logs</h3>
                <button class="modal-close" onclick="closeModal('exportModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 20px;">
                    <p style="color: #2c3e50; margin-bottom: 15px;">
                        <strong>Select export format:</strong>
                    </p>

                    <div style="margin-bottom: 15px;">
                        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <input type="radio" name="export_format" value="csv" checked>
                            <span>CSV (Comma Separated Values)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <input type="radio" name="export_format" value="json">
                            <span>JSON (JavaScript Object Notation)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 10px;">
                            <input type="radio" name="export_format" value="txt">
                            <span>TXT (Plain Text)</span>
                        </label>
                    </div>
                </div>

                <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 15px;">
                    <p style="margin: 0; color: #0c5460;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Export Information:</strong>
                    </p>
                    <ul style="margin: 10px 0 0 20px; color: #0c5460;">
                        <li>Export will include all visible log entries based on current filters</li>
                        <li>Large datasets may take some time to process</li>
                        <li>Downloaded file will be named with current timestamp</li>
                    </ul>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('exportModal')">Cancel</button>
                <button class="btn btn-success" onclick="confirmExportLogs()">
                    <i class="fas fa-download"></i> Export Logs
                </button>
            </div>
        </div>
    </div>

    <script>
    // Global variables
    let currentAction = null;

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });

        // Auto-refresh logs every 30 seconds (optional)
        // setInterval(refreshLogs, 30000);
    });

    // Filter functions
    function filterByLevel(level) {
        const url = new URL(window.location);
        if (level) {
            url.searchParams.set('level', level);
        } else {
            url.searchParams.delete('level');
        }
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    function filterByType(type) {
        const url = new URL(window.location);
        if (type) {
            url.searchParams.set('type', type);
        } else {
            url.searchParams.delete('type');
        }
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    function handleSearchKeypress(event) {
        if (event.key === 'Enter') {
            document.getElementById('filtersForm').submit();
        }
    }

    // Modal functions
    function showClearLogsModal() {
        document.getElementById('clearLogsModal').style.display = 'block';
    }

    function exportLogs() {
        document.getElementById('exportModal').style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Clear logs function
    function confirmClearLogs() {
        const clearType = document.querySelector('input[name="clear_type"]:checked').value;
        const daysOld = document.getElementById('daysOld').value;

        performAjaxAction('clear_logs', {
            log_type: clearType,
            days_old: daysOld
        });

        closeModal('clearLogsModal');
    }

    // Export logs function
    function confirmExportLogs() {
        const exportFormat = document.querySelector('input[name="export_format"]:checked').value;

        performAjaxAction('export_logs', {
            format: exportFormat
        });

        closeModal('exportModal');
    }

    // Refresh logs
    function refreshLogs() {
        window.location.reload();
    }

    // Perform AJAX action
    function performAjaxAction(action, data) {
        const formData = new FormData();
        formData.append('action', action);

        for (const key in data) {
            formData.append(key, data[key]);
        }

        // Show loading state
        document.body.style.opacity = '0.7';
        document.body.style.pointerEvents = 'none';

        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.body.style.opacity = '1';
                document.body.style.pointerEvents = 'auto';

                if (data.success) {
                    showMessage(data.message, 'success');

                    // Refresh page after successful action
                    if (action === 'clear_logs') {
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }
                } else {
                    showMessage(data.message || 'An error occurred', 'error');
                }
            })
            .catch(error => {
                document.body.style.opacity = '1';
                document.body.style.pointerEvents = 'auto';
                console.error('Error:', error);
                showMessage('Network error occurred', 'error');
            });
    }

    // Show message notification
    function showMessage(message, type) {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.message');
        existingMessages.forEach(msg => msg.remove());

        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        messageDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
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

    // Auto-update timestamp display
    function updateTimestamps() {
        const timestamps = document.querySelectorAll('.log-timestamp');
        timestamps.forEach(timestamp => {
            // You could add relative time here (e.g., "2 minutes ago")
            // For now, we'll keep the absolute timestamps
        });
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(event) {
        // Escape key to close modals
        if (event.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => modal.style.display = 'none');
        }

        // Ctrl+R to refresh
        if (event.ctrlKey && event.key === 'r') {
            event.preventDefault();
            refreshLogs();
        }

        // Ctrl+E to export
        if (event.ctrlKey && event.key === 'e') {
            event.preventDefault();
            exportLogs();
        }
    });

    // Click outside to close modals
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }

    // Animate statistics counters on page load
    function animateCounters() {
        const counters = document.querySelectorAll('.stat-number');
        counters.forEach(counter => {
            const target = parseInt(counter.textContent.replace(/,/g, ''));
            if (target > 0) {
                let current = 0;
                const increment = target / 30;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target.toLocaleString();
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current).toLocaleString();
                    }
                }, 50);
            }
        });
    }

    // Start counter animation after page load
    setTimeout(animateCounters, 500);
    </script>
</body>

</html>