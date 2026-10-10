<?php
/**
 * MediCycle - Smart / AI Algorithmic Intelligence Module
 * 
 * Features:
 * 1. Smart Supply-Requirement Matchmaking (0 - 100% Match Engine)
 * 2. Waste-Risk & Shelf-Life Priority Scoring (Low / Medium / High / Critical)
 * 3. Demand & Consumption Analytics Extraction
 */

/**
 * Calculate transparent 0-100 Priority Score for a medical supply listing
 * Factors: Expiry proximity, Packaging integrity, Volume, Market demand
 */
function smart_calculate_priority($expiry_date, $condition_status, $quantity, $category_id = null): array {
    $score = 40; // Base score
    $factors = [];

    // Factor 1: Expiry shelf-life proximity
    $today = new DateTime();
    $expiry = new DateTime($expiry_date);
    $diffDays = (int)$today->diff($expiry)->format('%r%a');

    if ($diffDays < 0) {
        return [
            'score' => 0,
            'level' => 'Expired',
            'explanation' => 'Item is expired. Strictly ineligible for redistribution.'
        ];
    } elseif ($diffDays <= 60) {
        $score += 45;
        $factors[] = 'Expiring within 60 days (Urgent redistribution recommended)';
    } elseif ($diffDays <= 180) {
        $score += 35;
        $factors[] = 'Expiring within 6 months (Elevated priority)';
    } elseif ($diffDays <= 365) {
        $score += 20;
        $factors[] = '1 year remaining shelf life';
    } else {
        $score += 10;
        $factors[] = 'Extended shelf life (> 1 year)';
    }

    // Factor 2: Packaging condition
    if ($condition_status === 'Sterile Sealed') {
        $score += 15;
        $factors[] = 'Sterile sealed packaging provides maximum clinical utility';
    } elseif ($condition_status === 'New / Unopened') {
        $score += 10;
        $factors[] = 'New / unopened stock';
    }

    // Factor 3: Quantity volume
    if ($quantity >= 300) {
        $score += 5;
        $factors[] = 'High quantity bulk batch';
    }

    $finalScore = min(100, max(1, $score));
    $level = 'Low';
    if ($finalScore >= 75) {
        $level = 'Critical';
    } elseif ($finalScore >= 65) {
        $level = 'High';
    } elseif ($finalScore >= 45) {
        $level = 'Medium';
    }

    return [
        'score' => $finalScore,
        'level' => $level,
        'factors' => $factors,
        'explanation' => implode('. ', $factors)
    ];
}

/**
 * Smart Matchmaking Algorithm: Compares a Medical Supply with an NGO Requirement
 * Generates recommendation percentage (0-100%) and plain-English clinical explanation.
 */
function smart_evaluate_match(array $supply, array $requirement): array {
    $score = 0;
    $reasons = [];

    // Factor A: Direct Category Match (Weight: 35)
    if ((int)$supply['category_id'] === (int)$requirement['category_id']) {
        $score += 35;
        $reasons[] = 'Exact category alignment with clinic need';
    }

    // Factor B: Supply Name / Keyword Similarity (Weight: 25)
    $supplyWords = array_filter(explode(' ', strtolower($supply['supply_name'])));
    $reqWords = array_filter(explode(' ', strtolower($requirement['supply_name'])));
    $intersect = array_intersect($supplyWords, $reqWords);
    if (!empty($intersect)) {
        $score += 25;
        $reasons[] = 'Supply keywords matched (' . htmlspecialchars(implode(', ', array_slice($intersect, 0, 3))) . ')';
    }

    // Factor C: Geographic Proximity / Same City (Weight: 20)
    $supplyCity = strtolower(trim($supply['city'] ?? $supply['location'] ?? ''));
    $reqCity = strtolower(trim($requirement['city'] ?? ''));
    if (!empty($supplyCity) && !empty($reqCity) && $supplyCity === $reqCity) {
        $score += 20;
        $reasons[] = 'Located in the same city (' . ucfirst($supplyCity) . ') for rapid delivery';
    } else {
        $score += 5;
    }

    // Factor D: Quantity Adequacy (Weight: 10)
    if ((int)$supply['quantity'] >= (int)$requirement['required_quantity']) {
        $score += 10;
        $reasons[] = 'Full quantity requirement can be satisfied';
    } elseif ((int)$supply['quantity'] > 0) {
        $score += 5;
        $reasons[] = 'Partial quantity available to assist';
    }

    // Factor E: Urgency Boost (Weight: 10)
    $urgency = $requirement['urgency'] ?? 'Medium';
    if (in_array($urgency, ['High', 'Critical'])) {
        $score += 10;
        $reasons[] = 'High clinic urgency prioritizes allocation';
    }

    $finalScore = min(100, max(0, $score));
    $isRecommended = $finalScore >= 60;

    return [
        'score' => $finalScore,
        'is_recommended' => $isRecommended,
        'badge' => $isRecommended ? 'Recommended Match' : 'Possible Match',
        'reasons' => $reasons,
        'explanation' => 'Recommended because: ' . implode('; ', $reasons) . '.'
    ];
}

/**
 * Historical Demand Analytics Helper
 */
function smart_get_demand_analytics(PDO $pdo): array {
    $analytics = [
        'top_demanded_categories' => [],
        'frequently_requested_supplies' => [],
        'monthly_redistribution_trends' => [],
        'most_active_organizations' => []
    ];

    try {
        // Top demanded categories by request volume
        $catStmt = $pdo->query("
            SELECT c.category_name, COUNT(r.id) as request_count, COALESCE(SUM(r.requested_quantity), 0) as total_units
            FROM categories c
            LEFT JOIN medical_supplies ms ON ms.category_id = c.id
            LEFT JOIN requests r ON r.supply_id = ms.id
            GROUP BY c.id, c.category_name
            ORDER BY request_count DESC
            LIMIT 5
        ");
        $analytics['top_demanded_categories'] = $catStmt->fetchAll();

        // Frequently requested supplies
        $supStmt = $pdo->query("
            SELECT ms.supply_name, c.category_name, COUNT(r.id) as total_requests, SUM(r.requested_quantity) as total_quantity
            FROM requests r
            JOIN medical_supplies ms ON r.supply_id = ms.id
            JOIN categories c ON ms.category_id = c.id
            GROUP BY ms.supply_name, c.category_name
            ORDER BY total_requests DESC
            LIMIT 5
        ");
        $analytics['frequently_requested_supplies'] = $supStmt->fetchAll();

        // Monthly trends
        $trendStmt = $pdo->query("
            SELECT DATE_FORMAT(created_at, '%b %Y') as month_label, COUNT(*) as request_count, COALESCE(SUM(requested_quantity), 0) as total_units
            FROM requests
            GROUP BY DATE_FORMAT(created_at, '%b %Y'), YEAR(created_at), MONTH(created_at)
            ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC
            LIMIT 6
        ");
        $analytics['monthly_redistribution_trends'] = $trendStmt->fetchAll();

        // Most active organizations
        $orgStmt = $pdo->query("
            SELECT o.organization_name, o.organization_type, o.city, u.role,
                   COUNT(DISTINCT r.id) as interaction_count
            FROM organizations o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN requests r ON (r.requester_id = u.id)
            GROUP BY o.id, o.organization_name, o.organization_type, o.city, u.role
            ORDER BY interaction_count DESC
            LIMIT 5
        ");
        $analytics['most_active_organizations'] = $orgStmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Error fetching smart demand analytics: " . $e->getMessage());
    }

    return $analytics;
}
