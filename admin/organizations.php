<?php
/**
 * MediCycle - Admin Organizations Verification & Management
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$search = clean($_GET['search'] ?? '');
$statusFilter = clean($_GET['status'] ?? '');
$typeFilter = clean($_GET['type'] ?? '');

// Handle Verification & Modification Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security token mismatch. Please try again.');
    } else {
        $action = clean($_POST['action'] ?? '');
        $orgId = (int)($_POST['org_id'] ?? 0);
        $notes = clean($_POST['verification_notes'] ?? '');

        // 1. VERIFY ORGANIZATION
        if ($action === 'verify') {
            try {
                $upd = $pdo->prepare("UPDATE organizations SET verification_status = 'verified', verification_notes = ? WHERE id = ?");
                $upd->execute([$notes ?: 'Verified by System Administrator.', $orgId]);

                // Notify User
                $uStmt = $pdo->prepare("SELECT user_id, organization_name FROM organizations WHERE id = ?");
                $uStmt->execute([$orgId]);
                $org = $uStmt->fetch();
                if ($org) {
                    create_notification($pdo, $org['user_id'], 'Organization Verified!', 'Your healthcare entity has been officially verified by MediCycle admin.', 'supplier/profile.php');
                }

                set_flash('success', "Organization #{$orgId} verified successfully!");
                header('Location: ' . BASE_URL . '/admin/organizations.php');
                exit;
            } catch (PDOException $e) {
                set_flash('danger', 'Error verifying organization: ' . $e->getMessage());
            }
        }

        // 2. REJECT ORGANIZATION
        elseif ($action === 'reject') {
            try {
                $upd = $pdo->prepare("UPDATE organizations SET verification_status = 'rejected', verification_notes = ? WHERE id = ?");
                $upd->execute([$notes ?: 'Documentation incomplete or invalid.', $orgId]);

                $uStmt = $pdo->prepare("SELECT user_id, organization_name FROM organizations WHERE id = ?");
                $uStmt->execute([$orgId]);
                $org = $uStmt->fetch();
                if ($org) {
                    create_notification($pdo, $org['user_id'], 'Verification Notice', 'Organization verification was not approved: ' . $notes, 'supplier/profile.php');
                }

                set_flash('warning', "Organization #{$orgId} marked as Rejected.");
                header('Location: ' . BASE_URL . '/admin/organizations.php');
                exit;
            } catch (PDOException $e) {
                set_flash('danger', 'Error updating status: ' . $e->getMessage());
            }
        }

        // 3. EDIT DETAILS
        elseif ($action === 'edit') {
            $name = clean($_POST['organization_name'] ?? '');
            $type = clean($_POST['organization_type'] ?? '');
            $license = clean($_POST['license_number'] ?? '');
            $city = clean($_POST['city'] ?? '');
            $state = clean($_POST['state'] ?? '');
            $pincode = clean($_POST['pincode'] ?? '');
            $address = clean($_POST['address'] ?? '');

            try {
                $upd = $pdo->prepare("UPDATE organizations SET organization_name = ?, organization_type = ?, license_number = ?, city = ?, state = ?, pincode = ?, address = ?, verification_notes = ? WHERE id = ?");
                $upd->execute([$name, $type, $license, $city, $state, $pincode, $address, $notes, $orgId]);

                set_flash('success', "Organization #{$orgId} details updated successfully.");
                header('Location: ' . BASE_URL . '/admin/organizations.php');
                exit;
            } catch (PDOException $e) {
                set_flash('danger', 'Error updating details: ' . $e->getMessage());
            }
        }
    }
}

// Build Search Query
$sql = "SELECT o.*, u.name as contact_name, u.email as contact_email, u.phone as contact_phone, u.role 
        FROM organizations o 
        JOIN users u ON o.user_id = u.id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (o.organization_name LIKE ? OR o.license_number LIKE ? OR o.city LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if (!empty($statusFilter)) {
    $sql .= " AND o.verification_status = ?";
    $params[] = $statusFilter;
}

if (!empty($typeFilter)) {
    $sql .= " AND o.organization_type = ?";
    $params[] = $typeFilter;
}

$sql .= " ORDER BY o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$organizations = $stmt->fetchAll();

$pageTitle = 'Organizations Management - Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-dark mb-1">Healthcare Organizations & Clinics</h3>
                <p class="text-muted small mb-0">Verify accreditation licenses, medical facility types and regional distribution centers</p>
            </div>
        </div>

        <!-- Search and Filter Bar -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by organization name, license number, city..." value="<?php echo e($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Verification Statuses</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-teal text-white w-100" style="background:#0f766e;">Filter</button>
                    <a href="<?php echo BASE_URL; ?>/admin/organizations.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Organizations Table -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-building-circle-check text-teal me-2"></i> Registered Healthcare Entities (<?php echo count($organizations); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Organization Name</th>
                                <th>Type / Role</th>
                                <th>License #</th>
                                <th>Location</th>
                                <th>Contact Person</th>
                                <th>Verification</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($organizations)): ?>
                                <?php foreach ($organizations as $org): ?>
                                    <tr>
                                        <td>#<?php echo $org['id']; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($org['organization_name']); ?></div>
                                            <small class="text-muted"><?php echo e($org['address']); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($org['organization_type']); ?></div>
                                            <span class="badge bg-light text-dark border"><?php echo strtoupper(e($org['role'])); ?></span>
                                        </td>
                                        <td><code><?php echo e($org['license_number'] ?? 'N/A'); ?></code></td>
                                        <td>
                                            <div><?php echo e($org['city']); ?>, <?php echo e($org['state']); ?></div>
                                            <small class="text-muted"><?php echo e($org['pincode']); ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo e($org['contact_name']); ?></div>
                                            <small class="text-muted"><?php echo e($org['contact_phone']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($org['verification_status'] === 'verified'): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Verified</span>
                                            <?php elseif ($org['verification_status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary py-0" onclick="openOrgModal(<?php echo htmlspecialchars(json_encode($org)); ?>)">
                                                Review / Action
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No organizations found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Review & Verification Modal -->
<div class="modal fade" id="orgActionModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="org_id" id="modal_org_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modal_org_title">Review Organization</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Organization Name</label>
                    <input type="text" name="organization_name" id="modal_org_name" class="form-control form-control-sm" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Entity Type</label>
                        <input type="text" name="organization_type" id="modal_org_type" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">License Number</label>
                        <input type="text" name="license_number" id="modal_license" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label class="form-label small fw-semibold">City</label>
                        <input type="text" name="city" id="modal_city" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-semibold">State</label>
                        <input type="text" name="state" id="modal_state" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small fw-semibold">Pincode</label>
                        <input type="text" name="pincode" id="modal_pincode" class="form-control form-control-sm" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Physical Street Address</label>
                    <textarea name="address" id="modal_address" rows="2" class="form-control form-control-sm" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Admin Verification Notes</label>
                    <textarea name="verification_notes" id="modal_notes" rows="2" class="form-control form-control-sm" placeholder="Notes on accreditation, license validity, or verification status..."></textarea>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-times me-1"></i> Reject
                </button>
                <div class="d-flex gap-2">
                    <button type="submit" name="action" value="edit" class="btn btn-sm btn-outline-secondary">
                        Save Changes
                    </button>
                    <button type="submit" name="action" value="verify" class="btn btn-sm btn-success">
                        <i class="fas fa-check me-1"></i> Verify Organization
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openOrgModal(org) {
    document.getElementById('modal_org_id').value = org.id;
    document.getElementById('modal_org_title').textContent = 'Review: ' + org.organization_name;
    document.getElementById('modal_org_name').value = org.organization_name;
    document.getElementById('modal_org_type').value = org.organization_type;
    document.getElementById('modal_license').value = org.license_number || '';
    document.getElementById('modal_city').value = org.city;
    document.getElementById('modal_state').value = org.state;
    document.getElementById('modal_pincode').value = org.pincode;
    document.getElementById('modal_address').value = org.address;
    document.getElementById('modal_notes').value = org.verification_notes || '';
    new bootstrap.Modal(document.getElementById('orgActionModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
