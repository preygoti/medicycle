<?php
/**
 * MediCycle - Admin User Management (Full CRUD & Search)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$errors = [];
$search = clean($_GET['search'] ?? '');
$roleFilter = clean($_GET['role'] ?? '');
$statusFilter = clean($_GET['status'] ?? '');

// Handle ACTIONS: CREATE, UPDATE, DELETE, TOGGLE STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security validation token mismatch.');
    } else {
        $action = clean($_POST['action'] ?? '');
        
        // 1. ADD USER (INSERT)
        if ($action === 'create') {
            $name = clean($_POST['name'] ?? '');
            $email = clean($_POST['email'] ?? '');
            $phone = clean($_POST['phone'] ?? '');
            $role = clean($_POST['role'] ?? 'supplier');
            $password = $_POST['password'] ?? '';
            $status = clean($_POST['status'] ?? 'active');

            if (empty($name) || empty($email) || empty($password)) {
                set_flash('danger', 'Name, email, and password are required.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                set_flash('danger', 'Invalid email address format.');
            } else {
                try {
                    $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    $chk->execute([$email]);
                    if ($chk->fetch()) {
                        set_flash('danger', 'A user with this email address already exists.');
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $ins = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, email_verified, status, created_at) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())");
                        $ins->execute([$name, $email, $hash, $phone, $role, $status]);
                        $newId = $pdo->lastInsertId();

                        // Create basic organization entry if supplier or ngo
                        if (in_array($role, ['supplier', 'ngo', 'delivery'])) {
                            $orgType = match($role) {
                                'supplier' => 'Hospital / Medical Center',
                                'ngo'      => 'Charitable Clinic / NGO',
                                'delivery' => 'Volunteer Transport Fleet'
                            };
                            $insOrg = $pdo->prepare("INSERT INTO organizations (user_id, organization_name, organization_type, address, city, state, pincode, verification_status, created_at) VALUES (?, ?, ?, 'Address on file', 'Ahmedabad', 'Gujarat', '380001', 'verified', NOW())");
                            $insOrg->execute([$newId, $name, $orgType]);
                        }

                        set_flash('success', "User '{$name}' successfully created!");
                        header('Location: ' . BASE_URL . '/admin/users.php');
                        exit;
                    }
                } catch (PDOException $e) {
                    set_flash('danger', 'Error creating user: ' . $e->getMessage());
                }
            }
        }

        // 2. EDIT USER (UPDATE)
        elseif ($action === 'update') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $name = clean($_POST['name'] ?? '');
            $email = clean($_POST['email'] ?? '');
            $phone = clean($_POST['phone'] ?? '');
            $role = clean($_POST['role'] ?? '');
            $status = clean($_POST['status'] ?? 'active');
            $newPassword = $_POST['new_password'] ?? '';

            if (empty($name) || empty($email)) {
                set_flash('danger', 'Name and email are required.');
            } else {
                try {
                    if (!empty($newPassword)) {
                        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                        $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ?, password = ?, updated_at = NOW() WHERE id = ?");
                        $upd->execute([$name, $email, $phone, $role, $status, $hash, $userId]);
                    } else {
                        $upd = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
                        $upd->execute([$name, $email, $phone, $role, $status, $userId]);
                    }
                    set_flash('success', "User #{$userId} successfully updated!");
                    header('Location: ' . BASE_URL . '/admin/users.php');
                    exit;
                } catch (PDOException $e) {
                    set_flash('danger', 'Error updating user: ' . $e->getMessage());
                }
            }
        }

        // 3. DELETE USER (DELETE)
        elseif ($action === 'delete') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId === (int)$_SESSION['user_id']) {
                set_flash('danger', 'Cannot delete your own active administrator account.');
            } else {
                try {
                    $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $del->execute([$userId]);
                    set_flash('success', "User #{$userId} has been permanently deleted.");
                    header('Location: ' . BASE_URL . '/admin/users.php');
                    exit;
                } catch (PDOException $e) {
                    set_flash('danger', 'Cannot delete user: foreign key constraint exists with active supplies or transactions.');
                }
            }
        }
    }
}

// Build Search and Filter SQL Query
$sql = "SELECT u.*, o.organization_name, o.organization_type, o.city 
        FROM users u 
        LEFT JOIN organizations o ON u.id = o.user_id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR o.organization_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($roleFilter)) {
    $sql .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY u.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users - Admin';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<div class="app-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="app-content">
        <?php echo render_flash_messages(); ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h3 class="fw-bold text-dark mb-1">User Account Management</h3>
                <p class="text-muted small mb-0">Role assignments, account security, activation status & associated organizations</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-user-plus me-1"></i> Add New User
                </button>
            </div>
        </div>

        <!-- Search and Filter Bar -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone, organization..." value="<?php echo e($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="supplier" <?php echo $roleFilter === 'supplier' ? 'selected' : ''; ?>>Supplier</option>
                        <option value="ngo" <?php echo $roleFilter === 'ngo' ? 'selected' : ''; ?>>NGO / Clinic</option>
                        <option value="delivery" <?php echo $roleFilter === 'delivery' ? 'selected' : ''; ?>>Delivery Partner</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-teal text-white w-100" style="background:#0f766e;">Filter</button>
                    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-users text-teal me-2"></i> All System Users (<?php echo count($users); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>User / Contact</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Organization</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>#<?php echo $u['id']; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo e($u['name']); ?></div>
                                            <small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo e($u['phone']); ?></small>
                                        </td>
                                        <td><code><?php echo e($u['email']); ?></code></td>
                                        <td>
                                            <?php
                                                $roleBadge = match($u['role']) {
                                                    'admin'    => 'bg-danger',
                                                    'supplier' => 'bg-primary',
                                                    'ngo'      => 'bg-success',
                                                    'delivery' => 'bg-warning text-dark',
                                                    default    => 'bg-secondary'
                                                };
                                            ?>
                                            <span class="badge <?php echo $roleBadge; ?>"><?php echo strtoupper(e($u['role'])); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($u['organization_name']): ?>
                                                <div class="fw-semibold"><?php echo e($u['organization_name']); ?></div>
                                                <small class="text-muted"><?php echo e($u['city']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Direct User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $u['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo ucfirst(e($u['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted"><?php echo format_date($u['created_at']); ?></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-primary py-0" 
                                                    onclick="openEditUser(<?php echo htmlspecialchars(json_encode($u)); ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Permanently delete user #<?php echo $u['id']; ?>? This cannot be undone.');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 ms-1">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No users found matching your search query.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus text-teal me-2"></i> Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Full Name / Contact Person</label>
                    <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Dr. Rajesh Sharma">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control form-control-sm" required placeholder="user@organization.org">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Contact Phone</label>
                    <input type="text" name="phone" class="form-control form-control-sm" required placeholder="+91 9876543210">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Role</label>
                        <select name="role" class="form-select form-select-sm" required>
                            <option value="supplier">Supplier (Hospital/Pharmacy)</option>
                            <option value="ngo">NGO / Clinic</option>
                            <option value="delivery">Delivery Partner / Volunteer</option>
                            <option value="admin">System Administrator</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Initial Password</label>
                    <input type="password" name="password" class="form-control form-control-sm" required minlength="6" placeholder="Minimum 6 characters">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-edit text-teal me-2"></i> Edit User Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Full Name</label>
                    <input type="text" name="name" id="edit_name" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email Address</label>
                    <input type="email" name="email" id="edit_email" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control form-control-sm" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Role</label>
                        <select name="role" id="edit_role" class="form-select form-select-sm" required>
                            <option value="supplier">Supplier</option>
                            <option value="ngo">NGO / Clinic</option>
                            <option value="delivery">Delivery Partner</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" id="edit_status" class="form-select form-select-sm" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Reset Password (leave empty to keep current)</label>
                    <input type="password" name="new_password" class="form-control form-control-sm" placeholder="New password if updating">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_phone').value = user.phone;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status').value = user.status;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
