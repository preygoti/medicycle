<?php
/**
 * MediCycle - Smart Medical Supply Redistribution Management System
 * Harvest Ledger-style direct redistribution model: Suppliers <-> NGOs/Clinics
 */
require_once __DIR__ . '/config/config.php';

// Fetch dynamic platform impact metrics from the database
try {
    $statSupplies = $pdo->query("SELECT COUNT(*) FROM medical_supplies WHERE status = 'Available'")->fetchColumn();
    $statOrgs = $pdo->query("SELECT COUNT(*) FROM organizations WHERE verification_status = 'verified'")->fetchColumn();
    $statTransfers = $pdo->query("SELECT COUNT(*) FROM requests WHERE status IN ('Completed', 'Received')")->fetchColumn();
    $statWaste = $pdo->query("SELECT COALESCE(SUM(estimated_waste_avoided), 0) FROM impact_metrics")->fetchColumn();
    
    // Fetch featured available medical supplies
    $featuredStmt = $pdo->query("SELECT s.*, c.category_name, o.organization_name, o.city 
                                  FROM medical_supplies s 
                                  JOIN categories c ON s.category_id = c.id 
                                  JOIN organizations o ON s.supplier_id = o.user_id 
                                  WHERE s.status = 'Available' AND s.expiry_date > CURDATE()
                                  ORDER BY s.priority_score DESC, s.created_at DESC 
                                  LIMIT 4");
    $featuredSupplies = $featuredStmt->fetchAll();
} catch (PDOException $e) {
    $statSupplies = 0; $statOrgs = 0; $statTransfers = 0; $statWaste = 0;
    $featuredSupplies = [];
}

$pageTitle = 'Home - Medical Supply Redistribution';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<!-- Harvest Ledger-Style Hero Section -->
<section class="hero-section text-center text-lg-start py-5">
    <div class="container py-lg-4">
        <div class="row align-items-center gy-4">
            <div class="col-lg-7">
                <span class="badge bg-teal-light text-white px-3 py-2 mb-3 rounded-pill border border-teal text-uppercase" style="background: rgba(204, 251, 241, 0.2); letter-spacing: 0.05em;">
                    <i class="fas fa-recycle me-1"></i> Harvest Ledger Healthcare Edition
                </span>
                <h1 class="hero-title text-white mb-3">
                    Turn Medical Surplus Into<br>
                    <span style="color: #5eead4;">Community Impact.</span>
                </h1>
                <p class="hero-subtitle mb-4">
                    MediCycle directly connects healthcare suppliers with verified NGOs and charitable clinics. We redirect eligible, unopened medical consumables to those who need them before they go to waste.
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                    <?php if (is_logged_in()): ?>
                        <?php if ($_SESSION['role'] === 'ngo'): ?>
                            <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="btn btn-light btn-lg fw-semibold text-teal shadow-sm">
                                <i class="fas fa-search me-2"></i> Find Available Supplies
                            </a>
                            <a href="<?php echo BASE_URL; ?>/ngo/post-requirement.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-bullhorn me-2"></i> Post Supply Need
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/supplier/add-supply.php" class="btn btn-light btn-lg fw-semibold text-teal shadow-sm">
                                <i class="fas fa-plus-circle me-2"></i> List Surplus Supply
                            </a>
                            <a href="<?php echo BASE_URL; ?>/supplier/dashboard.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-th-large me-2"></i> Supplier Dashboard
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-light btn-lg fw-semibold text-teal shadow-sm">
                            <i class="fas fa-search me-2"></i> Find Supplies
                        </a>
                        <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-box-open me-2"></i> List Surplus Supplies
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card bg-white text-dark shadow-lg border-0 p-4 rounded-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h6 class="fw-bold text-teal mb-0"><i class="fas fa-chart-line me-2"></i>Redistribution Ledger</h6>
                        <span class="badge bg-success bg-opacity-10 text-success"><i class="fas fa-circle me-1 small"></i>Live Metrics</span>
                    </div>
                    <div class="row g-3 text-center">
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="fs-2 fw-bold text-dark"><?php echo number_format($statSupplies); ?></div>
                                <div class="text-muted small fw-medium">Available Surplus Batches</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="fs-2 fw-bold text-teal"><?php echo number_format($statOrgs); ?></div>
                                <div class="text-muted small fw-medium">Active Organizations</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="fs-2 fw-bold text-primary"><?php echo number_format($statTransfers); ?></div>
                                <div class="text-muted small fw-medium">Handovers Completed</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="fs-2 fw-bold text-success"><?php echo number_format($statWaste, 1); ?> kg</div>
                                <div class="text-muted small fw-medium">Medical Waste Diverted</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 text-center">
                        <small class="text-muted" style="font-size: 0.72rem;">
                            <i class="fas fa-shield-alt text-success me-1"></i> Direct supplier-to-NGO handovers with code verification.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Safety & Domain Scope Banner -->
<section class="bg-light border-bottom py-3">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-teal bg-opacity-10 text-teal p-2 fs-5">
                    <i class="fas fa-check-shield"></i>
                </div>
                <div>
                    <strong class="d-block text-dark small">Strict Safety Scope: Non-Drug Consumables Only</strong>
                    <span class="text-muted small">We redistribute unopened PPE, examination gloves, sterile bandages, dressing packs, and non-drug test strips. Prescription medicines and damaged items are strictly prohibited.</span>
                </div>
            </div>
            <a href="<?php echo BASE_URL; ?>/how-it-works.php" class="btn btn-sm btn-outline-secondary">Redistribution Guide</a>
        </div>
    </div>
</section>

<!-- Active Live Surplus Feed -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="text-teal fw-semibold text-uppercase small">Surplus Medical Inventory</span>
                <h2 class="fw-bold text-dark mb-1">Available Medical Supplies</h2>
                <p class="text-muted mb-0 small">Eligible surplus lots ready for immediate request and pickup</p>
            </div>
            <div>
                <?php if (is_logged_in() && $_SESSION['role'] === 'ngo'): ?>
                    <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="btn btn-outline-primary btn-sm">
                        Browse Full Catalog <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-primary btn-sm">
                        Sign In to Request <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-4">
            <?php if (!empty($featuredSupplies)): ?>
                <?php foreach ($featuredSupplies as $supply): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card h-100 card-hover border-0 shadow-sm">
                            <div class="card-body p-4 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-light text-secondary border"><?php echo e($supply['category_name']); ?></span>
                                    <?php echo priority_badge($supply['priority_level']); ?>
                                </div>
                                <h5 class="fw-bold text-dark mt-2 mb-2 line-clamp-2"><?php echo e($supply['supply_name']); ?></h5>
                                <p class="text-muted small flex-grow-1 mb-3" style="font-size: 0.83rem;">
                                    <?php echo e(substr($supply['description'], 0, 90)) . '...'; ?>
                                </p>
                                
                                <div class="bg-light p-2 rounded-2 mb-3 small">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Available:</span>
                                        <span class="fw-bold text-teal"><?php echo $supply['quantity'] . ' ' . e($supply['unit']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Expiry Date:</span>
                                        <span class="fw-semibold text-dark"><?php echo format_date($supply['expiry_date']); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Supplier City:</span>
                                        <span class="fw-medium text-dark"><i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo e($supply['city']); ?></span>
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <?php if (is_logged_in() && $_SESSION['role'] === 'ngo'): ?>
                                        <a href="<?php echo BASE_URL; ?>/ngo/supply-details.php?id=<?php echo $supply['id']; ?>" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-hand-holding-heart me-1"></i> Request Supply
                                        </a>
                                    <?php elseif (is_logged_in() && $_SESSION['role'] === 'supplier'): ?>
                                        <a href="<?php echo BASE_URL; ?>/supplier/inventory.php" class="btn btn-outline-secondary btn-sm w-100">
                                            <i class="fas fa-eye me-1"></i> View Inventory
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-secondary btn-sm w-100">
                                            <i class="fas fa-lock me-1"></i> Sign In to Request
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 text-muted">
                    <p>No active supplies available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Harvest Ledger-Style 4-Step Direct Workflow -->
<section class="bg-light py-5">
    <div class="container">
        <div class="text-center max-w-700 mx-auto mb-5">
            <span class="text-teal fw-semibold text-uppercase small">Direct Redistribution Model</span>
            <h2 class="fw-bold text-dark">How MediCycle Works</h2>
            <p class="text-muted">A streamlined direct connection between healthcare donors and recipient clinics</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="rounded-circle bg-teal-light text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background:#ccfbf1;">
                        <i class="fas fa-boxes-stacked fs-4" style="color:#0f766e;"></i>
                    </div>
                    <h5 class="fw-bold">1. List Surplus</h5>
                    <p class="text-muted small">Medical stores & hospitals list eligible surplus consumables (gloves, masks, gauze, bandages, test kits).</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="rounded-circle bg-blue-light text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background:#e0f2fe;">
                        <i class="fas fa-search-location fs-4" style="color:#0284c7;"></i>
                    </div>
                    <h5 class="fw-bold">2. Discover & Request</h5>
                    <p class="text-muted small">NGOs & free clinics search available batches by category or urgency and submit a collection request.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="rounded-circle bg-amber-light text-warning mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background:#fef3c7;">
                        <i class="fas fa-handshake fs-4" style="color:#b45309;"></i>
                    </div>
                    <h5 class="fw-bold">3. Direct Handover</h5>
                    <p class="text-muted small">Supplier accepts the request, marks it ready, and issues a secure Handover Code for direct collection.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="rounded-circle bg-green-light text-success mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background:#dcfce7;">
                        <i class="fas fa-chart-line fs-4" style="color:#15803d;"></i>
                    </div>
                    <h5 class="fw-bold">4. Track Impact</h5>
                    <p class="text-muted small">NGO confirms receipt. Inventory updates instantly and waste diverted statistics are recorded.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-5 bg-white">
    <div class="container text-center py-4">
        <div class="max-w-700 mx-auto">
            <h2 class="fw-bold text-dark mb-3">Join the Medical Supply Redistribution Movement</h2>
            <p class="text-muted mb-4">
                Help prevent thousands of kilograms of usable healthcare consumables from ending up in landfills while supporting under-resourced community health clinics.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-user-plus me-1"></i> Register Your Organization
                </a>
                <a href="<?php echo BASE_URL; ?>/how-it-works.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-info-circle me-1"></i> How It Works
                </a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
