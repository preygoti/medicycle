<?php
/**
 * MediCycle - Password Reset / Recovery Simulation
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$message = '';
$isSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $message = 'Security token invalid. Please refresh and try again.';
    } else {
        $email = clean($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please provide a valid registered email address.';
        } else {
            // Verify if user exists
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // In a college OEP demo environment: provide clear instructive feedback
            $isSuccess = true;
            $message = "If an account exists for '{$email}', password reset instructions have been generated. For this college OEP demo, you may use the demo passwords listed on the login page or contact the system administrator (admin@medicycle.org).";
        }
    }
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-light d-inline-flex p-3 text-secondary mb-2">
                            <i class="fas fa-key fs-3 text-primary"></i>
                        </div>
                        <h3 class="fw-bold text-dark">Password Recovery</h3>
                        <p class="text-muted small">Enter your registered email to reset your credentials</p>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $isSuccess ? 'success' : 'danger'; ?> shadow-sm small">
                            <i class="fas <?php echo $isSuccess ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
                            <?php echo e($message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$isSuccess): ?>
                        <form action="<?php echo BASE_URL; ?>/forgot-password.php" method="POST" class="needs-validation" novalidate>
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label for="email" class="form-label small fw-semibold">Registered Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-envelope text-muted"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="name@organization.org" required>
                                    <div class="invalid-feedback">Valid email is required.</div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="fas fa-paper-plane me-1"></i> Send Reset Link
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <a href="<?php echo BASE_URL; ?>/login.php" class="small text-decoration-none fw-semibold">
                            <i class="fas fa-arrow-left me-1"></i> Back to Sign In
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
