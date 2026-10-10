<?php
/**
 * MediCycle - NGO Request Details & Handover Timeline
 * Direct Healthcare Supplier <-> Recipient NGO/Clinic Handover Model
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];
$requestId = (int)($_GET['id'] ?? 0);

// Handle Receipt Confirmation from details page as well
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_receipt') {
    set_flash('info', 'Secure Handover Protocol: For medical supply chain integrity, handovers must be authenticated by the supplier or courier verifying your 6-Digit Handshake PIN or QR Code.');
    header('Location: ' . BASE_URL . '/ngo/request-details.php?id=' . $requestId);
    exit;
}

$stmt = $pdo->prepare("SELECT r.*, s.supply_name, s.unit, s.batch_number, s.condition_status, s.expiry_date, s.storage_requirements,
                              o.organization_name as supplier_name, o.address as supplier_address, o.city as supplier_city,
                              u_supp.phone as supplier_phone, u_supp.email as supplier_email
                       FROM requests r 
                       JOIN medical_supplies s ON r.supply_id = s.id 
                       JOIN users u_supp ON s.supplier_id = u_supp.id 
                       LEFT JOIN organizations o ON u_supp.id = o.user_id 
                       WHERE r.id = ? AND r.requester_id = ? LIMIT 1");
$stmt->execute([$requestId, $userId]);
$req = $stmt->fetch();

if (!$req) {
    set_flash('danger', 'Request not found.');
    header('Location: ' . BASE_URL . '/ngo/my-requests.php');
    exit;
}

$pageTitle = 'Requisition Details - ' . $req['supply_name'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <a href="<?php echo BASE_URL; ?>/ngo/my-requests.php" class="text-decoration-none text-muted small">
                    <i class="fas fa-arrow-left me-1"></i> Back to Requests
                </a>
                <h3 class="fw-bold text-dark mt-1 mb-0">Requisition - <?php echo e($req['supply_name']); ?></h3>
            </div>
            <div>
                <?php echo status_badge($req['status']); ?>
            </div>
        </div>

        <!-- Handover Pass Card (Shown when accepted or ready) -->
        <?php if (!empty($req['handover_code'])): ?>
            <div class="card border-0 shadow-sm mb-4 p-4" style="background:#f0fdfa; border-left: 5px solid #0f766e !important;">
                <div class="row align-items-center gy-3">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-teal text-white px-2 py-1" style="background:#0f766e;">Handover Collection Pass</span>
                            <span class="text-muted small">Show this QR code or provide the 6-digit verification code to the courier or hospital</span>
                        </div>
                        
                        <div class="d-flex flex-wrap align-items-center gap-3 my-2">
                            <div class="bg-white p-2 rounded-3 border text-center shadow-2xs">
                                <img src="<?php echo e(get_handshake_qr_url($req['handover_code'], $req['id'])); ?>" alt="Handshake QR" class="img-fluid rounded" style="width:105px; height:105px;">
                                <div class="text-muted text-uppercase fw-bold mt-1" style="font-size:0.65rem;"><i class="fas fa-qrcode me-1 text-teal"></i>Scan Handshake</div>
                            </div>
                            <div>
                                <div class="handshake-code-box mb-2">
                                    <span class="text-muted small fw-semibold me-2">6-DIGIT CODE:</span>
                                    <span class="handshake-code-digit"><?php echo e($req['handover_code']); ?></span>
                                </div>
                                <p class="text-secondary small mb-0">
                                    <strong>Collection Location:</strong> <?php echo e($req['supplier_name']); ?>, <?php echo e($req['supplier_address']); ?>, <?php echo e($req['supplier_city']); ?> 
                                    (Contact: <?php echo e($req['supplier_phone']); ?>)
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <?php if (in_array($req['status'], ['Accepted', 'Ready for Handover', 'Collected', 'Approved'])): ?>
                            <div class="p-3 bg-white rounded-3 border text-start text-lg-end shadow-xs">
                                <span class="badge bg-info text-dark px-3 py-2 fs-6 mb-2 d-inline-block"><i class="fas fa-shield-alt me-1"></i> Handshake Required</span>
                                <p class="small text-muted mb-0" style="font-size:0.8rem;">
                                    Present this Pass or 6-digit PIN to the supplier/courier during pickup. Handover completes upon their verification.
                                </p>
                            </div>
                        <?php else: ?>
                            <span class="badge bg-success p-2 px-3 fs-6"><i class="fas fa-check-double me-1"></i> Handover Completed</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Column: Item & Donor Info -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-boxes-stacked text-teal me-2" style="color:#0f766e;"></i>Consumable Item Details</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 small">
                            <div class="col-sm-6">
                                <label class="text-muted d-block">Supply Item</label>
                                <span class="fw-bold text-dark fs-6"><?php echo e($req['supply_name']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted d-block">Quantity Requested</label>
                                <span class="fw-bold text-teal fs-6"><?php echo number_format($req['requested_quantity']) . ' ' . e(format_unit($req['unit'])); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted d-block">Supplying Donor</label>
                                <span class="fw-semibold text-dark"><?php echo e($req['supplier_name']); ?></span>
                                <div class="text-muted"><?php echo e($req['supplier_city']); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted d-block">Batch Expiry</label>
                                <span class="fw-medium text-dark"><?php echo format_date($req['expiry_date']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted d-block">Preferred Collection Date</label>
                                <span class="fw-semibold text-dark"><?php echo format_date($req['preferred_collection_date'] ?? $req['requested_at']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted d-block">Urgency Level</label>
                                <?php echo priority_badge($req['urgency'] ?? 'Medium'); ?>
                            </div>
                            <div class="col-12 border-top pt-2">
                                <label class="text-muted d-block">Clinical Outreach Purpose</label>
                                <span class="fw-medium text-dark"><?php echo e($req['purpose']); ?></span>
                                <?php if (!empty($req['message'])): ?>
                                    <div class="text-muted fst-italic mt-1">"<?php echo e($req['message']); ?>"</div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($req['supplier_remarks'])): ?>
                                <div class="col-12 bg-light p-3 rounded-3 border">
                                    <label class="text-muted d-block fw-semibold mb-1"><i class="fas fa-info-circle text-primary me-1"></i>Supplier Handover Instructions</label>
                                    <span class="text-dark"><?php echo e($req['supplier_remarks']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Donor Facility Contact Details -->
                <div class="card border-0 shadow-sm p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-hospital text-teal me-2" style="color:#0f766e;"></i>Donor Contact & Pickup Point</h6>
                    <div class="small text-secondary">
                        <div class="fw-bold text-dark fs-6"><?php echo e($req['supplier_name']); ?></div>
                        <div class="mt-1"><i class="fas fa-map-marker-alt text-muted me-1"></i><?php echo e($req['supplier_address'] . ', ' . $req['supplier_city']); ?></div>
                        <div class="mt-1"><i class="fas fa-phone text-muted me-1"></i><?php echo e($req['supplier_phone'] ?? 'N/A'); ?> | <i class="fas fa-envelope text-muted ms-2 me-1"></i><?php echo e($req['supplier_email']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Handover Milestone Timeline -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-stream text-teal me-2" style="color:#0f766e;"></i>Handover Lifecycle</h6>
                    
                    <ul class="list-unstyled position-relative mb-0">
                        <!-- Step 1: Requisition Submitted -->
                        <li class="d-flex gap-3 mb-4">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; flex-shrink: 0;">
                                <i class="fas fa-check small"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark small">1. Requisition Submitted</strong>
                                <span class="text-muted small"><?php echo format_datetime($req['requested_at']); ?></span>
                                <div class="text-secondary small mt-1">Requested by <?php echo e($_SESSION['org_name']); ?></div>
                            </div>
                        </li>

                        <!-- Step 2: Supplier Accepted -->
                        <li class="d-flex gap-3 mb-4">
                            <?php $isApproved = !empty($req['approved_at']) || in_array($req['status'], ['Accepted', 'Ready for Handover', 'Completed']); ?>
                            <div class="rounded-circle <?php echo $isApproved ? 'bg-success text-white' : 'bg-light text-muted border'; ?> d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; flex-shrink: 0;">
                                <i class="fas <?php echo $isApproved ? 'fa-check' : 'fa-hourglass-half'; ?> small"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark small">2. Accepted by Supplier</strong>
                                <?php if ($isApproved): ?>
                                    <span class="text-muted small"><?php echo !empty($req['approved_at']) ? format_datetime($req['approved_at']) : 'Accepted'; ?></span>
                                    <div class="text-secondary small mt-1">Handover code generated and reserved in inventory.</div>
                                <?php else: ?>
                                    <span class="text-muted small">Pending supplier review</span>
                                <?php endif; ?>
                            </div>
                        </li>

                        <!-- Step 3: Ready for Handover -->
                        <li class="d-flex gap-3 mb-4">
                            <?php $isReady = !empty($req['ready_at']) || in_array($req['status'], ['Ready for Handover', 'Completed']); ?>
                            <div class="rounded-circle <?php echo $isReady ? 'bg-success text-white' : 'bg-light text-muted border'; ?> d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; flex-shrink: 0;">
                                <i class="fas <?php echo $isReady ? 'fa-box-check' : 'fa-box'; ?> small"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark small">3. Packaged & Ready for Collection</strong>
                                <?php if ($isReady): ?>
                                    <span class="text-muted small"><?php echo !empty($req['ready_at']) ? format_datetime($req['ready_at']) : 'Ready for pickup'; ?></span>
                                    <div class="text-secondary small mt-1">Supplies prepared at collection counter.</div>
                                <?php else: ?>
                                    <span class="text-muted small">Awaiting packing confirmation</span>
                                <?php endif; ?>
                            </div>
                        </li>

                        <!-- Step 4: Collection Completed -->
                        <li class="d-flex gap-3">
                            <?php $isCompleted = ($req['status'] === 'Completed'); ?>
                            <div class="rounded-circle <?php echo $isCompleted ? 'bg-success text-white' : 'bg-light text-muted border'; ?> d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; flex-shrink: 0;">
                                <i class="fas <?php echo $isCompleted ? 'fa-flag-checkered' : 'fa-circle'; ?> small"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark small">4. Handover Verified & Completed</strong>
                                <?php if ($isCompleted): ?>
                                    <span class="text-muted small"><?php echo !empty($req['completed_at']) ? format_datetime($req['completed_at']) : 'Completed'; ?></span>
                                    <div class="text-success small mt-1"><i class="fas fa-seedling me-1"></i>Impact metrics officially recorded!</div>
                                <?php else: ?>
                                    <span class="text-muted small">Awaiting physical collection confirmation</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    </ul>

                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
