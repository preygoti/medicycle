<?php
/**
 * MediCycle - Delivery Consignment Details & Route Tracker
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('delivery');

$userId = $_SESSION['user_id'];
$deliveryId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT d.*, r.requested_quantity, r.purpose, r.message, r.handover_code, r.status as req_status,
           ms.supply_name, ms.unit, ms.condition_status, ms.packaging_status, ms.expiry_date, ms.storage_requirements,
           c.category_name,
           su.name as supplier_name, su.phone as supplier_phone, so.organization_name as supplier_org, so.address as supplier_address, so.city as supplier_city,
           nu.name as ngo_name, nu.phone as ngo_phone, no.organization_name as ngo_org, no.address as ngo_address, no.city as ngo_city
    FROM deliveries d
    JOIN requests r ON d.request_id = r.id
    JOIN medical_supplies ms ON r.supply_id = ms.id
    JOIN categories c ON ms.category_id = c.id
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
    set_flash('danger', 'Consignment not found or access restricted.');
    header('Location: ' . BASE_URL . '/delivery/assigned.php');
    exit;
}

$pageTitle = 'Consignment #DEL-' . $delivery['id'] . ' Details - MediCycle';
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
                    <i class="fas fa-arrow-left me-1"></i> Back to Assigned Deliveries
                </a>
                <h3 class="fw-bold text-dark mt-1 mb-0">Consignment #DEL-<?php echo $delivery['id']; ?></h3>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>/delivery/update-status.php?id=<?php echo $delivery['id']; ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-pen-to-square me-1"></i> Update Transit Status
                </a>
            </div>
        </div>

        <!-- Visual Milestone Progress Bar -->
        <?php
            $statuses = ['Assigned', 'Accepted', 'Picked Up', 'In Transit', 'Delivered'];
            $currentIndex = array_search($delivery['delivery_status'], $statuses);
            if ($currentIndex === false) $currentIndex = 0;
        ?>
        <div class="card border-0 shadow-sm rounded-3 p-4 mb-4 bg-white">
            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-timeline text-teal me-2"></i> Real-time Consignment Progress</h6>
            <div class="position-relative m-4">
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo ($currentIndex / (count($statuses) - 1)) * 100; ?>%;"></div>
                </div>
                <div class="d-flex justify-content-between position-absolute top-0 start-0 w-100 translate-middle-y">
                    <?php foreach ($statuses as $idx => $st): ?>
                        <div class="text-center">
                            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto shadow-sm <?php echo $idx <= $currentIndex ? 'bg-success text-white' : 'bg-light text-muted border'; ?>" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                <?php if ($idx < $currentIndex): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <?php echo ($idx + 1); ?>
                                <?php endif; ?>
                            </div>
                            <div class="small fw-semibold mt-1 <?php echo $idx <= $currentIndex ? 'text-dark' : 'text-muted'; ?>">
                                <?php echo $st; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Consignment Spec -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-box-open text-teal me-2"></i> Medical Consignment Specification</h6>
                    
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <div class="fs-5 fw-bold text-dark"><?php echo e($delivery['supply_name']); ?></div>
                        <div class="fs-4 fw-bold text-teal"><?php echo number_format($delivery['requested_quantity']) . ' ' . e(format_unit($delivery['unit'])); ?></div>
                        <span class="badge bg-secondary"><?php echo e($delivery['category_name']); ?></span>
                    </div>

                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Condition Integrity</span>
                            <span class="fw-semibold text-dark"><?php echo e($delivery['condition_status']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Packaging Seal</span>
                            <span class="fw-semibold text-dark"><?php echo e($delivery['packaging_status']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Expiration Date</span>
                            <span class="fw-bold text-dark"><?php echo format_date($delivery['expiry_date']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Storage Guidelines</span>
                            <span class="fw-semibold text-secondary"><?php echo e($delivery['storage_requirements']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Recipient Handshake PIN</span>
                            <span class="badge bg-light text-secondary border font-monospace"><i class="fas fa-lock text-teal me-1"></i>Kept by Recipient</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Route Coordinates -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-route text-teal me-2"></i> Route Points & Contacts</h6>

                    <!-- Origin -->
                    <div class="p-3 border rounded-3 mb-3 border-start border-4 border-danger">
                        <div class="small text-danger fw-bold text-uppercase mb-1"><i class="fas fa-arrow-up me-1"></i> Pickup Origin</div>
                        <div class="fw-bold text-dark fs-6"><?php echo e($delivery['supplier_org'] ?? $delivery['supplier_name']); ?></div>
                        <div class="text-secondary small mt-1"><?php echo e($delivery['pickup_location']); ?></div>
                        <div class="small text-muted mt-2">
                            <i class="fas fa-phone text-secondary me-1"></i> Contact: <?php echo e($delivery['supplier_phone'] ?? 'On file'); ?>
                        </div>
                    </div>

                    <!-- Destination -->
                    <div class="p-3 border rounded-3 mb-3 border-start border-4 border-success">
                        <div class="small text-success fw-bold text-uppercase mb-1"><i class="fas fa-arrow-down me-1"></i> Drop Destination</div>
                        <div class="fw-bold text-dark fs-6"><?php echo e($delivery['ngo_org'] ?? $delivery['ngo_name']); ?></div>
                        <div class="text-secondary small mt-1"><?php echo e($delivery['delivery_location']); ?></div>
                        <div class="small text-muted mt-2">
                            <i class="fas fa-phone text-secondary me-1"></i> Contact: <?php echo e($delivery['ngo_phone'] ?? 'On file'); ?>
                        </div>
                    </div>

                    <!-- Tracking Notes Log -->
                    <div class="border-top pt-3">
                        <div class="small fw-semibold text-muted mb-1">Transit Dispatch Notes:</div>
                        <div class="p-2 bg-light rounded text-secondary small font-monospace" style="white-space: pre-line;">
                            <?php echo e($delivery['tracking_notes'] ?: 'No transit notes recorded yet.'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
