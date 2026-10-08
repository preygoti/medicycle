<?php
/**
 * MediCycle - Edit Requirement (UPDATE operation)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];
$reqId = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = $pdo->prepare("SELECT * FROM requirements WHERE id = ? AND organization_id = ? LIMIT 1");
$stmt->execute([$reqId, $userId]);
$requirement = $stmt->fetch();

if (!$requirement) {
    set_flash('danger', 'Requirement not found or access denied.');
    header('Location: ' . BASE_URL . '/ngo/my-requirements.php');
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

$formData = [
    'supply_name' => $requirement['supply_name'],
    'category_id' => $requirement['category_id'],
    'required_quantity' => $requirement['required_quantity'],
    'unit' => $requirement['unit'],
    'urgency' => $requirement['urgency'],
    'required_by' => $requirement['required_by'],
    'city' => $requirement['city'],
    'description' => $requirement['description'],
    'status' => $requirement['status']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid. Please resubmit.';
    } else {
        foreach ($formData as $k => $v) {
            $formData[$k] = clean($_POST[$k] ?? '');
        }

        if (empty($formData['supply_name'])) $errors[] = 'Supply name is required.';
        if (!is_numeric($formData['required_quantity']) || (int)$formData['required_quantity'] <= 0) {
            $errors[] = 'Quantity must be positive.';
        }
        if (empty($formData['required_by'])) $errors[] = 'Required date is mandatory.';

        if (empty($errors)) {
            try {
                $updStmt = $pdo->prepare("UPDATE requirements SET 
                    supply_name = ?, 
                    category_id = ?, 
                    required_quantity = ?, 
                    unit = ?, 
                    urgency = ?, 
                    required_by = ?, 
                    city = ?, 
                    description = ?, 
                    status = ?, 
                    updated_at = NOW() 
                    WHERE id = ? AND organization_id = ?");
                $updStmt->execute([
                    $formData['supply_name'],
                    (int)$formData['category_id'],
                    (int)$formData['required_quantity'],
                    $formData['unit'],
                    $formData['urgency'],
                    $formData['required_by'],
                    $formData['city'],
                    $formData['description'],
                    $formData['status'],
                    $reqId,
                    $userId
                ]);

                set_flash('success', "Requirement for '{$formData['supply_name']}' updated successfully.");
                header('Location: ' . BASE_URL . '/ngo/my-requirements.php');
                exit;

            } catch (PDOException $e) {
                error_log("Update requirement error: " . $e->getMessage());
                $errors[] = 'Database update error.';
            }
        }
    }
}

$pageTitle = 'Edit Requirement';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Edit Requirement</h3>
                <p class="text-muted small mb-0">Update needed quantities or fulfillment status</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/ngo/my-requirements.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Requirements
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
                <form action="<?php echo BASE_URL; ?>/ngo/edit-requirement.php?id=<?php echo $reqId; ?>" method="POST" class="needs-validation" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label class="form-label small fw-semibold">Item Name *</label>
                            <input type="text" class="form-control" name="supply_name" value="<?php echo e($formData['supply_name']); ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Category *</label>
                            <select class="form-select" name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $formData['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Description</label>
                            <textarea class="form-control" name="description" rows="2"><?php echo e($formData['description']); ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Quantity *</label>
                            <input type="number" class="form-control" name="required_quantity" value="<?php echo e($formData['required_quantity']); ?>" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Unit *</label>
                            <input type="text" class="form-control" name="unit" value="<?php echo e($formData['unit']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Urgency *</label>
                            <select class="form-select" name="urgency" required>
                                <option value="Low" <?php echo $formData['urgency'] === 'Low' ? 'selected' : ''; ?>>Low Priority</option>
                                <option value="Medium" <?php echo $formData['urgency'] === 'Medium' ? 'selected' : ''; ?>>Medium (Routine Need)</option>
                                <option value="High" <?php echo $formData['urgency'] === 'High' ? 'selected' : ''; ?>>High (Outreach Camp)</option>
                                <option value="Critical" <?php echo $formData['urgency'] === 'Critical' ? 'selected' : ''; ?>>Critical (Imminent Depletion)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Required By *</label>
                            <input type="date" class="form-control" name="required_by" value="<?php echo e($formData['required_by']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Delivery City *</label>
                            <input type="text" class="form-control" name="city" value="<?php echo e($formData['city']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="Active" <?php echo $formData['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Fulfilled" <?php echo $formData['status'] === 'Fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                                <option value="Cancelled" <?php echo $formData['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?php echo BASE_URL; ?>/ngo/my-requirements.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
