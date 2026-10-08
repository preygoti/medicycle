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
    $statUnits = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM requests WHERE status IN ('Completed', 'Received')")->fetchColumn();
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
    $statSupplies = 0; $statOrgs = 0; $statTransfers = 0; $statUnits = 0; $statWaste = 0;
    $featuredSupplies = [];
}

$pageTitle = 'MediCycle - Smart Medical Supply Redistribution';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<!-- 1. HERO SECTION -->
<section class="hero-section text-center text-lg-start py-5">
    <div class="container py-lg-4">
        <div class="row align-items-center gy-5">
            <div class="col-lg-7">
                <span class="badge bg-teal-light text-white px-3 py-2 mb-3 rounded-pill border border-teal text-uppercase" style="background: rgba(204, 251, 241, 0.2); letter-spacing: 0.05em;">
                    <i class="fas fa-hand-holding-medical me-1"></i> Harvest Ledger Healthcare Edition
                </span>
                <h1 class="hero-title mb-3">
                    Turn Medical Surplus Into<br>
                    <span style="color: #5EEAD4;">Community Impact.</span>
                </h1>
                <p class="hero-subtitle mb-4">
                    MediCycle directly connects hospitals and healthcare suppliers with verified NGOs and charitable clinics. We redirect unexpired, unopened healthcare consumables before they go to waste.
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                    <?php if (is_logged_in()): ?>
                        <?php if ($_SESSION['role'] === 'ngo'): ?>
                            <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="btn btn-light btn-lg fw-semibold text-teal shadow-sm">
                                <i class="fas fa-search me-1"></i> Browse Supplies
                            </a>
                            <a href="<?php echo BASE_URL; ?>/ngo/post-requirement.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-bullhorn me-1"></i> Post Clinical Need
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/supplier/add-supply.php" class="btn btn-light btn-lg fw-semibold text-teal shadow-sm">
                                <i class="fas fa-plus-circle me-1"></i> List Surplus Consumables
                            </a>
                            <a href="<?php echo BASE_URL; ?>/supplier/dashboard.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-chart-pie me-1"></i> Supplier Dashboard
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-light btn-lg fw-semibold text-teal shadow-sm">
                            <i class="fas fa-sign-in-alt me-1"></i> Sign In to Portal
                        </a>
                        <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-user-plus me-1"></i> Register Organization
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card bg-white text-dark shadow-lg border-0 p-4 rounded-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h6 class="fw-bold text-teal mb-0"><i class="fas fa-chart-line me-2"></i>Live Redistribution Ledger</h6>
                        <span class="badge bg-success bg-opacity-10 text-success"><i class="fas fa-circle me-1 small"></i>Real-Time DB</span>
                    </div>
                    <div class="row g-3 text-center">
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3 h-100 d-flex flex-column justify-content-center">
                                <div class="fs-2 fw-bold text-dark"><?php echo number_format($statSupplies); ?></div>
                                <div class="text-muted small fw-medium">Available Batches</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3 h-100 d-flex flex-column justify-content-center">
                                <div class="fs-2 fw-bold text-teal"><?php echo number_format($statOrgs); ?></div>
                                <div class="text-muted small fw-medium">Verified Orgs</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3 h-100 d-flex flex-column justify-content-center">
                                <div class="fs-2 fw-bold text-primary"><?php echo number_format($statTransfers); ?></div>
                                <div class="text-muted small fw-medium">Direct Transfers</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3 h-100 d-flex flex-column justify-content-center">
                                <div class="fs-2 fw-bold text-success"><?php echo number_format($statWaste, 1); ?> kg</div>
                                <div class="text-muted small fw-medium">Waste Diverted</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 text-center">
                        <small class="text-muted" style="font-size: 0.76rem;">
                            <i class="fas fa-shield-alt text-success me-1"></i> Direct Handover Protocol with encrypted collection verification.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 2. PROBLEM & MISSION SECTION -->
