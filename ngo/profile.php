<?php
/**
 * MediCycle - NGO / Clinic Profile Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];
$errors = [];

$stmt = $pdo->prepare("SELECT u.name, u.email, u.phone, o.* 
                       FROM users u 
                       LEFT JOIN organizations o ON u.id = o.user_id 
                       WHERE u.id = ? LIMIT 1");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid.';
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
        if (empty($address)) $errors[] = 'Delivery address is required.';
        if (empty($city)) $errors[] = 'City is required.';

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $updUser = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $updUser->execute([$name, $phone, $userId]);

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
                set_flash('success', 'Clinic profile updated successfully.');
                header('Location: ' . BASE_URL . '/ngo/profile.php');
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("NGO profile update error: " . $e->getMessage());
                $errors[] = 'Database update error.';
            }
        }
    }
}

$pageTitle = 'Clinic Profile';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Clinic & Facility Profile</h3>
                <p class="text-muted small mb-0">Update receiving address, point-of-contact, and registration documents</p>
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
                <form action="<?php echo BASE_URL; ?>/ngo/profile.php" method="POST" class="needs-validation" novalidate>
                    <?php echo csrf_field(); ?>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-clinic-medical me-2"></i>Clinic Information</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Facility / NGO Name *</label>
                            <input type="text" class="form-control" name="org_name" value="<?php echo e($profile['organization_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Trust / NGO Reg Number</label>
                            <input type="text" class="form-control" name="license_number" value="<?php echo e($profile['license_number'] ?? ''); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Delivery Address (Facility Location) *</label>
                            <textarea class="form-control" name="address" rows="2" required><?php echo e($profile['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">City *</label>
                            <input type="text" class="form-control" name="city" value="<?php echo e($profile['city'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">State *</label>
                            <input type="text" class="form-control" name="state" value="<?php echo e($profile['state'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Pincode *</label>
                            <input type="text" class="form-control" name="pincode" value="<?php echo e($profile['pincode'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-user-doctor me-2"></i>Primary Contact</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Representative Name *</label>
                            <input type="text" class="form-control" name="name" value="<?php echo e($profile['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" class="form-control bg-light" value="<?php echo e($profile['email']); ?>" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number *</label>
                            <input type="tel" class="form-control" name="phone" value="<?php echo e($profile['phone']); ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary px-4">Save Profile</button>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
