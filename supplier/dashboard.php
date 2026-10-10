<?php
/**
 * MediCycle - Supplier Dashboard
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];

try {
    // 1. My Total Supplies
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_supplies WHERE supplier_id = ?");
    $stmt->execute([$userId]);
    $totalSupplies = $stmt->fetchColumn();

    // 2. Available Supplies
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_supplies WHERE supplier_id = ? AND status = 'Available'");
    $stmt->execute([$userId]);
    $availableSupplies = $stmt->fetchColumn();

    // 3. Pending Incoming Requests
    $stmt = $pdo->prepare("SELECT COUNT(r.id) FROM requests r 
                           JOIN medical_supplies s ON r.supply_id = s.id 
                           WHERE s.supplier_id = ? AND r.status = 'Pending'");
    $stmt->execute([$userId]);
    $pendingRequests = $stmt->fetchColumn();

    // 4. Completed Transfers
    $stmt = $pdo->prepare("SELECT COUNT(r.id) FROM requests r 
                           JOIN medical_supplies s ON r.supply_id = s.id 
                           WHERE s.supplier_id = ? AND r.status = 'Completed'");
    $stmt->execute([$userId]);
    $completedTransfers = $stmt->fetchColumn();

    // 5. Total Items Redistributed & Waste Saved
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(m.quantity_redistributed), 0) as total_qty, 
                                  COALESCE(SUM(m.estimated_waste_avoided), 0) as waste_avoided 
                           FROM impact_metrics m 
                           JOIN requests r ON m.request_id = r.id 
                           JOIN medical_supplies s ON r.supply_id = s.id 
                           WHERE s.supplier_id = ?");
    $stmt->execute([$userId]);
    $impactData = $stmt->fetch();

    // 6. Recent Incoming Requests (with NGO details)
    $stmt = $pdo->prepare("SELECT r.*, s.supply_name, s.unit, u.name as requester_name, o.organization_name, o.city 
                           FROM requests r 
                           JOIN medical_supplies s ON r.supply_id = s.id 
                           JOIN users u ON r.requester_id = u.id 
                           LEFT JOIN organizations o ON u.id = o.user_id 
                           WHERE s.supplier_id = ? 
                           ORDER BY r.requested_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recentRequests = $stmt->fetchAll();

    // 7. Recent Supplies added
    $stmt = $pdo->prepare("SELECT s.*, c.category_name 
                           FROM medical_supplies s 
                           JOIN categories c ON s.category_id = c.id 
                           WHERE s.supplier_id = ? 
                           ORDER BY s.created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recentSupplies = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Supplier dashboard error: " . $e->getMessage());
    $totalSupplies = $availableSupplies = $pendingRequests = $completedTransfers = 0;
    $impactData = ['total_qty' => 0, 'waste_avoided' => 0];
    $recentRequests = [];
    $recentSupplies = [];
}

$pageTitle = 'Supplier Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Supplier Portal</h3>
                <div class="heading-accent-line start" style="width: 45px; height: 3px; margin: 0.35rem 0 0.5rem;"></div>
                <p class="text-muted small mb-0 page-headline">Overview for <?php echo e($_SESSION['org_name']); ?></p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?php echo BASE_URL; ?>/supplier/add-supply.php" class="btn btn-teal text-white shadow-xs" style="background:#0f766e;">
                    <i class="fas fa-plus-circle me-1"></i> Add Medical Item
                </a>
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php" class="btn btn-outline-secondary position-relative">
                    <i class="fas fa-inbox me-1"></i> Requests
                    <?php if ($pendingRequests > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-1"><?php echo $pendingRequests; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number"><?php echo number_format($totalSupplies); ?></div>
                        <div class="stat-label">Total Listings</div>
                    </div>
                    <div class="stat-icon teal">
                        <i class="fas fa-boxes-stacked"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-success"><?php echo number_format($availableSupplies); ?></div>
                        <div class="stat-label">Available Lots</div>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-warning"><?php echo number_format($pendingRequests); ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                    <div class="stat-icon amber">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-primary"><?php echo number_format($completedTransfers); ?></div>
                        <div class="stat-label">Completed Transfers</div>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-truck-ramp-box"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Environmental & Social Impact Summary Banner -->
        <div class="card border-0 shadow-sm mb-4 bg-teal-light p-3" style="background:#f0fdfa; border-left: 4px solid #0f766e !important;">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-white text-teal p-3 fs-4 shadow-sm" style="color:#0f766e;">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0">Redistribution Impact Footprint</h6>
                        <span class="text-muted small">Your facility has diverted <strong><?php echo number_format($impactData['waste_avoided'], 2); ?> kg</strong> of consumable waste and provided <strong><?php echo number_format($impactData['total_qty']); ?> units</strong> to community clinics.</span>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>/supplier/analytics.php" class="btn btn-sm btn-outline-teal" style="color:#0f766e; border-color:#0f766e;">View Impact Report</a>
            </div>
        </div>

        <!-- Recent Supplies Listing (Full Width) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-boxes-stacked text-teal me-2" style="color:#0f766e;"></i>Recent Supplies</h6>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo BASE_URL; ?>/supplier/add-supply.php" class="btn btn-sm btn-teal text-white rounded-pill px-3" style="background:#0f766e;">
                        <i class="fas fa-plus-circle me-1"></i> Add Item
                    </a>
                    <a href="<?php echo BASE_URL; ?>/supplier/inventory.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        View All Inventory
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Supply Item</th>
                                <th>Category</th>
                                <th>Available Stock</th>
                                <th>Batch Number</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentSupplies)): ?>
                                <?php foreach ($recentSupplies as $supply): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/supplier/view-supply.php?id=<?php echo $supply['id']; ?>" class="fw-semibold text-dark text-decoration-none d-block">
                                                <?php echo e($supply['supply_name']); ?>
                                            </a>
                                            <small class="text-muted">Added: <?php echo format_date($supply['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?php echo e($supply['category_name'] ?? 'General Medical'); ?></span>
                                        </td>
                                        <td class="fw-bold text-dark">
                                            <?php echo number_format($supply['quantity']) . ' ' . e(format_unit($supply['unit'])); ?>
                                        </td>
                                        <td>
                                            <span class="font-monospace small text-muted"><?php echo e($supply['batch_number'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <span class="small <?php echo (strtotime($supply['expiry_date']) < strtotime('+60 days')) ? 'text-warning fw-semibold' : 'text-muted'; ?>">
                                                <i class="fas fa-calendar-day me-1"></i><?php echo format_date($supply['expiry_date']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo status_badge($supply['status']); ?></td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex align-items-center gap-2 justify-content-end">
                                                <a href="<?php echo BASE_URL; ?>/supplier/view-supply.php?id=<?php echo $supply['id']; ?>" class="btn btn-sm btn-outline-secondary rounded-2 py-1 px-2 shadow-xs" style="font-size:0.78rem;" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/supplier/edit-supply.php?id=<?php echo $supply['id']; ?>" class="btn btn-sm btn-outline-teal rounded-2 py-1 px-2 shadow-xs" style="border-color:#0f766e; color:#0f766e; font-size:0.78rem;" title="Edit Item">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted small">
                                        You haven't added any supplies yet.<br>
                                        <a href="<?php echo BASE_URL; ?>/supplier/add-supply.php" class="btn btn-sm btn-teal text-white mt-2" style="background:#0f766e;">Add Your First Supply</a>
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
