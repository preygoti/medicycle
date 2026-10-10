<?php
/**
 * MediCycle - Real-Time Status & Event Poller API
 * Lightweight JSON endpoint queried via JS to detect status transitions (Pending -> Approved, etc.)
 * Returns unread notifications, pending count, and the latest request state fingerprint.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!is_logged_in()) {
    echo json_encode(['logged_in' => false]);
    exit;
}

$currentUser = current_user();
$userId = (int)$currentUser['id'];
$role = $currentUser['role'];

try {
    // 1. Unread notifications & recent notifications
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

    // 2. Gather role-specific operational state data
    $requests = [];
    $extraData = [];
    $pendingCount = 0;

    if ($role === 'ngo') {
        // Track requests made by this NGO
        $rStmt = $pdo->prepare("SELECT id, status, handover_code, approved_at, completed_at, updated_at 
                               FROM requests 
                               WHERE requester_id = ? 
                               ORDER BY id DESC LIMIT 30");
        $rStmt->execute([$userId]);
        $requests = $rStmt->fetchAll(PDO::FETCH_ASSOC);

        // Track requirement changes
        $reqStmt = $pdo->prepare("SELECT COUNT(*), MAX(id) FROM requirements WHERE organization_id = (SELECT id FROM organizations WHERE user_id = ? LIMIT 1)");
        $reqStmt->execute([$userId]);
        $extraData['requirements'] = $reqStmt->fetch(PDO::FETCH_NUM);

        // Count pending
        foreach ($requests as $r) {
            if ($r['status'] === 'Pending') $pendingCount++;
        }

    } elseif ($role === 'supplier') {
        // Track requests on this supplier's medical supplies
        $rStmt = $pdo->prepare("SELECT r.id, r.status, r.approved_at, r.completed_at, r.updated_at 
                               FROM requests r 
                               JOIN medical_supplies ms ON r.supply_id = ms.id 
                               WHERE ms.supplier_id = ? 
                               ORDER BY r.id DESC LIMIT 30");
        $rStmt->execute([$userId]);
        $requests = $rStmt->fetchAll(PDO::FETCH_ASSOC);

        // Track medical supplies inventory changes
        $sStmt = $pdo->prepare("SELECT COUNT(*), MAX(updated_at), SUM(quantity) FROM medical_supplies WHERE supplier_id = ?");
        $sStmt->execute([$userId]);
        $extraData['supplies'] = $sStmt->fetch(PDO::FETCH_NUM);

        // Count pending
        foreach ($requests as $r) {
            if ($r['status'] === 'Pending') $pendingCount++;
        }

    } elseif ($role === 'admin') {
        $rStmt = $pdo->query("SELECT id, status, updated_at FROM requests ORDER BY id DESC LIMIT 30");
        $requests = $rStmt->fetchAll(PDO::FETCH_ASSOC);
        $extraData['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetch(PDO::FETCH_NUM);

    } else { // delivery partner
        $rStmt = $pdo->prepare("SELECT r.id, r.status, d.delivery_status, d.pickup_status, d.updated_at 
                               FROM requests r 
                               JOIN deliveries d ON r.id = d.request_id 
                               WHERE d.delivery_partner_id = ? 
                               ORDER BY r.id DESC LIMIT 30");
        $rStmt->execute([$userId]);
        $requests = $rStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Compute unique state fingerprint
    $fingerprintData = [
        'req' => $requests,
        'extra' => $extraData,
        'notif_unread' => $unreadCount,
        'notif_max' => $latestNotifId
    ];
    $stateHash = md5(json_encode($fingerprintData));

    echo json_encode([
        'logged_in' => true,
        'user_id' => $userId,
        'role' => $role,
        'fingerprint' => $stateHash,
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
        'error' => $e->getMessage()
    ]);
}
