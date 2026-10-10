<?php
/**
 * MediCycle - Admin Medical Supplies Oversight (Approve, Reject, Filter & CRUD)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$search = clean($_GET['search'] ?? '');
$statusFilter = clean($_GET['status'] ?? '');
$categoryFilter = clean($_GET['category'] ?? '');
$highlightId = (int)($_GET['highlight'] ?? 0);

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

// Handle Actions (Approve, Reject, Delete, Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch.');
    } else {
        $action = clean($_POST['action'] ?? '');
        $supplyId = (int)($_POST['supply_id'] ?? 0);
        $notes = clean($_POST['admin_notes'] ?? '');

        // 1. APPROVE SUPPLY (Becomes 'Available')
        if ($action === 'approve') {
            try {
                $upd = $pdo->prepare("UPDATE medical_supplies SET status = 'Available', admin_notes = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$notes ?: 'Approved by Administrator. Active for redistribution.', $supplyId]);

                // Notify Supplier
                $sStmt = $pdo->prepare("SELECT supplier_id, supply_name FROM medical_supplies WHERE id = ?");
                $sStmt->execute([$supplyId]);
                $sup = $sStmt->fetch();
                if ($sup) {
                    create_notification($pdo, $sup['supplier_id'], 'Supply Listing Approved!', "Your listing for '{$sup['supply_name']}' is approved and available to NGOs.", 'supplier/inventory.php');
                }

                set_flash('success', "Supply listing #{$supplyId} approved! It is now live in the available pool.");
                header('Location: ' . BASE_URL . '/admin/supplies.php');
                exit;
            } catch (PDOException $e) {
                set_flash('danger', 'Error approving supply: ' . $e->getMessage());
            }
        }

        // 2. REJECT SUPPLY
        elseif ($action === 'reject') {
            try {
                $upd = $pdo->prepare("UPDATE medical_supplies SET status = 'Rejected', admin_notes = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$notes ?: 'Does not meet consumable redistribution criteria.', $supplyId]);

                $sStmt = $pdo->prepare("SELECT supplier_id, supply_name FROM medical_supplies WHERE id = ?");
                $sStmt->execute([$supplyId]);
                $sup = $sStmt->fetch();
                if ($sup) {
                    create_notification($pdo, $sup['supplier_id'], 'Listing Not Approved', "Your listing for '{$sup['supply_name']}' was rejected: " . $notes, 'supplier/inventory.php');
                }

                set_flash('warning', "Supply listing #{$supplyId} marked as Rejected.");
                header('Location: ' . BASE_URL . '/admin/supplies.php');
                exit;
            } catch (PDOException $e) {
                set_flash('danger', 'Error updating supply: ' . $e->getMessage());
            }
        }

        // 3. DELETE SUPPLY
        elseif ($action === 'delete') {
            try {
                $del = $pdo->prepare("DELETE FROM medical_supplies WHERE id = ?");
                $del->execute([$supplyId]);
                set_flash('success', "Supply #{$supplyId} permanently removed.");
                header('Location: ' . BASE_URL . '/admin/supplies.php');
                exit;
            } catch (PDOException $e) {
                set_flash('danger', 'Cannot delete: active requests or dependencies reference this supply.');
            }
        }
    }
}

// Build Search and Filter Query
$sql = "SELECT ms.*, u.name as supplier_name, o.organization_name, c.category_name 
        FROM medical_supplies ms 
        JOIN users u ON ms.supplier_id = u.id 
        LEFT JOIN organizations o ON u.id = o.user_id 
        JOIN categories c ON ms.category_id = c.id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (ms.supply_name LIKE ? OR ms.batch_number LIKE ? OR ms.city LIKE ? OR u.name LIKE ? OR o.organization_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if (!empty($statusFilter)) {
    $sql .= " AND ms.status = ?";
    $params[] = $statusFilter;
}

if (!empty($categoryFilter)) {
    $sql .= " AND ms.category_id = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY (ms.status = 'Pending') DESC, ms.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$supplies = $stmt->fetchAll();

$pageTitle = 'Medical Supplies Oversight - Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Medical Supplies Inventory Oversight</h3>
                <p class="text-muted small mb-0">Audit listings, enforce safety criteria (unopened non-drug PPE & consumables), approve or reject</p>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by item name, batch, supplier, city..." value="<?php echo e($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending Approval</option>
                        <option value="Available" <?php echo $statusFilter === 'Available' ? 'selected' : ''; ?>>Available</option>
                        <option value="Reserved" <?php echo $statusFilter === 'Reserved' ? 'selected' : ''; ?>>Reserved</option>
                        <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="Rejected" <?php echo $statusFilter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo e($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-teal text-white w-100" style="background:#0f766e;">Filter</button>
                    <a href="<?php echo BASE_URL; ?>/admin/supplies.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Supplies Table -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-boxes-stacked text-teal me-2"></i> All Listed Supplies (<?php echo count($supplies); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Supply Item</th>
                                <th>Category</th>
                                <th>Supplier / Facility</th>
                                <th>Stock Qty</th>
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
                                    <tr class="<?php echo $s['id'] === $highlightId ? 'table-warning' : ($s['status'] === 'Pending' ? 'table-light' : ''); ?>">
                                        <td>#<?php echo $s['id']; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($s['supply_name']); ?></div>
                                            <small class="text-muted">Batch: <code><?php echo e($s['batch_number'] ?? 'N/A'); ?></code></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?php echo e($s['category_name']); ?></span></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($s['organization_name'] ?? $s['supplier_name']); ?></div>
                                            <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo e($s['city']); ?></small>
                                        </td>
                                        <td><span class="fw-bold text-teal"><?php echo $s['quantity'] . ' ' . e($s['unit']); ?></span></td>
                                        <td>
                                            <div><?php echo e($s['condition_status']); ?></div>
                                            <small class="text-muted"><?php echo e($s['packaging_status']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                                $daysLeft = (int)(new DateTime())->diff(new DateTime($s['expiry_date']))->format('%r%a');
                                                $badgeClass = ($daysLeft < 60) ? 'bg-danger text-white' : (($daysLeft < 180) ? 'bg-warning text-dark' : 'bg-light text-dark border');
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo format_date($s['expiry_date']); ?></span>
                                            <div class="text-muted" style="font-size:0.7rem;"><?php echo $daysLeft > 0 ? $daysLeft . ' days left' : 'Expired'; ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo $s['priority_score']; ?>/100</div>
                                            <?php echo priority_badge($s['priority_level']); ?>
                                        </td>
                                        <td><?php echo status_badge($s['status']); ?></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="openSupplyReview(<?php echo htmlspecialchars(json_encode($s)); ?>)">
                                                Review
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete supply #<?php echo $s['id']; ?>?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="supply_id" value="<?php echo $s['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 ms-1">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">No supplies found matching filter criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Supply Review Modal -->
<div class="modal fade" id="supplyReviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="supply_id" id="rev_supply_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="rev_title">Review Medical Supply</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Supply Name</label>
                        <div class="fw-bold fs-6 text-dark" id="rev_name"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Category</label>
                        <div class="fw-semibold text-dark" id="rev_category"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Quantity</label>
                        <div class="fw-bold text-teal" id="rev_quantity"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Physical Condition</label>
                        <div id="rev_condition"></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Packaging Status</label>
                        <div id="rev_packaging"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Expiry Date</label>
                        <div class="fw-bold" id="rev_expiry"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Pickup Location / City</label>
                        <div id="rev_location"></div>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small">Technical Description</label>
                        <p class="small text-muted bg-light p-2 rounded" id="rev_description"></p>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Admin Audit Notes / Feedback</label>
                        <textarea name="admin_notes" id="rev_notes" rows="2" class="form-control form-control-sm" placeholder="Review remarks, compliance comments, or instructions..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-times-circle me-1"></i> Reject Listing
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">
                        <i class="fas fa-check-circle me-1"></i> Approve & Make Available
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openSupplyReview(sup) {
    document.getElementById('rev_supply_id').value = sup.id;
    document.getElementById('rev_title').textContent = 'Review #' + sup.id + ': ' + sup.supply_name;
    document.getElementById('rev_name').textContent = sup.supply_name;
    document.getElementById('rev_category').textContent = sup.category_name;
    document.getElementById('rev_quantity').textContent = sup.quantity + ' ' + sup.unit;
    document.getElementById('rev_condition').textContent = sup.condition_status;
    document.getElementById('rev_packaging').textContent = sup.packaging_status;
    document.getElementById('rev_expiry').textContent = sup.expiry_date;
    document.getElementById('rev_location').textContent = sup.location + ' (' + sup.city + ')';
    document.getElementById('rev_description').textContent = sup.description || 'No description provided.';
    document.getElementById('rev_notes').value = sup.admin_notes || '';
    new bootstrap.Modal(document.getElementById('supplyReviewModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
