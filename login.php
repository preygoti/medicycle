<?php
/**
 * MediCycle - User Login
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid or expired session security token. Please try again.';
    } else {
        $email = clean($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validation
        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required.';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT u.*, o.id as org_id, o.organization_name, o.verification_status 
                                       FROM users u 
                                       LEFT JOIN organizations o ON u.id = o.user_id 
                                       WHERE u.email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    // Check if email has been verified via 6-digit OTP
                    if ((int)($user['email_verified'] ?? 1) === 0) {
                        $_SESSION['pending_verification_email'] = $user['email'];
                        set_flash('warning', 'Please verify your email address before signing in. A 6-digit verification code was sent to your inbox.');
                        header('Location: ' . BASE_URL . '/verify-email.php?email=' . urlencode($user['email']));
                        exit;
                    }

                    if ($user['status'] === 'suspended' || $user['status'] === 'inactive') {
                        $errors[] = 'Your account has been deactivated or suspended. Please contact the administrator.';
                    } else {
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['org_id'] = $user['org_id'];
                        $_SESSION['org_name'] = $user['organization_name'] ?? $user['name'];
                        $_SESSION['verification_status'] = $user['verification_status'] ?? 'verified';
                        $_SESSION['last_activity'] = time();
                        
                        set_flash('success', 'Welcome back, ' . e($user['name']) . '!');
                        
                        // Determine redirect destination
                        $redirectTo = $_GET['redirect'] ?? '';
                        if (!empty($redirectTo) && str_starts_with($redirectTo, '/')) {
                            header('Location: ' . $redirectTo);
                            exit;
                        }
                        
                        $dashUrl = match($user['role']) {
                            'supplier' => BASE_URL . '/supplier/dashboard.php',
                            'ngo' => BASE_URL . '/ngo/dashboard.php',
                            default => BASE_URL . '/index.php'
                        };
                        header('Location: ' . $dashUrl);
                        exit;
                    }
                } else {
                    $errors[] = 'Invalid email address or password.';
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $errors[] = 'An error occurred while authenticating. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Sign In';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-teal-light d-inline-flex p-3 text-primary mb-2" style="background:#ccfbf1;">
                            <i class="fas fa-lock fs-3" style="color:#0f766e;"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Welcome to MediCycle</h3>
                        <div class="heading-accent-line mx-auto" style="width: 45px; height: 3px; margin: 0.4rem auto 0.75rem;"></div>
                        <p class="text-muted small page-headline">Sign in to your medical supply redistribution account</p>
                    </div>

                    <?php echo render_flash_messages(); ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger shadow-sm">
                            <ul class="mb-0 ps-3 small">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>/login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST" class="needs-validation" novalidate autocomplete="off">
                        <?php echo csrf_field(); ?>

                        <div class="mb-3">
                            <label for="email" class="form-label small fw-semibold">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo e($email); ?>" placeholder="name@hospital.org" required autofocus>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="password" class="form-label small fw-semibold">Password</label>
                                <a href="<?php echo BASE_URL; ?>/forgot-password.php" class="small text-decoration-none">Forgot password?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="toggleLoginPassword(this)">
                                    <i class="far fa-eye"></i>
                                </button>
                                <div class="invalid-feedback">Please enter your password.</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                            <i class="fas fa-sign-in-alt me-1"></i> Sign In
                        </button>
                    </form>

                    <!-- Quick Demo Credentials Selector for OEP Evaluation -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-muted"><i class="fas fa-flask text-teal me-1"></i> Quick Demo Logins</span>
                            <span class="badge bg-light text-secondary border">Direct Handover Flow</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-primary btn-sm w-100 text-truncate text-start p-2" onclick="fillDemo('apollo.supplies@medicycle.org', 'Supplier@123')">
                                    <div class="fw-bold"><i class="fas fa-hospital me-1"></i> Supplier</div>
                                    <small class="d-block text-truncate opacity-75">Apollo Health</small>
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-success btn-sm w-100 text-truncate text-start p-2" onclick="fillDemo('hope.clinic@medicycle.org', 'Ngo@123')">
                                    <div class="fw-bold"><i class="fas fa-hand-holding-heart me-1"></i> NGO / Clinic</div>
                                    <small class="d-block text-truncate opacity-75">Hope Clinic</small>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <span class="text-muted small">Need an account?</span>
                        <a href="<?php echo BASE_URL; ?>/register.php" class="small fw-semibold text-decoration-none ms-1">Register Organization</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillDemo(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
}
function toggleLoginPassword(btn) {
    const input = document.getElementById('password');
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
