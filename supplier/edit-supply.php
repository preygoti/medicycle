<?php
/**
 * MediCycle - Edit Medical Supply (UPDATE Operation)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];
$supplyId = (int)($_GET['id'] ?? 0);
$errors = [];

// Verify supply ownership
$stmt = $pdo->prepare("SELECT * FROM medical_supplies WHERE id = ? AND supplier_id = ? LIMIT 1");
$stmt->execute([$supplyId, $userId]);
$supply = $stmt->fetch();

if (!$supply) {
    set_flash('danger', 'Supply listing not found or access unauthorized.');
    header('Location: ' . BASE_URL . '/supplier/inventory.php');
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

$formData = [
    'supply_name' => $supply['supply_name'],
    'category_id' => $supply['category_id'],
    'description' => $supply['description'],
    'quantity' => $supply['quantity'],
    'unit' => $supply['unit'],
    'condition_status' => $supply['condition_status'],
    'packaging_status' => $supply['packaging_status'],
    'expiry_date' => $supply['expiry_date'],
    'batch_number' => $supply['batch_number'],
    'storage_requirements' => $supply['storage_requirements'],
    'location' => $supply['location'],
    'status' => $supply['status']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid. Please resubmit.';
    } else {
        foreach ($formData as $k => $v) {
            $formData[$k] = clean($_POST[$k] ?? '');
        }

        if (empty($formData['supply_name'])) $errors[] = 'Supply name is required.';
        if (empty($formData['category_id'])) $errors[] = 'Category is required.';
        if (!is_numeric($formData['quantity']) || (int)$formData['quantity'] <= 0) {
            $errors[] = 'Quantity must be a positive integer.';
        }
        if (empty($formData['unit'])) $errors[] = 'Unit is required.';
        if (empty($formData['expiry_date'])) {
            $errors[] = 'Expiry date is required.';
        } else {
            if (strtotime($formData['expiry_date']) <= time()) {
                $errors[] = 'Expiry date must be in the future.';
            }
        }
        if (empty($formData['location'])) $errors[] = 'Location/City is required.';

        if (empty($errors)) {
            try {
                // Recompute priority score
                $priority = calculate_supply_priority_score(
                    $formData['expiry_date'],
                    $formData['condition_status'],
                    (int)$formData['quantity']
                );

                $updateStmt = $pdo->prepare("UPDATE medical_supplies SET 
                    category_id = ?, 
                    supply_name = ?, 
                    description = ?, 
                    quantity = ?, 
                    unit = ?, 
                    condition_status = ?, 
                    packaging_status = ?, 
                    expiry_date = ?, 
                    batch_number = ?, 
                    storage_requirements = ?, 
                    location = ?, 
                    status = ?, 
                    priority_score = ?, 
                    priority_level = ?, 
                    updated_at = NOW() 
                    WHERE id = ? AND supplier_id = ?");

                $updateStmt->execute([
                    (int)$formData['category_id'],
                    $formData['supply_name'],
                    $formData['description'],
                    (int)$formData['quantity'],
                    $formData['unit'],
                    $formData['condition_status'],
                    $formData['packaging_status'],
                    $formData['expiry_date'],
                    $formData['batch_number'],
                    $formData['storage_requirements'],
                    $formData['location'],
                    $formData['status'],
                    $priority['score'],
                    $priority['level'],
                    $supplyId,
                    $userId
                ]);

                set_flash('success', "Medical supply '{$formData['supply_name']}' updated successfully.");
                header('Location: ' . BASE_URL . '/supplier/inventory.php');
                exit;

            } catch (PDOException $e) {
                error_log("Update supply error: " . $e->getMessage());
                $errors[] = 'Failed to update record in database.';
            }
        }
    }
}

$pageTitle = 'Edit Medical Supply';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Edit Medical Supply</h3>
                <p class="text-muted small mb-0">Update lot details, availability quantity, or expiry date</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/supplier/inventory.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm">
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <form action="<?php echo BASE_URL; ?>/supplier/edit-supply.php?id=<?php echo $supplyId; ?>" method="POST" class="needs-validation" novalidate>
                    <?php echo csrf_field(); ?>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-edit me-2"></i>Product Details</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label for="supply_name" class="form-label small fw-semibold">Supply Name *</label>
                            <input type="text" class="form-control" id="supply_name" name="supply_name" value="<?php echo e($formData['supply_name']); ?>" required>
                            <div class="invalid-feedback">Product name is required.</div>
                        </div>

                        <div class="col-md-5">
                            <label for="category_id" class="form-label small fw-semibold">Category *</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $formData['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label small fw-semibold">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2"><?php echo e($formData['description']); ?></textarea>
                        </div>
                    </div>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-boxes me-2"></i>Inventory & Packaging</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="quantity" class="form-label small fw-semibold">Quantity Available *</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="<?php echo e($formData['quantity']); ?>" min="1" required>
                            <div class="invalid-feedback">Quantity must be positive.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="unit" class="form-label small fw-semibold">Packaging Unit *</label>
                            <input type="text" class="form-control" id="unit" name="unit" value="<?php echo e($formData['unit']); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label for="batch_number" class="form-label small fw-semibold">Batch / Lot Number</label>
                            <input type="text" class="form-control" id="batch_number" name="batch_number" value="<?php echo e($formData['batch_number']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label for="condition_status" class="form-label small fw-semibold">Physical Condition *</label>
                            <select class="form-select" id="condition_status" name="condition_status" required>
                                <option value="New / Unopened" <?php echo $formData['condition_status'] === 'New / Unopened' ? 'selected' : ''; ?>>New / Unopened</option>
                                <option value="Sterile Sealed" <?php echo $formData['condition_status'] === 'Sterile Sealed' ? 'selected' : ''; ?>>Sterile Sealed</option>
                                <option value="Surplus Stock" <?php echo $formData['condition_status'] === 'Surplus Stock' ? 'selected' : ''; ?>>Surplus Overstock</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="packaging_status" class="form-label small fw-semibold">Packaging Seal Status *</label>
                            <select class="form-select" id="packaging_status" name="packaging_status" required>
                                <option value="Original Factory Seal" <?php echo $formData['packaging_status'] === 'Original Factory Seal' ? 'selected' : ''; ?>>Original Factory Seal</option>
                                <option value="Tamper Evident Packaging" <?php echo $formData['packaging_status'] === 'Tamper Evident Packaging' ? 'selected' : ''; ?>>Tamper Evident Packaging</option>
                                <option value="Intact Outer Box" <?php echo $formData['packaging_status'] === 'Intact Outer Box' ? 'selected' : ''; ?>>Intact Outer Box</option>
                            </select>
                        </div>
                    </div>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-info-circle me-2"></i>Status & Expiry</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="expiry_date" class="form-label small fw-semibold">Expiry Date *</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo e($formData['expiry_date']); ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label small fw-semibold">Listing Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Available" <?php echo $formData['status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                                <option value="Reserved" <?php echo $formData['status'] === 'Reserved' ? 'selected' : ''; ?>>Reserved</option>
                                <option value="Transferred" <?php echo $formData['status'] === 'Transferred' ? 'selected' : ''; ?>>Transferred</option>
                                <option value="Pending" <?php echo $formData['status'] === 'Pending' ? 'selected' : ''; ?>>Pending Approval</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="location" class="form-label small fw-semibold">Location / City *</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?php echo e($formData['location']); ?>" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?php echo BASE_URL; ?>/supplier/inventory.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
