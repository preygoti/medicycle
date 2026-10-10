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
    'expiry_date' => date('Y-m-d', strtotime('+6 months')),
    'batch_number' => '',
    'storage_requirements' => '',
    'location' => $defaultLocation
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid or expired. Please refresh and try again.';
    } else {
        $action = clean($_POST['action'] ?? 'add_item');

        if ($action === 'add_to_medical_supply') {
            $supplyId = (int)($_POST['supply_id'] ?? 0);
            $checkStmt = $pdo->prepare("SELECT * FROM medical_supplies WHERE id = ? AND supplier_id = ? AND listing_type = 'regular'");
            $checkStmt->execute([$supplyId, $userId]);
            $itemToTransfer = $checkStmt->fetch();

            if (!$itemToTransfer) {
                set_flash('danger', 'Medical item not found or already added to Medical Supply.');
            } else {
                $today = date('Y-m-d');
                if ($itemToTransfer['expiry_date'] <= $today) {
                    set_flash('danger', "Cannot add '{$itemToTransfer['supply_name']}' to Medical Supply: Expiration date has passed (" . date('d M Y', strtotime($itemToTransfer['expiry_date'])) . "). Expired items cannot be redistributed.");
                } else {
                    $updateStmt = $pdo->prepare("UPDATE medical_supplies SET listing_type = 'surplus', status = 'Available', updated_at = NOW() WHERE id = ? AND supplier_id = ?");
                    $updateStmt->execute([$supplyId, $userId]);
                    set_flash('success', "Medical item '{$itemToTransfer['supply_name']}' has been moved to Medical Supply successfully! It is now listed in Medical Supply.");
                }
            }
            header('Location: ' . BASE_URL . '/supplier/add-supply.php');
            exit;

        } elseif ($action === 'delete_item') {
            $supplyId = (int)($_POST['supply_id'] ?? 0);
            $delStmt = $pdo->prepare("DELETE FROM medical_supplies WHERE id = ? AND supplier_id = ? AND listing_type = 'regular'");
            $delStmt->execute([$supplyId, $userId]);
            set_flash('success', 'Medical item removed from list.');
            header('Location: ' . BASE_URL . '/supplier/add-supply.php');
            exit;

        } else {
            foreach ($formData as $k => $v) {
                $formData[$k] = clean($_POST[$k] ?? '');
            }

            // Validation
            if (empty($formData['supply_name'])) $errors[] = 'Medical item name is required.';
            if (empty($formData['category_id'])) $errors[] = 'Please select a valid supply category.';
            if (!is_numeric($formData['quantity']) || (int)$formData['quantity'] <= 0) {
                $errors[] = 'Quantity must be a positive whole number.';
            }
            if (empty($formData['unit'])) {
                $errors[] = 'Unit specification is required (e.g. Boxes, Packs, Cartons).';
            } elseif (is_numeric($formData['unit'])) {
                $errors[] = 'Packaging unit must be a text unit name (e.g. Boxes, Packs, Units), not a number.';
            }
            if (empty($formData['condition_status'])) $errors[] = 'Please select physical condition status.';
            if (empty($formData['packaging_status'])) $errors[] = 'Please select packaging seal status.';
            if (empty($formData['expiry_date'])) {
                $errors[] = 'Expiry date is required.';
            }
            if (empty($formData['location'])) $errors[] = 'Physical pickup location/city is required.';

            if (empty($errors)) {
                try {
                    $priority = calculate_supply_priority_score(
                        $formData['expiry_date'],
                        $formData['condition_status'],
                        (int)$formData['quantity']
                    );

                    $initialStatus = 'Available';

                    $stmt = $pdo->prepare("INSERT INTO medical_supplies 
                        (supplier_id, category_id, supply_name, description, quantity, unit, listing_type, condition_status, packaging_status, expiry_date, batch_number, storage_requirements, location, status, priority_score, priority_level, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'regular', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

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

                    set_flash('success', "Medical item '{$formData['supply_name']}' added successfully! You can review it below and click 'Add to Medical Supply' to transfer it.");
                    header('Location: ' . BASE_URL . '/supplier/add-supply.php');
                    exit;

                } catch (PDOException $e) {
                    error_log("Add supply error: " . $e->getMessage());
                    $errors[] = 'Database error while saving the supply record. Please try again.';
                }
            }
        }
    }
}

// Fetch all medical items (listing_type = 'regular') awaiting addition to Medical Supply
$itemsStmt = $pdo->prepare("SELECT s.*, c.category_name 
                            FROM medical_supplies s 
                            JOIN categories c ON s.category_id = c.id 
                            WHERE s.supplier_id = ? AND s.listing_type = 'regular' 
                            ORDER BY s.created_at DESC");
$itemsStmt->execute([$userId]);
$medicalItems = $itemsStmt->fetchAll();

$pageTitle = 'Medical Items';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h3 class="fw-bold text-dark mb-1">Medical Items</h3>
                <p class="text-muted small mb-0">List surplus unexpired consumables through your preferred method</p>
            </div>
            <!-- Clean 3-Option Switch (Manual Default, Barcode Scanner, Bulk CSV) -->
            <div class="d-flex gap-2">
                <button type="button" class="btn-mode-tab active" onclick="showAddTab('manual')">
                    <i class="fas fa-pen-to-square"></i> Manual Entry
                </button>
                <button type="button" class="btn-mode-tab" onclick="openBarcodeModal()">
                    <i class="fas fa-barcode"></i> Scan Barcode
                </button>
                <a href="<?php echo BASE_URL; ?>/supplier/bulk-import.php" class="btn-mode-tab">
                    <i class="fas fa-file-csv" style="color:#0f766e;"></i> Bulk CSV Import
                </a>
            </div>
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
                            <input type="text" class="form-control" id="unit" name="unit" list="unit_options" value="<?php echo e($formData['unit'] ?: 'Boxes (100 pcs)'); ?>" placeholder="Select or type unit" required>
                            <datalist id="unit_options">
                                <option value="Boxes (100 pcs)">
                                <option value="Boxes (50 pcs)">
                                <option value="Boxes (20 pcs)">
                                <option value="Boxes">
                                <option value="Packs">
                                <option value="Units / Pieces">
                                <option value="Vials / Ampoules">
                                <option value="Bottles">
                                <option value="Rolls">
                                <option value="Kits">
                                <option value="Cartons">
                                <option value="Strips">
                            </datalist>
                            <div class="form-text text-muted" style="font-size:0.75rem;">e.g. Boxes, Packs, Units (not numbers)</div>
                            <div class="invalid-feedback">Unit is required.</div>
                        </div>

                        <div class="col-md-4">
                            <label for="batch_number" class="form-label small fw-semibold">Batch / Lot / Barcode</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="batch_number" name="batch_number" value="<?php echo e($formData['batch_number']); ?>" placeholder="e.g. LOT-2024-88A">
                                <button class="btn btn-outline-teal" type="button" onclick="openBarcodeModal()" title="Scan Product Barcode" style="border-color:#0f766e; color:#0f766e;">
                                    <i class="fas fa-barcode"></i>
                                </button>
                            </div>
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
                            <label for="expiry_date" class="form-label small fw-semibold d-flex justify-content-between align-items-center">
                                <span>Expiration Date *</span>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-teal fw-semibold" onclick="document.getElementById('expiry_date').showPicker && document.getElementById('expiry_date').showPicker();">
                                    <i class="fas fa-calendar-day me-1"></i>Pick Date
                                </button>
                            </label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo e($formData['expiry_date']); ?>" required>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                <span class="badge bg-light text-dark border cursor-pointer" role="button" onclick="setExpiryDays(-30)">-30 D (Test Expired)</span>
                                <span class="badge bg-light text-dark border cursor-pointer" role="button" onclick="setExpiryDays(90)">+3 Mo</span>
                                <span class="badge bg-light text-dark border cursor-pointer" role="button" onclick="setExpiryDays(180)">+6 Mo</span>
                                <span class="badge bg-light text-dark border cursor-pointer" role="button" onclick="setExpiryDays(365)">+1 Yr</span>
                                <span class="badge bg-light text-dark border cursor-pointer" role="button" onclick="setExpiryDays(730)">+2 Yr</span>
                            </div>
                            <div class="invalid-feedback">Expiration date is required.</div>
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
                        <button type="submit" class="btn btn-teal text-white px-4 shadow-xs" style="background:#0f766e;">
                            <i class="fas fa-plus-circle me-1"></i> Add Medical Item
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Medical Items List (Awaiting Addition to Medical Supply) -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <span class="rounded-circle p-2 d-inline-flex" style="background:#ccfbf1; color:#0f766e;">
                            <i class="fas fa-boxes-stacked"></i>
                        </span>
                        Medical Items List
                    </h5>
                    <p class="text-muted small mb-0 mt-1">Review added medical items below. Click "Add to Medical Supply" to transfer them to Medical Supply. Expired items cannot be transferred.</p>
                </div>
                <span class="badge rounded-pill bg-light text-dark border px-3 py-2 fw-semibold">
                    <?php echo count($medicalItems); ?> Item<?php echo count($medicalItems) === 1 ? '' : 's'; ?>
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($medicalItems)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-box-open fs-1 mb-3 text-secondary opacity-50"></i>
                        <h6 class="fw-bold text-dark mb-1">No Pending Medical Items</h6>
                        <p class="small text-muted mb-0">Use the form above to add an item. It will appear here with an "Add to Medical Supply" action button.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Item Details</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Batch / Lot</th>
                                    <th>Expiration Date</th>
                                    <th>Location</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicalItems as $item): 
                                    $isExpired = strtotime($item['expiry_date']) <= strtotime(date('Y-m-d'));
                                    $daysRemaining = ceil((strtotime($item['expiry_date']) - time()) / 86400);
                                ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?php echo e($item['supply_name']); ?></div>
                                            <div class="text-muted small" style="font-size:0.78rem;">
                                                <?php echo e($item['condition_status']); ?> &bull; <?php echo e($item['packaging_status']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border">
                                                <?php echo e($item['category_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-dark"><?php echo number_format($item['quantity']); ?></span>
                                            <span class="text-muted small"><?php echo e($item['unit']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?php echo e($item['batch_number'] ?: 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold <?php echo $isExpired ? 'text-danger' : 'text-dark'; ?>">
                                                <i class="fas <?php echo $isExpired ? 'fa-calendar-xmark' : 'fa-calendar-check'; ?> me-1"></i>
                                                <?php echo date('d M Y', strtotime($item['expiry_date'])); ?>
                                            </div>
                                            <?php if ($isExpired): ?>
                                                <span class="badge bg-danger mt-1">
                                                    <i class="fas fa-ban me-1"></i>Expired
                                                </span>
                                            <?php elseif ($daysRemaining <= 90): ?>
                                                <span class="badge bg-warning text-dark mt-1">
                                                    <i class="fas fa-clock me-1"></i><?php echo $daysRemaining; ?> days left
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border mt-1">
                                                    <i class="fas fa-check me-1"></i><?php echo $daysRemaining; ?> days left
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-muted small"><i class="fas fa-location-dot me-1 text-secondary"></i><?php echo e($item['location']); ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-inline-flex align-items-center gap-2">
                                                <?php if ($isExpired): ?>
                                                    <button type="button" class="btn btn-sm btn-secondary disabled opacity-50 rounded-2" disabled title="Expiration date has passed. Expired items cannot be added to Medical Supply.">
                                                        <i class="fas fa-ban me-1"></i> Add to Medical Supply
                                                    </button>
                                                <?php else: ?>
                                                    <form action="<?php echo BASE_URL; ?>/supplier/add-supply.php" method="POST" class="d-inline m-0">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="add_to_medical_supply">
                                                        <input type="hidden" name="supply_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-teal text-white shadow-xs px-3 rounded-2" style="background:#0f766e;" title="Move this item to Medical Supply">
                                                            <i class="fas fa-plus-circle me-1"></i> Add to Medical Supply
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <form action="<?php echo BASE_URL; ?>/supplier/add-supply.php" method="POST" class="d-inline m-0" onsubmit="return confirm('Are you sure you want to delete this medical item?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="supply_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-2 px-2" title="Delete Medical Item">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <?php if ($isExpired): ?>
                                                <div class="text-danger small mt-1" style="font-size:0.73rem;">
                                                    <i class="fas fa-circle-exclamation me-1"></i>Item expired (action blocked)
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Barcode Scanner Modal with Live Camera Feed -->
<div class="modal fade" id="barcodeModal" tabindex="-1" aria-labelledby="barcodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="barcodeModalLabel">
                    <span class="rounded-circle p-2 d-inline-flex" style="background:#ccfbf1; color:#0f766e;">
                        <i class="fas fa-barcode"></i>
                    </span>
                    Scan Medical Package Barcode
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="stopCamera()"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <!-- Live Camera Viewfinder Box -->
                <div class="barcode-scanner-box mb-3 position-relative" id="scanner_container">
                    <video id="barcode_video" class="barcode-scanner-video d-none" playsinline autoplay muted></video>
                    
                    <div id="camera_placeholder" class="text-center text-white p-4">
                        <i class="fas fa-camera fs-1 mb-2 text-teal opacity-75"></i>
                        <h6 class="fw-bold mb-1">Live Camera Barcode Scanner</h6>
                        <p class="small text-light opacity-75 mb-3">Turn on camera to scan physical consumable barcodes (UPC / EAN / GTIN)</p>
                        <button type="button" class="btn btn-teal text-white btn-sm px-3 shadow-sm" id="btn_start_cam" onclick="startCamera()" style="background:#0f766e;">
                            <i class="fas fa-video me-1"></i> Start Camera
                        </button>
                    </div>

                    <!-- Red Laser Scanning Animation & Guide -->
                    <div id="barcode_laser" class="barcode-laser d-none"></div>
                    <div id="barcode_guide" class="barcode-target-guide d-none"></div>
                    <div id="cam_status_badge" class="position-absolute bottom-0 start-0 m-2 badge bg-dark bg-opacity-75 text-white small d-none">
                        <i class="fas fa-circle-notch fa-spin me-1 text-teal"></i> Camera Live: Aim at barcode
                    </div>
                </div>

                <div id="camera_error_alert" class="alert alert-warning border-0 small d-none text-start p-2 mb-3">
                    <i class="fas fa-exclamation-triangle me-1"></i> <span id="camera_error_msg">Camera not accessible.</span>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_stop_cam" onclick="stopCamera()" style="display:none;">
                        <i class="fas fa-stop me-1"></i> Stop Camera
                    </button>
                    <button type="button" class="btn btn-outline-teal btn-sm ms-auto" onclick="simulateCameraScan()" style="color:#0f766e; border-color:#0f766e;">
                        <i class="fas fa-bolt me-1"></i> Simulate Camera Detection
                    </button>
                </div>

                <div class="mb-3 text-start">
                    <label class="form-label small fw-semibold text-muted">Or enter barcode manually:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="manual_barcode" placeholder="e.g. 8901234567890 (GTIN/EAN)">
                        <button class="btn btn-teal text-white" type="button" onclick="processBarcode(document.getElementById('manual_barcode').value)" style="background:#0f766e;">
                            Lookup
                        </button>
                    </div>
                </div>

                <!-- One click test presets -->
                <div class="text-start mt-3 pt-3 border-top">
                    <div class="small fw-bold text-muted mb-2">⚡ Quick Medical Consumable Presets:</div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="processBarcode('8901234567890')">
                            🧤 Nitrile Gloves Box
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="processBarcode('8909876543210')">
                            😷 N95 Respirators Box
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="processBarcode('8901122334455')">
                            🩹 Sterile Crepe Bandage
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" onclick="stopCamera()">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let barcodeModalInstance = null;
let mediaStream = null;
let scanInterval = null;
let barcodeDetector = null;

if ('BarcodeDetector' in window) {
    try {
        barcodeDetector = new BarcodeDetector({
            formats: ['code_128', 'ean_13', 'ean_8', 'qr_code', 'upc_a', 'upc_e']
        });
    } catch (e) {
        console.warn('BarcodeDetector error:', e);
    }
}

function openBarcodeModal() {
    const modalEl = document.getElementById('barcodeModal');
    if (!barcodeModalInstance) {
        barcodeModalInstance = new bootstrap.Modal(modalEl);
        modalEl.addEventListener('hidden.bs.modal', function () {
            stopCamera();
        });
    }
    barcodeModalInstance.show();
    setTimeout(() => {
        startCamera();
    }, 350);
}

async function startCamera() {
    const video = document.getElementById('barcode_video');
    const placeholder = document.getElementById('camera_placeholder');
    const laser = document.getElementById('barcode_laser');
    const guide = document.getElementById('barcode_guide');
    const badge = document.getElementById('cam_status_badge');
    const errAlert = document.getElementById('camera_error_alert');
    const errMsg = document.getElementById('camera_error_msg');
    const stopBtn = document.getElementById('btn_stop_cam');

    errAlert.classList.add('d-none');

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        errAlert.classList.remove('d-none');
        errMsg.textContent = 'Camera API (getUserMedia) not supported in this browser environment. You can use manual lookup or test presets.';
        return;
    }

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' }
            },
            audio: false
        });

        video.srcObject = mediaStream;
        video.classList.remove('d-none');
        placeholder.classList.add('d-none');
        laser.classList.remove('d-none');
        guide.classList.remove('d-none');
        badge.classList.remove('d-none');
        stopBtn.style.display = 'inline-block';

        await video.play();

        if (barcodeDetector) {
            scanInterval = setInterval(async () => {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    try {
                        const barcodes = await barcodeDetector.detect(video);
                        if (barcodes.length > 0) {
                            const detectedCode = barcodes[0].rawValue;
                            stopCamera();
                            processBarcode(detectedCode);
                        }
                    } catch (detErr) {
                        // ignore frame detect error
                    }
                }
            }, 300);
        }
    } catch (err) {
        console.warn('Camera access error:', err);
        errAlert.classList.remove('d-none');
        errMsg.textContent = 'Camera access blocked or webcam not detected (' + (err.name || 'Error') + '). Use manual input or quick test presets below.';
    }
}

