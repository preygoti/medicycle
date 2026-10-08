<?php
/**
 * MediCycle - Global HTML Footer
 */
?>
<?php
// Check if the current page is an authenticated portal/dashboard page
$currentDir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
$isPortalPage = in_array($currentDir, ['admin', 'supplier', 'ngo', 'delivery']) || !empty($hideFooter);
?>

<?php if (!$isPortalPage): ?>
<footer class="bg-white border-top mt-auto py-4">
    <div class="container-fluid px-4">
        <div class="row align-items-center gy-3">
            <div class="col-md-6 text-center text-md-start">
                <span class="fw-bold text-teal"><i class="fas fa-hand-holding-medical text-success me-1"></i> MediCycle</span>
                <span class="text-muted small ms-2">&copy; <?php echo date('Y'); ?> Smart Medical Supply Redistribution System.</span>
                <div class="text-muted small mt-1" style="font-size: 0.78rem;">
                    <i class="fas fa-shield-halved text-primary me-1"></i>
                    <strong>Scope & Safety:</strong> Strict protocol for unopened PPE, bandages, sterile wound care & non-drug consumables. Prescription drugs prohibited.
                </div>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <ul class="list-inline mb-0 small">
                    <li class="list-inline-item"><a href="<?php echo BASE_URL; ?>/index.php" class="text-decoration-none text-muted">Home</a></li>
                    <li class="list-inline-item">&bull;</li>
                    <li class="list-inline-item"><a href="<?php echo BASE_URL; ?>/about.php" class="text-decoration-none text-muted">About OEP</a></li>
                    <li class="list-inline-item">&bull;</li>
                    <li class="list-inline-item"><a href="<?php echo BASE_URL; ?>/how-it-works.php" class="text-decoration-none text-muted">Workflow</a></li>
                    <li class="list-inline-item">&bull;</li>
                    <li class="list-inline-item"><a href="<?php echo BASE_URL; ?>/contact.php" class="text-decoration-none text-muted">Contact & Help</a></li>
                </ul>
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>

<!-- Bootstrap 5.3 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- MediCycle App Scripts -->
<script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>
