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
            $error_message = "Please fill in all required fields for vaccination.";
        } else {
            // Verify pet belongs to this shelter
            $pet_exists = DBHelper::selectOne(
                "SELECT pet_id FROM pets WHERE pet_id = ? AND shelter_id = ?",
                [$pet_id, $shelter_id]
            );
            
            if (!$pet_exists) {
                $error_message = "Invalid pet selection.";
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
    } catch (Exception $e) {
        $error_message = "System error: " . $e->getMessage();
        error_log("Vaccination add error: " . $e->getMessage());
    }
}

// Handle Add Medical Record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medical_record'])) {
    try {
        $pet_id = (int)$_POST['medical_pet_id'];
        $record_type = Security::sanitize($_POST['record_type']);
        $record_date = $_POST['record_date'];
        $veterinarian_name = !empty($_POST['medical_veterinarian_name']) ? Security::sanitize($_POST['medical_veterinarian_name']) : null;
        $diagnosis = !empty($_POST['diagnosis']) ? Security::sanitize($_POST['diagnosis']) : null;
        $treatment = !empty($_POST['treatment']) ? Security::sanitize($_POST['treatment']) : null;
        $cost = !empty($_POST['cost']) ? (float)$_POST['cost'] : null;

        // Validate required fields
        if (empty($pet_id) || empty($record_type) || empty($record_date)) {
            $error_message = "Please fill in all required fields for medical record.";
        } else {
            // Verify pet belongs to this shelter
            $pet_exists = DBHelper::selectOne(
                "SELECT pet_id FROM pets WHERE pet_id = ? AND shelter_id = ?",
                [$pet_id, $shelter_id]
            );
            
            if (!$pet_exists) {
                $error_message = "Invalid pet selection for medical record.";
            } else {
                $insert_id = DBHelper::insert(
                    "INSERT INTO medical_records (pet_id, record_type, record_date, veterinarian_name, diagnosis, treatment, cost) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$pet_id, $record_type, $record_date, $veterinarian_name, $diagnosis, $treatment, $cost]
                );
                
                if ($insert_id) {
                    $success_message = "Medical record added successfully!";
                } else {
                    $error_message = "Error adding medical record. Please try again.";
                }
            }
        }
    } catch (Exception $e) {
        $error_message = "System error: " . $e->getMessage();
        error_log("Medical record add error: " . $e->getMessage());
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
                $success_message = "Vaccination record deleted successfully!";
            } else {
                $error_message = "Error deleting vaccination record.";
            }
        }
    } catch (Exception $e) {
        $error_message = "System error: " . $e->getMessage();
    }
}

// Handle Delete Medical Record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_medical_record'])) {
    try {
        $record_id = (int)$_POST['record_id'];
        
        // Verify medical record belongs to this shelter's pet
        $record_exists = DBHelper::selectOne(
            "SELECT m.record_id, p.pet_name 
             FROM medical_records m 
             INNER JOIN pets p ON m.pet_id = p.pet_id 
             WHERE m.record_id = ? AND p.shelter_id = ?",
            [$record_id, $shelter_id]
        );
        
        if (!$record_exists) {
            $error_message = "Invalid medical record or access denied.";
        } else {
            $affected_rows = DBHelper::execute(
                "DELETE FROM medical_records WHERE record_id = ?",
                [$record_id]
            );
            
            if ($affected_rows > 0) {
                $success_message = "Medical record deleted successfully!";
            } else {
                $error_message = "Error deleting medical record.";
            }
        }
    } catch (Exception $e) {
        $error_message = "System error: " . $e->getMessage();
    }
}

// Get all pets from this shelter with their categories and breeds
$pets = DBHelper::select(
    "SELECT p.pet_id, p.pet_name, pc.category_name, pb.breed_name 
     FROM pets p
     LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
     LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
     WHERE p.shelter_id = ? 
     ORDER BY p.pet_name",
    [$shelter_id]
);

