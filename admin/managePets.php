<?php
require_once '../config/db.php';
check_role('admin');
$page_title = 'Manage Pets';

$success_message = '';
$error_message = '';

// Handle pet actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $pet_id = (int)$_POST['pet_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'approve':
                    $stmt = $pdo->prepare("UPDATE pets SET status = 'available' WHERE id = ?");
                    $stmt->execute([$pet_id]);
                    $success_message = "Pet approved and made available for adoption.";
                    break;
                    
                case 'medical_hold':
                    $stmt = $pdo->prepare("UPDATE pets SET status = 'medical_hold' WHERE id = ?");
                    $stmt->execute([$pet_id]);
                    $success_message = "Pet placed on medical hold.";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM pets WHERE id = ?");
                    $stmt->execute([$pet_id]);
                    $success_message = "Pet record deleted successfully.";
                    break;
            }
        } catch(PDOException $e) {
            $error_message = "Error performing action: " . $e->getMessage();
        }
    }
}

// Get pets with pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_species = isset($_GET['species']) ? $_GET['species'] : '';
$filter_shelter = isset($_GET['shelter']) ? $_GET['shelter'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where_conditions = [];
$params = [];

if ($filter_status) {
    $where_conditions[] = "p.status = ?";
    $params[] = $filter_status;
}

if ($filter_species) {
    $where_conditions[] = "p.species = ?";
    $params[] = $filter_species;
}

if ($filter_shelter) {
    $where_conditions[] = "s.id = ?";
    $params[] = $filter_shelter;
}

if ($search) {
    $where_conditions[] = "(p.name LIKE ? OR p.breed LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM pets p 
                  JOIN shelters s ON p.shelter_id = s.id 
                  JOIN users u ON s.user_id = u.id 
                  $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_pets = $stmt->fetch()['total'];
    $total_pages = ceil($total_pets / $per_page);
    
    // Get pets
    $sql = "SELECT p.*, s.shelter_name, u.first_name, u.last_name 
            FROM pets p 
            JOIN shelters s ON p.shelter_id = s.id 
            JOIN users u ON s.user_id = u.id 
            $where_clause 
            ORDER BY p.created_at DESC 
            LIMIT $per_page OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pets = $stmt->fetchAll();
    
    // Get shelters for filter
    $stmt = $pdo->query("SELECT s.id, s.shelter_name FROM shelters s JOIN users u ON s.user_id = u.id ORDER BY s.shelter_name");
    $shelters = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $pets = [];
    $shelters = [];
    $total_pages = 1;
    $error_message = "Error fetching pets: " . $e->getMessage();
}

include '../common/header.php';
include '../common/navbar_admin.php';
?>

<main class="main-content">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 class="page-title">Manage Pets</h1>
            <div style="display: flex; gap: 1rem;">
                <span style="color: #6b7280;">Total: <?php echo $total_pets; ?> pets</span>
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
                    <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" id="search" name="search" class="form-input" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Pet name or breed">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-input form-select">
                            <option value="">All Status</option>
                            <option value="available" <?php echo $filter_status === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="adopted" <?php echo $filter_status === 'adopted' ? 'selected' : ''; ?>>Adopted</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="medical_hold" <?php echo $filter_status === 'medical_hold' ? 'selected' : ''; ?>>Medical Hold</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="species" class="form-label">Species</label>
                        <select id="species" name="species" class="form-input form-select">
                            <option value="">All Species</option>
                            <option value="dog" <?php echo $filter_species === 'dog' ? 'selected' : ''; ?>>Dog</option>
                            <option value="cat" <?php echo $filter_species === 'cat' ? 'selected' : ''; ?>>Cat</option>
                            <option value="bird" <?php echo $filter_species === 'bird' ? 'selected' : ''; ?>>Bird</option>
                            <option value="rabbit" <?php echo $filter_species === 'rabbit' ? 'selected' : ''; ?>>Rabbit</option>
                            <option value="other" <?php echo $filter_species === 'other' ? 'selected' : ''; ?>>Other</option>
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
                    
                    <button type="submit" class="btn">Filter</button>
                    <a href="/admin/managePets.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>
        </div>

        <!-- Pets Grid -->
        <div class="grid grid-cols-3">
            <?php if (!empty($pets)): ?>
                <?php foreach ($pets as $pet): ?>
                    <div class="card">
                        <div style="height: 200px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; font-size: 4rem;">
                            <?php echo $pet['species'] === 'dog' ? '🐕' : ($pet['species'] === 'cat' ? '🐱' : ($pet['species'] === 'bird' ? '🐦' : ($pet['species'] === 'rabbit' ? '🐰' : '🐾'))); ?>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                <div>
                                    <h3 style="font-size: 1.25rem; font-weight: bold; margin-bottom: 0.25rem;">
                                        <?php echo htmlspecialchars($pet['name']); ?>
                                    </h3>
                                    <p style="color: #6b7280; font-size: 0.875rem;">
                                        <?php echo ucfirst($pet['species']); ?> • <?php echo $pet['age']; ?> years • <?php echo ucfirst($pet['gender']); ?>
                                    </p>
                                    <?php if ($pet['breed']): ?>
                                        <p style="color: #6b7280; font-size: 0.75rem;">
                                            <?php echo htmlspecialchars($pet['breed']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span style="padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 9999px; font-weight: 500; 
                                             background: <?php echo $pet['status'] === 'available' ? '#d1fae5' : ($pet['status'] === 'adopted' ? '#dbeafe' : ($pet['status'] === 'medical_hold' ? '#fef3c7' : '#fee2e2')); ?>; 
                                             color: <?php echo $pet['status'] === 'available' ? '#065f46' : ($pet['status'] === 'adopted' ? '#1e40af' : ($pet['status'] === 'medical_hold' ? '#92400e' : '#991b1b')); ?>;">
                                    <?php echo ucfirst(str_replace('_', ' ', $pet['status'])); ?>
                                </span>
                            </div>
                            
                            <div style="margin-bottom: 1rem;">
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                    🏠 <?php echo htmlspecialchars($pet['shelter_name']); ?>
                                </p>
                                <p style="color: #9ca3af; font-size: 0.75rem;">
                                    Added <?php echo date('M j, Y', strtotime($pet['created_at'])); ?>
                                </p>
                            </div>
                            
                            <?php if ($pet['description']): ?>
                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 1rem; line-height: 1.4;">
                                    <?php echo htmlspecialchars(substr($pet['description'], 0, 100)) . (strlen($pet['description']) > 100 ? '...' : ''); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <?php if ($pet['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="pet_id" value="<?php echo $pet['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #059669; color: white; border: none; border-radius: 0.25rem; cursor: pointer;">
                                            Approve
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($pet['status'] !== 'medical_hold'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Place this pet on medical hold?')">
                                        <input type="hidden" name="pet_id" value="<?php echo $pet['id']; ?>">
                                        <input type="hidden" name="action" value="medical_hold">
                                        <button type="submit" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #f59e0b; color: white; border: none; border-radius: 0.25rem; cursor: pointer;">
                                            Medical Hold
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this pet record? This action cannot be undone.')">
                                    <input type="hidden" name="pet_id" value="<?php echo $pet['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #dc2626; color: white; border: none; border-radius: 0.25rem; cursor: pointer;">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #6b7280;">
                    <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">🐾</div>
                    <p>No pets found matching your criteria.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 2rem;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $filter_status; ?>&species=<?php echo $filter_species; ?>&shelter=<?php echo $filter_shelter; ?>&search=<?php echo urlencode($search); ?>" 
                       class="btn btn-secondary">Previous</a>
                <?php endif; ?>
                
                <span style="color: #6b7280;">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $filter_status; ?>&species=<?php echo $filter_species; ?>&shelter=<?php echo $filter_shelter; ?>&search=<?php echo urlencode($search); ?>" 
                       class="btn btn-secondary">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../common/footer.php'; ?>