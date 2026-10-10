<?php
/**
 * MediCycle - Supplier Active Consignment Transfers
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];
$search = clean($_GET['search'] ?? '');

$sql = "SELECT d.*, r.requested_quantity, r.purpose, r.handover_code,
               ms.supply_name, ms.unit, c.category_name,
               u_del.name as courier_name, u_del.phone as courier_phone,
               u_ngo.name as ngo_contact, u_ngo.phone as ngo_phone, o_ngo.organization_name as ngo_org, o_ngo.city as ngo_city
        FROM deliveries d
        JOIN requests r ON d.request_id = r.id
        JOIN medical_supplies ms ON r.supply_id = ms.id
        JOIN categories c ON ms.category_id = c.id
        LEFT JOIN users u_del ON d.delivery_partner_id = u_del.id
        JOIN users u_ngo ON r.requester_id = u_ngo.id
        LEFT JOIN organizations o_ngo ON u_ngo.id = o_ngo.user_id
        WHERE ms.supplier_id = ?
        ORDER BY (d.delivery_status != 'Delivered') DESC, d.assigned_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$transfers = $stmt->fetchAll();

$pageTitle = 'Consignment Transfers - MediCycle Supplier';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Active Consignment Transfers</h3>
                <p class="text-muted small mb-0">Track outgoing supply dispatches, assigned courier logistics, and recipient clinic receipts</p>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-inbox me-1"></i> Incoming Requests
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-dolly text-teal me-2"></i> Outgoing Consignments (<?php echo count($transfers); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Delivery ID</th>
                                <th>Supply Item</th>
                                <th>Recipient Clinic</th>
                                <th>Assigned Courier</th>
                                <th>Pickup Verification Code</th>
                                <th>Transit Status</th>
                                <th>Assigned Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($transfers)): ?>
                                <?php foreach ($transfers as $tr): ?>
                                    <tr>
                                        <td>#DEL-<?php echo $tr['id']; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($tr['supply_name']); ?></div>
                                            <span class="badge bg-light text-primary"><?php echo $tr['requested_quantity'] . ' ' . e($tr['unit']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo e($tr['ngo_org'] ?? $tr['ngo_contact']); ?></div>
                                            <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo e($tr['ngo_city']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($tr['courier_name']): ?>
                                                <div class="fw-semibold text-dark"><i class="fas fa-truck text-teal me-1"></i><?php echo e($tr['courier_name']); ?></div>
                                                <small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo e($tr['courier_phone']); ?></small>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Awaiting Courier</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border py-1 px-2 font-monospace">
                                                <i class="fas fa-lock text-teal me-1"></i>Kept by Recipient
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo status_badge($tr['delivery_status']); ?>
                                        </td>
                                        <td class="text-muted"><?php echo format_date($tr['assigned_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No active transfers currently in motion.</td>
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
