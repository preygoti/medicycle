<?php
/**
 * MediCycle - Public Live Metrics Polling API
 * Lightweight JSON endpoint queried to update the homepage redistribution metrics in real time.
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    $statSupplies = (int)$pdo->query("SELECT COUNT(*) FROM medical_supplies WHERE status = 'Available'")->fetchColumn();
    $statOrgs = (int)$pdo->query("SELECT COUNT(*) FROM organizations WHERE verification_status = 'verified'")->fetchColumn();
    if ($statOrgs === 0) {
        $statOrgs = (int)$pdo->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
    }
    $statTransfers = (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status IN ('Completed', 'Received')")->fetchColumn();
    $statUnits = (int)$pdo->query("SELECT COALESCE(SUM(requested_quantity), 0) FROM requests WHERE status IN ('Completed', 'Received')")->fetchColumn();
    $statWaste = (float)$pdo->query("SELECT COALESCE(SUM(estimated_waste_avoided), 0) FROM impact_metrics")->fetchColumn();

    echo json_encode([
        'success'   => true,
        'supplies'  => $statSupplies,
        'orgs'      => $statOrgs,
        'transfers' => $statTransfers,
        'units'     => $statUnits,
        'waste'     => round($statWaste, 1),
        'timestamp' => time(),
        'updated_at'=> date('h:i:s A')
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success'   => false,
        'error'     => 'metrics_unavailable'
    ]);
}
