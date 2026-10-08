<?php
/**
 * MediCycle - Set New Password After OTP Verification
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$errors = [];
$resetEmail = $_SESSION['authorized_password_reset_email'] ?? '';
$resetUserId = $_SESSION['authorized_password_reset_user_id'] ?? null;
$resetTime = $_SESSION['authorized_password_reset_time'] ?? 0;

// Security check: Must have verified OTP within last 15 minutes
if (empty($resetEmail) || empty($resetUserId) || (time() - $resetTime) > 900) {
    unset(
        $_SESSION['authorized_password_reset_email'],
        $_SESSION['authorized_password_reset_user_id'],
        $_SESSION['authorized_password_reset_time']
    );
    set_flash('warning', 'Password reset session has expired or is invalid. Please start again.');
    header('Location: ' . BASE_URL . '/forgot-password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please submit again.';
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword)) {
            $errors[] = 'Please enter a new password.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'The new passwords do not match.';
        } else {
            // Update user password in database
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ? AND email = ?");
            $stmt->execute([$hashedPassword, $resetUserId, $resetEmail]);

            // Clear reset session tokens
            unset(
                $_SESSION['authorized_password_reset_email'],
                $_SESSION['authorized_password_reset_user_id'],
                $_SESSION['authorized_password_reset_time']
            );

            // Invalidate all remaining OTPs for this user
            $invStmt = $pdo->prepare("UPDATE email_otps SET is_used = 1 WHERE email = ? AND otp_type = 'password_reset'");
            $invStmt->execute([$resetEmail]);

            set_flash('success', 'Your password has been successfully reset! Please sign in with your new credentials.');
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
}

$pageTitle = 'Set New Password';
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
                            <i class="fas fa-lock-open fs-3" style="color:#0f766e;"></i>
                        </div>
                        <h3 class="fw-bold text-dark">Set New Password</h3>
                        <p class="text-muted small">Choose a strong password for your MediCycle account</p>
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

                    <form action="<?php echo BASE_URL; ?>/reset-password.php" method="POST" class="needs-validation" novalidate autocomplete="off">
                        <?php echo csrf_field(); ?>

                        <div class="mb-3">
                            <label for="new_password" class="form-label small fw-semibold">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" placeholder="At least 8 characters" required autofocus>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password', this)">
                                    <i class="far fa-eye"></i>
                                </button>
                                <div class="invalid-feedback">Password must be at least 8 characters.</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label small fw-semibold">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="fas fa-check-double"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" placeholder="Repeat your new password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password', this)">
                                    <i class="far fa-eye"></i>
                                </button>
                                <div class="invalid-feedback">Please re-enter your password to confirm.</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-save me-1"></i> Update Password & Sign In
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-2 border-top">
                        <a href="<?php echo BASE_URL; ?>/login.php" class="small text-muted text-decoration-none">
                            <i class="fas fa-times me-1"></i> Cancel and return to login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
