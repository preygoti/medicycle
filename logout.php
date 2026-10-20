<?php
/**
 * MediCycle - Logout Handler
 */
require_once __DIR__ . '/config/config.php';

$logoutRole = $_GET['role'] ?? null;
if (!$logoutRole) {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (str_contains($referer, '/supplier/')) $logoutRole = 'supplier';
    elseif (str_contains($referer, '/ngo/')) $logoutRole = 'ngo';
    elseif (str_contains($referer, '/delivery/')) $logoutRole = 'delivery';
}

if ($logoutRole && !empty($_SESSION['accounts'][$logoutRole])) {
    unset($_SESSION['accounts'][$logoutRole]);
    if (($_SESSION['role'] ?? '') === $logoutRole) {
        unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['role'], $_SESSION['org_id'], $_SESSION['org_name'], $_SESSION['verification_status']);
        if (!empty($_SESSION['accounts'])) {
            $other = reset($_SESSION['accounts']);
            $_SESSION['user_id'] = $other['user_id'];
            $_SESSION['role'] = $other['role'];
            $_SESSION['user_name'] = $other['user_name'];
            $_SESSION['user_email'] = $other['user_email'];
            $_SESSION['org_id'] = $other['org_id'] ?? null;
            $_SESSION['org_name'] = $other['org_name'] ?? null;
            $_SESSION['verification_status'] = $other['verification_status'] ?? 'verified';
        }
    }
} else {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    session_start();
}

$_SESSION['flash']['info'] = 'You have been safely signed out. Thank you for using MediCycle.';

header('Location: ' . BASE_URL . '/login.php');
exit;
