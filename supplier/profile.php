<?php
/**
 * MediCycle - Supplier Profile & Facility Details (UPDATE)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];
$errors = [];

// Fetch current user & organization details
$stmt = $pdo->prepare("SELECT u.name, u.email, u.phone, o.* 
                       FROM users u 
                       LEFT JOIN organizations o ON u.id = o.user_id 
                       WHERE u.id = ? LIMIT 1");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid. Please resubmit.';
    } else {
        $name = clean($_POST['name'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $orgName = clean($_POST['org_name'] ?? '');
        $license = clean($_POST['license_number'] ?? '');
        $address = clean($_POST['address'] ?? '');
        $city = clean($_POST['city'] ?? '');
        $state = clean($_POST['state'] ?? '');
        $pincode = clean($_POST['pincode'] ?? '');

        if (empty($name)) $errors[] = 'Contact person name is required.';
        if (empty($phone)) $errors[] = 'Phone number is required.';
        if (empty($orgName)) $errors[] = 'Organization name is required.';
        if (empty($address)) $errors[] = 'Address is required.';
        if (empty($city)) $errors[] = 'City is required.';

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Update users table
                $updUser = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $updUser->execute([$name, $phone, $userId]);

                // Update organizations table
                $updOrg = $pdo->prepare("UPDATE organizations SET 
                    organization_name = ?, 
                    license_number = ?, 
                    address = ?, 
                    city = ?, 
                    state = ?, 
                    pincode = ? 
                    WHERE user_id = ?");
                $updOrg->execute([$orgName, $license, $address, $city, $state, $pincode, $userId]);

                $_SESSION['user_name'] = $name;
                $_SESSION['org_name'] = $orgName;

                $pdo->commit();
                set_flash('success', 'Profile and organization details updated successfully.');
                header('Location: ' . BASE_URL . '/supplier/profile.php');
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Profile update error: " . $e->getMessage());
                $errors[] = 'Database error while saving profile updates.';
            }
        }
    }
}

$pageTitle = 'Facility Profile';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Facility Profile</h3>
                <p class="text-muted small mb-0">Manage hospital details, physical dispatch address, and medical license</p>
            </div>
            <div>
                <span class="badge bg-success px-3 py-2">
                    <i class="fas fa-check-circle me-1"></i> Status: <?php echo ucfirst(e($profile['verification_status'] ?? 'verified')); ?>
                </span>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo e($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <form action="<?php echo BASE_URL; ?>/supplier/profile.php" method="POST" class="needs-validation" novalidate>
                    <?php echo csrf_field(); ?>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-hospital me-2"></i>Organization Information</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label for="org_name" class="form-label small fw-semibold">Organization / Facility Name *</label>
                            <input type="text" class="form-control" id="org_name" name="org_name" value="<?php echo e($profile['organization_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="license_number" class="form-label small fw-semibold">License / Registration ID</label>
                            <input type="text" class="form-control" id="license_number" name="license_number" value="<?php echo e($profile['license_number'] ?? ''); ?>">
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label small fw-semibold">Dispatch / Pickup Address *</label>
                            <textarea class="form-control" id="address" name="address" rows="2" required><?php echo e($profile['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label for="city" class="form-label small fw-semibold">City *</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo e($profile['city'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="state" class="form-label small fw-semibold">State *</label>
                            <input type="text" class="form-control" id="state" name="state" value="<?php echo e($profile['state'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="pincode" class="form-label small fw-semibold">Pincode *</label>
                            <input type="text" class="form-control" id="pincode" name="pincode" value="<?php echo e($profile['pincode'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-user me-2"></i>Contact Representative</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="name" class="form-label small fw-semibold">Primary Contact Person *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo e($profile['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Official Email</label>
                            <input type="email" class="form-control bg-light" value="<?php echo e($profile['email']); ?>" readonly disabled>
                            <small class="text-muted" style="font-size:0.75rem;">Email cannot be modified directly.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label small fw-semibold">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e($profile['phone']); ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-1"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
