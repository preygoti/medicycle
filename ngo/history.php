<?php
/**
 * MediCycle - NGO Request History
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];

// Completed requisitions
$stmt = $pdo->prepare("SELECT r.*, s.supply_name, s.unit, 
                              o.organization_name as supplier_name, o.city as supplier_city,
                              m.quantity_redistributed, m.estimated_waste_avoided, m.estimated_value_saved
                       FROM requests r 
                       JOIN medical_supplies s ON r.supply_id = s.id 
                       LEFT JOIN organizations o ON s.supplier_id = o.user_id 
                       LEFT JOIN impact_metrics m ON r.id = m.request_id 
                       WHERE r.requester_id = ? AND r.status = 'Completed' 
                       ORDER BY r.completed_at DESC");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

$pageTitle = 'Requisition History';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Received Supplies History</h3>
                <p class="text-muted small mb-0">Record of confirmed consumable deliveries to your clinical facility</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/ngo/impact.php" class="btn btn-outline-teal btn-sm" style="color:#0f766e; border-color:#0f766e;">
                <i class="fas fa-seedling me-1"></i> View Impact Metrics
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Item Received</th>
                                <th>Supplying Hospital</th>
                                <th>Quantity</th>
                                <th>Clinical Purpose</th>
                                <th>Waste Diverted</th>
                                <th>Value Preserved</th>
                                <th>Received On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($history)): ?>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td><span class="fw-bold text-dark"><?php echo e($h['supply_name']); ?></span></td>
                                        <td>
                                            <span class="fw-semibold text-dark"><?php echo e($h['supplier_name']); ?></span>
                                            <div class="text-muted small"><?php echo e($h['supplier_city']); ?></div>
                                        </td>
                                        <td class="fw-bold text-teal"><?php echo number_format($h['quantity_redistributed'] ?? $h['requested_quantity']) . ' ' . e($h['unit']); ?></td>
                                        <td class="small text-secondary"><?php echo e($h['purpose']); ?></td>
                                        <td class="text-success fw-medium"><?php echo number_format($h['estimated_waste_avoided'] ?? 0, 2); ?> kg</td>
                                        <td class="text-dark">₹<?php echo number_format($h['estimated_value_saved'] ?? 0, 2); ?></td>
                                        <td class="small text-muted"><?php echo format_date($h['completed_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-history fs-3 text-secondary d-block mb-2"></i>
                                        No completed deliveries logged yet.
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
