<?php
/**
 * MediCycle - Courier Completed Delivery History
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('delivery');

$userId = $_SESSION['user_id'];
$search = clean($_GET['search'] ?? '');

$sql = "SELECT d.*, r.requested_quantity, ms.supply_name, ms.unit, c.category_name,
               COALESCE(so.organization_name, su.name) as supplier_name,
               COALESCE(no.organization_name, nu.name) as ngo_name, no.city as ngo_city,
               im.estimated_waste_avoided
        FROM deliveries d
        JOIN requests r ON d.request_id = r.id
        JOIN medical_supplies ms ON r.supply_id = ms.id
        JOIN categories c ON ms.category_id = c.id
        JOIN users su ON ms.supplier_id = su.id
        LEFT JOIN organizations so ON su.id = so.user_id
        JOIN users nu ON r.requester_id = nu.id
        LEFT JOIN organizations no ON nu.id = no.user_id
        LEFT JOIN impact_metrics im ON im.request_id = r.id
        WHERE d.delivery_partner_id = ? AND d.delivery_status = 'Delivered'";
$params = [$userId];

if (!empty($search)) {
    $sql .= " AND (ms.supply_name LIKE ? OR d.delivery_location LIKE ? OR so.organization_name LIKE ? OR no.organization_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

$sql .= " ORDER BY d.delivered_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

$totalItems = array_sum(array_column($history, 'requested_quantity'));
$totalWaste = array_sum(array_column($history, 'estimated_waste_avoided'));

$pageTitle = 'Delivery History - MediCycle Courier';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Fulfilled Delivery History</h3>
                <p class="text-muted small mb-0">Record of safely completed medical transport runs and environmental waste diverted</p>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>/delivery/assigned.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-boxes-packing me-1"></i> Active Consignments
                </a>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="small text-muted text-uppercase fw-semibold">Completed Runs</div>
                    <div class="fs-4 fw-bold text-dark mt-1"><?php echo count($history); ?> dispatches</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="small text-muted text-uppercase fw-semibold">Medical Consumables Delivered</div>
                    <div class="fs-4 fw-bold text-teal mt-1"><?php echo number_format($totalItems); ?> units</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="small text-muted text-uppercase fw-semibold">Landfill Waste Diverted</div>
                    <div class="fs-4 fw-bold text-success mt-1"><?php echo number_format($totalWaste, 2); ?> kg</div>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-9">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by medical item, clinic, hospital origin..." value="<?php echo e($search); ?>">
                    </div>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-teal text-white w-100" style="background:#0f766e;">Search</button>
                    <a href="<?php echo BASE_URL; ?>/delivery/history.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- History Table -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-history text-teal me-2"></i> Courier Delivery Log (<?php echo count($history); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Delivery ID</th>
                                <th>Supply Consignment</th>
                                <th>Donor Facility</th>
                                <th>Recipient Clinic</th>
                                <th>Volume Delivered</th>
                                <th>Delivered At</th>
                                <th class="text-end">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($history)): ?>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td>#DEL-<?php echo $h['id']; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($h['supply_name']); ?></div>
                                            <span class="badge bg-light text-dark border"><?php echo e($h['category_name']); ?></span>
                                        </td>
                                        <td><?php echo e($h['supplier_name']); ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo e($h['ngo_name']); ?></div>
                                            <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo e($h['ngo_city']); ?></small>
                                        </td>
                                        <td><span class="fw-bold text-teal"><?php echo $h['requested_quantity'] . ' ' . e($h['unit']); ?></span></td>
                                        <td class="text-muted"><?php echo format_datetime($h['delivered_at']); ?></td>
                                        <td class="text-end">
                                            <a href="<?php echo BASE_URL; ?>/delivery/delivery-details.php?id=<?php echo $h['id']; ?>" class="btn btn-sm btn-outline-secondary py-0">
                                                View Route
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No completed delivery history records.</td>
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
