<?php
/**
 * MediCycle - NGO Search & Filter Medical Supplies
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];

// Filter parameters
$search = clean($_GET['search'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);
$city = clean($_GET['city'] ?? '');
$priority = clean($_GET['priority'] ?? '');

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

// Fetch distinct cities for dropdown filter
$cities = $pdo->query("SELECT DISTINCT location FROM medical_supplies WHERE status = 'Available' AND location IS NOT NULL AND location != '' ORDER BY location ASC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch active requirements of this NGO for smart match labeling
$reqStmt = $pdo->prepare("SELECT * FROM requirements WHERE organization_id = ? AND status = 'Active'");
$reqStmt->execute([$userId]);
$myRequirements = $reqStmt->fetchAll();

// Dynamic Query
$sql = "SELECT s.*, c.category_name, o.organization_name as supplier_name, o.city as supplier_city 
        FROM medical_supplies s 
        JOIN categories c ON s.category_id = c.id 
        JOIN organizations o ON s.supplier_id = o.user_id 
        WHERE s.status = 'Available' AND s.expiry_date > CURDATE()";
$params = [];

if (!empty($search)) {
    $sql .= " AND (s.supply_name LIKE ? OR s.description LIKE ? OR s.batch_number LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($categoryId > 0) {
    $sql .= " AND s.category_id = ?";
    $params[] = $categoryId;
}

if (!empty($city)) {
    $sql .= " AND s.location = ?";
    $params[] = $city;
}

if (!empty($priority)) {
    $sql .= " AND s.priority_level = ?";
    $params[] = $priority;
}

$sql .= " ORDER BY s.priority_score DESC, s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$supplies = $stmt->fetchAll();

$pageTitle = 'Search Medical Supplies';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Search Available Medical Supplies</h3>
                <div class="heading-accent-line start" style="width: 45px; height: 3px; margin: 0.35rem 0 0.5rem;"></div>
                <p class="text-muted small mb-0 page-headline">Browse and request eligible, unexpired non-drug consumables</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/ngo/post-requirement.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-bullhorn me-1"></i> Post Specific Clinical Need
            </a>
        </div>

        <!-- Search and Filter Form -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form action="<?php echo BASE_URL; ?>/ngo/search-supplies.php" method="GET" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Search gloves, masks, gauze, bandages...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId === (int)$cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="city">
                            <option value="">All Locations</option>
                            <?php foreach ($cities as $c): ?>
                                <option value="<?php echo e($c); ?>" <?php echo $city === $c ? 'selected' : ''; ?>><?php echo e($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="priority">
                            <option value="">All Priorities</option>
                            <option value="High" <?php echo $priority === 'High' ? 'selected' : ''; ?>>High Priority</option>
                            <option value="Medium" <?php echo $priority === 'Medium' ? 'selected' : ''; ?>>Medium Priority</option>
                            <option value="Low" <?php echo $priority === 'Low' ? 'selected' : ''; ?>>Low Priority</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-teal w-100 text-white" style="background:#0f766e;">
                            <i class="fas fa-filter"></i>
                        </button>
                        <?php if (!empty($search) || $categoryId > 0 || !empty($city) || !empty($priority)): ?>
                            <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="btn btn-outline-secondary" title="Reset">
                                <i class="fas fa-undo"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Search Results Grid -->
        <div class="row g-4">
            <?php if (!empty($supplies)): ?>
                <?php foreach ($supplies as $s): ?>
                    <?php
                        // Check if this supply matches any requirement
                        $isSmartMatch = false;
                        $matchReason = '';
                        foreach ($myRequirements as $req) {
                            $m = calculate_match_score($s, $req);
                            if ($m['is_recommended']) {
                                $isSmartMatch = true;
                                $matchReason = $m['summary'];
                                break;
                            }
                        }
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm card-hover d-flex flex-column <?php echo $isSmartMatch ? 'border-start border-4 border-success' : ''; ?>">
                            <div class="card-body p-4 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-light text-secondary border"><?php echo e($s['category_name']); ?></span>
                                    <div>
                                        <?php echo priority_badge($s['priority_level']); ?>
                                    </div>
                                </div>

                                <h5 class="fw-bold text-dark mt-1 mb-2">
                                    <a href="<?php echo BASE_URL; ?>/ngo/supply-details.php?id=<?php echo $s['id']; ?>" class="text-dark text-decoration-none">
                                        <?php echo e($s['supply_name']); ?>
                                    </a>
                                </h5>

                                <p class="text-muted small mb-3 flex-grow-1" style="font-size:0.82rem;">
                                    <?php echo e(substr($s['description'] ?? '', 0, 95)) . '...'; ?>
                                </p>

                                <?php if ($isSmartMatch): ?>
                                    <div class="p-2 mb-3 bg-success bg-opacity-10 text-success rounded small" style="font-size:0.75rem;">
                                        <i class="fas fa-brain me-1"></i> <strong>AI Recommendation:</strong> <?php echo e($matchReason); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="bg-light p-3 rounded-3 small mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Available Stock:</span>
                                        <span class="fw-bold text-teal"><?php echo number_format($s['quantity']) . ' ' . e($s['unit']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Expiry Date:</span>
                                        <span class="fw-semibold text-dark"><?php echo format_date($s['expiry_date']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Condition:</span>
                                        <span class="badge bg-white text-dark border"><?php echo e($s['condition_status']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Hospital Hub:</span>
                                        <span class="text-dark"><i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo e($s['supplier_city']); ?></span>
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <a href="<?php echo BASE_URL; ?>/ngo/supply-details.php?id=<?php echo $s['id']; ?>" class="btn btn-primary btn-sm w-100">
                                        <i class="fas fa-hand-holding-heart me-1"></i> View & Request Lot
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="fas fa-box-open fs-2 text-secondary mb-3 d-block"></i>
                    <h5>No medical supplies found</h5>
                    <p class="small">Try adjusting your search criteria or post a clinical requirement so hospitals can respond.</p>
                    <a href="<?php echo BASE_URL; ?>/ngo/post-requirement.php" class="btn btn-primary btn-sm mt-2">Post Clinical Requirement</a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
