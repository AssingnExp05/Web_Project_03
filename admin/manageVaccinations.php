<?php
require_once '../config/db.php';
check_role('admin');
$page_title = 'Vaccination Tracker';

$success_message = '';
$error_message = '';

// Handle vaccination actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $vaccination_id = (int)$_POST['vaccination_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM vaccinations WHERE id = ?");
                $stmt->execute([$vaccination_id]);
                $success_message = "Vaccination record deleted successfully.";
            }
        } catch(PDOException $e) {
            $error_message = "Error performing action: " . $e->getMessage();
        }
    }
}

// Get vaccinations with filters
$filter_pet = isset($_GET['pet']) ? $_GET['pet'] : '';
$filter_shelter = isset($_GET['shelter']) ? $_GET['shelter'] : '';
$filter_overdue = isset($_GET['overdue']) ? $_GET['overdue'] : '';

$where_conditions = [];
$params = [];

if ($filter_pet) {
    $where_conditions[] = "p.id = ?";
    $params[] = $filter_pet;
}

if ($filter_shelter) {
    $where_conditions[] = "s.id = ?";
    $params[] = $filter_shelter;
}

if ($filter_overdue === '1') {
    $where_conditions[] = "v.next_due_date < CURDATE()";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Get vaccinations
    $sql = "SELECT v.*, p.name as pet_name, p.species, s.shelter_name 
            FROM vaccinations v 
            JOIN pets p ON v.pet_id = p.id 
            JOIN shelters sh ON p.shelter_id = sh.id 
            JOIN users s ON sh.user_id = s.id 
            $where_clause 
            ORDER BY v.next_due_date ASC, v.vaccination_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vaccinations = $stmt->fetchAll();
    
    // Get pets for filter
    $stmt = $pdo->query("SELECT p.id, p.name, s.shelter_name FROM pets p 
                        JOIN shelters sh ON p.shelter_id = sh.id 
                        JOIN users s ON sh.user_id = s.id 
                        WHERE p.status != 'adopted' 
                        ORDER BY s.shelter_name, p.name");
    $pets = $stmt->fetchAll();
    
    // Get shelters for filter
    $stmt = $pdo->query("SELECT s.id, s.shelter_name FROM shelters s 
                        JOIN users u ON s.user_id = u.id 
                        ORDER BY s.shelter_name");
    $shelters = $stmt->fetchAll();
    
    // Get overdue count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vaccinations v 
                        JOIN pets p ON v.pet_id = p.id 
                        WHERE v.next_due_date < CURDATE() AND p.status != 'adopted'");
    $overdue_count = $stmt->fetch()['count'];
    
} catch(PDOException $e) {
    $vaccinations = [];
    $pets = [];
    $shelters = [];
    $overdue_count = 0;
    $error_message = "Error fetching vaccination data: " . $e->getMessage();
}

include '../common/header.php';
include '../common/navbar_admin.php';
?>

<main class="main-content">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 class="page-title">Vaccination Tracker</h1>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <?php if ($overdue_count > 0): ?>
                    <span style="padding: 0.5rem 1rem; background: #fee2e2; color: #991b1b; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500;">
                        ⚠️ <?php echo $overdue_count; ?> Overdue
                    </span>
                <?php endif; ?>
                <span style="color: #6b7280;">Total: <?php echo count($vaccinations); ?> records</span>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-6">
            <div class="card-body">
                <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="pet" class="form-label">Pet</label>
                        <select id="pet" name="pet" class="form-input form-select" style="min-width: 200px;">
                            <option value="">All Pets</option>
                            <?php foreach ($pets as $pet): ?>
                                <option value="<?php echo $pet['id']; ?>" <?php echo $filter_pet == $pet['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pet['name'] . ' (' . $pet['shelter_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="shelter" class="form-label">Shelter</label>
                        <select id="shelter" name="shelter" class="form-input form-select">
                            <option value="">All Shelters</option>
                            <?php foreach ($shelters as $shelter): ?>
                                <option value="<?php echo $shelter['id']; ?>" <?php echo $filter_shelter == $shelter['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($shelter['shelter_name']); ?>
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
                    <a href="/admin/manageVaccinations.php" class="btn btn-secondary">Clear</a>
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
                                                    <?php echo ucfirst($vaccination['species']); ?> • 
                                                    <?php echo htmlspecialchars($vaccination['shelter_name']); ?>
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
                                        No vaccination records found matching your criteria.
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

<?php include '../common/footer.php'; ?>