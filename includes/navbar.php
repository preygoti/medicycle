<?php
/**
 * MediCycle - Global Navigation Bar
 * Responsive, role-aware navigation
 */
require_once __DIR__ . '/auth.php';

$currentUser = current_user();
$unreadNotifCount = 0;
$recentNotifs = [];

if ($currentUser && isset($pdo)) {
    $unreadNotifCount = get_unread_notifications_count($pdo, $currentUser['id']);
    $recentNotifs = get_user_notifications($pdo, $currentUser['id'], 5);
}
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$currentDir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));

$isPublicPage = !in_array($currentDir, ['admin', 'supplier', 'ngo', 'delivery']);
$isLoginActive = ($currentScript === 'login.php');
$isRegisterActive = ($currentScript === 'register.php');
?>
<nav class="navbar navbar-expand-lg navbar-medicycle sticky-top shadow-sm">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>/index.php">
            <span class="brand-icon rounded-circle p-1 bg-teal-light text-teal d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background: #ccfbf1; color: #0f766e;">
                <i class="fas fa-hand-holding-medical fs-5"></i>
            </span>
            <span class="fw-bold tracking-tight text-teal" style="color: #0f766e;">MediCycle</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1 gap-lg-2" id="mainNavLinks">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentScript === 'index.php' && $isPublicPage) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php#home" data-bookmark="home">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php#about" data-bookmark="about">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php#how-it-works" data-bookmark="how-it-works">How It Works</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php#safety" data-bookmark="safety">Safety & Quality</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php#contact" data-bookmark="contact">Contact</a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <?php if (!$isPublicPage && $currentUser): ?>
                    <!-- Internal Portal Header (Only shown after Login/Signup inside app) -->
                    <!-- Modern Notification Bell Dropdown -->
                    <li class="nav-item dropdown position-relative ms-1">
                        <a class="nav-link notif-icon-btn position-relative" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                            <i class="far fa-bell fs-5 text-secondary"></i>
                            <?php if ($unreadNotifCount > 0): ?>
                                <span class="notif-pulse-badge">
                                    <?php echo $unreadNotifCount; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2" style="width: 320px; max-height: 400px; overflow-y: auto;" aria-labelledby="notifDropdown">
                            <li class="d-flex justify-content-between align-items-center px-2 py-1 mb-2 border-bottom">
                                <span class="fw-bold small text-uppercase text-muted">Notifications</span>
                                <span class="badge bg-light text-dark border"><?php echo $unreadNotifCount; ?> New</span>
                            </li>
                            <?php if (!empty($recentNotifs)): ?>
                                <?php foreach ($recentNotifs as $notif): ?>
                                    <li class="p-2 border-bottom small <?php echo $notif['is_read'] ? '' : 'bg-light rounded'; ?>">
                                        <div class="fw-semibold text-dark"><?php echo e($notif['title']); ?></div>
                                        <div class="text-muted small mt-1"><?php echo e($notif['message']); ?></div>
                                        <div class="text-secondary small mt-1" style="font-size: 0.72rem;">
                                            <i class="far fa-clock me-1"></i><?php echo format_datetime($notif['created_at']); ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="text-center py-3 text-muted small">No new notifications</li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <!-- User Profile Dropdown Capsule -->
                    <li class="nav-item dropdown ms-1">
                        <a class="nav-link user-profile-capsule d-flex align-items-center gap-2 text-dark shadow-2xs" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar-circle">
                                <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                            </div>
                            <div class="d-none d-md-block text-start pe-1">
                                <span class="d-block fw-bold small text-dark leading-tight" style="font-size:0.82rem;"><?php echo e($currentUser['name']); ?></span>
                                <span class="badge text-uppercase text-secondary bg-light border p-1" style="font-size: 0.62rem; letter-spacing:0.04em;"><?php echo strtoupper(e($currentUser['role'])); ?></span>
                            </div>
                            <i class="fas fa-chevron-down text-muted small ms-1" style="font-size:0.65rem;"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userDropdown">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold small"><?php echo e($currentUser['name']); ?></div>
                                <div class="text-muted small"><?php echo e($currentUser['email']); ?></div>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo get_role_dashboard(); ?>">
                                    <i class="fas fa-gauge-high me-2 text-teal"></i> Dashboard
                                </a>
                            </li>
                            <?php if ($currentUser['role'] === 'supplier'): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/supplier/profile.php"><i class="fas fa-id-card me-2 text-muted"></i> Organization Profile</a></li>
                            <?php elseif ($currentUser['role'] === 'ngo'): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/ngo/profile.php"><i class="fas fa-id-card me-2 text-muted"></i> Clinic / NGO Profile</a></li>
                            <?php elseif ($currentUser['role'] === 'admin'): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings.php"><i class="fas fa-sliders me-2 text-muted"></i> System Settings</a></li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>/login.php?switch=1">
                                    <i class="fas fa-users-cog me-2 text-primary"></i> Switch Account
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Sign Out
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Public Website Header (Home, About, How It Works, Contact) -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $isLoginActive ? 'active fw-bold' : ''; ?> fw-semibold text-dark px-2" href="<?php echo BASE_URL; ?>/login.php">
                            <i class="fas fa-sign-in-alt text-teal me-1"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-teal text-white shadow-sm px-3" href="<?php echo BASE_URL; ?>/register.php">
                            <i class="fas fa-user-plus me-1"></i> Sign Up
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
