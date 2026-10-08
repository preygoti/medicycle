<?php
/**
 * MediCycle - NGO My Requests (Direct Collection & Receipt Confirmation)
 * Harvest Ledger Model: Direct Supplier <-> NGO Redistribution
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];

// Handle CONFIRM RECEIPT / COLLECTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_receipt') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security token mismatch. Please try again.');
    } else {
        $requestId = (int)($_POST['request_id'] ?? 0);
        try {
            // Verify request ownership and that it is in an active handover status
            $reqStmt = $pdo->prepare("SELECT r.*, s.supplier_id, s.supply_name, s.unit, o.organization_name as supplier_org 
                                      FROM requests r 
                                      JOIN medical_supplies s ON r.supply_id = s.id 
                                      LEFT JOIN organizations o ON s.supplier_id = o.user_id 
                                      WHERE r.id = ? AND r.requester_id = ? AND r.status != 'Completed' LIMIT 1");
            $reqStmt->execute([$requestId, $userId]);
            $request = $reqStmt->fetch();

            if ($request) {
                $pdo->beginTransaction();

                // 1. Mark request as Completed
                $updReq = $pdo->prepare("UPDATE requests SET status = 'Completed', completed_at = NOW() WHERE id = ?");
                $updReq->execute([$requestId]);

                // 2. Log into impact metrics table
                record_impact($pdo, $requestId, (int)$request['requested_quantity']);

                // 3. Notify supplying hospital
                create_notification(
                    $pdo,
                    $request['supplier_id'],
                    'Collection Confirmed by Clinic',
                    "{$_SESSION['org_name']} has confirmed receipt & physical collection of {$request['requested_quantity']} {$request['unit']} of {$request['supply_name']}. Impact recorded!",
                    'supplier/history.php'
                );

                $pdo->commit();
                set_flash('success', "Collection confirmed for {$request['supply_name']}! Thank you for verifying receipt. Ecological and community impact metrics recorded.");
            } else {
                set_flash('danger', 'Request not found or already marked completed.');
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Confirm receipt error: " . $e->getMessage());
            set_flash('danger', 'Error updating handover receipt: ' . $e->getMessage());
        }
        header('Location: ' . BASE_URL . '/ngo/my-requests.php');
        exit;
    }
}

// Fetch all requests for this NGO
$stmt = $pdo->prepare("SELECT r.*, s.supply_name, s.unit, s.location as supply_location, 
                              o.organization_name as supplier_name, o.address as supplier_address, o.city as supplier_city, o.phone as supplier_phone
                       FROM requests r 
                       JOIN medical_supplies s ON r.supply_id = s.id 
                       JOIN users u_supp ON s.supplier_id = u_supp.id 
                       LEFT JOIN organizations o ON u_supp.id = o.user_id 
                       WHERE r.requester_id = ? 
                       ORDER BY r.requested_at DESC");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll();

$pageTitle = 'My Supply Requisitions';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">My Collection Requests</h3>
                <p class="text-muted small mb-0">Track donor approvals, manage Handover Codes, and verify direct supply pickups</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="btn btn-primary">
                <i class="fas fa-search me-1"></i> Discover Supplies
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item Requested</th>
                                <th>Supplying Donor</th>
                                <th>Quantity</th>
                                <th>Pickup Date & Code</th>
                                <th>Status</th>
                                <th>Pickup Instructions</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($requests)): ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/ngo/request-details.php?id=<?php echo $r['id']; ?>" class="fw-bold text-dark text-decoration-none">
                                                <?php echo e($r['supply_name']); ?>
                                            </a>
                                            <div class="text-muted small"><?php echo e($r['purpose']); ?></div>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark d-block"><?php echo e($r['supplier_name']); ?></span>
                                            <span class="text-muted small"><i class="fas fa-map-marker-alt me-1 text-danger"></i><?php echo e($r['supplier_city'] ?? $r['supply_location']); ?></span>
                                        </td>
                                        <td class="fw-bold text-teal"><?php echo number_format($r['requested_quantity']) . ' ' . e($r['unit']); ?></td>
                                        <td>
                                            <div class="small text-muted mb-1">
                                                <i class="far fa-calendar-alt me-1"></i><?php echo format_date($r['preferred_collection_date'] ?? $r['requested_at']); ?>
                                            </div>
                                            <?php if (!empty($r['handover_code'])): ?>
                                                <span class="badge bg-dark font-monospace" title="Present this code at pickup">
                                                    <i class="fas fa-key text-warning me-1"></i><?php echo e($r['handover_code']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Code on Approval</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo status_badge($r['status']); ?></td>
                                        <td>
                                            <?php if (!empty($r['supplier_remarks'])): ?>
                                                <span class="text-dark small d-block" style="max-width: 200px;">
                                                    <i class="fas fa-comment-dots text-primary me-1"></i><?php echo e($r['supplier_remarks']); ?>
                                                </span>
                                            <?php elseif ($r['status'] === 'Pending'): ?>
                                                <span class="text-muted small fst-italic">Awaiting supplier review</span>
                                            <?php else: ?>
                                                <span class="text-muted small">Standard facility pickup</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/ngo/request-details.php?id=<?php echo $r['id']; ?>" class="btn btn-outline-secondary" title="View Details">
                                                    <i class="fas fa-eye me-1"></i> Details
                                                </a>
                                                <?php if (in_array($r['status'], ['Accepted', 'Ready for Handover', 'Collected', 'Received'])): ?>
                                                    <?php if ($r['status'] !== 'Completed'): ?>
                                                        <form action="<?php echo BASE_URL; ?>/ngo/my-requests.php" method="POST" class="d-inline" onsubmit="return confirm('Confirm receipt of these supplies at your facility?');">
                                                            <?php echo csrf_field(); ?>
                                                            <input type="hidden" name="action" value="confirm_receipt">
                                                            <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                            <button type="submit" class="btn btn-success" title="Confirm Handover Received">
                                                                <i class="fas fa-check-double me-1"></i> Confirm Received
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-hand-holding-medical fs-2 text-secondary mb-2 d-block"></i>
                                        You haven't submitted any supply requisitions yet.<br>
                                        <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="btn btn-sm btn-primary mt-3">Find Available Supplies</a>
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
