<?php
/**
 * MediCycle - Universal Handover Verification View & Engine
 * Supports Camera QR Scanning (html5-qrcode), Image QR File Upload, and 6-Digit PIN lookup.
 * Enforces strict single-use code security, clear error states on invalid codes, and instant delivery completion on match.
 */
$currentUser = current_user();
$role = $currentUser['role'] ?? 'supplier';
$userId = $currentUser['id'];

// Handle AJAX verification or lookup requests
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json') && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    header('Content-Type: application/json');
    $rawPayload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $rawPayload['action'] ?? '';
    $code = clean($rawPayload['code'] ?? $rawPayload['handover_code'] ?? '');

    if ($action === 'verify_and_complete' || $action === 'complete') {
        $res = verify_and_complete_handover($pdo, $code, $currentUser);
        echo json_encode($res);
        exit;
    } elseif ($action === 'lookup') {
        $res = lookup_handover_consignment($pdo, $code, $currentUser);
        echo json_encode($res);
        exit;
    }
}

// Standard Form POST Handling
$lookedUpConsignment = null;
$searchedCode = clean($_GET['code'] ?? $_POST['handover_code'] ?? '');
$errorMessage = null;
$securityAlert = null;
$successMessage = null;
$isNewlyCompleted = false;
$isAlreadyCompleted = false;
$isWrongCode = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch.');
    } else {
        $action = $_POST['action'];
        $searchedCode = clean($_POST['handover_code'] ?? '');

        if ($action === 'verify_and_complete' || $action === 'complete') {
            $res = verify_and_complete_handover($pdo, $searchedCode, $currentUser);
            if ($res['success']) {
                $successMessage = $res['message'];
                $lookedUpConsignment = $res['consignment'];
                $isNewlyCompleted = true;
            } else {
                if (($res['error_type'] ?? '') === 'already_completed') {
                    $securityAlert = $res['error'];
                    $lookedUpConsignment = $res['consignment'] ?? null;
                    $isAlreadyCompleted = true;
                } elseif (($res['error_type'] ?? '') === 'wrong_code') {
                    $errorMessage = $res['error'];
                    $isWrongCode = true;
                } else {
                    $errorMessage = $res['error'];
                }
            }
        } elseif ($action === 'lookup') {
            $res = lookup_handover_consignment($pdo, $searchedCode, $currentUser);
            if ($res['success']) {
                $lookedUpConsignment = $res['consignment'];
                if ($res['already_completed']) {
                    $isAlreadyCompleted = true;
                    $securityAlert = "Handshake Code Already Redeemed! This consignment was completed on " . format_datetime($lookedUpConsignment['completed_at']) . ". Handshake codes are strictly single-use and cannot be reused.";
                }
            } else {
                $errorMessage = $res['error'];
                $isWrongCode = true;
            }
        }
    }
} elseif (!empty($searchedCode)) {
    $res = lookup_handover_consignment($pdo, $searchedCode, $currentUser);
    if ($res['success']) {
        $lookedUpConsignment = $res['consignment'];
        if ($res['already_completed']) {
            $isAlreadyCompleted = true;
            if (empty($_GET['verified_success'])) {
                $securityAlert = "Handshake Code Already Redeemed! This consignment ({$lookedUpConsignment['supply_name']}) was completed on " . format_datetime($lookedUpConsignment['completed_at']) . ". Handshake codes are strictly single-use.";
            }
        }
        if (!empty($_GET['verified_success'])) {
            $successMessage = "Delivery Successfully Completed! Handshake verified for {$lookedUpConsignment['requested_quantity']} {$lookedUpConsignment['unit']} of {$lookedUpConsignment['supply_name']}.";
            $isNewlyCompleted = true;
        }
    } else {
        $errorMessage = $res['error'];
        $isWrongCode = true;
    }
}

