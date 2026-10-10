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

$defaultRequiredBy = date('Y-m-d', strtotime('+7 days'));

$formData = [
    'supply_name' => '',
    'category_id' => '',
    'required_quantity' => '',
    'unit' => 'Boxes',
    'urgency' => 'Medium',
    'required_by' => $defaultRequiredBy,
    'city' => $defaultCity,
    'description' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid. Please resubmit.';
    } else {
        foreach ($formData as $k => $v) {
            $formData[$k] = clean($_POST[$k] ?? '');
        }

        // Auto-fallback for city if left blank but default city is known
        if (empty($formData['city']) && !empty($defaultCity)) {
            $formData['city'] = $defaultCity;
        }

        // Auto-fallback for date if incomplete or empty
        if (empty($formData['required_by'])) {
            $formData['required_by'] = $defaultRequiredBy;
        }

        if (empty($formData['supply_name'])) $errors[] = 'Supply name is required.';
        if (empty($formData['category_id'])) $errors[] = 'Category is required.';
        if (!is_numeric($formData['required_quantity']) || (int)$formData['required_quantity'] <= 0) {
            $errors[] = 'Required quantity must be a positive number.';
        }
        
        $reqTs = strtotime($formData['required_by']);
        if (!$reqTs) {
            $formData['required_by'] = $defaultRequiredBy;
        } elseif ($reqTs < strtotime(date('Y-m-d'))) {
            $errors[] = 'Required date must be today or a future date.';
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
                $orgName = $_SESSION['org_name'] ?? 'Recipient Clinic';
                foreach ($suppliers as $s) {
                    create_notification(
                        $pdo,
                        $s['id'],
                        'New Supply Need Broadcast',
                        "{$orgName} posted a clinical need for {$formData['required_quantity']} {$formData['unit']} of {$formData['supply_name']} in {$formData['city']}.",
                        'supplier/inventory.php'
                    );
                }

                set_flash('success', "Requirement for '{$formData['supply_name']}' has been posted to the network.");
                header('Location: ' . BASE_URL . '/ngo/my-requirements.php');
                exit;

            } catch (PDOException $e) {
                error_log("Post requirement error: " . $e->getMessage());
                $errors[] = 'Database error while saving requirement: ' . $e->getMessage();
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

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h3 class="fw-bold text-dark mb-1">Post Clinical Requirement</h3>
                <p class="text-muted small mb-0">Broadcast your healthcare facility's consumable supply deficits</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/ngo/my-requirements.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-1"></i> My Requirements
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4">
                <div class="fw-bold mb-1"><i class="fas fa-exclamation-triangle me-1"></i> Please correct the following:</div>
                <ul class="mb-0 ps-3 small">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo e($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">
                <form action="<?php echo BASE_URL; ?>/ngo/post-requirement.php" method="POST" id="postRequirementForm" class="needs-validation" novalidate>
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
                            <div class="invalid-feedback">Please choose a category.</div>
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
                            <div class="invalid-feedback">Packaging unit is required.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="urgency" class="form-label small fw-semibold">Clinical Urgency *</label>
                            <select class="form-select" id="urgency" name="urgency" required>
                                <option value="">Select Urgency...</option>
                                <option value="Low" <?php echo $formData['urgency'] === 'Low' ? 'selected' : ''; ?>>Low Priority</option>
                                <option value="Medium" <?php echo ($formData['urgency'] === 'Medium' || empty($formData['urgency'])) ? 'selected' : ''; ?>>Medium (Routine Need)</option>
                                <option value="High" <?php echo $formData['urgency'] === 'High' ? 'selected' : ''; ?>>High (Outreach Camp)</option>
                                <option value="Critical" <?php echo $formData['urgency'] === 'Critical' ? 'selected' : ''; ?>>Critical (Imminent Depletion)</option>
                            </select>
                            <div class="invalid-feedback">Please select urgency level.</div>
                        </div>

                        <!-- Date Field with Quick-Pick Buttons & Calendar Picker -->
                        <div class="col-md-6">
                            <label for="required_by" class="form-label small fw-semibold d-flex justify-content-between align-items-center">
                                <span>Required By Date *</span>
                                <span class="text-muted fw-normal" style="font-size:0.75rem;">(Expected by)</span>
                            </label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="required_by" name="required_by" 
                                       value="<?php echo e($formData['required_by']); ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="triggerDatePicker('required_by')" title="Open Calendar Picker">
                                    <i class="fas fa-calendar-alt text-teal"></i>
                                </button>
                            </div>
                            <!-- Quick-Pick Date Pill Shortcuts -->
                            <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                <span class="text-muted small me-1" style="font-size:0.72rem;">Quick pick:</span>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill" style="font-size:0.72rem;" onclick="setQuickDate('required_by', 3)">+3 Days</button>
                                <button type="button" class="btn btn-xs btn-outline-teal py-0 px-2 rounded-pill" style="font-size:0.72rem; color:#0f766e; border-color:#0f766e;" onclick="setQuickDate('required_by', 7)">+1 Week</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill" style="font-size:0.72rem;" onclick="setQuickDate('required_by', 14)">+2 Weeks</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 rounded-pill" style="font-size:0.72rem;" onclick="setQuickDate('required_by', 30)">+1 Month</button>
                            </div>
                            <div class="invalid-feedback">Please select a valid date (today or future).</div>
                        </div>

                        <!-- Delivery City Field (Pre-filled with NGO Registered City) -->
                        <div class="col-md-6">
                            <label for="city" class="form-label small fw-semibold">Delivery City / Clinic District *</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo e($formData['city']); ?>" placeholder="e.g. Pune, Maharashtra" required>
                            <div class="invalid-feedback">Delivery city/district is required.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                        <a href="<?php echo BASE_URL; ?>/ngo/my-requirements.php" class="btn btn-light px-4">Cancel</a>
                        <button type="submit" class="btn btn-teal text-white px-4 fw-semibold shadow-sm" style="background:#0f766e;">
                            <i class="fas fa-bullhorn me-1"></i> Post Clinical Requirement
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
function triggerDatePicker(inputId) {
    const el = document.getElementById(inputId);
    if (!el) return;
    if (typeof el.showPicker === 'function') {
        el.showPicker();
    } else {
        el.focus();
    }
}

function setQuickDate(inputId, daysAhead) {
    const el = document.getElementById(inputId);
    if (!el) return;
    const target = new Date();
    target.setDate(target.getDate() + daysAhead);
    const yyyy = target.getFullYear();
    const mm = String(target.getMonth() + 1).padStart(2, '0');
    const dd = String(target.getDate()).padStart(2, '0');
    el.value = `${yyyy}-${mm}-${dd}`;
    el.classList.remove('is-invalid');
}

document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('required_by');
    if (dateInput) {
        // If left incomplete or invalid on blur, automatically ensure year is filled
        dateInput.addEventListener('blur', function() {
            if (!this.value) {
                setQuickDate('required_by', 7);
            }
        });
    }

    // Client-side form submission safety
    const form = document.getElementById('postRequirementForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const dateEl = document.getElementById('required_by');
            if (dateEl && !dateEl.value) {
                setQuickDate('required_by', 7);
            }
            const cityEl = document.getElementById('city');
            if (cityEl && !cityEl.value.trim() && '<?php echo addslashes($defaultCity); ?>') {
                cityEl.value = '<?php echo addslashes($defaultCity); ?>';
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
