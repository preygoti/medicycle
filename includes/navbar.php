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

$isPublicPage = !in_array($currentDir, ['supplier', 'ngo', 'delivery']);
$isLoginActive = ($currentScript === 'login.php');
$isRegisterActive = ($currentScript === 'register.php');
?>
<nav class="navbar <?php echo $isPublicPage ? 'navbar-expand-lg' : ''; ?> navbar-medicycle sticky-top shadow-sm">
    <div class="container-fluid px-3 px-lg-4 d-flex align-items-center justify-content-between">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>/index.php">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo-icon.png" width="34" height="34" alt="MediCycle" class="rounded-circle shadow-xs" style="object-fit: cover;">
            <span class="fw-bold tracking-tight text-teal" style="color: #0f766e;">MediCycle</span>
        </a>

        <?php if (!$isPublicPage && $currentUser): ?>
            <!-- Portal Mobile & Desktop Header Controls (Always visible, clean, no awkward collapse) -->
            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <!-- Modern Notification Bell Dropdown -->
                <div class="dropdown position-relative">
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
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="fw-semibold text-dark"><?php echo e($notif['title']); ?></div>
                                        <span class="badge rounded-pill text-teal border border-teal-subtle bg-teal-subtle" style="font-size:0.62rem; padding: 2px 7px;">
                                            <?php echo time_ago($notif['created_at']); ?>
                                        </span>
                                    </div>
                                    <div class="text-muted small mt-1" style="font-size:0.78rem; line-height:1.35;"><?php echo e($notif['message']); ?></div>
                                    <div class="d-flex justify-content-between align-items-center text-secondary small mt-1 pt-1" style="font-size: 0.70rem;">
                                        <span title="<?php echo format_datetime($notif['created_at']); ?>">
                                            <i class="far fa-clock me-1 text-teal"></i><?php echo format_datetime($notif['created_at'], 'h:i A'); ?>
                                        </span>
                                        <?php if (!empty($notif['link'])): ?>
                                            <a href="<?php echo BASE_URL . '/' . ltrim($notif['link'], '/'); ?>" class="text-teal text-decoration-none fw-bold" style="font-size:0.72rem;">
                                                Open <i class="fas fa-arrow-right ms-1"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="text-center py-3 text-muted small">No new notifications</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- User Profile Dropdown Capsule -->
                <div class="dropdown">
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
                </div>
            </div>
        <?php else: ?>
            <!-- Public Header: Modern Animated 3-Lines Hamburger Button -->
            <button class="navbar-toggler modern-hamburger-btn d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="hamburger-bar top-bar"></span>
                <span class="hamburger-bar mid-bar"></span>
                <span class="hamburger-bar bot-bar"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1 gap-lg-2" id="mainNavLinks">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentScript === 'index.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php#home" data-bookmark="home">
                            <i class="fas fa-house-chimney d-lg-none"></i>
                            <span>Home</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php#about" data-bookmark="about">
                            <i class="fas fa-circle-info d-lg-none"></i>
                            <span>About</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php#how-it-works" data-bookmark="how-it-works">
                            <i class="fas fa-diagram-project d-lg-none"></i>
                            <span>How It Works</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php#safety" data-bookmark="safety">
                            <i class="fas fa-shield-halved d-lg-none"></i>
                            <span>Safety & Quality</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php#contact" data-bookmark="contact">
                            <i class="fas fa-envelope d-lg-none"></i>
                            <span>Contact</span>
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto align-items-center gap-2 mobile-auth-actions-nav">
                    <?php if ($currentUser): ?>
                        <li class="nav-item w-100">
                            <a class="btn btn-teal-solid w-100 py-2 d-flex align-items-center justify-content-center gap-2" href="<?php echo get_role_dashboard(); ?>">
                                <i class="fas fa-gauge-high"></i> Dashboard
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item auth-btn-item">
                            <a class="btn btn-header-login <?php echo $isLoginActive ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/login.php">
                                <i class="fas fa-arrow-right-to-bracket me-1"></i> Login
                            </a>
                        </li>
                        <li class="nav-item auth-btn-item">
                            <a class="btn btn-header-signup <?php echo $isRegisterActive ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/register.php">
                                <i class="fas fa-user-plus me-1"></i> Sign Up
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</nav>
