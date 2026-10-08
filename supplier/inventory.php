<?php
/**
 * MediCycle - Supplier Inventory (READ, SEARCH, FILTER, DELETE)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];

// Handle DELETE operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security token error. Deletion cancelled.');
    } else {
        $deleteId = (int)($_POST['supply_id'] ?? 0);
        try {
            // Verify ownership
            $checkStmt = $pdo->prepare("SELECT id, supply_name FROM medical_supplies WHERE id = ? AND supplier_id = ?");
            $checkStmt->execute([$deleteId, $userId]);
            $item = $checkStmt->fetch();

            if ($item) {
                // Delete record
                $delStmt = $pdo->prepare("DELETE FROM medical_supplies WHERE id = ? AND supplier_id = ?");
                $delStmt->execute([$deleteId, $userId]);
                set_flash('success', "Medical supply '{$item['supply_name']}' has been successfully removed.");
            } else {
                set_flash('danger', 'Item not found or you do not have permission to delete it.');
            }
        } catch (PDOException $e) {
            error_log("Delete supply error: " . $e->getMessage());
            set_flash('danger', 'Cannot delete this supply because it is linked to active requests or transfers.');
        }
        header('Location: ' . BASE_URL . '/supplier/inventory.php');
        exit;
    }
}

// Search and Filter parameters
$search = clean($_GET['search'] ?? '');
$categoryFilter = (int)($_GET['category'] ?? 0);
$statusFilter = clean($_GET['status'] ?? '');

// Categories for filter dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

// Build dynamic SQL query with PDO prepared statements
$sql = "SELECT s.*, c.category_name 
        FROM medical_supplies s 
        JOIN categories c ON s.category_id = c.id 
        WHERE s.supplier_id = ?";
$params = [$userId];

if (!empty($search)) {
    $sql .= " AND (s.supply_name LIKE ? OR s.batch_number LIKE ? OR s.description LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($categoryFilter > 0) {
    $sql .= " AND s.category_id = ?";
    $params[] = $categoryFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND s.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY s.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $supplies = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Inventory fetch error: " . $e->getMessage());
    $supplies = [];
}

$pageTitle = 'My Inventory';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Medical Supply Inventory</h3>
                <p class="text-muted small mb-0">Manage, search, edit, and monitor your listed lots</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/supplier/add-supply.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-1"></i> Add New Consumable
            </a>
        </div>

        <!-- Search & Filter Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form action="<?php echo BASE_URL; ?>/supplier/inventory.php" method="GET" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0" name="search" value="<?php echo e($search); ?>" placeholder="Search by item name, lot #, or specs...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter === (int)$cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <option value="Available" <?php echo $statusFilter === 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="Reserved" <?php echo $statusFilter === 'Reserved' ? 'selected' : ''; ?>>Reserved</option>
                            <option value="Transferred" <?php echo $statusFilter === 'Transferred' ? 'selected' : ''; ?>>Transferred</option>
                            <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending Approval</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-teal w-100 text-white" style="background:#0f766e;">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                        <?php if (!empty($search) || $categoryFilter > 0 || !empty($statusFilter)): ?>
                            <a href="<?php echo BASE_URL; ?>/supplier/inventory.php" class="btn btn-outline-secondary" title="Reset Filters">
                                <i class="fas fa-undo"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Inventory Table Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold text-dark"><i class="fas fa-boxes-stacked text-teal me-2" style="color:#0f766e;"></i>Inventory Records</span>
                <span class="badge bg-light text-muted border"><?php echo count($supplies); ?> Total Lots</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item Details</th>
                                <th>Category</th>
                                <th>Quantity Available</th>
                                <th>Condition & Seal</th>
                                <th>Expiry Date</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($supplies)): ?>
                                <?php foreach ($supplies as $s): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/supplier/view-supply.php?id=<?php echo $s['id']; ?>" class="fw-bold text-dark text-decoration-none">
                                                <?php echo e($s['supply_name']); ?>
                                            </a>
                                            <?php if (!empty($s['batch_number'])): ?>
                                                <div class="text-muted small">Lot: <code><?php echo e($s['batch_number']); ?></code></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-light text-secondary border"><?php echo e($s['category_name']); ?></span></td>
                                        <td class="fw-bold text-teal"><?php echo number_format($s['quantity']) . ' ' . e($s['unit']); ?></td>
                                        <td>
                                            <div class="small fw-medium text-dark"><?php echo e($s['condition_status']); ?></div>
                                            <div class="text-muted small" style="font-size:0.75rem;"><?php echo e($s['packaging_status']); ?></div>
                                        </td>
                                        <td>
                                            <?php
                                                $daysLeft = (int)(strtotime($s['expiry_date']) - time()) / 86400;
                                                $dateClass = $daysLeft < 90 ? 'text-danger fw-bold' : 'text-dark';
                                            ?>
                                            <span class="<?php echo $dateClass; ?>"><?php echo format_date($s['expiry_date']); ?></span>
                                            <div class="text-muted small" style="font-size:0.72rem;"><?php echo round($daysLeft); ?> days remaining</div>
                                        </td>
                                        <td>
                                            <?php echo priority_badge($s['priority_level']); ?>
                                            <span class="d-block text-muted small" style="font-size:0.72rem;">Score: <?php echo $s['priority_score']; ?>/100</span>
                                        </td>
                                        <td><?php echo status_badge($s['status']); ?></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/supplier/view-supply.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-secondary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/supplier/edit-supply.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-primary" title="Edit Supply">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <!-- DELETE Form with confirmation -->
                                                <form action="<?php echo BASE_URL; ?>/supplier/inventory.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this supply listing? This action cannot be undone.');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="supply_id" value="<?php echo $s['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete Supply">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-box-open fs-2 mb-2 d-block text-secondary"></i>
                                        No medical supplies matched your search query.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
