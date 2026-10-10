<?php
/**
 * MediCycle - Authentication & Role Access Guards
 * Full Role Management for: Admin, Supplier, NGO/Clinic, Delivery Partner
 */

require_once __DIR__ . '/session.php';

/**
 * Get the proper dashboard URL for a given role
 */
function get_role_dashboard(?string $role = null): string {
    $role = $role ?? ($_SESSION['role'] ?? null);
    return match($role) {
        'admin'    => BASE_URL . '/admin/dashboard.php',
        'supplier' => BASE_URL . '/supplier/dashboard.php',
        'ngo'      => BASE_URL . '/ngo/dashboard.php',
        'delivery' => BASE_URL . '/delivery/dashboard.php',
        default    => BASE_URL . '/index.php'
    };
}

/**
 * Require the user to be logged in, otherwise redirect to login page
 */
function require_login(?string $redirectTo = null): void {
    if (!is_logged_in() || current_user() === null) {
        $target = $redirectTo ?? ($_SERVER['REQUEST_URI'] ?? '');
        set_flash('warning', 'Please sign in to access this area.');
        header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode($target));
        exit;
    }
}

/**
 * Require specific role or array of allowed roles
 */
function require_role(string|array $allowed_roles): void {
    require_login();
    
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    if (!in_array($_SESSION['role'], $allowed_roles, true)) {
        set_flash('danger', 'Access denied. You do not have permission to access that section.');
        header('Location: ' . get_role_dashboard());
        exit;
    }
}

/**
 * Role specific convenience guards
 */
function require_admin(): void {
    require_role('admin');
}

function require_supplier(): void {
    require_role('supplier');
}

function require_ngo(): void {
    require_role('ngo');
}

function require_delivery(): void {
    require_role('delivery');
}

/**
 * Redirect user if they are already logged in, unless explicitly switching accounts or navigating to auth
 */
function redirect_if_logged_in(): void {
    if (is_logged_in()) {
        // If user wants to switch account, clear previous session credentials
        if (isset($_GET['switch']) || isset($_GET['logout'])) {
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['role'], $_SESSION['org_id'], $_SESSION['org_name'], $_SESSION['verification_status']);
            return;
        }
        // Don't forcefully lock the user out if they intentionally visited login or register
    }
}
