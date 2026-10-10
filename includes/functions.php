<?php
/**
 * MediCycle - Helper Functions & Smart Business Logic
 * Direct Healthcare Supplier <-> Recipient NGO/Clinic Redistribution Model
 */

/**
 * Escape output for safe HTML rendering (prevents XSS)
 */
function e($string) {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize text input
 */
function clean($data) {
    return trim(strip_tags((string)($data ?? '')));
}

/**
 * Format date nicely
 */
function format_date($dateStr, $format = 'd M Y') {
    if (empty($dateStr) || $dateStr === '0000-00-00') {
        return 'N/A';
    }
    $timestamp = strtotime($dateStr);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

/**
 * Format datetime nicely
 */
function format_datetime($datetimeStr, $format = 'd M Y, h:i A') {
    if (empty($datetimeStr) || $datetimeStr === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $timestamp = strtotime($datetimeStr);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

/**
 * Format packaging unit safely, ensuring raw numbers are converted to clean Units
 */
function format_unit($unit) {
    $unit = trim((string)($unit ?? ''));
    if ($unit === '' || is_numeric($unit)) {
        return 'Units';
    }
    return $unit;
}

/**
 * Render Bootstrap badge for status (Handover & Collection lifecycle)
 */
function status_badge($status) {
    $status = trim($status);
    $lower = strtolower($status);
    
    $class = match($lower) {
        'available', 'completed', 'received', 'verified' => 'bg-success text-white',
        'accepted', 'approved' => 'bg-info text-dark',
        'ready for handover' => 'bg-primary text-white',
        'collected' => 'bg-secondary text-white',
        'pending', 'reserved' => 'bg-warning text-dark',
        'rejected', 'cancelled', 'inactive', 'suspended' => 'bg-danger text-white',
        default => 'bg-secondary text-white'
    };
    
    $icon = match($lower) {
        'available' => 'fa-check-circle',
        'accepted', 'approved' => 'fa-handshake',
        'ready for handover' => 'fa-box-open',
        'collected' => 'fa-people-carry-box',
        'completed', 'received' => 'fa-check-double',
        'pending' => 'fa-clock',
        'rejected', 'cancelled' => 'fa-times-circle',
        default => 'fa-circle'
    };
    
    $label = match($lower) {
        'approved' => 'Awaiting Handshake',
        default => ucfirst($status)
    };
    
    return '<span class="badge ' . $class . ' px-2 py-1"><i class="fas ' . $icon . ' me-1 small"></i>' . e($label) . '</span>';
}

/**
 * Render Bootstrap badge for priority
 */
function priority_badge($priority) {
    $priority = trim($priority);
    $class = match(strtolower($priority)) {
        'critical' => 'bg-danger text-white',
        'high'     => 'bg-danger bg-opacity-75 text-white',
        'medium'   => 'bg-warning text-dark',
        'low'      => 'bg-info text-dark',
        default    => 'bg-secondary text-white'
    };
    return '<span class="badge ' . $class . ' px-2 py-1">' . e(ucfirst($priority)) . ' Priority</span>';
}

/**
 * SMART MODULE: Calculate Supply Priority / Waste Risk Score (0 - 100)
 * Evaluates:
 * 1. Expiry proximity (shorter shelf life => higher priority to redistribute before waste)
 * 2. Packaging / condition integrity
 * 3. Quantity available
 */
function calculate_supply_priority_score($expiry_date, $condition_status, $quantity) {
    $score = 40; // baseline
    
    // Expiry proximity calculation
    $today = new DateTime();
    $expiry = new DateTime($expiry_date);
    $diffDays = (int)$today->diff($expiry)->format('%r%a');
    
    if ($diffDays < 0) {
        return ['score' => 0, 'level' => 'Expired'];
    } elseif ($diffDays <= 90) { // < 3 months
        $score += 45;
    } elseif ($diffDays <= 180) { // < 6 months
        $score += 35;
    } elseif ($diffDays <= 365) { // < 1 year
        $score += 20;
    } else {
        $score += 10;
    }
    
    // Condition factor
    if ($condition_status === 'Sterile Sealed') {
        $score += 10;
    } elseif ($condition_status === 'New / Unopened') {
        $score += 15;
    }
    
    // Volume demand factor
    if ($quantity > 300) {
        $score += 5;
    }
    
    $score = min(100, max(1, $score));
    
    $level = 'Low';
    if ($score >= 70) {
        $level = 'High';
    } elseif ($score >= 50) {
        $level = 'Medium';
    }
    
    return [
        'score' => $score,
        'level' => $level
    ];
}

/**
 * SMART MODULE: Supply-Requirement Matchmaking Algorithm
 * Computes recommendation score (0-100%) and plain-language explanation.
 */
function calculate_match_score($supply, $requirement) {
    $score = 0;
    $reasons = [];
    
    // 1. Category Matching (Weight: 35)
    if ($supply['category_id'] == $requirement['category_id']) {
        $score += 35;
        $reasons[] = "Direct category match (" . e($supply['category_name'] ?? 'Consumable') . ")";
    }
    
    // 2. Keyword / Name Similarity (Weight: 25)
    $supplyWords = array_filter(explode(' ', strtolower($supply['supply_name'])));
    $reqWords = array_filter(explode(' ', strtolower($requirement['supply_name'])));
    $intersect = array_intersect($supplyWords, $reqWords);
    if (!empty($intersect)) {
        $score += 25;
        $reasons[] = "Supply description closely matches required items ('" . e(implode(', ', array_slice($intersect, 0, 3))) . "')";
    }
    
    // 3. Geographic Proximity / City Match (Weight: 20)
    $supplyCity = strtolower(trim($supply['location'] ?? ''));
    $reqCity = strtolower(trim($requirement['city'] ?? ''));
    if (!empty($supplyCity) && !empty($reqCity) && $supplyCity === $reqCity) {
        $score += 20;
        $reasons[] = "Nearby location in " . ucfirst($supplyCity) . " for fast handover";
    } else {
        $score += 8;
    }
    
    // 4. Quantity Fulfillment Capacity (Weight: 10)
    if ($supply['quantity'] >= $requirement['required_quantity']) {
        $score += 10;
        $reasons[] = "Full required volume available (" . $supply['quantity'] . " available vs " . $requirement['required_quantity'] . " requested)";
    } elseif ($supply['quantity'] > 0) {
        $score += 5;
        $reasons[] = "Partial fulfillment available (" . $supply['quantity'] . " in stock)";
    }
    
    // 5. Urgency & Freshness (Weight: 10)
    if (isset($requirement['urgency']) && in_array($requirement['urgency'], ['High', 'Critical'])) {
        $score += 10;
        $reasons[] = "High clinical urgency prioritizes immediate allocation";
    }
    
    $score = min(100, max(0, $score));
    
    return [
        'score' => $score,
        'is_recommended' => $score >= 60,
        'summary' => implode('; ', $reasons),
        'reasons' => $reasons
    ];
}

/**
 * Generate a unique 6-digit numeric handover verification code
 */
function generate_handover_code() {
    return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Get dynamic QR code image URL for handshaking
 */
function get_handshake_qr_url($code, $requestId = null) {
    $payload = "MEDICYCLE-HANDSHAKE:" . ($requestId ? "REQ-" . $requestId . ":" : "") . "CODE-" . $code;
    return "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($payload) . "&margin=1&format=svg";
}

/**
 * Render visual Handshake QR & 6-digit pass card HTML
 */
function render_handshake_pass_html($code, $requestId = null, $title = 'Handover Collection Pass') {
    $qrUrl = get_handshake_qr_url($code, $requestId);
    $digits = str_split(str_pad(preg_replace('/[^0-9]/', '', (string)$code), 6, '0'));
    
    $html = '<div class="handshake-pass-card mb-4">';
    $html .= '  <div class="handshake-pass-header d-flex flex-wrap justify-content-between align-items-center gap-2">';
    $html .= '    <div>';
    $html .= '      <div class="d-flex align-items-center gap-2 mb-1">';
    $html .= '        <span class="badge bg-white text-teal fw-bold px-2 py-1" style="color:#0f766e;"><i class="fas fa-shield-alt me-1"></i> SECURE COLLECTION PASS</span>';
    $html .= '        <span class="small text-white opacity-75"><i class="fas fa-check-circle me-1"></i>VERIFIED CLAIM</span>';
    $html .= '      </div>';
    $html .= '      <h5 class="fw-bold mb-0 text-white">' . e($title) . '</h5>';
    $html .= '    </div>';
    $html .= '    <div class="text-end">';
    $html .= '      <span class="badge px-2 py-1" style="background:rgba(255,255,255,0.18); color:#ffffff; border:1px solid rgba(255,255,255,0.3);"><i class="fas fa-lock me-1"></i> Confidential Handshake</span>';
    $html .= '    </div>';
    $html .= '  </div>';

    $html .= '  <div class="p-3 p-md-4 bg-white">';
    $html .= '    <div class="row g-3 g-md-4 align-items-center">';
    $html .= '      <div class="col-12 col-sm-4 text-center border-bottom border-sm-bottom-0 border-sm-end pb-3 pb-sm-0">';
    $html .= '        <div class="p-2 rounded-3 bg-light border d-inline-block shadow-2xs">';
    $html .= '          <img src="' . e($qrUrl) . '" alt="Handshake QR" class="img-fluid rounded" style="width:115px; height:115px; max-width:100%;">';
    $html .= '        </div>';
    $html .= '        <div class="text-muted text-uppercase fw-bold mt-2" style="font-size:0.68rem; letter-spacing:0.06em;"><i class="fas fa-qrcode me-1 text-teal"></i>Scan at Handover</div>';
    $html .= '      </div>';
    
    $html .= '      <div class="col-12 col-sm-8 text-center text-sm-start">';
    $html .= '        <div class="small text-muted text-uppercase fw-bold mb-1" style="letter-spacing:0.05em;">Your 6-Digit Handshake PIN</div>';
    $html .= '        <div class="handshake-digits-row justify-content-center justify-content-sm-start my-2">';
    foreach ($digits as $d) {
        $html .= '      <div class="handshake-digit-tile">' . e($d) . '</div>';
    }
    $html .= '        </div>';
    $html .= '        <p class="text-muted small mb-3" style="font-size:0.82rem;">Present this QR code or 6-digit PIN to the supplying donor upon physical collection. The donor will scan or enter it to verify and release supplies.</p>';
    $html .= '        <div class="d-flex flex-wrap justify-content-center justify-content-sm-start gap-2">';
    $html .= '          <button type="button" class="btn btn-sm btn-outline-teal rounded-pill px-3" onclick="navigator.clipboard.writeText(\'' . e($code) . '\'); alert(\'Handshake PIN ' . e($code) . ' copied to clipboard!\');"><i class="fas fa-copy me-1"></i> Copy PIN</button>';
    $html .= '          <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="window.print();"><i class="fas fa-print me-1"></i> Print Pass</button>';
    $html .= '        </div>';
    $html .= '      </div>';
    $html .= '    </div>';
    $html .= '  </div>';
    $html .= '</div>';
    return $html;
}

/**
 * Create an internal notification
 */
function create_notification($pdo, $user_id, $title, $message, $link = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        return $stmt->execute([$user_id, $title, $message, $link]);
    } catch (PDOException $e) {
        error_log("Failed to insert notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for a user
 */
function get_unread_notifications_count($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Get recent notifications for a user
 */
function get_user_notifications($pdo, $user_id, $limit = 5) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Log redistribution impact metric
 */
function record_impact($pdo, $request_id, $quantity) {
    try {
        // Average estimates: 0.12 kg medical packaging waste per unit, ~35 INR average saved
        $waste_avoided = round($quantity * 0.12, 2);
        $value_saved = round($quantity * 35.0, 2);
        
        $stmt = $pdo->prepare("INSERT INTO impact_metrics (request_id, quantity_redistributed, estimated_waste_avoided, estimated_value_saved, organizations_helped, created_at) 
                               VALUES (?, ?, ?, ?, 1, NOW())");
        return $stmt->execute([$request_id, $quantity, $waste_avoided, $value_saved]);
    } catch (PDOException $e) {
        error_log("Failed to log impact: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieve organization details for a user
 */
function get_user_organization($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM organizations WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Look up handover consignment by 6-digit code or QR code payload
 */
function lookup_handover_consignment($pdo, $rawInput, $currentUser = null) {
    $rawInput = trim((string)$rawInput);
    if (empty($rawInput)) {
        return ['success' => false, 'error_type' => 'empty', 'error' => 'Please enter a 6-digit handshake code or scan a valid MediCycle QR code.'];
    }

    $code = '';
    $requestId = null;

    if (preg_match('/MEDICYCLE-HANDSHAKE:(?:REQ-(\d+):)?CODE-([0-9]{6})/i', $rawInput, $matches)) {
        $requestId = !empty($matches[1]) ? (int)$matches[1] : null;
        $code = $matches[2];
    } elseif (preg_match('/(\d{6})/', $rawInput, $matches)) {
        $code = $matches[1];
        if (preg_match('/REQ-(\d+)/i', $rawInput, $reqMatches)) {
            $requestId = (int)$reqMatches[1];
        }
    } else {
        return ['success' => false, 'error_type' => 'invalid_format', 'error' => 'Invalid code format. Expected a 6-digit numeric handshake code or a valid QR code payload.'];
    }

    try {
        $sql = "SELECT r.*, ms.supply_name, ms.unit, ms.expiry_date, ms.condition_status, ms.batch_number, ms.supplier_id,
                       c.category_name,
                       su.name AS supplier_name, su.email AS supplier_email, su.phone AS supplier_phone,
                       so.organization_name AS supplier_org, so.address AS supplier_address, so.city AS supplier_city,
                       nu.name AS requester_name, nu.email AS requester_email, nu.phone AS requester_phone,
                       no.organization_name AS requester_org, no.address AS requester_address, no.city AS requester_city,
                       d.id AS delivery_id, d.delivery_status, d.pickup_status, d.assigned_at, d.delivered_at,
                       del_u.name AS courier_name, del_u.phone AS courier_phone
                FROM requests r
                JOIN medical_supplies ms ON r.supply_id = ms.id
                LEFT JOIN categories c ON ms.category_id = c.id
                JOIN users su ON ms.supplier_id = su.id
                LEFT JOIN organizations so ON su.id = so.user_id
                JOIN users nu ON r.requester_id = nu.id
                LEFT JOIN organizations no ON nu.id = no.user_id
                LEFT JOIN deliveries d ON r.id = d.request_id
                LEFT JOIN users del_u ON d.delivery_partner_id = del_u.id
                WHERE r.handover_code = ?";
        
        $params = [$code];
        if ($requestId !== null) {
            $sql .= " AND r.id = ?";
            $params[] = $requestId;
        }
        $sql .= " ORDER BY r.id DESC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $consignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$consignment) {
            return [
                'success' => false,
                'error_type' => 'wrong_code',
                'code' => $code,
                'error' => "Incorrect Handshake Code! The code '{$code}' does not match any consignment in our records. Please verify the 6-digit PIN and try again."
            ];
        }

        $isCompleted = ($consignment['status'] === 'Completed');

        return [
            'success' => true,
            'already_completed' => $isCompleted,
            'code' => $code,
            'consignment' => $consignment,
            'notice' => $isCompleted ? "This consignment was already completed on " . format_datetime($consignment['completed_at']) . ". Handshake codes cannot be reused." : null
        ];
    } catch (PDOException $e) {
        error_log("lookup_handover_consignment error: " . $e->getMessage());
        return ['success' => false, 'error_type' => 'db_error', 'error' => 'Database error while searching for consignment: ' . $e->getMessage()];
    }
}

/**
 * Universal Handover Verification & Completion Engine
 * Validates 6-digit code or QR code payload:
 * 1. If wrong code: returns error ('wrong_code').
 * 2. If code was already completed: returns error ('already_completed') and strictly blocks 2nd time use.
 * 3. If code matches active consignment: marks requests.status = 'Completed', deliveries.delivery_status = 'Delivered',
 *    records impact, sends mutual notifications, and returns success!
 */
function verify_and_complete_handover($pdo, $rawInput, $currentUser = null) {
    $rawInput = trim((string)$rawInput);
    if (empty($rawInput)) {
        return [
            'success' => false,
            'error_type' => 'empty',
            'error' => 'Please enter a 6-digit handshake code or scan a valid MediCycle QR pass.'
        ];
    }

    $code = '';
    $requestId = null;

    if (preg_match('/MEDICYCLE-HANDSHAKE:(?:REQ-(\d+):)?CODE-([0-9]{6})/i', $rawInput, $matches)) {
        $requestId = !empty($matches[1]) ? (int)$matches[1] : null;
        $code = $matches[2];
    } elseif (preg_match('/(\d{6})/', $rawInput, $matches)) {
        $code = $matches[1];
        if (preg_match('/REQ-(\d+)/i', $rawInput, $reqMatches)) {
            $requestId = (int)$reqMatches[1];
        }
    } else {
        return [
            'success' => false,
            'error_type' => 'invalid_format',
            'error' => "Invalid Code Format! Expected a 6-digit numeric PIN (e.g. 582491) or a valid MediCycle QR pass."
        ];
    }

    try {
        $sql = "SELECT r.*, ms.supply_name, ms.unit, ms.expiry_date, ms.condition_status, ms.batch_number, ms.supplier_id,
                       c.category_name,
                       su.name AS supplier_name, su.email AS supplier_email, su.phone AS supplier_phone,
                       so.organization_name AS supplier_org, so.address AS supplier_address, so.city AS supplier_city,
                       nu.name AS requester_name, nu.email AS requester_email, nu.phone AS requester_phone,
                       no.organization_name AS requester_org, no.address AS requester_address, no.city AS requester_city,
                       d.id AS delivery_id, d.delivery_status, d.pickup_status, d.assigned_at, d.delivered_at,
                       del_u.name AS courier_name, del_u.phone AS courier_phone
                FROM requests r
                JOIN medical_supplies ms ON r.supply_id = ms.id
                LEFT JOIN categories c ON ms.category_id = c.id
                JOIN users su ON ms.supplier_id = su.id
                LEFT JOIN organizations so ON su.id = so.user_id
                JOIN users nu ON r.requester_id = nu.id
                LEFT JOIN organizations no ON nu.id = no.user_id
                LEFT JOIN deliveries d ON r.id = d.request_id
                LEFT JOIN users del_u ON d.delivery_partner_id = del_u.id
                WHERE r.handover_code = ?";

        $params = [$code];
        if ($requestId !== null) {
            $sql .= " AND r.id = ?";
            $params[] = $requestId;
        }
        $sql .= " ORDER BY r.id DESC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $consignment = $stmt->fetch(PDO::FETCH_ASSOC);

        // 1. Code is Wrong (Does not exist in DB)
        if (!$consignment) {
            return [
                'success' => false,
                'error_type' => 'wrong_code',
                'code' => $code,
                'error' => "Incorrect Handshake Code! The 6-digit PIN '{$code}' does not match any consignment in our records. Please verify the code and try again."
            ];
        }

        // 2. Code was ALREADY USED (Block 2nd time use strictly)
        if ($consignment['status'] === 'Completed') {
            return [
                'success' => false,
                'error_type' => 'already_completed',
                'code' => $code,
                'consignment' => $consignment,
                'error' => "Security Notice: Handshake Code Already Used! Consignment ({$consignment['supply_name']}) was already verified and delivery completed on " . format_datetime($consignment['completed_at']) . ". Handshake codes are strictly single-use and cannot be used a second time."
            ];
        }

        // 3. Status check: ensure consignment is approved / ready
        if (!in_array($consignment['status'], ['Approved', 'Ready for Handover', 'Accepted', 'In Transit', 'Collected'])) {
            return [
                'success' => false,
                'error_type' => 'invalid_status',
                'code' => $code,
                'consignment' => $consignment,
                'error' => "This consignment ({$consignment['supply_name']}) is currently '{$consignment['status']}'. It must be approved before physical delivery can be verified."
            ];
        }

        // 4. Code MATCHES and is ready -> Mark delivery as COMPLETED!
        $pdo->beginTransaction();

        $lockStmt = $pdo->prepare("SELECT r.id, r.status, r.requested_quantity, ms.unit, ms.supply_name, ms.supplier_id, r.requester_id 
                                   FROM requests r 
                                   JOIN medical_supplies ms ON r.supply_id = ms.id 
                                   WHERE r.id = ? FOR UPDATE");
        $lockStmt->execute([$consignment['id']]);
        $lockedReq = $lockStmt->fetch(PDO::FETCH_ASSOC);

        if ($lockedReq['status'] === 'Completed') {
            $pdo->rollBack();
            return [
                'success' => false,
                'error_type' => 'already_completed',
                'code' => $code,
                'consignment' => $consignment,
                'error' => "Security Notice: Handshake Code Already Used! This consignment was already verified and completed. This code cannot be used a second time."
            ];
        }

        // 1. Mark Request Completed
        $updReq = $pdo->prepare("UPDATE requests SET status = 'Completed', completed_at = NOW() WHERE id = ?");
        $updReq->execute([$consignment['id']]);

        // 2. Mark Delivery Delivered (if delivery consignment exists)
        $actorName = $currentUser['name'] ?? ($currentUser['role'] ?? 'System User');
        $updDel = $pdo->prepare("UPDATE deliveries SET delivery_status = 'Delivered', pickup_status = 'Picked Up', delivered_at = NOW(), tracking_notes = CONCAT(COALESCE(tracking_notes, ''), '\n[', NOW(), '] Verified via handshake code by ', ?) WHERE request_id = ?");
        $updDel->execute([$actorName, $consignment['id']]);

        // 2b. Mark Medical Supply as Transferred if quantity is depleted
        $suppStmt = $pdo->prepare("SELECT id, quantity FROM medical_supplies WHERE id = ?");
        $suppStmt->execute([$consignment['supply_id']]);
        $suppRow = $suppStmt->fetch(PDO::FETCH_ASSOC);
        if ($suppRow && (int)$suppRow['quantity'] <= 0) {
            $updSupp = $pdo->prepare("UPDATE medical_supplies SET status = 'Transferred', updated_at = NOW() WHERE id = ?");
            $updSupp->execute([$consignment['supply_id']]);
        }

        // 3. Record Impact
        $qty = (int)$consignment['requested_quantity'];
        record_impact($pdo, $consignment['id'], $qty);

        // 4. Notify Supplier
        create_notification(
            $pdo,
            $consignment['supplier_id'],
            'Handover Completed & Verified!',
            "Consignment for {$qty} {$consignment['unit']} of {$consignment['supply_name']} delivery was successfully verified with 6-digit handshake code. Stock officially transferred.",
            'supplier/history.php'
        );

        // 5. Notify Recipient Clinic
        create_notification(
            $pdo,
            $consignment['requester_id'],
            'Supplies Received & Delivery Completed!',
            "Your clinic has verified and received {$qty} {$consignment['unit']} of {$consignment['supply_name']}. Verification complete.",
            'ngo/history.php'
        );

        $pdo->commit();

        $completedConsignment = array_merge($consignment, [
            'status' => 'Completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'delivery_status' => 'Delivered',
            'pickup_status' => 'Picked Up'
        ]);

        return [
            'success' => true,
            'is_newly_completed' => true,
            'code' => $code,
            'message' => "Delivery Successfully Completed! Handshake verified for {$qty} {$consignment['unit']} of {$consignment['supply_name']}.",
            'consignment' => $completedConsignment
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("verify_and_complete_handover error: " . $e->getMessage());
        return [
            'success' => false,
            'error_type' => 'database_error',
            'error' => 'System error during handover verification: ' . $e->getMessage()
        ];
    }
}

/**
 * Execute verified physical handover completion (Existing helper wrapper)
 */
function execute_handover_verification($pdo, $requestId, $enteredCode, $currentUser) {
    return verify_and_complete_handover($pdo, $enteredCode, $currentUser);
}

