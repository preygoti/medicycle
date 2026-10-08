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
 * Render Bootstrap badge for status (Handover & Collection lifecycle)
 */
function status_badge($status) {
    $status = trim($status);
    $lower = strtolower($status);
    
    $class = match($lower) {
        'available', 'completed', 'received', 'verified' => 'bg-success text-white',
        'accepted' => 'bg-primary text-white',
        'ready for handover' => 'bg-info text-dark',
        'collected' => 'bg-secondary text-white',
        'pending', 'reserved' => 'bg-warning text-dark',
        'rejected', 'cancelled', 'inactive', 'suspended' => 'bg-danger text-white',
        default => 'bg-secondary text-white'
    };
    
    $icon = match($lower) {
        'available' => 'fa-check-circle',
        'accepted' => 'fa-thumbs-up',
        'ready for handover' => 'fa-box-open',
        'collected' => 'fa-people-carry-box',
        'completed', 'received' => 'fa-check-double',
        'pending' => 'fa-clock',
        'rejected', 'cancelled' => 'fa-times-circle',
        default => 'fa-circle'
    };
    
    return '<span class="badge ' . $class . ' px-2 py-1"><i class="fas ' . $icon . ' me-1 small"></i>' . e(ucfirst($status)) . '</span>';
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
 * Generate a unique handover verification code
 */
function generate_handover_code() {
    return 'HO-' . rand(1000, 9999);
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
