<?php
/**
 * MediCycle - Real-Time Status & Event Poller API (High-Performance Optimized)
 * Lightweight JSON endpoint queried via JS to detect any state transitions across
 * Supplies, Requests, Handovers, Requirements, Deliveries, and Notifications.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$isLoggedIn = is_logged_in();
$currentUser = $isLoggedIn ? current_user() : null;
$userId = $currentUser ? (int)$currentUser['id'] : 0;
$role = $currentUser['role'] ?? 'guest';

// Release PHP session lock immediately so concurrent requests/tabs never block navigation
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Guest / unauthenticated users never need live status polling
if (!$isLoggedIn) {
    echo json_encode([
        'logged_in' => false,
        'timestamp' => time()
    ]);
    exit;
}

try {
    // 1. Ultrafast single-row aggregate query for operational fingerprint (<5ms execution)
    $stmt = $pdo->query("
        SELECT 
            (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id), 0), COALESCE(MAX(UNIX_TIMESTAMP(updated_at)), 0)) FROM medical_supplies) AS s_fp,
            (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id), 0), COALESCE(MAX(UNIX_TIMESTAMP(updated_at)), 0), COALESCE(MAX(UNIX_TIMESTAMP(completed_at)), 0)) FROM requests) AS r_fp,
            (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id), 0), COALESCE(MAX(UNIX_TIMESTAMP(updated_at)), 0)) FROM requirements) AS q_fp,
            (SELECT CONCAT_WS(':', COUNT(*), COALESCE(MAX(id), 0), COALESCE(MAX(UNIX_TIMESTAMP(updated_at)), 0)) FROM deliveries) AS d_fp
    ");
    $digest = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
    $globalFingerprint = md5(implode('|', $digest ?: []));

    // 2. User-specific Unread Notifications & Recent Alerts (indexed via idx_notif_user_read)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $countStmt->execute([$userId]);
    $unreadCount = (int)$countStmt->fetchColumn();

    $nStmt = $pdo->prepare("SELECT id, title, message, link, is_read, created_at 
                           FROM notifications 
                           WHERE user_id = ? 
                           ORDER BY id DESC LIMIT 5");
    $nStmt->execute([$userId]);
    $recentNotifs = $nStmt->fetchAll(PDO::FETCH_ASSOC);
    $latestNotifId = !empty($recentNotifs) ? (int)$recentNotifs[0]['id'] : 0;

    // 3. Fast indexed pending count
    $pendingCount = 0;
    if ($role === 'ngo') {
        $pStmt = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE requester_id = ? AND status = 'Pending'");
        $pStmt->execute([$userId]);
        $pendingCount = (int)$pStmt->fetchColumn();
    } elseif ($role === 'supplier' || $role === 'admin') {
        $pStmt = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'Pending'");
        $pendingCount = (int)($pStmt ? $pStmt->fetchColumn() : 0);
    }

    // 4. Combined State Fingerprint
    $stateHash = md5($globalFingerprint . ':' . $unreadCount . ':' . $latestNotifId);

    echo json_encode([
        'logged_in' => true,
        'user_id' => $userId,
        'role' => $role,
        'fingerprint' => $stateHash,
        'global_fingerprint' => $globalFingerprint,
        'unread_count' => $unreadCount,
        'latest_notif_id' => $latestNotifId,
        'recent_notifications' => $recentNotifs,
        'pending_count' => $pendingCount,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    error_log("poll_status error: " . $e->getMessage());
    echo json_encode([
        'logged_in' => true,
        'error' => 'poll_error'
    ]);
}
