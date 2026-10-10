<?php
/**
 * MediCycle - Secure Self-Account Deletion
 * Permanently removes user account after verifying current password.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . get_role_dashboard());
    exit;
}

if (!verify_csrf_token()) {
    set_flash('danger', 'Security validation token expired. Please try again.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? get_role_dashboard()));
    exit;
}

$userId = (int)$_SESSION['user_id'];
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

if (empty($confirmPassword)) {
    set_flash('danger', 'Password confirmation is required to delete your account.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? get_role_dashboard()));
    exit;
}

// Fetch user record
$stmt = $pdo->prepare("SELECT id, name, email, role, password FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !password_verify($confirmPassword, $user['password'])) {
    set_flash('danger', 'Incorrect password! Account deletion was cancelled. Your account remains active and secure.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? get_role_dashboard()));
    exit;
}

// Safety check: Prevent deleting the primary admin account if it's the last admin
if ($user['role'] === 'admin') {
    $adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
    if ($adminCount <= 1) {
        set_flash('danger', 'Cannot delete account: System requires at least one active Administrator.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? get_role_dashboard()));
        exit;
    }
}

try {
    $pdo->beginTransaction();

    // Cascading deletion:
    // Due to ON DELETE CASCADE foreign keys in the schema:
    // organizations, requirements, medical_supplies, requests, notifications, deliveries will be safely cleaned up.
    $delUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $delUser->execute([$userId]);

    $pdo->commit();

    // Log the user out completely and destroy session
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

    set_flash('success', 'Your MediCycle account has been permanently deleted. We are sorry to see you go!');
    header('Location: ' . BASE_URL . '/index.php');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete account error: " . $e->getMessage());
    set_flash('danger', 'Database error while deleting account: ' . $e->getMessage());
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? get_role_dashboard()));
    exit;
}
