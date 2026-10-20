<?php
/**
 * MediCycle - NGO My Requests (Direct Collection & Receipt Confirmation)
 * Direct Healthcare Supplier <-> Recipient NGO/Clinic Redistribution Model
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];

// Direct receipt confirmation disabled: Handovers MUST be verified through secure Handshake PIN or QR scanning by supplier/courier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_receipt') {
    set_flash('info', 'Secure Handover Protocol: For medical supply chain accountability, handovers must be authenticated by the supplier or courier verifying your 6-Digit Handshake PIN or QR Code.');
    header('Location: ' . BASE_URL . '/ngo/my-requests.php');
    exit;
}

// Fetch all requests for this NGO
$stmt = $pdo->prepare("SELECT r.*, s.supply_name, s.unit, s.location as supply_location, 
                              u_supp.phone as supplier_phone,
                              o.organization_name as supplier_name, o.address as supplier_address, o.city as supplier_city
                       FROM requests r 
                       JOIN medical_supplies s ON r.supply_id = s.id 
                       JOIN users u_supp ON s.supplier_id = u_supp.id 
                       LEFT JOIN organizations o ON u_supp.id = o.user_id 
                       WHERE r.requester_id = ? 
                         AND (r.purpose NOT LIKE 'Fulfilled Clinical Requirement%' OR r.purpose IS NULL)
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
                                <th class="ps-4">Item Requested</th>
                                <th>Supplying Donor</th>
                                <th>Quantity</th>
                                <th>Pickup Date & Code</th>
                                <th>Status</th>
                                <th>Pickup Instructions</th>
                                <th class="text-start pe-4 text-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($requests)): ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <a href="<?php echo BASE_URL; ?>/ngo/request-details.php?id=<?php echo $r['id']; ?>" class="fw-bold text-dark text-decoration-none">
                                                <?php echo e($r['supply_name']); ?>
                                            </a>
                                            <div class="text-muted small"><?php echo e($r['purpose']); ?></div>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark d-block"><?php echo e($r['supplier_name']); ?></span>
                                            <span class="text-muted small"><i class="fas fa-map-marker-alt me-1 text-danger"></i><?php echo e($r['supplier_city'] ?? $r['supply_location']); ?></span>
                                        </td>
                                        <td class="fw-bold text-teal text-nowrap"><?php echo number_format($r['requested_quantity']) . ' ' . e(format_unit($r['unit'])); ?></td>
                                        <td class="text-nowrap">
                                            <div class="small text-muted mb-1">
                                                <i class="far fa-calendar-alt me-1"></i><?php echo format_date($r['preferred_collection_date'] ?? $r['requested_at']); ?>
                                            </div>
                                            <?php if (!empty($r['handover_code'])): ?>
                                                <button type="button" class="btn btn-xs btn-dark py-1 px-2 font-monospace shadow-2xs text-nowrap" data-bs-toggle="modal" data-bs-target="#qrModal<?php echo $r['id']; ?>" title="Click to view QR & 6-Digit Pass">
                                                    <i class="fas fa-qrcode text-warning me-1"></i><?php echo e($r['handover_code']); ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Code on Approval</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap"><?php echo status_badge($r['status']); ?></td>
                                        <td>
                                            <?php if (!empty($r['supplier_remarks'])): ?>
                                                <span class="text-dark small d-block" style="max-width: 180px;">
                                                    <i class="fas fa-comment-dots text-primary me-1"></i><?php echo e($r['supplier_remarks']); ?>
                                                </span>
                                            <?php elseif ($r['status'] === 'Pending'): ?>
                                                <span class="text-muted small fst-italic">Awaiting supplier review</span>
                                            <?php else: ?>
                                                <span class="text-muted small">Standard facility pickup</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-start pe-4 text-nowrap">
                                            <div class="d-inline-flex justify-content-start align-items-center gap-2 flex-nowrap">
                                                <a href="<?php echo BASE_URL; ?>/ngo/request-details.php?id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-secondary text-nowrap d-inline-flex align-items-center gap-1 px-2.5" style="height:32px; font-size:0.78rem; white-space:nowrap !important;" title="View Details">
                                                    <i class="fas fa-eye"></i> <span>Details</span>
                                                </a>
                                                <?php if (!empty($r['handover_code']) && in_array($r['status'], ['Approved', 'Accepted', 'Ready for Handover'])): ?>
                                                    <button type="button" class="btn btn-sm btn-teal text-white shadow-2xs fw-semibold text-nowrap d-inline-flex align-items-center gap-1 px-3" style="background:#0f766e; height:32px; font-size:0.78rem; white-space:nowrap !important;" data-bs-toggle="modal" data-bs-target="#qrModal<?php echo $r['id']; ?>" title="Show Pickup Handshake Pass & QR">
                                                        <i class="fas fa-qrcode"></i> <span>Show Pass</span>
                                                    </button>
                                                <?php elseif ($r['status'] === 'Completed'): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1.5 text-nowrap d-inline-flex align-items-center gap-1" style="height:32px; font-size:0.76rem; white-space:nowrap !important;"><i class="fas fa-check-double"></i> Received</span>
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

        <!-- Pickup Verification Pass Modals (Placed cleanly outside table for valid DOM & live sync) -->
        <?php if (!empty($requests)): ?>
            <?php foreach ($requests as $r): ?>
                <?php if (!empty($r['handover_code'])): ?>
                    <div class="modal fade" id="qrModal<?php echo $r['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content border-0 shadow-lg rounded-4">
                                <div class="modal-header border-bottom py-3">
                                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                        <span class="rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="background:#ccfbf1; color:#0f766e; width:36px; height:36px;">
                                            <i class="fas fa-qrcode"></i>
                                        </span>
                                        Pickup Verification Pass
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-3 p-md-4 text-start">
                                    <?php 
                                    $passDetails = [
                                        'item' => $r['supply_name'],
                                        'quantity' => number_format($r['requested_quantity']) . ' ' . format_unit($r['unit']),
                                        'location' => $r['supplier_city'] ?? $r['supply_location'] ?? ''
                                    ];
                                    echo render_handshake_pass_html($r['handover_code'], $r['id'], 'Donor: ' . ($r['supplier_name'] ?? 'Medical Facility'), $passDetails); 
                                    ?>
                                </div>
                                <div class="modal-footer border-top py-2">
                                    <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
