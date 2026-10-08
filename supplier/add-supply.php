<?php
/**
 * MediCycle - Add Medical Supply (INSERT Operation)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];
$errors = [];

// Fetch categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

// Fetch default location from user's organization
$orgStmt = $pdo->prepare("SELECT city, organization_name, verification_status FROM organizations WHERE user_id = ? LIMIT 1");
$orgStmt->execute([$userId]);
$userOrg = $orgStmt->fetch();
$defaultLocation = $userOrg['city'] ?? '';

$formData = [
    'supply_name' => '',
    'category_id' => '',
    'description' => '',
    'quantity' => '',
    'unit' => '',
    'condition_status' => '',
    'packaging_status' => '',
    'expiry_date' => '',
    'batch_number' => '',
    'storage_requirements' => '',
    'location' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please refresh and try again.';
    } else {
        foreach ($formData as $k => $v) {
            $formData[$k] = clean($_POST[$k] ?? '');
        }

        // Validation
        if (empty($formData['supply_name'])) $errors[] = 'Medical supply name is required.';
        if (empty($formData['category_id'])) $errors[] = 'Please select a valid supply category.';
        if (!is_numeric($formData['quantity']) || (int)$formData['quantity'] <= 0) {
            $errors[] = 'Quantity must be a positive whole number.';
        }
        if (empty($formData['unit'])) $errors[] = 'Unit specification is required (e.g. Boxes, Packs, Cartons).';
        if (empty($formData['condition_status'])) $errors[] = 'Please select physical condition status.';
        if (empty($formData['packaging_status'])) $errors[] = 'Please select packaging seal status.';
        if (empty($formData['expiry_date'])) {
            $errors[] = 'Expiry date is required.';
        } else {
            $expiryTime = strtotime($formData['expiry_date']);
            if ($expiryTime <= time()) {
                $errors[] = 'Expiry date must be in the future. Expired supplies are strictly prohibited.';
            }
        }
        if (empty($formData['location'])) $errors[] = 'Physical pickup location/city is required.';

        if (empty($errors)) {
            try {
                // Calculate Priority Score & Level via Smart Module Algorithm
                $priority = calculate_supply_priority_score(
                    $formData['expiry_date'],
                    $formData['condition_status'],
                    (int)$formData['quantity']
                );

                // Supply is immediately listed as 'Available' for NGOs to discover
                $initialStatus = 'Available';

                $stmt = $pdo->prepare("INSERT INTO medical_supplies 
                    (supplier_id, category_id, supply_name, description, quantity, unit, condition_status, packaging_status, expiry_date, batch_number, storage_requirements, location, status, priority_score, priority_level, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                $stmt->execute([
                    $userId,
                    (int)$formData['category_id'],
                    $formData['supply_name'],
                    $formData['description'],
                    (int)$formData['quantity'],
                    $formData['unit'],
                    $formData['condition_status'],
                    $formData['packaging_status'],
                    $formData['expiry_date'],
                    $formData['batch_number'],
                    $formData['storage_requirements'] ?: 'Standard Room Temperature (15-25°C)',
                    $formData['location'],
                    $initialStatus,
                    $priority['score'],
                    $priority['level']
                ]);

                $newSupplyId = $pdo->lastInsertId();

                set_flash('success', "Medical supply '{$formData['supply_name']}' successfully added to available inventory!");
                header('Location: ' . BASE_URL . '/supplier/inventory.php');
                exit;

            } catch (PDOException $e) {
                error_log("Add supply error: " . $e->getMessage());
                $errors[] = 'Database error while saving the supply record. Please try again.';
            }
        }
    }
}

$pageTitle = 'Add Medical Supply';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Add Medical Consumable</h3>
                <p class="text-muted small mb-0">List surplus unexpired consumables for community redistribution</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/supplier/inventory.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>

        <div class="safety-ribbon">
            <i class="fas fa-shield-virus me-2"></i>
            <strong>Eligibility Protocol:</strong> Strictly for unopened personal protective equipment, sterile wound care, gauze, and non-drug healthcare consumables. Prescription drugs are strictly forbidden.
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
                <form action="<?php echo BASE_URL; ?>/supplier/add-supply.php" method="POST" class="needs-validation" novalidate>
                    <?php echo csrf_field(); ?>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-box-open me-2"></i>Supply Identification</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label for="supply_name" class="form-label small fw-semibold">Supply / Product Name *</label>
                            <input type="text" class="form-control" id="supply_name" name="supply_name" value="<?php echo e($formData['supply_name']); ?>" placeholder="e.g. Sterile Nitrile Examination Gloves (Size M)" required>
                            <div class="invalid-feedback">Product name is required.</div>
                        </div>

                        <div class="col-md-5">
                            <label for="category_id" class="form-label small fw-semibold">Category *</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select Category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $formData['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a category.</div>
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label small fw-semibold">Technical Description & Specifications</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Material composition, ply count, dimensions, or manufacturer specs"><?php echo e($formData['description']); ?></textarea>
                        </div>
                    </div>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-balance-scale me-2"></i>Quantity & Packaging</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="quantity" class="form-label small fw-semibold">Quantity Available *</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="<?php echo e($formData['quantity']); ?>" min="1" placeholder="e.g. 250" required>
                            <div class="invalid-feedback">Quantity must be greater than zero.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="unit" class="form-label small fw-semibold">Packaging Unit *</label>
                            <input type="text" class="form-control" id="unit" name="unit" value="<?php echo e($formData['unit']); ?>" placeholder="e.g. Boxes (100 pcs), Packs, Rolls" required>
                            <div class="invalid-feedback">Unit is required.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="batch_number" class="form-label small fw-semibold">Batch / Lot Number</label>
                            <input type="text" class="form-control" id="batch_number" name="batch_number" value="<?php echo e($formData['batch_number']); ?>" placeholder="e.g. LOT-2024-88A">
                        </div>

                        <div class="col-md-6">
                            <label for="condition_status" class="form-label small fw-semibold">Physical Condition *</label>
                            <select class="form-select" id="condition_status" name="condition_status" required>
                                <option value="">Select Condition...</option>
                                <option value="New / Unopened" <?php echo $formData['condition_status'] === 'New / Unopened' ? 'selected' : ''; ?>>New / Unopened (Factory Condition)</option>
                                <option value="Sterile Sealed" <?php echo $formData['condition_status'] === 'Sterile Sealed' ? 'selected' : ''; ?>>Sterile Sealed (Intact Blister / Peel Pack)</option>
                                <option value="Surplus Stock" <?php echo $formData['condition_status'] === 'Surplus Stock' ? 'selected' : ''; ?>>Surplus Overstock (Intact Outer Carton)</option>
                            </select>
                            <div class="invalid-feedback">Please select the condition status.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="packaging_status" class="form-label small fw-semibold">Packaging Seal Status *</label>
                            <select class="form-select" id="packaging_status" name="packaging_status" required>
                                <option value="">Select Seal Status...</option>
                                <option value="Original Factory Seal" <?php echo $formData['packaging_status'] === 'Original Factory Seal' ? 'selected' : ''; ?>>Original Factory Seal (100% Intact)</option>
                                <option value="Tamper Evident Packaging" <?php echo $formData['packaging_status'] === 'Tamper Evident Packaging' ? 'selected' : ''; ?>>Tamper-Evident Packaging (Validated)</option>
                                <option value="Intact Outer Box" <?php echo $formData['packaging_status'] === 'Intact Outer Box' ? 'selected' : ''; ?>>Intact Outer Box (Undamaged)</option>
                            </select>
                            <div class="invalid-feedback">Please select packaging status.</div>
                        </div>
                    </div>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-calendar-check me-2"></i>Dates & Logistics</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="expiry_date" class="form-label small fw-semibold">Expiration Date *</label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo e($formData['expiry_date']); ?>" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                            <div class="invalid-feedback">A future expiration date is mandatory.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="storage_requirements" class="form-label small fw-semibold">Storage Condition</label>
                            <input type="text" class="form-control" id="storage_requirements" name="storage_requirements" value="<?php echo e($formData['storage_requirements']); ?>" placeholder="e.g. Cool & Dry (15-25°C)">
                        </div>

                        <div class="col-md-4">
                            <label for="location" class="form-label small fw-semibold">Pickup City / Hub *</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?php echo e($formData['location']); ?>" placeholder="e.g. Mumbai, Maharashtra" required>
                            <div class="invalid-feedback">Pickup city is required.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?php echo BASE_URL; ?>/supplier/inventory.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-plus-circle me-1"></i> Register Medical Supply
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
