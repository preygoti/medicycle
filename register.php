<?php
/**
 * MediCycle - User & Organization Registration
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'supplier',
    'org_name' => '',
    'org_type' => 'Hospital',
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
        if (empty($formData['name'])) $errors[] = 'Contact person full name is required.';
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (empty($formData['phone']) || strlen(preg_replace('/[^0-9]/', '', $formData['phone'])) < 10) {
            $errors[] = 'A valid contact phone number (at least 10 digits) is required.';
        }
        if (!in_array($formData['role'], ['supplier', 'ngo'])) {
            $errors[] = 'Invalid account role selected. Please select Supplier or NGO/Clinic.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }
        if (empty($formData['org_name'])) {
            $errors[] = 'Organization / entity name is required.';
        }
        if (empty($formData['city'])) $errors[] = 'City is required.';
        if (empty($formData['state'])) $errors[] = 'State is required.';
        if (empty($formData['pincode'])) $errors[] = 'Pincode is required.';

        // Check duplicate email
        if (empty($errors)) {
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $checkStmt->execute([$formData['email']]);
                if ($checkStmt->fetch()) {
                    $errors[] = 'An account with this email address already exists.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Database verification error. Please try again.';
            }
        }

        // Save record inside transaction
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $userStmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                $userStmt->execute([
                    $formData['name'],
                    $formData['email'],
                    $hash,
                    $formData['phone'],
                    $formData['role']
                ]);
                $userId = $pdo->lastInsertId();

                $orgStmt = $pdo->prepare("INSERT INTO organizations (user_id, organization_name, organization_type, license_number, address, city, state, pincode, verification_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'verified', NOW())");
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
                    "Your organization profile ({$formData['org_name']}) is active and ready to redistribute supplies.",
                    $formData['role'] === 'supplier' ? 'supplier/dashboard.php' : 'ngo/dashboard.php'
                );

                $pdo->commit();

                set_flash('success', 'Registration successful! Your account is active. You can now log in.');
                header('Location: ' . BASE_URL . '/login.php');
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
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
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-teal-light d-inline-flex p-3 text-primary mb-2" style="background:#ccfbf1;">
                            <i class="fas fa-hospital-user fs-3" style="color:#0f766e;"></i>
                        </div>
                        <h3 class="fw-bold text-dark">Join MediCycle</h3>
                        <p class="text-muted small">Connect directly to redistribute surplus healthcare supplies</p>
                    </div>

                    <div class="safety-ribbon">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Notice:</strong> MediCycle handles eligible, unopened non-drug medical consumables only. Prescription drugs and damaged items are strictly prohibited.
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

                    <form action="<?php echo BASE_URL; ?>/register.php" method="POST" class="needs-validation" novalidate>
                        <?php echo csrf_field(); ?>

                        <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-user-tag me-2"></i>Account Role & Type</h5>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="role" class="form-label small fw-semibold">I represent a: *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="supplier" <?php echo $formData['role'] === 'supplier' ? 'selected' : ''; ?>>Healthcare Supplier (Hospital, Store, Clinic, Distributor)</option>
                                    <option value="ngo" <?php echo $formData['role'] === 'ngo' ? 'selected' : ''; ?>>Recipient Organization (Charitable NGO, Free Clinic, Healthcare Center)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="org_type" class="form-label small fw-semibold">Entity Type *</label>
                                <select class="form-select" id="org_type" name="org_type" required>
                                    <option value="Hospital" <?php echo $formData['org_type'] === 'Hospital' ? 'selected' : ''; ?>>Hospital / Medical Center</option>
                                    <option value="Medical Store" <?php echo $formData['org_type'] === 'Medical Store' ? 'selected' : ''; ?>>Medical Store / Pharmacy Supplier</option>
                                    <option value="Medical Distributor" <?php echo $formData['org_type'] === 'Medical Distributor' ? 'selected' : ''; ?>>Authorized Medical Distributor</option>
                                    <option value="Charitable NGO" <?php echo $formData['org_type'] === 'Charitable NGO' ? 'selected' : ''; ?>>Charitable Healthcare NGO</option>
                                    <option value="Community Clinic" <?php echo $formData['org_type'] === 'Community Clinic' ? 'selected' : ''; ?>>Community / Rural Free Clinic</option>
                                </select>
                            </div>
                        </div>

                        <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-building me-2"></i>Organization Information</h5>

                        <div class="row g-3 mb-4">
                            <div class="col-md-8">
                                <label for="org_name" class="form-label small fw-semibold">Organization / Facility Name *</label>
                                <input type="text" class="form-control" id="org_name" name="org_name" value="<?php echo e($formData['org_name']); ?>" placeholder="e.g. Hope Community Health Clinic" required>
                                <div class="invalid-feedback">Organization name is required.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="license_number" class="form-label small fw-semibold">License / Reg Number</label>
                                <input type="text" class="form-control" id="license_number" name="license_number" value="<?php echo e($formData['license_number']); ?>" placeholder="e.g. REG-2024-998">
                            </div>

                            <div class="col-12">
                                <label for="address" class="form-label small fw-semibold">Street Address *</label>
                                <textarea class="form-control" id="address" name="address" rows="2" placeholder="Full physical facility address" required><?php echo e($formData['address']); ?></textarea>
                                <div class="invalid-feedback">Street address is required.</div>
                            </div>

                            <div class="col-md-4">
                                <label for="city" class="form-label small fw-semibold">City *</label>
                                <input type="text" class="form-control" id="city" name="city" value="<?php echo e($formData['city']); ?>" placeholder="e.g. Ahmedabad" required>
                                <div class="invalid-feedback">City is required.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="state" class="form-label small fw-semibold">State *</label>
                                <input type="text" class="form-control" id="state" name="state" value="<?php echo e($formData['state']); ?>" placeholder="e.g. Gujarat" required>
                                <div class="invalid-feedback">State is required.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="pincode" class="form-label small fw-semibold">Pincode *</label>
                                <input type="text" class="form-control" id="pincode" name="pincode" value="<?php echo e($formData['pincode']); ?>" placeholder="e.g. 380015" required>
                                <div class="invalid-feedback">Pincode is required.</div>
                            </div>
                        </div>

                        <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-id-card me-2"></i>Primary Contact & Credentials</h5>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="name" class="form-label small fw-semibold">Contact Person Name *</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo e($formData['name']); ?>" placeholder="Dr. / Mr. / Ms. Full Name" required>
                                <div class="invalid-feedback">Contact person name is required.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label small fw-semibold">Official Email Address *</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo e($formData['email']); ?>" placeholder="contact@organization.org" required>
                                <div class="invalid-feedback">Please provide a valid email.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label small fw-semibold">Phone Number *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($formData['phone']); ?>" placeholder="+91 9876543210" required>
                                <div class="invalid-feedback">Valid phone number required.</div>
                            </div>
                            <div class="col-md-6">
                                <!-- blank column for symmetry -->
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label small fw-semibold">Password * (Min 6 characters)</label>
                                <input type="password" class="form-control" id="password" name="password" minlength="6" placeholder="••••••••" required>
                                <div class="invalid-feedback">Password must be at least 6 characters.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label small fw-semibold">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" placeholder="••••••••" required>
                                <div class="invalid-feedback">Passwords must match.</div>
                            </div>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label small text-muted" for="terms">
                                I confirm that all listed medical items conform strictly to eligible, unopened non-drug healthcare consumables and will undergo mandatory physical inspection.
                            </label>
                            <div class="invalid-feedback">You must accept the safety guidelines to proceed.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-check-circle me-1"></i> Complete Registration
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <span class="text-muted small">Already registered?</span>
                        <a href="<?php echo BASE_URL; ?>/login.php" class="small fw-semibold text-decoration-none ms-1">Sign In Here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
