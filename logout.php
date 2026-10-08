<?php
/**
 * MediCycle - Logout Handler
 */
require_once __DIR__ . '/config/config.php';

// Unset all session values
$_SESSION = [];

// Delete the session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start a fresh session to deliver the logout flash message
session_start();
$_SESSION['flash']['info'] = 'You have been safely signed out. Thank you for using MediCycle.';

header('Location: ' . BASE_URL . '/login.php');
exit;
