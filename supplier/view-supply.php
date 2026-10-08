<?php
/**
 * MediCycle - View Medical Supply Details
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];
$supplyId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT s.*, c.category_name 
                       FROM medical_supplies s 
                       JOIN categories c ON s.category_id = c.id 
                       WHERE s.id = ? AND s.supplier_id = ? LIMIT 1");
$stmt->execute([$supplyId, $userId]);
$supply = $stmt->fetch();

if (!$supply) {
    set_flash('danger', 'Supply listing not found.');
    header('Location: ' . BASE_URL . '/supplier/inventory.php');
    exit;
}

// Fetch requests associated with this supply
$reqStmt = $pdo->prepare("SELECT r.*, u.name as requester_name, o.organization_name, o.city 
                          FROM requests r 
                          JOIN users u ON r.requester_id = u.id 
                          LEFT JOIN organizations o ON u.id = o.user_id 
                          WHERE r.supply_id = ? 
                          ORDER BY r.requested_at DESC");
$reqStmt->execute([$supplyId]);
$requests = $reqStmt->fetchAll();

$pageTitle = 'View Supply - ' . $supply['supply_name'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="<?php echo BASE_URL; ?>/supplier/inventory.php" class="text-decoration-none text-muted small">
                    <i class="fas fa-arrow-left me-1"></i> Back to Inventory
                </a>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?php echo e($supply['supply_name']); ?></h3>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo BASE_URL; ?>/supplier/edit-supply.php?id=<?php echo $supply['id']; ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-edit me-1"></i> Edit Batch
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0"><i class="fas fa-info-circle text-teal me-2" style="color:#0f766e;"></i>Batch Specifications</h6>
                            <div>
                                <?php echo status_badge($supply['status']); ?>
                                <?php echo priority_badge($supply['priority_level']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Category</label>
                                <span class="fw-semibold text-dark"><?php echo e($supply['category_name']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Stock Quantity</label>
                                <span class="fw-bold fs-5 text-teal"><?php echo number_format($supply['quantity']) . ' ' . e($supply['unit']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Batch / Lot Identifier</label>
                                <code><?php echo e($supply['batch_number'] ?? 'N/A'); ?></code>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Expiration Date</label>
                                <span class="fw-bold text-dark"><?php echo format_date($supply['expiry_date']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Physical Condition</label>
                                <span class="badge bg-light text-dark border"><?php echo e($supply['condition_status']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Packaging Seal Verification</label>
                                <span class="badge bg-light text-dark border"><?php echo e($supply['packaging_status']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Storage Guidelines</label>
                                <span class="text-secondary small"><?php echo e($supply['storage_requirements']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Location / Dispatch Hub</label>
                                <span class="text-secondary small"><i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo e($supply['location']); ?></span>
                            </div>
                            <div class="col-12 border-top pt-3">
                                <label class="text-muted small d-block">Technical Overview</label>
                                <p class="text-secondary mb-0 small"><?php echo nl2br(e($supply['description'] ?? 'No additional technical notes provided.')); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requests for this supply -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-inbox text-teal me-2" style="color:#0f766e;"></i>Requisition Activity (<?php echo count($requests); ?>)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>NGO / Requester</th>
                                        <th>Qty Requested</th>
                                        <th>Clinical Purpose</th>
                                        <th>Requested Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($requests)): ?>
                                        <?php foreach ($requests as $r): ?>
                                            <tr>
                                                <td>
                                                    <span class="fw-semibold text-dark"><?php echo e($r['organization_name'] ?? $r['requester_name']); ?></span>
                                                    <div class="text-muted small"><i class="fas fa-map-marker-alt me-1"></i><?php echo e($r['city'] ?? 'N/A'); ?></div>
                                                </td>
                                                <td class="fw-bold"><?php echo $r['requested_quantity'] . ' ' . e($supply['unit']); ?></td>
                                                <td class="small text-secondary"><?php echo e($r['purpose'] ?? 'General Outreach'); ?></td>
                                                <td class="small text-muted"><?php echo format_date($r['requested_at']); ?></td>
                                                <td><?php echo status_badge($r['status']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted small">No requisition requests submitted for this item yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Smart Priority Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm p-4 mb-4 bg-light">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-brain text-teal me-2" style="color:#0f766e;"></i>Smart Priority Score</h6>
                    <div class="text-center my-3">
                        <div class="display-4 fw-bold text-teal" style="color:#0f766e;"><?php echo $supply['priority_score']; ?><span class="fs-6 text-muted">/100</span></div>
                        <div class="mt-1"><?php echo priority_badge($supply['priority_level']); ?></div>
                    </div>
                    <ul class="list-unstyled small text-muted mb-0 mt-3 border-top pt-3">
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i><strong>Expiry buffer:</strong> Evaluated against shelf-life decay curve.</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i><strong>Sterile seal integrity:</strong> Verified per ISO packaging criteria.</li>
                        <li><i class="fas fa-check-circle text-success me-2"></i><strong>Redistribution urgency:</strong> Prioritized to avert landfill disposal.</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
