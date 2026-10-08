<?php
/**
 * MediCycle - 6-Digit Email Verification (Registration Activation)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$errors = [];
$email = clean($_SESSION['pending_verification_email'] ?? $_GET['email'] ?? '');

if (empty($email)) {
    set_flash('info', 'Please sign in or register to verify your account.');
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Fetch user by email
$stmt = $pdo->prepare("SELECT id, name, email, email_verified, status FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    set_flash('danger', 'Account not found for verification.');
    header('Location: ' . BASE_URL . '/register.php');
    exit;
}

// If already verified, redirect to login
if ((int)$user['email_verified'] === 1 && $user['status'] === 'active') {
    unset($_SESSION['pending_verification_email']);
    set_flash('success', 'Your email address is already verified. Please sign in.');
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Helper to mask email for display (e.g., d***r@example.com)
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

// Handle RESEND OTP
if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    $resendResult = send_registration_otp($pdo, $user['email'], $user['name'], (int)$user['id']);
    if ($resendResult['success']) {
        set_flash('success', 'A new 6-digit verification code has been dispatched to your email.');
    } else {
        set_flash('warning', $resendResult['error'] ?? 'Could not dispatch a new code at this moment.');
    }
    header('Location: ' . BASE_URL . '/verify-email.php?email=' . urlencode($user['email']));
    exit;
}

// Handle OTP SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please submit again.';
    } else {
        $otp = trim($_POST['otp'] ?? '');

        if (empty($otp)) {
            $errors[] = 'Please enter the 6-digit verification code.';
        } elseif (strlen($otp) !== 6 || !ctype_digit($otp)) {
            $errors[] = 'Verification code must be exactly 6 numeric digits.';
        } else {
            $verifyResult = verify_email_otp($pdo, $user['email'], $otp, 'registration');
            if ($verifyResult['valid']) {
                // Activate account
                $updStmt = $pdo->prepare("UPDATE users SET email_verified = 1, status = 'active', updated_at = NOW() WHERE id = ?");
                $updStmt->execute([$user['id']]);

                unset($_SESSION['pending_verification_email']);
                set_flash('success', 'Email verification successful! Your account is now active. Please sign in to access your portal.');
                header('Location: ' . BASE_URL . '/login.php');
                exit;
            } else {
                $errors[] = $verifyResult['message'];
            }
        }
    }
}

$pageTitle = 'Verify Your Email';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="rounded-circle d-inline-flex p-3 mb-3" style="background: #CCFBF1; color: #0F766E;">
                            <i class="fas fa-envelope-circle-check fs-2"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Verify Your Email</h3>
                        <p class="text-muted small mb-0">
                            We sent a 6-digit verification code to:<br>
                            <strong class="text-dark font-monospace"><?php echo e(mask_email_display($user['email'])); ?></strong>
                        </p>
                    </div>

                    <?php echo render_flash_messages(); ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger shadow-sm rounded-3 py-2 px-3 small">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>/verify-email.php?email=<?php echo urlencode($user['email']); ?>" method="POST" class="needs-validation" novalidate>
                        <?php echo csrf_field(); ?>

                        <div class="mb-4 text-center">
                            <label for="otp" class="form-label small fw-semibold text-secondary mb-2">Enter 6-Digit Code</label>
                            <input type="text" 
                                   class="form-control form-control-lg text-center font-monospace fw-bold tracking-wide" 
                                   id="otp" 
                                   name="otp" 
                                   maxlength="6" 
                                   pattern="\d{6}" 
                                   inputmode="numeric" 
                                   autocomplete="one-time-code"
                                   placeholder="••••••" 
                                   style="font-size: 2rem; letter-spacing: 0.5rem; height: 60px;" 
                                   required 
                                   autofocus>
                            <div class="form-text small text-muted mt-2">
                                <i class="far fa-clock me-1"></i> Code expires in <strong>10 minutes</strong>.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="fas fa-check-circle me-1"></i> Verify & Activate Account
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="text-muted small mb-2">Didn't receive the email code?</p>
                        <a href="<?php echo BASE_URL; ?>/verify-email.php?email=<?php echo urlencode($user['email']); ?>&action=resend" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-rotate-right me-1"></i> Resend Verification Code
                        </a>
                    </div>

                    <div class="text-center mt-3">
                        <a href="<?php echo BASE_URL; ?>/login.php" class="small text-muted text-decoration-none">
                            <i class="fas fa-arrow-left me-1"></i> Return to Sign In
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
