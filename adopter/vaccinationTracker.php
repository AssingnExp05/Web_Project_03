<?php
session_start();

// Check if user is logged in and is an adopter
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'adopter') {
    header("Location: ../auth/login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pet_adoption_care_guide";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Get adopter ID from session
$adopter_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Get selected pet ID from URL
$selected_pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;

// Fetch all adopted pets for this adopter
$adopted_pets_query = "SELECT DISTINCT p.pet_id, p.pet_name, p.primary_image, p.age, p.gender,
                      pc.category_name, pb.breed_name, ad.adoption_date
                      FROM pets p 
                      INNER JOIN adoptions ad ON p.pet_id = ad.pet_id 
                      INNER JOIN pet_categories pc ON p.category_id = pc.category_id
                      LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
                      WHERE ad.adopter_id = ? 
                      ORDER BY ad.adoption_date DESC";

$adopted_pets = [];
if ($stmt = $conn->prepare($adopted_pets_query)) {
    $stmt->bind_param("i", $adopter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $adopted_pets[] = $row;
    }
    $stmt->close();
}

// Initialize variables
$selected_pet = null;
$vaccinations = [];
$medical_records = [];

// If a pet is selected, fetch its details
if ($selected_pet_id > 0) {
    // Verify that this pet belongs to the adopter
    $verify_query = "SELECT p.*, pc.category_name, pb.breed_name, ad.adoption_date
                     FROM pets p 
                     INNER JOIN adoptions ad ON p.pet_id = ad.pet_id 
                     INNER JOIN pet_categories pc ON p.category_id = pc.category_id
                     LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
                     WHERE p.pet_id = ? AND ad.adopter_id = ?";
    
    if ($stmt = $conn->prepare($verify_query)) {
        $stmt->bind_param("ii", $selected_pet_id, $adopter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_pet = $result->fetch_assoc();
        $stmt->close();
    }
    
    // If pet is verified, fetch vaccination records
    if ($selected_pet) {
        $vacc_query = "SELECT * FROM vaccinations 
                       WHERE pet_id = ? 
                       ORDER BY vaccination_date DESC";
        
        if ($stmt = $conn->prepare($vacc_query)) {
            $stmt->bind_param("i", $selected_pet_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $vaccinations[] = $row;
            }
            $stmt->close();
        }
        
        // Also fetch medical records
        $medical_query = "SELECT * FROM medical_records 
                          WHERE pet_id = ? 
                          ORDER BY record_date DESC 
                          LIMIT 5";
        
        if ($stmt = $conn->prepare($medical_query)) {
            $stmt->bind_param("i", $selected_pet_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $medical_records[] = $row;
            }
            $stmt->close();
        }
    }
}

// Function to calculate vaccination status
function getVaccinationStatus($next_due_date) {
    if (empty($next_due_date)) {
        return ['status' => 'unknown', 'text' => 'Not Scheduled', 'days' => null];
    }
    
    $due_timestamp = strtotime($next_due_date);
    $current_timestamp = time();
    $days_diff = floor(($due_timestamp - $current_timestamp) / (60 * 60 * 24));
    
    if ($days_diff < 0) {
        return ['status' => 'overdue', 'text' => 'Overdue by ' . abs($days_diff) . ' days', 'days' => $days_diff];
    } elseif ($days_diff == 0) {
        return ['status' => 'due-today', 'text' => 'Due Today', 'days' => $days_diff];
    } elseif ($days_diff <= 7) {
        return ['status' => 'due-soon', 'text' => 'Due in ' . $days_diff . ' days', 'days' => $days_diff];
    } elseif ($days_diff <= 30) {
        return ['status' => 'upcoming', 'text' => 'Due in ' . $days_diff . ' days', 'days' => $days_diff];
    } else {
        return ['status' => 'current', 'text' => 'Up to Date', 'days' => $days_diff];
    }
}

// Get upcoming vaccinations across all pets
$upcoming_vaccinations = [];
if (!empty($adopted_pets)) {
    $pet_ids = array_column($adopted_pets, 'pet_id');
    if (!empty($pet_ids)) {
        $placeholders = str_repeat('?,', count($pet_ids) - 1) . '?';
        
        $upcoming_query = "SELECT v.*, p.pet_name, p.primary_image 
                           FROM vaccinations v
                           INNER JOIN pets p ON v.pet_id = p.pet_id
                           WHERE v.pet_id IN ($placeholders) 
                           AND v.next_due_date IS NOT NULL 
                           AND v.next_due_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
                           ORDER BY v.next_due_date ASC";
        
        if ($stmt = $conn->prepare($upcoming_query)) {
            $types = str_repeat('i', count($pet_ids));
            $stmt->bind_param($types, ...$pet_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $upcoming_vaccinations[] = $row;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Tracker - Pet Adoption Care Guide</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f7fa;
        min-height: 100vh;
        padding-top: 70px;
    }

    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 40px 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .page-header h1 {
        font-size: 2.5rem;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .page-header p {
        font-size: 1.1rem;
        opacity: 0.9;
    }

    .content-wrapper {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 25px;
        margin-bottom: 30px;
    }

    /* Pet Selection Sidebar */
    .pets-sidebar {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        height: fit-content;
        position: sticky;
        top: 90px;
    }

    .sidebar-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .sidebar-header h3 {
        color: #2c3e50;
        font-size: 1.3rem;
    }

    .pet-list {
        max-height: 600px;
        overflow-y: auto;
    }

    .pet-item {
        display: flex;
        align-items: center;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .pet-item:hover {
        background: #f8f9fa;
        transform: translateX(5px);
    }

    .pet-item.active {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border-color: #667eea;
    }

    .pet-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 15px;
        border: 3px solid #e0e0e0;
        flex-shrink: 0;
    }

    .pet-avatar-default {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        margin-right: 15px;
        border: 3px solid #e0e0e0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
    }

    .pet-item.active .pet-avatar,
    .pet-item.active .pet-avatar-default {
        border-color: #667eea;
    }

    .pet-item-info h4 {
        color: #2c3e50;
        margin-bottom: 5px;
        font-size: 1rem;
    }

    .pet-item-info span {
        color: #6c757d;
        font-size: 0.85rem;
    }

    /* Main Content */
    .main-content {
        display: flex;
        flex-direction: column;
        gap: 25px;
    }

    /* Upcoming Vaccinations Alert */
    .upcoming-alert {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        border-left: 4px solid #ffc107;
    }

    .upcoming-alert h3 {
        color: #2c3e50;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .upcoming-list {
        display: grid;
        gap: 10px;
    }

    .upcoming-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    /* Vaccination Records */
    .vaccination-section {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .section-title {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .section-title h2 {
        color: #2c3e50;
        font-size: 1.8rem;
    }

    .section-title i {
        color: #667eea;
        font-size: 1.5rem;
    }

    /* Pet Info Card */
    .pet-info-card {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .pet-info-image {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #667eea;
    }

    .pet-info-image-default {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 4px solid #667eea;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
    }

    .pet-info-details h3 {
        color: #2c3e50;
        margin-bottom: 8px;
    }

    .pet-info-details p {
        color: #6c757d;
        margin: 5px 0;
        font-size: 0.95rem;
    }

    /* Vaccination Table */
    .vaccination-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .vaccination-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #2c3e50;
        border-bottom: 2px solid #e0e0e0;
    }

    .vaccination-table td {
        padding: 15px;
        border-bottom: 1px solid #e0e0e0;
    }

    .vaccination-table tr:hover {
        background: #f8f9fa;
    }

    /* Status Badges */
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-block;
    }

    .status-overdue {
        background: #dc3545;
        color: white;
    }

    .status-due-today {
        background: #fd7e14;
        color: white;
    }

    .status-due-soon {
        background: #ffc107;
        color: #212529;
    }

    .status-upcoming {
        background: #17a2b8;
        color: white;
    }

    .status-current {
        background: #28a745;
        color: white;
    }

    .status-unknown {
        background: #6c757d;
        color: white;
    }

    /* Medical Records Section */
    .medical-records {
        margin-top: 30px;
        padding-top: 30px;
        border-top: 2px solid #f0f0f0;
    }

    .medical-records h3 {
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .medical-card {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 10px;
        border-left: 4px solid #667eea;
    }

    .medical-card-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .medical-card-header strong {
        color: #2c3e50;
    }

    .medical-card-header span {
        color: #6c757d;
        font-size: 0.9rem;
    }

    /* Empty States */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        color: #dee2e6;
        display: block;
    }

    .empty-state h3 {
        color: #495057;
        margin-bottom: 10px;
    }

    /* Buttons */
    .btn {
        padding: 10px 20px;
        border-radius: 25px;
        border: none;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .content-wrapper {
            grid-template-columns: 250px 1fr;
        }
    }

    @media (max-width: 768px) {
        .content-wrapper {
            grid-template-columns: 1fr;
        }

        .pets-sidebar {
            position: static;
            margin-bottom: 20px;
        }

        .pet-list {
            max-height: 300px;
        }

        .page-header h1 {
            font-size: 2rem;
        }

        .vaccination-table {
            font-size: 0.9rem;
        }

        .vaccination-table th,
        .vaccination-table td {
            padding: 10px;
        }
    }

    @media (max-width: 480px) {
        .container {
            padding: 10px;
        }

        .vaccination-table {
            display: block;
            overflow-x: auto;
        }

        .pet-info-card {
            flex-direction: column;
            text-align: center;
        }

        .upcoming-item {
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }

        .page-header h1 {
            font-size: 1.5rem;
            flex-direction: column;
            text-align: center;
        }
    }

    /* Loading Animation */
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(0, 0, 0, 0.1);
        border-radius: 50%;
        border-top-color: #667eea;
        animation: spin 1s ease-in-out infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Scrollbar Styling */
    .pet-list::-webkit-scrollbar {
        width: 6px;
    }

    .pet-list::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .pet-list::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 10px;
    }

    .pet-list::-webkit-scrollbar-thumb:hover {
        background: #764ba2;
    }
    </style>
</head>

<body>
    <?php 
    if (file_exists('../common/navbar_adopter.php')) {
        include '../common/navbar_adopter.php'; 
    }
    ?>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-syringe"></i> Vaccination Tracker</h1>
            <p>Monitor your pets' vaccination schedules and medical records</p>
        </div>

        <?php if (empty($adopted_pets)): ?>
        <div class="vaccination-section">
            <div class="empty-state">
                <i class="fas fa-heart-broken"></i>
                <h3>No Adopted Pets Yet</h3>
                <p>You haven't adopted any pets yet. Start your journey by browsing available pets.</p>
                <a href="browsePets.php" class="btn btn-primary">Browse Available Pets</a>
            </div>
        </div>
        <?php else: ?>
        <!-- Show upcoming vaccinations if any -->
        <?php if (!empty($upcoming_vaccinations)): ?>
        <div class="upcoming-alert">
            <h3><i class="fas fa-exclamation-triangle"></i> Upcoming Vaccinations</h3>
            <div class="upcoming-list">
                <?php foreach ($upcoming_vaccinations as $upcoming): ?>
                <?php $status = getVaccinationStatus($upcoming['next_due_date']); ?>
                <div class="upcoming-item">
                    <div>
                        <strong><?php echo htmlspecialchars($upcoming['pet_name']); ?></strong> -
                        <?php echo htmlspecialchars($upcoming['vaccine_name']); ?>
                    </div>
                    <span class="status-badge status-<?php echo $status['status']; ?>">
                        <?php echo $status['text']; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="content-wrapper">
            <!-- Pet Selection Sidebar -->
            <div class="pets-sidebar">
                <div class="sidebar-header">
                    <h3>My Adopted Pets</h3>
                </div>
                <div class="pet-list">
                    <?php foreach ($adopted_pets as $pet): ?>
                    <div class="pet-item <?php echo ($selected_pet_id == $pet['pet_id']) ? 'active' : ''; ?>"
                        onclick="selectPet(<?php echo $pet['pet_id']; ?>)">
                        <?php if (!empty($pet['primary_image']) && file_exists('../uploads/' . $pet['primary_image'])): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                            alt="<?php echo htmlspecialchars($pet['pet_name']); ?>" class="pet-avatar">
                        <?php else: ?>
                        <div class="pet-avatar-default">
                            <i class="fas fa-paw"></i>
                        </div>
                        <?php endif; ?>
                        <div class="pet-item-info">
                            <h4><?php echo htmlspecialchars($pet['pet_name']); ?></h4>
                            <span><?php echo htmlspecialchars($pet['category_name']); ?> •
                                <?php echo htmlspecialchars($pet['breed_name'] ?: 'Mixed'); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="main-content">
                <div class="vaccination-section">
                    <?php if (!$selected_pet_id): ?>
                    <div class="empty-state">
                        <i class="fas fa-hand-pointer"></i>
                        <h3>Select a Pet</h3>
                        <p>Choose a pet from the sidebar to view their vaccination records and medical history.</p>
                    </div>
                    <?php elseif (!$selected_pet): ?>
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <h3>Invalid Selection</h3>
                        <p>Please select a valid pet from your adopted pets list.</p>
                    </div>
                    <?php else: ?>
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-shield-alt"></i>
                            <h2>Vaccination Records</h2>
                        </div>
                    </div>

                    <!-- Pet Info Card -->
                    <div class="pet-info-card">
                        <?php if (!empty($selected_pet['primary_image']) && file_exists('../uploads/' . $selected_pet['primary_image'])): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($selected_pet['primary_image']); ?>"
                            alt="<?php echo htmlspecialchars($selected_pet['pet_name']); ?>" class="pet-info-image">
                        <?php else: ?>
                        <div class="pet-info-image-default">
                            <i class="fas fa-paw"></i>
                        </div>
                        <?php endif; ?>
                        <div class="pet-info-details">
                            <h3><?php echo htmlspecialchars($selected_pet['pet_name']); ?></h3>
                            <p><i class="fas fa-paw"></i>
                                <?php echo htmlspecialchars($selected_pet['category_name']); ?> -
                                <?php echo htmlspecialchars($selected_pet['breed_name'] ?: 'Mixed Breed'); ?></p>
                            <p><i class="fas fa-venus-mars"></i> <?php echo ucfirst($selected_pet['gender']); ?> • <i
                                    class="fas fa-birthday-cake"></i> <?php echo $selected_pet['age']; ?> years old</p>
                            <p><i class="fas fa-heart"></i> Adopted on
                                <?php echo date('F j, Y', strtotime($selected_pet['adoption_date'])); ?></p>
                        </div>
                    </div>

                    <!-- Vaccination Records Table -->
                    <?php if (!empty($vaccinations)): ?>
                    <div style="overflow-x: auto;">
                        <table class="vaccination-table">
                            <thead>
                                <tr>
                                    <th>Vaccine Name</th>
                                    <th>Date Given</th>
                                    <th>Next Due Date</th>
                                    <th>Veterinarian</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vaccinations as $vacc): ?>
                                <?php $status = getVaccinationStatus($vacc['next_due_date']); ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($vacc['vaccine_name']); ?></strong></td>
                                    <td><?php echo date('M j, Y', strtotime($vacc['vaccination_date'])); ?></td>
                                    <td>
                                        <?php if (!empty($vacc['next_due_date'])): ?>
                                        <?php echo date('M j, Y', strtotime($vacc['next_due_date'])); ?>
                                        <?php else: ?>
                                        <span style="color: #6c757d;">Not scheduled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($vacc['veterinarian_name'] ?: 'Not specified'); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $status['status']; ?>">
                                            <?php echo $status['text']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($vacc['notes'] ?: '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-syringe"></i>
                        <h3>No Vaccination Records</h3>
                        <p>No vaccination records found for <?php echo htmlspecialchars($selected_pet['pet_name']); ?>.
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- Medical Records Section -->
                    <?php if (!empty($medical_records)): ?>
                    <div class="medical-records">
                        <h3><i class="fas fa-notes-medical"></i> Recent Medical Records</h3>
                        <?php foreach ($medical_records as $record): ?>
                        <div class="medical-card">
                            <div class="medical-card-header">
                                <strong><?php echo ucfirst($record['record_type']); ?></strong>
                                <span><?php echo date('M j, Y', strtotime($record['record_date'])); ?></span>
                            </div>
                            <?php if ($record['diagnosis']): ?>
                            <p><strong>Diagnosis:</strong> <?php echo htmlspecialchars($record['diagnosis']); ?></p>
                            <?php endif; ?>
                            <?php if ($record['treatment']): ?>
                            <p><strong>Treatment:</strong> <?php echo htmlspecialchars($record['treatment']); ?></p>
                            <?php endif; ?>
                            <?php if ($record['veterinarian_name']): ?>
                            <p><strong>Veterinarian:</strong>
                                <?php echo htmlspecialchars($record['veterinarian_name']); ?></p>
                            <?php endif; ?>
                            <?php if ($record['cost'] > 0): ?>
                            <p><strong>Cost:</strong> $<?php echo number_format($record['cost'], 2); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function selectPet(petId) {
        window.location.href = '?pet_id=' + petId;
    }

    // Add smooth scroll behavior
    document.addEventListener('DOMContentLoaded', function() {
        // Highlight overdue vaccinations
        const overdueElements = document.querySelectorAll('.status-overdue, .status-due-today');
        overdueElements.forEach(element => {
            const row = element.closest('tr');
            if (row) {
                row.style.backgroundColor = '#fff5f5';
            }
        });

        // Add hover effects
        const petItems = document.querySelectorAll('.pet-item');
        petItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                if (!this.classList.contains('active')) {
                    this.style.backgroundColor = '#f8f9fa';
                }
            });

            item.addEventListener('mouseleave', function() {
                if (!this.classList.contains('active')) {
                    this.style.backgroundColor = 'transparent';
                }
            });
        });

        // Auto-scroll to active pet in sidebar
        const activePet = document.querySelector('.pet-item.active');
        if (activePet) {
            activePet.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    });
    </script>
</body>

</html>

<?php
// Close database connection
$conn->close();
?>