function stopCamera() {
    if (scanInterval) {
        clearInterval(scanInterval);
        scanInterval = null;
    }
    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
        mediaStream = null;
    }
    const video = document.getElementById('barcode_video');
    const placeholder = document.getElementById('camera_placeholder');
    const laser = document.getElementById('barcode_laser');
    const guide = document.getElementById('barcode_guide');
    const badge = document.getElementById('cam_status_badge');
    const stopBtn = document.getElementById('btn_stop_cam');

    if (video) {
        video.pause();
        video.srcObject = null;
        video.classList.add('d-none');
    }
    if (placeholder) placeholder.classList.remove('d-none');
    if (laser) laser.classList.add('d-none');
    if (guide) guide.classList.add('d-none');
    if (badge) badge.classList.add('d-none');
    if (stopBtn) stopBtn.style.display = 'none';
}

function simulateCameraScan() {
    const sampleCodes = ['8901234567890', '8909876543210', '8901122334455'];
    const randomCode = sampleCodes[Math.floor(Math.random() * sampleCodes.length)];
    stopCamera();
    processBarcode(randomCode);
}

// Database of standard GTIN/Barcodes for medical supplies
const barcodeDatabase = {
    '8901234567890': {
        name: 'Sterile Nitrile Examination Gloves (Powder-Free, Size M)',
        batch: 'LOT-GLV-8821',
        category: 1, // PPE
        unit: 'Boxes (100 pcs)',
        qty: 150,
        condition: 'New / Unopened',
        packaging: 'Original Factory Seal',
        desc: 'Micro-textured fingertips, latex-free, non-sterile examination grade gloves.',
        storage: 'Cool & Dry (15-25°C)'
    },
    '8909876543210': {
        name: '3-Ply Surgical Earloop Face Masks (BFE >= 98%)',
        batch: 'LOT-MSK-4402',
        category: 1, // PPE
        unit: 'Boxes (50 pcs)',
        qty: 300,
        condition: 'New / Unopened',
        packaging: 'Original Factory Seal',
        desc: 'Fluid resistant 3-ply meltblown filter masks with soft elastic ear loops.',
        storage: 'Room Temperature'
    },
    '8901122334455': {
        name: 'Elastic Crepe Compression Bandage 10cm x 4m',
        batch: 'LOT-BND-9011',
        category: 2, // Wound Care
        unit: 'Packs (10 rolls)',
        qty: 80,
        condition: 'Sterile Sealed',
        packaging: 'Tamper Evident Packaging',
        desc: 'Fast-edge woven cotton elastic crepe bandage for joint support and wound dressing.',
        storage: 'Standard Room Temperature'
    }
};

