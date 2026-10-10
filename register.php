<?php
/**
 * MediCycle - User & Organization Registration
 * Strict Email OTP Verification Required Prior to Activation
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'role' => '',
    'org_name' => '',
    'org_type' => '',
    'license_number' => '',
    'address' => '',
    'city' => '',
    'state' => '',
    'pincode' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please submit again.';
    } else {
        foreach ($formData as $key => $val) {
            $formData[$key] = clean($_POST[$key] ?? '');
        }
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($formData['role']) || !in_array($formData['role'], ['supplier', 'ngo'])) {
            $errors[] = 'Please select whether you represent a Supplier or an NGO/Clinic.';
        }
        if (empty($formData['org_name'])) {
            $errors[] = 'Organization / facility name is required.';
        }
        if (empty($formData['org_type'])) {
            $errors[] = 'Please select an entity type.';
        }
        if (empty($formData['name'])) {
            $errors[] = 'Contact person full name is required.';
        }
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (empty($formData['phone']) || strlen(preg_replace('/[^0-9]/', '', $formData['phone'])) < 10) {
            $errors[] = 'A valid contact phone number (at least 10 digits) is required.';
        }
        if (empty($formData['city'])) {
            $errors[] = 'City is required.';
        }
        if (empty($formData['state'])) {
            $errors[] = 'State is required.';
        }
        if (empty($formData['pincode'])) {
            $errors[] = 'Pincode is required.';
        }
        if (empty($formData['address'])) {
            $errors[] = 'Physical street address is required.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        // Check duplicate email
        if (empty($errors)) {
            try {
                $checkStmt = $pdo->prepare("SELECT id, email_verified, status FROM users WHERE email = ? LIMIT 1");
                $checkStmt->execute([$formData['email']]);
                $existing = $checkStmt->fetch();
                if ($existing) {
                    if ((int)$existing['email_verified'] === 0) {
                        // Account exists but unverified: re-send OTP
                        $_SESSION['pending_verification_email'] = $formData['email'];
                        send_registration_otp($pdo, $formData['email'], $formData['name'], (int)$existing['id']);
                        set_flash('info', 'An unverified account with this email exists. A new verification code has been dispatched.');
                        header('Location: ' . BASE_URL . '/verify-email.php?email=' . urlencode($formData['email']));
                        exit;
                    } else {
                        $errors[] = 'An active account with this email address already exists. Please sign in.';
                    }
                }
            } catch (PDOException $e) {
                $errors[] = 'Database verification error. Please try again.';
            }
        }

        // Save inactive record pending email OTP verification
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $userStmt = $pdo->prepare("INSERT INTO users 
                    (name, email, password, phone, role, email_verified, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 0, 'inactive', NOW())");
                $userStmt->execute([
                    $formData['name'],
                    $formData['email'],
                    $hash,
                    $formData['phone'],
                    $formData['role']
                ]);
                $userId = (int)$pdo->lastInsertId();

                $orgStmt = $pdo->prepare("INSERT INTO organizations 
                    (user_id, organization_name, organization_type, license_number, address, city, state, pincode, verification_status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'verified', NOW())");
                $orgStmt->execute([
                    $userId,
                    $formData['org_name'],
                    $formData['org_type'],
                    $formData['license_number'],
                    $formData['address'],
                    $formData['city'],
                    $formData['state'],
                    $formData['pincode']
                ]);

                // Create welcome notification
                create_notification(
                    $pdo,
                    $userId,
                    'Welcome to MediCycle!',
                    "Your organization profile ({$formData['org_name']}) is set up. Verify your email to complete activation.",
                    'login.php'
                );

                // Generate and dispatch real 6-digit email OTP
                $otpResult = send_registration_otp($pdo, $formData['email'], $formData['name'], $userId);

                $pdo->commit();

                $_SESSION['pending_verification_email'] = $formData['email'];
                set_flash('success', "Registration initiated! We have sent a 6-digit verification code to " . e($formData['email']) . ". Please enter it below to activate your account.");
                header('Location: ' . BASE_URL . '/verify-email.php?email=' . urlencode($formData['email']));
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Registration error: " . $e->getMessage());
                $errors[] = 'An error occurred during registration. Please try again.';
            }
        }
    }
}

$pageTitle = 'Register Organization';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="rounded-circle d-inline-flex p-3 mb-2" style="background:#CCFBF1; color:#0F766E;">
                            <i class="fas fa-hospital-user fs-2"></i>
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Join MediCycle</h3>
                        <div class="heading-accent-line mx-auto" style="width: 45px; height: 3px; margin: 0.4rem auto 0.75rem;"></div>
                        <p class="text-muted small page-headline">Connect directly to redistribute surplus healthcare supplies</p>
                    </div>

                    <div class="alert alert-light border rounded-3 p-3 mb-4 small text-secondary">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-shield-alt text-teal fs-5" style="color:#0F766E;"></i>
                            <div>
                                <strong class="text-dark d-block">Eligible Healthcare Supplies Only</strong>
                                Unopened, non-drug medical consumables (gloves, masks, gauze, test strips). Prescription pharmaceuticals and opened items are strictly prohibited.
                            </div>
                        </div>
                    </div>

                    <?php echo render_flash_messages(); ?>

                    <?php if (is_logged_in()): ?>
                        <div class="alert alert-info py-2 px-3 small d-flex justify-content-between align-items-center mb-4 rounded-3 border-0" style="background:#ccfbf1; color:#0f766e;">
                            <span><i class="fas fa-info-circle me-1"></i> Active: <strong><?php echo e($_SESSION['user_name'] ?? ''); ?></strong>. Registering below creates a new organization account.</span>
                            <a href="<?php echo get_role_dashboard(); ?>" class="btn btn-sm btn-teal py-1 px-2 text-white">Go to Dashboard</a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger shadow-sm rounded-3 py-2 px-3 small mb-4">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>/register.php" method="POST" class="needs-validation" novalidate>
                        <?php echo csrf_field(); ?>

                        <h5 class="fw-bold text-teal border-bottom pb-2 mb-3" style="color:#0F766E;">
                            <i class="fas fa-user-tag me-2"></i>Select Account Role & Entity Type
                        </h5>

                        <!-- Hidden Input for Form Submission -->
                        <input type="hidden" name="role" id="selected_role" value="<?php echo e($formData['role']); ?>">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="role-select-card <?php echo $formData['role'] === 'supplier' ? 'active' : ''; ?>" id="card_supplier" onclick="selectRole('supplier')">
                                    <div class="role-check"><i class="fas fa-check"></i></div>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="rounded-circle p-2 d-inline-flex" style="background:#CCFBF1; color:#0F766E;">
                                            <i class="fas fa-hospital fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="role-title mb-0 small fw-bold">Healthcare Supplier</div>
                                            <span class="badge bg-light text-secondary border" style="font-size:0.65rem;">Hospitals & Medical Stores</span>
                                        </div>
                                    </div>
                                    <p class="role-desc small mb-0">Hospitals, medical distributors & pharmacy stores with surplus unexpired supplies.</p>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="role-select-card <?php echo $formData['role'] === 'ngo' ? 'active' : ''; ?>" id="card_ngo" onclick="selectRole('ngo')">
                                    <div class="role-check"><i class="fas fa-check"></i></div>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="rounded-circle p-2 d-inline-flex" style="background:#DCFCE7; color:#16A34A;">
                                            <i class="fas fa-hand-holding-heart fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="role-title mb-0 small fw-bold">NGO / Clinic</div>
                                            <span class="badge bg-light text-secondary border" style="font-size:0.65rem;">Charitable Clinics & Centers</span>
                                        </div>
                                    </div>
                                    <p class="role-desc small mb-0">Charitable clinics, rural health centers & community dispensaries seeking supplies.</p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="org_type" class="form-label small fw-semibold">Specific Entity Classification: <span class="text-danger">*</span></label>
                            <select class="form-select" id="org_type" name="org_type" required>
                                <option value="">-- Choose Entity Type --</option>
                                <option value="Hospital" <?php echo $formData['org_type'] === 'Hospital' ? 'selected' : ''; ?>>Hospital / Medical Center</option>
                                <option value="Medical Store" <?php echo $formData['org_type'] === 'Medical Store' ? 'selected' : ''; ?>>Medical Store / Pharmacy Supplier</option>
                                <option value="Medical Distributor" <?php echo $formData['org_type'] === 'Medical Distributor' ? 'selected' : ''; ?>>Authorized Medical Distributor</option>
                                <option value="Charitable NGO" <?php echo $formData['org_type'] === 'Charitable NGO' ? 'selected' : ''; ?>>Charitable Healthcare NGO</option>
                                <option value="Community Clinic" <?php echo $formData['org_type'] === 'Community Clinic' ? 'selected' : ''; ?>>Community / Rural Free Clinic</option>
                                <option value="Dispensary" <?php echo $formData['org_type'] === 'Dispensary' ? 'selected' : ''; ?>>Charitable Dispensary</option>
                            </select>
                            <div class="invalid-feedback">Please select an entity classification.</div>
                        </div>

                        <h5 class="fw-bold text-teal border-bottom pb-2 mb-3" style="color:#0F766E;">
                            <i class="fas fa-building me-2"></i>Organization Information
                        </h5>

                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label for="org_name" class="form-label small fw-semibold">Organization / Facility Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="org_name" name="org_name" value="<?php echo e($formData['org_name']); ?>" placeholder="e.g. Hope Community Health Clinic" required>
                                <div class="invalid-feedback">Organization name is required.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="license_number" class="form-label small fw-semibold">License / Registration Number</label>
                                <input type="text" class="form-control" id="license_number" name="license_number" value="<?php echo e($formData['license_number']); ?>" placeholder="e.g. REG-2024-998">
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label small fw-semibold">Street Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="address" name="address" rows="2" placeholder="Full physical facility address for collection" required><?php echo e($formData['address']); ?></textarea>
                                <div class="invalid-feedback">Street address is required.</div>
                            </div>

                            <div class="col-md-4">
                                <label for="city" class="form-label small fw-semibold">City <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="city" name="city" value="<?php echo e($formData['city']); ?>" placeholder="e.g. Ahmedabad" required>
                                <div class="invalid-feedback">City is required.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="state" class="form-label small fw-semibold">State <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="state" name="state" value="<?php echo e($formData['state']); ?>" placeholder="e.g. Gujarat" required>
                                <div class="invalid-feedback">State is required.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="pincode" class="form-label small fw-semibold">Pincode <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="pincode" name="pincode" value="<?php echo e($formData['pincode']); ?>" placeholder="e.g. 380015" required>
                                <div class="invalid-feedback">Pincode is required.</div>
                            </div>
                        </div>

                        <h5 class="fw-bold text-teal border-bottom pb-2 mb-3" style="color:#0F766E;">
                            <i class="fas fa-id-card me-2"></i>Contact Person & Security Credentials
                        </h5>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="name" class="form-label small fw-semibold">Contact Person Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo e($formData['name']); ?>" placeholder="e.g. Dr. Ramesh Patel" required>
                                <div class="invalid-feedback">Contact person name is required.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label small fw-semibold">Official Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo e($formData['email']); ?>" placeholder="contact@organization.org" required>
                                <div class="form-text small text-muted">A 6-digit OTP will be sent to verify this email.</div>
                                <div class="invalid-feedback">Please provide a valid email.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label small fw-semibold">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($formData['phone']); ?>" placeholder="e.g. +91 9876543210" required>
                                <div class="invalid-feedback">Valid phone number required.</div>
                            </div>
                            <div class="col-md-6">
                                <!-- empty spacer column for clean 2-column alignment -->
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label small fw-semibold">Password <span class="text-danger">*</span> (Min 6 chars)</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" minlength="6" placeholder="••••••••" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password', this)" title="Show/Hide Password">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Password must be at least 6 characters.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label small fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" placeholder="••••••••" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password', this)" title="Show/Hide Password">
                                        <i class="far fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Passwords must match.</div>
                            </div>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label small text-muted" for="terms">
                                I confirm that all listed medical items conform strictly to eligible, unopened non-drug healthcare consumables and will undergo mandatory physical inspection upon collection.
                            </label>
                            <div class="invalid-feedback">You must accept the safety guidelines to proceed.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            <i class="fas fa-paper-plane me-1"></i> Register & Send Verification Code
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <span class="text-muted small">Already registered?</span>
                        <a href="<?php echo BASE_URL; ?>/login.php" class="small fw-semibold text-decoration-none ms-1 text-teal">Sign In Here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectRole(role) {
    document.getElementById('selected_role').value = role;
    const cardSupplier = document.getElementById('card_supplier');
    const cardNgo = document.getElementById('card_ngo');
    const orgSelect = document.getElementById('org_type');
    
    if (role === 'supplier') {
        cardSupplier.classList.add('active');
        cardNgo.classList.remove('active');
        updateEntityTypes(['Hospital', 'Medical Store', 'Medical Distributor', 'Pharmacy Supplier', 'Clinical Laboratory']);
    } else if (role === 'ngo') {
        cardNgo.classList.add('active');
        cardSupplier.classList.remove('active');
        updateEntityTypes(['Charitable NGO', 'Community Clinic', 'Dispensary', 'Rural Mobile Camp', 'Public Health Center']);
    }
}

function updateEntityTypes(options) {
    const orgSelect = document.getElementById('org_type');
    const currentVal = orgSelect.value;
    orgSelect.innerHTML = '<option value="">-- Choose Entity Type --</option>';
    options.forEach(opt => {
        const el = document.createElement('option');
        el.value = opt;
        el.textContent = opt;
        if (opt === currentVal) el.selected = true;
        orgSelect.appendChild(el);
    });
}

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
