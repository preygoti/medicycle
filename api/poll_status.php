<?php
/**
 * MediCycle - Real-Time Status & Event Poller API
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

// Release PHP session lock immediately so concurrent requests/tabs never block
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    // 1. Global Operational State Digest (keeps Supplier, NGO, Courier & Admin 100% synchronized)
    $suppliesDigest = $pdo->query("SELECT id, status, listing_type, quantity, expiry_date FROM medical_supplies ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $requestsDigest = $pdo->query("SELECT id, supply_id, requester_id, requested_quantity, status, handover_code, supplier_remarks, requested_at, approved_at, completed_at FROM requests ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $reqsDigest     = $pdo->query("SELECT id, organization_id, required_quantity, urgency, status FROM requirements ORDER BY id DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);
    $delivDigest    = $pdo->query("SELECT id, request_id, pickup_status, delivery_status, delivered_at FROM deliveries ORDER BY id DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);

    $globalFingerprint = md5(json_encode([
        's' => $suppliesDigest,
        'r' => $requestsDigest,
        'q' => $reqsDigest,
        'd' => $delivDigest
    ]));

    if (!$isLoggedIn) {
        echo json_encode([
            'logged_in' => false,
            'fingerprint' => $globalFingerprint,
            'global_fingerprint' => $globalFingerprint,
            'timestamp' => time()
        ]);
        exit;
    }

    // 2. User-specific Unread Notifications & Recent Alerts
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

    // 3. Pending count for badge display
    $pendingCount = 0;
    foreach ($requestsDigest as $r) {
        if ($r['status'] === 'Pending') {
            if ($role === 'ngo' && (int)$r['requester_id'] === $userId) {
                $pendingCount++;
            } elseif ($role === 'supplier' || $role === 'admin') {
                $pendingCount++;
            }
        }
    }

    // 4. Combined State Fingerprint (triggers instant UI update on any supply/request/handover/notification change)
    $stateHash = md5(json_encode([
        'global' => $globalFingerprint,
        'notif_unread' => $unreadCount,
        'notif_max' => $latestNotifId
    ]));

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
        'logged_in' => $isLoggedIn,
        'error' => $e->getMessage()
    ]);
}

