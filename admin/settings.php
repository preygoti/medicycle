<?php
/**
 * MediCycle - Admin System Settings & Environment Configuration
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch.');
    } else {
        $settings = [
            'platform_name' => clean($_POST['platform_name'] ?? 'MediCycle'),
            'support_email' => clean($_POST['support_email'] ?? 'support@medicycle.org'),
            'waste_factor_kg_per_unit' => clean($_POST['waste_factor_kg_per_unit'] ?? '0.12'),
            'avg_value_inr_per_unit' => clean($_POST['avg_value_inr_per_unit'] ?? '35.00'),
            'min_shelf_life_days' => clean($_POST['min_shelf_life_days'] ?? '30')
        ];

        try {
            $upd = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                                   VALUES (?, ?, NOW()) 
                                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
            foreach ($settings as $key => $val) {
                $upd->execute([$key, $val]);
            }
            set_flash('success', 'System parameters successfully updated!');
            header('Location: ' . BASE_URL . '/admin/settings.php');
            exit;
        } catch (PDOException $e) {
            set_flash('danger', 'Error updating settings: ' . $e->getMessage());
        }
    }
}

// Fetch current settings
$currentSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'System Settings - Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">System Configuration & Parameters</h3>
                <p class="text-muted small mb-0">Platform governance, environmental impact algorithms, and server health checks</p>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>/setup.php" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-rotate me-1"></i> Database Re-seed Utility
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Settings Form -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-sliders text-teal me-2"></i> Operational Parameters</h6>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <?php echo csrf_field(); ?>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Platform Brand Name</label>
                                <input type="text" name="platform_name" class="form-control" value="<?php echo e($currentSettings['platform_name'] ?? 'MediCycle'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">System Support Email</label>
                                <input type="email" name="support_email" class="form-control" value="<?php echo e($currentSettings['support_email'] ?? 'support@medicycle.org'); ?>" required>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Waste Factor (kg per Unit)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="waste_factor_kg_per_unit" class="form-control" value="<?php echo e($currentSettings['waste_factor_kg_per_unit'] ?? '0.12'); ?>" required>
                                        <span class="input-group-text small bg-light">kg/unit</span>
                                    </div>
                                    <small class="text-muted">Used for calculating averted landfill weight.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Average Consumable Value (INR)</label>
                                    <div class="input-group">
                                        <span class="input-group-text small bg-light">₹</span>
                                        <input type="number" step="0.50" name="avg_value_inr_per_unit" class="form-control" value="<?php echo e($currentSettings['avg_value_inr_per_unit'] ?? '35.00'); ?>" required>
                                    </div>
                                    <small class="text-muted">Estimated procurement savings per unit.</small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Minimum Remaining Shelf Life (Days)</label>
                                <div class="input-group">
                                    <input type="number" name="min_shelf_life_days" class="form-control" value="<?php echo e($currentSettings['min_shelf_life_days'] ?? '30'); ?>" required>
                                    <span class="input-group-text small bg-light">days</span>
                                </div>
                                <small class="text-muted">Safety threshold: supplies with shorter expiry cannot be redistributed.</small>
                            </div>

                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-1"></i> Save Configuration
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Server & Environment Diagnostics -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-server text-teal me-2"></i> Environment Health Diagnostics</h6>
                    </div>
                    <div class="card-body p-3">
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span class="text-muted">PHP Version</span>
                                <span class="fw-bold"><?php echo PHP_VERSION; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span class="text-muted">Database Engine</span>
                                <span class="fw-bold text-success"><i class="fas fa-check-circle me-1"></i> MySQL / MariaDB (PDO)</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span class="text-muted">Session Timeout</span>
                                <span class="fw-bold">7,200 seconds (2 Hours)</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span class="text-muted">Document Web Root</span>
                                <span class="text-truncate" style="max-width: 180px;"><code><?php echo e(BASE_URL); ?></code></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span class="text-muted">Audit Mail Log</span>
                                <span class="badge bg-light text-dark border">logs/mail.log</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Safety Scope Notice -->
                <div class="card border-0 shadow-sm rounded-3 bg-light p-3 border-start border-4 border-warning">
                    <h6 class="fw-bold text-dark mb-1"><i class="fas fa-shield-halved text-warning me-2"></i> Safety & Medical Scope</h6>
                    <p class="small text-muted mb-0">
                        MediCycle enforces strict eligibility rules: only unopened non-drug consumables (PPE, bandages, dressings, sterile sets) are legally accepted. Prescription pharmaceuticals are prohibited.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
