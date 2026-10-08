<?php
/**
 * MediCycle - Dynamic Role-based Sidebar
 */
$currentUser = current_user();
$currentScript = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function is_active_link($script, $dir = null) {
    global $currentScript, $currentDir;
    if ($dir !== null && $currentDir !== $dir) {
        return '';
    }
    return ($currentScript === $script) ? 'active' : '';
}
?>
<aside class="app-sidebar shadow-sm">
    <div class="sidebar-heading">
        <?php echo strtoupper(e($currentUser['role'])); ?> PORTAL
    </div>
    <ul class="nav flex-column mb-auto">
        <?php if ($currentUser['role'] === 'supplier'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('dashboard.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/dashboard.php">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('add-supply.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/add-supply.php">
                    <i class="fas fa-plus-circle"></i> Add Surplus Supply
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('inventory.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/inventory.php">
                    <i class="fas fa-boxes-stacked"></i> My Inventory
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('requests.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/requests.php">
                    <i class="fas fa-inbox"></i> Incoming Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('history.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/history.php">
                    <i class="fas fa-history"></i> Handover History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('analytics.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/analytics.php">
                    <i class="fas fa-chart-pie"></i> Impact & Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('profile.php', 'supplier'); ?>" href="<?php echo BASE_URL; ?>/supplier/profile.php">
                    <i class="fas fa-hospital"></i> Organization Profile
                </a>
            </li>

        <?php elseif ($currentUser['role'] === 'ngo'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('dashboard.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/dashboard.php">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('search-supplies.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/search-supplies.php">
                    <i class="fas fa-search"></i> Discover Supplies
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('post-requirement.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/post-requirement.php">
                    <i class="fas fa-bullhorn"></i> Post Supply Need
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('my-requirements.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/my-requirements.php">
                    <i class="fas fa-clipboard-list"></i> My Needs / Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('my-requests.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/my-requests.php">
                    <i class="fas fa-hands-helping"></i> My Collection Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('history.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/history.php">
                    <i class="fas fa-history"></i> Receipt History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('impact.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/impact.php">
                    <i class="fas fa-seedling"></i> Impact Metrics
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo is_active_link('profile.php', 'ngo'); ?>" href="<?php echo BASE_URL; ?>/ngo/profile.php">
                    <i class="fas fa-clinic-medical"></i> Clinic/NGO Profile
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="p-3 border-top border-secondary mt-auto">
        <div class="small text-muted mb-2">Signed in as:</div>
        <div class="fw-semibold text-truncate text-white small"><?php echo e($currentUser['name']); ?></div>
        <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-outline-danger btn-sm w-100 mt-2">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </a>
    </div>
</aside>
