<?php
/**
 * MediCycle - Post Clinical Requirement (INSERT into requirements)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];
$errors = [];

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

// Get NGO default city
$orgStmt = $pdo->prepare("SELECT city FROM organizations WHERE user_id = ? LIMIT 1");
$orgStmt->execute([$userId]);
$userOrg = $orgStmt->fetch();
$defaultCity = $userOrg['city'] ?? '';

$formData = [
    'supply_name' => '',
    'category_id' => '',
    'required_quantity' => '',
    'unit' => '',
    'urgency' => '',
    'required_by' => '',
    'city' => '',
    'description' => ''
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
        if (!is_numeric($formData['required_quantity']) || (int)$formData['required_quantity'] <= 0) {
            $errors[] = 'Required quantity must be a positive number.';
        }
        if (empty($formData['required_by'])) {
            $errors[] = 'Date required by is mandatory.';
        } else {
            if (strtotime($formData['required_by']) < strtotime(date('Y-m-d'))) {
                $errors[] = 'Required date must be today or a future date.';
            }
        }
        if (empty($formData['city'])) $errors[] = 'Delivery city is required.';

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO requirements 
                    (organization_id, supply_name, category_id, required_quantity, unit, urgency, required_by, city, description, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW())");
                $stmt->execute([
                    $userId,
                    $formData['supply_name'],
                    (int)$formData['category_id'],
                    (int)$formData['required_quantity'],
                    $formData['unit'],
                    $formData['urgency'],
                    $formData['required_by'],
                    $formData['city'],
                    $formData['description']
                ]);

                // Notify matching active suppliers in this category
                $suppStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'supplier' AND status = 'active' LIMIT 5");
                $suppStmt->execute();
                $suppliers = $suppStmt->fetchAll();
                foreach ($suppliers as $s) {
                    create_notification(
                        $pdo,
                        $s['id'],
                        'New Supply Need Broadcast',
                        "{$_SESSION['org_name']} posted a clinical need for {$formData['required_quantity']} {$formData['unit']} of {$formData['supply_name']} in {$formData['city']}.",
                        'supplier/inventory.php'
                    );
                }

                set_flash('success', "Requirement for '{$formData['supply_name']}' has been posted to the network.");
                header('Location: ' . BASE_URL . '/ngo/my-requirements.php');
                exit;

            } catch (PDOException $e) {
                error_log("Post requirement error: " . $e->getMessage());
                $errors[] = 'Database error while saving requirement.';
            }
        }
    }
}

$pageTitle = 'Post Requirement';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Post Clinical Requirement</h3>
                <p class="text-muted small mb-0">Broadcast your healthcare facility's consumable supply deficits</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/ngo/my-requirements.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> My Requirements
            </a>
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
                <form action="<?php echo BASE_URL; ?>/ngo/post-requirement.php" method="POST" class="needs-validation" novalidate>
                    <?php echo csrf_field(); ?>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-stethoscope me-2"></i>Needed Consumable</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label for="supply_name" class="form-label small fw-semibold">Item / Consumable Name *</label>
                            <input type="text" class="form-control" id="supply_name" name="supply_name" value="<?php echo e($formData['supply_name']); ?>" placeholder="e.g. Sterile Gauze Swabs 10x10cm" required>
                            <div class="invalid-feedback">Item name is required.</div>
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
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label small fw-semibold">Clinical Need & Purpose</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Describe patient volume, camp date, or specific health emergency..."><?php echo e($formData['description']); ?></textarea>
                        </div>
                    </div>

                    <h5 class="fw-bold text-teal border-bottom pb-2 mb-3"><i class="fas fa-sliders-h me-2"></i>Urgency & Allocation</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="required_quantity" class="form-label small fw-semibold">Quantity Needed *</label>
                            <input type="number" class="form-control" id="required_quantity" name="required_quantity" value="<?php echo e($formData['required_quantity']); ?>" min="1" placeholder="e.g. 100" required>
                            <div class="invalid-feedback">Must be a positive number.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="unit" class="form-label small fw-semibold">Unit *</label>
                            <input type="text" class="form-control" id="unit" name="unit" value="<?php echo e($formData['unit']); ?>" placeholder="e.g. Boxes, Packs, Rolls" required>
                        </div>

                        <div class="col-md-4">
                            <label for="urgency" class="form-label small fw-semibold">Clinical Urgency *</label>
                            <select class="form-select" id="urgency" name="urgency" required>
                                <option value="">Select Urgency...</option>
                                <option value="Low" <?php echo $formData['urgency'] === 'Low' ? 'selected' : ''; ?>>Low Priority</option>
                                <option value="Medium" <?php echo $formData['urgency'] === 'Medium' ? 'selected' : ''; ?>>Medium (Routine Need)</option>
                                <option value="High" <?php echo $formData['urgency'] === 'High' ? 'selected' : ''; ?>>High (Outreach Camp)</option>
                                <option value="Critical" <?php echo $formData['urgency'] === 'Critical' ? 'selected' : ''; ?>>Critical (Imminent Depletion)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="required_by" class="form-label small fw-semibold">Required By Date *</label>
                            <input type="date" class="form-control" id="required_by" name="required_by" value="<?php echo e($formData['required_by']); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="city" class="form-label small fw-semibold">Delivery City / Clinic District *</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo e($formData['city']); ?>" placeholder="e.g. Pune, Maharashtra" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?php echo BASE_URL; ?>/ngo/my-requirements.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-bullhorn me-1"></i> Post Clinical Requirement
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
