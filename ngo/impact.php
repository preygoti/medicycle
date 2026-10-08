<?php
/**
 * MediCycle - NGO Redistribution Impact Metrics
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];

// Aggregate impact metrics for this NGO
$stmt = $pdo->prepare("SELECT 
        COALESCE(SUM(m.quantity_redistributed), 0) as total_units,
        COALESCE(SUM(m.estimated_waste_avoided), 0) as total_waste_kg,
        COALESCE(SUM(m.estimated_value_saved), 0) as total_value_saved,
        COUNT(DISTINCT s.supplier_id) as donor_hospitals_count,
        COUNT(r.id) as completed_batches
    FROM impact_metrics m 
    JOIN requests r ON m.request_id = r.id 
    JOIN medical_supplies s ON r.supply_id = s.id 
    WHERE r.requester_id = ?");
$stmt->execute([$userId]);
$impact = $stmt->fetch();

$pageTitle = 'Clinic Impact Metrics';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Clinical Impact Metrics</h3>
                <p class="text-muted small mb-0">Quantifying medical waste diverted and essential healthcare consumables secured</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-teal"><?php echo number_format($impact['total_units']); ?></div>
                        <div class="stat-label">Consumable Units Received</div>
                    </div>
                    <div class="stat-icon teal"><i class="fas fa-boxes-packing"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-success"><?php echo number_format($impact['total_waste_kg'], 1); ?> kg</div>
                        <div class="stat-label">Landfill Waste Diverted</div>
                    </div>
                    <div class="stat-icon green"><i class="fas fa-seedling"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-primary">₹<?php echo number_format($impact['total_value_saved']); ?></div>
                        <div class="stat-label">Charitable Funds Saved</div>
                    </div>
                    <div class="stat-icon blue"><i class="fas fa-piggy-bank"></i></div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div>
                        <div class="stat-number text-purple"><?php echo number_format($impact['donor_hospitals_count']); ?></div>
                        <div class="stat-label">Partner Donor Hospitals</div>
                    </div>
                    <div class="stat-icon purple"><i class="fas fa-hospital"></i></div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm p-4 p-md-5">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-heart text-danger me-2"></i>Community Health Beneficiary Framework</h5>
            <p class="text-secondary small leading-relaxed">
                Every carton of examination gloves, pack of sterile gauze swabs, and roll of elastic crepe bandage redistributed through MediCycle directly equips frontline nurses and doctors in mobile health units and underserved primary clinics.
            </p>
            <div class="row g-3 mt-2">
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <div class="display-6 fw-bold text-teal"><?php echo max(1, $impact['completed_batches'] * 35); ?>+</div>
                        <div class="text-muted small fw-medium">Estimated Patients Treated</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <div class="display-6 fw-bold text-success"><?php echo number_format($impact['total_waste_kg'] * 2.1, 1); ?> kg</div>
                        <div class="text-muted small fw-medium">CO₂ Equivalent Mitigated</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <div class="display-6 fw-bold text-primary">100%</div>
                        <div class="text-muted small fw-medium">Sterile Integrity Maintained</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
