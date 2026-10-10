<?php
/**
 * MediCycle - Supplier Incoming Requests & Handover Management
 * Direct Healthcare Supplier <-> Recipient NGO/Clinic Handover Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];

// Handle REQUEST ACTIONS (Accept, Mark Ready, Complete Handover, Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch. Please try again.');
    } else {
        $action = clean($_POST['action'] ?? '');
        $requestId = (int)($_POST['request_id'] ?? 0);
        $remarks = clean($_POST['remarks'] ?? '');
        $enteredCode = strtoupper(trim(clean($_POST['handover_code'] ?? '')));

        try {
            // Verify that this request belongs to one of this supplier's supplies
            $reqStmt = $pdo->prepare("SELECT r.*, s.supplier_id, s.supply_name, s.quantity, s.unit, s.location, 
                                             u.id as requester_id, u.name as requester_name,
                                             o.organization_name as requester_org, o.address as ngo_addr, o.city as ngo_city, u.phone as ngo_phone
                                      FROM requests r 
                                      JOIN medical_supplies s ON r.supply_id = s.id 
                                      JOIN users u ON r.requester_id = u.id 
                                      LEFT JOIN organizations o ON u.id = o.user_id 
                                      WHERE r.id = ? AND s.supplier_id = ? LIMIT 1");
            $reqStmt->execute([$requestId, $userId]);
            $request = $reqStmt->fetch();

            if (!$request) {
                set_flash('danger', 'Request not found or access denied.');
            } else {
                $pdo->beginTransaction();

                if ($action === 'accept') {
                    if ($request['status'] !== 'Pending') {
                        throw new Exception("Only pending requests can be accepted.");
                    }

                    // Check inventory availability
                    if ($request['quantity'] < $request['requested_quantity']) {
                        throw new Exception("Cannot accept: Available inventory ({$request['quantity']}) is less than requested quantity ({$request['requested_quantity']}).");
                    }

                    // Generate secure Handover Verification Code
                    $handoverCode = generate_handover_code();

                    // 1. Update request status to 'Accepted'
                    $updReq = $pdo->prepare("UPDATE requests SET status = 'Accepted', handover_code = ?, supplier_remarks = ?, approved_at = NOW() WHERE id = ?");
                    $updReq->execute([$handoverCode, $remarks, $requestId]);

                    // 2. Decrement supply quantity
                    $remainingQty = $request['quantity'] - $request['requested_quantity'];
                    $newSupplyStatus = ($remainingQty == 0) ? 'Reserved' : 'Available';

                    $updSupply = $pdo->prepare("UPDATE medical_supplies SET quantity = ?, status = ?, updated_at = NOW() WHERE id = ?");
                    $updSupply->execute([$remainingQty, $newSupplyStatus, $request['supply_id']]);

                    // 3. Notify NGO
                    create_notification(
                        $pdo,
                        $request['requester_id'],
                        'Request Accepted by Supplier!',
                        "Your request for {$request['requested_quantity']} {$request['unit']} of {$request['supply_name']} was accepted. Handover Code: {$handoverCode}.",
                        'ngo/my-requests.php'
                    );

                    $pdo->commit();
                    set_flash('success', "Request accepted successfully! Requisition is approved and awaiting physical verification in Verify Handover.");

                } elseif ($action === 'mark_ready') {
                    if (!in_array($request['status'], ['Accepted', 'Pending'])) {
                        throw new Exception("Request cannot be marked ready in its current status.");
                    }

                    $handoverCode = $request['handover_code'] ?: generate_handover_code();

                    // Update to 'Ready for Handover'
                    $updReq = $pdo->prepare("UPDATE requests SET status = 'Ready for Handover', handover_code = ?, supplier_remarks = COALESCE(?, supplier_remarks), ready_at = NOW() WHERE id = ?");
                    $updReq->execute([$handoverCode, $remarks ?: null, $requestId]);

                    create_notification(
                        $pdo,
                        $request['requester_id'],
                        'Supplies Ready for Pickup!',
                        "Your requested {$request['supply_name']} is packaged and ready for collection. Show Handover Code: {$handoverCode} at pickup.",
                        'ngo/my-requests.php'
                    );

                    $pdo->commit();
                    set_flash('success', "Supplies are now marked Ready for Handover.");

                } elseif ($action === 'complete_handover') {
                    if (!in_array($request['status'], ['Accepted', 'Ready for Handover', 'Collected', 'Received'])) {
                        throw new Exception("Invalid status for completing handover.");
                    }

                    // Optional code validation if entered
                    if (!empty($enteredCode) && !empty($request['handover_code']) && $enteredCode !== strtoupper($request['handover_code'])) {
                        throw new Exception("Handover verification code does not match. Expected: {$request['handover_code']}");
                    }

                    // 1. Mark request Completed
                    $updReq = $pdo->prepare("UPDATE requests SET status = 'Completed', completed_at = NOW() WHERE id = ?");
                    $updReq->execute([$requestId]);

                    // 2. Record or update Impact Metrics
                    $qty = (int)$request['requested_quantity'];
                    $wasteAvoided = round($qty * 0.15, 2); // 150g per consumable average packaging diverted
                    $valueSaved = round($qty * 25.0, 2);   // Average savings estimate in INR

                    $checkImp = $pdo->prepare("SELECT id FROM impact_metrics WHERE request_id = ? LIMIT 1");
                    $checkImp->execute([$requestId]);
                    if ($checkImp->fetch()) {
                        $updImp = $pdo->prepare("UPDATE impact_metrics SET quantity_redistributed = ?, estimated_waste_avoided = ?, estimated_value_saved = ?, recorded_at = NOW() WHERE request_id = ?");
                        $updImp->execute([$qty, $wasteAvoided, $valueSaved, $requestId]);
                    } else {
                        $insImp = $pdo->prepare("INSERT INTO impact_metrics (request_id, recipient_id, quantity_redistributed, estimated_waste_avoided, estimated_value_saved, recorded_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $insImp->execute([$requestId, $request['requester_id'], $qty, $wasteAvoided, $valueSaved]);
                    }

                    // 3. Notify NGO
                    create_notification(
                        $pdo,
                        $request['requester_id'],
                        'Handover Confirmed & Completed!',
                        "Handover of {$qty} {$request['unit']} of {$request['supply_name']} has been completed and verified. Thank you for preventing medical waste!",
                        'ngo/history.php'
                    );

                    $pdo->commit();
                    set_flash('success', "Handover verified and completed successfully! Impact metrics updated.");

                } elseif ($action === 'reject') {
                    $updReq = $pdo->prepare("UPDATE requests SET status = 'Rejected', supplier_remarks = ? WHERE id = ?");
                    $updReq->execute([$remarks, $requestId]);

                    create_notification(
                        $pdo,
                        $request['requester_id'],
                        'Requisition Declined',
                        "Your request for {$request['supply_name']} could not be fulfilled. Note from supplier: " . ($remarks ?: 'Stock unavailable or capacity reached.'),
                        'ngo/my-requests.php'
                    );

                    $pdo->commit();
                    set_flash('info', "Requisition request declined.");
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Supplier request action error: " . $e->getMessage());
            set_flash('danger', $e->getMessage());
        }
        header('Location: ' . BASE_URL . '/supplier/requests.php');
        exit;
    }
}

// Fetch all requests for this supplier's supplies
$statusFilter = clean($_GET['status'] ?? '');

$sql = "SELECT r.*, s.supply_name, s.unit, s.quantity as available_qty, s.location as pickup_hub,
               u.name as requester_name, u.email as requester_email, u.phone as requester_phone,
               o.organization_name, o.city as ngo_city, o.organization_type
        FROM requests r 
        JOIN medical_supplies s ON r.supply_id = s.id 
        JOIN users u ON r.requester_id = u.id 
        LEFT JOIN organizations o ON u.id = o.user_id 
        WHERE s.supplier_id = ?";
$params = [$userId];

if (!empty($statusFilter)) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY r.requested_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$pageTitle = 'Incoming Requests & Handovers';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Incoming Requests & Direct Handovers</h3>
                <p class="text-muted small mb-0">Manage clinic requests, issue Handover Codes, and verify direct collections</p>
            </div>
            <!-- Status filter buttons with space between them -->
            <div class="d-flex flex-wrap gap-2">
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php" class="btn btn-sm rounded-pill px-3 shadow-xs <?php echo empty($statusFilter) ? 'btn-secondary text-white fw-semibold' : 'btn-outline-secondary bg-white'; ?>">All</a>
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php?status=Pending" class="btn btn-sm rounded-pill px-3 shadow-xs <?php echo $statusFilter === 'Pending' ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning text-dark bg-white'; ?>">Pending</a>
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php?status=Accepted" class="btn btn-sm rounded-pill px-3 shadow-xs <?php echo $statusFilter === 'Accepted' ? 'btn-info text-white fw-bold' : 'btn-outline-info bg-white'; ?>">Accepted</a>
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php?status=Ready for Handover" class="btn btn-sm rounded-pill px-3 shadow-xs <?php echo $statusFilter === 'Ready for Handover' ? 'btn-primary text-white fw-bold' : 'btn-outline-primary bg-white'; ?>">Ready</a>
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php?status=Completed" class="btn btn-sm rounded-pill px-3 shadow-xs <?php echo $statusFilter === 'Completed' ? 'btn-success text-white fw-bold' : 'btn-outline-success bg-white'; ?>">Completed</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Supply Item</th>
                                <th>Recipient Organization</th>
                                <th>Requested Qty</th>
                                <th>Urgency & Date</th>
                                <th>Handover Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($requests)): ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr id="req-<?php echo $r['id']; ?>">
                                        <td>
                                            <span class="fw-bold text-dark d-block"><?php echo e($r['supply_name']); ?></span>
                                            <span class="text-muted small">In Stock: <?php echo $r['available_qty'] . ' ' . e($r['unit']); ?></span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark d-block"><?php echo e($r['organization_name'] ?? $r['requester_name']); ?></span>
                                            <span class="text-muted small">
                                                <i class="fas fa-map-marker-alt me-1 text-danger"></i><?php echo e($r['ngo_city'] ?? 'City N/A'); ?> | 
                                                <i class="fas fa-phone me-1"></i><?php echo e($r['requester_phone']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold fs-6 text-teal"><?php echo number_format($r['requested_quantity']) . ' ' . e($r['unit']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo priority_badge($r['urgency'] ?? 'Medium'); ?>
                                            <?php if (!empty($r['preferred_collection_date'])): ?>
                                                <div class="small text-muted mt-1">
                                                    <i class="far fa-calendar-alt me-1"></i>Pickup: <?php echo format_date($r['preferred_collection_date']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo status_badge($r['status']); ?>
                                            <?php if (!empty($r['supplier_remarks'])): ?>
                                                <div class="text-muted small mt-1 text-truncate" style="max-width: 150px;" title="<?php echo e($r['supplier_remarks']); ?>">
                                                    <i class="fas fa-comment-dots me-1"></i><?php echo e($r['supplier_remarks']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3 text-nowrap">
                                            <?php if ($r['status'] === 'Pending'): ?>
                                                <div class="d-inline-flex align-items-center gap-2 justify-content-end">
                                                    <button type="button" class="btn btn-sm btn-success rounded-2 px-3 py-1 shadow-xs d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#acceptModal<?php echo $r['id']; ?>">
                                                        <i class="fas fa-check"></i> Accept
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-2 px-3 py-1 shadow-xs d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $r['id']; ?>">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </div>
                                            <?php elseif (in_array($r['status'], ['Accepted', 'Ready for Handover', 'In Transit', 'Collected'])): ?>
                                                <span class="badge rounded-pill bg-light text-secondary border px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" style="font-size:0.76rem;">
                                                    <i class="fas fa-handshake text-teal"></i> Verify at Handover
                                                </span>
                                            <?php else: ?>
                                                <?php if ($r['status'] === 'Completed'): ?>
                                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" style="font-size:0.76rem;">
                                                        <i class="fas fa-circle-check"></i> Fulfilled
                                                    </span>
                                                <?php elseif ($r['status'] === 'Rejected'): ?>
                                                    <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" style="font-size:0.76rem;">
                                                        <i class="fas fa-circle-xmark"></i> Declined
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-light text-secondary border px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" style="font-size:0.76rem;">
                                                        <i class="fas fa-check-circle text-muted"></i> No Action Needed
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- Accept Modal -->
                                    <div class="modal fade" id="acceptModal<?php echo $r['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg rounded-4">
                                                <form action="<?php echo BASE_URL; ?>/supplier/requests.php" method="POST">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="accept">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                    <div class="modal-header border-bottom py-3">
                                                        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                                            <span class="rounded-circle p-2 d-inline-flex" style="background:#ccfbf1; color:#0f766e;">
                                                                <i class="fas fa-check"></i>
                                                            </span>
                                                            Accept Requisition Request
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <p class="text-secondary small mb-3">
                                                            Accepting this request will allocate <strong><?php echo $r['requested_quantity'] . ' ' . e($r['unit']); ?></strong> of <strong><?php echo e($r['supply_name']); ?></strong> to <strong><?php echo e($r['organization_name'] ?? $r['requester_name']); ?></strong>. The recipient clinic will receive their secure verification code for pickup.
                                                        </p>
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-semibold">Pickup / Handover Instructions</label>
                                                            <textarea name="remarks" class="form-control" rows="3" placeholder="e.g. Ready for collection Mon-Fri 10am-4pm at Main Pharmacy Counter."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top py-3">
                                                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success rounded-pill px-4 shadow-xs">
                                                            <i class="fas fa-check-circle me-1"></i> Confirm & Accept Request
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectModal<?php echo $r['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow-lg rounded-4">
                                                <form action="<?php echo BASE_URL; ?>/supplier/requests.php" method="POST">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                    <div class="modal-header border-bottom py-3">
                                                        <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
                                                            <span class="rounded-circle p-2 d-inline-flex" style="background:#fee2e2; color:#ef4444;">
                                                                <i class="fas fa-times"></i>
                                                            </span>
                                                            Decline Requisition Request
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="mb-2">
                                                            <label class="form-label small fw-semibold">Reason for Declining *</label>
                                                            <textarea name="remarks" class="form-control" rows="3" placeholder="Stock reserved, clinic capacity mismatch, or other reason..." required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top py-3">
                                                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger rounded-pill px-4 shadow-xs">Decline Request</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-inbox fs-3 text-secondary d-block mb-2"></i>
                                        No requisition requests found matching your filter.
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
