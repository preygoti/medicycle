<?php
/**
 * MediCycle - NGO & Clinic Handover Code Pass & Scanner
 * Dedicated recipient pass portal: Displays secure 6-digit Handshake PINs,
 * high-resolution QR collection passes, donor pickup details, and on-demand camera QR scanner.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$currentUser = current_user();
$userId = $currentUser['id'];
$pageTitle = 'Handover Code Pass & Scanner';

$selectedCode = clean($_GET['code'] ?? '');

// Fetch active collection passes awaiting pickup/handover
$activeStmt = $pdo->prepare("
    SELECT r.*, ms.supply_name, ms.unit, ms.batch_number, ms.expiry_date,
           su.name AS supplier_name, so.organization_name AS supplier_org, 
           so.address AS supplier_address, so.city AS supplier_city, so.phone AS supplier_phone
    FROM requests r
    JOIN medical_supplies ms ON r.supply_id = ms.id
    JOIN users su ON ms.supplier_id = su.id
    LEFT JOIN organizations so ON su.id = so.user_id
    WHERE r.requester_id = :userId
      AND r.handover_code IS NOT NULL
      AND r.status IN ('Approved', 'Accepted', 'Ready for Handover', 'In Transit')
    ORDER BY r.approved_at DESC
");
$activeStmt->execute(['userId' => $userId]);
$activePasses = $activeStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recently completed handovers
$historyStmt = $pdo->prepare("
    SELECT r.*, ms.supply_name, ms.unit, ms.batch_number,
           su.name AS supplier_name, so.organization_name AS supplier_org,
           d.completed_at
    FROM requests r
    JOIN medical_supplies ms ON r.supply_id = ms.id
    JOIN users su ON ms.supplier_id = su.id
    LEFT JOIN organizations so ON su.id = so.user_id
    LEFT JOIN deliveries d ON r.id = d.request_id
    WHERE r.requester_id = :userId
      AND r.status = 'Completed'
    ORDER BY r.updated_at DESC
    LIMIT 10
");
$historyStmt->execute(['userId' => $userId]);
$completedPasses = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle p-2 bg-teal-subtle text-teal d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background:#ccfbf1; color:#0f766e;">
                        <i class="fas fa-qrcode fs-5"></i>
                    </span>
                    <div>
                        <h3 class="fw-bold text-dark mb-0">Handover Code Pass & Scanner</h3>
                        <p class="text-muted small mb-0">Show your secure QR Pass or 6-digit PIN to collect supplies, or scan delivery packages</p>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 d-flex align-items-center gap-1 rounded-pill">
                    <i class="fas fa-shield-alt"></i> Verified Recipient Claim
                </span>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3" id="passTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill px-4 fw-semibold d-inline-flex align-items-center gap-2" id="active-tab" data-bs-toggle="tab" data-bs-target="#tab-active-passes" type="button" role="tab">
                    <i class="fas fa-id-card"></i>
                    <span>Active Collection Passes</span>
                    <?php if (count($activePasses) > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo count($activePasses); ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4 fw-semibold d-inline-flex align-items-center gap-2" id="scanner-tab" data-bs-toggle="tab" data-bs-target="#tab-scanner" type="button" role="tab">
                    <i class="fas fa-camera"></i>
                    <span>Camera QR Scanner</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4 fw-semibold d-inline-flex align-items-center gap-2" id="history-tab" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab">
                    <i class="fas fa-history"></i>
                    <span>Past Collected (<?php echo count($completedPasses); ?>)</span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="passTabContent">
            <!-- TAB 1: Active Passes -->
            <div class="tab-pane fade show active" id="tab-active-passes" role="tabpanel">
                <?php if (empty($activePasses)): ?>
                    <div class="card border-0 shadow-sm rounded-4 text-center py-5 px-3">
                        <div class="rounded-circle p-3 bg-light d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px;">
                            <i class="fas fa-qrcode fs-2 text-muted"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">No Active Collection Passes</h5>
                        <p class="text-muted small mx-auto mb-3" style="max-width: 480px;">
                            You currently don't have any pending supply handovers. Once a donor approves your request or fulfills your requirement, your secure 6-digit Handover Code and QR Pass will appear here automatically.
                        </p>
                        <div>
                            <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="btn btn-teal text-white rounded-pill px-4 fw-semibold shadow-sm" style="background:#0f766e;">
                                <i class="fas fa-search me-1"></i> Browse Available Supplies
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($activePasses as $pass): ?>
                            <?php 
                                $isTarget = (!empty($selectedCode) && $pass['handover_code'] === $selectedCode);
                                $digits = str_split(str_pad(preg_replace('/[^0-9]/', '', (string)$pass['handover_code']), 6, '0'));
                                $qrUrl = get_handshake_qr_url($pass['handover_code'], $pass['id']);
                            ?>
                            <div class="col-xl-6">
                                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden <?php echo $isTarget ? 'ring-2 ring-teal' : ''; ?>" style="<?php echo $isTarget ? 'border: 2px solid #0f766e !important;' : ''; ?>">
                                    <!-- Card Header with Gradient -->
                                    <div class="p-3 text-white d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);">
                                        <div>
                                            <span class="badge bg-white text-teal fw-bold px-2.5 py-1 mb-1 shadow-xs" style="color:#0f766e; font-size:0.7rem; letter-spacing:0.04em;">
                                                <i class="fas fa-shield-alt me-1"></i>SECURE COLLECTION PASS
                                            </span>
                                            <h5 class="fw-bold mb-0 text-white"><?php echo e($pass['supply_name']); ?></h5>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-white bg-opacity-25 text-white border border-white border-opacity-25 px-2.5 py-1 text-nowrap">
                                                <?php echo status_badge($pass['status']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="card-body p-4 bg-white">
                                        <!-- QR Code & 6-Digit PIN Side-by-Side -->
                                        <div class="row g-3 align-items-center mb-4">
                                            <!-- QR Preview -->
                                            <div class="col-sm-5 text-center border-sm-end pb-3 pb-sm-0">
                                                <div class="p-2 rounded-3 bg-light border d-inline-block shadow-2xs">
                                                    <img src="<?php echo e($qrUrl); ?>" alt="Collection QR Pass" class="img-fluid rounded" style="width: 140px; height: 140px; object-fit: contain;">
                                                </div>
                                                <div class="text-muted text-uppercase fw-bold mt-2" style="font-size:0.7rem; letter-spacing:0.05em;">
                                                    <i class="fas fa-qrcode me-1 text-teal"></i>Scan at Collection
                                                </div>
                                            </div>

                                            <!-- 6 Digit PIN -->
                                            <div class="col-sm-7 text-center">
                                                <div class="text-muted text-uppercase fw-bold mb-1" style="font-size:0.74rem; letter-spacing:0.05em;">
                                                    6-Digit Handover PIN
                                                </div>
                                                <div class="d-flex justify-content-center gap-1.5 my-2">
                                                    <?php foreach ($digits as $d): ?>
                                                        <div class="d-inline-flex align-items-center justify-content-center fw-bold rounded-3 shadow-2xs" 
                                                             style="width: 36px; height: 46px; background: #f8fafc; border: 1.5px solid #cbd5e1; font-size: 1.25rem; font-family: monospace; color: #0f172a;">
                                                            <?php echo e($d); ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3 py-1 mt-1 copy-pin-btn" data-code="<?php echo e($pass['handover_code']); ?>" style="font-size:0.75rem;">
                                                    <i class="fas fa-copy me-1"></i>Copy PIN
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Supply & Donor Details Box -->
                                        <div class="bg-light p-3 rounded-3 mb-3 border" style="font-size:0.82rem;">
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <span class="text-muted d-block small">Allocated Quantity:</span>
                                                    <span class="fw-bold text-dark fs-6"><?php echo number_format($pass['requested_quantity']) . ' ' . e(format_unit($pass['unit'])); ?></span>
                                                </div>
                                                <div class="col-6">
                                                    <span class="text-muted d-block small">Batch / Shelf Life:</span>
                                                    <span class="fw-semibold text-dark"><?php echo e($pass['batch_number'] ?: 'Standard'); ?> (Exp: <?php echo format_date($pass['expiry_date']); ?>)</span>
                                                </div>
                                                <div class="col-12 border-top pt-2 mt-2">
                                                    <span class="text-muted d-block small">Donor / Collection Point:</span>
                                                    <span class="fw-bold text-dark"><?php echo e($pass['supplier_org'] ?: $pass['supplier_name']); ?></span>
                                                    <?php if (!empty($pass['supplier_address']) || !empty($pass['supplier_city'])): ?>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                                            <?php echo e(implode(', ', array_filter([$pass['supplier_address'] ?? '', $pass['supplier_city'] ?? '']))); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($pass['supplier_phone'])): ?>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-phone text-teal me-1"></i> <?php echo e($pass['supplier_phone']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Instructions Banner -->
                                        <div class="alert alert-info py-2 px-3 rounded-3 mb-0 d-flex align-items-center gap-2 border-0" style="background: #f0fdfa; color: #0f766e; font-size:0.78rem;">
                                            <i class="fas fa-info-circle fs-5 flex-shrink-0"></i>
                                            <div>
                                                Present this screen to the supplier or courier at collection time. Once they verify your code or QR, handover marks completed instantly.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-footer bg-white border-top p-3 d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">Approved: <?php echo format_date($pass['approved_at'] ?? $pass['requested_at']); ?></span>
                                        <a href="<?php echo BASE_URL; ?>/ngo/request-details.php?id=<?php echo $pass['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1">
                                            View Requisition Details <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 2: Camera QR Scanner -->
            <div class="tab-pane fade" id="tab-scanner" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-camera text-teal fs-5"></i>
                                    <h6 class="fw-bold text-dark mb-0">Camera & File QR Scanner</h6>
                                </div>
                                <span class="badge bg-light text-secondary border">Universal Reader</span>
                            </div>
                            <div class="card-body p-4 text-center">
                                <p class="text-muted small mb-3">
                                    Point camera at any MediCycle consignment QR code or upload a QR image from your device
                                </p>

                                <!-- Scanner Viewport Container -->
                                <div class="scanner-viewport-hud mb-3 position-relative mx-auto" style="height: 280px; max-width: 460px; overflow: hidden; background: #090d16; border-radius: 16px;">
                                    <div id="qr-reader" style="width: 100%; height: 100%; position: absolute; top: 0; left: 0;"></div>

                                    <!-- Inactive State Placeholder Overlay -->
                                    <div id="qr-reader-placeholder" style="width: 100%; height: 100%; position: absolute; top: 0; left: 0; z-index: 8; background: radial-gradient(circle at center, #111827 0%, #090d16 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.5rem; text-align: center; color: #ffffff;">
                                        <div class="rounded-circle p-2 mb-2 d-inline-flex align-items-center justify-content-center" style="background: rgba(45, 212, 191, 0.12); border: 1.5px solid rgba(45, 212, 191, 0.35); width: 52px; height: 52px;">
                                            <i class="fas fa-qrcode fs-3" style="color: #2dd4bf !important;"></i>
                                        </div>
                                        <h6 class="fw-bold text-white mb-1">Live Camera QR Scanner</h6>
                                        <p class="small mb-3 text-muted" style="max-width: 320px; font-size: 0.8rem; color: #94a3b8 !important;">
                                            Scan supply labels or donor passes with your mobile or desktop camera
                                        </p>
                                        <button type="button" id="btn-start-scanner" class="btn btn-teal text-white btn-sm px-4 py-2 shadow-sm rounded-pill fw-semibold" style="background: #0f766e;">
                                            <i class="fas fa-camera me-1"></i> Start Camera Scanner
                                        </button>
                                    </div>
                                </div>

                                <!-- Scanner Controls -->
                                <div class="d-flex justify-content-center gap-2 mb-3">
                                    <button type="button" id="btn-stop-scanner" class="btn btn-outline-danger btn-sm px-3 rounded-pill d-none">
                                        <i class="fas fa-stop me-1"></i> Stop Camera
                                    </button>
                                    <label class="btn btn-outline-secondary btn-sm px-3 rounded-pill mb-0" style="cursor: pointer;">
                                        <i class="fas fa-file-image me-1"></i> Upload QR Image
                                        <input type="file" id="qr-input-file" accept="image/*" class="d-none">
                                    </label>
                                </div>

                                <!-- Result Box -->
                                <div id="scan-result-card" class="d-none alert alert-success text-start rounded-3 p-3 mt-3">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="fas fa-check-circle text-success fs-5"></i>
                                        <h6 class="fw-bold text-success mb-0">QR Code Detected</h6>
                                    </div>
                                    <div class="small text-dark mb-1">Scanned Value: <strong id="scanned-raw-code" class="font-monospace text-teal"></strong></div>
                                    <div id="scanned-action-area" class="mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Past History -->
            <div class="tab-pane fade" id="tab-history" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-check-double text-teal me-2"></i>Recently Collected & Completed Supplies</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Item Name</th>
                                        <th>Quantity Received</th>
                                        <th>Donor / Facility</th>
                                        <th>Completed On</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($completedPasses)): ?>
                                        <?php foreach ($completedPasses as $cp): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="fw-bold text-dark"><?php echo e($cp['supply_name']); ?></div>
                                                    <div class="text-muted small">Batch: <?php echo e($cp['batch_number'] ?: 'N/A'); ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border">
                                                        <?php echo number_format($cp['requested_quantity']) . ' ' . e(format_unit($cp['unit'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?php echo e($cp['supplier_org'] ?: $cp['supplier_name']); ?></div>
                                                </td>
                                                <td class="text-nowrap text-muted">
                                                    <?php echo format_datetime($cp['completed_at'] ?? $cp['updated_at']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success text-white px-2 py-1"><i class="fas fa-check-double me-1"></i>Verified & Received</span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <a href="<?php echo BASE_URL; ?>/ngo/request-details.php?id=<?php echo $cp['id']; ?>" class="btn btn-xs btn-outline-secondary px-2.5 py-1 rounded-pill">
                                                        View Receipt
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                No completed handovers on record yet.
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
    </div>
</div>

<!-- html5-qrcode Library for in-browser Camera Scanning -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Copy PIN Button
    document.querySelectorAll('.copy-pin-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const code = this.getAttribute('data-code');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(() => {
                    const originalHtml = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check text-success me-1"></i>Copied!';
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-outline-success');
                    setTimeout(() => {
                        this.innerHTML = originalHtml;
                        this.classList.remove('btn-outline-success');
                        this.classList.add('btn-outline-secondary');
                    }, 2000);
                });
            }
        });
    });

    // Scanner Initialization
    let html5QrCode = null;
    const btnStart = document.getElementById('btn-start-scanner');
    const btnStop = document.getElementById('btn-stop-scanner');
    const placeholder = document.getElementById('qr-reader-placeholder');
    const fileInput = document.getElementById('qr-input-file');
    const resultCard = document.getElementById('scan-result-card');
    const rawCodeEl = document.getElementById('scanned-raw-code');
    const actionArea = document.getElementById('scanned-action-area');

    function onScanSuccess(decodedText, decodedResult) {
        console.log("QR scanned:", decodedText);
        // Play subtle beep if supported
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            osc.connect(ctx.destination);
            osc.frequency.value = 880;
            osc.start();
            osc.stop(ctx.currentTime + 0.12);
        } catch(e) {}

        if (html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => {
                btnStart.classList.remove('d-none');
                btnStop.classList.add('d-none');
                placeholder.classList.remove('d-none');
            });
        }

        resultCard.classList.remove('d-none');
        rawCodeEl.textContent = decodedText;

        // Extract 6-digit number if present in string/URL
        const digitsMatch = decodedText.match(/\b\d{6}\b/);
        if (digitsMatch) {
            const code = digitsMatch[0];
            actionArea.innerHTML = `
                <div class="d-flex align-items-center gap-2 mt-2">
                    <span class="badge bg-dark font-monospace fs-6 px-3 py-2">${code}</span>
                    <a href="code-pass.php?code=${encodeURIComponent(code)}" class="btn btn-sm btn-teal text-white fw-semibold rounded-pill px-3" style="background:#0f766e;">
                        <i class="fas fa-eye me-1"></i> View Matching Pass
                    </a>
                </div>
            `;
        } else {
            actionArea.innerHTML = `
                <div class="text-muted small mt-1">
                    Scanned content: <em>${decodedText}</em>
                </div>
            `;
        }
    }

    if (btnStart) {
        btnStart.addEventListener('click', function() {
            placeholder.classList.add('d-none');
            btnStart.classList.add('d-none');
            btnStop.classList.remove('d-none');

            html5QrCode = new Html5Qrcode("qr-reader");
            html5QrCode.start(
                { facingMode: "environment" },
                {
                    fps: 15,
                    qrbox: { width: 220, height: 220 }
                },
                onScanSuccess,
                (errorMessage) => {}
            ).catch(err => {
                console.error("Camera start failed:", err);
                alert("Camera access was blocked or is not available on this device. You can also use the 'Upload QR Image' button.");
                placeholder.classList.remove('d-none');
                btnStart.classList.remove('d-none');
                btnStop.classList.add('d-none');
            });
        });
    }

    if (btnStop) {
        btnStop.addEventListener('click', function() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    btnStart.classList.remove('d-none');
                    btnStop.classList.add('d-none');
                    placeholder.classList.remove('d-none');
                });
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            if (e.target.files.length === 0) return;
            const file = e.target.files[0];
            const reader = new Html5Qrcode("qr-reader");
            reader.scanFile(file, true)
                .then(decodedText => {
                    onScanSuccess(decodedText, null);
                })
                .catch(err => {
                    alert("Could not detect a valid QR code in the uploaded image. Please ensure the QR is clear and well lit.");
                });
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
