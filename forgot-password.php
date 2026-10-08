<?php
/**
 * MediCycle - Password Reset / 6-Digit Email OTP Dispatch
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$errors = [];
$email = clean($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please refresh and try again.';
    } else {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid registered email address.';
        } else {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, name, email, status FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Avoid revealing account enumeration while giving actionable feedback
                $errors[] = 'No account associated with that email address was found. Please check spelling or register.';
            } elseif ($user['status'] === 'suspended') {
                $errors[] = 'This account has been suspended. Please contact support.';
            } else {
                // Dispatch 6-digit password reset OTP
                $otpResult = send_password_reset_otp($pdo, $user['email'], $user['name'], (int)$user['id']);

                if ($otpResult['success']) {
                    $_SESSION['reset_email'] = $user['email'];
                    set_flash('success', 'A 6-digit verification code has been dispatched to your email address.');
                    header('Location: ' . BASE_URL . '/verify-reset-otp.php?email=' . urlencode($user['email']));
                    exit;
                } else {
                    $errors[] = $otpResult['error'] ?? 'Could not dispatch verification email. Please try again shortly.';
                }
            }
        }
    }
}

$pageTitle = 'Password Recovery';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="rounded-circle d-inline-flex p-3 mb-2" style="background:#ccfbf1;">
                            <i class="fas fa-key fs-3" style="color:#0f766e;"></i>
                        </div>
                        <h3 class="fw-bold text-dark">Password Recovery</h3>
                        <p class="text-muted small">Enter your registered email to receive a 6-digit security code</p>
                    </div>

                    <?php echo render_flash_messages(); ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger shadow-sm small">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>/forgot-password.php" method="POST" class="needs-validation" novalidate>
                        <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label for="email" class="form-label small fw-semibold">Registered Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-envelope text-muted"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo e($email); ?>" placeholder="name@organization.org" required autofocus>
                                <div class="invalid-feedback">A valid email address is required.</div>
                            </div>
                            <div class="form-text small text-muted">We will send a 10-minute one-time password (OTP) to this address.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                            <i class="fas fa-paper-plane me-1"></i> Send Verification Code
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-2 border-top">
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
