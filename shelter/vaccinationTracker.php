<?php
// Start session and include database
require_once '../config/db.php';

// Check if user is logged in and is a shelter
if (!Session::isLoggedIn() || Session::getUserType() !== 'shelter') {
    header('Location: ../auth/login.php');
    exit();
}

$shelter_user_id = Session::getUserId();

// Get shelter information
$shelter_data = DBHelper::selectOne(
    "SELECT shelter_id, shelter_name FROM shelters WHERE user_id = ?",
    [$shelter_user_id]
);

if (!$shelter_data) {
    die("Error: Shelter not found for this user. Please contact administrator.");
}

$shelter_id = $shelter_data['shelter_id'];
$shelter_name = $shelter_data['shelter_name'];

// Initialize message variables
$success_message = '';
$error_message = '';

// Handle Add Vaccination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vaccination'])) {
    try {
        $pet_id = (int)$_POST['pet_id'];
        $vaccine_name = Security::sanitize($_POST['vaccine_name']);
        $vaccination_date = $_POST['vaccination_date'];
        $next_due_date = !empty($_POST['next_due_date']) ? $_POST['next_due_date'] : null;
        $veterinarian_name = !empty($_POST['veterinarian_name']) ? Security::sanitize($_POST['veterinarian_name']) : null;
        $notes = !empty($_POST['notes']) ? Security::sanitize($_POST['notes']) : null;

        // Validate required fields
        if (empty($pet_id) || empty($vaccine_name) || empty($vaccination_date)) {
            $error_message = "Please fill in all required fields.";
        } else {
            // Verify pet belongs to this shelter
            $pet_exists = DBHelper::exists('pets', ['pet_id' => $pet_id, 'shelter_id' => $shelter_id]);
            
            if (!$pet_exists) {
                $error_message = "Invalid pet selection.";
            } else {
                // Check for duplicate vaccination (same pet, same vaccine, same date)
                $duplicate_check = DBHelper::selectOne(
                    "SELECT vaccination_id FROM vaccinations WHERE pet_id = ? AND vaccine_name = ? AND vaccination_date = ?",
                    [$pet_id, $vaccine_name, $vaccination_date]
                );
                
                if ($duplicate_check) {
                    $error_message = "A vaccination record for this pet with the same vaccine and date already exists.";
                } else {
                    $insert_id = DBHelper::insert(
                        "INSERT INTO vaccinations (pet_id, vaccine_name, vaccination_date, next_due_date, veterinarian_name, notes) VALUES (?, ?, ?, ?, ?, ?)",
                        [$pet_id, $vaccine_name, $vaccination_date, $next_due_date, $veterinarian_name, $notes]
                    );
                    
                    if ($insert_id) {
                        $success_message = "Vaccination record added successfully!";
                    } else {
                        $error_message = "Error adding vaccination record. Please try again.";
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error_message = "System error: " . $e->getMessage();
        error_log("Vaccination add error: " . $e->getMessage());
    }
}

// Handle Delete Vaccination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_vaccination'])) {
    try {
        $vaccination_id = (int)$_POST['vaccination_id'];
        
        // Verify vaccination belongs to this shelter's pet
        $vaccination_exists = DBHelper::selectOne(
            "SELECT v.vaccination_id, p.pet_name 
             FROM vaccinations v 
             INNER JOIN pets p ON v.pet_id = p.pet_id 
             WHERE v.vaccination_id = ? AND p.shelter_id = ?",
            [$vaccination_id, $shelter_id]
        );
        
        if (!$vaccination_exists) {
            $error_message = "Invalid vaccination record or access denied.";
        } else {
            $affected_rows = DBHelper::execute(
                "DELETE FROM vaccinations WHERE vaccination_id = ?",
                [$vaccination_id]
            );
            
            if ($affected_rows > 0) {
                $success_message = "Vaccination record for {$vaccination_exists['pet_name']} deleted successfully!";
            } else {
                $error_message = "Error deleting vaccination record. Please try again.";
            }
        }
    } catch (Exception $e) {
        $error_message = "System error: " . $e->getMessage();
        error_log("Vaccination delete error: " . $e->getMessage());
    }
}

// Handle Bulk Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_ids = $_POST['selected_vaccinations'] ?? [];
    
    if (empty($selected_ids)) {
        $error_message = "Please select at least one vaccination record.";
    } else {
        try {
            if ($action === 'delete') {
                $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
                $params = array_merge($selected_ids, [$shelter_id]);
                
                $affected_rows = DBHelper::execute(
                    "DELETE v FROM vaccinations v 
                     INNER JOIN pets p ON v.pet_id = p.pet_id 
                     WHERE v.vaccination_id IN ($placeholders) AND p.shelter_id = ?",
                    $params
                );
                
                if ($affected_rows > 0) {
                    $success_message = "Successfully deleted $affected_rows vaccination record(s).";
                } else {
                    $error_message = "No records were deleted. Please check your selection.";
                }
            }
        } catch (Exception $e) {
            $error_message = "Bulk operation error: " . $e->getMessage();
            error_log("Vaccination bulk delete error: " . $e->getMessage());
        }
    }
}

// Get all pets from this shelter
$pets = DBHelper::select(
    "SELECT pet_id, pet_name, category_id FROM pets WHERE shelter_id = ? ORDER BY pet_name",
    [$shelter_id]
);

// Get vaccination records with pet details and enhanced information
$vaccinations = DBHelper::select(
    "SELECT v.*, p.pet_name, p.pet_id, pc.category_name,
            CASE 
                WHEN v.next_due_date IS NULL THEN 'no_due_date'
                WHEN v.next_due_date < CURDATE() THEN 'overdue'
                WHEN v.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'due_this_week'
                WHEN v.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'due_soon'
                ELSE 'current'
            END as status_category,
            DATEDIFF(v.next_due_date, CURDATE()) as days_until_due
     FROM vaccinations v
     INNER JOIN pets p ON v.pet_id = p.pet_id
     LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
     WHERE p.shelter_id = ?
     ORDER BY 
        CASE 
            WHEN v.next_due_date IS NULL THEN 3
            WHEN v.next_due_date < CURDATE() THEN 1
            WHEN v.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 2
            ELSE 4
        END,
        v.next_due_date ASC, 
        v.vaccination_date DESC",
    [$shelter_id]
);

// Calculate statistics
$stats = [
    'total' => count($vaccinations),
    'overdue' => 0,
    'due_this_week' => 0,
    'due_this_month' => 0,
    'no_due_date' => 0
];

foreach ($vaccinations as $vaccination) {
    switch ($vaccination['status_category']) {
        case 'overdue':
            $stats['overdue']++;
            break;
        case 'due_this_week':
            $stats['due_this_week']++;
            break;
        case 'due_soon':
            $stats['due_this_month']++;
            break;
        case 'no_due_date':
            $stats['no_due_date']++;
            break;
    }
}

// Get upcoming vaccination reminders
$upcoming_reminders = DBHelper::select(
    "SELECT v.*, p.pet_name, DATEDIFF(v.next_due_date, CURDATE()) as days_until_due
     FROM vaccinations v
     INNER JOIN pets p ON v.pet_id = p.pet_id
     WHERE p.shelter_id = ? 
     AND v.next_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
     ORDER BY v.next_due_date ASC
     LIMIT 5",
    [$shelter_id]
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Tracker - <?php echo htmlspecialchars($shelter_name); ?></title>
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

    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
        background: rgba(255, 255, 255, 0.95);
        min-height: 100vh;
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.1);
    }

    .header-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }

    .header-section::before {
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

    .header-content {
        position: relative;
        z-index: 2;
    }

    .header-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .header-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        font-weight: 300;
    }

    .shelter-info {
        margin-top: 15px;
        padding: 15px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        border-left: 4px solid #fff;
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

    .stat-card.overdue {
        --card-color: #ff6b6b;
    }

    .stat-card.due-week {
        --card-color: #ffa726;
    }

    .stat-card.due-month {
        --card-color: #66bb6a;
    }

    .stat-card.total {
        --card-color: #42a5f5;
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
        grid-template-columns: 1fr 350px;
        gap: 30px;
        margin-bottom: 30px;
    }

    .card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
    }

    .card-header {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 25px;
        border-bottom: 1px solid #e9ecef;
    }

    .card-header h3 {
        color: #495057;
        font-size: 1.4rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-body {
        padding: 25px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #555;
        font-size: 0.95rem;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: #fafafa;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 10px;
        font-size: 1rem;
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

    .btn-danger {
        background: linear-gradient(135deg, #ff6b6b, #ee5a52);
        color: white;
        padding: 8px 15px;
        font-size: 0.9rem;
    }

    .btn-warning {
        background: linear-gradient(135deg, #ffa726, #ff9800);
        color: white;
    }

    .btn-success {
        background: linear-gradient(135deg, #66bb6a, #4caf50);
        color: white;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .quick-vaccines {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        margin-top: 10px;
    }

    .vaccine-btn {
        padding: 8px 12px;
        background: #e3f2fd;
        border: 1px solid #90caf9;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85rem;
        text-align: center;
        transition: all 0.2s ease;
    }

    .vaccine-btn:hover {
        background: #bbdefb;
    }

    .reminders-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .reminder-item {
        display: flex;
        align-items: center;
        padding: 12px;
        margin-bottom: 10px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 4px solid #ffa726;
    }

    .reminder-content {
        flex: 1;
    }

    .reminder-pet {
        font-weight: 600;
        color: #333;
    }

    .reminder-vaccine {
        font-size: 0.9rem;
        color: #666;
    }

    .reminder-days {
        background: #ffa726;
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .table-section {
        grid-column: 1 / -1;
    }

    .table-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .filter-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 8px 16px;
        border: 2px solid #e9ecef;
        background: white;
        border-radius: 20px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .filter-btn.active,
    .filter-btn:hover {
        background: #667eea;
        border-color: #667eea;
        color: white;
    }

    .search-box {
        display: flex;
        align-items: center;
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 25px;
        padding: 5px 15px;
        min-width: 250px;
    }

    .search-box input {
        border: none;
        outline: none;
        padding: 8px;
        flex: 1;
        background: transparent;
    }

    .table-responsive {
        overflow-x: auto;
        background: white;
        border-radius: 10px;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem;
    }

    .table th {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 15px 12px;
        text-align: left;
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .table td {
        padding: 15px 12px;
        border-bottom: 1px solid #e9ecef;
        vertical-align: middle;
    }

    .table tbody tr {
        transition: all 0.2s ease;
    }

    .table tbody tr:hover {
        background: #f8f9fa;
    }

    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-align: center;
        min-width: 80px;
        display: inline-block;
    }

    .status-overdue {
        background: #ffebee;
        color: #c62828;
        border: 1px solid #ffcdd2;
    }

    .status-due-this-week {
        background: #fff3e0;
        color: #ef6c00;
        border: 1px solid #ffcc02;
    }

    .status-due-soon {
        background: #e8f5e8;
        color: #2e7d32;
        border: 1px solid #c8e6c9;
    }

    .status-current {
        background: #e3f2fd;
        color: #1565c0;
        border: 1px solid #90caf9;
    }

    .status-no-due-date {
        background: #f3e5f5;
        color: #7b1fa2;
        border: 1px solid #ce93d8;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
        border-color: #c3e6cb;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
        border-color: #f5c6cb;
    }

    .no-records {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }

    .no-records-icon {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .bulk-actions {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 15px;
        display: none;
    }

    .bulk-actions.show {
        display: block;
    }

    .checkbox-column {
        width: 40px;
        text-align: center;
    }

    .pet-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .pet-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
    }

    @media (max-width: 768px) {
        .container {
            padding: 10px;
        }

        .header-title {
            font-size: 2rem;
        }

        .main-content {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .table-controls {
            flex-direction: column;
            align-items: stretch;
        }

        .filter-buttons {
            justify-content: center;
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
    </style>
</head>

<body>
    <?php 
    // Include navbar if exists, otherwise show a simple one
    if (file_exists('../common/navbar_shelter.php')) {
        include '../common/navbar_shelter.php';
    } else {
        echo '<nav style="background: #343a40; padding: 1rem; color: white; margin-bottom: 0;">
                <div style="max-width: 1400px; margin: 0 auto; display: flex; align-items: center; gap: 15px;">
                    <i class="fas fa-home"></i>
                    <h2 style="margin: 0;">Shelter Dashboard</h2>
                    <span style="margin-left: auto;">Welcome, ' . htmlspecialchars(Session::get('first_name', 'User')) . '</span>
                </div>
              </nav>';
    }
    ?>

    <div class="container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-content">
                <h1 class="header-title">
                    <i class="fas fa-syringe"></i>
                    Vaccination Tracker
                </h1>
                <p class="header-subtitle">Monitor and manage vaccination schedules for all your pets</p>
                <div class="shelter-info">
                    <i class="fas fa-home"></i>
                    <strong><?php echo htmlspecialchars($shelter_name); ?></strong> • Total Pets:
                    <?php echo count($pets); ?>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card overdue">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['overdue']; ?></div>
                <div class="stat-label">Overdue Vaccinations</div>
            </div>

            <div class="stat-card due-week">
                <div class="stat-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-number"><?php echo $stats['due_this_week']; ?></div>
                <div class="stat-label">Due This Week</div>
            </div>

            <div class="stat-card due-month">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo $stats['due_this_month']; ?></div>
                <div class="stat-label">Due This Month</div>
            </div>

            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Records</div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Add Vaccination Form -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-plus-circle"></i>
                        Add New Vaccination
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="vaccinationForm">
                        <div class="form-group">
                            <label for="pet_id">
                                <i class="fas fa-paw"></i>
                                Select Pet *
                            </label>
                            <select name="pet_id" id="pet_id" class="form-control" required>
                                <option value="">Choose a pet...</option>
                                <?php foreach ($pets as $pet): ?>
                                <option value="<?php echo $pet['pet_id']; ?>"
                                    data-category="<?php echo $pet['category_id']; ?>">
                                    <?php echo htmlspecialchars($pet['pet_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="vaccine_name">
                                <i class="fas fa-prescription-bottle-alt"></i>
                                Vaccine Name *
                            </label>
                            <input type="text" name="vaccine_name" id="vaccine_name" class="form-control"
                                placeholder="Enter vaccine name" required maxlength="100">
                            <div id="quickVaccines" class="quick-vaccines" style="margin-top: 10px;"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="vaccination_date">
                                    <i class="fas fa-calendar"></i>
                                    Vaccination Date *
                                </label>
                                <input type="date" name="vaccination_date" id="vaccination_date" class="form-control"
                                    required max="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="form-group">
                                <label for="next_due_date">
                                    <i class="fas fa-calendar-plus"></i>
                                    Next Due Date
                                </label>
                                <input type="date" name="next_due_date" id="next_due_date" class="form-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="veterinarian_name">
                                <i class="fas fa-user-md"></i>
                                Veterinarian Name
                            </label>
                            <input type="text" name="veterinarian_name" id="veterinarian_name" class="form-control"
                                placeholder="Dr. Smith" maxlength="100">
                        </div>

                        <div class="form-group">
                            <label for="notes">
                                <i class="fas fa-sticky-note"></i>
                                Notes
                            </label>
                            <textarea name="notes" id="notes" class="form-control" rows="3"
                                placeholder="Additional notes about the vaccination..."></textarea>
                        </div>

                        <button type="submit" name="add_vaccination" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-syringe"></i>
                            Add Vaccination Record
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Quick Actions -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h3>
                    </div>
                    <div class="card-body">
                        <button onclick="exportData()" class="btn btn-success"
                            style="width: 100%; margin-bottom: 10px;">
                            <i class="fas fa-download"></i>
                            Export Records
                        </button>
                        <button onclick="showBulkActions()" class="btn btn-warning"
                            style="width: 100%; margin-bottom: 10px;">
                            <i class="fas fa-tasks"></i>
                            Bulk Actions
                        </button>
                        <button onclick="printReport()" class="btn btn-secondary" style="width: 100%;">
                            <i class="fas fa-print"></i>
                            Print Report
                        </button>
                    </div>
                </div>

                <!-- Upcoming Reminders -->
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-bell"></i>
                            Upcoming Reminders
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="reminders-list">
                            <?php if (!empty($upcoming_reminders)): ?>
                            <?php foreach ($upcoming_reminders as $reminder): ?>
                            <div class="reminder-item">
                                <div class="reminder-content">
                                    <div class="reminder-pet"><?php echo htmlspecialchars($reminder['pet_name']); ?>
                                    </div>
                                    <div class="reminder-vaccine">
                                        <?php echo htmlspecialchars($reminder['vaccine_name']); ?></div>
                                </div>
                                <div class="reminder-days">
                                    <?php echo $reminder['days_until_due']; ?>d
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div style="text-align: center; color: #666; padding: 20px;">
                                <i class="fas fa-check-circle"
                                    style="font-size: 2rem; margin-bottom: 10px; opacity: 0.3;"></i>
                                <p>No upcoming vaccinations in the next 14 days</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vaccination Records Table -->
        <div class="card table-section">
            <div class="card-header">
                <h3>
                    <i class="fas fa-table"></i>
                    Vaccination Records
                </h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Bulk Actions Bar -->
                <div class="bulk-actions" id="bulkActionsBar">
                    <form method="POST" onsubmit="return confirmBulkAction()">
                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <span id="selectedCount">0 selected</span>
                            <select name="bulk_action" class="form-control" style="width: auto; min-width: 150px;">
                                <option value="">Choose action...</option>
                                <option value="delete">Delete Selected</option>
                            </select>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i>
                                Apply
                            </button>
                            <button type="button" onclick="hideBulkActions()" class="btn btn-secondary">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Table Controls -->
                <div class="table-controls" style="padding: 20px;">
                    <div class="filter-buttons">
                        <button class="filter-btn active" onclick="filterTable('all')">
                            <i class="fas fa-list"></i> All
                        </button>
                        <button class="filter-btn" onclick="filterTable('overdue')">
                            <i class="fas fa-exclamation-triangle"></i> Overdue
                        </button>
                        <button class="filter-btn" onclick="filterTable('due_this_week')">
                            <i class="fas fa-calendar-week"></i> This Week
                        </button>
                        <button class="filter-btn" onclick="filterTable('due_soon')">
                            <i class="fas fa-calendar-alt"></i> This Month
                        </button>
                    </div>

                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchBox" placeholder="Search pets, vaccines..."
                            onkeyup="searchTable()">
                    </div>
                </div>

                <?php if (!empty($vaccinations)): ?>
                <div class="table-responsive">
                    <table class="table" id="vaccinationTable">
                        <thead>
                            <tr>
                                <th class="checkbox-column">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Pet</th>
                                <th>Vaccine</th>
                                <th>Date Given</th>
                                <th>Next Due Date</th>
                                <th>Days Until Due</th>
                                <th>Veterinarian</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vaccinations as $vaccination): 
                                    // Determine status display
                                    $status_info = [
                                        'overdue' => ['class' => 'status-overdue', 'text' => 'Overdue', 'icon' => 'fas fa-exclamation-triangle'],
                                        'due_this_week' => ['class' => 'status-due-this-week', 'text' => 'Due This Week', 'icon' => 'fas fa-calendar-week'],
                                        'due_soon' => ['class' => 'status-due-soon', 'text' => 'Due Soon', 'icon' => 'fas fa-calendar-alt'],
                                        'current' => ['class' => 'status-current', 'text' => 'Current', 'icon' => 'fas fa-check-circle'],
                                        'no_due_date' => ['class' => 'status-no-due-date', 'text' => 'No Due Date', 'icon' => 'fas fa-question-circle']
                                    ];
                                    
                                    $current_status = $status_info[$vaccination['status_category']] ?? $status_info['current'];
                                    $pet_initial = strtoupper(substr($vaccination['pet_name'], 0, 1));
                                ?>
                            <tr data-status="<?php echo $vaccination['status_category']; ?>" class="vaccination-row">
                                <td class="checkbox-column">
                                    <input type="checkbox" name="selected_vaccinations[]"
                                        value="<?php echo $vaccination['vaccination_id']; ?>" class="row-checkbox"
                                        onchange="updateBulkActions()">
                                </td>
                                <td>
                                    <div class="pet-info">
                                        <div class="pet-avatar">
                                            <?php echo $pet_initial; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #333;">
                                                <?php echo htmlspecialchars($vaccination['pet_name']); ?>
                                            </div>
                                            <div style="font-size: 0.85rem; color: #666;">
                                                <?php echo htmlspecialchars($vaccination['category_name'] ?? 'Unknown'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;">
                                        <?php echo htmlspecialchars($vaccination['vaccine_name']); ?>
                                    </div>
                                    <?php if ($vaccination['notes']): ?>
                                    <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">
                                        <i class="fas fa-sticky-note" style="margin-right: 5px;"></i>
                                        <?php 
                                                    $notes = htmlspecialchars($vaccination['notes']);
                                                    echo strlen($notes) > 50 ? substr($notes, 0, 50) . '...' : $notes; 
                                                    ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 500;">
                                        <?php echo date('M j, Y', strtotime($vaccination['vaccination_date'])); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #666;">
                                        <?php echo date('l', strtotime($vaccination['vaccination_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($vaccination['next_due_date']): ?>
                                    <div style="font-weight: 500;">
                                        <?php echo date('M j, Y', strtotime($vaccination['next_due_date'])); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #666;">
                                        <?php echo date('l', strtotime($vaccination['next_due_date'])); ?>
                                    </div>
                                    <?php else: ?>
                                    <em style="color: #999;">
                                        <i class="fas fa-question-circle"></i>
                                        Not set
                                    </em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($vaccination['next_due_date'] && $vaccination['status_category'] !== 'no_due_date'): ?>
                                    <?php 
                                                $days = (int)$vaccination['days_until_due'];
                                                if ($days < 0): ?>
                                    <span
                                        style="color: #c62828; font-weight: 600; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php echo abs($days); ?> day<?php echo abs($days) != 1 ? 's' : ''; ?> overdue
                                    </span>
                                    <?php elseif ($days == 0): ?>
                                    <span
                                        style="color: #ef6c00; font-weight: 600; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-calendar-day"></i>
                                        Due today
                                    </span>
                                    <?php elseif ($days <= 7): ?>
                                    <span
                                        style="color: #ef6c00; font-weight: 600; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $days; ?> day<?php echo $days != 1 ? 's' : ''; ?>
                                    </span>
                                    <?php else: ?>
                                    <span
                                        style="color: #2e7d32; font-weight: 500; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-calendar-check"></i>
                                        <?php echo $days; ?> day<?php echo $days != 1 ? 's' : ''; ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span style="color: #999;">
                                        <i class="fas fa-minus"></i>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($vaccination['veterinarian_name']): ?>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-user-md" style="color: #667eea;"></i>
                                        <span style="font-weight: 500;">
                                            <?php echo htmlspecialchars($vaccination['veterinarian_name']); ?>
                                        </span>
                                    </div>
                                    <?php else: ?>
                                    <em style="color: #999; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-user-times"></i>
                                        Not specified
                                    </em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $current_status['class']; ?>">
                                        <i class="<?php echo $current_status['icon']; ?>"></i>
                                        <?php echo $current_status['text']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button onclick="viewDetails(<?php echo $vaccination['vaccination_id']; ?>)"
                                            class="btn"
                                            style="background: #17a2b8; color: white; padding: 6px 10px; font-size: 0.8rem;"
                                            title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirmDelete('<?php echo htmlspecialchars($vaccination['vaccine_name']); ?>', '<?php echo htmlspecialchars($vaccination['pet_name']); ?>');">
                                            <input type="hidden" name="vaccination_id"
                                                value="<?php echo $vaccination['vaccination_id']; ?>">
                                            <button type="submit" name="delete_vaccination" class="btn btn-danger"
                                                style="padding: 6px 10px; font-size: 0.8rem;" title="Delete Record">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-records">
                    <div class="no-records-icon">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <h3>No Vaccination Records Yet</h3>
                    <p>Start by adding vaccination records for your pets using the form above.</p>
                    <?php if (empty($pets)): ?>
                    <div
                        style="margin-top: 20px; padding: 20px; background: #fff3cd; border-radius: 10px; border-left: 4px solid #ffc107;">
                        <p style="margin: 0; color: #856404;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> You need to add pets to your shelter first before you can track
                            vaccinations.
                        </p>
                        <a href="addPet.php" class="btn btn-primary" style="margin-top: 15px; text-decoration: none;">
                            <i class="fas fa-plus"></i>
                            Add Your First Pet
                        </a>
                    </div>
                    <?php else: ?>
                    <div style="margin-top: 20px;">
                        <p style="color: #666;">You have <?php echo count($pets); ?>
                            pet<?php echo count($pets) != 1 ? 's' : ''; ?> ready for vaccination tracking.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Vaccination Details Modal -->
    <div id="detailsModal"
        style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center;">
        <div
            style="background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin: 0; color: #333; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-syringe" style="color: #667eea;"></i>
                    Vaccination Details
                </h3>
                <button onclick="closeDetailsModal()"
                    style="background: none; border: none; font-size: 1.5rem; color: #999; cursor: pointer; padding: 5px;"
                    title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="detailsContent">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
    // Common vaccines data based on pet categories
    const commonVaccines = {
        '1': ['DHPP (Distemper)', 'Rabies', 'Bordetella', 'Lyme Disease', 'Canine Influenza',
            'Heartworm Prevention'
        ],
        '2': ['FVRCP', 'Rabies', 'FeLV (Leukemia)', 'FIV', 'Bordetella'],
        '3': ['Polyomavirus', 'PBFD', 'Pacheco Disease'],
        '4': ['RHDV', 'Myxomatosis'],
        'default': ['Rabies', 'Annual Checkup', 'Custom Vaccine']
    };

    // Initialize page when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializePage();
    });

    function initializePage() {
        // Set today's date as default
        const today = new Date().toISOString().split('T')[0];
        const vaccinationDateInput = document.getElementById('vaccination_date');
        if (vaccinationDateInput) {
            vaccinationDateInput.value = today;
        }

        // Initialize quick vaccines
        updateQuickVaccines();

        // Add event listeners
        const petSelect = document.getElementById('pet_id');
        const vaccineDateInput = document.getElementById('vaccination_date');

        if (petSelect) {
            petSelect.addEventListener('change', updateQuickVaccines);
        }

        if (vaccineDateInput) {
            vaccineDateInput.addEventListener('change', calculateNextDueDate);
        }

        // Initialize form validation
        initializeFormValidation();
    }

    // Update quick vaccine buttons based on selected pet
    function updateQuickVaccines() {
        const petSelect = document.getElementById('pet_id');
        const quickVaccinesDiv = document.getElementById('quickVaccines');

        if (!petSelect || !quickVaccinesDiv) return;

        const selectedOption = petSelect.options[petSelect.selectedIndex];
        const categoryId = selectedOption.getAttribute('data-category') || 'default';

        const vaccines = commonVaccines[categoryId] || commonVaccines['default'];

        quickVaccinesDiv.innerHTML = vaccines.map(vaccine =>
            `<div class="vaccine-btn" onclick="selectVaccine('${vaccine.replace(/'/g, "\\'")}')" title="Click to select">${vaccine}</div>`
        ).join('');
    }

    // Select vaccine from quick buttons
    function selectVaccine(vaccineName) {
        const vaccineNameInput = document.getElementById('vaccine_name');
        if (vaccineNameInput) {
            vaccineNameInput.value = vaccineName;

            // Highlight selected button
            document.querySelectorAll('.vaccine-btn').forEach(btn => {
                btn.style.background = btn.textContent === vaccineName ? '#90caf9' : '#e3f2fd';
                btn.style.fontWeight = btn.textContent === vaccineName ? '600' : 'normal';
            });
        }
    }

    // Auto-calculate next due date (1 year later)
    function calculateNextDueDate() {
        const vaccinationDateInput = document.getElementById('vaccination_date');
        const nextDueDateInput = document.getElementById('next_due_date');

        if (!vaccinationDateInput || !nextDueDateInput) return;

        const vaccinationDate = vaccinationDateInput.value;
        if (vaccinationDate) {
            const nextDue = new Date(vaccinationDate);
            nextDue.setFullYear(nextDue.getFullYear() + 1);
            nextDueDateInput.value = nextDue.toISOString().split('T')[0];
        }
    }

    // Filter table function
    function filterTable(filterType) {
        const rows = document.querySelectorAll('#vaccinationTable tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const status = row.getAttribute('data-status');
            if (filterType === 'all' || filterType === status) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
                // Uncheck hidden rows
                const checkbox = row.querySelector('.row-checkbox');
                if (checkbox) checkbox.checked = false;
            }
        });

        // Update active filter button
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');

        // Update bulk actions if any were visible
        updateBulkActions();

        // Show count
        console.log(`Filtered: ${visibleCount} records visible`);
    }

    // Search table function
    function searchTable() {
        const input = document.getElementById('searchBox');
        if (!input) return;

        const filter = input.value.toUpperCase();
        const rows = document.querySelectorAll('#vaccinationTable tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            let found = false;
            const cells = row.getElementsByTagName('td');

            // Search in pet name, vaccine, and veterinarian columns (indices 1, 2, 6)
            const searchableIndices = [1, 2, 6];
            for (let i of searchableIndices) {
                if (cells[i] && cells[i].textContent.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }

            if (found) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
                // Uncheck hidden rows
                const checkbox = row.querySelector('.row-checkbox');
                if (checkbox) checkbox.checked = false;
            }
        });

        // Update bulk actions
        updateBulkActions();

        console.log(`Search results: ${visibleCount} records found`);
    }

    // Bulk actions functionality
    function showBulkActions() {
        const bulkBar = document.getElementById('bulkActionsBar');
        if (bulkBar) {
            bulkBar.classList.add('show');
            updateBulkActions();
        }
    }

    function hideBulkActions() {
        const bulkBar = document.getElementById('bulkActionsBar');
        if (bulkBar) {
            bulkBar.classList.remove('show');
        }

        // Uncheck all checkboxes
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
        const selectAllCb = document.getElementById('selectAll');
        if (selectAllCb) selectAllCb.checked = false;
    }

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.row-checkbox');

        if (!selectAll) return;

        checkboxes.forEach(cb => {
            // Only select visible rows
            if (cb.closest('tr').style.display !== 'none') {
                cb.checked = selectAll.checked;
            }
        });

        updateBulkActions();
    }

    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
        const selectedCount = checkedBoxes.length;
        const selectedCountSpan = document.getElementById('selectedCount');
        const bulkBar = document.getElementById('bulkActionsBar');

        if (selectedCountSpan) {
            selectedCountSpan.textContent = `${selectedCount} selected`;
        }

        // Show/hide bulk actions bar
        if (selectedCount > 0 && bulkBar) {
            bulkBar.classList.add('show');
        }

        // Update select all checkbox state
        const allCheckboxes = document.querySelectorAll('.row-checkbox');
        const visibleCheckboxes = Array.from(allCheckboxes).filter(cb =>
            cb.closest('tr').style.display !== 'none'
        );

        const selectAllCb = document.getElementById('selectAll');
        if (selectAllCb) {
            if (visibleCheckboxes.length === 0) {
                selectAllCb.checked = false;
                selectAllCb.indeterminate = false;
            } else if (visibleCheckboxes.every(cb => cb.checked)) {
                selectAllCb.checked = true;
                selectAllCb.indeterminate = false;
            } else if (visibleCheckboxes.some(cb => cb.checked)) {
                selectAllCb.checked = false;
                selectAllCb.indeterminate = true;
            } else {
                selectAllCb.checked = false;
                selectAllCb.indeterminate = false;
            }
        }
    }

    function confirmBulkAction() {
        const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
        const action = document.querySelector('select[name="bulk_action"]').value;

        if (!action) {
            alert('Please select an action.');
            return false;
        }

        if (action === 'delete') {
            return confirm(
                `Are you sure you want to delete ${checkedBoxes.length} vaccination record(s)?\n\nThis action cannot be undone.`
            );
        }

        return true;
    }

    // View vaccination details
    function viewDetails(vaccinationId) {
        // Find the vaccination data from the table
        const row = document.querySelector(`input[value="${vaccinationId}"]`).closest('tr');
        const cells = row.getElementsByTagName('td');

        const petInfo = cells[1].textContent.trim();
        const vaccine = cells[2].textContent.trim();
        const dateGiven = cells[3].textContent.trim();
        const nextDue = cells[4].textContent.trim();
        const daysUntilDue = cells[5].textContent.trim();
        const veterinarian = cells[6].textContent.trim();
        const status = cells[7].textContent.trim();

        const content = `
                <div style="display: grid; gap: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-paw"></i> Pet
                            </label>
                            <div style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                ${petInfo}
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-syringe"></i> Vaccine
                            </label>
                            <div style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                ${vaccine}
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-calendar"></i> Date Given
                            </label>
                            <div style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                ${dateGiven}
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-calendar-plus"></i> Next Due Date
                            </label>
                            <div style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                ${nextDue}
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-clock"></i> Days Until Due
                            </label>
                            <div style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                ${daysUntilDue}
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-user-md"></i> Veterinarian
                            </label>
                            <div style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                ${veterinarian}
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                            <i class="fas fa-info-circle"></i> Status
                        </label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                            ${status}
                        </div>
                    </div>
                </div>
            `;

        document.getElementById('detailsContent').innerHTML = content;
        document.getElementById('detailsModal').style.display = 'flex';
    }

    function closeDetailsModal() {
        document.getElementById('detailsModal').style.display = 'none';
    }

    // Confirm delete function
    function confirmDelete(vaccineName, petName) {
        return confirm(
            `Are you sure you want to delete the ${vaccineName} vaccination record for ${petName}?\n\nThis action cannot be undone.`
        );
    }

    // Export data function
    function exportData() {
        const rows = document.querySelectorAll('#vaccinationTable tbody tr:not([style*="display: none"])');
        let csvContent = "Pet Name,Pet Type,Vaccine,Date Given,Next Due Date,Days Until Due,Veterinarian,Status\n";

        rows.forEach(row => {
            const cells = row.getElementsByTagName('td');
            if (cells.length > 1) {
                const rowData = [
                    cells[1].textContent.trim().replace(/\s+/g, ' '),
                    cells[2].textContent.trim().replace(/\s+/g, ' '),
                    cells[3].textContent.trim(),
                    cells[4].textContent.trim(),
                    cells[5].textContent.trim().replace(/\s+/g, ' '),
                    cells[6].textContent.trim().replace(/\s+/g, ' '),
                    cells[7].textContent.trim()
                ].map(field => `"${field.replace(/"/g, '""')}"`).join(',');
                csvContent += rowData + "\n";
            }
        });

        const blob = new Blob([csvContent], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', `vaccination_records_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Print report function
    function printReport() {
        const printWindow = window.open('', '_blank');
        const tableContent = document.getElementById('vaccinationTable').outerHTML;

        printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Vaccination Report - <?php echo htmlspecialchars($shelter_name); ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f8f9fa; font-weight: bold; }
                        tr:nth-child(even) { background-color: #f9f9f9; }
                        .checkbox-column { display: none; }
                        @media print { 
                            .no-print { display: none; }
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Vaccination Report</h1>
                    <p><strong>Shelter:</strong> <?php echo htmlspecialchars($shelter_name); ?></p>
                    <p><strong>Generated:</strong> ${new Date().toLocaleDateString()}</p>
                    ${tableContent}
                </body>
                </html>
            `);

        printWindow.document.close();
        printWindow.print();
    }

    // Form validation
    function initializeFormValidation() {
        const form = document.getElementById('vaccinationForm');
        if (!form) return;

        form.addEventListener('submit', function(e) {
            const petId = document.getElementById('pet_id').value;
            const vaccineName = document.getElementById('vaccine_name').value.trim();
            const vaccinationDate = document.getElementById('vaccination_date').value;

            if (!petId || !vaccineName || !vaccinationDate) {
                e.preventDefault();
                alert(
                    'Please fill in all required fields:\n• Pet selection\n• Vaccine name\n• Vaccination date'
                );
                return false;
            }

            // Check if vaccination date is not in the future
            const today = new Date().toISOString().split('T')[0];
            if (vaccinationDate > today) {
                e.preventDefault();
                alert('Vaccination date cannot be in the future.');
                return false;
            }

            // Show loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalHTML = submitBtn.innerHTML;
                submitBtn.innerHTML = '<div class="loading"></div> Adding...';
                submitBtn.disabled = true;

                // Restore button after 5 seconds if form doesn't submit
                setTimeout(() => {
                    if (submitBtn.disabled) {
                        submitBtn.innerHTML = originalHTML;
                        submitBtn.disabled = false;
                    }
                }, 5000);
            }

            return true;
        });
    }

    // Auto-clear form after successful submission
    <?php if (!empty($success_message)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            const form = document.getElementById('vaccinationForm');
            if (form) {
                form.reset();
                const today = new Date().toISOString().split('T')[0];
                const vaccinationDateInput = document.getElementById('vaccination_date');
                if (vaccinationDateInput) {
                    vaccinationDateInput.value = today;
                }
                updateQuickVaccines();

                // Scroll to table
                const tableSection = document.querySelector('.table-section');
                if (tableSection) {
                    tableSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        }, 100);
    });
    <?php endif; ?>

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchBox = document.getElementById('searchBox');
            if (searchBox) {
                searchBox.focus();
                searchBox.select();
            }
        }

        // Escape to close modals
        if (e.key === 'Escape') {
            closeDetailsModal();
            hideBulkActions();
        }
    });

    // Click outside modal to close
    document.getElementById('detailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDetailsModal();
        }
    });
    </script>
</body>

</html>