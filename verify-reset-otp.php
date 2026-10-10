<?php
/**
 * MediCycle - Verify 6-Digit Password Reset OTP
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$errors = [];
$email = clean($_SESSION['reset_email'] ?? $_GET['email'] ?? '');

if (empty($email)) {
    set_flash('info', 'Please submit your registered email address to start password recovery.');
    header('Location: ' . BASE_URL . '/forgot-password.php');
    exit;
}

// Fetch user
$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('danger', 'Account not found for password reset.');
    header('Location: ' . BASE_URL . '/forgot-password.php');
    exit;
}

// Helper to mask email for privacy
function mask_email_display(string $em): string {
    $parts = explode('@', $em);
    if (count($parts) !== 2) return $em;
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $maskedName = substr($name, 0, 1) . '***';
    } else {
        $maskedName = substr($name, 0, 1) . str_repeat('*', min(4, $len - 2)) . substr($name, -1);
    }
    return $maskedName . '@' . $domain;
}

// Resend OTP action
if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    $resendResult = send_password_reset_otp($pdo, $user['email'], $user['name'], (int)$user['id']);
    if ($resendResult['success']) {
        set_flash('success', 'A fresh 6-digit reset code has been dispatched to your email.');
    } else {
        set_flash('warning', $resendResult['error'] ?? 'Could not dispatch a new code at this time.');
    }
    header('Location: ' . BASE_URL . '/verify-reset-otp.php?email=' . urlencode($user['email']));
    exit;
}

// Process OTP Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please submit again.';
    } else {
        $otp = trim($_POST['otp'] ?? '');
        if (empty($otp)) {
            $errors[] = 'Please enter the 6-digit verification code.';
        } elseif (strlen($otp) !== 6 || !ctype_digit($otp)) {
            $errors[] = 'The code must be exactly 6 numeric digits.';
        } else {
            $verifyResult = verify_email_otp($pdo, $user['email'], $otp, 'password_reset');
            if ($verifyResult['valid']) {
                // Authorize reset session
                $_SESSION['authorized_password_reset_email'] = $user['email'];
                $_SESSION['authorized_password_reset_user_id'] = $user['id'];
                $_SESSION['authorized_password_reset_time'] = time();
                unset($_SESSION['reset_email']);

                set_flash('success', 'Security verification confirmed! Please choose your new password.');
                header('Location: ' . BASE_URL . '/reset-password.php');
                exit;
            } else {
                $errors[] = $verifyResult['message'];
            }
        }
    }
}

$pageTitle = 'Verify Reset Code';
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
                            <i class="fas fa-shield-alt fs-3" style="color:#0f766e;"></i>
                        </div>
                        <h3 class="fw-bold text-dark">Enter Security Code</h3>
                        <p class="text-muted small mb-1">We sent a 6-digit verification code to</p>
                        <div class="badge bg-light text-dark border px-3 py-2 font-monospace fs-6">
                            <?php echo e(mask_email_display($user['email'])); ?>
                        </div>
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

                    <form action="<?php echo BASE_URL; ?>/verify-reset-otp.php" method="POST" class="needs-validation" novalidate autocomplete="off">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="email" value="<?php echo e($user['email']); ?>">

                        <div class="mb-4">
                            <label for="otp" class="form-label small fw-semibold text-center d-block">
                                6-Digit Password Reset Code
                            </label>
                            <div class="d-flex justify-content-center">
                                <input type="text"
                                       class="form-control form-control-lg text-center font-monospace fw-bold"
                                       id="otp"
                                       name="otp"
                                       maxlength="6"
                                       pattern="[0-9]{6}"
                                       placeholder="123456"
                                       style="letter-spacing: 12px; font-size: 1.8rem; max-width: 260px;"
                                       required
                                       autofocus
                                       inputmode="numeric">
                            </div>
                            <div class="invalid-feedback text-center">Please enter all 6 digits.</div>
                            <div class="form-text text-center text-muted small mt-2">
                                <i class="far fa-clock me-1 text-teal"></i> Code remains valid for <strong>10 minutes</strong>.
                            </div>
                            <?php
                            $devOtp = '';
                            if (file_exists(APP_ROOT . '/logs/mail.log')) {
                                $logContent = file_get_contents(APP_ROOT . '/logs/mail.log');
                                if (preg_match_all('/TO:\s*' . preg_quote($user['email'], '/') . '.*?\n\s*OTP_CODE:\s*(\d{6})/is', $logContent, $matches)) {
                                    $devOtp = end($matches[1]);
                                }
                            }
                            if ($devOtp): ?>
                                <div class="mt-2 text-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 small" onclick="document.getElementById('otp').value='<?php echo $devOtp; ?>';">
                                        <i class="fas fa-flask text-teal me-1"></i> Demo Autofill Code: <strong><?php echo $devOtp; ?></strong>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-check-circle me-1"></i> Verify & Proceed
                        </button>
                    </form>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top small">
                        <span class="text-muted">Didn't receive the code?</span>
                        <a href="<?php echo BASE_URL; ?>/verify-reset-otp.php?action=resend&email=<?php echo urlencode($user['email']); ?>" class="fw-semibold text-teal text-decoration-none">
                            <i class="fas fa-redo-alt me-1"></i> Resend Code
                        </a>
                    </div>

                    <div class="text-center mt-3">
                        <a href="<?php echo BASE_URL; ?>/forgot-password.php" class="text-muted small text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i> Try a different email
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
