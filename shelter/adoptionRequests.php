<?php
require_once '../config/db.php';
check_role('shelter');
$page_title = 'Adoption Requests';

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

// Handle adoption actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $adoption_id = (int)$_POST['adoption_id'];
        $action = $_POST['action'];
        $admin_notes = isset($_POST['admin_notes']) ? sanitize_input($_POST['admin_notes']) : '';
        
        try {
            // Verify adoption belongs to this shelter
            $stmt = $pdo->prepare("SELECT a.id FROM adoptions a 
                                  JOIN pets p ON a.pet_id = p.id 
                                  WHERE a.id = ? AND p.shelter_id = ?");
            $stmt->execute([$adoption_id, $shelter['id']]);
            if (!$stmt->fetch()) {
                $error_message = "Adoption request not found or access denied.";
            } else {
                switch ($action) {
                    case 'approve':
                        $stmt = $pdo->prepare("UPDATE adoptions SET status = 'approved', approved_by = ?, approved_date = NOW(), admin_notes = ? WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id'], $admin_notes, $adoption_id]);
                        $success_message = "Adoption application approved.";
                        break;
                        
                    case 'reject':
                        $stmt = $pdo->prepare("UPDATE adoptions SET status = 'rejected', approved_by = ?, approved_date = NOW(), admin_notes = ? WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id'], $admin_notes, $adoption_id]);
                        $success_message = "Adoption application rejected.";
                        break;
                        
                    case 'complete':
                        $pdo->beginTransaction();
                        
                        // Update adoption status
                        $stmt = $pdo->prepare("UPDATE adoptions SET status = 'completed', admin_notes = ? WHERE id = ?");
                        $stmt->execute([$admin_notes, $adoption_id]);
                        
                        // Update pet status
                        $stmt = $pdo->prepare("UPDATE pets p JOIN adoptions a ON p.id = a.pet_id SET p.status = 'adopted' WHERE a.id = ?");
                        $stmt->execute([$adoption_id]);
                        
                        $pdo->commit();
                        $success_message = "Adoption completed successfully!";
                        break;
                }
            }
        } catch(PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            $error_message = "Error performing action: " . $e->getMessage();
        }
    }
}

// Get adoption requests with filters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_pet = isset($_GET['pet_id']) ? (int)$_GET['pet_id'] : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where_conditions = ["p.shelter_id = ?"];
$params = [$shelter['id']];

if ($filter_status) {
    $where_conditions[] = "a.status = ?";
    $params[] = $filter_status;
}

if ($filter_pet) {
    $where_conditions[] = "p.id = ?";
    $params[] = $filter_pet;
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR p.name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

try {
    // Get adoption requests
    $sql = "SELECT a.*, p.name as pet_name, p.species, p.breed, p.age, 
                   u.first_name, u.last_name, u.email, u.phone,
                   admin.first_name as admin_first_name, admin.last_name as admin_last_name
            FROM adoptions a 
            JOIN pets p ON a.pet_id = p.id 
            JOIN users u ON a.adopter_id = u.id 
            LEFT JOIN users admin ON a.approved_by = admin.id
            $where_clause 
            ORDER BY a.application_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $adoptions = $stmt->fetchAll();
    
    // Get pets for filter
    $stmt = $pdo->prepare("SELECT id, name FROM pets WHERE shelter_id = ? ORDER BY name");
    $stmt->execute([$shelter['id']]);
    $pets = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->prepare("SELECT a.status, COUNT(*) as count 
                          FROM adoptions a 
                          JOIN pets p ON a.pet_id = p.id 
                          WHERE p.shelter_id = ? 
                          GROUP BY a.status");
    $stmt->execute([$shelter['id']]);
    $stats = [];
    while ($row = $stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
    }
    
} catch(PDOException $e) {
    $adoptions = [];
    $pets = [];
    $stats = [];
    $error_message = "Error fetching adoption requests: " . $e->getMessage();
}

include '../common/header.php';
include '../common/navbar_shelter.php';
?>

<main class="main-content">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 class="page-title">Adoption Requests</h1>
                <p style="color: #6b7280;">Review and manage adoption applications</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <span style="color: #6b7280;">Total: <?php echo count($adoptions); ?> applications</span>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-4 mb-6">
            <div class="card">
                <div class="card-body text-center">
                    <h3 style="font-size: 2rem; color: #f59e0b; margin-bottom: 0.5rem;">
                        <?php echo $stats['pending'] ?? 0; ?>
                    </h3>
                    <p style="color: #6b7280;">Pending</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <h3 style="font-size: 2rem; color: #059669; margin-bottom: 0.5rem;">
                        <?php echo $stats['approved'] ?? 0; ?>
                    </h3>
                    <p style="color: #6b7280;">Approved</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <h3 style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem;">
                        <?php echo $stats['completed'] ?? 0; ?>
                    </h3>
                    <p style="color: #6b7280;">Completed</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body text-center">
                    <h3 style="font-size: 2rem; color: #dc2626; margin-bottom: 0.5rem;">
                        <?php echo $stats['rejected'] ?? 0; ?>
                    </h3>
                    <p style="color: #6b7280;">Rejected</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-6">
            <div class="card-body">
                <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" id="search" name="search" class="form-input" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Adopter name or pet name">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-input form-select">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="pet_id" class="form-label">Pet</label>
                        <select id="pet_id" name="pet_id" class="form-input form-select">
                            <option value="">All Pets</option>
                            <?php foreach ($pets as $pet): ?>
                                <option value="<?php echo $pet['id']; ?>" <?php echo $filter_pet == $pet['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pet['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Filter</button>
                    <a href="/shelter/adoptionRequests.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>
        </div>

        <!-- Adoption Requests -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php if (!empty($adoptions)): ?>
                <?php foreach ($adoptions as $adoption): ?>
                    <div class="card">
                        <div class="card-body">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                        <h3 style="font-size: 1.25rem; font-weight: bold; margin: 0;">
                                            <?php echo htmlspecialchars($adoption['first_name'] . ' ' . $adoption['last_name']); ?>
                                        </h3>
                                        <span style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border-radius: 9999px; font-weight: 500; 
                                                     background: <?php echo $adoption['status'] === 'pending' ? '#fef3c7' : ($adoption['status'] === 'approved' ? '#d1fae5' : ($adoption['status'] === 'rejected' ? '#fee2e2' : '#dbeafe')); ?>; 
                                                     color: <?php echo $adoption['status'] === 'pending' ? '#92400e' : ($adoption['status'] === 'approved' ? '#065f46' : ($adoption['status'] === 'rejected' ? '#991b1b' : '#1e40af')); ?>;">
                                            <?php echo ucfirst($adoption['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div style="display: flex; gap: 2rem; margin-bottom: 1rem;">
                                        <div>
                                            <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                                <strong>Pet:</strong> <?php echo htmlspecialchars($adoption['pet_name']); ?>
                                            </p>
                                            <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                                <strong>Species:</strong> <?php echo ucfirst($adoption['species']); ?> 
                                                <?php if ($adoption['breed']): ?>
                                                    (<?php echo htmlspecialchars($adoption['breed']); ?>)
                                                <?php endif; ?>
                                            </p>
                                            <p style="color: #6b7280; font-size: 0.875rem;">
                                                <strong>Age:</strong> <?php echo $adoption['age']; ?> years
                                            </p>
                                        </div>
                                        
                                        <div>
                                            <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                                <strong>Email:</strong> <?php echo htmlspecialchars($adoption['email']); ?>
                                            </p>
                                            <?php if ($adoption['phone']): ?>
                                                <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($adoption['phone']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <p style="color: #6b7280; font-size: 0.875rem;">
                                                <strong>Applied:</strong> <?php echo date('M j, Y g:i A', strtotime($adoption['application_date'])); ?>
                                            </p>
                                        </div>
                                        
                                        <div>
                                            <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                                <strong>Experience:</strong> <?php echo ucfirst($adoption['experience_level']); ?>
                                            </p>
                                            <p style="color: #6b7280; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                                <strong>Housing:</strong> <?php echo ucfirst($adoption['housing_type']); ?>
                                            </p>
                                            <p style="color: #6b7280; font-size: 0.875rem;">
                                                <strong>Yard:</strong> <?php echo $adoption['has_yard'] ? 'Yes' : 'No'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($adoption['reason_for_adoption']): ?>
                                        <div style="margin-bottom: 1rem;">
                                            <p style="color: #374151; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Reason for Adoption:</p>
                                            <p style="color: #6b7280; font-size: 0.875rem; line-height: 1.4;">
                                                <?php echo htmlspecialchars($adoption['reason_for_adoption']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($adoption['other_pets']): ?>
                                        <div style="margin-bottom: 1rem;">
                                            <p style="color: #374151; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Other Pets:</p>
                                            <p style="color: #6b7280; font-size: 0.875rem; line-height: 1.4;">
                                                <?php echo htmlspecialchars($adoption['other_pets']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($adoption['references']): ?>
                                        <div style="margin-bottom: 1rem;">
                                            <p style="color: #374151; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">References:</p>
                                            <p style="color: #6b7280; font-size: 0.875rem; line-height: 1.4;">
                                                <?php echo htmlspecialchars($adoption['references']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($adoption['admin_notes']): ?>
                                        <div style="margin-bottom: 1rem; padding: 0.75rem; background: #f8fafc; border-radius: 0.375rem;">
                                            <p style="color: #374151; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">Notes:</p>
                                            <p style="color: #6b7280; font-size: 0.875rem; line-height: 1.4;">
                                                <?php echo htmlspecialchars($adoption['admin_notes']); ?>
                                            </p>
                                            <?php if ($adoption['admin_first_name']): ?>
                                                <p style="color: #9ca3af; font-size: 0.75rem; margin-top: 0.5rem;">
                                                    By <?php echo htmlspecialchars($adoption['admin_first_name'] . ' ' . $adoption['admin_last_name']); ?>
                                                    <?php if ($adoption['approved_date']): ?>
                                                        on <?php echo date('M j, Y g:i A', strtotime($adoption['approved_date'])); ?>
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($adoption['status'] === 'pending'): ?>
                                <div style="display: flex; gap: 1rem; border-top: 1px solid #e2e8f0; padding-top: 1rem;">
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="adoption_id" value="<?php echo $adoption['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <div class="form-group" style="margin-bottom: 0.5rem;">
                                            <textarea name="admin_notes" class="form-input form-textarea" 
                                                      placeholder="Add approval notes (optional)" 
                                                      style="min-height: 60px;"></textarea>
                                        </div>
                                        <button type="submit" class="btn" style="background: #059669;">
                                            ✓ Approve Application
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="flex: 1;">
                                        <input type="hidden" name="adoption_id" value="<?php echo $adoption['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <div class="form-group" style="margin-bottom: 0.5rem;">
                                            <textarea name="admin_notes" class="form-input form-textarea" 
                                                      placeholder="Add rejection reason (required)" 
                                                      style="min-height: 60px;" required></textarea>
                                        </div>
                                        <button type="submit" class="btn" style="background: #dc2626;" 
                                                onclick="return confirm('Reject this adoption application?')">
                                            ✗ Reject Application
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($adoption['status'] === 'approved'): ?>
                                <div style="border-top: 1px solid #e2e8f0; padding-top: 1rem;">
                                    <form method="POST" style="max-width: 500px;">
                                        <input type="hidden" name="adoption_id" value="<?php echo $adoption['id']; ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <div class="form-group" style="margin-bottom: 0.5rem;">
                                            <textarea name="admin_notes" class="form-input form-textarea" 
                                                      placeholder="Add completion notes (optional)" 
                                                      style="min-height: 60px;"></textarea>
                                        </div>
                                        <button type="submit" class="btn" style="background: #3b82f6;" 
                                                onclick="return confirm('Mark this adoption as completed? This will update the pet status to adopted.')">
                                            🎉 Complete Adoption
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center" style="padding: 3rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;">📋</div>
                        <p style="color: #6b7280;">No adoption requests found matching your criteria.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../common/footer.php'; ?>