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
$inPortal = (bool)preg_match('#/(supplier|ngo|delivery)/#i', $_SERVER['PHP_SELF'] ?? '');
$isPublicPage = !$inPortal;
$isLoginActive = ($currentScript === 'login.php');
$isRegisterActive = ($currentScript === 'register.php');
?>
<nav class="navbar navbar-expand-lg navbar-medicycle sticky-top shadow-sm">
    <div class="container-fluid px-2 px-sm-3 px-lg-4 d-flex align-items-center justify-content-between">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>/index.php">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo-icon.png" width="34" height="34" alt="MediCycle" class="rounded-circle shadow-xs" style="object-fit: cover;">
            <span class="fw-bold tracking-tight text-teal" style="color: #0f766e;">MediCycle</span>
        </a>

        <?php if ($inPortal): ?>
            <!-- Portal Mobile & Desktop Header: Logo on left, Notification Icon + Profile Name on right (Always visible on phone & desktop) -->
            <div class="d-flex align-items-center gap-1 gap-sm-2 gap-md-3">
                <!-- Modern Notification Bell Dropdown -->
                <div class="dropdown position-relative">
                    <button type="button" class="btn notif-icon-btn position-relative" id="notifDropdown" aria-expanded="false" title="Notifications" onclick="togglePortalDropdown(this, event)">
                        <i class="far fa-bell fs-5 text-secondary"></i>
                        <?php if ($unreadNotifCount > 0): ?>
                            <span class="notif-pulse-badge">
                                <?php echo $unreadNotifCount; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-2 notif-dropdown-menu" style="max-height: 400px; overflow-y: auto;" aria-labelledby="notifDropdown">
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

                <!-- User Profile Dropdown Capsule: User Name is visible on both phone and desktop -->
                <?php
                $displayName = $currentUser['name'] ?? ($_SESSION['user_name'] ?? 'User');
                $displayRole = $currentUser['role'] ?? ($_SESSION['role'] ?? '');
                $displayEmail = $currentUser['email'] ?? ($_SESSION['user_email'] ?? '');
                ?>
                <div class="dropdown position-relative">
                    <button type="button" class="btn user-profile-capsule d-flex align-items-center gap-1 gap-sm-2 text-dark shadow-2xs" id="userDropdown" aria-expanded="false" onclick="togglePortalDropdown(this, event)">
                        <div class="user-avatar-circle">
                            <?php echo strtoupper(substr($displayName, 0, 1)); ?>
                        </div>
                        <div class="text-start pe-1 user-profile-info">
                            <span class="d-block fw-bold text-dark leading-tight user-profile-name"><?php echo e($displayName); ?></span>
                            <?php if ($displayRole): ?>
                                <span class="badge text-uppercase text-secondary bg-light border p-1 user-profile-role d-none d-sm-inline-block"><?php echo strtoupper(e($displayRole)); ?></span>
                            <?php endif; ?>
                        </div>
                        <i class="fas fa-chevron-down text-muted small ms-1" style="font-size:0.62rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 user-dropdown-menu" aria-labelledby="userDropdown">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-bold small"><?php echo e($displayName); ?></div>
                            <?php if ($displayEmail): ?>
                                <div class="text-muted small"><?php echo e($displayEmail); ?></div>
                            <?php endif; ?>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo get_role_dashboard(); ?>">
                                <i class="fas fa-gauge-high me-2 text-teal"></i> Dashboard
                            </a>
                        </li>
                        <?php if (($currentUser['role'] ?? ($_SESSION['role'] ?? '')) === 'supplier'): ?>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/supplier/profile.php"><i class="fas fa-id-card me-2 text-muted"></i> Organization Profile</a></li>
                        <?php elseif (($currentUser['role'] ?? ($_SESSION['role'] ?? '')) === 'ngo'): ?>
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

            <!-- Precision Instant Fail-Safe Dropdown Logic (Zero external dependencies or cache delays) -->
            <script>
            window.togglePortalDropdown = function(btn, ev) {
                if (ev) {
                    if (ev.preventDefault) ev.preventDefault();
                    if (ev.stopPropagation) ev.stopPropagation();
                }
                var parent = btn.closest('.dropdown');
                if (!parent) return false;
                var menu = parent.querySelector('.dropdown-menu');
                if (!menu) return false;

                var isOpen = menu.classList.contains('show');

                // Close any other open dropdowns
                var allMenus = document.querySelectorAll('.navbar-medicycle .dropdown-menu.show');
                for (var i = 0; i < allMenus.length; i++) {
                    if (allMenus[i] !== menu) {
                        allMenus[i].classList.remove('show');
                        var p = allMenus[i].closest('.dropdown');
                        var b = p ? p.querySelector('#notifDropdown, #userDropdown, button, a') : null;
                        if (b) b.setAttribute('aria-expanded', 'false');
                    }
                }

                if (isOpen) {
                    menu.classList.remove('show');
                    btn.setAttribute('aria-expanded', 'false');
                } else {
                    menu.classList.add('show');
                    btn.setAttribute('aria-expanded', 'true');
                }
                return false;
            };

            if (!window._portalDropdownDocListenerAdded) {
                window._portalDropdownDocListenerAdded = true;
                var closeAll = function(e) {
                    if (!e.target.closest || !e.target.closest('.navbar-medicycle .dropdown')) {
                        var openMenus = document.querySelectorAll('.navbar-medicycle .dropdown-menu.show');
                        for (var j = 0; j < openMenus.length; j++) {
                            openMenus[j].classList.remove('show');
                            var pr = openMenus[j].closest('.dropdown');
                            var bt = pr ? pr.querySelector('#notifDropdown, #userDropdown, button, a') : null;
                            if (bt) bt.setAttribute('aria-expanded', 'false');
                        }
                    }
                };
                document.addEventListener('click', closeAll);
                document.addEventListener('touchend', closeAll, { passive: true });
            }
            </script>
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

                <!-- Always show Login and Register buttons on public pages (never replace with Dashboard) -->
                <ul class="navbar-nav ms-auto align-items-center gap-2 mobile-auth-actions-nav">
                    <li class="nav-item auth-btn-item">
                        <a class="btn btn-header-login <?php echo $isLoginActive ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/login.php">
                            <i class="fas fa-arrow-right-to-bracket me-1"></i> Login
                        </a>
                    </li>
                    <li class="nav-item auth-btn-item">
                        <a class="btn btn-header-signup <?php echo $isRegisterActive ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/register.php">
                            <i class="fas fa-user-plus me-1"></i> Register
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</nav>
