<?php
// Start session and include database
require_once '../config/db.php';

// Check if user is logged in and is an adopter
if (!Session::isLoggedIn() || Session::getUserType() !== 'adopter') {
    header('Location: ../auth/login.php');
    exit();
}

$adopter_user_id = Session::getUserId();

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$breed_filter = $_GET['breed'] ?? '';
$age_filter = $_GET['age'] ?? '';
$gender_filter = $_GET['gender'] ?? '';
$size_filter = $_GET['size'] ?? '';
$location_filter = $_GET['location'] ?? '';
$fee_min = $_GET['fee_min'] ?? '';
$fee_max = $_GET['fee_max'] ?? '';
$search_query = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'newest';

// Pagination
$page = (int)($_GET['page'] ?? 1);
$pets_per_page = 12;
$offset = ($page - 1) * $pets_per_page;

// Build WHERE clause for filters
$where_conditions = ["p.status = 'available'"];
$params = [];

if (!empty($category_filter)) {
    $where_conditions[] = "pc.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($breed_filter)) {
    $where_conditions[] = "pb.breed_id = ?";
    $params[] = $breed_filter;
}

if (!empty($gender_filter)) {
    $where_conditions[] = "p.gender = ?";
    $params[] = $gender_filter;
}

if (!empty($size_filter)) {
    $where_conditions[] = "p.size = ?";
    $params[] = $size_filter;
}

if (!empty($age_filter)) {
    switch ($age_filter) {
        case 'young':
            $where_conditions[] = "p.age <= 2";
            break;
        case 'adult':
            $where_conditions[] = "p.age BETWEEN 3 AND 7";
            break;
        case 'senior':
            $where_conditions[] = "p.age >= 8";
            break;
    }
}

if (!empty($fee_min)) {
    $where_conditions[] = "p.adoption_fee >= ?";
    $params[] = (float)$fee_min;
}

if (!empty($fee_max)) {
    $where_conditions[] = "p.adoption_fee <= ?";
    $params[] = (float)$fee_max;
}

