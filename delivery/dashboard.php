<?php
/**
 * MediCycle - Delivery Partner & Volunteer Logistics Dashboard
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('delivery');

$userId = $_SESSION['user_id'];

try {
    // Deliveries summary for this delivery partner
    $delStats = $pdo->prepare("SELECT delivery_status, COUNT(*) as count 
                               FROM deliveries 
                               WHERE delivery_partner_id = ? OR delivery_partner_id IS NULL
                               GROUP BY delivery_status");
    $delStats->execute([$userId]);
    $stats = $delStats->fetchAll(PDO::FETCH_KEY_PAIR);

    $assignedCount = $stats['Assigned'] ?? 0;
    $acceptedCount = $stats['Accepted'] ?? 0;
    $pickedUpCount = $stats['Picked Up'] ?? 0;
    $inTransitCount = $stats['In Transit'] ?? 0;
    $completedCount = $stats['Delivered'] ?? 0;

    // Active Deliveries needing action
    $activeQuery = $pdo->prepare("
        SELECT d.*, r.requested_quantity, ms.supply_name, ms.unit, c.category_name,
               su.name as supplier_name, su.phone as supplier_phone, so.organization_name as supplier_org,
               nu.name as ngo_name, nu.phone as ngo_phone, no.organization_name as ngo_org, no.city as ngo_city
        FROM deliveries d
        JOIN requests r ON d.request_id = r.id
        JOIN medical_supplies ms ON r.supply_id = ms.id
        JOIN categories c ON ms.category_id = c.id
        JOIN users su ON ms.supplier_id = su.id
        LEFT JOIN organizations so ON su.id = so.user_id
        JOIN users nu ON r.requester_id = nu.id
        LEFT JOIN organizations no ON nu.id = no.user_id
        WHERE (d.delivery_partner_id = ? OR d.delivery_partner_id IS NULL)
          AND d.delivery_status IN ('Assigned', 'Accepted', 'Picked Up', 'In Transit')
        ORDER BY (d.delivery_status = 'Assigned') DESC, d.assigned_at DESC
    ");
    $activeQuery->execute([$userId]);
    $activeDeliveries = $activeQuery->fetchAll();

    // Recent Completed Deliveries
    $recentQuery = $pdo->prepare("
        SELECT d.*, r.requested_quantity, ms.supply_name, ms.unit,
               COALESCE(no.organization_name, nu.name) as recipient_name,
               COALESCE(so.organization_name, su.name) as donor_name
        FROM deliveries d
        JOIN requests r ON d.request_id = r.id
        JOIN medical_supplies ms ON r.supply_id = ms.id
        JOIN users su ON ms.supplier_id = su.id
        LEFT JOIN organizations so ON su.id = so.user_id
        JOIN users nu ON r.requester_id = nu.id
        LEFT JOIN organizations no ON nu.id = no.user_id
        WHERE d.delivery_partner_id = ? AND d.delivery_status = 'Delivered'
        ORDER BY d.delivered_at DESC LIMIT 5
    ");
    $recentQuery->execute([$userId]);
    $recentCompleted = $recentQuery->fetchAll();

} catch (PDOException $e) {
    error_log("Delivery dashboard error: " . $e->getMessage());
    $assignedCount = $acceptedCount = $pickedUpCount = $inTransitCount = $completedCount = 0;
    $activeDeliveries = $recentCompleted = [];
}

$pageTitle = 'Logistics & Courier Dashboard - MediCycle';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h3 class="fw-bold text-dark mb-1">Medical Logistics Fleet Dispatch</h3>
                <p class="text-muted small mb-0">Manage hospital pickup dispatches, transport status updates, and clinic delivery drop-offs</p>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>/delivery/assigned.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-boxes-packing me-1"></i> View Assigned Deliveries
                </a>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">New Assignments</span>
                            <h3 class="fw-bold text-warning mb-0 mt-1"><?php echo $assignedCount; ?></h3>
                            <small class="text-muted">Awaiting courier acceptance</small>
                        </div>
                        <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-clipboard-check fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Pending Pickup</span>
                            <h3 class="fw-bold text-primary mb-0 mt-1"><?php echo $acceptedCount; ?></h3>
                            <small class="text-muted">Accepted & ready at supplier</small>
                        </div>
                        <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-box-open fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">In Transit</span>
                            <h3 class="fw-bold text-info mb-0 mt-1"><?php echo ($pickedUpCount + $inTransitCount); ?></h3>
                            <small class="text-muted">En route to recipient clinic</small>
                        </div>
                        <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info">
                            <i class="fas fa-truck-fast fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Completed Drops</span>
                            <h3 class="fw-bold text-success mb-0 mt-1"><?php echo $completedCount; ?></h3>
                            <small class="text-muted">Successfully fulfilled</small>
                        </div>
                        <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success">
                            <i class="fas fa-check-double fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Deliveries -->
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-truck text-teal me-2"></i> Active Consignments Needing Courier Action (<?php echo count($activeDeliveries); ?>)</h6>
                <a href="<?php echo BASE_URL; ?>/delivery/assigned.php" class="small text-teal text-decoration-none">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Delivery ID</th>
                                <th>Medical Consignment</th>
                                <th>Origin (Pickup Facility)</th>
                                <th>Destination (Drop Clinic)</th>
                                <th>Transit Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($activeDeliveries)): ?>
                                <?php foreach ($activeDeliveries as $del): ?>
                                    <tr>
                                        <td>#DEL-<?php echo $del['id']; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($del['supply_name']); ?></div>
                                            <span class="badge bg-light text-primary"><?php echo $del['requested_quantity'] . ' ' . e($del['unit']); ?></span>
                                            <small class="text-muted d-block"><?php echo e($del['category_name']); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo e($del['supplier_org'] ?? $del['supplier_name']); ?></div>
                                            <small class="text-muted"><i class="fas fa-location-dot me-1 text-danger"></i><?php echo e($del['pickup_location']); ?></small>
                                            <div class="text-muted" style="font-size: 0.72rem;"><i class="fas fa-phone me-1"></i><?php echo e($del['supplier_phone'] ?? 'On file'); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo e($del['ngo_org'] ?? $del['ngo_name']); ?></div>
                                            <small class="text-muted"><i class="fas fa-flag me-1 text-success"></i><?php echo e($del['delivery_location']); ?></small>
                                            <div class="text-muted" style="font-size: 0.72rem;"><i class="fas fa-phone me-1"></i><?php echo e($del['ngo_phone'] ?? 'On file'); ?></div>
                                        </td>
                                        <td>
                                            <?php echo status_badge($del['delivery_status']); ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo BASE_URL; ?>/delivery/delivery-details.php?id=<?php echo $del['id']; ?>" class="btn btn-sm btn-outline-primary py-0 me-1">
                                                Details
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/delivery/update-status.php?id=<?php echo $del['id']; ?>" class="btn btn-sm btn-success py-0">
                                                Update Status
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="fas fa-check-circle text-success me-1"></i> No active delivery dispatches pending at this moment.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Completed Drops -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-clock-rotate-left text-teal me-2"></i> Recently Fulfilled Deliveries</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Delivery ID</th>
                                <th>Supply Consignment</th>
                                <th>From Hospital</th>
                                <th>To Recipient Clinic</th>
                                <th>Delivered At</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentCompleted)): ?>
                                <?php foreach ($recentCompleted as $rc): ?>
                                    <tr>
                                        <td>#DEL-<?php echo $rc['id']; ?></td>
                                        <td class="fw-bold text-dark"><?php echo e($rc['supply_name']); ?> (<?php echo $rc['requested_quantity'] . ' ' . e($rc['unit']); ?>)</td>
                                        <td><?php echo e($rc['donor_name']); ?></td>
                                        <td><?php echo e($rc['recipient_name']); ?></td>
                                        <td class="text-muted"><?php echo format_datetime($rc['delivered_at']); ?></td>
                                        <td><span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Delivered</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No completed delivery history recorded yet.</td>
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
