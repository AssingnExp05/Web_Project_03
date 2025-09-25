<?php
require_once '../config/db.php';
check_role('shelter');
$page_title = 'Vaccination Tracker';

$success_message = '';
$error_message = '';

// Get shelter info
try {
    $stmt = $pdo->prepare("SELECT * FROM shelters WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $shelter = $stmt->fetch();
    
    if (!$shelter) {
        redirect('../auth/login.php');
    }
} catch(PDOException $e) {
    redirect('../auth/login.php');
}

// Handle vaccination actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $pet_id = (int)$_POST['pet_id'];
            $vaccine_name = sanitize_input($_POST['vaccine_name']);
            $vaccination_date = sanitize_input($_POST['vaccination_date']);
            $next_due_date = sanitize_input($_POST['next_due_date']);
            $veterinarian = sanitize_input($_POST['veterinarian']);
            $notes = sanitize_input($_POST['notes']);
            
            if (empty($pet_id) || empty($vaccine_name) || empty($vaccination_date)) {
                $error_message = "Please fill in all required fields.";
            } else {
                try {
                    // Verify pet belongs to this shelter
                    $stmt = $pdo->prepare("SELECT id FROM pets WHERE id = ? AND shelter_id = ?");
                    $stmt->execute([$pet_id, $shelter['id']]);
                    if (!$stmt->fetch()) {
                        $error_message = "Pet not found or access denied.";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO vaccinations (pet_id, vaccine_name, vaccination_date, next_due_date, veterinarian, notes) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$pet_id, $vaccine_name, $vaccination_date, $next_due_date ?: null, $veterinarian, $notes]);
                        $success_message = "Vaccination record added successfully.";
                    }
                } catch(PDOException $e) {
                    $error_message = "Error adding vaccination record: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $vaccination_id = (int)$_POST['vaccination_id'];
            
            try {
                // Verify vaccination belongs to this shelter
                $stmt = $pdo->prepare("SELECT v.id FROM vaccinations v 
                                      JOIN pets p ON v.pet_id = p.id 
                                      WHERE v.id = ? AND p.shelter_id = ?");
                $stmt->execute([$vaccination_id, $shelter['id']]);
                if (!$stmt->fetch()) {
                    $error_message = "Vaccination record not found or access denied.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM vaccinations WHERE id = ?");
                    $stmt->execute([$vaccination_id]);
                    $success_message = "Vaccination record deleted successfully.";
                }
            } catch(PDOException $e) {
                $error_message = "Error deleting vaccination record: " . $e->getMessage();
            }
        }
    }
}

// Get vaccinations with filters
$filter_pet = isset($_GET['pet_id']) ? (int)$_GET['pet_id'] : 0;
$filter_overdue = isset($_GET['overdue']) ? $_GET['overdue'] : '';

$where_conditions = ["p.shelter_id = ?"];
$params = [$shelter['id']];

if ($filter_pet) {
    $where_conditions[] = "p.id = ?";
    $params[] = $filter_pet;
}

