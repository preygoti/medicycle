<?php
/**
 * MediCycle - Admin Supply Requests & Redistribution Oversight
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$search = clean($_GET['search'] ?? '');
$statusFilter = clean($_GET['status'] ?? '');

// Handle Admin Actions (Override Approval, Assign Delivery, Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch.');
    } else {
        $action = clean($_POST['action'] ?? '');
        $requestId = (int)($_POST['request_id'] ?? 0);

        if ($action === 'approve') {
            try {
                $reqStmt = $pdo->prepare("SELECT r.*, s.supplier_id, s.supply_name, s.quantity, s.location as pickup_loc, 
                                                 o.address as ngo_addr, o.city as ngo_city 
                                          FROM requests r 
                                          JOIN medical_supplies s ON r.supply_id = s.id 
                                          JOIN users u ON r.requester_id = u.id 
                                          LEFT JOIN organizations o ON u.id = o.user_id 
                                          WHERE r.id = ?");
                $reqStmt->execute([$requestId]);
                $req = $reqStmt->fetch();

                if ($req) {
                    $pdo->beginTransaction();

                    // Update request status to 'Approved'
                    $upd = $pdo->prepare("UPDATE requests SET status = 'Approved', approved_at = NOW() WHERE id = ?");
                    $upd->execute([$requestId]);

                    // Assign first available delivery partner if not already created
                    $chkDel = $pdo->prepare("SELECT id FROM deliveries WHERE request_id = ?");
                    $chkDel->execute([$requestId]);
                    if (!$chkDel->fetch()) {
                        $delPartnerStmt = $pdo->query("SELECT id FROM users WHERE role = 'delivery' AND status = 'active' LIMIT 1");
                        $delPartnerId = $delPartnerStmt->fetchColumn() ?: null;

                        $pickup = $req['pickup_loc'] ?: 'Supplier Facility';
                        $drop = ($req['ngo_addr'] ? $req['ngo_addr'] . ', ' . $req['ngo_city'] : 'NGO Clinic Dispensary');

                        $insDel = $pdo->prepare("INSERT INTO deliveries (request_id, delivery_partner_id, pickup_location, delivery_location, pickup_status, delivery_status, assigned_at) 
                                                 VALUES (?, ?, ?, ?, 'Pending', 'Assigned', NOW())");
                        $insDel->execute([$requestId, $delPartnerId, $pickup, $drop]);

                        if ($delPartnerId) {
                            create_notification($pdo, $delPartnerId, 'New Delivery Assigned', "Medical supply consignment assigned for {$req['supply_name']}.", 'delivery/assigned.php');
                        }
                    }

                    // Decrement supply quantity
                    $rem = max(0, $req['quantity'] - $req['requested_quantity']);
                    $st = ($rem == 0) ? 'Reserved' : 'Available';
                    $pdo->prepare("UPDATE medical_supplies SET quantity = ?, status = ? WHERE id = ?")->execute([$rem, $st, $req['supply_id']]);

                    // Notify parties
                    create_notification($pdo, $req['requester_id'], 'Request Approved by Admin', "Your request for {$req['supply_name']} has been approved. Logistics assigned.", 'ngo/my-requests.php');
                    create_notification($pdo, $req['supplier_id'], 'Consignment Approved', "Admin approved request #{$requestId} for {$req['supply_name']}.", 'supplier/requests.php');

                    $pdo->commit();
                    set_flash('success', "Request #{$requestId} approved and logistics dispatch generated!");
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash('danger', 'Error approving request: ' . $e->getMessage());
            }
            header('Location: ' . BASE_URL . '/admin/requests.php');
            exit;

        } elseif ($action === 'reject') {
            $reason = clean($_POST['rejection_reason'] ?? 'Rejected by administrative oversight.');
            $upd = $pdo->prepare("UPDATE requests SET status = 'Rejected', rejection_reason = ? WHERE id = ?");
            $upd->execute([$reason, $requestId]);
            set_flash('warning', "Request #{$requestId} rejected.");
            header('Location: ' . BASE_URL . '/admin/requests.php');
            exit;
        }
    }
}

// Search and Filter SQL
$sql = "SELECT r.*, ms.supply_name, ms.unit, 
               u.name as requester_name, o.organization_name as requester_org, o.city as ngo_city,
               s.name as supplier_name, so.organization_name as supplier_org,
               d.id as delivery_id, d.delivery_status, d.pickup_status
        FROM requests r 
        JOIN medical_supplies ms ON r.supply_id = ms.id 
        JOIN users u ON r.requester_id = u.id 
        LEFT JOIN organizations o ON u.id = o.user_id 
        JOIN users s ON ms.supplier_id = s.id 
        LEFT JOIN organizations so ON s.id = so.user_id 
        LEFT JOIN deliveries d ON d.request_id = r.id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (ms.supply_name LIKE ? OR u.name LIKE ? OR o.organization_name LIKE ? OR s.name LIKE ? OR so.organization_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if (!empty($statusFilter)) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY (r.status = 'Pending') DESC, r.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$pageTitle = 'Supply Requests Oversight - Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Redistribution Requisitions</h3>
                <p class="text-muted small mb-0">Track reallocation transactions between medical donors and recipient community clinics</p>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>/admin/deliveries.php" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-truck-fast me-1"></i> Deliveries & Transport
                </a>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-7">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by supply item, NGO clinic, hospital supplier..." value="<?php echo e($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending Supplier/Admin Action</option>
                        <option value="Approved" <?php echo $statusFilter === 'Approved' ? 'selected' : ''; ?>>Approved / In Fulfillment</option>
                        <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>Completed & Received</option>
                        <option value="Rejected" <?php echo $statusFilter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-teal text-white w-100" style="background:#0f766e;">Filter</button>
                    <a href="<?php echo BASE_URL; ?>/admin/requests.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Requests Table -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-hand-holding-medical text-teal me-2"></i> All Requisition Requests (<?php echo count($requests); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Req ID</th>
                                <th>Requested Consumable</th>
                                <th>Donor Supplier</th>
                                <th>Recipient Clinic</th>
                                <th>Qty</th>
                                <th>Urgency</th>
                                <th>Delivery Status</th>
                                <th>Request Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($requests)): ?>
                                <?php foreach ($requests as $r): ?>
                                    <tr>
                                        <td>#<?php echo $r['id']; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($r['supply_name']); ?></div>
                                            <small class="text-muted"><?php echo e(mb_strimwidth($r['purpose'] ?? 'Clinical care', 0, 45, '...')); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($r['supplier_org'] ?? $r['supplier_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo e($r['requester_org'] ?? $r['requester_name']); ?></div>
                                            <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo e($r['ngo_city']); ?></small>
                                        </td>
                                        <td><span class="fw-bold text-teal"><?php echo $r['requested_quantity'] . ' ' . e($r['unit']); ?></span></td>
                                        <td><?php echo priority_badge($r['urgency']); ?></td>
                                        <td>
                                            <?php if ($r['delivery_status']): ?>
                                                <a href="<?php echo BASE_URL; ?>/admin/deliveries.php" class="text-decoration-none">
                                                    <?php echo status_badge($r['delivery_status']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo status_badge($r['status']); ?></td>
                                        <td class="text-end">
                                            <?php if ($r['status'] === 'Pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success py-0" title="Admin Approve & Dispatch">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <button type="button" class="btn btn-sm btn-outline-danger py-0 ms-1" onclick="openReject(<?php echo $r['id']; ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">Action Taken</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No requisition records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="request_id" id="rej_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Reject Requisition Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Reason for Rejection</label>
                    <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Specify reason for clinical rejection or logistics unfeasibility..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-danger">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReject(id) {
    document.getElementById('rej_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
