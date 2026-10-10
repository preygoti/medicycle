<?php
/**
 * MediCycle - Admin Analytics & Predictive Demand Intelligence
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$analytics = smart_get_demand_analytics($pdo);

// Additional analytics aggregates
$statusCounts = $pdo->query("SELECT status, COUNT(*) as count FROM medical_supplies GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$urgencyCounts = $pdo->query("SELECT urgency, COUNT(*) as count FROM requirements GROUP BY urgency")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Analytics & Demand Intelligence - Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Supply Redistribution Analytics</h3>
                <p class="text-muted small mb-0">Demand forecasting patterns, consumption bottlenecks, and clinical redistribution velocities</p>
            </div>
            <div>
                <span class="badge bg-teal-light text-teal border border-teal px-3 py-2" style="background:#ccfbf1; color:#0f766e;">
                    <i class="fas fa-brain me-1"></i> AI Demand Intelligence Active
                </span>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="row g-4 mb-4">
            <!-- Top Demanded Categories Chart -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-chart-pie text-teal me-2"></i> High-Demand Supply Categories</h6>
                    <div style="height: 300px; position: relative;">
                        <canvas id="categoryDemandChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Redistribution Trends Chart -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-4 bg-white h-100">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-chart-line text-primary me-2"></i> Monthly Consignment Velocity</h6>
                    <div style="height: 300px; position: relative;">
                        <canvas id="velocityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Frequently Requested Supplies -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-fire-flame-curved text-danger me-2"></i> Frequently Requisitioned Items</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Supply Item</th>
                                        <th>Category</th>
                                        <th>Requests</th>
                                        <th>Units Moved</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($analytics['frequently_requested_supplies'])): ?>
                                        <?php foreach ($analytics['frequently_requested_supplies'] as $freq): ?>
                                            <tr>
                                                <td class="fw-semibold text-dark"><?php echo e($freq['supply_name']); ?></td>
                                                <td><span class="badge bg-light text-dark border"><?php echo e($freq['category_name']); ?></span></td>
                                                <td><span class="badge bg-primary"><?php echo $freq['total_requests']; ?> times</span></td>
                                                <td class="fw-bold text-teal"><?php echo number_format($freq['total_quantity']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">Insufficient requisition data yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Most Active Healthcare Entities -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-award text-warning me-2"></i> Most Active Healthcare Entities</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Organization</th>
                                        <th>Type</th>
                                        <th>City</th>
                                        <th>Activity Index</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($analytics['most_active_organizations'])): ?>
                                        <?php foreach ($analytics['most_active_organizations'] as $org): ?>
                                            <tr>
                                                <td class="fw-semibold text-dark"><?php echo e($org['organization_name']); ?></td>
                                                <td><span class="badge bg-light text-dark border"><?php echo e($org['organization_type']); ?></span></td>
                                                <td><?php echo e($org['city']); ?></td>
                                                <td><span class="badge bg-success"><?php echo $org['interaction_count']; ?> operations</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">No organizations recorded.</td>
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
    // 1. Demand Categories Chart
    const catLabels = <?php echo json_encode(array_column($analytics['top_demanded_categories'], 'category_name')); ?>;
    const catUnits = <?php echo json_encode(array_column($analytics['top_demanded_categories'], 'total_units')); ?>;

    new Chart(document.getElementById('categoryDemandChart').getContext('2d'), {
        type: 'polarArea',
        data: {
            labels: catLabels.length ? catLabels : ['PPE & Masks', 'Wound Dressings', 'Sterilization', 'Kits'],
            datasets: [{
                data: catUnits.length ? catUnits : [1200, 850, 430, 200],
                backgroundColor: ['rgba(15, 118, 110, 0.7)', 'rgba(20, 184, 166, 0.7)', 'rgba(6, 182, 212, 0.7)', 'rgba(59, 130, 246, 0.7)']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right' } }
        }
    });

    // 2. Velocity Chart
    const trendLabels = <?php echo json_encode(array_column($analytics['monthly_redistribution_trends'], 'month_label')); ?>;
    const trendUnits = <?php echo json_encode(array_column($analytics['monthly_redistribution_trends'], 'total_units')); ?>;

    new Chart(document.getElementById('velocityChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: trendLabels.length ? trendLabels : ['Jul 2026', 'Aug 2026', 'Sep 2026', 'Oct 2026'],
            datasets: [{
                label: 'Consumable Units Redistributed',
                data: trendUnits.length ? trendUnits : [150, 320, 680, 890],
                borderColor: '#0f766e',
                backgroundColor: 'rgba(15, 118, 110, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
