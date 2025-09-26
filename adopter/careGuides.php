<?php
// adopter/careGuides.php - Care Guides Page for Adopters
require_once '../config/db.php';

// Check if user is logged in as adopter
if (!Session::isLoggedIn() || Session::getUserType() !== 'adopter') {
    header('Location: ../auth/login.php');
    exit();
}

$adopter_id = Session::getUserId();
$page_title = 'Care Guides - Pet Adoption Care Guide';

// Initialize variables
$care_guides = [];
$categories = [];
$selected_category = $_GET['category'] ?? '';
$selected_difficulty = $_GET['difficulty'] ?? '';
$search_query = trim($_GET['search'] ?? '');
$error_message = null;

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

try {
    // Get all categories for filter
    $categories_query = "
        SELECT pc.*, COUNT(cg.guide_id) as guide_count 
        FROM pet_categories pc 
        LEFT JOIN care_guides cg ON pc.category_id = cg.category_id AND cg.is_published = 1
        GROUP BY pc.category_id 
        ORDER BY pc.category_name
    ";
    $categories = DBHelper::select($categories_query) ?: [];

    // Build WHERE conditions
    $where_conditions = ['cg.is_published = 1'];
    $params = [];

    if (!empty($selected_category)) {
        $where_conditions[] = 'cg.category_id = ?';
        $params[] = $selected_category;
    }

    if (!empty($selected_difficulty)) {
        $where_conditions[] = 'cg.difficulty_level = ?';
        $params[] = $selected_difficulty;
    }

    if (!empty($search_query)) {
        $where_conditions[] = '(cg.title LIKE ? OR cg.content LIKE ?)';
        $search_param = '%' . $search_query . '%';
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    // Get total count for pagination
    $count_query = "
        SELECT COUNT(*) as total
        FROM care_guides cg
        JOIN pet_categories pc ON cg.category_id = pc.category_id
        JOIN users u ON cg.author_id = u.user_id
        $where_clause
    ";
    $total_result = DBHelper::selectOne($count_query, $params);
    $total_guides = $total_result ? (int)$total_result['total'] : 0;
    $total_pages = ceil($total_guides / $per_page);

    // Get care guides with pagination
    $guides_query = "
        SELECT 
            cg.guide_id,
            cg.title,
            cg.content,
            cg.difficulty_level,
            cg.created_at,
            pc.category_name,
            u.first_name,
            u.last_name,
            s.shelter_name
        FROM care_guides cg
        JOIN pet_categories pc ON cg.category_id = pc.category_id
        JOIN users u ON cg.author_id = u.user_id
        LEFT JOIN shelters s ON u.user_id = s.user_id
        $where_clause
        ORDER BY cg.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $final_params = array_merge($params, [$per_page, $offset]);
    $care_guides = DBHelper::select($guides_query, $final_params) ?: [];

} catch (Exception $e) {
    $error_message = "Error loading care guides: " . $e->getMessage();
    error_log($error_message);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        line-height: 1.6;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Page Header */
    .page-header {
        text-align: center;
        margin-bottom: 40px;
        color: #fff;
        padding: 30px 0;
    }

    .page-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 10px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    .page-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        font-weight: 300;
    }

    /* Search and Filter Section */
    .search-filter-section {
        background: #fff;
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .search-form {
        display: grid;
        grid-template-columns: 1fr 200px 200px auto;
        gap: 15px;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group label {
        font-weight: 600;
        color: #2c3e50;
        font-size: 0.9rem;
    }

    .form-group input,
    .form-group select {
        padding: 12px 15px;
        border: 2px solid #e1e8ed;
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: white;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .search-btn {
        padding: 12px 25px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .search-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
    }

    .clear-filters {
        margin-top: 15px;
        text-align: center;
    }

    .clear-filters a {
        color: #667eea;
        text-decoration: none;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .clear-filters a:hover {
        background: rgba(102, 126, 234, 0.1);
    }

    /* Results Header */
    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding: 20px 0;
        color: #fff;
    }

    .results-count {
        font-size: 1.1rem;
        font-weight: 500;
    }

    .sort-options {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .sort-options select {
        padding: 8px 12px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        font-weight: 500;
    }

    /* Care Guides Grid */
    .guides-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .guide-card {
        background: #fff;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    .guide-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .guide-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .guide-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
        line-height: 1.3;
        margin-bottom: 8px;
    }

    .guide-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 15px;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.85rem;
        color: #666;
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 12px;
    }

    .meta-item i {
        color: #667eea;
    }

    .difficulty-badge {
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .difficulty-beginner {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .difficulty-intermediate {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: white;
    }

    .difficulty-advanced {
        background: linear-gradient(135deg, #dc3545, #e83e8c);
        color: white;
    }

    .guide-content {
        color: #555;
        font-size: 0.95rem;
        line-height: 1.6;
        margin-bottom: 20px;
        display: -webkit-box;
        -webkit-line-clamp: 4;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .guide-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 15px;
        border-top: 1px solid #f0f0f0;
    }

    .author-info {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        color: #666;
    }

    .read-more-btn {
        padding: 8px 16px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .read-more-btn:hover {
        background: linear-gradient(135deg, #764ba2, #667eea);
        transform: translateY(-1px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #fff;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 10px;
    }

    .empty-state p {
        font-size: 1.1rem;
        opacity: 0.8;
    }

    /* Error Message */
    .error-message {
        background: rgba(220, 53, 69, 0.1);
        color: #721c24;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        border: 1px solid rgba(220, 53, 69, 0.2);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 40px;
    }

    .pagination a,
    .pagination span {
        padding: 10px 15px;
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .pagination a:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-1px);
    }

    .pagination .current {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-color: transparent;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
    }

    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
        animation: modalFadeIn 0.3s ease;
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .modal-content {
        background: #fff;
        padding: 30px;
        border-radius: 20px;
        max-width: 800px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        position: relative;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .modal-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: #2c3e50;
        line-height: 1.3;
        flex: 1;
        margin-right: 20px;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #666;
        cursor: pointer;
        padding: 5px;
        transition: color 0.3s ease;
        flex-shrink: 0;
    }

    .close-modal:hover {
        color: #dc3545;
    }

    .modal-body {
        line-height: 1.8;
        color: #333;
        font-size: 1rem;
    }

    .modal-body h3 {
        color: #2c3e50;
        margin: 25px 0 15px 0;
        font-size: 1.3rem;
    }

    .modal-body h3:first-child {
        margin-top: 0;
    }

    .modal-body p {
        margin-bottom: 15px;
    }

    .modal-body ul,
    .modal-body ol {
        margin: 15px 0 15px 25px;
    }

    .modal-body li {
        margin-bottom: 8px;
    }

    .modal-meta {
        background: #f8f9fa;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .page-title {
            font-size: 2rem;
        }

        .search-form {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .guides-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .results-header {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }

        .guide-card {
            padding: 20px;
        }

        .modal-content {
            padding: 20px;
            margin: 10px;
        }

        .modal-title {
            font-size: 1.3rem;
            margin-right: 10px;
        }
    }

    @media (max-width: 480px) {
        .page-title {
            font-size: 1.5rem;
        }

        .search-filter-section {
            padding: 20px;
        }

        .guide-card {
            padding: 15px;
        }

        .pagination {
            flex-wrap: wrap;
            gap: 5px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            font-size: 0.9rem;
        }
    }
    </style>
</head>

<body>
    <!-- Include Adopter Navigation -->
    <?php include '../common/navbar_adopter.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Care Guides</h1>
            <p class="page-subtitle">Expert advice and tips for taking care of your beloved pets</p>
        </div>

        <!-- Error Message -->
        <?php if ($error_message): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Search and Filter Section -->
        <div class="search-filter-section">
            <form class="search-form" method="GET" action="">
                <div class="form-group">
                    <label for="search">Search Guides</label>
                    <input type="text" id="search" name="search" placeholder="Search by title or content..."
                        value="<?php echo htmlspecialchars($search_query); ?>">
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>"
                            <?php echo $selected_category == $category['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category_name']); ?>
                            (<?php echo $category['guide_count']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="difficulty">Difficulty</label>
                    <select id="difficulty" name="difficulty">
                        <option value="">All Levels</option>
                        <option value="beginner" <?php echo $selected_difficulty === 'beginner' ? 'selected' : ''; ?>>
                            Beginner
                        </option>
                        <option value="intermediate"
                            <?php echo $selected_difficulty === 'intermediate' ? 'selected' : ''; ?>>
                            Intermediate
                        </option>
                        <option value="advanced" <?php echo $selected_difficulty === 'advanced' ? 'selected' : ''; ?>>
                            Advanced
                        </option>
                    </select>
                </div>

                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                    Search
                </button>
            </form>

            <?php if ($search_query || $selected_category || $selected_difficulty): ?>
            <div class="clear-filters">
                <a href="careGuides.php">
                    <i class="fas fa-times"></i> Clear all filters
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Results Header -->
        <div class="results-header">
            <div class="results-count">
                <i class="fas fa-book"></i>
                Found <?php echo $total_guides; ?> guide<?php echo $total_guides !== 1 ? 's' : ''; ?>
                <?php if ($search_query): ?>
                for "<?php echo htmlspecialchars($search_query); ?>"
                <?php endif; ?>
            </div>
        </div>

        <!-- Care Guides Grid -->
        <?php if (empty($care_guides)): ?>
        <div class="empty-state">
            <i class="fas fa-book-open"></i>
            <h3>No Care Guides Found</h3>
            <p>
                <?php if ($search_query || $selected_category || $selected_difficulty): ?>
                Try adjusting your search criteria or filters.
                <?php else: ?>
                No care guides are currently available.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <div class="guides-grid">
            <?php foreach ($care_guides as $guide): ?>
            <div class="guide-card">
                <div class="guide-header">
                    <div class="difficulty-badge difficulty-<?php echo $guide['difficulty_level']; ?>">
                        <?php echo ucfirst($guide['difficulty_level']); ?>
                    </div>
                </div>

                <h3 class="guide-title"><?php echo htmlspecialchars($guide['title']); ?></h3>

                <div class="guide-meta">
                    <div class="meta-item">
                        <i class="fas fa-tag"></i>
                        <span><?php echo htmlspecialchars($guide['category_name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('M j, Y', strtotime($guide['created_at'])); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo ceil(str_word_count($guide['content']) / 200); ?> min read</span>
                    </div>
                </div>

                <div class="guide-content">
                    <?php echo nl2br(htmlspecialchars(substr($guide['content'], 0, 200) . '...')); ?>
                </div>

                <div class="guide-footer">
                    <div class="author-info">
                        <i class="fas fa-user"></i>
                        <span>
                            by <?php echo htmlspecialchars($guide['first_name'] . ' ' . $guide['last_name']); ?>
                            <?php if ($guide['shelter_name']): ?>
                            (<?php echo htmlspecialchars($guide['shelter_name']); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                    <button class="read-more-btn" onclick="viewGuide(<?php echo $guide['guide_id']; ?>)">
                        <i class="fas fa-book-open"></i>
                        Read More
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
                    $base_url = 'careGuides.php?';
                    $params = [];
                    if ($search_query) $params[] = 'search=' . urlencode($search_query);
                    if ($selected_category) $params[] = 'category=' . urlencode($selected_category);
                    if ($selected_difficulty) $params[] = 'difficulty=' . urlencode($selected_difficulty);
                    $base_url .= implode('&', $params);
                    $base_url .= $params ? '&' : '';
                    ?>

            <?php if ($page > 1): ?>
            <a href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>

            <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1): ?>
            <a href="<?php echo $base_url; ?>page=1">1</a>
            <?php if ($start > 2): ?>
            <span>...</span>
            <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i == $page): ?>
            <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
            <a href="<?php echo $base_url; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $total_pages): ?>
            <?php if ($end < $total_pages - 1): ?>
            <span>...</span>
            <?php endif; ?>
            <a href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
            <a href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Guide Detail Modal -->
    <div id="guideModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle"></h2>
                <button class="close-modal" onclick="closeGuideModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Guide content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
    // Store guides data for JavaScript access
    const guides = <?php echo json_encode($care_guides); ?>;

    // Function to view guide details
    function viewGuide(guideId) {
        const guide = guides.find(g => g.guide_id == guideId);

        if (guide) {
            document.getElementById('modalTitle').textContent = guide.title;

            const readingTime = Math.ceil(guide.content.split(' ').length / 200);
            const difficultyClass = 'difficulty-' + guide.difficulty_level;

            document.getElementById('modalBody').innerHTML = `
                    <div class="modal-meta">
                        <div class="guide-meta">
                            <div class="meta-item">
                                <i class="fas fa-tag"></i>
                                <span>${escapeHtml(guide.category_name)}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span>by ${escapeHtml(guide.first_name + ' ' + guide.last_name)}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span>${new Date(guide.created_at).toLocaleDateString()}</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-clock"></i>
                                <span>${readingTime} min read</span>
                            </div>
                            <div class="difficulty-badge ${difficultyClass}">
                                ${guide.difficulty_level.charAt(0).toUpperCase() + guide.difficulty_level.slice(1)}
                            </div>
                        </div>
                    </div>
                    <div class="guide-full-content">
                        ${escapeHtml(guide.content).replace(/\n/g, '<br>')}
                    </div>
                `;

            document.getElementById('guideModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }

    // Function to close guide modal
    function closeGuideModal() {
        document.getElementById('guideModal').classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? String(text).replace(/[&<>"']/g, function(m) {
            return map[m];
        }) : '';
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('guideModal');
        if (event.target === modal) {
            closeGuideModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeGuideModal();
        }
    });

    // Enhanced search functionality
    document.getElementById('search').addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            this.form.submit();
        }
    });

    // Auto-submit form when filters change
    ['category', 'difficulty'].forEach(id => {
        document.getElementById(id).addEventListener('change', function() {
            this.form.submit();
        });
    });

    // Smooth scrolling for pagination
    document.querySelectorAll('.pagination a').forEach(link => {
        link.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    });

    // Animation for guide cards
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Apply animation to cards when they come into view
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.guide-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition =
                `opacity 0.5s ease ${index * 0.1}s, transform 0.5s ease ${index * 0.1}s`;
            observer.observe(card);
        });
    });

    // Focus management for modal
    function focusModal() {
        const modal = document.getElementById('guideModal');
        const firstFocusable = modal.querySelector(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (firstFocusable) {
            firstFocusable.focus();
        }
    }

    // Update viewGuide to include focus management
    const originalViewGuide = viewGuide;
    viewGuide = function(guideId) {
        originalViewGuide(guideId);
        setTimeout(focusModal, 100);
    };

    console.log('Care Guides page loaded successfully');
    console.log(`Total guides: ${guides.length}`);
    </script>
</body>

</html>