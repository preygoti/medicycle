<?php
/**
 * MediCycle - Courier Consignment Status Update
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('delivery');

$userId = $_SESSION['user_id'];
$deliveryId = (int)($_GET['id'] ?? $_POST['delivery_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT d.*, r.requested_quantity, ms.supply_name, ms.unit,
           COALESCE(so.organization_name, su.name) as supplier_name,
           COALESCE(no.organization_name, nu.name) as ngo_name
    FROM deliveries d
    JOIN requests r ON d.request_id = r.id
    JOIN medical_supplies ms ON r.supply_id = ms.id
    JOIN users su ON ms.supplier_id = su.id
    LEFT JOIN organizations so ON su.id = so.user_id
    JOIN users nu ON r.requester_id = nu.id
    LEFT JOIN organizations no ON nu.id = no.user_id
    WHERE d.id = ? AND (d.delivery_partner_id = ? OR d.delivery_partner_id IS NULL)
    LIMIT 1
");
$stmt->execute([$deliveryId, $userId]);
$delivery = $stmt->fetch();

if (!$delivery) {
    set_flash('danger', 'Consignment not found.');
    header('Location: ' . BASE_URL . '/delivery/assigned.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch.');
    } else {
        $newStatus = clean($_POST['delivery_status'] ?? '');
        $notes = clean($_POST['tracking_notes'] ?? '');

        $allowedStatuses = ['Accepted', 'Picked Up', 'In Transit', 'Delivered'];
        if (!in_array($newStatus, $allowedStatuses)) {
            set_flash('danger', 'Invalid status selected.');
        } else {
            try {
                $pdo->beginTransaction();

                $pickupStatus = in_array($newStatus, ['Picked Up', 'In Transit', 'Delivered']) ? 'Picked Up' : 'Pending';

                $noteEntry = "[" . date('d M Y, h:i A') . "] Status updated to: " . $newStatus;
                if (!empty($notes)) {
                    $noteEntry .= " - Notes: " . $notes;
                }

                $upd = $pdo->prepare("UPDATE deliveries SET 
                                        delivery_partner_id = ?,
                                        delivery_status = ?,
                                        pickup_status = ?,
                                        tracking_notes = CONCAT(COALESCE(tracking_notes, ''), '\n', ?),
                                        picked_up_at = IF(? = 'Picked Up' AND picked_up_at IS NULL, NOW(), picked_up_at),
                                        delivered_at = IF(? = 'Delivered' AND delivered_at IS NULL, NOW(), delivered_at),
                                        updated_at = NOW()
                                      WHERE id = ?");
                $upd->execute([$userId, $newStatus, $pickupStatus, $noteEntry, $newStatus, $newStatus, $deliveryId]);

                // Notifications
                $reqStmt = $pdo->prepare("SELECT r.id, r.requester_id, r.requested_quantity, ms.supplier_id, ms.supply_name 
                                          FROM requests r 
                                          JOIN medical_supplies ms ON r.supply_id = ms.id 
                                          WHERE r.id = ?");
                $reqStmt->execute([$delivery['request_id']]);
                $req = $reqStmt->fetch();

                if ($newStatus === 'Delivered') {
                    // Consignment Completed: Update request status and record impact
                    $pdo->prepare("UPDATE requests SET status = 'Completed', completed_at = NOW() WHERE id = ?")->execute([$delivery['request_id']]);
                    record_impact($pdo, $delivery['request_id'], $req['requested_quantity']);

                    create_notification($pdo, $req['requester_id'], 'Consignment Successfully Delivered!', "Courier confirmed delivery of {$req['supply_name']} at your facility.", 'ngo/my-requests.php');
                    create_notification($pdo, $req['supplier_id'], 'Transfer Completed!', "Medical supplies for {$req['supply_name']} have reached recipient clinic.", 'supplier/history.php');
                } elseif ($newStatus === 'Picked Up' || $newStatus === 'In Transit') {
                    create_notification($pdo, $req['requester_id'], 'Consignment In Transit', "Courier picked up {$req['supply_name']} from hospital and is en route.", 'ngo/my-requests.php');
                }

                $pdo->commit();
                set_flash('success', "Consignment #DEL-{$deliveryId} status updated to <strong>{$newStatus}</strong>!");
                
                if ($newStatus === 'Delivered') {
                    header('Location: ' . BASE_URL . '/delivery/history.php');
                } else {
                    header('Location: ' . BASE_URL . '/delivery/assigned.php');
                }
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash('danger', 'Error updating status: ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Update Transit Status #DEL-' . $delivery['id'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="<?php echo BASE_URL; ?>/delivery/assigned.php" class="text-decoration-none text-muted small">
                    <i class="fas fa-arrow-left me-1"></i> Back to Deliveries
                </a>
                <h3 class="fw-bold text-dark mt-1 mb-0">Update Transit Status</h3>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fas fa-truck-ramp-box text-teal me-2"></i> Update Consignment #DEL-<?php echo $delivery['id']; ?>
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Medical Item</span>
                                    <strong class="text-dark"><?php echo e($delivery['supply_name']); ?> (<?php echo $delivery['requested_quantity'] . ' ' . e($delivery['unit']); ?>)</strong>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Current Transit Status</span>
                                    <?php echo status_badge($delivery['delivery_status']); ?>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Donor Facility</span>
                                    <span class="text-secondary small"><?php echo e($delivery['supplier_name']); ?></span>
                                </div>
                                <div class="col-sm-6">
                                    <span class="text-muted small d-block">Recipient Clinic</span>
                                    <span class="text-secondary small"><?php echo e($delivery['ngo_name']); ?></span>
                                </div>
                            </div>
                        </div>

                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">

                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Select New Transit Stage</label>
                                <select name="delivery_status" class="form-select" required>
                                    <option value="Accepted" <?php echo $delivery['delivery_status'] === 'Accepted' ? 'selected' : ''; ?>>
                                        1. Accepted &mdash; Job confirmed, heading to supplier facility
                                    </option>
                                    <option value="Picked Up" <?php echo $delivery['delivery_status'] === 'Picked Up' ? 'selected' : ''; ?>>
                                        2. Picked Up &mdash; Package verified and loaded onto transport
                                    </option>
                                    <option value="In Transit" <?php echo $delivery['delivery_status'] === 'In Transit' ? 'selected' : ''; ?>>
                                        3. In Transit &mdash; En route on highway / courier dispatch
                                    </option>
                                    <option value="Delivered" <?php echo $delivery['delivery_status'] === 'Delivered' ? 'selected' : ''; ?>>
                                        4. Delivered &mdash; Successfully received & signed at clinic
                                    </option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Dispatch Checkpoint Notes</label>
                                <textarea name="tracking_notes" rows="3" class="form-control" placeholder="e.g. Checked packaging seals, vehicle registration GJ-01-AB-1234, handed over to recipient nurse Dr. Patel..."></textarea>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <a href="<?php echo BASE_URL; ?>/delivery/assigned.php" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-check-circle me-1"></i> Confirm Status Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