<section class="py-5 bg-white">
    <div class="container py-3">
        <div class="text-center mx-auto mb-5" style="max-width: 720px;">
            <span class="text-teal fw-bold text-uppercase small letter-spacing-1">Why MediCycle Exists</span>
            <h2 class="fw-bold text-dark mt-1">Healthcare Surplus Shouldn't Become Waste</h2>
            <p class="text-muted">Every day, valuable unopened clinical consumables expire on hospital shelves while charitable community clinics face critical supply shortages.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-4 text-center">
                    <div class="stat-icon red mx-auto mb-3" style="width: 56px; height: 56px; border-radius: 14px;">
                        <i class="fas fa-trash-alt fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">The Surplus Dilemma</h5>
                    <p class="text-muted small mb-0">Hospitals and medical distributors frequently order overstock or receive batch donations that approach expiration dates, leading to premature disposal of unopened goods.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-4 text-center">
                    <div class="stat-icon amber mx-auto mb-3" style="width: 56px; height: 56px; border-radius: 14px;">
                        <i class="fas fa-first-aid fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">The Community Need</h5>
                    <p class="text-muted small mb-0">Free clinics, mobile rural outreach camps, and NGO healthcare centers continually struggle with basic consumable supplies like surgical masks, sterile gauze, and examination gloves.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-4 text-center">
                    <div class="stat-icon teal mx-auto mb-3" style="width: 56px; height: 56px; border-radius: 14px;">
                        <i class="fas fa-network-wired fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">The Intelligent Connection</h5>
                    <p class="text-muted small mb-0">MediCycle acts as the missing link: providing smart urgency scoring, category matching, and direct physical handovers without administrative roadblocks.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 3. HOW IT WORKS: 4-STEP DIRECT WORKFLOW -->
<section class="py-5 bg-teal-subtle">
    <div class="container py-3">
        <div class="text-center mx-auto mb-5" style="max-width: 700px;">
            <span class="text-teal fw-bold text-uppercase small letter-spacing-1">Direct Redistribution Model</span>
            <h2 class="fw-bold text-dark mt-1">How MediCycle Works</h2>
            <p class="text-muted">A streamlined, 4-step direct peer workflow connecting verified healthcare providers with NGOs.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="stat-icon teal mx-auto mb-3" style="width: 52px; height: 52px; border-radius: 12px;">
                        <span class="fw-bold fs-4">1</span>
                    </div>
                    <h5 class="fw-bold text-dark">List Surplus</h5>
                    <p class="text-muted small mb-0">Suppliers log usable unexpired consumables with batch number, packaging seal status, and physical location.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="stat-icon blue mx-auto mb-3" style="width: 52px; height: 52px; border-radius: 12px;">
                        <span class="fw-bold fs-4">2</span>
                    </div>
                    <h5 class="fw-bold text-dark">Discover & Request</h5>
                    <p class="text-muted small mb-0">NGOs search available batches by category or city, or post custom clinical needs that alert local suppliers.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="stat-icon amber mx-auto mb-3" style="width: 52px; height: 52px; border-radius: 12px;">
                        <span class="fw-bold fs-4">3</span>
                    </div>
                    <h5 class="fw-bold text-dark">Direct Handover</h5>
                    <p class="text-muted small mb-0">Supplier approves the request, coordinates physical pickup date, and issues an encrypted Handover Code.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm text-center p-4">
                    <div class="stat-icon green mx-auto mb-3" style="width: 52px; height: 52px; border-radius: 12px;">
                        <span class="fw-bold fs-4">4</span>
                    </div>
                    <h5 class="fw-bold text-dark">Confirm & Track</h5>
                    <p class="text-muted small mb-0">NGO verifies receipt on-site. Inventory updates instantly and waste diversion metrics are recorded.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 4. FOR SUPPLIERS & FOR NGOS SECTIONS -->