if ($filter_overdue === '1') {
    $where_conditions[] = "v.next_due_date < CURDATE()";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

try {
    // Get vaccinations
    $sql = "SELECT v.*, p.name as pet_name, p.species 
            FROM vaccinations v 
            JOIN pets p ON v.pet_id = p.id 
            $where_clause 
            ORDER BY v.next_due_date ASC, v.vaccination_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vaccinations = $stmt->fetchAll();
    
    // Get pets for filter and add form
    $stmt = $pdo->prepare("SELECT id, name, species FROM pets WHERE shelter_id = ? AND status != 'adopted' ORDER BY name");
    $stmt->execute([$shelter['id']]);
    $pets = $stmt->fetchAll();
    
    // Get overdue count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vaccinations v 
                          JOIN pets p ON v.pet_id = p.id 
                          WHERE v.next_due_date < CURDATE() AND p.shelter_id = ?");
    $stmt->execute([$shelter['id']]);
    $overdue_count = $stmt->fetch()['count'];
    
} catch(PDOException $e) {
    $vaccinations = [];
    $pets = [];
    $overdue_count = 0;
    $error_message = "Error fetching vaccination data: " . $e->getMessage();
}

include '../common/header.php';
include '../common/navbar_shelter.php';
?>

<main class="main-content">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 class="page-title">Vaccination Tracker</h1>
                <p style="color: #6b7280;">Manage vaccination records for your pets</p>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <?php if ($overdue_count > 0): ?>
                    <span style="padding: 0.5rem 1rem; background: #fee2e2; color: #991b1b; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500;">
                        ⚠️ <?php echo $overdue_count; ?> Overdue
                    </span>
                <?php endif; ?>
                <button onclick="toggleAddForm()" class="btn">➕ Add Vaccination</button>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Add Vaccination Form -->
        <div id="addVaccinationForm" class="card mb-6" style="display: none;">
            <div class="card-header">
                <h2 style="font-size: 1.25rem; color: #1f2937;">Add Vaccination Record</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="grid grid-cols-2">
                        <div class="form-group">
                            <label for="pet_id" class="form-label">Pet *</label>
                            <select id="pet_id" name="pet_id" class="form-input form-select" required>
                                <option value="">Select a pet</option>
                                <?php foreach ($pets as $pet): ?>
                                    <option value="<?php echo $pet['id']; ?>">
                                        <?php echo htmlspecialchars($pet['name']) . ' (' . ucfirst($pet['species']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="vaccine_name" class="form-label">Vaccine Name *</label>
                            <input type="text" id="vaccine_name" name="vaccine_name" class="form-input" required
                                   placeholder="e.g., Rabies, DHPP, FVRCP">
                        </div>
                    </div>

                    <div class="grid grid-cols-2">
                        <div class="form-group">
                            <label for="vaccination_date" class="form-label">Vaccination Date *</label>
                            <input type="date" id="vaccination_date" name="vaccination_date" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label for="next_due_date" class="form-label">Next Due Date</label>
                            <input type="date" id="next_due_date" name="next_due_date" class="form-input">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="veterinarian" class="form-label">Veterinarian</label>
                        <input type="text" id="veterinarian" name="veterinarian" class="form-input"
                               placeholder="Veterinarian name or clinic">
                    </div>

                    <div class="form-group">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" class="form-input form-textarea"
                                  placeholder="Any additional notes about the vaccination..."></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: end;">
                        <button type="button" onclick="toggleAddForm()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn">Add Vaccination</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-6">
            <div class="card-body">
                <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="pet_id" class="form-label">Pet</label>
                        <select id="pet_id" name="pet_id" class="form-input form-select" style="min-width: 200px;">
                            <option value="">All Pets</option>
                            <?php foreach ($pets as $pet): ?>
                                <option value="<?php echo $pet['id']; ?>" <?php echo $filter_pet == $pet['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pet['name']) . ' (' . ucfirst($pet['species']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="overdue" class="form-label">Status</label>
                        <select id="overdue" name="overdue" class="form-input form-select">
                            <option value="">All Records</option>
                            <option value="1" <?php echo $filter_overdue === '1' ? 'selected' : ''; ?>>Overdue Only</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Filter</button>
                    <a href="/shelter/vaccinationTracker.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>
        </div>

        <!-- Vaccinations Table -->
        <div class="card">
            <div class="card-body" style="padding: 0;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <tr>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;">Pet</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;">Vaccine</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;">Last Vaccination</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;">Next Due</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;">Veterinarian</th>
                                <th style="padding: 1rem; text-align: center; font-weight: 600; color: #374151;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($vaccinations)): ?>
                                <?php foreach ($vaccinations as $vaccination): ?>
                                    <?php 
                                    $is_overdue = $vaccination['next_due_date'] && strtotime($vaccination['next_due_date']) < time();
                                    $is_due_soon = $vaccination['next_due_date'] && strtotime($vaccination['next_due_date']) < strtotime('+30 days');
                                    ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0; <?php echo $is_overdue ? 'background: #fef2f2;' : ($is_due_soon ? 'background: #fffbeb;' : ''); ?>">
                                        <td style="padding: 1rem;">
                                            <div>
                                                <div style="font-weight: 500; color: #1f2937;">
                                                    <?php echo htmlspecialchars($vaccination['pet_name']); ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: #6b7280;">
                                                    <?php echo ucfirst($vaccination['species']); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <div style="font-weight: 500; color: #1f2937;">
                                                <?php echo htmlspecialchars($vaccination['vaccine_name']); ?>
                                            </div>
                                            <?php if ($vaccination['notes']): ?>
                                                <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                                                    <?php echo htmlspecialchars(substr($vaccination['notes'], 0, 50)) . (strlen($vaccination['notes']) > 50 ? '...' : ''); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem; color: #6b7280;">
                                            <?php echo date('M j, Y', strtotime($vaccination['vaccination_date'])); ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <?php if ($vaccination['next_due_date']): ?>
                                                <div style="color: <?php echo $is_overdue ? '#dc2626' : ($is_due_soon ? '#f59e0b' : '#6b7280'); ?>; font-weight: <?php echo $is_overdue || $is_due_soon ? '500' : 'normal'; ?>;">
                                                    <?php echo date('M j, Y', strtotime($vaccination['next_due_date'])); ?>
                                                    <?php if ($is_overdue): ?>
                                                        <span style="font-size: 0.75rem; display: block;">OVERDUE</span>
                                                    <?php elseif ($is_due_soon): ?>
                                                        <span style="font-size: 0.75rem; display: block;">DUE SOON</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;">
                                            <?php echo $vaccination['veterinarian'] ? htmlspecialchars($vaccination['veterinarian']) : 'Not specified'; ?>
                                        </td>
                                        <td style="padding: 1rem; text-align: center;">
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this vaccination record?')">
                                                <input type="hidden" name="vaccination_id" value="<?php echo $vaccination['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #dc2626; color: white; border: none; border-radius: 0.25rem; cursor: pointer;">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="padding: 3rem; text-align: center; color: #6b7280;">
                                        No vaccination records found. <button onclick="toggleAddForm()" style="color: #3b82f6; background: none; border: none; text-decoration: underline; cursor: pointer;">Add the first one</button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div style="margin-top: 1rem; display: flex; gap: 2rem; justify-content: center; font-size: 0.875rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 1rem; height: 1rem; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 0.25rem;"></div>
                <span style="color: #6b7280;">Overdue</span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 1rem; height: 1rem; background: #fffbeb; border: 1px solid #fcd34d; border-radius: 0.25rem;"></div>
                <span style="color: #6b7280;">Due Soon (30 days)</span>
            </div>
        </div>
    </div>
</main>

<script>
function toggleAddForm() {
    const form = document.getElementById('addVaccinationForm');
    if (form.style.display === 'none') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth' });
    } else {
        form.style.display = 'none';
    }
}

// Set default vaccination date to today
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('vaccination_date').value = today;
});
</script>

<?php include '../common/footer.php'; ?>