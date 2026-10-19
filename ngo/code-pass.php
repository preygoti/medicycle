<?php
/**
 * MediCycle - NGO & Clinic Handover Code Pass Portal
 * Recipient pass portal: Displays secure 6-digit Handover Codes,
 * donor pickup details, allocated medical supplies, and verification history.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$currentUser = current_user();
$userId = $currentUser['id'];
$pageTitle = 'Handover Collection Passes';

$selectedCode = clean($_GET['code'] ?? '');

// Fetch active collection passes awaiting pickup/handover
$activeStmt = $pdo->prepare("
    SELECT r.*, ms.supply_name, ms.unit, ms.batch_number, ms.expiry_date,
           su.name AS supplier_name, su.phone AS supplier_phone,
           so.organization_name AS supplier_org, so.address AS supplier_address, so.city AS supplier_city
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
           COALESCE(r.completed_at, d.delivered_at, r.updated_at) AS completed_at
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

<style>
/* Green theme nav-pills to match MediCycle branding */
.pass-nav-pills .nav-link {
    color: #0f766e;
    background-color: transparent;
    font-weight: 600;
    transition: all 0.2s ease-in-out;
    border: 1px solid transparent;
}
.pass-nav-pills .nav-link:hover:not(.active) {
    background-color: #f0fdfa;
    color: #0f766e;
    border-color: #ccfbf1;
}
.pass-nav-pills .nav-link.active {
    background-color: #0f766e !important;
    color: #ffffff !important;
    border-color: #0f766e !important;
    box-shadow: 0 4px 6px -1px rgba(15, 118, 110, 0.25), 0 2px 4px -2px rgba(15, 118, 110, 0.2);
}
</style>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px; background:#ccfbf1; color:#0f766e;">
                        <i class="fas fa-id-card fs-5"></i>
                    </span>
                    <div>
                        <h3 class="fw-bold text-dark mb-0">Handover Collection Passes</h3>
                        <p class="text-muted small mb-0">Present your 6-digit Handover Code to the donor or courier to claim medical supplies</p>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge border px-3 py-2 d-flex align-items-center gap-1 rounded-pill" style="background:#f0fdfa; color:#0f766e; border-color:#ccfbf1 !important;">
                    <i class="fas fa-shield-alt"></i> Verified Recipient Claim
                </span>
            </div>
        </div>

        <!-- Navigation Tabs (Green Styled) -->
        <ul class="nav nav-pills pass-nav-pills mb-4 gap-2 border-bottom pb-3" id="passTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill px-4 py-2 d-inline-flex align-items-center gap-2" id="active-tab" data-bs-toggle="tab" data-bs-target="#tab-active-passes" type="button" role="tab">
                    <i class="fas fa-id-card"></i>
                    <span>Active Collection Passes</span>
                    <?php if (count($activePasses) > 0): ?>
                        <span class="badge bg-white text-teal rounded-pill ms-1" style="color:#0f766e;"><?php echo count($activePasses); ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4 py-2 d-inline-flex align-items-center gap-2" id="history-tab" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab">
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
                        <div class="rounded-circle p-3 d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 70px; height: 70px; background:#f0fdfa;">
                            <i class="fas fa-id-card fs-2" style="color:#0f766e;"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">No Active Collection Passes</h5>
                        <p class="text-muted small mx-auto mb-3" style="max-width: 480px;">
                            You currently don't have any pending supply handovers. Once a donor approves your request or fulfills your requirement, your secure 6-digit Handover Code will appear here automatically.
                        </p>
                        <div>
                            <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" style="background:#0f766e;">
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
                            ?>
                            <div class="col-xl-6">
                                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden <?php echo $isTarget ? 'ring-2 ring-teal' : ''; ?>" style="<?php echo $isTarget ? 'border: 2px solid #0f766e !important;' : ''; ?>">
                                    <!-- Card Header with Gradient -->
                                    <div class="p-3 text-white d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);">
                                        <div>
                                            <span class="badge bg-white fw-bold px-2.5 py-1 mb-1 shadow-xs" style="color:#0f766e; font-size:0.7rem; letter-spacing:0.04em;">
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
                                        <!-- 6 Digit PIN Display -->
                                        <div class="text-center mb-4 p-3 rounded-4" style="background: #f8fafc; border: 1.5px dashed #cbd5e1;">
                                            <div class="text-muted text-uppercase fw-bold mb-2" style="font-size:0.75rem; letter-spacing:0.06em;">
                                                <i class="fas fa-key text-teal me-1" style="color:#0f766e;"></i> Your 6-Digit Handover Code
                                            </div>
                                            <div class="d-flex justify-content-center gap-2 my-2">
                                                <?php foreach ($digits as $d): ?>
                                                    <div class="d-inline-flex align-items-center justify-content-center fw-bold rounded-3 shadow-2xs" 
                                                         style="width: 42px; height: 52px; background: #ffffff; border: 2px solid #0f766e; font-size: 1.45rem; font-family: monospace; color: #0f766e;">
                                                        <?php echo e($d); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <button type="button" class="btn btn-xs rounded-pill px-3 py-1.5 mt-2 copy-pin-btn fw-semibold shadow-2xs" data-code="<?php echo e($pass['handover_code']); ?>" style="background:#ffffff; color:#0f766e; border:1px solid #0f766e; font-size:0.78rem;">
                                                <i class="fas fa-copy me-1"></i>Copy Handover Code
                                            </button>
                                        </div>

                                        <!-- Supply & Donor Details Box -->
                                        <div class="p-3 rounded-3 mb-3 border" style="background:#ffffff; font-size:0.84rem;">
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
                                                        <div class="text-muted small mt-0.5">
                                                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                                            <?php echo e(implode(', ', array_filter([$pass['supplier_address'] ?? '', $pass['supplier_city'] ?? '']))); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($pass['supplier_phone'])): ?>
                                                        <div class="text-muted small mt-0.5">
                                                            <i class="fas fa-phone text-teal me-1" style="color:#0f766e;"></i> <?php echo e($pass['supplier_phone']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Instructions Banner -->
                                        <div class="alert py-2.5 px-3 rounded-3 mb-0 d-flex align-items-center gap-2 border-0" style="background: #f0fdfa; color: #0f766e; font-size:0.8rem;">
                                            <i class="fas fa-info-circle fs-5 flex-shrink-0"></i>
                                            <div>
                                                Provide this 6-digit code to the donor or delivery person when collecting your supplies. Handover completes upon their verification.
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

            <!-- TAB 2: Past History -->
            <div class="tab-pane fade" id="tab-history" role="tabpanel">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-check-double text-teal me-2" style="color:#0f766e;"></i>Recently Collected & Completed Supplies</h6>
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
                    setTimeout(() => {
                        this.innerHTML = originalHtml;
                    }, 2000);
                });
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