<section class="py-5 bg-white">
    <div class="container py-3">
        <div class="row g-5 align-items-center mb-5 pb-4 border-bottom">
            <div class="col-lg-5">
                <span class="badge bg-teal text-teal px-3 py-2 rounded-pill fw-semibold mb-2">For Healthcare Suppliers</span>
                <h2 class="fw-bold text-dark mb-3">Have Eligible Medical Surplus?</h2>
                <p class="text-muted">Turn surplus stock into life-saving aid for under-resourced clinics rather than writing off unusable goods to medical waste incinerators.</p>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-primary">
                        <i class="fas fa-hospital me-1"></i> Join as Healthcare Supplier
                    </a>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 h-100 bg-light">
                            <div class="fw-bold text-dark mb-1"><i class="fas fa-boxes-stacked text-teal me-2"></i>Effortless Listing</div>
                            <p class="text-muted small mb-0">Register inventory with batch numbers, package seals, and storage specifications in under 2 minutes.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 h-100 bg-light">
                            <div class="fw-bold text-dark mb-1"><i class="fas fa-clock text-teal me-2"></i>Expiry Prioritization</div>
                            <p class="text-muted small mb-0">Automated urgency scoring prioritizes near-expiry items so they reach clinics well before becoming invalid.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 h-100 bg-light">
                            <div class="fw-bold text-dark mb-1"><i class="fas fa-key text-teal me-2"></i>Handover Security</div>
                            <p class="text-muted small mb-0">Each approved request generates a secure PIN code that the recipient presents in person.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 h-100 bg-light">
                            <div class="fw-bold text-dark mb-1"><i class="fas fa-award text-teal me-2"></i>Impact Analytics</div>
                            <p class="text-muted small mb-0">Generate ESG-ready summaries of kilograms diverted from landfills and community lives supported.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 align-items-center">
            <div class="col-lg-7 order-2 order-lg-1">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 h-100 bg-light">
                            <div class="fw-bold text-dark mb-1"><i class="fas fa-search text-green me-2"></i>Verified Search</div>
                            <p class="text-muted small mb-0">Filter live surplus by consumable category, pickup city, packaging condition, and expiry date.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 h-100 bg-light">
                            <div class="fw-bold text-dark mb-1"><i class="fas fa-bullhorn text-green me-2"></i>Broadcast Clinical Needs</div>
                            <p class="text-muted small mb-0">Post specific requirements for upcoming free medical camps and notify nearby registered suppliers.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 h-100 bg-light">
                            <div class="fw-bold text-dark mb-1"><i class="fas fa-certificate text-green me-2"></i>Zero Intermediary Fees</div>
                            <p class="text-muted small mb-0">100% free peer-to-peer connection for verified NGOs, trust hospitals, and charitable dispensaries.</p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 h-100 bg-light">
                            <div class="fw-bold text-dark mb-1"><i class="fas fa-file-invoice text-green me-2"></i>Audited Handover Slips</div>
                            <p class="text-muted small mb-0">Automated collection certificates for internal regulatory and NGO governance audits.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 order-1 order-lg-2">
                <span class="badge bg-green text-green px-3 py-2 rounded-pill fw-semibold mb-2">For NGOs & Clinics</span>
                <h2 class="fw-bold text-dark mb-3">Looking for Medical Supplies?</h2>
                <p class="text-muted">Access factory-sealed personal protective equipment, sterile wound care, dressings, and non-drug kits donated by verified suppliers.</p>
                <div class="mt-4">
                    <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-success">
                        <i class="fas fa-hand-holding-heart me-1"></i> Register as NGO / Clinic
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 5. SMART MATCHING FEATURE HIGHLIGHT -->
<section class="py-5 bg-teal-subtle">
    <div class="container py-3">
        <div class="row align-items-center gy-4">
            <div class="col-lg-6">
                <span class="badge bg-teal text-teal px-3 py-2 rounded-pill fw-semibold mb-2">Algorithmic Resource Allocation</span>
                <h2 class="fw-bold text-dark mb-3">Intelligent Urgency & Proximity Matching</h2>
                <p class="text-muted">MediCycle incorporates a multi-factor recommendation engine that connects surplus batches with recipient needs using clinical urgency, geographical proximity, and shelf-life scoring.</p>
                <ul class="list-unstyled text-secondary small mb-4">
                    <li class="mb-2"><i class="fas fa-check-circle text-teal me-2"></i><strong>Shelf-Life Priority:</strong> Items with less than 60 days remaining are boosted in search results.</li>
                    <li class="mb-2"><i class="fas fa-check-circle text-teal me-2"></i><strong>Proximity Weighting:</strong> Prioritizes local city matching to eliminate cross-state logistics overhead.</li>
                    <li class="mb-2"><i class="fas fa-check-circle text-teal me-2"></i><strong>Condition Verification:</strong> Only factory-sealed and sterile items with intact outer boxes qualify.</li>
                </ul>
                <a href="<?php echo BASE_URL; ?>/how-it-works.php" class="btn btn-outline-primary">
                    <i class="fas fa-cogs me-1"></i> Learn More About Algorithm
                </a>
            </div>
            <div class="col-lg-6">
                <div class="card border-0 shadow-lg p-4 rounded-4 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="match-badge"><i class="fas fa-bolt"></i> 92% Match Score</span>
                        <span class="badge bg-light text-secondary border">Simulated Allocation</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Nitrile Examination Gloves (Size M)</h5>
                    <p class="text-muted small mb-3">Donor: Apollo Healthcare Hub &bull; Ahmedabad</p>
                    <div class="p-3 bg-light rounded-3 mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Clinical Need Match:</span>
                            <span class="fw-bold text-success">100% Category Alignment</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Geographical Proximity:</span>
                            <span class="fw-bold text-teal">Same District (Vadodara &bull; 85 km)</span>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span>Expiry Viability:</span>
                            <span class="fw-bold text-dark">8 Months Remaining</span>
                        </div>
                    </div>
                    <div class="small text-muted text-center">
                        Matched with: <strong>Hope Community Health Clinic</strong> for upcoming tribal vaccination drive.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 6. STRICT SAFETY SCOPE: ALLOWED VS PROHIBITED -->
