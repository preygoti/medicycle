<?php
/**
 * MediCycle - Global Navigation Bar
 */
$currentUser = current_user();
$unreadNotifCount = 0;
$recentNotifs = [];

if ($currentUser && isset($pdo)) {
    $unreadNotifCount = get_unread_notifications_count($pdo, $currentUser['id']);
    $recentNotifs = get_user_notifications($pdo, $currentUser['id'], 4);
}
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$currentDir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));

$isHomeActive = ($currentScript === 'index.php' && $currentDir !== 'supplier' && $currentDir !== 'ngo');
$isAboutActive = ($currentScript === 'about.php');
$isHowItWorksActive = ($currentScript === 'how-it-works.php');
$isContactActive = ($currentScript === 'contact.php');
$isLoginActive = ($currentScript === 'login.php');
$isRegisterActive = ($currentScript === 'register.php');
$isDashActive = ($currentDir === 'supplier' || $currentDir === 'ngo');
?>
<nav class="navbar navbar-expand-lg navbar-medicycle sticky-top">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>/index.php">
            <i class="fas fa-hand-holding-medical"></i> MediCycle
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo $isHomeActive ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $isAboutActive ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $isHowItWorksActive ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/how-it-works.php">How It Works</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $isContactActive ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/contact.php">Contact</a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto align-items-center">
                <?php if ($currentUser): ?>
                    <!-- Notification Bell Dropdown -->
                    <li class="nav-item dropdown me-3 position-relative">
                        <a class="nav-link position-relative p-2" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                            <i class="fas fa-bell text-secondary fs-5"></i>
                            <?php if ($unreadNotifCount > 0): ?>
                                <span class="badge bg-danger rounded-pill notif-badge">
                                    <?php echo $unreadNotifCount; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="width: 320px; max-height: 400px; overflow-y: auto;" aria-labelledby="notifDropdown">
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

                    <!-- Role Dashboard Quick Link -->
                    <li class="nav-item me-2">
                        <?php
                            $dashUrl = match($currentUser['role']) {
                                'supplier' => BASE_URL . '/supplier/dashboard.php',
                                'ngo' => BASE_URL . '/ngo/dashboard.php',
                                default => BASE_URL . '/index.php'
                            };
                        ?>
                        <a href="<?php echo $dashUrl; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-chart-pie me-1"></i> My Dashboard
                        </a>
                    </li>

                    <!-- User Profile Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 py-1" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="rounded-circle bg-light border text-primary d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; font-weight: 700;">
                                <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                            </div>
                            <div class="d-none d-md-block text-start">
                                <span class="d-block fw-semibold small text-dark leading-tight"><?php echo e($currentUser['name']); ?></span>
                                <span class="badge bg-secondary" style="font-size: 0.65rem;"><?php echo strtoupper(e($currentUser['role'])); ?></span>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userDropdown">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold small"><?php echo e($currentUser['name']); ?></div>
                                <div class="text-muted small"><?php echo e($currentUser['email']); ?></div>
                            </li>
                            <?php if ($currentUser['role'] === 'supplier'): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/supplier/profile.php"><i class="fas fa-id-card me-2 text-muted"></i> Organization Profile</a></li>
                            <?php elseif ($currentUser['role'] === 'ngo'): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/ngo/profile.php"><i class="fas fa-id-card me-2 text-muted"></i> NGO / Clinic Profile</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Sign Out
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Guest Navigation Links -->
                    <li class="nav-item me-2">
                        <a class="nav-link <?php echo $isLoginActive ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn <?php echo $isRegisterActive ? 'btn-primary shadow-sm active' : 'btn-primary'; ?>" href="<?php echo BASE_URL; ?>/register.php">
                            <i class="fas fa-user-plus me-1"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
