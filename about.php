<?php
/**
 * MediCycle - About the Project & Platform
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'About Us';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="bg-light py-5 border-bottom">
    <div class="container text-center">
        <span class="badge bg-teal-light text-primary px-3 py-2 rounded-pill fw-semibold mb-2" style="background:#ccfbf1; color:#0f766e;">
            About the Initiative
        </span>
        <h1 class="fw-bold text-dark mb-2">Redefining Healthcare Supply Chains</h1>
        <div class="heading-accent-line mx-auto"></div>
        <p class="text-muted mx-auto page-headline" style="max-width: 650px;">
            MediCycle addresses the critical imbalance between medical surplus in major urban facilities and severe resource deficits in rural and charitable clinics.
        </p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5 align-items-center mb-5">
        <div class="col-lg-6">
            <h3 class="fw-bold text-dark mb-3">The Problem We Are Solving</h3>
            <p class="text-secondary leading-relaxed">
                Hospitals routinely discard vast quantities of unused, completely sterile, unopened medical consumables simply because batches reach an arbitrary surplus threshold, departments reorganize, or minor packaging overstock occurs.
            </p>
            <p class="text-secondary leading-relaxed">
                Concurrently, hundreds of community outpatient posts, rural primary centers, and disaster relief NGOs face chronic shortages of essential items like sterile gauze, gloves, crepe bandages, and protective gowns.
            </p>
            <div class="p-3 bg-light rounded-3 border-start border-4 border-danger">
                <strong class="text-danger d-block mb-1"><i class="fas fa-exclamation-triangle me-1"></i> Our Non-Negotiable Safety Scope</strong>
                <small class="text-muted">
                    MediCycle strictly manages non-drug medical supplies and PPE. Prescription medicines, vaccines, and biologics are completely outside our platform boundary to eliminate pharmaceutical diversion and storage risks.
                </small>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="row g-3">
                <div class="col-sm-6">
                    <div class="card p-3 shadow-sm border-0 h-100">
                        <i class="fas fa-boxes-packing text-teal fs-3 mb-2" style="color:#0f766e;"></i>
                        <h6 class="fw-bold text-dark">Surplus Verification</h6>
                        <p class="text-muted small mb-0">Every lot is checked for packaging integrity, lot number, and minimum shelf-life buffer.</p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card p-3 shadow-sm border-0 h-100">
                        <i class="fas fa-handshake-angle text-primary fs-3 mb-2"></i>
                        <h6 class="fw-bold text-dark">Verified Entities</h6>
                        <p class="text-muted small mb-0">Only government-licensed or verified healthcare entities and NGOs can trade lots.</p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card p-3 shadow-sm border-0 h-100">
                        <i class="fas fa-calculator text-success fs-3 mb-2"></i>
                        <h6 class="fw-bold text-dark">Impact Accounting</h6>
                        <p class="text-muted small mb-0">Automatic computation of solid packaging waste averted and monetary funds preserved.</p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="card p-3 shadow-sm border-0 h-100">
                        <i class="fas fa-handshake text-warning fs-3 mb-2"></i>
                        <h6 class="fw-bold text-dark">Direct Handover</h6>
                        <p class="text-muted small mb-0">Secure Handover Verification Codes and direct donor-to-clinic pickups.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
