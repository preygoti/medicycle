<?php
/**
 * MediCycle - Supplier Incoming Requests & Handover Management
 * Direct Healthcare Supplier <-> Recipient NGO/Clinic Handover Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('supplier');

$userId = $_SESSION['user_id'];

// Handle REQUEST ACTIONS (Accept, Mark Ready, Complete Handover, Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch. Please try again.');
    } else {
        $action = clean($_POST['action'] ?? '');
        $requestId = (int)($_POST['request_id'] ?? 0);
        $remarks = clean($_POST['remarks'] ?? '');
        $enteredCode = strtoupper(trim(clean($_POST['handover_code'] ?? '')));

        // Action: Fulfill a broadcast clinic requirement
        if ($action === 'fulfill_requirement') {
            $requirementId = (int)($_POST['requirement_id'] ?? 0);
            $supplyId = (int)($_POST['supply_id'] ?? 0);

            try {
                $reqStmt = $pdo->prepare("SELECT req.*, u.name as clinic_name, u.email as clinic_email, o.organization_name, o.city as clinic_city 
                                          FROM requirements req 
                                          JOIN users u ON req.organization_id = u.id 
                                          LEFT JOIN organizations o ON u.id = o.user_id 
                                          WHERE req.id = ? LIMIT 1");
                $reqStmt->execute([$requirementId]);
                $clinicReq = $reqStmt->fetch();

                if (!$clinicReq || $clinicReq['status'] !== 'Active') {
                    throw new Exception("This clinic requirement is either no longer active or already fulfilled.");
                }

                $pdo->beginTransaction();
                $handoverCode = generate_handover_code();

                // If a supply from inventory was chosen
                $selectedSupply = null;
                $supplyRecordId = null;
                if ($supplyId > 0) {
                    $supStmt = $pdo->prepare("SELECT * FROM medical_supplies WHERE id = ? AND supplier_id = ? AND status = 'Available' LIMIT 1");
                    $supStmt->execute([$supplyId, $userId]);
                    $selectedSupply = $supStmt->fetch();
                    if ($selectedSupply) {
                        $deductQty = min((int)$clinicReq['required_quantity'], (int)$selectedSupply['quantity']);
                        $remainingQty = $selectedSupply['quantity'] - $deductQty;
                        $newStatus = ($remainingQty == 0) ? 'Reserved' : 'Available';
                        $updSupply = $pdo->prepare("UPDATE medical_supplies SET quantity = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $updSupply->execute([$remainingQty, $newStatus, $supplyId]);
                        $supplyRecordId = $selectedSupply['id'];
                    }
                }

                // If not allocated from existing inventory, create a fulfilled consignment supply entry so joins and tracking remain 100% intact
                if (!$supplyRecordId) {
                    $city = !empty($_SESSION['org_city']) ? $_SESSION['org_city'] : ($clinicReq['clinic_city'] ?? 'General');
                    $location = !empty($_SESSION['org_address']) ? $_SESSION['org_address'] : ($clinicReq['clinic_address'] ?? 'Pharmacy Dispensing Center');
                    $expiry = !empty($clinicReq['required_by']) ? $clinicReq['required_by'] : date('Y-m-d', strtotime('+6 months'));
                    $batchNo = 'MC-FULFILL-' . strtoupper(substr(uniqid(), -6));

                    $insSupply = $pdo->prepare("INSERT INTO medical_supplies 
                        (supplier_id, category_id, supply_name, description, quantity, unit, listing_type, condition_status, packaging_status, expiry_date, batch_number, location, city, status, created_at)
                        VALUES (?, ?, ?, ?, 0, ?, 'regular', 'New / Unopened', 'Original Factory Seal', ?, ?, ?, ?, 'Reserved', NOW())");
                    $insSupply->execute([
                        $userId,
                        $clinicReq['category_id'],
                        $clinicReq['supply_name'],
                        'Supplied to fulfill clinical requirement #' . $requirementId . ' for ' . ($clinicReq['organization_name'] ?? $clinicReq['clinic_name']),
                        $clinicReq['unit'],
                        $expiry,
                        $batchNo,
                        $location,
                        $city
                    ]);
                    $supplyRecordId = $pdo->lastInsertId();
                }

                // 1. Mark requirement as Fulfilled
                $updReq = $pdo->prepare("UPDATE requirements SET status = 'Fulfilled', fulfilled_by_supplier_id = ?, handover_code = ?, fulfilled_at = NOW() WHERE id = ?");
                $updReq->execute([$userId, $handoverCode, $requirementId]);

                // 2. Also insert into requests table with Accepted status so both parties have a direct handover tracking code in Verify Handover
                $insReq = $pdo->prepare("INSERT INTO requests 
                    (supply_id, requester_id, requested_quantity, purpose, urgency, status, supplier_remarks, handover_code, requested_at, approved_at) 
                    VALUES (?, ?, ?, ?, ?, 'Accepted', ?, ?, NOW(), NOW())");
                $insReq->execute([
                    $supplyRecordId,
                    $clinicReq['organization_id'],
                    $clinicReq['required_quantity'],
                    'Fulfilled Clinical Requirement: ' . $clinicReq['supply_name'],
                    $clinicReq['urgency'],
                    $remarks ?: 'Supplier accepted to fulfill this broadcast clinical requirement.',
                    $handoverCode
                ]);

                // 3. Notify the NGO clinic
                $supplierOrg = $_SESSION['org_name'] ?? 'Healthcare Supplier';
                create_notification(
                    $pdo,
                    $clinicReq['organization_id'],
                    'Clinic Requirement Accepted by Supplier!',
                    "{$supplierOrg} accepted to fulfill your requirement for {$clinicReq['required_quantity']} {$clinicReq['unit']} of {$clinicReq['supply_name']}. Handover Code: {$handoverCode}.",
                    'ngo/my-requirements.php'
                );

                $pdo->commit();
                set_flash('success', "Clinic requirement accepted! Secure verification PIN issued to the clinic for handover verification.");
                header('Location: ' . BASE_URL . '/supplier/requests.php?tab=broadcasts');
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Fulfill requirement error: " . $e->getMessage());
                set_flash('danger', $e->getMessage());
                header('Location: ' . BASE_URL . '/supplier/requests.php?tab=broadcasts');
                exit;
            }
        }

        try {
            // Verify that this request belongs to one of this supplier's supplies
            $reqStmt = $pdo->prepare("SELECT r.*, s.supplier_id, s.supply_name, s.quantity, s.unit, s.location, 
                                             u.id as requester_id, u.name as requester_name,
                                             o.organization_name as requester_org, o.address as ngo_addr, o.city as ngo_city, u.phone as ngo_phone
                                      FROM requests r 
                                      JOIN medical_supplies s ON r.supply_id = s.id 
                                      JOIN users u ON r.requester_id = u.id 
                                      LEFT JOIN organizations o ON u.id = o.user_id 
                                      WHERE r.id = ? AND s.supplier_id = ? LIMIT 1");
            $reqStmt->execute([$requestId, $userId]);
            $request = $reqStmt->fetch();

            if (!$request) {
                set_flash('danger', 'Request not found or access denied.');
            } else {
                $pdo->beginTransaction();

                if ($action === 'accept') {
                    if ($request['status'] !== 'Pending') {
                        throw new Exception("Only pending requests can be accepted.");
                    }

                    // Check inventory availability
                    if ($request['quantity'] < $request['requested_quantity']) {
                        throw new Exception("Cannot accept: Available inventory ({$request['quantity']}) is less than requested quantity ({$request['requested_quantity']}).");
                    }

                    // Generate secure Handover Verification Code
                    $handoverCode = generate_handover_code();

                    // 1. Update request status to 'Accepted'
                    $updReq = $pdo->prepare("UPDATE requests SET status = 'Accepted', handover_code = ?, supplier_remarks = ?, approved_at = NOW() WHERE id = ?");
                    $updReq->execute([$handoverCode, $remarks, $requestId]);

                    // 2. Decrement supply quantity
                    $remainingQty = $request['quantity'] - $request['requested_quantity'];
                    $newSupplyStatus = ($remainingQty == 0) ? 'Reserved' : 'Available';

                    $updSupply = $pdo->prepare("UPDATE medical_supplies SET quantity = ?, status = ?, updated_at = NOW() WHERE id = ?");
                    $updSupply->execute([$remainingQty, $newSupplyStatus, $request['supply_id']]);

                    // 3. Notify NGO
                    create_notification(
                        $pdo,
                        $request['requester_id'],
                        'Request Accepted by Supplier!',
                        "Your request for {$request['requested_quantity']} {$request['unit']} of {$request['supply_name']} was accepted. Handover Code: {$handoverCode}.",
                        'ngo/my-requests.php'
                    );

                    $pdo->commit();
                    set_flash('success', "Request accepted successfully! Requisition is approved and awaiting physical verification in Verify Handover.");

                } elseif ($action === 'mark_ready') {
                    if (!in_array($request['status'], ['Accepted', 'Pending'])) {
                        throw new Exception("Request cannot be marked ready in its current status.");
                    }

                    $handoverCode = $request['handover_code'] ?: generate_handover_code();

                    // Update to 'Ready for Handover'
                    $updReq = $pdo->prepare("UPDATE requests SET status = 'Ready for Handover', handover_code = ?, supplier_remarks = COALESCE(?, supplier_remarks), ready_at = NOW() WHERE id = ?");
                    $updReq->execute([$handoverCode, $remarks ?: null, $requestId]);

                    create_notification(
                        $pdo,
                        $request['requester_id'],
                        'Supplies Ready for Pickup!',
                        "Your requested {$request['supply_name']} is packaged and ready for collection. Show Handover Code: {$handoverCode} at pickup.",
                        'ngo/my-requests.php'
                    );

                    $pdo->commit();
                    set_flash('success', "Supplies are now marked Ready for Handover.");

                } elseif ($action === 'complete_handover') {
                    if (!in_array($request['status'], ['Accepted', 'Ready for Handover', 'Collected', 'Received'])) {
                        throw new Exception("Invalid status for completing handover.");
                    }

                    // Optional code validation if entered
                    if (!empty($enteredCode) && !empty($request['handover_code']) && $enteredCode !== strtoupper($request['handover_code'])) {
                        throw new Exception("Handover verification code does not match. Expected: {$request['handover_code']}");
                    }

                    // 1. Mark request Completed
                    $updReq = $pdo->prepare("UPDATE requests SET status = 'Completed', completed_at = NOW() WHERE id = ?");
                    $updReq->execute([$requestId]);

                    // 2. Record or update Impact Metrics
                    $qty = (int)$request['requested_quantity'];
                    $wasteAvoided = round($qty * 0.15, 2); // 150g per consumable average packaging diverted
                    $valueSaved = round($qty * 25.0, 2);   // Average savings estimate in INR

                    $checkImp = $pdo->prepare("SELECT id FROM impact_metrics WHERE request_id = ? LIMIT 1");
                    $checkImp->execute([$requestId]);
                    if ($checkImp->fetch()) {
                        $updImp = $pdo->prepare("UPDATE impact_metrics SET quantity_redistributed = ?, estimated_waste_avoided = ?, estimated_value_saved = ?, recorded_at = NOW() WHERE request_id = ?");
                        $updImp->execute([$qty, $wasteAvoided, $valueSaved, $requestId]);
                    } else {
                        $insImp = $pdo->prepare("INSERT INTO impact_metrics (request_id, recipient_id, quantity_redistributed, estimated_waste_avoided, estimated_value_saved, recorded_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $insImp->execute([$requestId, $request['requester_id'], $qty, $wasteAvoided, $valueSaved]);
                    }

                    // 3. Notify NGO
                    create_notification(
                        $pdo,
                        $request['requester_id'],
                        'Handover Confirmed & Completed!',
                        "Handover of {$qty} {$request['unit']} of {$request['supply_name']} has been completed and verified. Thank you for preventing medical waste!",
                        'ngo/history.php'
                    );

                    $pdo->commit();
                    set_flash('success', "Handover verified and completed successfully! Impact metrics updated.");

                } elseif ($action === 'reject') {
                    $updReq = $pdo->prepare("UPDATE requests SET status = 'Rejected', supplier_remarks = ? WHERE id = ?");
                    $updReq->execute([$remarks, $requestId]);

                    create_notification(
                        $pdo,
                        $request['requester_id'],
                        'Requisition Declined',
                        "Your request for {$request['supply_name']} could not be fulfilled. Note from supplier: " . ($remarks ?: 'Stock unavailable or capacity reached.'),
                        'ngo/my-requests.php'
                    );

                    $pdo->commit();
                    set_flash('info', "Requisition request declined.");
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Supplier request action error: " . $e->getMessage());
            set_flash('danger', $e->getMessage());
        }
        header('Location: ' . BASE_URL . '/supplier/requests.php');
        exit;
    }
}

// Tab switcher: direct supply requests vs posted clinic broadcast requirements
$currentTab = clean($_GET['tab'] ?? '');
if (empty($currentTab)) {
    $currentTab = 'direct';
}

$statusFilter = clean($_GET['status'] ?? '');

// 1. Fetch direct requests for this supplier's inventory supplies
$sql = "SELECT r.*, s.supply_name, s.unit, s.quantity as available_qty, s.location as pickup_hub,
               u.name as requester_name, u.email as requester_email, u.phone as requester_phone,
               o.organization_name, o.city as ngo_city, o.organization_type
        FROM requests r 
        JOIN medical_supplies s ON r.supply_id = s.id 
        JOIN users u ON r.requester_id = u.id 
        LEFT JOIN organizations o ON u.id = o.user_id 
        WHERE s.supplier_id = ?";
$params = [$userId];

if (!empty($statusFilter)) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY r.requested_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// 2. Fetch clinic requirements broadcast by NGOs/clinics
$clinicReqStmt = $pdo->prepare("
    SELECT req.*, c.category_name,
           u.name as clinic_contact_name, u.email as clinic_email, u.phone as clinic_phone,
           o.organization_name, o.city as clinic_city, o.address as clinic_address, o.organization_type,
           sup.name as fulfilled_supplier_name
    FROM requirements req
    JOIN categories c ON req.category_id = c.id
    JOIN users u ON req.organization_id = u.id
    LEFT JOIN organizations o ON u.id = o.user_id
    LEFT JOIN users sup ON req.fulfilled_by_supplier_id = sup.id
    ORDER BY CASE WHEN req.status = 'Active' THEN 0 ELSE 1 END, req.created_at DESC
");
$clinicReqStmt->execute();
$clinicRequirements = $clinicReqStmt->fetchAll();

$activeClinicReqCount = 0;
foreach ($clinicRequirements as $cr) {
    if ($cr['status'] === 'Active') $activeClinicReqCount++;
}

// 3. Supplier's available supplies for fulfillment dropdown
$mySuppliesStmt = $pdo->prepare("SELECT id, supply_name, quantity, unit, batch_number, expiry_date, location FROM medical_supplies WHERE supplier_id = ? AND status = 'Available' AND quantity > 0 ORDER BY supply_name ASC");
$mySuppliesStmt->execute([$userId]);
$myAvailableSupplies = $mySuppliesStmt->fetchAll();

$pageTitle = 'Incoming Requests & Handovers';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
            <div>
                <h3 class="fw-bold text-dark mb-1">Incoming Requests & Direct Handovers</h3>
                <p class="text-muted small mb-0">Manage clinic requests, fulfill broadcast clinical needs, and issue Handover Codes</p>
            </div>
            <?php if ($currentTab !== 'broadcasts'): ?>
                <!-- Status filter buttons with dedicated .filter-pill class (Zero white-on-white glitch) -->
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo BASE_URL; ?>/supplier/requests.php?tab=direct" class="filter-pill <?php echo empty($statusFilter) ? 'active-all' : ''; ?>">All</a>
                    <a href="<?php echo BASE_URL; ?>/supplier/requests.php?tab=direct&status=Pending" class="filter-pill <?php echo $statusFilter === 'Pending' ? 'active-pending' : ''; ?>">Pending</a>
                    <a href="<?php echo BASE_URL; ?>/supplier/requests.php?tab=direct&status=Accepted" class="filter-pill <?php echo $statusFilter === 'Accepted' ? 'active-accepted' : ''; ?>">Accepted</a>
                    <a href="<?php echo BASE_URL; ?>/supplier/requests.php?tab=direct&status=Ready for Handover" class="filter-pill <?php echo $statusFilter === 'Ready for Handover' ? 'active-ready' : ''; ?>">Ready</a>
                    <a href="<?php echo BASE_URL; ?>/supplier/requests.php?tab=direct&status=Completed" class="filter-pill <?php echo $statusFilter === 'Completed' ? 'active-completed' : ''; ?>">Completed</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Mode Tab Navigation: Direct Requisitions vs Clinic Requirements Broadcasts -->
        <ul class="nav nav-pills custom-req-tabs mb-4 gap-2" id="requestModeTabs">
            <li class="nav-item">
                <a class="nav-link rounded-pill px-4 py-2 fw-semibold <?php echo $currentTab !== 'broadcasts' ? 'active shadow-sm' : 'bg-white border text-secondary'; ?>" href="<?php echo BASE_URL; ?>/supplier/requests.php?tab=direct">
                    <i class="fas fa-boxes-packing me-1"></i> Direct Supply Requests 
                    <span class="badge rounded-pill ms-2 <?php echo $currentTab !== 'broadcasts' ? 'bg-white text-teal' : 'bg-secondary text-white'; ?>"><?php echo count($requests); ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill px-4 py-2 fw-semibold <?php echo $currentTab === 'broadcasts' ? 'active shadow-sm' : 'bg-white border text-secondary'; ?>" href="<?php echo BASE_URL; ?>/supplier/requests.php?tab=broadcasts">
                    <i class="fas fa-bullhorn me-1"></i> Posted Clinic Requirements 
                    <?php if ($activeClinicReqCount > 0): ?>
                        <span class="badge rounded-pill ms-2 bg-danger text-white"><?php echo $activeClinicReqCount; ?> Active</span>
                    <?php else: ?>
                        <span class="badge rounded-pill ms-2 bg-light text-secondary"><?php echo count($clinicRequirements); ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <?php if ($currentTab !== 'broadcasts' && $activeClinicReqCount > 0): ?>
            <!-- Alert Banner for Broadcast Clinical Needs -->
            <div class="alert alert-info border-0 shadow-xs rounded-4 d-flex flex-wrap align-items-center justify-content-between mb-4 py-3 px-3 gap-2" style="background:#f0fdfa; border-left:4px solid #0f766e !important;">
                <div class="d-flex align-items-center gap-2">
                    <span class="rounded-circle p-2 d-inline-flex" style="background:#ccfbf1; color:#0f766e;">
                        <i class="fas fa-bullhorn"></i>
                    </span>
                    <span class="small fw-semibold text-dark">
                        <strong><?php echo $activeClinicReqCount; ?> Clinic Requirement<?php echo $activeClinicReqCount > 1 ? 's' : ''; ?></strong> broadcast by recipient healthcare clinics waiting for supplier supplies.
                    </span>
                </div>
                <a href="<?php echo BASE_URL; ?>/supplier/requests.php?tab=broadcasts" class="btn btn-sm text-white rounded-pill px-3 shadow-2xs fw-semibold" style="background:#0f766e;">
                    View Clinic Needs <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($currentTab === 'broadcasts'): ?>
            <!-- Clinic Requirements Broadcasts Table -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="fas fa-bullhorn text-teal"></i> Clinic Requirements Broadcast Network
                        </h6>
                        <small class="text-muted">Broadcast needs from clinics seeking medicine and medical supplies</small>
                    </div>
                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-semibold">
                        <?php echo count($clinicRequirements); ?> Total Needs
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Item Needed</th>
                                    <th>Clinic / Recipient Organization</th>
                                    <th>Quantity Needed</th>
                                    <th>Urgency & Required By</th>
                                    <th>Broadcast Status</th>
                                    <th class="text-start pe-4 text-nowrap">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($clinicRequirements)): ?>
                                    <?php foreach ($clinicRequirements as $cr): ?>
                                        <tr id="clinic-req-<?php echo $cr['id']; ?>">
                                            <td class="ps-4">
                                                <span class="fw-bold text-dark d-block"><?php echo e($cr['supply_name']); ?></span>
                                                <span class="badge bg-light text-secondary border mt-1" style="font-size:0.7rem;"><?php echo e($cr['category_name']); ?></span>
                                                <?php if (!empty($cr['description'])): ?>
                                                    <div class="text-muted small mt-1 text-truncate" style="max-width: 220px;" title="<?php echo e($cr['description']); ?>">
                                                        <i class="fas fa-info-circle me-1 text-teal"></i><?php echo e($cr['description']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-dark d-block"><?php echo e($cr['organization_name'] ?? $cr['clinic_contact_name']); ?></span>
                                                <span class="text-muted small">
                                                    <i class="fas fa-map-marker-alt me-1 text-danger"></i><?php echo e($cr['clinic_city']); ?> | 
                                                    <i class="fas fa-phone me-1"></i><?php echo e($cr['clinic_phone'] ?: 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="fw-bold fs-6 text-teal"><?php echo number_format($cr['required_quantity']) . ' ' . e($cr['unit']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo priority_badge($cr['urgency'] ?? 'Medium'); ?>
                                                <?php if (!empty($cr['required_by'])): ?>
                                                    <div class="small text-muted mt-1">
                                                        <i class="far fa-calendar-alt me-1"></i>Needed: <?php echo format_date($cr['required_by']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo status_badge($cr['status']); ?>
                                                <?php if (!empty($cr['handover_code'])): ?>
                                                    <div class="mt-1">
                                                        <span class="badge rounded-pill bg-light text-secondary border px-2 py-0.5" style="font-size:0.68rem;" title="Secure PIN issued to clinic for collection verification">
                                                            <i class="fas fa-lock text-teal me-1"></i>PIN Kept by Clinic
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($cr['fulfilled_supplier_name'])): ?>
                                                    <div class="small text-muted mt-1" style="font-size:0.7rem;">
                                                        Fulfilled by: <?php echo e($cr['fulfilled_supplier_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-start pe-4 text-nowrap">
                                                <?php if ($cr['status'] === 'Active'): ?>
                                                    <button type="button" class="btn btn-sm text-white rounded-pill px-3 py-1 shadow-xs d-inline-flex align-items-center gap-1 fw-semibold" style="background:#0f766e;" data-bs-toggle="modal" data-bs-target="#fulfillModal<?php echo $cr['id']; ?>">
                                                        <i class="fas fa-hand-holding-medical"></i> Fulfill Need
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" style="font-size:0.76rem;">
                                                        <i class="fas fa-circle-check"></i> Fulfilled
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-clipboard-check fs-3 text-secondary d-block mb-2"></i>
                                            No active clinical requirements posted by clinics at the moment.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Direct Supply Requests Table -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Supply Item</th>
                                    <th>Recipient Organization</th>
                                    <th>Requested Qty</th>
                                    <th>Urgency & Date</th>
                                    <th>Handover Status</th>
                                    <th class="text-start pe-4 text-nowrap">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($requests)): ?>
                                    <?php foreach ($requests as $r): ?>
                                        <tr id="req-<?php echo $r['id']; ?>">
                                            <td class="ps-4">
                                                <span class="fw-bold text-dark d-block"><?php echo e($r['supply_name']); ?></span>
                                                <span class="text-muted small">In Stock: <?php echo $r['available_qty'] . ' ' . e($r['unit']); ?></span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-dark d-block"><?php echo e($r['organization_name'] ?? $r['requester_name']); ?></span>
                                                <span class="text-muted small">
                                                    <i class="fas fa-map-marker-alt me-1 text-danger"></i><?php echo e($r['ngo_city'] ?? 'City N/A'); ?> | 
                                                    <i class="fas fa-phone me-1"></i><?php echo e($r['requester_phone']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="fw-bold fs-6 text-teal"><?php echo number_format($r['requested_quantity']) . ' ' . e($r['unit']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo priority_badge($r['urgency'] ?? 'Medium'); ?>
                                                <?php if (!empty($r['preferred_collection_date'])): ?>
                                                    <div class="small text-muted mt-1">
                                                        <i class="far fa-calendar-alt me-1"></i>Pickup: <?php echo format_date($r['preferred_collection_date']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo status_badge($r['status']); ?>
                                                <?php if (!empty($r['supplier_remarks'])): ?>
                                                    <div class="text-muted small mt-1 text-truncate" style="max-width: 150px;" title="<?php echo e($r['supplier_remarks']); ?>">
                                                        <i class="fas fa-comment-dots me-1"></i><?php echo e($r['supplier_remarks']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-start pe-4 text-nowrap">
                                                <?php if ($r['status'] === 'Pending'): ?>
                                                    <div class="d-inline-flex align-items-center gap-2 justify-content-start">
                                                        <button type="button" class="btn btn-sm btn-success rounded-2 px-3 py-1 shadow-xs d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#acceptModal<?php echo $r['id']; ?>">
                                                            <i class="fas fa-check"></i> Accept
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-2 px-3 py-1 shadow-xs d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $r['id']; ?>">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </div>
                                                <?php elseif (in_array($r['status'], ['Accepted', 'Ready for Handover', 'In Transit', 'Collected'])): ?>
                                                    <span class="badge rounded-pill bg-light text-secondary border px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" style="font-size:0.76rem;">
                                                        <i class="fas fa-handshake text-teal"></i> Verify at Handover
                                                    </span>
                                                <?php else: ?>
                                                    <?php if ($r['status'] === 'Completed'): ?>
                                                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" style="font-size:0.76rem;">
                                                            <i class="fas fa-circle-check"></i> Fulfilled
                                                        </span>
                                                    <?php elseif ($r['status'] === 'Rejected'): ?>
                                                        <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" style="font-size:0.76rem;">
                                                            <i class="fas fa-circle-xmark"></i> Declined
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge rounded-pill bg-light text-secondary border px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-xs" style="font-size:0.76rem;">
                                                            <i class="fas fa-check-circle text-muted"></i> No Action Needed
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fas fa-inbox fs-3 text-secondary d-block mb-2"></i>
                                            No requisition requests found matching your filter.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- ========================================================================= -->
<!-- Modals Rendered Outside Tables & Cards for Valid HTML5 & Reliable Form POST -->
<!-- ========================================================================= -->

<!-- 1. Fulfill Clinic Broadcast Need Modals -->
<?php if (!empty($clinicRequirements)): ?>
    <?php foreach ($clinicRequirements as $cr): ?>
        <?php if ($cr['status'] === 'Active'): ?>
            <div class="modal fade" id="fulfillModal<?php echo $cr['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <form action="<?php echo BASE_URL; ?>/supplier/requests.php" method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="fulfill_requirement">
                            <input type="hidden" name="requirement_id" value="<?php echo $cr['id']; ?>">
                            <div class="modal-header border-bottom py-3">
                                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                    <span class="rounded-circle p-2 d-inline-flex" style="background:#ccfbf1; color:#0f766e;">
                                        <i class="fas fa-hand-holding-medical"></i>
                                    </span>
                                    Fulfill Clinic Requirement
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="p-3 rounded-3 mb-3" style="background:#f8fafc; border:1px solid #e2e8f0;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="text-dark fs-6"><?php echo e($cr['supply_name']); ?></strong>
                                        <span class="badge bg-teal text-white" style="background:#0f766e;"><?php echo number_format($cr['required_quantity']) . ' ' . e($cr['unit']); ?></span>
                                    </div>
                                    <div class="small text-muted">Clinic: <?php echo e($cr['organization_name'] ?? $cr['clinic_contact_name']); ?> (<?php echo e($cr['clinic_city']); ?>)</div>
                                    <?php if (!empty($cr['description'])): ?>
                                        <div class="small text-secondary mt-1"><em>"<?php echo e($cr['description']); ?>"</em></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Allocate From Your Listed Inventory (Optional)</label>
                                    <select name="supply_id" class="form-select form-select-sm">
                                        <option value="0">-- Provide Directly / Source Direct --</option>
                                        <?php foreach ($myAvailableSupplies as $sup): ?>
                                            <option value="<?php echo $sup['id']; ?>">
                                                <?php echo e($sup['supply_name']); ?> (Available: <?php echo $sup['quantity'] . ' ' . e($sup['unit']); ?> | Batch: <?php echo e($sup['batch_number'] ?: 'N/A'); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text small">Selecting a batch automatically reserves inventory for this clinic.</div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Collection / Pickup Instructions</label>
                                    <textarea name="remarks" class="form-control form-control-sm" rows="3" placeholder="e.g. Ready for pickup at Pharmacy Dispatch Counter, Mon-Fri 10am-4pm. Please show Handover Code."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer border-top py-3">
                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-teal text-white rounded-pill px-4 shadow-xs fw-semibold" style="background:#0f766e;">
                                    <i class="fas fa-check-circle me-1"></i> Accept & Send PIN to Clinic
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<!-- 2. Accept & Reject Direct Requisition Modals -->
<?php if (!empty($requests)): ?>
    <?php foreach ($requests as $r): ?>
        <?php if ($r['status'] === 'Pending'): ?>
            <!-- Accept Modal -->
            <div class="modal fade" id="acceptModal<?php echo $r['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <form action="<?php echo BASE_URL; ?>/supplier/requests.php" method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="accept">
                            <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                            <div class="modal-header border-bottom py-3">
                                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                                    <span class="rounded-circle p-2 d-inline-flex" style="background:#ccfbf1; color:#0f766e;">
                                        <i class="fas fa-check"></i>
                                    </span>
                                    Accept Requisition Request
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4">
                                <p class="text-secondary small mb-3">
                                    Accepting this request will allocate <strong><?php echo $r['requested_quantity'] . ' ' . e($r['unit']); ?></strong> of <strong><?php echo e($r['supply_name']); ?></strong> to <strong><?php echo e($r['organization_name'] ?? $r['requester_name']); ?></strong>. The recipient clinic will receive their secure verification code for pickup.
                                </p>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Pickup / Handover Instructions</label>
                                    <textarea name="remarks" class="form-control" rows="3" placeholder="e.g. Ready for collection Mon-Fri 10am-4pm at Main Pharmacy Counter."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer border-top py-3">
                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success rounded-pill px-4 shadow-xs">
                                    <i class="fas fa-check-circle me-1"></i> Confirm & Accept Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Reject Modal -->
            <div class="modal fade" id="rejectModal<?php echo $r['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <form action="<?php echo BASE_URL; ?>/supplier/requests.php" method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                            <div class="modal-header border-bottom py-3">
                                <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2">
                                    <span class="rounded-circle p-2 d-inline-flex" style="background:#fee2e2; color:#ef4444;">
                                        <i class="fas fa-times"></i>
                                    </span>
                                    Decline Requisition Request
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Reason for Declining *</label>
                                    <textarea name="remarks" class="form-control" rows="3" placeholder="Stock reserved, clinic capacity mismatch, or other reason..." required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer border-top py-3">
                                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger rounded-pill px-4 shadow-xs">Decline Request</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
