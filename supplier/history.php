<?php
/**
 * MediCycle - Supplier Supply History
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];

// Completed redistribution transactions
$stmt = $pdo->prepare("SELECT r.*, s.supply_name, s.unit, 
                              u.name as recipient_name, o.organization_name, o.city,
                              m.quantity_redistributed, m.estimated_waste_avoided, m.estimated_value_saved
                       FROM requests r 
                       JOIN medical_supplies s ON r.supply_id = s.id 
                       JOIN users u ON r.requester_id = u.id 
                       LEFT JOIN organizations o ON u.id = o.user_id 
                       LEFT JOIN impact_metrics m ON r.id = m.request_id 
                       WHERE s.supplier_id = ? AND r.status = 'Completed' 
                       ORDER BY r.completed_at DESC");
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

$pageTitle = 'Redistribution History';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Completed Redistribution History</h3>
                <p class="text-muted small mb-0">Record of confirmed medical supply donations and their verified clinical impact</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Supply Description</th>
                                <th>Recipient Organization</th>
                                <th>Quantity Handed Over</th>
                                <th>Waste Diverted</th>
                                <th>Est. Value Saved</th>
                                <th>Completion Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($history)): ?>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark"><?php echo e($h['supply_name']); ?></span>
                                            <div class="text-muted small"><?php echo e($h['purpose'] ?? 'Clinical outreach'); ?></div>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark"><?php echo e($h['organization_name'] ?? $h['recipient_name']); ?></span>
                                            <div class="text-muted small"><i class="fas fa-map-marker-alt me-1"></i><?php echo e($h['city']); ?></div>
                                        </td>
                                        <td class="fw-bold text-teal"><?php echo number_format($h['quantity_redistributed'] ?? $h['requested_quantity']) . ' ' . e($h['unit']); ?></td>
                                        <td class="text-success fw-medium"><?php echo number_format($h['estimated_waste_avoided'] ?? 0, 2); ?> kg</td>
                                        <td class="text-dark">₹<?php echo number_format($h['estimated_value_saved'] ?? 0, 2); ?></td>
                                        <td class="small text-muted"><?php echo format_date($h['completed_at'] ?? $h['requested_at']); ?></td>
                                        <td><span class="badge bg-success"><i class="fas fa-check-double me-1"></i>Completed</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-history fs-3 text-secondary d-block mb-2"></i>
                                        No completed redistribution transactions recorded yet.
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