<section class="py-5 bg-white">
    <div class="container py-3">
        <div class="text-center mx-auto mb-5" style="max-width: 720px;">
            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill fw-semibold mb-2">Safety Protocols</span>
            <h2 class="fw-bold text-dark">Strict Consumable Safety Scope</h2>
            <p class="text-muted">MediCycle is built exclusively for non-drug healthcare consumables. Prescription drugs, controlled substances, and opened packages are strictly barred.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100 p-4 border-start border-4 border-success">
                    <h5 class="fw-bold text-success mb-3">
                        <i class="fas fa-check-circle me-2"></i> Eligible for Redistribution
                    </h5>
                    <ul class="list-unstyled small text-secondary mb-0">
                        <li class="py-2 border-bottom"><i class="fas fa-check text-success me-2"></i><strong>Examination & Surgical Gloves:</strong> Nitrile, latex, vinyl (unopened boxes).</li>
                        <li class="py-2 border-bottom"><i class="fas fa-check text-success me-2"></i><strong>Respiratory & Facial Protection:</strong> 3-ply surgical masks, N95 respirators, face shields.</li>
                        <li class="py-2 border-bottom"><i class="fas fa-check text-success me-2"></i><strong>Wound Care & Dressing:</strong> Sterile gauze pads, crepe bandages, surgical tape, cotton rolls.</li>
                        <li class="py-2 border-bottom"><i class="fas fa-check text-success me-2"></i><strong>First Aid & Emergency Kits:</strong> Factory-sealed non-drug trauma kits.</li>
                        <li class="py-2"><i class="fas fa-check text-success me-2"></i><strong>Diagnostic Consumables:</strong> Non-drug test strips, sterile lancets, disposable thermometers.</li>
                    </ul>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100 p-4 border-start border-4 border-danger">
                    <h5 class="fw-bold text-danger mb-3">
                        <i class="fas fa-times-circle me-2"></i> Strictly Prohibited Items
                    </h5>
                    <ul class="list-unstyled small text-secondary mb-0">
                        <li class="py-2 border-bottom"><i class="fas fa-times text-danger me-2"></i><strong>Prescription Medications:</strong> Antibiotics, analgesics, cardiovascular pills, injectables.</li>
                        <li class="py-2 border-bottom"><i class="fas fa-times text-danger me-2"></i><strong>Expired Goods:</strong> Any consumable that has reached or surpassed its expiration date.</li>
                        <li class="py-2 border-bottom"><i class="fas fa-times text-danger me-2"></i><strong>Compromised Packaging:</strong> Broken factory seals, torn peel-pouches, water-damaged cartons.</li>
                        <li class="py-2 border-bottom"><i class="fas fa-times text-danger me-2"></i><strong>Controlled Substances:</strong> Scheduled pharmaceuticals or narcotic compounds.</li>
                        <li class="py-2"><i class="fas fa-times text-danger me-2"></i><strong>Hazardous Bio-Materials:</strong> Used sharps, blood products, or contaminated devices.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 7. LIVE SURPLUS INVENTORY HIGHLIGHT -->
<section class="py-5 bg-teal-subtle">
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <span class="text-teal fw-bold text-uppercase small">Surplus Medical Inventory</span>
                <h2 class="fw-bold text-dark mb-1">Available Medical Supplies</h2>
                <p class="text-muted mb-0 small">Eligible surplus lots ready for immediate request and direct collection</p>
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
                                    <?php echo e(substr($supply['description'] ?? 'Surplus unexpired medical consumable lot in original factory seal.', 0, 85)) . '...'; ?>
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
                                        <span class="text-muted">City:</span>
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

<!-- 8. FINAL CALL TO ACTION -->
<section class="py-5 bg-white text-center">
    <div class="container py-4">
        <div class="mx-auto" style="max-width: 680px;">
            <h2 class="fw-bold text-dark mb-3">Ready to Join the Movement?</h2>
            <p class="text-muted mb-4">
                Whether you are a healthcare supplier with surplus inventory or an NGO clinic in need of medical consumables, MediCycle provides the direct, verified bridge.
            </p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-user-plus me-1"></i> Register Your Organization
                </a>
                <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-sign-in-alt me-1"></i> Sign In
                </a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
