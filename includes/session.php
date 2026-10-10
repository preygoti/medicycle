<?php
/**
 * MediCycle - Secure Session Management
 */

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    session_start();
}

// Inactivity timeout handling (2 hours)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash']['warning'] = 'Your session has expired due to inactivity. Please log in again.';
}
$_SESSION['last_activity'] = time();

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Get or regenerate CSRF token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden CSRF input field
 */
function csrf_field() {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify CSRF token from request
 */
function verify_csrf_token($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if a user is currently logged in
 */
function is_logged_in() {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

/**
 * Get the current logged-in user details
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    global $pdo;
    if (isset($pdo) && !empty($_SESSION['user_id'])) {
        try {
            $chk = $pdo->prepare("SELECT u.id, u.name, u.email, u.role, u.status,
                                         o.id AS org_id, o.organization_name, o.verification_status
                                  FROM users u
                                  LEFT JOIN organizations o ON u.id = o.user_id
                                  WHERE u.id = ? LIMIT 1");
            $chk->execute([$_SESSION['user_id']]);
            $u = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$u || $u['status'] === 'suspended' || $u['status'] === 'inactive') {
                unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['role'], $_SESSION['org_id'], $_SESSION['org_name'], $_SESSION['verification_status']);
                return null;
            }
            // Dynamically synchronize live profile name entered by user
            $_SESSION['user_name'] = $u['name'];
            $_SESSION['user_email'] = $u['email'];
            $_SESSION['role'] = $u['role'];
            $_SESSION['org_id'] = $u['org_id'];
            $_SESSION['org_name'] = $u['organization_name'] ?: $u['name'];
            $_SESSION['verification_status'] = $u['verification_status'] ?? 'verified';
        } catch (Exception $e) {}
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['role'],
        'org_id' => $_SESSION['org_id'] ?? null,
        'org_name' => $_SESSION['org_name'] ?? null,
        'verification_status' => $_SESSION['verification_status'] ?? 'verified',
    ];
}

/**
 * Check if the user has a specific role
 */
function has_role($role) {
    if (!is_logged_in()) {
        return false;
    }
    if (is_array($role)) {
        return in_array($_SESSION['role'], $role);
    }
    return $_SESSION['role'] === $role;
}

/**
 * Set a flash message
 */
function set_flash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

/**
 * Check if a flash message exists
 */
function has_flash($type = null) {
    if ($type === null) {
        return !empty($_SESSION['flash']);
    }
    return !empty($_SESSION['flash'][$type]);
}

/**
 * Get and clear a flash message
 */
function get_flash($type) {
    if (!empty($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

/**
 * Render all pending flash alerts in Bootstrap format
 */
function render_flash_messages() {
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    $types = [
        'success' => 'success',
        'error'   => 'danger',
        'danger'  => 'danger',
        'warning' => 'warning',
        'info'    => 'info'
    ];
    
    foreach ($_SESSION['flash'] as $type => $message) {
        $bootstrapClass = $types[$type] ?? 'info';
        $icon = match($bootstrapClass) {
            'success' => 'fa-check-circle',
            'danger' => 'fa-exclamation-triangle',
            'warning' => 'fa-exclamation-circle',
            default => 'fa-info-circle'
        };
        $html .= '<div class="alert alert-' . $bootstrapClass . ' alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas ' . $icon . ' me-2"></i> ' . htmlspecialchars($message) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    unset($_SESSION['flash']);
    return $html;
}