function processBarcode(code) {
    code = (code || '').trim();
    if (!code) {
        alert('Please enter or scan a valid barcode.');
        return;
    }

    const item = barcodeDatabase[code];
    if (item) {
        document.getElementById('supply_name').value = item.name;
        document.getElementById('batch_number').value = item.batch;
        document.getElementById('quantity').value = item.qty;
        document.getElementById('unit').value = item.unit;
        document.getElementById('condition_status').value = item.condition;
        document.getElementById('packaging_status').value = item.packaging;
        document.getElementById('description').value = item.desc;
        document.getElementById('storage_requirements').value = item.storage;
        
        // Select category if option exists
        const catSelect = document.getElementById('category_id');
        if (catSelect.options.length > 1) {
            catSelect.selectedIndex = 1;
        }

        // Set default future expiry date (e.g. +18 months)
        const expDate = new Date();
        expDate.setMonth(expDate.getMonth() + 18);
        document.getElementById('expiry_date').value = expDate.toISOString().split('T')[0];

        stopCamera();
        if (barcodeModalInstance) {
            barcodeModalInstance.hide();
        }

        alert('Barcode ' + code + ' verified! Supply details auto-populated.');
    } else {
        document.getElementById('batch_number').value = 'BC-' + code;
        stopCamera();
        if (barcodeModalInstance) {
            barcodeModalInstance.hide();
        }
        alert('Barcode ' + code + ' recorded as batch identifier. Please complete any remaining fields.');
    }
}

function setExpiryDays(days) {
    const d = new Date();
    d.setDate(d.getDate() + days);
    const yr = d.getFullYear();
    const mo = String(d.getMonth() + 1).padStart(2, '0');
    const da = String(d.getDate()).padStart(2, '0');
    const expInput = document.getElementById('expiry_date');
    if (expInput) {
        expInput.value = `${yr}-${mo}-${da}`;
        expInput.dispatchEvent(new Event('change'));
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