// Get vaccination records with pet details
$vaccinations = DBHelper::select(
    "SELECT v.*, p.pet_name, pc.category_name,
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

// Get medical records with pet details
$medical_records = DBHelper::select(
    "SELECT m.*, p.pet_name, pc.category_name
     FROM medical_records m
     INNER JOIN pets p ON m.pet_id = p.pet_id
     LEFT JOIN pet_categories pc ON p.category_id = pc.category_id
     WHERE p.shelter_id = ?
     ORDER BY m.record_date DESC, m.created_at DESC",
    [$shelter_id]
);

// Calculate statistics
$stats = [
    'total_vaccinations' => count($vaccinations),
    'total_medical_records' => count($medical_records),
    'overdue' => 0,
    'due_this_week' => 0,
    'due_this_month' => 0,
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
    <title>Health & Vaccination Tracker - <?php echo htmlspecialchars($shelter_name); ?></title>
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
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

    .stat-card.vaccinations {
        --card-color: #42a5f5;
    }

    .stat-card.medical {
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

    .tabs-container {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .tabs {
        display: flex;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }

    .tab {
        flex: 1;
        padding: 20px;
        background: none;
        border: none;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .tab.active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }

    .tab:not(.active):hover {
        background: #e9ecef;
    }

    .tab-content {
        display: none;
        padding: 30px;
    }

    .tab-content.active {
        display: block;
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

    .btn-success {
        background: linear-gradient(135deg, #66bb6a, #4caf50);
        color: white;
    }

    .btn-info {
        background: linear-gradient(135deg, #29b6f6, #0288d1);
        color: white;
        padding: 6px 10px;
        font-size: 0.8rem;
    }

    .table-responsive {
        overflow-x: auto;
        background: white;
        border-radius: 10px;
        margin-top: 20px;
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
    }

    .table td {
        padding: 15px 12px;
        border-bottom: 1px solid #e9ecef;
        vertical-align: middle;
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

    .record-type-checkup {
        background: #e8f5e8;
        color: #2e7d32;
    }

    .record-type-surgery {
        background: #ffebee;
        color: #c62828;
    }

    .record-type-treatment {
        background: #fff3e0;
        color: #ef6c00;
    }

    .record-type-other {
        background: #f3e5f5;
        color: #7b1fa2;
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
        padding: 40px 20px;
        color: #6c757d;
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

        .tabs {
            flex-direction: column;
        }
    }
    </style>
</head>

<body>
    <?php 
    // Include navbar
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
                    <i class="fas fa-heartbeat"></i>
                    Health & Vaccination Tracker
                </h1>
                <p class="header-subtitle">Complete health management system for your shelter pets</p>
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
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo $stats['overdue']; ?></div>
                <div class="stat-label">Overdue Vaccinations</div>
            </div>

            <div class="stat-card due-week">
                <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                <div class="stat-number"><?php echo $stats['due_this_week']; ?></div>
                <div class="stat-label">Due This Week</div>
            </div>

            <div class="stat-card due-month">
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-number"><?php echo $stats['due_this_month']; ?></div>
                <div class="stat-label">Due This Month</div>
            </div>

            <div class="stat-card vaccinations">
                <div class="stat-icon"><i class="fas fa-syringe"></i></div>
                <div class="stat-number"><?php echo $stats['total_vaccinations']; ?></div>
                <div class="stat-label">Total Vaccinations</div>
            </div>

            <div class="stat-card medical">
                <div class="stat-icon"><i class="fas fa-stethoscope"></i></div>
                <div class="stat-number"><?php echo $stats['total_medical_records']; ?></div>
                <div class="stat-label">Medical Records</div>
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
            <!-- Forms Section -->
            <div class="tabs-container">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('vaccination')">
                        <i class="fas fa-syringe"></i>
                        Add Vaccination
                    </button>
                    <button class="tab" onclick="switchTab('medical')">
                        <i class="fas fa-stethoscope"></i>
                        Add Medical Record
                    </button>
                </div>

                <!-- Vaccination Form -->
                <div id="vaccination-tab" class="tab-content active">
                    <form method="POST" id="vaccinationForm">
                        <div class="form-group">
                            <label for="pet_id">
                                <i class="fas fa-paw"></i> Select Pet *
                            </label>
                            <select name="pet_id" id="pet_id" class="form-control" required>
                                <option value="">Choose a pet...</option>
                                <?php foreach ($pets as $pet): ?>
                                <option value="<?php echo $pet['pet_id']; ?>">
                                    <?php echo htmlspecialchars($pet['pet_name']); ?>
                                    <?php if ($pet['category_name']): ?>
                                    (<?php echo htmlspecialchars($pet['category_name']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="vaccine_name">
                                <i class="fas fa-prescription-bottle-alt"></i> Vaccine Name *
                            </label>
                            <input type="text" name="vaccine_name" id="vaccine_name" class="form-control"
                                placeholder="e.g., DHPP, Rabies, FVRCP" required maxlength="100">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="vaccination_date">
                                    <i class="fas fa-calendar"></i> Vaccination Date *
                                </label>
                                <input type="date" name="vaccination_date" id="vaccination_date" class="form-control"
                                    required max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="next_due_date">
                                    <i class="fas fa-calendar-plus"></i> Next Due Date
                                </label>
                                <input type="date" name="next_due_date" id="next_due_date" class="form-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="veterinarian_name">
                                <i class="fas fa-user-md"></i> Veterinarian Name
                            </label>
                            <input type="text" name="veterinarian_name" id="veterinarian_name" class="form-control"
                                placeholder="Dr. Smith" maxlength="100">
                        </div>

                        <div class="form-group">
                            <label for="notes">
                                <i class="fas fa-sticky-note"></i> Notes
                            </label>
                            <textarea name="notes" id="notes" class="form-control" rows="3"
                                placeholder="Additional notes about the vaccination..."></textarea>
                        </div>

                        <button type="submit" name="add_vaccination" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-syringe"></i> Add Vaccination Record
                        </button>
                    </form>
                </div>

                <!-- Medical Record Form -->
                <div id="medical-tab" class="tab-content">
                    <form method="POST" id="medicalForm">
                        <div class="form-group">
                            <label for="medical_pet_id">
                                <i class="fas fa-paw"></i> Select Pet *
                            </label>
                            <select name="medical_pet_id" id="medical_pet_id" class="form-control" required>
                                <option value="">Choose a pet...</option>
                                <?php foreach ($pets as $pet): ?>
                                <option value="<?php echo $pet['pet_id']; ?>">
                                    <?php echo htmlspecialchars($pet['pet_name']); ?>
                                    <?php if ($pet['category_name']): ?>
                                    (<?php echo htmlspecialchars($pet['category_name']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="record_type">
                                    <i class="fas fa-clipboard-list"></i> Record Type *
                                </label>
                                <select name="record_type" id="record_type" class="form-control" required>
                                    <option value="">Select type...</option>
                                    <option value="checkup">Regular Checkup</option>
                                    <option value="surgery">Surgery</option>
                                    <option value="treatment">Treatment</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="record_date">
                                    <i class="fas fa-calendar"></i> Record Date *
                                </label>
                                <input type="date" name="record_date" id="record_date" class="form-control" required
                                    max="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="medical_veterinarian_name">
                                <i class="fas fa-user-md"></i> Veterinarian Name
                            </label>
                            <input type="text" name="medical_veterinarian_name" id="medical_veterinarian_name"
                                class="form-control" placeholder="Dr. Smith" maxlength="100">
                        </div>

                        <div class="form-group">
                            <label for="diagnosis">
                                <i class="fas fa-diagnoses"></i> Diagnosis
                            </label>
                            <textarea name="diagnosis" id="diagnosis" class="form-control" rows="3"
                                placeholder="Medical diagnosis details..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="treatment">
                                <i class="fas fa-pills"></i> Treatment
                            </label>
                            <textarea name="treatment" id="treatment" class="form-control" rows="3"
                                placeholder="Treatment details and medications..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="cost">
                                <i class="fas fa-dollar-sign"></i> Cost
                            </label>
                            <input type="number" name="cost" id="cost" class="form-control" placeholder="0.00"
                                step="0.01" min="0">
                        </div>

                        <button type="submit" name="add_medical_record" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-stethoscope"></i> Add Medical Record
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
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

        <!-- Records Tables -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" onclick="switchRecordsTab('vaccinations')">
                    <i class="fas fa-syringe"></i>
                    Vaccination Records (<?php echo count($vaccinations); ?>)
                </button>
                <button class="tab" onclick="switchRecordsTab('medical')">
                    <i class="fas fa-stethoscope"></i>
                    Medical Records (<?php echo count($medical_records); ?>)
                </button>
            </div>

            <!-- Vaccination Records Table -->
            <div id="vaccinations-records" class="tab-content active">
                <?php if (!empty($vaccinations)): ?>
                <div class="table-responsive">
                    <table class="table" id="vaccinationTable">
                        <thead>
                            <tr>
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
                                        'no_due_date' => ['class' => 'status-current', 'text' => 'No Due Date', 'icon' => 'fas fa-question-circle']
                                    ];
                                    
                                    $current_status = $status_info[$vaccination['status_category']] ?? $status_info['current'];
                                    $pet_initial = strtoupper(substr($vaccination['pet_name'], 0, 1));
                                ?>
                            <tr>
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
                                                    echo strlen($notes) > 30 ? substr($notes, 0, 30) . '...' : $notes; 
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
                                        <button
                                            onclick="viewVaccinationDetails(<?php echo $vaccination['vaccination_id']; ?>)"
                                            class="btn btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirm('Are you sure you want to delete the <?php echo htmlspecialchars($vaccination['vaccine_name']); ?> vaccination record for <?php echo htmlspecialchars($vaccination['pet_name']); ?>?');">
                                            <input type="hidden" name="vaccination_id"
                                                value="<?php echo $vaccination['vaccination_id']; ?>">
                                            <button type="submit" name="delete_vaccination" class="btn btn-danger"
                                                title="Delete Record">
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
                    <i class="fas fa-syringe" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3>No Vaccination Records Yet</h3>
                    <p>Start by adding vaccination records for your pets using the form above.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Medical Records Table -->
            <div id="medical-records" class="tab-content">
                <?php if (!empty($medical_records)): ?>
                <div class="table-responsive">
                    <table class="table" id="medicalTable">
                        <thead>
                            <tr>
                                <th>Pet</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Veterinarian</th>
                                <th>Diagnosis</th>
                                <th>Treatment</th>
                                <th>Cost</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medical_records as $record): 
                                    $pet_initial = strtoupper(substr($record['pet_name'], 0, 1));
                                    $record_type_class = 'record-type-' . $record['record_type'];
                                ?>
                            <tr>
                                <td>
                                    <div class="pet-info">
                                        <div class="pet-avatar">
                                            <?php echo $pet_initial; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #333;">
                                                <?php echo htmlspecialchars($record['pet_name']); ?>
                                            </div>
                                            <div style="font-size: 0.85rem; color: #666;">
                                                <?php echo htmlspecialchars($record['category_name'] ?? 'Unknown'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $record_type_class; ?>">
                                        <?php 
                                                $type_icons = [
                                                    'checkup' => 'fas fa-check-circle',
                                                    'surgery' => 'fas fa-cut',
                                                    'treatment' => 'fas fa-pills',
                                                    'other' => 'fas fa-ellipsis-h'
                                                ];
                                                $icon = $type_icons[$record['record_type']] ?? 'fas fa-circle';
                                                ?>
                                        <i class="<?php echo $icon; ?>"></i>
                                        <?php echo ucfirst($record['record_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 500;">
                                        <?php echo date('M j, Y', strtotime($record['record_date'])); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #666;">
                                        <?php echo date('l', strtotime($record['record_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($record['veterinarian_name']): ?>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-user-md" style="color: #667eea;"></i>
                                        <span style="font-weight: 500;">
                                            <?php echo htmlspecialchars($record['veterinarian_name']); ?>
                                        </span>
                                    </div>
                                    <?php else: ?>
                                    <em style="color: #999;">
                                        <i class="fas fa-user-times"></i>
                                        Not specified
                                    </em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['diagnosis']): ?>
                                    <div style="max-width: 200px;">
                                        <?php 
                                                    $diagnosis = htmlspecialchars($record['diagnosis']);
                                                    echo strlen($diagnosis) > 50 ? substr($diagnosis, 0, 50) . '...' : $diagnosis; 
                                                    ?>
                                    </div>
                                    <?php else: ?>
                                    <em style="color: #999;">No diagnosis</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['treatment']): ?>
                                    <div style="max-width: 200px;">
                                        <?php 
                                                    $treatment = htmlspecialchars($record['treatment']);
                                                    echo strlen($treatment) > 50 ? substr($treatment, 0, 50) . '...' : $treatment; 
                                                    ?>
                                    </div>
                                    <?php else: ?>
                                    <em style="color: #999;">No treatment</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($record['cost']): ?>
                                    <div style="font-weight: 600; color: #2e7d32;">
                                        <i class="fas fa-dollar-sign"></i>
                                        <?php echo number_format($record['cost'], 2); ?>
                                    </div>
                                    <?php else: ?>
                                    <em style="color: #999;">-</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button onclick="viewMedicalDetails(<?php echo $record['record_id']; ?>)"
                                            class="btn btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" style="display: inline;"
                                            onsubmit="return confirm('Are you sure you want to delete this medical record for <?php echo htmlspecialchars($record['pet_name']); ?>?');">
                                            <input type="hidden" name="record_id"
                                                value="<?php echo $record['record_id']; ?>">
                                            <button type="submit" name="delete_medical_record" class="btn btn-danger"
                                                title="Delete Record">
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
                    <i class="fas fa-stethoscope" style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3>No Medical Records Yet</h3>
                    <p>Start by adding medical records for your pets using the form above.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Vaccination Details Modal -->
    <div id="vaccinationModal"
        style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center;">
        <div
            style="background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin: 0; color: #333; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-syringe" style="color: #667eea;"></i>
                    Vaccination Details
                </h3>
                <button onclick="closeVaccinationModal()"
                    style="background: none; border: none; font-size: 1.5rem; color: #999; cursor: pointer; padding: 5px;"
                    title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="vaccinationModalContent">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Medical Details Modal -->
    <div id="medicalModal"
        style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center;">
        <div
            style="background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin: 0; color: #333; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-stethoscope" style="color: #667eea;"></i>
                    Medical Record Details
                </h3>
                <button onclick="closeMedicalModal()"
                    style="background: none; border: none; font-size: 1.5rem; color: #999; cursor: pointer; padding: 5px;"
                    title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="medicalModalContent">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Set today's date as default
        const today = new Date().toISOString().split('T')[0];
        const vaccinationDate = document.getElementById('vaccination_date');
        const recordDate = document.getElementById('record_date');

        if (vaccinationDate) vaccinationDate.value = today;
        if (recordDate) recordDate.value = today;

        // Add event listeners
        if (vaccinationDate) {
            vaccinationDate.addEventListener('change', calculateNextDueDate);
        }
    });

    // Tab switching functions
    function switchTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });

        // Remove active class from all tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });

        // Show selected tab content
        document.getElementById(tabName + '-tab').classList.add('active');

        // Add active class to clicked tab
        event.target.classList.add('active');
    }

    function switchRecordsTab(tabName) {
        // Hide all record tab contents
        document.querySelectorAll('#vaccinations-records, #medical-records').forEach(content => {
            content.classList.remove('active');
        });

        // Remove active class from record tabs
        event.target.parentElement.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });

        // Show selected record tab
        document.getElementById(tabName + '-records').classList.add('active');

        // Add active class to clicked tab
        event.target.classList.add('active');
    }

    // Auto-calculate next due date (1 year later)
    function calculateNextDueDate() {
        const vaccinationDate = document.getElementById('vaccination_date').value;
        const nextDueDateInput = document.getElementById('next_due_date');

        if (vaccinationDate && nextDueDateInput) {
            const nextDue = new Date(vaccinationDate);
            nextDue.setFullYear(nextDue.getFullYear() + 1);
            nextDueDateInput.value = nextDue.toISOString().split('T')[0];
        }
    }

    // View vaccination details
    function viewVaccinationDetails(vaccinationId) {
        // Find the vaccination data from the table
        const row = document.querySelector(`input[name="vaccination_id"][value="${vaccinationId}"]`).closest('tr');
        const cells = row.getElementsByTagName('td');

        const petInfo = cells[0].textContent.trim();
        const vaccine = cells[1].textContent.trim();
        const dateGiven = cells[2].textContent.trim();
        const nextDue = cells[3].textContent.trim();
        const daysUntilDue = cells[4].textContent.trim();
        const veterinarian = cells[5].textContent.trim();
        const status = cells[6].textContent.trim();

        const content = `
                <div style="display: grid; gap: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-paw"></i> Pet
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
                                ${petInfo}
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-syringe"></i> Vaccine
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
                                ${vaccine}
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-calendar"></i> Date Given
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #66bb6a;">
                                ${dateGiven}
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-calendar-plus"></i> Next Due Date
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #ffa726;">
                                ${nextDue}
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-clock"></i> Days Until Due
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #ff6b6b;">
                                ${daysUntilDue}
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-user-md"></i> Veterinarian
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #42a5f5;">
                                ${veterinarian}
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-info-circle"></i> Status
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #ab47bc;">
                                ${status}
                            </div>
                        </div>
                    </div>
                </div>
            `;

        document.getElementById('vaccinationModalContent').innerHTML = content;
        document.getElementById('vaccinationModal').style.display = 'flex';
    }

    // View medical record details
    function viewMedicalDetails(recordId) {
        const row = document.querySelector(`input[name="record_id"][value="${recordId}"]`).closest('tr');
        const cells = row.getElementsByTagName('td');

        const petInfo = cells[0].textContent.trim();
        const type = cells[1].textContent.trim();
        const date = cells[2].textContent.trim();
        const veterinarian = cells[3].textContent.trim();
        const diagnosis = cells[4].textContent.trim();
        const treatment = cells[5].textContent.trim();
        const cost = cells[6].textContent.trim();

        const content = `
                <div style="display: grid; gap: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-paw"></i> Pet
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
                                ${petInfo}
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-clipboard-list"></i> Type
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #ab47bc;">
                                ${type}
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-calendar"></i> Date
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #66bb6a;">
                                ${date}
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-user-md"></i> Veterinarian
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #42a5f5;">
                                ${veterinarian}
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                                <i class="fas fa-dollar-sign"></i> Cost
                            </label>
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #4caf50;">
                                ${cost}
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                            <i class="fas fa-diagnoses"></i> Diagnosis
                        </label>
                        <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #ff9800; min-height: 60px;">
                            ${diagnosis}
                        </div>
                    </div>
                    
                    <div>
                        <label style="font-weight: 600; color: #555; display: block; margin-bottom: 5px;">
                            <i class="fas fa-pills"></i> Treatment
                        </label>
                        <div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #9c27b0; min-height: 60px;">
                            ${treatment}
                        </div>
                    </div>
                </div>
            `;

        document.getElementById('medicalModalContent').innerHTML = content;
        document.getElementById('medicalModal').style.display = 'flex';
    }

    // Close modal functions
    function closeVaccinationModal() {
        document.getElementById('vaccinationModal').style.display = 'none';
    }

    function closeMedicalModal() {
        document.getElementById('medicalModal').style.display = 'none';
    }

    // Click outside modal to close
    document.getElementById('vaccinationModal').addEventListener('click', function(e) {
        if (e.target === this) closeVaccinationModal();
    });

    document.getElementById('medicalModal').addEventListener('click', function(e) {
        if (e.target === this) closeMedicalModal();
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeVaccinationModal();
            closeMedicalModal();
        }
    });

    // Form validation
    document.getElementById('vaccinationForm').addEventListener('submit', function(e) {
        const petId = document.getElementById('pet_id').value;
        const vaccineName = document.getElementById('vaccine_name').value.trim();
        const vaccinationDate = document.getElementById('vaccination_date').value;

        if (!petId || !vaccineName || !vaccinationDate) {
            e.preventDefault();
            alert('Please fill in all required fields for vaccination record.');
            return false;
        }

        const today = new Date().toISOString().split('T')[0];
        if (vaccinationDate > today) {
            e.preventDefault();
            alert('Vaccination date cannot be in the future.');
            return false;
        }
    });

    document.getElementById('medicalForm').addEventListener('submit', function(e) {
        const petId = document.getElementById('medical_pet_id').value;
        const recordType = document.getElementById('record_type').value;
        const recordDate = document.getElementById('record_date').value;

        if (!petId || !recordType || !recordDate) {
            e.preventDefault();
            alert('Please fill in all required fields for medical record.');
            return false;
        }

        const today = new Date().toISOString().split('T')[0];
        if (recordDate > today) {
            e.preventDefault();
            alert('Record date cannot be in the future.');
            return false;
        }
    });

    // Auto-clear forms after successful submission
    <?php if (!empty($success_message)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            // Clear forms
            document.getElementById('vaccinationForm').reset();
            document.getElementById('medicalForm').reset();

            // Reset dates
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('vaccination_date').value = today;
            document.getElementById('record_date').value = today;

            // Scroll to records
            document.querySelector('.tabs-container:last-child').scrollIntoView({
                behavior: 'smooth'
            });
        }, 100);
    });
    <?php endif; ?>
    </script>
</body>

</html>