if (!empty($search_query)) {
    $where_conditions[] = "(p.pet_name LIKE ? OR p.description LIKE ? OR pc.category_name LIKE ? OR pb.breed_name LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = implode(' AND ', $where_conditions);

// Build ORDER BY clause
$order_clause = "p.created_at DESC"; // default
switch ($sort_by) {
    case 'name_asc':
        $order_clause = "p.pet_name ASC";
        break;
    case 'name_desc':
        $order_clause = "p.pet_name DESC";
        break;
    case 'age_asc':
        $order_clause = "p.age ASC";
        break;
    case 'age_desc':
        $order_clause = "p.age DESC";
        break;
    case 'fee_asc':
        $order_clause = "p.adoption_fee ASC";
        break;
    case 'fee_desc':
        $order_clause = "p.adoption_fee DESC";
        break;
    case 'newest':
        $order_clause = "p.created_at DESC";
        break;
    case 'oldest':
        $order_clause = "p.created_at ASC";
        break;
}

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM pets p
    INNER JOIN pet_categories pc ON p.category_id = pc.category_id
    LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
    INNER JOIN shelters s ON p.shelter_id = s.shelter_id
    WHERE $where_clause
";

$total_result = DBHelper::selectOne($count_query, $params);
$total_pets = $total_result['total'] ?? 0;
$total_pages = ceil($total_pets / $pets_per_page);

// Get pets with filters applied
$pets_query = "
    SELECT p.*, pc.category_name, pb.breed_name, s.shelter_name, s.shelter_id,
           (SELECT COUNT(*) FROM adoption_applications aa WHERE aa.pet_id = p.pet_id) as application_count,
           (SELECT COUNT(*) FROM adoption_applications aa WHERE aa.pet_id = p.pet_id AND aa.adopter_id = ?) as user_applied
    FROM pets p
    INNER JOIN pet_categories pc ON p.category_id = pc.category_id
    LEFT JOIN pet_breeds pb ON p.breed_id = pb.breed_id
    INNER JOIN shelters s ON p.shelter_id = s.shelter_id
    WHERE $where_clause
    ORDER BY $order_clause
    LIMIT $pets_per_page OFFSET $offset
";

$pets_params = array_merge([$adopter_user_id], $params);
$pets = DBHelper::select($pets_query, $pets_params);

// Get filter options
$categories = DBHelper::select("SELECT * FROM pet_categories ORDER BY category_name");
$breeds = DBHelper::select("SELECT pb.*, pc.category_name FROM pet_breeds pb INNER JOIN pet_categories pc ON pb.category_id = pc.category_id ORDER BY pc.category_name, pb.breed_name");
$shelters = DBHelper::select("SELECT DISTINCT s.shelter_id, s.shelter_name FROM shelters s INNER JOIN pets p ON s.shelter_id = p.shelter_id WHERE p.status = 'available' ORDER BY s.shelter_name");

// Get statistics
$stats = [
    'total_available' => DBHelper::count('pets', ['status' => 'available']),
    'total_categories' => count($categories),
    'total_shelters' => count($shelters)
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Pets - Pet Adoption Care Guide</title>
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

    .browse-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
        background: rgba(255, 255, 255, 0.95);
        min-height: calc(100vh - 70px);
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
        display: flex;
        justify-content: space-between;
        align-items: center;
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

    .stats-summary {
        background: rgba(255, 255, 255, 0.1);
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        min-width: 200px;
    }

    .stats-summary h3 {
        font-size: 2rem;
        margin-bottom: 5px;
    }

    .search-filter-section {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .search-bar {
        padding: 25px;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-bottom: 1px solid #e9ecef;
    }

    .search-form {
        display: flex;
        gap: 15px;
        align-items: center;
        flex-wrap: wrap;
    }

    .search-input-group {
        flex: 1;
        min-width: 300px;
        position: relative;
    }

    .search-input {
        width: 100%;
        padding: 15px 20px 15px 50px;
        border: 2px solid #e9ecef;
        border-radius: 25px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
    }

    .search-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .search-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #666;
        font-size: 1.1rem;
    }

    .filter-toggles {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 12px 20px;
        border: 2px solid #667eea;
        background: white;
        color: #667eea;
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 600;
        text-decoration: none;
    }

    .filter-btn:hover,
    .filter-btn.active {
        background: #667eea;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    .filters-panel {
        display: none;
        padding: 25px;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
    }

    .filters-panel.show {
        display: block;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            max-height: 0;
            padding-top: 0;
            padding-bottom: 0;
        }

        to {
            opacity: 1;
            max-height: 500px;
            padding-top: 25px;
            padding-bottom: 25px;
        }
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .filter-label {
        font-weight: 600;
        color: #495057;
        font-size: 0.9rem;
    }

    .filter-select,
    .filter-input {
        padding: 10px 12px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: border-color 0.3s ease;
    }

    .filter-select:focus,
    .filter-input:focus {
        outline: none;
        border-color: #667eea;
    }

    .filter-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
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

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-outline {
        background: transparent;
        color: #667eea;
        border: 2px solid #667eea;
    }

    .btn-outline:hover {
        background: #667eea;
        color: white;
    }

    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .results-info {
        font-size: 1.1rem;
        color: #495057;
    }

    .sort-dropdown {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sort-select {
        padding: 8px 12px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 0.9rem;
    }

    .pets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .pet-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }

    .pet-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    }

    .pet-image {
        width: 100%;
        height: 220px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
        position: relative;
        overflow: hidden;
    }

    .pet-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .pet-card:hover .pet-image img {
        transform: scale(1.1);
    }

    .pet-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .applied-badge {
        background: linear-gradient(135deg, #28a745, #20c997);
    }

    .pet-content {
        padding: 20px;
    }

    .pet-name {
        font-size: 1.3rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 8px;
    }

    .pet-breed {
        color: #667eea;
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 12px;
    }

    .pet-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 15px;
        font-size: 0.9rem;
        color: #666;
    }

    .pet-detail {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .pet-description {
        color: #666;
        font-size: 0.9rem;
        line-height: 1.4;
        margin-bottom: 15px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .pet-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 15px;
        border-top: 1px solid #f0f0f0;
    }

    .adoption-fee {
        font-size: 1.2rem;
        font-weight: 700;
        color: #28a745;
    }

    .shelter-info {
        font-size: 0.85rem;
        color: #999;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .pet-actions {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }

    .btn-sm {
        padding: 8px 16px;
        font-size: 0.85rem;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 40px;
    }

    .pagination-btn {
        padding: 10px 15px;
        border: 2px solid #e9ecef;
        background: white;
        color: #667eea;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .pagination-btn:hover,
    .pagination-btn.active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .no-results {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }

    .no-results-icon {
        font-size: 5rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .loading {
        display: none;
        text-align: center;
        padding: 40px;
    }

    .loading-spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #667eea;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    @media (max-width: 768px) {
        .browse-container {
            padding: 10px;
        }

        .header-title {
            font-size: 2rem;
        }

        .header-content {
            flex-direction: column;
            gap: 20px;
            text-align: center;
        }

        .search-form {
            flex-direction: column;
        }

        .search-input-group {
            min-width: auto;
        }

        .filters-grid {
            grid-template-columns: 1fr;
        }

        .results-header {
            flex-direction: column;
        }

        .pets-grid {
            grid-template-columns: 1fr;
        }

        .pet-details {
            grid-template-columns: 1fr;
        }

        .pagination {
            flex-wrap: wrap;
        }
    }

    /* Favorite button animation */
    .favorite-btn {
        position: absolute;
        top: 15px;
        left: 15px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.9);
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 10;
    }

    .favorite-btn:hover {
        background: white;
        transform: scale(1.1);
    }

    .favorite-btn.favorited {
        background: #e74c3c;
        color: white;
    }

    /* Filter chip styles */
    .active-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }

    .filter-chip {
        background: #667eea;
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .filter-chip .remove {
        cursor: pointer;
        font-weight: bold;
    }
    </style>
</head>

<body>
    <?php include '../common/navbar_adopter.php'; ?>

    <div class="browse-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-content">
                <div>
                    <h1 class="header-title">
                        <i class="fas fa-search"></i>
                        Find Your Perfect Companion
                    </h1>
                    <p class="header-subtitle">Browse through our loving pets waiting for their forever homes</p>
                </div>
                <div class="stats-summary">
                    <h3><?php echo number_format($stats['total_available']); ?></h3>
                    <p>Pets Available</p>
                    <div style="font-size: 0.9rem; opacity: 0.8; margin-top: 10px;">
                        <?php echo $stats['total_categories']; ?> Categories • <?php echo $stats['total_shelters']; ?>
                        Shelters
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters Section -->
        <div class="search-filter-section">
            <div class="search-bar">
                <form method="GET" class="search-form" id="searchForm">
                    <div class="search-input-group">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="search" class="search-input"
                            placeholder="Search by name, breed, or description..."
                            value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>

                    <div class="filter-toggles">
                        <button type="button" class="filter-btn" onclick="toggleFilters()">
                            <i class="fas fa-filter"></i>
                            Advanced Filters
                        </button>

                        <button type="submit" class="filter-btn" style="background: #667eea; color: white;">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </div>
                </form>
            </div>

            <!-- Advanced Filters Panel -->
            <div class="filters-panel" id="filtersPanel">
                <form method="GET" id="filtersForm">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">

                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">Category</label>
                            <select name="category" class="filter-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>"
                                    <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Breed</label>
                            <select name="breed" class="filter-select" id="breedSelect">
                                <option value="">All Breeds</option>
                                <?php foreach ($breeds as $breed): ?>
                                <option value="<?php echo $breed['breed_id']; ?>"
                                    data-category="<?php echo $breed['category_id']; ?>"
                                    <?php echo $breed_filter == $breed['breed_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($breed['breed_name']); ?>
                                    (<?php echo htmlspecialchars($breed['category_name']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Age Group</label>
                            <select name="age" class="filter-select">
                                <option value="">All Ages</option>
                                <option value="young" <?php echo $age_filter == 'young' ? 'selected' : ''; ?>>Young (0-2
                                    years)</option>
                                <option value="adult" <?php echo $age_filter == 'adult' ? 'selected' : ''; ?>>Adult (3-7
                                    years)</option>
                                <option value="senior" <?php echo $age_filter == 'senior' ? 'selected' : ''; ?>>Senior
                                    (8+ years)</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Gender</label>
                            <select name="gender" class="filter-select">
                                <option value="">All Genders</option>
                                <option value="male" <?php echo $gender_filter == 'male' ? 'selected' : ''; ?>>Male
                                </option>
                                <option value="female" <?php echo $gender_filter == 'female' ? 'selected' : ''; ?>>
                                    Female</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Size</label>
                            <select name="size" class="filter-select">
                                <option value="">All Sizes</option>
                                <option value="small" <?php echo $size_filter == 'small' ? 'selected' : ''; ?>>Small
                                </option>
                                <option value="medium" <?php echo $size_filter == 'medium' ? 'selected' : ''; ?>>Medium
                                </option>
                                <option value="large" <?php echo $size_filter == 'large' ? 'selected' : ''; ?>>Large
                                </option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Adoption Fee Range</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" name="fee_min" class="filter-input" placeholder="Min $"
                                    value="<?php echo htmlspecialchars($fee_min); ?>" style="width: 50%;">
                                <input type="number" name="fee_max" class="filter-input" placeholder="Max $"
                                    value="<?php echo htmlspecialchars($fee_max); ?>" style="width: 50%;">
                            </div>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i>
                            Apply Filters
                        </button>

                        <a href="browsePets.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Clear All
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Active Filters Display -->
        <?php if (!empty($category_filter) || !empty($breed_filter) || !empty($age_filter) || !empty($gender_filter) || !empty($size_filter) || !empty($fee_min) || !empty($fee_max) || !empty($search_query)): ?>
        <div class="active-filters">
            <span style="font-weight: 600; color: #495057; margin-right: 10px;">Active Filters:</span>

            <?php if (!empty($search_query)): ?>
            <div class="filter-chip">
                Search: "<?php echo htmlspecialchars($search_query); ?>"
                <span class="remove" onclick="removeFilter('search')">&times;</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($category_filter)): 
                    $cat_name = '';
                    foreach ($categories as $cat) {
                        if ($cat['category_id'] == $category_filter) {
                            $cat_name = $cat['category_name'];
                            break;
                        }
                    }
                ?>
            <div class="filter-chip">
                Category: <?php echo htmlspecialchars($cat_name); ?>
                <span class="remove" onclick="removeFilter('category')">&times;</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($gender_filter)): ?>
            <div class="filter-chip">
                Gender: <?php echo ucfirst($gender_filter); ?>
                <span class="remove" onclick="removeFilter('gender')">&times;</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($age_filter)): ?>
            <div class="filter-chip">
                Age: <?php echo ucfirst($age_filter); ?>
                <span class="remove" onclick="removeFilter('age')">&times;</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Results Header -->
        <div class="results-header">
            <div class="results-info">
                <strong><?php echo number_format($total_pets); ?></strong> pets found
                <?php if ($total_pets != $stats['total_available']): ?>
                <span style="color: #666;">out of <?php echo number_format($stats['total_available']); ?>
                    available</span>
                <?php endif; ?>
            </div>

            <div class="sort-dropdown">
                <label for="sort" style="font-weight: 600; color: #495057;">Sort by:</label>
                <select name="sort" id="sortSelect" class="sort-select" onchange="updateSort()">
                    <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                    <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)
                    </option>
                    <option value="age_asc" <?php echo $sort_by == 'age_asc' ? 'selected' : ''; ?>>Youngest First
                    </option>
                    <option value="age_desc" <?php echo $sort_by == 'age_desc' ? 'selected' : ''; ?>>Oldest First
                    </option>
                    <option value="fee_asc" <?php echo $sort_by == 'fee_asc' ? 'selected' : ''; ?>>Lowest Fee</option>
                    <option value="fee_desc" <?php echo $sort_by == 'fee_desc' ? 'selected' : ''; ?>>Highest Fee
                    </option>
                </select>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div class="loading" id="loadingIndicator">
            <div class="loading-spinner"></div>
            <p>Loading pets...</p>
        </div>

        <!-- Pets Grid -->
        <?php if (!empty($pets)): ?>
        <div class="pets-grid" id="petsGrid">
            <?php foreach ($pets as $pet): ?>
            <div class="pet-card" onclick="window.location.href='petDetails.php?id=<?php echo $pet['pet_id']; ?>'">
                <!-- Favorite Button -->
                <button class="favorite-btn"
                    onclick="event.stopPropagation(); toggleFavorite(<?php echo $pet['pet_id']; ?>)">
                    <i class="fas fa-heart"></i>
                </button>

                <!-- Pet Image -->
                <div class="pet-image">
                    <?php if ($pet['primary_image']): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($pet['primary_image']); ?>"
                        alt="<?php echo htmlspecialchars($pet['pet_name']); ?>">
                    <?php else: ?>
                    <i class="fas fa-paw"></i>
                    <?php endif; ?>

                    <!-- Applied Badge -->
                    <?php if ($pet['user_applied'] > 0): ?>
                    <div class="pet-badge applied-badge">
                        <i class="fas fa-check"></i> Applied
                    </div>
                    <?php elseif ($pet['application_count'] > 0): ?>
                    <div class="pet-badge">
                        <?php echo $pet['application_count']; ?>
                        Application<?php echo $pet['application_count'] != 1 ? 's' : ''; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pet Content -->
                <div class="pet-content">
                    <div class="pet-name"><?php echo htmlspecialchars($pet['pet_name']); ?></div>
                    <div class="pet-breed">
                        <?php echo htmlspecialchars($pet['category_name']); ?>
                        <?php if ($pet['breed_name']): ?>
                        • <?php echo htmlspecialchars($pet['breed_name']); ?>
                        <?php endif; ?>
                    </div>

                    <div class="pet-details">
                        <div class="pet-detail">
                            <i class="fas fa-birthday-cake"></i>
                            <?php echo $pet['age']; ?> year<?php echo $pet['age'] != 1 ? 's' : ''; ?> old
                        </div>
                        <div class="pet-detail">
                            <i class="fas fa-<?php echo $pet['gender'] == 'male' ? 'mars' : 'venus'; ?>"></i>
                            <?php echo ucfirst($pet['gender']); ?>
                        </div>
                        <?php if ($pet['size']): ?>
                        <div class="pet-detail">
                            <i class="fas fa-ruler"></i>
                            <?php echo ucfirst($pet['size']); ?>
                        </div>
                        <?php endif; ?>
                        <div class="pet-detail">
                            <i class="fas fa-heart"></i>
                            <?php echo ucfirst($pet['health_status'] ?? 'Healthy'); ?>
                        </div>
                    </div>

                    <?php if ($pet['description']): ?>
                    <div class="pet-description">
                        <?php echo htmlspecialchars($pet['description']); ?>
                    </div>
                    <?php endif; ?>

                    <div class="pet-footer">
                        <div>
                            <div class="adoption-fee">$<?php echo number_format($pet['adoption_fee'], 2); ?></div>
                            <div class="shelter-info">
                                <i class="fas fa-home"></i>
                                <?php echo htmlspecialchars($pet['shelter_name']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="pet-actions">
                        <button class="btn btn-primary btn-sm"
                            onclick="event.stopPropagation(); window.location.href='petDetails.php?id=<?php echo $pet['pet_id']; ?>'">
                            <i class="fas fa-eye"></i>
                            View Details
                        </button>

                        <?php if ($pet['user_applied'] == 0): ?>
                        <button class="btn btn-outline btn-sm"
                            onclick="event.stopPropagation(); applyForPet(<?php echo $pet['pet_id']; ?>)">
                            <i class="fas fa-heart"></i>
                            Apply Now
                        </button>
                        <?php else: ?>
                        <button class="btn btn-secondary btn-sm" disabled>
                            <i class="fas fa-check"></i>
                            Applied
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
                Previous
            </a>
            <?php endif; ?>

            <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-btn">1</a>
            <?php if ($start_page > 2): ?>
            <span class="pagination-btn" style="border: none; cursor: default;">...</span>
            <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($end_page < $total_pages): ?>
            <?php if ($end_page < $total_pages - 1): ?>
            <span class="pagination-btn" style="border: none; cursor: default;">...</span>
            <?php endif; ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                class="pagination-btn"><?php echo $total_pages; ?></a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                class="pagination-btn">
                Next
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 20px; color: #666; font-size: 0.9rem;">
            Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $pets_per_page, $total_pets); ?> of
            <?php echo number_format($total_pets); ?> pets
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- No Results -->
        <div class="no-results">
            <i class="fas fa-search no-results-icon"></i>
            <h3>No Pets Found</h3>
            <p>We couldn't find any pets matching your search criteria.</p>
            <div style="margin-top: 30px;">
                <a href="browsePets.php" class="btn btn-primary">
                    <i class="fas fa-refresh"></i>
                    View All Pets
                </a>
                <button onclick="toggleFilters()" class="btn btn-outline">
                    <i class="fas fa-filter"></i>
                    Adjust Filters
                </button>
            </div>

            <!-- Search Suggestions -->
            <div style="margin-top: 40px; text-align: left; max-width: 500px; margin-left: auto; margin-right: auto;">
                <h4 style="margin-bottom: 15px; color: #495057;">Search Suggestions:</h4>
                <ul style="color: #666; line-height: 1.6;">
                    <li>Try removing some filters to see more results</li>
                    <li>Check your spelling in the search box</li>
                    <li>Try searching for general terms like "dog" or "cat"</li>
                    <li>Browse by category instead of specific breeds</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Apply Modal -->
    <div id="quickApplyModal"
        style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center;">
        <div
            style="background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #333;">Quick Apply</h3>
                <button onclick="closeQuickApplyModal()"
                    style="background: none; border: none; font-size: 1.5rem; color: #999; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="quickApplyContent">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Add fade-in animation to pet cards
        const petCards = document.querySelectorAll('.pet-card');
        petCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';

            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 50);
        });

        // Initialize category-breed filter relationship
        initializeCategoryBreedFilter();

        // Initialize infinite scroll (optional)
        // initializeInfiniteScroll();
    });

    // Toggle filters panel
    function toggleFilters() {
        const panel = document.getElementById('filtersPanel');
        const isVisible = panel.classList.contains('show');

        if (isVisible) {
            panel.classList.remove('show');
        } else {
            panel.classList.add('show');
        }
    }

    // Update sort and reload page
    function updateSort() {
        const sortValue = document.getElementById('sortSelect').value;
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('sort', sortValue);
        currentUrl.searchParams.set('page', '1'); // Reset to first page
        window.location.href = currentUrl.toString();
    }

    // Remove specific filter
    function removeFilter(filterName) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.delete(filterName);
        currentUrl.searchParams.set('page', '1'); // Reset to first page
        window.location.href = currentUrl.toString();
    }

    // Category-breed filter relationship
    function initializeCategoryBreedFilter() {
        const categorySelect = document.querySelector('select[name="category"]');
        const breedSelect = document.getElementById('breedSelect');

        if (!categorySelect || !breedSelect) return;

        categorySelect.addEventListener('change', function() {
            const selectedCategory = this.value;
            const breedOptions = breedSelect.querySelectorAll('option');

            breedOptions.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }

                const optionCategory = option.dataset.category;
                if (selectedCategory === '' || selectedCategory === optionCategory) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });

            // Reset breed selection if current selection is hidden
            if (breedSelect.value && breedSelect.querySelector(`option[value="${breedSelect.value}"]`).style
                .display === 'none') {
                breedSelect.value = '';
            }
        });

        // Trigger on page load
        categorySelect.dispatchEvent(new Event('change'));
    }

    // Toggle favorite status
    function toggleFavorite(petId) {
        const favoriteBtn = event.target.closest('.favorite-btn');
        const isFavorited = favoriteBtn.classList.contains('favorited');

        // Optimistic update
        if (isFavorited) {
            favoriteBtn.classList.remove('favorited');
            favoriteBtn.style.color = '#666';
        } else {
            favoriteBtn.classList.add('favorited');
            favoriteBtn.style.color = '#e74c3c';
        }

        // Here you would make an AJAX call to save the favorite status
        // For now, we'll just show a toast message
        showToast(isFavorited ? 'Removed from favorites' : 'Added to favorites');
    }

    // Quick apply for pet
    function applyForPet(petId) {
        // Show loading
        showLoading();

        // In a real application, you would make an AJAX call to check if user can apply
        // For demo purposes, we'll redirect to pet details page
        setTimeout(() => {
            hideLoading();
            window.location.href = `petDetails.php?id=${petId}&apply=true`;
        }, 1000);
    }

    // Show loading indicator
    function showLoading() {
        document.getElementById('loadingIndicator').style.display = 'block';
        document.getElementById('petsGrid').style.opacity = '0.5';
    }

    // Hide loading indicator
    function hideLoading() {
        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('petsGrid').style.opacity = '1';
    }

    // Show toast message
    function showToast(message) {
        // Create toast element
        const toast = document.createElement('div');
        toast.style.cssText = `
                position: fixed;
                top: 100px;
                right: 20px;
                background: #333;
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                font-size: 0.9rem;
                z-index: 10000;
                animation: slideInRight 0.3s ease, slideOutRight 0.3s ease 2.7s;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            `;
        toast.textContent = message;

        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
        document.head.appendChild(style);

        document.body.appendChild(toast);

        // Remove after animation
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
            if (style.parentNode) {
                style.parentNode.removeChild(style);
            }
        }, 3000);
    }

    // Close quick apply modal
    function closeQuickApplyModal() {
        document.getElementById('quickApplyModal').style.display = 'none';
    }

    // Advanced search functionality
    function performAdvancedSearch() {
        const searchInput = document.querySelector('.search-input');
        const searchTerm = searchInput.value.toLowerCase().trim();

        if (searchTerm.length === 0) {
            return;
        }

        // Show loading
        showLoading();

        // Submit the form
        document.getElementById('searchForm').submit();
    }

    // Enter key search
    document.querySelector('.search-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performAdvancedSearch();
        }
    });

    // Infinite scroll (optional feature)
    function initializeInfiniteScroll() {
        let loading = false;
        let currentPage = <?php echo $page; ?>;
        const totalPages = <?php echo $total_pages; ?>;

        window.addEventListener('scroll', function() {
            if (loading || currentPage >= totalPages) return;

            const scrollPosition = window.innerHeight + window.scrollY;
            const documentHeight = document.documentElement.scrollHeight;

            if (scrollPosition >= documentHeight - 1000) {
                loading = true;
                loadMorePets();
            }
        });

        function loadMorePets() {
            showLoading();

            // Create URL for next page
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('page', currentPage + 1);

            // In a real application, you would make an AJAX call here
            // For demo purposes, we'll just redirect after a delay
            setTimeout(() => {
                window.location.href = currentUrl.toString();
            }, 1500);
        }
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Press 'F' to focus search
        if (e.key === 'f' && !e.target.matches('input, textarea, select')) {
            e.preventDefault();
            document.querySelector('.search-input').focus();
        }

        // Press 'G' to toggle filters
        if (e.key === 'g' && !e.target.matches('input, textarea, select')) {
            e.preventDefault();
            toggleFilters();
        }

        // Press 'R' to reset filters
        if (e.key === 'r' && !e.target.matches('input, textarea, select')) {
            e.preventDefault();
            window.location.href = 'browsePets.php';
        }
    });

    // Add smooth scrolling to pagination
    document.querySelectorAll('.pagination-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            showLoading();
        });
    });

    // Auto-hide filters on mobile after applying
    if (window.innerWidth <= 768) {
        document.getElementById('filtersForm').addEventListener('submit', function() {
            setTimeout(() => {
                document.getElementById('filtersPanel').classList.remove('show');
            }, 100);
        });
    }

    // Responsive handling
    window.addEventListener('resize', function() {
        const filtersPanel = document.getElementById('filtersPanel');
        if (window.innerWidth > 768 && filtersPanel.classList.contains('show')) {
            // Keep filters open on desktop
        } else if (window.innerWidth <= 768) {
            // Auto-hide on mobile when resizing
        }
    });

    // Add click animation to pet cards
    document.querySelectorAll('.pet-card').forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    });

    // Show help modal
    function showHelp() {
        alert(
            'Browse Pets Help:\n\nF - Focus search\nG - Toggle filters\nR - Reset all filters\n\nClick on any pet card to view details!\nUse the heart button to add pets to favorites.'
        );
    }

    // Add help shortcut
    document.addEventListener('keydown', function(e) {
        if (e.key === '?' && !e.target.matches('input, textarea, select')) {
            e.preventDefault();
            showHelp();
        }
    });

    // Initialize tooltips for mobile
    if ('ontouchstart' in window) {
        document.querySelectorAll('.favorite-btn').forEach(btn => {
            btn.addEventListener('touchstart', function(e) {
                e.stopPropagation();
            });
        });
    }

    // Performance optimization: Lazy load images
    function initializeLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                        }
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    // Initialize lazy loading if images have data-src attributes
    // initializeLazyLoading();
    </script>
</body>

</html>