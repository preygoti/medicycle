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

// Calculate remaining cooldown seconds from database
$cdStmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed FROM email_otps 
                         WHERE email = ? AND otp_type = 'registration' AND is_used = 0 
                         ORDER BY created_at DESC LIMIT 1");
$cdStmt->execute([$user['email']]);
$lastOtp = $cdStmt->fetch();
$remainingCooldown = 0;
if ($lastOtp && isset($lastOtp['elapsed'])) {
    $elapsed = (int)$lastOtp['elapsed'];
    if ($elapsed >= 0 && $elapsed < 45) {
        $remainingCooldown = 45 - $elapsed;
    }
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
                        <?php 
                            $hasMaxAttempts = false;
                            foreach ($errors as $err) {
                                if (stripos($err, 'attempts') !== false || stripos($err, 'expired') !== false || stripos($err, 'request a new') !== false) {
                                    $hasMaxAttempts = true;
                                    break;
                                }
                            }
                        ?>
                        <div class="alert alert-danger shadow-sm rounded-3 py-2 px-3 small">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($hasMaxAttempts): ?>
                                <div class="mt-2 text-center pt-2 border-top border-danger border-opacity-25">
                                    <a href="<?php echo BASE_URL; ?>/verify-email.php?email=<?php echo urlencode($user['email']); ?>&action=resend" class="btn btn-sm btn-danger text-white fw-semibold rounded-pill px-3 shadow-xs">
                                        <i class="fas fa-rotate-right me-1"></i> Send Me a New Code Now
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>/verify-email.php?email=<?php echo urlencode($user['email']); ?>" method="POST" class="needs-validation" novalidate>
                        <?php echo csrf_field(); ?>

                        <div class="mb-4 text-center">
                            <label class="form-label small fw-semibold text-secondary mb-2">Enter 6-Digit Verification PIN</label>
                            
                            <!-- 6 Separate Digit PIN Boxes -->
                            <div class="d-flex justify-content-center gap-2 mb-3">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                    <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                                           id="pin<?php echo $i + 1; ?>"
                                           class="form-control text-center fw-bold otp-pin-input shadow-xs" 
                                           style="width: 52px; height: 58px; font-size: 1.5rem; border-radius: 10px;"
                                           data-index="<?php echo $i; ?>" autocomplete="off"
                                           <?php echo ($i === 0) ? 'autofocus' : ''; ?>>
                                <?php endfor; ?>
                            </div>

                            <input type="hidden" name="otp" id="hidden_otp" value="">

                            <div class="form-text small text-muted mt-2">
                                <i class="far fa-clock me-1 text-teal"></i> Code remains valid for <strong>10 minutes</strong>.
                        </div>

                        <button type="submit" id="btn-verify-otp" class="btn btn-teal text-white w-100 py-2 fw-semibold shadow-sm" style="background:#0f766e;">
                            <i class="fas fa-check-circle me-1"></i> Verify & Activate Account
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="text-muted small mb-2">Didn't receive the email code?</p>
                        
                        <!-- Live Backwards Countdown Component -->
                        <div id="resend-wrapper" class="d-inline-flex flex-column align-items-center">
                            <button type="button" id="btn-resend-countdown" class="btn btn-sm btn-light border text-muted px-3 py-2 rounded-pill shadow-xs" 
                                    style="<?php echo ($remainingCooldown > 0) ? 'display: inline-flex;' : 'display: none;'; ?> align-items: center; cursor: not-allowed;" disabled>
                                <i class="fas fa-hourglass-half fa-spin text-teal me-2"></i>
                                <span>Resend new code in <strong id="cooldown-seconds" class="text-dark font-monospace fs-6"><?php echo $remainingCooldown; ?></strong>s</span>
                            </button>

                            <a href="<?php echo BASE_URL; ?>/verify-email.php?email=<?php echo urlencode($user['email']); ?>&action=resend" 
                               id="btn-resend-active" 
                               class="btn btn-sm btn-outline-teal px-4 py-2 rounded-pill shadow-xs fw-semibold" 
                               style="<?php echo ($remainingCooldown > 0) ? 'display: none;' : 'display: inline-flex;'; ?> align-items: center; text-decoration: none;">
                                <i class="fas fa-rotate-right me-1"></i> Resend Verification Code Now
                            </a>
                        </div>
                    </div>

<script>
function fillOtp(code) {
    const pinInputs = document.querySelectorAll('.otp-pin-input');
    const hiddenOtp = document.getElementById('hidden_otp');
    if (!pinInputs.length) return;
    const digits = code.toString().replace(/\D/g, '').slice(0, 6);
    for (let i = 0; i < 6; i++) {
        if (pinInputs[i]) pinInputs[i].value = digits[i] || '';
    }
    if (hiddenOtp) hiddenOtp.value = digits;
}

document.addEventListener('DOMContentLoaded', function() {
    const pinInputs = document.querySelectorAll('.otp-pin-input');
    const hiddenOtp = document.getElementById('hidden_otp');
    const verifyForm = document.querySelector('form.needs-validation');
    const btnVerify = document.getElementById('btn-verify-otp');
    const btnResendCountdown = document.getElementById('btn-resend-countdown');
    const btnResendActive = document.getElementById('btn-resend-active');
    const cooldownSecondsEl = document.getElementById('cooldown-seconds');

    // 1. Live Countdown Timer
    let remainingCooldown = <?php echo (int)$remainingCooldown; ?>;

    function updateCooldown() {
        if (remainingCooldown > 0) {
            if (btnResendCountdown) {
                btnResendCountdown.style.display = 'inline-flex';
                if (cooldownSecondsEl) cooldownSecondsEl.textContent = remainingCooldown;
            }
            if (btnResendActive) btnResendActive.style.display = 'none';
            remainingCooldown--;
            setTimeout(updateCooldown, 1000);
        } else {
            if (btnResendCountdown) btnResendCountdown.style.display = 'none';
            if (btnResendActive) {
                btnResendActive.style.display = 'inline-flex';
                btnResendActive.style.animation = 'pulseGlow 1.5s infinite';
            }
        }
    }

    if (remainingCooldown > 0) {
        updateCooldown();
    }

    // 2. Resend Click Feedback
    if (btnResendActive) {
        btnResendActive.addEventListener('click', function(e) {
            this.classList.add('disabled');
            this.style.pointerEvents = 'none';
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Dispatching New Code...';
        });
    }

    // 3. Form Submit Feedback
    if (verifyForm) {
        verifyForm.addEventListener('submit', function(e) {
            if (hiddenOtp && hiddenOtp.value.length === 6) {
                if (btnVerify && !btnVerify.disabled) {
                    btnVerify.disabled = true;
                    btnVerify.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Verifying Code...';
                }
            }
        });
    }

    // 4. PIN Input auto-flow
    function syncOtp() {
        let full = '';
        pinInputs.forEach(inp => { full += inp.value; });
        if (hiddenOtp) hiddenOtp.value = full;
        if (full.length === 6 && verifyForm) {
            // Optional auto submit or visual hint
            if (btnVerify) {
                btnVerify.classList.remove('btn-teal');
                btnVerify.classList.add('btn-success');
            }
        }
    }

    pinInputs.forEach((input, index) => {
        input.addEventListener('focus', function() { this.select(); });
        input.addEventListener('click', function() { this.select(); });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace') {
                e.preventDefault();
                if (this.value !== '') {
                    this.value = '';
                    syncOtp();
                } else if (index > 0) {
                    pinInputs[index - 1].value = '';
                    pinInputs[index - 1].focus();
                    syncOtp();
                }
                return;
            }
            if (e.key === 'ArrowLeft' && index > 0) {
                e.preventDefault();
                pinInputs[index - 1].focus();
                return;
            }
            if (e.key === 'ArrowRight' && index < pinInputs.length - 1) {
                e.preventDefault();
                pinInputs[index + 1].focus();
                return;
            }
            if (/^[0-9]$/.test(e.key)) {
                e.preventDefault();
                this.value = e.key;
                syncOtp();
                if (index < pinInputs.length - 1) {
                    pinInputs[index + 1].focus();
                }
            }
        });

        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(-1);
            syncOtp();
            if (this.value.length === 1 && index < pinInputs.length - 1) {
                pinInputs[index + 1].focus();
            }
        });

        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, 6);
            if (pasteData) {
                fillOtp(pasteData);
                const nextIdx = Math.min(pasteData.length, pinInputs.length - 1);
                pinInputs[nextIdx].focus();
            }
        });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
