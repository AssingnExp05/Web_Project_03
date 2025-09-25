<?php
require_once '../config/db.php';
check_role('admin');
$page_title = 'Manage Users';

$success_message = '';
$error_message = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $user_id = (int)$_POST['user_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success_message = "User activated successfully.";
                    break;
                    
                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success_message = "User deactivated successfully.";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND user_type != 'admin'");
                    $stmt->execute([$user_id]);
                    $success_message = "User deleted successfully.";
                    break;
            }
        } catch(PDOException $e) {
            $error_message = "Error performing action: " . $e->getMessage();
        }
    }
}

// Get users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where_conditions = [];
$params = [];

if ($filter_type) {
    $where_conditions[] = "u.user_type = ?";
    $params[] = $filter_type;
}

if ($filter_status) {
    $where_conditions[] = "u.status = ?";
    $params[] = $filter_status;
}

if ($search) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM users u $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetch()['total'];
    $total_pages = ceil($total_users / $per_page);
    
    // Get users
    $sql = "SELECT u.*, s.shelter_name 
            FROM users u 
            LEFT JOIN shelters s ON u.id = s.user_id 
            $where_clause 
            ORDER BY u.created_at DESC 
            LIMIT $per_page OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $users = [];
    $total_pages = 1;
    $error_message = "Error fetching users: " . $e->getMessage();
}

include '../common/header.php';
include '../common/navbar_admin.php';
?>

<main class="main-content">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 class="page-title">Manage Users</h1>
            <div style="display: flex; gap: 1rem;">
                <span style="color: #6b7280;">Total: <?php echo $total_users; ?> users</span>
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
                               placeholder="Name, email, or username">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="type" class="form-label">User Type</label>
                        <select id="type" name="type" class="form-input form-select">
                            <option value="">All Types</option>
                            <option value="admin" <?php echo $filter_type === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="shelter" <?php echo $filter_type === 'shelter' ? 'selected' : ''; ?>>Shelter</option>
                            <option value="adopter" <?php echo $filter_type === 'adopter' ? 'selected' : ''; ?>>Adopter</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-input form-select">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Filter</button>
                    <a href="/admin/manageUsers.php" class="btn btn-secondary">Clear</a>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-body" style="padding: 0;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                            <tr>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;">User</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;">Type</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;">Status</th>
                                <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;">Joined</th>
                                <th style="padding: 1rem; text-align: center; font-weight: 600; color: #374151;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 1rem;">
                                            <div>
                                                <div style="font-weight: 500; color: #1f2937;">
                                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: #6b7280;">
                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #9ca3af;">
                                                    @<?php echo htmlspecialchars($user['username']); ?>
                                                </div>
                                                <?php if ($user['user_type'] === 'shelter' && $user['shelter_name']): ?>
                                                    <div style="font-size: 0.75rem; color: #059669; font-weight: 500;">
                                                        🏠 <?php echo htmlspecialchars($user['shelter_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <span style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border-radius: 9999px; font-weight: 500; 
                                                         background: <?php echo $user['user_type'] === 'admin' ? '#fee2e2' : ($user['user_type'] === 'shelter' ? '#d1fae5' : '#dbeafe'); ?>; 
                                                         color: <?php echo $user['user_type'] === 'admin' ? '#991b1b' : ($user['user_type'] === 'shelter' ? '#065f46' : '#1e40af'); ?>;">
                                                <?php echo ucfirst($user['user_type']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <span style="padding: 0.25rem 0.75rem; font-size: 0.75rem; border-radius: 9999px; font-weight: 500; 
                                                         background: <?php echo $user['status'] === 'active' ? '#d1fae5' : ($user['status'] === 'inactive' ? '#fee2e2' : '#fef3c7'); ?>; 
                                                         color: <?php echo $user['status'] === 'active' ? '#065f46' : ($user['status'] === 'inactive' ? '#991b1b' : '#92400e'); ?>;">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; color: #6b7280; font-size: 0.875rem;">
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td style="padding: 1rem; text-align: center;">
                                            <?php if ($user['user_type'] !== 'admin'): ?>
                                                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                                    <?php if ($user['status'] === 'active'): ?>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Deactivate this user?')">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="action" value="deactivate">
                                                            <button type="submit" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #f59e0b; color: white; border: none; border-radius: 0.25rem; cursor: pointer;">
                                                                Deactivate
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="action" value="activate">
                                                            <button type="submit" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #059669; color: white; border: none; border-radius: 0.25rem; cursor: pointer;">
                                                                Activate
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user? This action cannot be undone.')">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #dc2626; color: white; border: none; border-radius: 0.25rem; cursor: pointer;">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #6b7280; font-size: 0.75rem;">Protected</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="padding: 3rem; text-align: center; color: #6b7280;">
                                        No users found matching your criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 2rem;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $filter_type; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>" 
                       class="btn btn-secondary">Previous</a>
                <?php endif; ?>
                
                <span style="color: #6b7280;">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </span>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $filter_type; ?>&status=<?php echo $filter_status; ?>&search=<?php echo urlencode($search); ?>" 
                       class="btn btn-secondary">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../common/footer.php'; ?>