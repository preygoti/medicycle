<?php
/**
 * MediCycle - Admin Deliveries & Logistics Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$search = clean($_GET['search'] ?? '');
$statusFilter = clean($_GET['status'] ?? '');

// Fetch active delivery partners for dropdown
$deliveryPartners = $pdo->query("SELECT u.id, u.name, u.phone, o.organization_name 
                                 FROM users u 
                                 LEFT JOIN organizations o ON u.id = o.user_id 
                                 WHERE u.role = 'delivery' AND u.status = 'active'")->fetchAll();

// Handle Actions (Assign / Reassign, Update Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security token mismatch.');
    } else {
        $action = clean($_POST['action'] ?? '');
        $deliveryId = (int)($_POST['delivery_id'] ?? 0);

        if ($action === 'reassign') {
            $partnerId = (int)($_POST['delivery_partner_id'] ?? 0);
            try {
                $upd = $pdo->prepare("UPDATE deliveries SET delivery_partner_id = ?, assigned_at = NOW() WHERE id = ?");
                $upd->execute([$partnerId ?: null, $deliveryId]);

                if ($partnerId) {
                    create_notification($pdo, $partnerId, 'Delivery Assigned', "You were assigned to delivery consignment #{$deliveryId}.", 'delivery/assigned.php');
                }

                set_flash('success', "Delivery #{$deliveryId} partner assigned successfully.");
                header('Location: ' . BASE_URL . '/admin/deliveries.php');
                exit;
            } catch (PDOException $e) {
                set_flash('danger', 'Error reassigning partner: ' . $e->getMessage());
            }

        } elseif ($action === 'update_status') {
            $newStatus = clean($_POST['delivery_status'] ?? '');
            $notes = clean($_POST['tracking_notes'] ?? '');

            try {
                $pdo->beginTransaction();

                $pickupStatus = in_array($newStatus, ['Picked Up', 'In Transit', 'Delivered']) ? 'Picked Up' : 'Pending';
                
                $pickedUpAt = ($newStatus === 'Picked Up' || $newStatus === 'In Transit') ? 'NOW()' : 'picked_up_at';
                $deliveredAt = ($newStatus === 'Delivered') ? 'NOW()' : 'delivered_at';

                $upd = $pdo->prepare("UPDATE deliveries SET 
                                        delivery_status = ?, 
                                        pickup_status = ?, 
                                        tracking_notes = CONCAT(COALESCE(tracking_notes, ''), '\n', ?),
                                        picked_up_at = IF(? = 'Picked Up' AND picked_up_at IS NULL, NOW(), picked_up_at),
                                        delivered_at = IF(? = 'Delivered' AND delivered_at IS NULL, NOW(), delivered_at),
                                        updated_at = NOW() 
                                      WHERE id = ?");
                $upd->execute([$newStatus, $pickupStatus, "[Admin update]: " . $notes, $newStatus, $newStatus, $deliveryId]);

                // If marked Delivered, complete the request and log impact
                if ($newStatus === 'Delivered') {
                    $delStmt = $pdo->prepare("SELECT d.request_id, r.requested_quantity, r.requester_id, s.supply_name, s.supplier_id 
                                              FROM deliveries d 
                                              JOIN requests r ON d.request_id = r.id 
                                              JOIN medical_supplies s ON r.supply_id = s.id 
                                              WHERE d.id = ?");
                    $delStmt->execute([$deliveryId]);
                    $delData = $delStmt->fetch();

                    if ($delData) {
                        $pdo->prepare("UPDATE requests SET status = 'Completed', completed_at = NOW() WHERE id = ?")->execute([$delData['request_id']]);
                        record_impact($pdo, $delData['request_id'], $delData['requested_quantity']);

                        create_notification($pdo, $delData['requester_id'], 'Consignment Delivered!', "Your medical supplies for {$delData['supply_name']} have been delivered.", 'ngo/my-requests.php');
                        create_notification($pdo, $delData['supplier_id'], 'Transfer Completed!', "Medical supply transfer #{$delData['request_id']} has been fulfilled and delivered.", 'supplier/history.php');
                    }
                }

                $pdo->commit();
                set_flash('success', "Delivery #{$deliveryId} status updated to {$newStatus}.");
                header('Location: ' . BASE_URL . '/admin/deliveries.php');
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash('danger', 'Error updating delivery status: ' . $e->getMessage());
            }
        }
    }
}

// Search and Filter SQL
$sql = "SELECT d.*, r.requested_quantity, r.status as req_status, 
               ms.supply_name, ms.unit,
               u_del.name as partner_name, u_del.phone as partner_phone,
               u_ngo.name as ngo_name, o_ngo.organization_name as ngo_org,
               u_sup.name as supplier_name, o_sup.organization_name as supplier_org
        FROM deliveries d 
        JOIN requests r ON d.request_id = r.id 
        JOIN medical_supplies ms ON r.supply_id = ms.id 
        LEFT JOIN users u_del ON d.delivery_partner_id = u_del.id 
        JOIN users u_ngo ON r.requester_id = u_ngo.id 
        LEFT JOIN organizations o_ngo ON u_ngo.id = o_ngo.user_id 
        JOIN users u_sup ON ms.supplier_id = u_sup.id 
        LEFT JOIN organizations o_sup ON u_sup.id = o_sup.user_id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (ms.supply_name LIKE ? OR u_del.name LIKE ? OR d.pickup_location LIKE ? OR d.delivery_location LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($statusFilter)) {
    $sql .= " AND d.delivery_status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY (d.delivery_status IN ('Assigned', 'Accepted', 'In Transit')) DESC, d.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deliveries = $stmt->fetchAll();

$pageTitle = 'Deliveries Management - Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Deliveries & Transport Logistics</h3>
                <p class="text-muted small mb-0">Courier routes, volunteer fleet assignments, pickup and delivery fulfillment logs</p>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-7">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by item, courier name, pickup/delivery destination..." value="<?php echo e($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Delivery Statuses</option>
                        <option value="Assigned" <?php echo $statusFilter === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                        <option value="Accepted" <?php echo $statusFilter === 'Accepted' ? 'selected' : ''; ?>>Accepted by Courier</option>
                        <option value="Picked Up" <?php echo $statusFilter === 'Picked Up' ? 'selected' : ''; ?>>Picked Up</option>
                        <option value="In Transit" <?php echo $statusFilter === 'In Transit' ? 'selected' : ''; ?>>In Transit</option>
                        <option value="Delivered" <?php echo $statusFilter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-teal text-white w-100" style="background:#0f766e;">Filter</button>
                    <a href="<?php echo BASE_URL; ?>/admin/deliveries.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Deliveries Table -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-truck-fast text-teal me-2"></i> Active Consignments (<?php echo count($deliveries); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Delivery ID</th>
                                <th>Supply Item</th>
                                <th>Assigned Courier</th>
                                <th>Origin Pickup</th>
                                <th>Destination Drop</th>
                                <th>Status</th>
                                <th>Timestamps</th>
                                <th class="text-end">Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($deliveries)): ?>
                                <?php foreach ($deliveries as $del): ?>
                                    <tr>
                                        <td>#DEL-<?php echo $del['id']; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($del['supply_name']); ?></div>
                                            <span class="badge bg-light text-primary"><?php echo $del['requested_quantity'] . ' ' . e($del['unit']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($del['partner_name']): ?>
                                                <div class="fw-semibold text-dark"><i class="fas fa-user-astronaut me-1 text-teal"></i><?php echo e($del['partner_name']); ?></div>
                                                <small class="text-muted"><?php echo e($del['partner_phone']); ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 170px;" title="<?php echo e($del['pickup_location']); ?>">
                                                <i class="fas fa-hospital text-danger me-1"></i><?php echo e($del['pickup_location']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 170px;" title="<?php echo e($del['delivery_location']); ?>">
                                                <i class="fas fa-location-dot text-success me-1"></i><?php echo e($del['delivery_location']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo status_badge($del['delivery_status']); ?>
                                        </td>
                                        <td>
                                            <div class="text-muted" style="font-size: 0.72rem;">Assigned: <?php echo format_datetime($del['assigned_at']); ?></div>
                                            <?php if ($del['delivered_at']): ?>
                                                <div class="text-success" style="font-size: 0.72rem;">Delivered: <?php echo format_datetime($del['delivered_at']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="openDeliveryModal(<?php echo htmlspecialchars(json_encode($del)); ?>)">
                                                Manage
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No delivery consignments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Manage Delivery Modal -->
<div class="modal fade" id="deliveryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="delivery_id" id="mdl_del_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="mdl_del_title">Manage Delivery Consignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Assign Delivery Partner / Courier</label>
                    <select name="delivery_partner_id" id="mdl_partner_id" class="form-select form-select-sm">
                        <option value="">-- Select Delivery Partner --</option>
                        <?php foreach ($deliveryPartners as $dp): ?>
                            <option value="<?php echo $dp['id']; ?>">
                                <?php echo e($dp['name']); ?> (<?php echo e($dp['phone']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Delivery Transit Status</label>
                    <select name="delivery_status" id="mdl_del_status" class="form-select form-select-sm">
                        <option value="Assigned">Assigned</option>
                        <option value="Accepted">Accepted by Courier</option>
                        <option value="Picked Up">Picked Up from Supplier</option>
                        <option value="In Transit">In Transit</option>
                        <option value="Delivered">Delivered & Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Transit Tracking Notes</label>
                    <textarea name="tracking_notes" id="mdl_notes" rows="3" class="form-control form-control-sm" placeholder="Van registration, route checkpoint notes, recipient signatures..."></textarea>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="submit" name="action" value="reassign" class="btn btn-sm btn-outline-secondary">
                    Reassign Partner
                </button>
                <button type="submit" name="action" value="update_status" class="btn btn-sm btn-primary">
                    Update Consignment Status
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeliveryModal(del) {
    document.getElementById('mdl_del_id').value = del.id;
    document.getElementById('mdl_del_title').textContent = 'Delivery #' + del.id + ' (' + del.supply_name + ')';
    document.getElementById('mdl_partner_id').value = del.delivery_partner_id || '';
    document.getElementById('mdl_del_status').value = del.delivery_status;
    document.getElementById('mdl_notes').value = del.tracking_notes || '';
    new bootstrap.Modal(document.getElementById('deliveryModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
