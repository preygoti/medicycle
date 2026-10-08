<?php
/**
 * MediCycle - Supplier Incoming Requests & Handover Management
 * Direct Supplier <-> NGO Redistribution Flow (Harvest Ledger Model)
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
                                             o.organization_name as requester_org, o.address as ngo_addr, o.city as ngo_city, o.phone as ngo_phone
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
                    set_flash('success', "Request #{$requestId} accepted! Handover Code <strong>{$handoverCode}</strong> issued for direct collection.");

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
                    set_flash('success', "Request #{$requestId} is now marked Ready for Handover.");

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
                    set_flash('info', "Request #{$requestId} declined.");
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
            <!-- Status filter buttons -->
            <div class="btn-group btn-group-sm">
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php" class="btn btn-outline-secondary <?php echo empty($statusFilter) ? 'active' : ''; ?>">All</a>
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php?status=Pending" class="btn btn-outline-warning <?php echo $statusFilter === 'Pending' ? 'active' : ''; ?>">Pending</a>
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php?status=Accepted" class="btn btn-outline-info <?php echo $statusFilter === 'Accepted' ? 'active' : ''; ?>">Accepted</a>
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php?status=Ready for Handover" class="btn btn-outline-primary <?php echo $statusFilter === 'Ready for Handover' ? 'active' : ''; ?>">Ready</a>
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php?status=Completed" class="btn btn-outline-success <?php echo $statusFilter === 'Completed' ? 'active' : ''; ?>">Completed</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Request ID</th>
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
                                            <span class="badge bg-light text-dark border">#REQ-<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?></span>
                                            <?php if (!empty($r['handover_code'])): ?>
                                                <div class="mt-1">
                                                    <span class="badge bg-dark font-monospace" title="Handover Verification Code">
                                                        <i class="fas fa-key me-1 text-warning"></i><?php echo e($r['handover_code']); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
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
                                        <td class="text-end">
                                            <?php if ($r['status'] === 'Pending'): ?>
                                                <button type="button" class="btn btn-sm btn-success px-2 py-1" data-bs-toggle="modal" data-bs-target="#acceptModal<?php echo $r['id']; ?>">
                                                    <i class="fas fa-check me-1"></i> Accept
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $r['id']; ?>">
                                                    <i class="fas fa-times me-1"></i> Reject
                                                </button>
                                            <?php elseif ($r['status'] === 'Accepted'): ?>
                                                <form action="<?php echo BASE_URL; ?>/supplier/requests.php" method="POST" class="d-inline">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="mark_ready">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-primary px-2 py-1">
                                                        <i class="fas fa-box-check me-1"></i> Mark Ready
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-sm btn-outline-success px-2 py-1" data-bs-toggle="modal" data-bs-target="#completeModal<?php echo $r['id']; ?>">
                                                    <i class="fas fa-check-double me-1"></i> Complete Handover
                                                </button>
                                            <?php elseif ($r['status'] === 'Ready for Handover' || $r['status'] === 'Collected' || $r['status'] === 'Received'): ?>
                                                <button type="button" class="btn btn-sm btn-success px-2 py-1" data-bs-toggle="modal" data-bs-target="#completeModal<?php echo $r['id']; ?>">
                                                    <i class="fas fa-handshake me-1"></i> Confirm Handover
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">No action needed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- Accept Modal -->
                                    <div class="modal fade" id="acceptModal<?php echo $r['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="<?php echo BASE_URL; ?>/supplier/requests.php" method="POST">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="accept">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Accept Request #REQ-<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="small text-muted mb-3">
                                                            Accepting this request will allocate <strong><?php echo $r['requested_quantity'] . ' ' . e($r['unit']); ?></strong> of <strong><?php echo e($r['supply_name']); ?></strong> to <strong><?php echo e($r['organization_name'] ?? $r['requester_name']); ?></strong> and generate a secure Handover Code.
                                                        </p>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Pickup / Handover Instructions</label>
                                                            <textarea name="remarks" class="form-control" rows="3" placeholder="e.g. Ready for collection Mon-Fri 10am-4pm at Main Pharmacy Counter."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="fas fa-check-circle me-1"></i> Confirm & Generate Handover Code
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Complete Handover Modal -->
                                    <div class="modal fade" id="completeModal<?php echo $r['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="<?php echo BASE_URL; ?>/supplier/requests.php" method="POST">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="complete_handover">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                    <div class="modal-header bg-success text-white">
                                                        <h5 class="modal-title fw-bold"><i class="fas fa-handshake me-2"></i>Verify & Complete Handover</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-light border mb-3">
                                                            <div class="small text-muted">Beneficiary NGO:</div>
                                                            <div class="fw-bold"><?php echo e($r['organization_name'] ?? $r['requester_name']); ?></div>
                                                            <div class="small text-muted mt-2">Expected Handover Code:</div>
                                                            <div class="fs-5 fw-bold font-monospace text-primary"><?php echo e($r['handover_code'] ?? 'None'); ?></div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Enter / Confirm Handover Code (Optional verification)</label>
                                                            <input type="text" name="handover_code" class="form-control font-monospace" placeholder="<?php echo e($r['handover_code'] ?? 'HAND-XXXXXX'); ?>" value="<?php echo e($r['handover_code'] ?? ''); ?>">
                                                            <div class="form-text small">Entering the code confirms physical collection by the NGO representative.</div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="fas fa-check-double me-1"></i> Confirm Handover & Update Impact
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectModal<?php echo $r['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="<?php echo BASE_URL; ?>/supplier/requests.php" method="POST">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold text-danger">Decline Request #REQ-<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-semibold">Reason for Declining *</label>
                                                            <textarea name="remarks" class="form-control" rows="3" placeholder="Stock reserved, clinic capacity mismatch, or other reason..." required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Decline Request</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
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
