<?php
/**
 * MediCycle - Dynamic Role-based Sidebar
 * Seamless navigation for Admin, Supplier, NGO/Clinic, and Delivery Partner
 */
$currentUser = current_user();
$currentScript = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

if (!function_exists('is_active_link')) {
    function is_active_link($script, $dir = null) {
        global $currentScript, $currentDir;
        if ($dir !== null && $currentDir !== $dir) {
            return '';
        }
        return ($currentScript === $script) ? 'active' : '';
    }
}
$userRole = $currentUser['role'] ?? ($_SESSION['role'] ?? '');
?>
<aside class="app-sidebar shadow-sm">
    <div class="sidebar-heading">
        <?php echo strtoupper(e($userRole)); ?> PORTAL
    </div>
    <ul class="nav flex-column mb-auto">
        <?php if ($userRole === 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('dashboard.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/dashboard.php">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('users.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/users.php">
                    <i class="fas fa-users"></i> User Accounts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('organizations.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/organizations.php">
                    <i class="fas fa-building-circle-check"></i> Organizations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('supplies.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/supplies.php">
                    <i class="fas fa-boxes-stacked"></i> All Supplies
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('requirements.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/requirements.php">
                    <i class="fas fa-clipboard-list"></i> Requirements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('requests.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/requests.php">
                    <i class="fas fa-hand-holding-medical"></i> Supply Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('deliveries.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/deliveries.php">
                    <i class="fas fa-truck-fast"></i> Deliveries
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('verify-handover.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/verify-handover.php">
                    <i class="fas fa-qrcode text-warning"></i> Verify Handover
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('categories.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/categories.php">
                    <i class="fas fa-tags"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('reports.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/reports.php">
                    <i class="fas fa-file-invoice"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('analytics.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/analytics.php">
                    <i class="fas fa-chart-pie"></i> Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('settings.php', 'admin'); ?>" href="<?php echo BASE_URL; ?>/admin/settings.php">
                    <i class="fas fa-sliders"></i> System Settings
                </a>
            </li>

        <?php elseif ($userRole === 'supplier'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('dashboard.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/dashboard.php">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('add-supply.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/add-supply.php">
                    <i class="fas fa-plus-circle"></i> Medical Items
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('inventory.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/inventory.php">
                    <i class="fas fa-boxes-stacked"></i> Medical Supply
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('requests.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/requests.php">
                    <i class="fas fa-inbox"></i> Incoming Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('verify-handover.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/verify-handover.php">
                    <i class="fas fa-qrcode text-warning"></i> Verify Handover
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('history.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/history.php">
                    <i class="fas fa-history"></i> Transfer History
                </a>
            </li>

        <?php elseif ($userRole === 'ngo'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('dashboard.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/dashboard.php">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('search-supplies.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/search-supplies.php">
                    <i class="fas fa-search"></i> Search Supplies
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('post-requirement.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/post-requirement.php">
                    <i class="fas fa-bullhorn"></i> Post Requirement
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('my-requirements.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/my-requirements.php">
                    <i class="fas fa-clipboard-list"></i> My Requirements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('my-requests.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/my-requests.php">
                    <i class="fas fa-hands-helping"></i> My Supply Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('verify-handover.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/verify-handover.php">
                    <i class="fas fa-qrcode text-warning"></i> Verify Handover
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('history.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/history.php">
                    <i class="fas fa-history"></i> Request History
                </a>
            </li>

        <?php elseif ($userRole === 'delivery'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('dashboard.php', 'delivery'); ?>" href="<?php echo BASE_URL; ?>/delivery/dashboard.php">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('assigned.php', 'delivery'); ?>" href="<?php echo BASE_URL; ?>/delivery/assigned.php">
                    <i class="fas fa-clipboard-check"></i> Assigned Deliveries
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('update-status.php', 'delivery'); ?>" href="<?php echo BASE_URL; ?>/delivery/update-status.php">
                    <i class="fas fa-truck-ramp-box"></i> Update Status
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('verify-handover.php', 'delivery'); ?>" href="<?php echo BASE_URL; ?>/delivery/verify-handover.php">
                    <i class="fas fa-qrcode text-warning"></i> Verify Handover
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('history.php', 'delivery'); ?>" href="<?php echo BASE_URL; ?>/delivery/history.php">
                    <i class="fas fa-clock-rotate-left"></i> Delivery History
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer mt-auto">
        <a href="<?php echo BASE_URL; ?>/logout.php" class="btn-sidebar-signout mb-2">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sign Out</span>
        </a>
        <button type="button" class="btn-sidebar-delete" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
            <i class="fas fa-trash-can"></i>
            <span>Delete Account</span>
        </button>
    </div>
</aside>

<!-- Secure Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="<?php echo BASE_URL; ?>/delete-account.php" method="POST" id="deleteAccountForm">
                <?php echo csrf_field(); ?>
                <div class="modal-header border-bottom py-3 bg-danger bg-opacity-10">
                    <h5 class="modal-title fw-bold text-danger d-flex align-items-center gap-2 mb-0" id="deleteAccountModalLabel">
                        <span class="rounded-circle p-2 bg-danger text-white d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="fas fa-triangle-exclamation" style="font-size:0.85rem;"></i>
                        </span>
                        Delete Account Permanently
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <div class="alert alert-danger border-0 p-3 rounded-3 small mb-3">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fas fa-shield-alt text-danger mt-1 fs-6"></i>
                            <div>
                                <strong class="d-block mb-1">Permanent & Irreversible Action</strong>
                                Deleting your account will immediately erase your profile, organization data, clinical postings, inventory items, handshakes, and activity logs. <strong>This action cannot be recovered.</strong>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($_SESSION['email'])): ?>
                    <div class="mb-3 p-2 bg-light rounded-2 border text-muted small d-flex align-items-center gap-2">
                        <i class="fas fa-user-circle text-secondary fs-6"></i>
                        <span>Deleting account: <strong class="text-dark"><?php echo htmlspecialchars($_SESSION['email']); ?></strong></span>
                    </div>
                    <?php endif; ?>

                    <div class="mb-2">
                        <label for="delete_confirm_password" class="form-label small fw-semibold text-dark mb-1">
                            Enter Account Password to Confirm <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control border-start-0 border-end-0" id="delete_confirm_password" name="confirm_password" placeholder="Enter your current password" required autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary border-start-0" onclick="toggleDeletePasswordVisibility()" title="Show/Hide Password" style="border-color:#dee2e6;">
                                <i class="fas fa-eye" id="deletePassToggleIcon"></i>
                            </button>
                        </div>
                        <div class="form-text small text-muted mt-1">
                            <i class="fas fa-shield-halved me-1 text-teal"></i>Password verification guarantees only you can delete your account.
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3 bg-light d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3 rounded-pill fw-medium" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm px-4 rounded-pill fw-semibold shadow-sm" id="btnSubmitDeleteAccount">
                        <i class="fas fa-trash-can me-1"></i> Permanently Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleDeletePasswordVisibility() {
    const input = document.getElementById('delete_confirm_password');
    const icon = document.getElementById('deletePassToggleIcon');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}
</script>
