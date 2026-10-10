<?php
/**
 * MediCycle - Supply Details & Requisition Submission (INSERT into requests)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('ngo');

$userId = $_SESSION['user_id'];
$supplyId = (int)($_GET['id'] ?? 0);
$errors = [];

// Fetch supply details
$stmt = $pdo->prepare("SELECT s.*, c.category_name, 
                              u.name as supplier_contact, u.email as supplier_email,
                              o.organization_name as supplier_org, o.address as supplier_address, o.city as supplier_city, o.license_number
                       FROM medical_supplies s 
                       JOIN categories c ON s.category_id = c.id 
                       JOIN users u ON s.supplier_id = u.id 
                       LEFT JOIN organizations o ON u.id = o.user_id 
                       WHERE s.id = ? AND s.status = 'Available' LIMIT 1");
$stmt->execute([$supplyId]);
$supply = $stmt->fetch();

if (!$supply) {
    set_flash('danger', 'Supply listing is either unavailable, expired, or already reserved.');
    header('Location: ' . BASE_URL . '/ngo/search-supplies.php');
    exit;
}

$requestedQty = '';
$purpose = '';
$urgency = '';
$collectionDate = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Security token invalid. Please resubmit.';
    } else {
        $requestedQty = (int)($_POST['requested_quantity'] ?? 0);
        $purpose = clean($_POST['purpose'] ?? '');
        $urgency = clean($_POST['urgency'] ?? '');
        $collectionDate = clean($_POST['preferred_collection_date'] ?? '');
        $message = clean($_POST['message'] ?? '');

        if ($requestedQty <= 0) {
            $errors[] = 'Please specify a positive quantity to request.';
        } elseif ($requestedQty > $supply['quantity']) {
            $errors[] = "Requested quantity ({$requestedQty}) exceeds currently available stock ({$supply['quantity']}).";
        }

        if (empty($purpose)) {
            $errors[] = 'Please select the clinical purpose for these supplies.';
        }

        if (empty($urgency)) {
            $errors[] = 'Please select an urgency level.';
        }

        if (empty($collectionDate)) {
            $errors[] = 'Please select a preferred pickup / collection date.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Insert into requests table (MediCycle direct collection flow)
                $insReq = $pdo->prepare("INSERT INTO requests 
                    (supply_id, requester_id, requested_quantity, purpose, urgency, preferred_collection_date, message, status, requested_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
                $insReq->execute([
                    $supplyId,
                    $userId,
                    $requestedQty,
                    $purpose,
                    $urgency,
                    $collectionDate,
                    $message
                ]);
                $requestId = $pdo->lastInsertId();

                // Notify supplier of incoming request
                create_notification(
                    $pdo,
                    $supply['supplier_id'],
                    'New Requisition Request',
                    "{$_SESSION['org_name']} requested {$requestedQty} {$supply['unit']} of {$supply['supply_name']}.",
                    'supplier/requests.php'
                );

                $pdo->commit();

                set_flash('success', "Your request for {$requestedQty} {$supply['unit']} of {$supply['supply_name']} has been submitted to {$supply['supplier_org']}.");
                header('Location: ' . BASE_URL . '/ngo/my-requests.php');
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Request submission error: " . $e->getMessage());
                $errors[] = 'Database error while recording your requisition. Please try again.';
            }
        }
    }
}

$pageTitle = 'Request Supply - ' . $supply['supply_name'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="<?php echo BASE_URL; ?>/ngo/search-supplies.php" class="text-decoration-none text-muted small">
                    <i class="fas fa-arrow-left me-1"></i> Back to Search
                </a>
                <h3 class="fw-bold text-dark mt-1 mb-0"><?php echo e($supply['supply_name']); ?></h3>
            </div>
            <div>
                <?php echo priority_badge($supply['priority_level']); ?>
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

        <div class="row g-4">
            <!-- Left: Batch Information -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-box-medical text-teal me-2" style="color:#0f766e;"></i>Consumable Specifications</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Category</label>
                                <span class="fw-semibold text-dark"><?php echo e($supply['category_name']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Available Quantity</label>
                                <span class="fw-bold fs-5 text-teal"><?php echo number_format($supply['quantity']) . ' ' . e(format_unit($supply['unit'])); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Expiration Date</label>
                                <span class="fw-bold text-dark"><?php echo format_date($supply['expiry_date']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Batch / Lot Number</label>
                                <code><?php echo e($supply['batch_number'] ?? 'Batch Verified'); ?></code>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Packaging Condition</label>
                                <span class="badge bg-light text-dark border"><?php echo e($supply['condition_status']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Seal Integrity</label>
                                <span class="badge bg-light text-dark border"><?php echo e($supply['packaging_status']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Storage Guidelines</label>
                                <span class="text-secondary small"><?php echo e($supply['storage_requirements']); ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small d-block">Pickup City</label>
                                <span class="text-secondary small"><i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo e($supply['supplier_city']); ?></span>
                            </div>
                            <div class="col-12 border-top pt-3">
                                <label class="text-muted small d-block">Description & Specifications</label>
                                <p class="text-secondary small mb-0"><?php echo nl2br(e($supply['description'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Donating Entity Card -->
                <div class="card border-0 shadow-sm p-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="fas fa-hospital text-teal me-2" style="color:#0f766e;"></i>Supplying Healthcare Organization</h6>
                    <div class="small text-secondary">
                        <div class="fw-bold text-dark fs-6"><?php echo e($supply['supplier_org']); ?></div>
                        <div><i class="fas fa-map-marker-alt text-muted me-1"></i><?php echo e($supply['supplier_address'] . ', ' . $supply['supplier_city']); ?></div>
                        <div class="mt-1"><span class="badge bg-success bg-opacity-10 text-success"><i class="fas fa-check-circle me-1"></i>Verified Medical License</span></div>
                    </div>
                </div>
            </div>

            <!-- Right: Requisition Submission Form -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm p-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="fas fa-hand-holding-heart text-teal me-2" style="color:#0f766e;"></i>Submit Requisition</h5>
                    <p class="text-muted small">Request all or part of this batch for your clinic's charitable outreach.</p>

                    <form action="<?php echo BASE_URL; ?>/ngo/supply-details.php?id=<?php echo $supplyId; ?>" method="POST" class="needs-validation" novalidate>
                        <?php echo csrf_field(); ?>

                        <div class="mb-3">
                            <label for="requested_quantity" class="form-label small fw-semibold">Quantity Required * (Max: <?php echo number_format($supply['quantity']); ?> <?php echo e(format_unit($supply['unit'])); ?>)</label>
                            <input type="number" class="form-control" id="requested_quantity" name="requested_quantity" value="<?php echo e($requestedQty); ?>" min="1" max="<?php echo $supply['quantity']; ?>" placeholder="e.g. 50" required>
                            <div class="invalid-feedback">Enter a quantity between 1 and <?php echo number_format($supply['quantity']); ?>.</div>
                        </div>

                        <div class="mb-3">
                            <label for="purpose" class="form-label small fw-semibold">Clinical Purpose / Outreach Need *</label>
                            <select class="form-select" id="purpose" name="purpose" required>
                                <option value="">Select Clinical Purpose...</option>
                                <option value="Rural Health Camp" <?php echo $purpose === 'Rural Health Camp' ? 'selected' : ''; ?>>Free Mobile Rural Health Camp</option>
                                <option value="Slum Outreach Clinic" <?php echo $purpose === 'Slum Outreach Clinic' ? 'selected' : ''; ?>>Urban Slum Charitable Dispensary</option>
                                <option value="Primary Health Center" <?php echo $purpose === 'Primary Health Center' ? 'selected' : ''; ?>>Primary Outpatient Dressing Replenishment</option>
                                <option value="Disaster Relief Operations" <?php echo $purpose === 'Disaster Relief Operations' ? 'selected' : ''; ?>>Disaster Relief / Emergency Preparedness</option>
                                <option value="Maternal & Child Care" <?php echo $purpose === 'Maternal & Child Care' ? 'selected' : ''; ?>>Maternal & Child Care Outreach</option>
                            </select>
                            <div class="invalid-feedback">Please select a clinical purpose.</div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label for="urgency" class="form-label small fw-semibold">Urgency Level *</label>
                                <select class="form-select" id="urgency" name="urgency" required>
                                    <option value="">Select Urgency...</option>
                                    <option value="Low" <?php echo $urgency === 'Low' ? 'selected' : ''; ?>>Low - Routine stock</option>
                                    <option value="Medium" <?php echo $urgency === 'Medium' ? 'selected' : ''; ?>>Medium - Normal need</option>
                                    <option value="High" <?php echo $urgency === 'High' ? 'selected' : ''; ?>>High - Urgent need</option>
                                    <option value="Critical" <?php echo $urgency === 'Critical' ? 'selected' : ''; ?>>Critical - Immediate</option>
                                </select>
                                <div class="invalid-feedback">Please select an urgency level.</div>
                            </div>
                            <div class="col-sm-6">
                                <label for="preferred_collection_date" class="form-label small fw-semibold">Preferred Pickup Date *</label>
                                <input type="date" class="form-control" id="preferred_collection_date" name="preferred_collection_date" value="<?php echo e($collectionDate); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Pickup date is required.</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="message" class="form-label small fw-semibold">Message to Supplying Hospital / Store</label>
                            <textarea class="form-control" id="message" name="message" rows="2" placeholder="Describe patient community or specific pickup notes..."><?php echo e($message); ?></textarea>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-4 small text-muted">
                            <i class="fas fa-handshake me-1 text-teal" style="color:#0f766e;"></i>
                            <strong>Direct Handover Flow:</strong> When the supplier accepts, a secure Handover Code will be issued. Present the code upon physical collection to verify receipt.
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-paper-plane me-1"></i> Submit Collection Request
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
