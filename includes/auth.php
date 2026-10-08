<?php
/**
 * MediCycle - Authentication & Role Access Guards (Supplier <-> NGO Model)
 */

require_once __DIR__ . '/session.php';

/**
 * Require the user to be logged in, otherwise redirect to login page
 */
function require_login($redirectTo = null) {
    if (!is_logged_in()) {
        $target = $redirectTo ?? $_SERVER['REQUEST_URI'] ?? '';
        set_flash('warning', 'Please sign in to access this area.');
        header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode($target));
        exit;
    }
}

/**
 * Require specific role: 'supplier' or 'ngo'
 */
function require_role($allowed_roles) {
    require_login();
    
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        set_flash('danger', 'Access denied. You do not have permission to view that section.');
        
        $redirectUrl = match($_SESSION['role']) {
            'supplier' => BASE_URL . '/supplier/dashboard.php',
            'ngo' => BASE_URL . '/ngo/dashboard.php',
            default => BASE_URL . '/index.php'
        };
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Redirect user if they are already logged in
 */
function redirect_if_logged_in() {
    if (is_logged_in()) {
        $redirectUrl = match($_SESSION['role']) {
            'supplier' => BASE_URL . '/supplier/dashboard.php',
            'ngo' => BASE_URL . '/ngo/dashboard.php',
            default => BASE_URL . '/index.php'
        };
        header('Location: ' . $redirectUrl);
        exit;
    }
}
