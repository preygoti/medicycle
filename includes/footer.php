<?php
/**
 * MediCycle - Global HTML Footer
 */
// Check if the current page is an authenticated portal/dashboard page
$currentDir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
$isPortalPage = in_array($currentDir, ['supplier', 'ngo', 'delivery']) || !empty($hideFooter);
?>

<?php if (!$isPortalPage): ?>
<footer class="bg-white border-top mt-auto py-3">
    <div class="container-fluid px-4">
        <div class="text-center">
            <span class="fw-bold text-teal"><i class="fas fa-hand-holding-medical text-success me-1"></i> MediCycle</span>
            <span class="text-muted small ms-2">&copy; <?php echo date('Y'); ?> Smart Medical Supply Redistribution System.</span>
            <div class="text-muted small mt-1" style="font-size: 0.78rem;">
                <i class="fas fa-shield-halved text-primary me-1"></i>
                <strong>Scope & Safety:</strong> Strict protocol for unopened PPE, bandages, sterile wound care & non-drug consumables. Prescription drugs prohibited.
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>

<?php if (function_exists('is_logged_in') && is_logged_in()): ?>
<!-- Universal Sign Out Confirmation Modal (Always at Body Root Level) -->
<div class="modal fade" id="signOutModal" tabindex="-1" aria-labelledby="signOutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3 bg-light">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2 mb-0" id="signOutModalLabel">
                    <span class="rounded-circle p-2 d-inline-flex align-items-center justify-content-center" style="background:#fee2e2; color:#ef4444; width:34px; height:34px;">
                        <i class="fas fa-sign-out-alt" style="font-size:0.9rem;"></i>
                    </span>
                    Sign Out
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-start">
                <p class="mb-1 text-dark fw-semibold" style="font-size:0.98rem;">Are you sure you want to sign out?</p>
                <p class="text-muted small mb-0">You will need to sign back in to access your portal, inventory, and active handovers.</p>
            </div>
            <div class="modal-footer border-top py-2.5 bg-light d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light btn-sm px-3 rounded-pill fw-medium" data-bs-dismiss="modal">Cancel</button>
                <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-danger btn-sm px-4 rounded-pill fw-semibold shadow-xs">
                    <i class="fas fa-sign-out-alt me-1"></i> Sign Out
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap 5.3 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- MediCycle App Scripts -->
<script src="<?php echo BASE_URL; ?>/assets/js/main.js?v=2.9.0_<?php echo file_exists(__DIR__ . '/../assets/js/main.js') ? filemtime(__DIR__ . '/../assets/js/main.js') : time(); ?>"></script>
</body>
</html>
