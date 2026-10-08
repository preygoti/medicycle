<?php
/**
 * MediCycle - Supplier Impact & Analytics
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];

// Aggregated impact statistics
$stmt = $pdo->prepare("SELECT 
        COALESCE(SUM(m.quantity_redistributed), 0) as total_units,
        COALESCE(SUM(m.estimated_waste_avoided), 0) as total_waste_kg,
        COALESCE(SUM(m.estimated_value_saved), 0) as total_value_inr,
        COUNT(DISTINCT r.requester_id) as beneficiary_orgs_count
    FROM impact_metrics m
    JOIN requests r ON m.request_id = r.id
    JOIN medical_supplies s ON r.supply_id = s.id
    WHERE s.supplier_id = ?");
$stmt->execute([$userId]);
$metrics = $stmt->fetch();

// Supplies distribution by category
$catStmt = $pdo->prepare("SELECT c.category_name, COUNT(s.id) as count 
                          FROM medical_supplies s 
                          JOIN categories c ON s.category_id = c.id 
                          WHERE s.supplier_id = ? 
                          GROUP BY c.id");
$catStmt->execute([$userId]);
$catStats = $catStmt->fetchAll();

$catLabels = [];
$catCounts = [];
foreach ($catStats as $cs) {
    $catLabels[] = $cs['category_name'];
    $catCounts[] = (int)$cs['count'];
}

$pageTitle = 'Supplier Analytics';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Impact Analytics</h3>
                <p class="text-muted small mb-0">Quantified ecological and clinical redistribution metrics</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-teal"><?php echo number_format($metrics['total_units']); ?></div>
                        <div class="stat-label">Units Donated</div>
                    </div>
                    <div class="stat-icon teal"><i class="fas fa-hand-holding-medical"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-success"><?php echo number_format($metrics['total_waste_kg'], 1); ?> kg</div>
                        <div class="stat-label">Packaging Diverted</div>
                    </div>
                    <div class="stat-icon green"><i class="fas fa-seedling"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-primary">₹<?php echo number_format($metrics['total_value_inr']); ?></div>
                        <div class="stat-label">Community Savings</div>
                    </div>
                    <div class="stat-icon blue"><i class="fas fa-coins"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-purple"><?php echo number_format($metrics['beneficiary_orgs_count']); ?></div>
                        <div class="stat-label">Beneficiary Clinics</div>
                    </div>
                    <div class="stat-icon purple"><i class="fas fa-clinic-medical"></i></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100 p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-chart-pie text-teal me-2" style="color:#0f766e;"></i>Supplies by Category</h6>
                    <div style="height: 280px;" class="d-flex align-items-center justify-content-center">
                        <?php if (!empty($catCounts)): ?>
                            <canvas id="categoryChart"></canvas>
                        <?php else: ?>
                            <span class="text-muted small">No category distribution data available yet.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100 p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-award text-warning me-2"></i>Sustainability Impact Framework</h6>
                    <p class="text-secondary small leading-relaxed">
                        Medical supply manufacturing consumes substantial raw plastics, sterile foils, and high-energy sterilization. When unexpired consumables are redirected rather than discarded:
                    </p>
                    <ul class="list-unstyled small text-secondary">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>Landfill Diversion:</strong> Averts premature incineration of non-hazardous clinical plastics.</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>Financial Relief:</strong> Eliminates consumable procurement overhead for low-budget rural outpatient dispensaries.</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>Carbon Footprint:</strong> Avoids re-manufacturing and long-haul transportation emissions.</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('categoryChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($catLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($catCounts); ?>,
                    backgroundColor: ['#0f766e', '#0284c7', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#64748b']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
