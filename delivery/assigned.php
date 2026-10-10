<?php
/**
 * MediCycle - Delivery Partner Assigned Deliveries
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('delivery');

$userId = $_SESSION['user_id'];
$search = clean($_GET['search'] ?? '');
$statusFilter = clean($_GET['status'] ?? '');

// Handle Accept Delivery
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'accept') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch.');
    } else {
        $deliveryId = (int)($_POST['delivery_id'] ?? 0);
        try {
            $upd = $pdo->prepare("UPDATE deliveries SET delivery_partner_id = ?, delivery_status = 'Accepted', accepted_at = NOW(), updated_at = NOW() WHERE id = ?");
            $upd->execute([$userId, $deliveryId]);

            // Notify Requester and Supplier
            $infoStmt = $pdo->prepare("SELECT d.request_id, r.requester_id, s.supplier_id, s.supply_name 
                                       FROM deliveries d 
                                       JOIN requests r ON d.request_id = r.id 
                                       JOIN medical_supplies s ON r.supply_id = s.id 
                                       WHERE d.id = ?");
            $infoStmt->execute([$deliveryId]);
            $info = $infoStmt->fetch();

            if ($info) {
                create_notification($pdo, $info['requester_id'], 'Courier Accepted Delivery', "Courier has accepted delivery for {$info['supply_name']} and is preparing for pickup.", 'ngo/my-requests.php');
                create_notification($pdo, $info['supplier_id'], 'Courier Assigned for Pickup', "Courier has accepted consignment #{$deliveryId}. Please prepare package.", 'supplier/requests.php');
            }

            set_flash('success', "Delivery #{$deliveryId} accepted! Proceed to supplier location for package pickup.");
            header('Location: ' . BASE_URL . '/delivery/update-status.php?id=' . $deliveryId);
            exit;
        } catch (PDOException $e) {
            set_flash('danger', 'Error accepting delivery: ' . $e->getMessage());
        }
    }
}

// Build Query
$sql = "SELECT d.*, r.requested_quantity, r.purpose, ms.supply_name, ms.unit, c.category_name,
               su.name as supplier_name, su.phone as supplier_phone, so.organization_name as supplier_org,
               nu.name as ngo_name, nu.phone as ngo_phone, no.organization_name as ngo_org, no.city as ngo_city
        FROM deliveries d
        JOIN requests r ON d.request_id = r.id
        JOIN medical_supplies ms ON r.supply_id = ms.id
        JOIN categories c ON ms.category_id = c.id
        JOIN users su ON ms.supplier_id = su.id
        LEFT JOIN organizations so ON su.id = so.user_id
        JOIN users nu ON r.requester_id = nu.id
        LEFT JOIN organizations no ON nu.id = no.user_id
        WHERE (d.delivery_partner_id = ? OR d.delivery_partner_id IS NULL)";
$params = [$userId];

if (!empty($search)) {
    $sql .= " AND (ms.supply_name LIKE ? OR d.pickup_location LIKE ? OR d.delivery_location LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term]);
}

if (!empty($statusFilter)) {
    $sql .= " AND d.delivery_status = ?";
    $params[] = $statusFilter;
} else {
    $sql .= " AND d.delivery_status != 'Delivered' AND d.delivery_status != 'Cancelled'";
}

$sql .= " ORDER BY (d.delivery_status = 'Assigned') DESC, d.assigned_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deliveries = $stmt->fetchAll();

$pageTitle = 'Assigned Deliveries - MediCycle Courier';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Assigned Courier Consignments</h3>
                <p class="text-muted small mb-0">Accept delivery jobs, navigate pickup points, and manage live transit updates</p>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>/delivery/history.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-history me-1"></i> Delivery History
                </a>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-7">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by consignment item, origin pickup, or drop..." value="<?php echo e($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Active Statuses</option>
                        <option value="Assigned" <?php echo $statusFilter === 'Assigned' ? 'selected' : ''; ?>>Newly Assigned</option>
                        <option value="Accepted" <?php echo $statusFilter === 'Accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="Picked Up" <?php echo $statusFilter === 'Picked Up' ? 'selected' : ''; ?>>Picked Up</option>
                        <option value="In Transit" <?php echo $statusFilter === 'In Transit' ? 'selected' : ''; ?>>In Transit</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-teal text-white w-100" style="background:#0f766e;">Filter</button>
                    <a href="<?php echo BASE_URL; ?>/delivery/assigned.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Deliveries Card List -->
        <div class="row g-3">
            <?php if (!empty($deliveries)): ?>
                <?php foreach ($deliveries as $del): ?>
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-light text-secondary border me-1">#DEL-<?php echo $del['id']; ?></span>
                                    <span class="badge bg-light text-dark border"><?php echo e($del['category_name']); ?></span>
                                </div>
                                <div>
                                    <?php echo status_badge($del['delivery_status']); ?>
                                </div>
                            </div>

                            <h5 class="fw-bold text-dark mb-1"><?php echo e($del['supply_name']); ?></h5>
                            <div class="fs-6 fw-bold text-teal mb-3"><?php echo $del['requested_quantity'] . ' ' . e($del['unit']); ?></div>

                            <div class="bg-light p-3 rounded-3 mb-3 small">
                                <div class="mb-2">
                                    <span class="text-muted d-block" style="font-size:0.75rem;"><i class="fas fa-arrow-up text-danger me-1"></i> PICKUP ORIGIN (Supplier)</span>
                                    <strong class="text-dark"><?php echo e($del['supplier_org'] ?? $del['supplier_name']); ?></strong>
                                    <div class="text-secondary"><?php echo e($del['pickup_location']); ?></div>
                                    <div class="text-muted"><i class="fas fa-phone me-1"></i><?php echo e($del['supplier_phone'] ?? 'Phone on record'); ?></div>
                                </div>
                                <hr class="my-2">
                                <div>
                                    <span class="text-muted d-block" style="font-size:0.75rem;"><i class="fas fa-arrow-down text-success me-1"></i> DROP DESTINATION (NGO / Clinic)</span>
                                    <strong class="text-dark"><?php echo e($del['ngo_org'] ?? $del['ngo_name']); ?></strong>
                                    <div class="text-secondary"><?php echo e($del['delivery_location']); ?></div>
                                    <div class="text-muted"><i class="fas fa-phone me-1"></i><?php echo e($del['ngo_phone'] ?? 'Phone on record'); ?></div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                <a href="<?php echo BASE_URL; ?>/delivery/delivery-details.php?id=<?php echo $del['id']; ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-eye me-1"></i> View Route Details
                                </a>

                                <?php if ($del['delivery_status'] === 'Assigned'): ?>
                                    <form method="POST">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="accept">
                                        <input type="hidden" name="delivery_id" value="<?php echo $del['id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-check me-1"></i> Accept Job
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="<?php echo BASE_URL; ?>/delivery/update-status.php?id=<?php echo $del['id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-truck me-1"></i> Update Transit Status
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-3 p-5 text-center bg-white">
                        <i class="fas fa-clipboard-check text-muted display-4 mb-3"></i>
                        <h5 class="fw-bold text-dark">No Active Deliveries Assigned</h5>
                        <p class="text-muted small">You currently have no pending medical supply transit consignments.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
