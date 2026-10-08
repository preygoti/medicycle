<?php
/**
 * MediCycle - How It Works Workflow Guide (Direct Redistribution Model)
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'How It Works';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="bg-light py-5 border-bottom">
    <div class="container text-center">
        <span class="badge bg-teal-light text-primary px-3 py-2 rounded-pill fw-semibold mb-2" style="background:#ccfbf1; color:#0f766e;">
            Direct Redistribution Architecture
        </span>
        <h1 class="fw-bold text-dark mb-2">How MediCycle Works</h1>
        <div class="heading-accent-line mx-auto"></div>
        <p class="text-muted mx-auto page-headline" style="max-width: 650px;">
            A straightforward 4-step direct redistribution pipeline. Healthcare suppliers directly channel surplus consumables to charitable clinics and NGOs.
        </p>
    </div>
</div>

<div class="container py-5">
    <!-- Visual 4-Step Timeline -->
    <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 p-4 border-0 shadow-sm border-top border-4 border-teal">
                <div class="badge bg-teal-light text-primary p-2 fs-6 mb-3 rounded-3" style="background:#ccfbf1; color:#0f766e; width:fit-content;">
                    Step 01
                </div>
                <h5 class="fw-bold text-dark">Supplier Lists Surplus</h5>
                <p class="text-secondary small leading-relaxed">
                    Hospitals, medical stores, and distributors list unexpired, unopened consumables (gloves, bandages, gauze, PPE kits) with batch numbers, packaging status, and pickup location.
                </p>
                <div class="mt-auto pt-2 border-top">
                    <span class="badge bg-success bg-opacity-10 text-success"><i class="fas fa-box-open me-1"></i>Instant Inventory</span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card h-100 p-4 border-0 shadow-sm border-top border-4 border-primary">
                <div class="badge bg-blue-light text-primary p-2 fs-6 mb-3 rounded-3" style="background:#e0f2fe; width:fit-content;">
                    Step 02
                </div>
                <h5 class="fw-bold text-dark">Discover & Request</h5>
                <p class="text-secondary small leading-relaxed">
                    Charitable NGOs and community clinics search available batches or get recommended matches from our priority algorithm. They specify required quantity, urgency, and preferred pickup date.
                </p>
                <div class="mt-auto pt-2 border-top">
                    <span class="badge bg-primary bg-opacity-10 text-primary"><i class="fas fa-search me-1"></i>Smart Match Filter</span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card h-100 p-4 border-0 shadow-sm border-top border-4 border-warning">
                <div class="badge bg-amber-light text-warning p-2 fs-6 mb-3 rounded-3" style="background:#fef3c7; color:#b45309; width:fit-content;">
                    Step 03
                </div>
                <h5 class="fw-bold text-dark">Direct Handover Pass</h5>
                <p class="text-secondary small leading-relaxed">
                    Supplier accepts the request. The system generates a unique <strong>Handover Code</strong>. The supplier packages the items and provides collection notes (e.g. counter pickup times).
                </p>
                <div class="mt-auto pt-2 border-top">
                    <span class="badge bg-warning bg-opacity-10 text-warning"><i class="fas fa-key me-1"></i>Secure Handover Code</span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card h-100 p-4 border-0 shadow-sm border-top border-4 border-success">
                <div class="badge bg-green-light text-success p-2 fs-6 mb-3 rounded-3" style="background:#dcfce7; color:#15803d; width:fit-content;">
                    Step 04
                </div>
                <h5 class="fw-bold text-dark">Verify & Measure Impact</h5>
                <p class="text-secondary small leading-relaxed">
                    The NGO representative presents the code upon collection. Both parties verify physical handover. Inventory updates automatically and ecological waste diverted metrics are recorded.
                </p>
                <div class="mt-auto pt-2 border-top">
                    <span class="badge bg-success bg-opacity-10 text-success"><i class="fas fa-seedling me-1"></i>Impact Recorded</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Safety Scope Section -->
    <div id="safety" class="card border-0 shadow-sm p-4 p-md-5 mb-5 bg-white">
        <div class="row align-items-center gy-4">
            <div class="col-lg-6">
                <span class="text-teal text-uppercase fw-semibold small">Strict Healthcare Protocols</span>
                <h3 class="fw-bold text-dark mb-3">Allowed vs. Prohibited Supplies</h3>
                <p class="text-secondary small leading-relaxed mb-4">
                    To maintain strict hygiene and safety standards, MediCycle strictly prohibits prescription medications, pharmaceuticals, and opened items. Only factory-sealed non-drug consumables are eligible.
                </p>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="p-3 bg-success bg-opacity-10 rounded-3">
                            <h6 class="fw-bold text-success mb-2"><i class="fas fa-check-circle me-1"></i>Permitted Supplies</h6>
                            <ul class="list-unstyled small text-secondary mb-0">
                                <li>• Examination & surgical gloves</li>
                                <li>• N95 / 3-ply surgical masks</li>
                                <li>• Sterile gauze swabs & sponges</li>
                                <li>• Crepe bandages & dressings</li>
                                <li>• Sealed first aid kits</li>
                                <li>• Diagnostic test strips (non-drug)</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-danger bg-opacity-10 rounded-3">
                            <h6 class="fw-bold text-danger mb-2"><i class="fas fa-times-circle me-1"></i>Strictly Prohibited</h6>
                            <ul class="list-unstyled small text-secondary mb-0">
                                <li>• Prescription medications</li>
                                <li>• Scheduled / controlled drugs</li>
                                <li>• Biologics, blood or vaccines</li>
                                <li>• Expired consumables</li>
                                <li>• Compromised or torn seals</li>
                                <li>• Single-use reprocessed items</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="p-4 bg-light rounded-4 border">
                    <h5 class="fw-bold text-dark mb-3"><i class="fas fa-shield-alt text-teal me-2" style="color:#0f766e;"></i>The MediCycle Safety Standard</h5>
                    <div class="d-flex gap-3 mb-3">
                        <i class="fas fa-clipboard-check text-success fs-4 mt-1"></i>
                        <div>
                            <strong class="d-block text-dark small">Unbroken Packaging Integrity</strong>
                            <span class="text-muted small">Every item listed must retain its original manufacturer seal or tamper-evident pouch.</span>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <i class="fas fa-calendar-check text-primary fs-4 mt-1"></i>
                        <div>
                            <strong class="d-block text-dark small">Expiration Buffer Guard</strong>
                            <span class="text-muted small">Items within critical proximity to expiration receive urgent matching alerts for immediate outreach use.</span>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <i class="fas fa-handshake text-warning fs-4 mt-1"></i>
                        <div>
                            <strong class="d-block text-dark small">Verified Direct Collection</strong>
                            <span class="text-muted small">Handover codes ensure complete accountability and trace between donating healthcare facilities and recipient NGOs.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to action -->
    <div class="text-center py-4">
        <h4 class="fw-bold text-dark mb-3">Ready to Start Redistributing?</h4>
        <div class="d-flex justify-content-center gap-3">
            <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-primary px-4 py-2">
                <i class="fas fa-user-plus me-1"></i> Register Your Organization
            </a>
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-secondary px-4 py-2">
                <i class="fas fa-sign-in-alt me-1"></i> Log In
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
