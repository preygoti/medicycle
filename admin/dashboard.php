<?php
/**
 * MediCycle - Admin Dashboard
 * System Overview, KPI Cards, Workflow Statistics & Real-time Activity
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

try {
    // KPI 1: Total Users & breakdown
    $userStats = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
    $totalUsers = array_sum($userStats);

    // KPI 2: Verified Organizations
    $orgStats = $pdo->query("SELECT verification_status, COUNT(*) as count FROM organizations GROUP BY verification_status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $verifiedOrgs = $orgStats['verified'] ?? 0;
    $pendingOrgs = $orgStats['pending'] ?? 0;

    // KPI 3: Supplies by Status
    $supplyStats = $pdo->query("SELECT status, COUNT(*) as count FROM medical_supplies GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $activeSupplies = $supplyStats['Available'] ?? 0;
    $pendingSupplies = $supplyStats['Pending'] ?? 0;

    // KPI 4: Pending Requests
    $requestStats = $pdo->query("SELECT status, COUNT(*) as count FROM requests GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $pendingRequests = $requestStats['Pending'] ?? 0;
    $approvedRequests = $requestStats['Approved'] ?? 0;
    $completedTransfers = $requestStats['Completed'] ?? 0;

    // KPI 5: Active Deliveries
    $deliveryStats = $pdo->query("SELECT delivery_status, COUNT(*) as count FROM deliveries GROUP BY delivery_status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $inTransitDeliveries = $deliveryStats['In Transit'] ?? 0;

    // KPI 6: Total Impact Metrics
    $impactTotal = $pdo->query("SELECT COALESCE(SUM(quantity_redistributed), 0) as total_units, 
                                       COALESCE(SUM(estimated_waste_avoided), 0) as total_waste,
                                       COALESCE(SUM(estimated_value_saved), 0) as total_value 
                                FROM impact_metrics")->fetch();

    // Chart Data 1: Supplies by Category
    $catChart = $pdo->query("
        SELECT c.category_name, COUNT(ms.id) as count 
        FROM categories c 
        LEFT JOIN medical_supplies ms ON c.id = ms.category_id 
        GROUP BY c.id, c.category_name 
        ORDER BY count DESC
    ")->fetchAll();

    // Chart Data 2: Monthly Activity Trends
    $monthlyChart = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%b %Y') as month_label, COUNT(*) as request_count 
        FROM requests 
        GROUP BY DATE_FORMAT(created_at, '%b %Y'), YEAR(created_at), MONTH(created_at) 
        ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC 
        LIMIT 6
    ")->fetchAll();

    // Recent Supplies Awaiting Approval
    $pendingSupplyList = $pdo->query("
        SELECT ms.*, u.name as supplier_name, c.category_name 
        FROM medical_supplies ms 
        JOIN users u ON ms.supplier_id = u.id 
        JOIN categories c ON ms.category_id = c.id 
        WHERE ms.status = 'Pending' 
        ORDER BY ms.created_at DESC LIMIT 5
    ")->fetchAll();

    // Recent Organizations Awaiting Verification
    $pendingOrgList = $pdo->query("
        SELECT o.*, u.name as user_name, u.email as user_email, u.phone 
        FROM organizations o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.verification_status = 'pending' 
        ORDER BY o.created_at DESC LIMIT 5
    ")->fetchAll();

    // Recent System Activity
    $recentRequests = $pdo->query("
        SELECT r.*, ms.supply_name, u.name as requester_name, s.name as supplier_name 
        FROM requests r 
        JOIN medical_supplies ms ON r.supply_id = ms.id 
        JOIN users u ON r.requester_id = u.id 
        JOIN users s ON ms.supplier_id = s.id 
        ORDER BY r.requested_at DESC LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $totalUsers = $verifiedOrgs = $activeSupplies = $pendingRequests = $completedTransfers = 0;
    $impactTotal = ['total_units' => 0, 'total_waste' => 0, 'total_value' => 0];
    $catChart = $monthlyChart = $pendingSupplyList = $pendingOrgList = $recentRequests = [];
}

$pageTitle = 'Admin Dashboard - MediCycle';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h3 class="fw-bold text-dark mb-1">Administrative Operations Control</h3>
                <p class="text-muted small mb-0">System health, organization verification, supply oversight & redistribution analytics</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo BASE_URL; ?>/admin/supplies.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-boxes-stacked me-1"></i> Review Supplies
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-file-invoice me-1"></i> Audit Reports
                </a>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Users</span>
                            <h3 class="fw-bold text-dark mb-0 mt-1"><?php echo $totalUsers; ?></h3>
                            <small class="text-muted">
                                <?php echo ($userStats['supplier'] ?? 0); ?> Suppliers &bull; <?php echo ($userStats['ngo'] ?? 0); ?> NGOs
                            </small>
                        </div>
                        <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-users fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Verified Entities</span>
                            <h3 class="fw-bold text-success mb-0 mt-1"><?php echo $verifiedOrgs; ?></h3>
                            <small class="text-danger fw-semibold">
                                <?php echo $pendingOrgs; ?> Pending Review
                            </small>
                        </div>
                        <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success">
                            <i class="fas fa-building-circle-check fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Available Supplies</span>
                            <h3 class="fw-bold text-teal mb-0 mt-1" style="color:#0f766e;"><?php echo $activeSupplies; ?></h3>
                            <small class="text-warning fw-semibold">
                                <?php echo $pendingSupplies; ?> Needs Approval
                            </small>
                        </div>
                        <div class="rounded-3 p-3 text-teal" style="background:#ccfbf1; color:#0f766e;">
                            <i class="fas fa-boxes-stacked fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Completed Transfers</span>
                            <h3 class="fw-bold text-dark mb-0 mt-1"><?php echo $completedTransfers; ?></h3>
                            <small class="text-info fw-semibold">
                                <?php echo $inTransitDeliveries; ?> In Transit
                            </small>
                        </div>
                        <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info">
                            <i class="fas fa-truck-ramp-box fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Impact Bar Banner -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 text-white" style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);">
            <div class="row align-items-center text-center text-md-start g-3">
                <div class="col-md-4 border-end-md">
                    <div class="small opacity-75 text-uppercase fw-semibold">Consumable Waste Diverted</div>
                    <div class="fs-4 fw-bold mt-1"><i class="fas fa-recycle me-2"></i><?php echo number_format($impactTotal['total_waste'], 2); ?> kg</div>
                </div>
                <div class="col-md-4 border-end-md">
                    <div class="small opacity-75 text-uppercase fw-semibold">Units Successfully Redistributed</div>
                    <div class="fs-4 fw-bold mt-1"><i class="fas fa-box-tissue me-2"></i><?php echo number_format($impactTotal['total_units']); ?> items</div>
                </div>
                <div class="col-md-4">
                    <div class="small opacity-75 text-uppercase fw-semibold">Healthcare Costs Saved</div>
                    <div class="fs-4 fw-bold mt-1"><i class="fas fa-indian-rupee-sign me-2"></i>₹<?php echo number_format($impactTotal['total_value'], 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Pending Items Needing Administrator Action -->
        <div class="row g-4 mb-4">
            <!-- Pending Supplies -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-clock text-warning me-2"></i> Supplies Awaiting Approval</h6>
                        <a href="<?php echo BASE_URL; ?>/admin/supplies.php?status=Pending" class="small text-teal text-decoration-none">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Supply Item</th>
                                        <th>Supplier</th>
                                        <th>Qty</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($pendingSupplyList)): ?>
                                        <?php foreach ($pendingSupplyList as $ps): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?php echo e($ps['supply_name']); ?></div>
                                                    <span class="badge bg-light text-dark border"><?php echo e($ps['category_name']); ?></span>
                                                </td>
                                                <td><?php echo e($ps['supplier_name']); ?></td>
                                                <td><span class="badge bg-light text-primary"><?php echo $ps['quantity'] . ' ' . e($ps['unit']); ?></span></td>
                                                <td>
                                                    <a href="<?php echo BASE_URL; ?>/admin/supplies.php?highlight=<?php echo $ps['id']; ?>" class="btn btn-sm btn-primary py-0">Review</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="fas fa-check-circle text-success me-1"></i> All supply submissions approved.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Organizations -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-shield-halved text-danger me-2"></i> Organizations Awaiting Verification</h6>
                        <a href="<?php echo BASE_URL; ?>/admin/organizations.php?status=pending" class="small text-teal text-decoration-none">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Organization</th>
                                        <th>Type</th>
                                        <th>City</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($pendingOrgList)): ?>
                                        <?php foreach ($pendingOrgList as $po): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?php echo e($po['organization_name']); ?></div>
                                                    <small class="text-muted"><?php echo e($po['license_number'] ?? 'No license given'); ?></small>
                                                </td>
                                                <td><span class="badge bg-light text-dark border"><?php echo e($po['organization_type']); ?></span></td>
                                                <td><?php echo e($po['city']); ?></td>
                                                <td>
                                                    <a href="<?php echo BASE_URL; ?>/admin/organizations.php?verify_id=<?php echo $po['id']; ?>" class="btn btn-sm btn-success py-0">Verify</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="fas fa-check-circle text-success me-1"></i> All organizations are verified.
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

        <!-- Charts Section -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-chart-pie text-teal me-2"></i> Supply Inventory by Category</h6>
                    <div style="height: 280px; position: relative;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-chart-line text-primary me-2"></i> Monthly Supply Requisitions</h6>
                    <div style="height: 280px; position: relative;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Supply Requisitions Table -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-hand-holding-medical text-teal me-2"></i> Live Requisition Stream</h6>
                <a href="<?php echo BASE_URL; ?>/admin/requests.php" class="small text-teal text-decoration-none">Manage All Requests</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Req ID</th>
                                <th>Supply Item</th>
                                <th>Supplier</th>
                                <th>NGO Requester</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentRequests)): ?>
                                <?php foreach ($recentRequests as $rq): ?>
                                    <tr>
                                        <td>#<?php echo $rq['id']; ?></td>
                                        <td class="fw-semibold text-dark"><?php echo e($rq['supply_name']); ?></td>
                                        <td><?php echo e($rq['supplier_name']); ?></td>
                                        <td><?php echo e($rq['requester_name']); ?></td>
                                        <td class="fw-bold"><?php echo $rq['requested_quantity']; ?> units</td>
                                        <td><?php echo status_badge($rq['status']); ?></td>
                                        <td class="text-muted"><?php echo format_date($rq['requested_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No request activity recorded yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Category Chart
    const catLabels = <?php echo json_encode(array_column($catChart, 'category_name')); ?>;
    const catData = <?php echo json_encode(array_column($catChart, 'count')); ?>;
    const ctxCat = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: catLabels,
            datasets: [{
                data: catData,
                backgroundColor: ['#0f766e', '#14b8a6', '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6', '#10b981']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });

    // 2. Trend Chart
    const trendLabels = <?php echo json_encode(array_column($monthlyChart, 'month_label')); ?>;
    const trendData = <?php echo json_encode(array_column($monthlyChart, 'request_count')); ?>;
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'bar',
        data: {
            labels: trendLabels.length ? trendLabels : ['Sep 2026', 'Oct 2026'],
            datasets: [{
                label: 'Requests Submitted',
                data: trendData.length ? trendData : [2, 1],
                backgroundColor: '#0f766e',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