// Fetch pending consignments awaiting handover for this user
$pendingSql = "SELECT r.*, ms.supply_name, ms.unit, ms.batch_number, ms.expiry_date,
                      su.name AS supplier_name, so.organization_name AS supplier_org, so.city AS supplier_city,
                      nu.name AS requester_name, no.organization_name AS requester_org, no.city AS requester_city
               FROM requests r
               JOIN medical_supplies ms ON r.supply_id = ms.id
               JOIN users su ON ms.supplier_id = su.id
               LEFT JOIN organizations so ON su.id = so.user_id
               JOIN users nu ON r.requester_id = nu.id
               LEFT JOIN organizations no ON nu.id = no.user_id
               WHERE r.status IN ('Approved', 'Ready for Handover', 'In Transit', 'Accepted')";

if ($role === 'supplier') {
    $pendingSql .= " AND ms.supplier_id = {$userId}";
} elseif ($role === 'ngo') {
    $pendingSql .= " AND r.requester_id = {$userId}";
} elseif ($role === 'delivery') {
    $pendingSql .= " AND r.id IN (SELECT request_id FROM deliveries WHERE delivery_partner_id = {$userId})";
}
$pendingSql .= " ORDER BY r.approved_at DESC LIMIT 6";

$pendingHandovers = $pdo->query($pendingSql)->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="app-container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <!-- Wrong Code Error Alert -->
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger shadow-sm border-0 rounded-4 mb-4 d-flex align-items-center gap-3 p-3" style="background:#fef2f2; border-left: 5px solid #ef4444 !important;">
                <div class="rounded-circle p-2 bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px; height:44px;">
                    <i class="fas fa-times-circle fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-1 text-danger">Verification Failed: Code is Incorrect</h6>
                    <div class="text-dark small"><?php echo e($errorMessage); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Code Already Used Warning Alert (Single Use Protection) -->
        <?php if (!empty($securityAlert)): ?>
            <div class="alert alert-warning shadow-sm border-0 rounded-4 mb-4 d-flex align-items-center gap-3 p-3" style="background:#fffbeb; border-left: 5px solid #f59e0b !important;">
                <div class="rounded-circle p-2 bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px; height:44px;">
                    <i class="fas fa-shield-halved fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-1 text-dark">Security Notice: Handshake Code Already Used</h6>
                    <div class="text-secondary small"><?php echo e($securityAlert); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Handover Completed Success Alert -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success shadow-sm border-0 rounded-4 mb-4 d-flex align-items-center gap-3 p-3" style="background:#ecfdf5; border-left: 5px solid #10b981 !important;">
                <div class="rounded-circle p-2 bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px; height:44px;">
                    <i class="fas fa-check-circle fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="fw-bold mb-1 text-success">Delivery Successfully Completed!</h6>
                    <div class="text-dark small"><?php echo e($successMessage); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle p-2 bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background:#fef3c7; color:#d97706;">
                        <i class="fas fa-qrcode fs-5"></i>
                    </span>
                    <div>
                        <h3 class="fw-bold text-dark mb-0">Handover Verification Terminal</h3>
                        <p class="text-muted small mb-0">Scan QR pass or enter 6-digit handshake code to verify and complete physical delivery</p>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 mt-2 mt-md-0">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 d-flex align-items-center gap-1">
                    <i class="fas fa-shield-alt"></i> One-Time Encrypted Handshake
                </span>
            </div>
        </div>

        <!-- Verification Input Section (Tabs: Live Camera / File QR & 6-Digit Code) -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <!-- Method 1: Camera Scanner & Image File QR -->
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-camera text-teal fs-5"></i>
                            <h6 class="fw-bold text-dark mb-0">Scan QR Code (Camera or File)</h6>
                        </div>
                        <span class="badge bg-light text-secondary border">Real-Time</span>
                    </div>
                    <div class="card-body p-3 text-center d-flex flex-column justify-content-between">
                        <!-- Camera Viewport Box with HUD -->
                        <div class="scanner-viewport-hud mb-2 position-relative" style="height: 255px; min-height: 255px; width: 100%; position: relative; overflow: hidden; background: #090d16; border-radius: 14px;">
                            <!-- html5-qrcode target region -->
                            <div id="qr-reader" style="width: 100%; height: 100%; position: absolute; top: 0; left: 0;"></div>

                            <!-- Centered Viewfinder HUD Target Reticle (Active when camera scans) -->
                            <div id="scanner-hud-overlay" class="hud-target-frame d-none">
                                <div class="hud-corner top-left"></div>
                                <div class="hud-corner top-right"></div>
                                <div class="hud-corner bottom-left"></div>
                                <div class="hud-corner bottom-right"></div>
                                <div class="laser-scan-bar"></div>
                            </div>

                            <!-- Inactive State Placeholder Overlay -->
                            <div id="qr-reader-placeholder" class="scanner-placeholder-overlay" style="width: 100%; height: 100%; position: absolute; top: 0; left: 0; z-index: 8; background: radial-gradient(circle at center, #111827 0%, #090d16 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.25rem; text-align: center; color: #ffffff;">
                                <div class="rounded-circle p-2 mb-2 d-inline-flex align-items-center justify-content-center" style="background: rgba(45, 212, 191, 0.12); border: 1.5px solid rgba(45, 212, 191, 0.35); width: 48px; height: 48px;">
                                    <i class="fas fa-qrcode fs-4" style="color: #2dd4bf !important;"></i>
                                </div>
                                <h6 class="fw-bold text-white mb-1" style="font-size: 0.95rem;">Live QR Scanner</h6>
                                <p class="small mb-3 px-2" style="font-size: 0.78rem; max-width: 280px; color: #94a3b8 !important; line-height: 1.4;">
                                    Point camera at the recipient's Handover Pass QR to verify instantly
                                </p>
                                <button type="button" id="btn-start-scanner" class="btn btn-teal text-white btn-sm px-4 py-2 shadow-sm rounded-pill fw-semibold d-inline-flex align-items-center gap-2" style="background: #0f766e; font-size: 0.82rem;">
                                    <i class="fas fa-camera"></i> Start Camera Scanner
                                </button>
                            </div>
                        </div>

                        <!-- Scanner Controls -->
                        <div>
                            <div class="d-flex flex-wrap justify-content-center gap-2 mb-2">
                                <button type="button" id="btn-stop-scanner" class="btn btn-outline-danger btn-sm py-1 px-3 d-none rounded-pill" style="font-size:0.75rem;">
                                    <i class="fas fa-stop me-1"></i> Stop Camera
                                </button>
                                <label class="btn btn-outline-secondary btn-sm py-1 px-3 mb-0 rounded-pill" style="font-size:0.75rem; cursor: pointer;">
                                    <i class="fas fa-file-image me-1"></i> Upload QR Image
                                    <input type="file" id="qr-input-file" accept="image/*" class="d-none">
                                </label>
                            </div>
                            <div id="qr-scan-status" class="text-muted" style="font-size:0.75rem;">Position QR code inside the frame to verify automatically</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- Method 2: Manual 6-Digit Code Input -->
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-keypad text-warning fs-5"></i>
                            <h6 class="fw-bold text-dark mb-0">Enter 6-Digit Handshake Code</h6>
                        </div>
                        <span class="badge bg-light text-secondary border">Manual Pass</span>
                    </div>
                    <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <form action="" method="POST" id="code-lookup-form" class="w-100">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="verify_and_complete">
                            
                            <label class="form-label text-muted text-uppercase mb-2 text-center d-block fw-semibold" style="letter-spacing:0.05em; font-size:0.75rem;">
                                6-Digit Verification PIN
                            </label>
                            
                            <!-- 6 Digit PIN Boxes -->
                            <div class="d-flex justify-content-center gap-2 mb-2">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                    <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                                           class="form-control text-center fw-bold otp-pin-input <?php echo $isWrongCode ? 'is-invalid' : ''; ?>" 
                                           data-index="<?php echo $i; ?>" autocomplete="off">
                                <?php endfor; ?>
                            </div>

                            <input type="hidden" name="handover_code" id="hidden_handover_code" value="<?php echo e($searchedCode); ?>">

                            <p class="text-muted text-center mb-3" style="font-size:0.78rem;">
                                Enter the 6 numbers shown on the recipient's MediCycle Handover Pass
                            </p>

                            <div class="d-grid gap-2">
                                <button type="submit" id="btn-submit-pin" class="btn btn-teal text-white w-100 py-2 fw-semibold rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2" style="background:#0f766e; font-size:0.875rem;">
                                    <i class="fas fa-check-circle"></i> Verify & Complete Delivery
                                </button>
                            </div>
                        </form>

                        <div class="bg-light p-2 rounded-3 small mt-3 border" style="font-size:0.75rem;">
                            <div class="d-flex align-items-start gap-2 text-muted">
                                <i class="fas fa-shield-alt text-teal mt-1"></i>
                                <span><strong>Single-Use Security:</strong> Once verified, this 6-digit PIN cannot be reused. Physical transfer is marked completed immediately upon verification.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Consignment Details Card (Shown when code is searched/scanned/verified) -->
        <?php if (!empty($lookedUpConsignment)): ?>
            <?php 
                $c = $lookedUpConsignment; 
                $isReady = in_array($c['status'], ['Approved', 'Ready for Handover', 'In Transit', 'Accepted', 'Collected']);
                $isCompleted = ($c['status'] === 'Completed');
            ?>
            <div class="card border-0 shadow rounded-4 mb-4 overflow-hidden" id="consignment-result-card" style="border-top: 5px solid <?php echo $isCompleted ? '#10b981' : '#0f766e'; ?> !important;">
                <div class="card-header bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-teal-subtle text-teal border px-2 py-1" style="background:#ccfbf1; color:#0f766e;">
                            <i class="fas fa-box-medical me-1"></i>Medical Supply
                        </span>
                        <h5 class="fw-bold text-dark mb-0"><?php echo e($c['supply_name']); ?></h5>
                    </div>
                    <div>
                        <?php if ($isCompleted): ?>
                            <span class="badge bg-success px-3 py-2 fs-6"><i class="fas fa-check-double me-1"></i> Delivery Completed</span>
                        <?php elseif ($isReady): ?>
                            <span class="badge bg-warning text-dark px-3 py-2 fs-6"><i class="fas fa-clock me-1"></i> Ready for Handover</span>
                        <?php else: ?>
                            <span class="badge bg-secondary px-3 py-2 fs-6"><?php echo e($c['status']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <!-- Left Details: Supply Specs -->
                        <div class="col-md-6 col-lg-4">
                            <h6 class="fw-bold text-muted text-uppercase small mb-3">Supply Specification</h6>
                            <div class="bg-light p-3 rounded-3 mb-3 border">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Category:</span>
                                    <span class="fw-semibold small"><?php echo e($c['category_name'] ?? 'Consumable'); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Handover Volume:</span>
                                    <span class="fw-bold text-teal"><?php echo number_format($c['requested_quantity']) . ' ' . e($c['unit']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Expiry Date:</span>
                                    <span class="fw-semibold text-danger small"><?php echo format_date($c['expiry_date']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Condition:</span>
                                    <span class="badge bg-white text-dark border"><?php echo e($c['condition_status']); ?></span>
                                </div>
                                <?php if (!empty($c['batch_number'])): ?>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted small">Batch / Lot #:</span>
                                        <span class="font-monospace small"><?php echo e($c['batch_number']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="p-3 rounded-3 text-center border" style="background:#f0fdfa;">
                                <div class="small text-muted text-uppercase fw-bold" style="font-size:0.75rem;">Verified Handshake Code</div>
                                <div class="fs-3 fw-bold font-monospace text-teal my-1" style="letter-spacing:0.12em;">
                                    <?php echo e($c['handover_code']); ?>
                                </div>
                                <div class="d-flex justify-content-center gap-2 mt-2">
                                    <button type="button" class="btn btn-xs btn-outline-teal py-1 px-2 font-monospace" data-bs-toggle="modal" data-bs-target="#resultPassModal<?php echo $c['id']; ?>">
                                        <i class="fas fa-qrcode me-1"></i> View Full Pass
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Result Pass Modal -->
                        <div class="modal fade" id="resultPassModal<?php echo $c['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content border-0 shadow-lg rounded-4">
                                    <div class="modal-header border-bottom py-3">
                                        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                            <span class="rounded-circle p-2 d-inline-flex" style="background:#ccfbf1; color:#0f766e;">
                                                <i class="fas fa-handshake"></i>
                                            </span>
                                            Handshake Verification Pass
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-4 text-center">
                                        <?php echo render_handshake_pass_html($c['handover_code'], $c['id'], 'Handover Collection Pass'); ?>
                                    </div>
                                    <div class="modal-footer border-top py-2">
                                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Middle Details: Parties Involved -->
                        <div class="col-md-6 col-lg-5">
                            <h6 class="fw-bold text-muted text-uppercase small mb-3">Donor & Recipient Parties</h6>
                            
                            <!-- Donor / Supplier -->
                            <div class="d-flex align-items-start gap-3 p-3 bg-light rounded-3 mb-3 border">
                                <div class="rounded-circle p-2 bg-primary-subtle text-primary mt-1" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-hospital"></i>
                                </div>
                                <div>
                                    <div class="small text-muted text-uppercase fw-bold" style="font-size:0.7rem;">Donor / Supplier</div>
                                    <div class="fw-bold text-dark"><?php echo e($c['supplier_org'] ?: $c['supplier_name']); ?></div>
                                    <div class="small text-muted"><?php echo e($c['supplier_city'] ?? ''); ?> &bull; Contact: <?php echo e($c['supplier_name']); ?> (<?php echo e($c['supplier_phone']); ?>)</div>
                                </div>
                            </div>

                            <!-- Recipient / NGO -->
                            <div class="d-flex align-items-start gap-3 p-3 bg-light rounded-3 mb-3 border">
                                <div class="rounded-circle p-2 bg-success-subtle text-success mt-1" style="width:36px; height:36px; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-clinic-medical"></i>
                                </div>
                                <div>
                                    <div class="small text-muted text-uppercase fw-bold" style="font-size:0.7rem;">Recipient Clinic / NGO</div>
                                    <div class="fw-bold text-dark"><?php echo e($c['requester_org'] ?: $c['requester_name']); ?></div>
                                    <div class="small text-muted"><?php echo e($c['requester_city'] ?? ''); ?> &bull; Contact: <?php echo e($c['requester_name']); ?> (<?php echo e($c['requester_phone']); ?>)</div>
                                </div>
                            </div>

                            <?php if (!empty($c['courier_name'])): ?>
                                <div class="small text-muted px-2">
                                    <i class="fas fa-truck text-secondary me-1"></i> Assigned Courier: <strong><?php echo e($c['courier_name']); ?></strong> (<?php echo e($c['courier_phone']); ?>)
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right Details: Action Execution -->
                        <div class="col-lg-3 d-flex flex-column justify-content-center text-center">
                            <?php if ($isNewlyCompleted): ?>
                                <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 border border-success">
                                    <i class="fas fa-check-circle fs-2 mb-2 text-success"></i>
                                    <h6 class="fw-bold mb-1">Delivery Completed</h6>
                                    <p class="text-muted mb-0" style="font-size:0.75rem;">Consignment successfully transferred and closed.</p>
                                    <div class="text-secondary mt-2 fw-semibold" style="font-size:0.72rem;">Completed: <?php echo format_datetime($c['completed_at']); ?></div>
                                </div>
                            <?php elseif ($isAlreadyCompleted): ?>
                                <div class="p-3 bg-secondary bg-opacity-10 text-secondary rounded-3 border border-secondary">
                                    <i class="fas fa-ban fs-2 text-danger mb-2"></i>
                                    <h6 class="fw-bold text-dark mb-1">Code Already Redeemed</h6>
                                    <p class="text-muted mb-0" style="font-size:0.75rem;">This pass cannot be used a second time.</p>
                                    <div class="text-dark mt-2 fw-semibold" style="font-size:0.72rem;">Completed On: <?php echo format_datetime($c['completed_at']); ?></div>
                                </div>
                            <?php elseif ($isReady): ?>
                                <div class="p-3 bg-light rounded-3 border text-center">
                                    <i class="fas fa-handshake fs-2 text-teal mb-2" style="color:#0f766e;"></i>
                                    <h6 class="fw-bold text-dark mb-1" style="font-size:0.95rem;">Ready for Physical Release</h6>
                                    <p class="text-muted mb-3" style="font-size:0.75rem;">Confirming will deduct stock and mark consignment delivered.</p>
                                    
                                    <form action="" method="POST">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="verify_and_complete">
                                        <input type="hidden" name="handover_code" value="<?php echo e($c['handover_code']); ?>">
                                        <button type="submit" class="btn btn-success w-100 py-2 fw-semibold rounded-3 shadow-2xs" style="font-size:0.875rem;">
                                            <i class="fas fa-check-circle me-1"></i> Confirm & Complete Delivery
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning p-2 small mb-0" style="font-size:0.78rem;">
                                    Consignment status is <strong><?php echo e($c['status']); ?></strong>. Please ensure the lot is accepted before handover.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Active Consignments Ready for Handover Table -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-boxes-packing text-teal"></i>
                    <h6 class="fw-bold text-dark mb-0">Active Consignments Ready for Handover (<?php echo count($pendingHandovers); ?>)</h6>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Approved Date</th>
                                <th>Medical Item</th>
                                <th>Quantity</th>
                                <th>From (Supplier)</th>
                                <th>To (Recipient NGO)</th>
                                <th><?php echo ($role === 'ngo') ? '6-Digit PIN' : 'Recipient Secret PIN'; ?></th>
                                <th class="text-end">Verification Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pendingHandovers)): ?>
                                <?php foreach ($pendingHandovers as $ph): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border"><i class="fas fa-calendar-check text-teal me-1"></i><?php echo format_date($ph['approved_at'] ?? $ph['created_at']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($ph['supply_name']); ?></div>
                                            <div class="text-muted small">Exp: <?php echo format_date($ph['expiry_date']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?php echo number_format($ph['requested_quantity']) . ' ' . e(format_unit($ph['unit'])); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo e($ph['supplier_org'] ?: $ph['supplier_name']); ?></div>
                                            <div class="text-muted small"><?php echo e($ph['supplier_city'] ?? ''); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo e($ph['requester_org'] ?: $ph['requester_name']); ?></div>
                                            <div class="text-muted small"><?php echo e($ph['requester_city'] ?? ''); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($role === 'ngo'): ?>
                                                <button type="button" class="btn btn-xs btn-outline-dark py-1 px-2 font-monospace shadow-2xs" data-bs-toggle="modal" data-bs-target="#tablePassModal<?php echo $ph['id']; ?>" title="Click to view Pass & QR">
                                                    <i class="fas fa-qrcode text-teal me-1"></i><?php echo e($ph['handover_code']); ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary border py-1 px-2 font-monospace">
                                                    <i class="fas fa-lock text-teal me-1"></i>Kept by Recipient
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($role === 'ngo'): ?>
                                                <button type="button" class="btn btn-sm btn-teal text-white py-1 px-3 shadow-2xs" style="background:#0f766e; font-size:0.8rem;" data-bs-toggle="modal" data-bs-target="#tablePassModal<?php echo $ph['id']; ?>">
                                                    <i class="fas fa-qrcode me-1"></i> Show Pass
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-teal text-white py-1 px-3 shadow-2xs fw-semibold" style="background:#0f766e; font-size:0.8rem;" onclick="document.getElementById('pin1').focus(); window.scrollTo({top: 0, behavior: 'smooth'});">
                                                    <i class="fas fa-handshake me-1"></i> Enter Recipient PIN
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <?php if ($role === 'ngo'): ?>
                                        <!-- Table Pass Modal (Only for Recipient NGO) -->
                                        <div class="modal fade" id="tablePassModal<?php echo $ph['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                                <div class="modal-content border-0 shadow-lg rounded-4">
                                                    <div class="modal-header border-bottom py-3">
                                                        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                                            <span class="rounded-circle p-2 d-inline-flex" style="background:#ccfbf1; color:#0f766e;">
                                                                <i class="fas fa-handshake"></i>
                                                            </span>
                                                            Handshake Verification Pass
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4 text-center">
                                                        <?php echo render_handshake_pass_html($ph['handover_code'], $ph['id'], 'Handover Collection Pass'); ?>
                                                        <div class="alert alert-light border small text-muted text-start mt-3 mb-0">
                                                            <i class="fas fa-shield-halved text-teal me-1"></i> Show this QR code or 6-digit PIN to the supplier or courier to authorize collection.
                                                        </div>
                                                        <div class="p-3 bg-light rounded-3 text-start small mt-3">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Item:</span>
                                                                <span class="fw-bold text-dark"><?php echo e($ph['supply_name']); ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Volume:</span>
                                                                <span class="fw-bold text-teal"><?php echo number_format($ph['requested_quantity']) . ' ' . e(format_unit($ph['unit'])); ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between">
                                                                <span class="text-muted">Recipient Clinic:</span>
                                                                <span class="fw-semibold text-dark"><?php echo e($ph['requester_org'] ?: $ph['requester_name']); ?> (<?php echo e($ph['requester_city'] ?? ''); ?>)</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top py-2">
                                                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block text-secondary opacity-50"></i>
                                        No pending consignments awaiting handover right now.
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

<!-- Load html5-qrcode Library for Live Camera & File QR Scanning -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pinInputs = document.querySelectorAll('.otp-pin-input');
    const hiddenCode = document.getElementById('hidden_handover_code');
    const lookupForm = document.getElementById('code-lookup-form');
    let html5QrCode = null;

    // Prepopulate PIN inputs if code was searched
    if (hiddenCode && hiddenCode.value) {
        const val = hiddenCode.value.replace(/\D/g, '');
        for (let i = 0; i < 6; i++) {
            if (pinInputs[i] && val[i]) {
                pinInputs[i].value = val[i];
            }
        }
    }

    // Auto-advance and backspace handling for 6-Digit PIN boxes
    pinInputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            this.classList.remove('is-invalid');
            if (this.value.length === 1 && index < pinInputs.length - 1) {
                pinInputs[index + 1].focus();
            }
            syncPinToHidden();
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value === '' && index > 0) {
                pinInputs[index - 1].focus();
            }
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
            if (pasteData) {
                for (let i = 0; i < 6; i++) {
                    if (pinInputs[i] && pasteData[i]) {
                        pinInputs[i].value = pasteData[i];
                        pinInputs[i].classList.remove('is-invalid');
                    }
                }
                syncPinToHidden();
                if (pasteData.length >= 6) {
                    lookupForm.submit();
                }
            }
        });
    });

    function syncPinToHidden() {
        let fullPin = '';
        pinInputs.forEach(inp => fullPin += inp.value);
        if (hiddenCode) {
            hiddenCode.value = fullPin;
        }
    }

    // Camera QR Code Scanner using html5-qrcode
    const btnStartScanner = document.getElementById('btn-start-scanner');
    const btnStopScanner = document.getElementById('btn-stop-scanner');
    const scanStatus = document.getElementById('qr-scan-status');
    const qrPlaceholder = document.getElementById('qr-reader-placeholder');
    const hudOverlay = document.getElementById('scanner-hud-overlay');
    const qrFileInput = document.getElementById('qr-input-file');

    function onScanSuccess(decodedText, decodedResult) {
        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                if (qrPlaceholder) qrPlaceholder.classList.remove('d-none');
                if (hudOverlay) hudOverlay.classList.add('d-none');
                if (btnStartScanner) btnStartScanner.classList.remove('d-none');
                if (btnStopScanner) btnStopScanner.classList.add('d-none');
            }).catch(err => console.error(err));
        }

        scanStatus.innerHTML = `<span class="text-teal fw-bold"><i class="fas fa-spinner fa-spin me-1"></i> Validating Handshake Code: ${decodedText}...</span>`;

        // Direct submission or AJAX verification
        fetch('verify-handover.php?ajax=1', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                action: 'verify_and_complete',
                code: decodedText
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                scanStatus.innerHTML = `<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Verified! Delivery Completed.</span>`;
                window.location.href = `verify-handover.php?verified_success=1&code=${encodeURIComponent(data.code || decodedText)}`;
            } else {
                if (data.error_type === 'already_completed') {
                    scanStatus.innerHTML = `<span class="text-warning fw-bold"><i class="fas fa-shield-halved me-1"></i> Code Already Used! Cannot reuse.</span>`;
                } else {
                    scanStatus.innerHTML = `<span class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i> Invalid Code: ${data.error || 'Does not match.'}</span>`;
                }
                window.location.href = `verify-handover.php?code=${encodeURIComponent(decodedText)}`;
            }
        })
        .catch(err => {
            window.location.href = `verify-handover.php?code=${encodeURIComponent(decodedText)}`;
        });
    }

    if (btnStartScanner) {
        btnStartScanner.addEventListener('click', function() {
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode("qr-reader");
            }
            if (qrPlaceholder) qrPlaceholder.classList.add('d-none');
            if (hudOverlay) hudOverlay.classList.remove('d-none');
            if (btnStartScanner) btnStartScanner.classList.add('d-none');
            if (btnStopScanner) btnStopScanner.classList.remove('d-none');
            scanStatus.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Launching camera viewfinder...</span>';

            html5QrCode.start(
                { facingMode: "environment" },
                {
                    fps: 15,
                    qrbox: { width: 170, height: 170 },
                    aspectRatio: 1.0
                },
                onScanSuccess,
                (errorMessage) => {
                    // Scanning loop in progress
                }
            ).then(() => {
                scanStatus.innerHTML = '<span class="text-success fw-semibold"><i class="fas fa-circle-dot fa-fade me-1 text-teal"></i> Camera active. Center QR code inside viewfinder.</span>';
            }).catch(err => {
                scanStatus.innerHTML = `<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> Camera unavailable: ${err}. Please use upload or 6-digit PIN.</span>`;
                if (qrPlaceholder) qrPlaceholder.classList.remove('d-none');
                if (hudOverlay) hudOverlay.classList.add('d-none');
                if (btnStartScanner) btnStartScanner.classList.remove('d-none');
                if (btnStopScanner) btnStopScanner.classList.add('d-none');
            });
        });
    }

    if (btnStopScanner) {
        btnStopScanner.addEventListener('click', function() {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().then(() => {
                    if (qrPlaceholder) qrPlaceholder.classList.remove('d-none');
                    if (hudOverlay) hudOverlay.classList.add('d-none');
                    if (btnStartScanner) btnStartScanner.classList.remove('d-none');
                    if (btnStopScanner) btnStopScanner.classList.add('d-none');
                    scanStatus.innerText = "Scanner stopped.";
                }).catch(err => console.error(err));
            }
        });
    }

    // QR Image File Scan
    if (qrFileInput) {
        qrFileInput.addEventListener('change', function(e) {
            if (e.target.files.length === 0) return;
            const imageFile = e.target.files[0];
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode("qr-reader");
            }
            scanStatus.innerText = "Scanning selected image file...";
            html5QrCode.scanFile(imageFile, true)
                .then(decodedText => {
                    onScanSuccess(decodedText, null);
                })
                .catch(err => {
                    scanStatus.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i> No QR code found in file. Please ensure clear image.</span>`;
                });
        });
    }
});
</script>
